<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'failed';
        }

        try {
            $key = 'health:'.bin2hex(random_bytes(8));
            Cache::put($key, 'ok', 5);
            $checks['cache'] = Cache::pull($key) === 'ok' ? 'ok' : 'failed';
        } catch (Throwable) {
            $checks['cache'] = 'failed';
        }

        if ((bool) config('observability.meilisearch_required')) {
            try {
                $checks['search'] = Http::timeout(2)->get(rtrim((string) config('scout.meilisearch.host'), '/').'/health')->successful() ? 'ok' : 'failed';
            } catch (Throwable) {
                $checks['search'] = 'failed';
            }
        }

        $ready = ! in_array('failed', $checks, true);

        return response()->json(['status' => $ready ? 'ok' : 'unavailable', 'checks' => $checks], $ready ? 200 : 503);
    }
}
