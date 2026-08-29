import { router } from '@inertiajs/react';
import { useState } from 'react';
import SelectController from '@/actions/App/Http/Controllers/SelectController';
import type { SourceTab } from '@/lib/recitation';
import { cn } from '@/lib/utils';

const SOURCE_TABS: Array<{ key: SourceTab; label: string }> = [
    { key: 'selected', label: '自选卡' },
    { key: 'mistake', label: '错题本' },
];

/**
 * 当前背诵源 tab（领域概念见 CONTEXT.md）：点击即切换并 POST setActiveSource，
 * 本地乐观更新；redirect 决定切换后的回跳页。
 */
export default function SourceTabs({
    source,
    onSourceChange,
    redirect,
}: {
    /** 服务器侧的当前背诵源；null 表示尚未设置（按自选卡展示）。 */
    source: SourceTab | null;
    /** 乐观切换通知（需要跟随当前源渲染的页面使用）。 */
    onSourceChange?: (next: SourceTab) => void;
    redirect?: 'recite';
}) {
    const [active, setActive] = useState<SourceTab>(source ?? 'selected');

    function switchSource(next: SourceTab) {
        setActive(next);
        onSourceChange?.(next);

        if (next !== source) {
            router.post(
                SelectController.setActiveSource.url(),
                redirect === 'recite'
                    ? { source: next, redirect }
                    : { source: next },
                { preserveScroll: true },
            );
        }
    }

    return (
        <div className="flex gap-2 rounded-lg bg-muted p-1">
            {SOURCE_TABS.map(({ key, label }) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => switchSource(key)}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        active === key
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}
