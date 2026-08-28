import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, MinusCircle, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ReciteController from '@/actions/App/Http/Controllers/ReciteController';
import SelectController from '@/actions/App/Http/Controllers/SelectController';
import MarkdownContent from '@/components/markdown-content';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { recite, select } from '@/routes';

type Rating = 'known' | 'fuzzy' | 'forgotten';

type RatingInfo = {
    label: string;
    className: string;
    Icon: typeof CheckCircle2;
};

const RATING_INFO: Record<Rating, RatingInfo> = {
    known: {
        label: '认识',
        className: 'bg-green-500 hover:bg-green-600',
        Icon: CheckCircle2,
    },
    fuzzy: {
        label: '模糊',
        className: 'bg-amber-500 hover:bg-amber-600',
        Icon: MinusCircle,
    },
    forgotten: {
        label: '忘记',
        className: 'bg-red-500 hover:bg-red-600',
        Icon: XCircle,
    },
};

type CardPayload = {
    id: number;
    question: string;
    answer: string;
    path: string;
    history: {
        total: number;
        known: number;
        fuzzy: number;
        forgotten: number;
        last_rating: Rating | null;
        last_at: string | null;
    };
};

type RecitationState = {
    source: 'selected' | 'mistake';
    phase: 'empty' | 'fresh' | 'active' | 'completed' | 'unavailable';
    progress: { evaluated: number; total: number };
    card: CardPayload | null;
    task: {
        stats: { known: number; fuzzy: number; forgotten: number };
    } | null;
};

type PageProps = {
    state: RecitationState;
};

function formatRelativeTime(iso: string): string {
    const diffMs = Date.now() - new Date(iso).getTime();
    const minutes = Math.floor(diffMs / 60000);

    if (minutes < 1) {
        return '刚刚';
    }

    if (minutes < 60) {
        return `${minutes} 分钟前`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours} 小时前`;
    }

    const days = Math.floor(hours / 24);

    return days === 1 ? '昨天' : `${days} 天前`;
}

function ReciteCard({
    card,
    disabled,
    onRate,
    onUndo,
}: {
    card: CardPayload;
    disabled: boolean;
    onRate: (rating: Rating) => void;
    onUndo: () => void;
}) {
    const [flipped, setFlipped] = useState(false);

    // 空格翻面（组件随卡片 key 重建，无需清理跨卡监听）
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.code === 'Space') {
                event.preventDefault();
                setFlipped((value) => !value);
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    return (
        <div className="relative min-h-[16rem]" onClick={() => setFlipped((value) => !value)}>
            {/* 正面：问题 */}
            <div
                className={cn(
                    'absolute inset-0 flex flex-col gap-4 rounded-xl border bg-card p-6 transition-opacity',
                    flipped && 'pointer-events-none opacity-0',
                )}
            >
                <p className="text-sm text-muted-foreground">{card.path}</p>
                <h2 className="text-lg font-semibold leading-relaxed">
                    {card.question}
                </h2>
                <p className="mt-auto text-sm text-muted-foreground">
                    请回忆该问题，点击屏幕显示答案
                </p>
                {card.history.total > 0 && (
                    <div className="flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                        <span>共背 {card.history.total} 次</span>
                        <span className="text-green-600">
                            认识 {card.history.known}
                        </span>
                        <span className="text-amber-600">
                            模糊 {card.history.fuzzy}
                        </span>
                        <span className="text-red-600">
                            忘记 {card.history.forgotten}
                        </span>
                        {card.history.last_rating && card.history.last_at && (
                            <span>
                                最近：
                                {RATING_INFO[card.history.last_rating].label}·
                                {formatRelativeTime(card.history.last_at)}
                            </span>
                        )}
                    </div>
                )}
            </div>

            {/* 反面：答案 + 评价 */}
            <div
                className={cn(
                    'absolute inset-0 flex flex-col gap-4 rounded-xl border bg-card p-6 transition-opacity',
                    !flipped && 'pointer-events-none opacity-0',
                )}
                onClick={(event) => event.stopPropagation()}
            >
                <p className="text-sm text-muted-foreground">{card.path}</p>
                <MarkdownContent content={card.answer} className="flex-1" />
                <div className="grid grid-cols-3 gap-2">
                    {(Object.keys(RATING_INFO) as Rating[]).map((rating) => {
                        const { label, className, Icon } = RATING_INFO[rating];

                        return (
                            <Button
                                key={rating}
                                className={cn('text-white', className)}
                                disabled={disabled}
                                onClick={() => onRate(rating)}
                            >
                                <Icon />
                                {label}
                            </Button>
                        );
                    })}
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    className="mx-auto text-muted-foreground"
                    disabled={disabled}
                    onClick={onUndo}
                >
                    撤销上一次评价
                </Button>
            </div>
        </div>
    );
}

export default function Recite() {
    const { state } = usePage<PageProps>().props;
    const [processing, setProcessing] = useState(false);
    const [sourceTab, setSourceTab] = useState<'selected' | 'mistake'>(
        state.source,
    );

    function switchSource(next: 'selected' | 'mistake') {
        setSourceTab(next);

        if (next !== state.source) {
            router.post(
                SelectController.setActiveSource.url(),
                { source: next, redirect: 'recite' },
                { preserveScroll: true },
            );
        }
    }

    function rate(rating: Rating) {
        if (!state.card) {
            return;
        }

        setProcessing(true);
        router.post(
            ReciteController.rate.url(),
            { card_id: state.card.id, rating },
            {
                preserveScroll: true,
                onError: () => toast.error('评价失败，请重试'),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function undo() {
        setProcessing(true);
        router.post(ReciteController.undo.url(), {}, {
            preserveScroll: true,
            onError: () => toast.error('撤销失败，请重试'),
            onFinish: () => setProcessing(false),
        });
    }

    // 键盘 1/2/3 评价、U 撤销（仅背诵中）
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            const inSession = state.phase === 'active' || state.phase === 'fresh';

            if (!inSession) {
                return;
            }

            if (event.key === '1') {
                rate('known');
            } else if (event.key === '2') {
                rate('fuzzy');
            } else if (event.key === '3') {
                rate('forgotten');
            } else if (event.key.toLowerCase() === 'u') {
                undo();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    });

    const percent =
        state.progress.total > 0
            ? Math.round(
                  (state.progress.evaluated / state.progress.total) * 100,
              )
            : 0;

    return (
        <>
            <Head title="背诵" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6">
                {/* 源切换 */}
                <div className="flex justify-center">
                    <div className="flex gap-2 rounded-lg bg-muted p-1">
                        {(
                            [
                                { key: 'selected', label: '自选卡' },
                                { key: 'mistake', label: '错题本' },
                            ] as const
                        ).map(({ key, label }) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => switchSource(key)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    sourceTab === key
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {(state.phase === 'empty' || state.phase === 'unavailable') && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 p-8 text-center">
                            {state.phase === 'unavailable' ? (
                                <p className="text-muted-foreground">
                                    错题本功能建设中
                                </p>
                            ) : (
                                <>
                                    <p className="text-lg font-medium">
                                        当前您没有选择背诵源
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        请到选卡页面选择！
                                    </p>
                                    <Button asChild className="mt-2">
                                        <Link href={select()}>去选卡</Link>
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {state.phase === 'completed' && state.task && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 p-8 text-center">
                            <p className="text-xl font-semibold">
                                恭喜你！当前背诵任务已完成！
                            </p>
                            <div className="flex gap-6 text-sm">
                                <span className="text-green-600">
                                    认识 {state.task.stats.known}
                                </span>
                                <span className="text-amber-600">
                                    模糊 {state.task.stats.fuzzy}
                                </span>
                                <span className="text-red-600">
                                    忘记 {state.task.stats.forgotten}
                                </span>
                            </div>
                            <div className="flex gap-3">
                                <Button
                                    onClick={() =>
                                        router.get(recite({ query: { start: 1 } }))
                                    }
                                >
                                    再背一轮
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={select()}>返回选卡</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {(state.phase === 'fresh' || state.phase === 'active') &&
                    state.card && (
                        <>
                            {/* 背诵进度 */}
                            <div className="space-y-1">
                                <div className="flex justify-between text-sm text-muted-foreground">
                                    <span>
                                        {state.progress.evaluated}/
                                        {state.progress.total} 张
                                    </span>
                                    <span>{percent}%</span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-primary transition-all"
                                        style={{ width: `${percent}%` }}
                                    />
                                </div>
                            </div>

                            <ReciteCard
                                key={state.card.id}
                                card={state.card}
                                disabled={processing}
                                onRate={rate}
                                onUndo={undo}
                            />
                        </>
                    )}
            </div>
        </>
    );
}
