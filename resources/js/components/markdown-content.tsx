import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

/**
 * 卡片答案的 markdown 渲染（无 typography 插件，使用内置样式类）。
 */
export default function MarkdownContent({
    content,
    className,
}: {
    content: string;
    className?: string;
}) {
    return (
        <div className={cn('markdown-body', className)}>
            <ReactMarkdown remarkPlugins={[remarkGfm]}>
                {content}
            </ReactMarkdown>
        </div>
    );
}
