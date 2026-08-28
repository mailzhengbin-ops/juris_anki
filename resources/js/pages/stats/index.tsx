import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { Card, CardContent } from '@/components/ui/card';

type DayStat = {
    date: string;
    label: string;
    known: number;
    fuzzy: number;
    forgotten: number;
};

type PageProps = {
    stats: {
        days: DayStat[];
        totals: { known: number; fuzzy: number; forgotten: number };
    };
    timezone: string;
};

const COLORS = {
    known: 'bg-green-500',
    fuzzy: 'bg-amber-500',
    forgotten: 'bg-red-500',
} as const;

export default function Stats() {
    const { stats, timezone } = usePage<PageProps>().props;

    // 以浏览器时区对齐服务端分桶（首次加载可能为 UTC）
    useEffect(() => {
        const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        if (browserTimezone && browserTimezone !== timezone) {
            router.reload({ only: ['stats', 'timezone'], data: { tz: browserTimezone } });
        }
    }, [timezone]);

    const maxTotal = useMemo(
        () =>
            Math.max(
                1,
                ...stats.days.map((day) => day.known + day.fuzzy + day.forgotten),
            ),
        [stats.days],
    );

    return (
        <>
            <Head title="统计" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-lg font-semibold">近七日背诵统计</h2>
                    <p className="text-sm text-muted-foreground">
                        按 {timezone} 时区自然日统计；同卡同日多次评价只计最后一次
                    </p>
                </div>

                <Card>
                    <CardContent className="p-6">
                        {/* 堆叠柱状图 */}
                        <div className="flex h-48 items-end gap-3">
                            {stats.days.map((day) => {
                                const total = day.known + day.fuzzy + day.forgotten;
                                const height = (total / maxTotal) * 100;

                                return (
                                    <div
                                        key={day.date}
                                        className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                        title={`${day.label}\n认识 ${day.known} · 模糊 ${day.fuzzy} · 忘记 ${day.forgotten}`}
                                    >
                                        {total > 0 ? (
                                            <div
                                                className="flex w-full max-w-10 flex-col overflow-hidden rounded-sm"
                                                style={{ height: `${height}%` }}
                                            >
                                                <div
                                                    className={`${COLORS.forgotten} w-full`}
                                                    style={{
                                                        flexGrow: day.forgotten,
                                                    }}
                                                />
                                                <div
                                                    className={`${COLORS.fuzzy} w-full`}
                                                    style={{ flexGrow: day.fuzzy }}
                                                />
                                                <div
                                                    className={`${COLORS.known} w-full`}
                                                    style={{
                                                        flexGrow: day.known,
                                                    }}
                                                />
                                            </div>
                                        ) : (
                                            <div className="w-full max-w-10 rounded-sm bg-muted" />
                                        )}
                                        <span className="text-xs text-muted-foreground">
                                            {day.label}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>

                        {/* 图例 */}
                        <div className="mt-4 flex items-center justify-center gap-6 text-sm">
                            <span className="flex items-center gap-1.5">
                                <span className={`size-3 rounded-sm ${COLORS.known}`} />
                                认识
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className={`size-3 rounded-sm ${COLORS.fuzzy}`} />
                                模糊
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className={`size-3 rounded-sm ${COLORS.forgotten}`} />
                                忘记
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* 本周合计 */}
                <Card>
                    <CardContent className="flex items-center justify-around p-6 text-center">
                        <div>
                            <p className="text-sm text-muted-foreground">认识</p>
                            <p className="text-2xl font-semibold text-green-600">
                                {stats.totals.known}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">模糊</p>
                            <p className="text-2xl font-semibold text-amber-600">
                                {stats.totals.fuzzy}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">忘记</p>
                            <p className="text-2xl font-semibold text-red-600">
                                {stats.totals.forgotten}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
