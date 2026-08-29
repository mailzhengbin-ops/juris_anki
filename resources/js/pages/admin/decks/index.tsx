import { Head, Link, usePage } from '@inertiajs/react';
import { Folder } from '@phosphor-icons/react';
import AdminDeckController from '@/actions/App/Http/Controllers/Admin/AdminDeckController';
import MarkdownImportButton from '@/components/markdown-import-button';
import { Card, CardContent } from '@/components/ui/card';

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
                    <MarkdownImportButton
                        action={AdminDeckController.import()}
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
                                    <Folder className="size-4 text-muted-foreground" />
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
