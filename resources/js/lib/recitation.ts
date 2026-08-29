import { CheckCircle, MinusCircle, XCircle } from '@phosphor-icons/react';

/**
 * 背诵领域内核（单一来源）：类型契约与展示语义与后端对齐。
 *
 * - Rating / RecitationPhase 与 app/Enums 的 value 一一对应
 * - CardPayload / RecitationState 镜像 RecitationService 的序列化形状
 * - 评价展示映射供 recite 页与 stats 页共用，语义变更只改这里
 */

export type SourceTab = 'selected' | 'mistake';

export type Rating = 'known' | 'fuzzy' | 'forgotten';

export type RecitationPhase = 'empty' | 'fresh' | 'active' | 'completed';

export type CardPayload = {
    id: number;
    question: string;
    answer: string;
    path: string;
    enrolled: Exclude<Rating, 'known'> | null;
    history: {
        total: number;
        known: number;
        fuzzy: number;
        forgotten: number;
        last_rating: Rating | null;
        last_at: string | null;
    };
};

export type RecitationState = {
    source: SourceTab;
    phase: RecitationPhase;
    progress: { evaluated: number; total: number };
    card: CardPayload | null;
    task: {
        stats: Record<Rating, number>;
    } | null;
};

export const RATINGS: Rating[] = ['known', 'fuzzy', 'forgotten'];

export const RATING_INFO: Record<
    Rating,
    {
        label: string;
        /** 图表/标识底色（无 hover 变体）。 */
        barClass: string;
        /** 按钮底色（含 hover 变体）。 */
        className: string;
        Icon: typeof CheckCircle;
    }
> = {
    known: {
        label: '认识',
        barClass: 'bg-green-500',
        className: 'bg-green-500 hover:bg-green-600',
        Icon: CheckCircle,
    },
    fuzzy: {
        label: '模糊',
        barClass: 'bg-amber-500',
        className: 'bg-amber-500 hover:bg-amber-600',
        Icon: MinusCircle,
    },
    forgotten: {
        label: '忘记',
        barClass: 'bg-red-500',
        className: 'bg-red-500 hover:bg-red-600',
        Icon: XCircle,
    },
};

/**
 * 相对时间文案：刚刚 / N 分钟前 / N 小时前 / 昨天 / N 天前。
 */
export function formatRelativeTime(iso: string): string {
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

/**
 * 背诵进度百分比（0 张时取 0，避免除零）。
 */
export function progressPercent(progress: {
    evaluated: number;
    total: number;
}): number {
    return progress.total > 0
        ? Math.round((progress.evaluated / progress.total) * 100)
        : 0;
}
