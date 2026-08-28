import { Head, router, usePage } from '@inertiajs/react';
import { Check, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import DeckController from '@/actions/App/Http/Controllers/DeckController';
import SelectController from '@/actions/App/Http/Controllers/SelectController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type DeckSummary = {
    id: number;
    name: string;
    cards_count: number;
    sections: Array<{
        id: number;
        name: string;
        cards_count: number;
    }>;
};

type PageProps = {
    warehouse: {
        systemDecks: DeckSummary[];
        userDecks: DeckSummary[];
    };
    selectedDeck: DeckSummary | null;
};

type Tab = 'system' | 'user';

export default function Select() {
    const { warehouse, selectedDeck, errors } = usePage<PageProps & { errors: Record<string, string> }>().props;
    const [tab, setTab] = useState<Tab>('system');
    const [detail, setDetail] = useState<DeckSummary | null>(null);
    const [processing, setProcessing] = useState(false);
    const [importing, setImporting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const decks = tab === 'system' ? warehouse.systemDecks : warehouse.userDecks;
    const current = detail ?? decks[0] ?? null;

    function switchTab(next: Tab) {
        setTab(next);
        setDetail(null);
    }

    function setAsSelected(deck: DeckSummary) {
        setProcessing(true);

        router.post(
            SelectController.setSelectedDeck.url(),
            { deck_id: deck.id },
            {
                preserveScroll: true,
                onError: () => toast.error('设置失败，请稍后重试'),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function importDocument(file: File) {
        const formData = new FormData();
        formData.append('document', file);

        setImporting(true);
        router.post(DeckController.import.url(), formData, {
            preserveScroll: true,
            onError: (importErrors) => {
                toast.error(importErrors.document ?? '导入失败，请检查文档格式');
            },
            onFinish: () => {
                setImporting(false);

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    }

    function deleteDeck(deck: DeckSummary) {
        if (!window.confirm(`确定删除卡组「${deck.name}」吗？删除后不可恢复。`)) {
            return;
        }

        setProcessing(true);
        router.delete(DeckController.destroy.url({ deck: deck.id }), {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <>
            <Head title="选卡" />

            <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
                {/* 左栏：卡组列表 */}
                <div className="space-y-4">
                    <div className="flex gap-2 rounded-lg bg-muted p-1">
                        {(
                            [
                                { key: 'system', label: '系统卡组' },
                                { key: 'user', label: '用户卡组' },
                            ] as const
                        ).map(({ key, label }) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => switchTab(key)}
                                className={cn(
                                    'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    tab === key
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    {tab === 'user' && (
                        <>
                            <Button
                                variant="outline"
                                className="w-full"
                                onClick={() => fileInputRef.current?.click()}
                                disabled={importing}
                            >
                                <Plus />
                                导入 markdown 文档
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
                            {errors.document && (
                                <p className="text-sm text-destructive">
                                    {errors.document}
                                </p>
                            )}
                        </>
                    )}

                    <div className="space-y-2">
                        {decks.map((deck) => (
                            <button
                                key={deck.id}
                                type="button"
                                onClick={() => setDetail(deck)}
                                className={cn(
                                    'w-full rounded-lg border p-3 text-left transition-colors',
                                    current?.id === deck.id
                                        ? 'border-primary bg-accent/50'
                                        : 'hover:bg-accent/50',
                                )}
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        {deck.name}
                                    </span>
                                    {selectedDeck?.id === deck.id && (
                                        <Check className="size-4 text-primary" />
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {deck.cards_count} 张卡片
                                </p>
                            </button>
                        ))}
                    </div>
                </div>

                {/* 右栏：卡组详情 */}
                <div>
                    {current ? (
                        <Card>
                            <CardContent className="space-y-4 p-6">
                                <div className="space-y-1">
                                    <h2 className="text-xl font-semibold">
                                        {current.name}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        共 {current.cards_count} 张卡片
                                    </p>
                                </div>

                                <ul className="divide-y">
                                    {current.sections.map((section) => (
                                        <li
                                            key={section.id}
                                            className="flex items-center justify-between py-2 text-sm"
                                        >
                                            <span>{section.name}</span>
                                            <span className="text-muted-foreground">
                                                {section.cards_count} 张
                                            </span>
                                        </li>
                                    ))}
                                </ul>

                                {selectedDeck?.id === current.id ? (
                                    <Button disabled className="w-full">
                                        <Check />
                                        当前自选卡
                                    </Button>
                                ) : (
                                    <Button
                                        className="w-full"
                                        onClick={() => setAsSelected(current)}
                                        disabled={processing}
                                    >
                                        设为自选卡
                                    </Button>
                                )}

                                {tab === 'user' && (
                                    <Button
                                        variant="destructive"
                                        className="w-full"
                                        onClick={() => deleteDeck(current)}
                                        disabled={processing}
                                    >
                                        <Trash2 />
                                        删除卡组
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="p-6 text-center text-sm text-muted-foreground">
                                请选择左侧卡组查看详情
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
