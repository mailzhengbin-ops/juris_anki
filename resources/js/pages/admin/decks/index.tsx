import { Head, Link, usePage } from '@inertiajs/react';
import { FolderKanban, Plus } from 'lucide-react';
import { useRef } from 'react';
import AdminDeckController from '@/actions/App/Http/Controllers/Admin/AdminDeckController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAction } from '@/hooks/use-action';

type DeckSummary = {
    id: number;
    name: string;
    cards_count: number;
    sections_count: number;
};

type PageProps = {
    decks: DeckSummary[];
    errors: Record<string, string>;
};

export default function AdminDecks() {
    const { decks, errors } = usePage<PageProps>().props;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { processing: importing, submit: submitImport } = useAction();

    function importDocument(file: File) {
        const formData = new FormData();
        formData.append('document', file);

        submitImport(AdminDeckController.import(), formData, {
            error: '导入失败，请检查文档格式',
            onFinish: () => {
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    }

    return (
        <>
            <Head title="卡片管理" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">系统卡组</h2>
                        <p className="text-sm text-muted-foreground">
                            通过 markdown 文档导入创建系统卡组
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={importing}
                    >
                        <Plus />
                        导入 markdown
                    </Button>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".md,.markdown,text/markdown,text/plain"
                        className="hidden"
                        onChange={(e) => {
                            const file = e.target.files?.[0];

                            if (file) {
                                importDocument(file);
                            }
                        }}
                    />
                </div>

                {errors.document && (
                    <p className="text-sm text-destructive">
                        {errors.document}
                    </p>
                )}

                {decks.length === 0 ? (
                    <Card>
                        <CardContent className="p-6 text-center text-sm text-muted-foreground">
                            暂无系统卡组
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {decks.map((deck) => (
                            <Link
                                key={deck.id}
                                href={`/admin/decks/${deck.id}`}
                                className="block rounded-lg border p-4 transition-colors hover:bg-accent/50"
                            >
                                <div className="flex items-center gap-2">
                                    <FolderKanban className="size-4 text-muted-foreground" />
                                    <span className="font-medium">
                                        {deck.name}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {deck.sections_count} 个子卡组 ·{' '}
                                    {deck.cards_count} 张卡片
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
