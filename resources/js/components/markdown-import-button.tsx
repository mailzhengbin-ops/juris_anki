import { Plus } from '@phosphor-icons/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useAction } from '@/hooks/use-action';
import type { ActionRoute } from '@/hooks/use-action';
import { cn } from '@/lib/utils';

/**
 * Markdown 导入入口：FormData 组装、导入中状态、错误 toast 与 input 重置全部内藏，
 * 用户端（选卡页）与管理端（卡片管理）共用，仅 action 与文案不同。
 */
export default function MarkdownImportButton({
    action,
    label = '导入 markdown',
    error = '导入失败，请检查文档格式',
    className,
    asCard = false,
}: {
    action: ActionRoute;
    label?: string;
    error?: string;
    className?: string;
    /** 以卡组卡片形态展示（选卡页卡组网格内使用）。 */
    asCard?: boolean;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { processing: importing, submit } = useAction();

    function importDocument(file: File) {
        const formData = new FormData();
        formData.append('document', file);

        submit(action, formData, {
            error,
            onFinish: () => {
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    }

    return (
        <>
            {asCard ? (
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={importing}
                    className={cn(
                        'flex min-h-[64px] w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed p-3 text-muted-foreground transition-colors hover:bg-accent/50 hover:text-foreground disabled:pointer-events-none disabled:opacity-50',
                        className,
                    )}
                >
                    <Plus className="size-4" />
                    <span className="text-sm font-medium">{label}</span>
                </button>
            ) : (
                <Button
                    variant="outline"
                    className={className}
                    onClick={() => fileInputRef.current?.click()}
                    disabled={importing}
                >
                    <Plus />
                    {label}
                </Button>
            )}
            <input
                ref={fileInputRef}
                type="file"
                accept=".md,.markdown,text/markdown,text/plain"
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file) {
                        importDocument(file);
                    }
                }}
            />
        </>
    );
}
