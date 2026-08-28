<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use DateTimeZone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    /**
     * 统计页面：近七日三色评价数（按用户浏览器时区分桶）。
     */
    public function show(Request $request): Response
    {
        $timezone = $this->resolveTimezone($request->string('tz')->toString());

        return Inertia::render('stats/index', [
            'stats' => $this->stats->daily($request->user(), $timezone),
            'timezone' => $timezone,
        ]);
    }

    /**
     * 校验时区标识，非法时回退 UTC。
     */
    private function resolveTimezone(string $timezone): string
    {
        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (\Exception) {
            return 'UTC';
        }
    }
}
