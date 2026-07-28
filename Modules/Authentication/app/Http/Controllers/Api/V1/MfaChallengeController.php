<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Actions\IssueStoreToken;
use Modules\Authentication\Actions\VerifyMfaCode;
use Modules\Authentication\Http\Requests\MfaChallengeRequest;
use Modules\Authentication\Http\Resources\UserResource;
use Modules\Authentication\Models\User;
use Modules\Authentication\Support\MfaChallengeStore;

final class MfaChallengeController extends Controller
{
    public function store(
        MfaChallengeRequest $request,
        MfaChallengeStore $challenges,
        VerifyMfaCode $verify,
        IssueStoreToken $tokens,
    ): JsonResponse {
        return $challenges->consume(
            $request->string('challenge_token')->toString(),
            function (array $payload) use ($request, $challenges, $verify, $tokens): JsonResponse {
                $user = User::query()->find($payload['user_id']);
                if ($user === null) {
                    throw ValidationException::withMessages([
                        'challenge_token' => ['The MFA challenge is invalid or has expired.'],
                    ]);
                }
                if ($payload['purpose'] === MfaChallengeStore::SESSION && ! $request->hasSession()) {
                    abort(400, 'A stateful session is required to complete this MFA challenge.');
                }

                $challenges->assertMatchesUser($payload, $user);
                $user = $verify->handle(
                    $user,
                    $request->filled('code') ? $request->string('code')->toString() : null,
                    $request->filled('recovery_code') ? $request->string('recovery_code')->toString() : null,
                );

                if ($payload['purpose'] === MfaChallengeStore::SESSION) {
                    Auth::guard('web')->login($user);
                    $request->session()->regenerate();

                    return response()->json([
                        'mfa_required' => false,
                        'user' => new UserResource($user),
                    ]);
                }

                $context = $payload['context'];
                $issued = $tokens->issue(
                    $user,
                    (string) $context['device_name'],
                    (string) $context['store_id'],
                    ['store:access'],
                    null,
                    [
                        'ip' => $context['ip'] ?? $request->ip(),
                        'user_agent' => $context['user_agent'] ?? $request->userAgent(),
                        'mfa_verified' => true,
                    ],
                );

                return response()->json([
                    'mfa_required' => false,
                    'token' => $issued->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $issued->accessToken->expires_at?->toISOString(),
                ]);
            },
        );
    }
}
