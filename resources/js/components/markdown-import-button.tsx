import { Plus } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useAction } from '@/hooks/use-action';
import type { ActionRoute } from '@/hooks/use-action';

/**
 * Markdown 导入按钮：FormData 组装、导入中状态、错误 toast 与 input 重置全部内藏，
 * 用户端（选卡页）与管理端（卡片管理）共用，仅 action 与文案不同。
 */
export default function MarkdownImportButton({
    action,
    label = '导入 markdown',
    error = '导入失败，请检查文档格式',
    className,
}: {
    action: ActionRoute;
    label?: string;
    error?: string;
    className?: string;
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
            <Button
                variant="outline"
                className={className}
                onClick={() => fileInputRef.current?.click()}
                disabled={importing}
            >
                <Plus />
                {label}
            </Button>
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
