<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PullService;
use Illuminate\Http\Request;

/** For hosts without shell cron: call GET|POST /api/cron/fetch with the CRON_SECRET (Bearer or X-Cron-Token header). */
class CronController extends Controller
{
    public function __invoke(Request $request, PullService $pull)
    {
        $secret = (string) config('services.cron.secret');
        $given = (string) ($request->bearerToken() ?: $request->header('X-Cron-Token'));
        abort_unless($secret !== '' && hash_equals($secret, $given), 401, __('Invalid cron token.'));

        $results = $pull->run($request->query('device'), $request->boolean('force'));

        return response()->json([
            'devices' => count($results),
            'ok' => count(array_filter($results, fn ($r) => $r['ok'])),
            'failed' => count(array_filter($results, fn ($r) => ! $r['ok'])),
            'results' => $results,
        ]);
    }
}
