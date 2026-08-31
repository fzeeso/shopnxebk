<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use App\Models\IdempotencyRecord;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Authentication\Models\User;
use Throwable;

final readonly class IdempotencyExecutor
{
    private const REPLAYABLE_HEADERS = [
        'Content-Type',
        'Content-Language',
        'Location',
        'ETag',
    ];

    public function __construct(
        private IdempotencyKeyParser $keys,
        private IdempotencyRequestFactory $requests,
    ) {}

    /**
     * @param  Closure(): void  $preflight
     * @param  Closure(): JsonResponse  $action
     */
    public function execute(
        Request $request,
        string $operation,
        Closure $preflight,
        Closure $action,
    ): JsonResponse {
        $policy = IdempotencyPolicy::forOperation($operation);
        if (! $policy->enabled || $policy->mode === IdempotencyMode::Excluded) {
            return $action();
        }

        $present = $this->keys->isPresent($request);
        if (! $present && $policy->mode === IdempotencyMode::Supported) {
            return $action();
        }

        $preflight();

        try {
            $key = $this->keys->parse($request);
        } catch (InvalidArgumentException $exception) {
            return $this->error($request, $exception->getMessage(), 'idempotency_key_invalid', 400);
        }

        if ($key === null) {
            return $this->error(
                $request,
                'This operation requires an Idempotency-Key header.',
                'idempotency_key_required',
                400,
            );
        }

        /** @var User|null $actor */
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->error($request, 'Unauthenticated.', 'idempotency_unauthenticated', 401);
        }

        try {
            $idempotentRequest = $this->requests->make(
                request: $request,
                actor: $actor,
                operation: $operation,
                key: $key,
                ttlSeconds: $policy->ttlSeconds,
            );
        } catch (Throwable $exception) {
            return $this->unavailable($request, $operation, $exception);
        }

        try {
            $record = $this->findActive($idempotentRequest);
            if ($record instanceof IdempotencyRecord) {
                return $this->replayOrReject($request, $record, $idempotentRequest);
            }
        } catch (QueryException $exception) {
            return $this->unavailable($request, $operation, $exception);
        }

        try {
            return DB::transaction(function () use ($action, $idempotentRequest, $request): JsonResponse {
                if (! $this->acquireLock($idempotentRequest)) {
                    return $this->error(
                        $request,
                        'An operation with this Idempotency-Key is still processing.',
                        'idempotency_in_progress',
                        409,
                        ['Retry-After' => '1'],
                    );
                }

                $record = $this->findAny($idempotentRequest);
                if ($record instanceof IdempotencyRecord && $record->expires_at?->isPast()) {
                    $record->delete();
                    $record = null;
                }

                if ($record instanceof IdempotencyRecord) {
                    return $this->replayOrReject($request, $record, $idempotentRequest);
                }

                $response = $action();
                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    throw new UncacheableIdempotencyResponse($response);
                }

                $body = (string) $response->getContent();
                $maximumBytes = max(1024, (int) config('idempotency.maximum_response_bytes', 1048576));
                if (strlen($body) > $maximumBytes) {
                    throw new IdempotencyResponseRejected('The idempotent response exceeds the configured snapshot limit.');
                }

                IdempotencyRecord::query()->create([
                    'scope_hash' => $idempotentRequest->scopeHash,
                    'key_hash' => $idempotentRequest->keyHash,
                    'fingerprint_version' => (int) config('idempotency.fingerprint_version', 1),
                    'request_fingerprint' => $idempotentRequest->fingerprint,
                    'actor_id' => $idempotentRequest->actorId,
                    'store_id' => $idempotentRequest->storeId,
                    'route_name' => $idempotentRequest->operation,
                    'http_method' => strtoupper($request->getMethod()),
                    'response_status' => $response->getStatusCode(),
                    'response_headers' => $this->captureHeaders($response),
                    'response_body_ciphertext' => Crypt::encryptString($body),
                    'response_body_sha256' => hash('sha256', $body),
                    'original_request_id' => (string) $request->attributes->get('request_id'),
                    'completed_at' => now(),
                    'expires_at' => now()->addSeconds($idempotentRequest->ttlSeconds),
                ]);

                return $response;
            }, attempts: 1);
        } catch (UncacheableIdempotencyResponse $exception) {
            return $exception->response;
        } catch (QueryException $exception) {
            return $this->unavailable($request, $operation, $exception);
        } catch (IdempotencyResponseRejected $exception) {
            Log::error('idempotency.response_rejected', [
                'request_id' => $request->attributes->get('request_id'),
                'operation' => $operation,
                'message' => $exception->getMessage(),
            ]);

            return $this->error($request, 'The protected response could not be recorded.', 'idempotency_response_invalid', 500);
        }
    }

    private function findActive(IdempotencyRequest $request): ?IdempotencyRecord
    {
        return IdempotencyRecord::query()
            ->where('scope_hash', $request->scopeHash)
            ->where('key_hash', $request->keyHash)
            ->where('expires_at', '>', now())
            ->first();
    }

    private function findAny(IdempotencyRequest $request): ?IdempotencyRecord
    {
        return IdempotencyRecord::query()
            ->where('scope_hash', $request->scopeHash)
            ->where('key_hash', $request->keyHash)
            ->first();
    }

    private function acquireLock(IdempotencyRequest $request): bool
    {
        $row = (array) DB::selectOne(
            'select pg_try_advisory_xact_lock(?, ?) as acquired',
            [$request->lockKeyHigh, $request->lockKeyLow],
        );
        $acquired = $row['acquired'] ?? false;

        return in_array($acquired, [true, 1, '1', 't', 'true'], true);
    }

    private function replayOrReject(
        Request $request,
        IdempotencyRecord $record,
        IdempotencyRequest $idempotentRequest,
    ): JsonResponse {
        if (! hash_equals((string) $record->request_fingerprint, $idempotentRequest->fingerprint)) {
            return $this->error(
                $request,
                'This Idempotency-Key was already used with a different request.',
                'idempotency_key_reused',
                422,
            );
        }

        try {
            $body = Crypt::decryptString((string) $record->response_body_ciphertext);
        } catch (DecryptException $exception) {
            return $this->unavailable($request, $idempotentRequest->operation, $exception);
        }

        if (! hash_equals((string) $record->response_body_sha256, hash('sha256', $body))) {
            return $this->unavailable(
                $request,
                $idempotentRequest->operation,
                new InvalidArgumentException('Stored idempotency response integrity check failed.'),
            );
        }

        $headers = is_array($record->response_headers) ? $record->response_headers : [];
        $headers['Idempotency-Replayed'] = 'true';
        $headers['Idempotency-Original-Request-ID'] = (string) $record->original_request_id;

        return new JsonResponse(
            data: $body,
            status: (int) $record->response_status,
            headers: $headers,
            json: true,
        );
    }

    /** @return array<string, list<string>> */
    private function captureHeaders(JsonResponse $response): array
    {
        $headers = [];
        foreach (self::REPLAYABLE_HEADERS as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->all($header);
            }
        }

        return $headers;
    }

    /** @param array<string, string> $headers */
    private function error(
        Request $request,
        string $message,
        string $code,
        int $status,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'request_id' => $request->attributes->get('request_id'),
        ], $status, $headers);
    }

    private function unavailable(Request $request, string $operation, Throwable $exception): JsonResponse
    {
        Log::error('idempotency.unavailable', [
            'request_id' => $request->attributes->get('request_id'),
            'operation' => $operation,
            'exception' => $exception::class,
        ]);

        return $this->error(
            $request,
            'Idempotency protection is temporarily unavailable.',
            'idempotency_unavailable',
            503,
        );
    }
}
