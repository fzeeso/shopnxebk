<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpFoundation\Response;

final class RecordRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('scalability.request_performance.enabled', false)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $response = null;

        try {
            $response = $next($request);
            $durationMs = $this->elapsedMilliseconds($startedAt);

            if ((bool) config('scalability.request_performance.server_timing_header', false)) {
                $response->headers->set('Server-Timing', 'app;dur='.number_format($durationMs, 2, '.', ''));
            }

            return $response;
        } finally {
            $durationMs = $this->elapsedMilliseconds($startedAt);
            $slowThreshold = max(1, (int) config('scalability.request_performance.slow_request_ms', 1000));
            if ($durationMs >= $slowThreshold || $this->sampled()) {
                $store = $request->attributes->get('store');
                Log::info('request.performance', [
                    'request_id' => $request->attributes->get('request_id'),
                    'method' => $request->getMethod(),
                    'route' => $request->route()?->getName(),
                    'status' => $response?->getStatusCode() ?? 500,
                    'duration_ms' => round($durationMs, 2),
                    'database_query_count' => (int) $request->attributes->get('database_query_count', 0),
                    'database_duration_ms' => round((float) $request->attributes->get('database_duration_ms', 0.0), 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'store_id' => $store instanceof Store ? $store->getKey() : null,
                ]);
            }
        }
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    private function sampled(): bool
    {
        $rate = min(1.0, max(0.0, (float) config('scalability.request_performance.sample_rate', 0.05)));

        return $rate >= 1.0 || ($rate > 0.0 && mt_rand() / mt_getrandmax() < $rate);
    }
}
