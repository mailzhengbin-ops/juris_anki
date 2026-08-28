import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Pencil, Save, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AdminDeckController from '@/actions/App/Http/Controllers/Admin/AdminDeckController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type CardItem = {
    id: number;
    question: string;
    answer: string;
};

type SectionItem = {
    id: number;
    name: string;
    cards: CardItem[];
};

type DeckDetail = {
    id: number;
    name: string;
    sections: SectionItem[];
};

type PageProps = {
    deck: DeckDetail;
};

function useDeckActions() {
    const [processing, setProcessing] = useState(false);

    function run(exec: () => void) {
        setProcessing(true);
        exec();
    }

    return { processing, run };
}

export default function AdminDeckShow() {
    const { deck } = usePage<PageProps>().props;
    const [deckName, setDeckName] = useState(deck.name);
    const [editingSection, setEditingSection] = useState<number | null>(null);
    const [sectionNames, setSectionNames] = useState<Record<number, string>>({});
    const [expandedCards, setExpandedCards] = useState<Set<number>>(new Set());
    const [editingCard, setEditingCard] = useState<number | null>(null);
    const [cardForms, setCardForms] = useState<Record<number, { question: string; answer: string }>>({});
    const { processing, run } = useDeckActions();

    function patch(url: string, data: Record<string, string>, message: string) {
        run(() =>
            router.patch(url, data, {
                preserveScroll: true,
                onError: (errors) => toast.error(Object.values(errors)[0] ?? '保存失败'),
                onSuccess: () => toast.success(message),
            }),
        );
    }

    function remove(url: string, message: string, confirmText: string) {
        if (!window.confirm(confirmText)) {
            return;
        }

        run(() =>
            router.delete(url, {
                preserveScroll: true,
                onSuccess: () => toast.success(message),
            }),
        );
    }

    function toggleCard(cardId: number) {
        setExpandedCards((prev) => {
            const next = new Set(prev);

            if (next.has(cardId)) {
                next.delete(cardId);
            } else {
                next.add(cardId);
            }

            return next;
        });
    }

    return (
        <>
            <Head title={`${deck.name} - 卡片管理`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex items-end gap-3">
                        <Link
                            href="/admin/decks"
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            ← 返回列表
                        </Link>
                        <h2 className="text-lg font-semibold">{deck.name}</h2>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={deckName}
                            onChange={(e) => setDeckName(e.target.value)}
                            className="w-48"
                        />
                        <Button
                            size="sm"
                            disabled={processing || deckName === deck.name}
                            onClick={() =>
                                patch(
                                    AdminDeckController.update.url({ deck: deck.id }),
                                    { name: deckName },
                                    '卡组名已更新',
                                )
                            }
                        >
                            <Save />
                            保存名称
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            disabled={processing}
                            onClick={() =>
                                remove(
                                    AdminDeckController.destroy.url({ deck: deck.id }),
                                    '系统卡组已删除',
                                    `确定删除系统卡组「${deck.name}」吗？其子卡组与卡片将被删除，用户的评价记录将保留用于统计。`,
                                )
                            }
                        >
                            <Trash2 />
                            删除卡组
                        </Button>
                    </div>
                </div>

                {deck.sections.map((section) => (
                    <Card key={section.id}>
                        <CardContent className="space-y-3 p-4">
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="px-1"
                                    onClick={() =>
                                        setExpandedCards((prev) => {
                                            const next = new Set(prev);

                                            section.cards.forEach((card) => next.add(card.id));

                                            return next;
                                        })
                                    }
                                >
                                    <ChevronDown className="size-4" />
                                </Button>
                                {editingSection === section.id ? (
                                    <>
                                        <Input
                                            autoFocus
                                            value={sectionNames[section.id] ?? section.name}
                                            onChange={(e) =>
                                                setSectionNames((prev) => ({
                                                    ...prev,
                                                    [section.id]: e.target.value,
                                                }))
                                            }
                                            className="w-48"
                                        />
                                        <Button
                                            size="sm"
                                            disabled={processing}
                                            onClick={() => {
                                                patch(
                                                    AdminDeckController.updateSection.url({
                                                        section: section.id,
                                                    }),
                                                    { name: sectionNames[section.id] },
                                                    '子卡组名已更新',
                                                );
                                                setEditingSection(null);
                                            }}
                                        >
                                            <Save />
                                            保存
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setEditingSection(null)}
                                        >
                                            <X />
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <span className="font-medium">
                                            {section.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {section.cards.length} 张卡片
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="ml-auto"
                                            onClick={() => {
                                                setSectionNames((prev) => ({
                                                    ...prev,
                                                    [section.id]: section.name,
                                                }));
                                                setEditingSection(section.id);
                                            }}
                                        >
                                            <Pencil />
                                            编辑
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            disabled={processing}
                                            onClick={() =>
                                                remove(
                                                    AdminDeckController.destroySection.url({
                                                        section: section.id,
                                                    }),
                                                    '子卡组已删除',
                                                    `确定删除子卡组「${section.name}」吗？其下 ${section.cards.length} 张卡片将一并删除。`,
                                                )
                                            }
                                        >
                                            <Trash2 />
                                            删除
                                        </Button>
                                    </>
                                )}
                            </div>

                            <div className="space-y-1 border-t pt-2">
                                {section.cards.map((card) => {
                                    const isExpanded = expandedCards.has(card.id);
                                    const isEditing = editingCard === card.id;
                                    const form = cardForms[card.id] ?? {
                                        question: card.question,
                                        answer: card.answer,
                                    };

                                    return (
                                        <div key={card.id} className="rounded-md border px-3">
                                            <div className="flex items-center gap-2 py-2">
                                                <button
                                                    type="button"
                                                    className="flex flex-1 items-center gap-2 text-left text-sm"
                                                    onClick={() => toggleCard(card.id)}
                                                >
                                                    <ChevronRight
                                                        className={cn(
                                                            'size-4 shrink-0 transition-transform',
                                                            isExpanded && 'rotate-90',
                                                        )}
                                                    />
                                                    <span className="truncate">
                                                        {card.question}
                                                    </span>
                                                </button>
                                                {!isEditing && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            setCardForms((prev) => ({
                                                                ...prev,
                                                                [card.id]: {
                                                                    question: card.question,
                                                                    answer: card.answer,
                                                                },
                                                            }));
                                                            setEditingCard(card.id);
                                                        }}
                                                    >
                                                        <Pencil />
                                                        编辑
                                                    </Button>
                                                )}
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive"
                                                    disabled={processing}
                                                    onClick={() =>
                                                        remove(
                                                            AdminDeckController.destroyCard.url({
                                                                card: card.id,
                                                            }),
                                                            '卡片已删除',
                                                            '确定删除该卡片吗？它将从所有用户的范围与错题本中消失，评价记录保留用于统计。',
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>

                                            {isExpanded && (
                                                <div className="space-y-2 border-t py-3 text-sm">
                                                    {isEditing ? (
                                                        <>
                                                            <div className="grid gap-2">
                                                                <Label>问题</Label>
                                                                <Input
                                                                    value={form.question}
                                                                    onChange={(e) =>
                                                                        setCardForms((prev) => ({
                                                                            ...prev,
                                                                            [card.id]: {
                                                                                ...form,
                                                                                question:
                                                                                    e.target.value,
                                                                            },
                                                                        }))
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label>答案</Label>
                                                                <textarea
                                                                    value={form.answer}
                                                                    onChange={(e) =>
                                                                        setCardForms((prev) => ({
                                                                            ...prev,
                                                                            [card.id]: {
                                                                                ...form,
                                                                                answer:
                                                                                    e.target.value,
                                                                            },
                                                                        }))
                                                                    }
                                                                    rows={4}
                                                                    className="w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                                                />
                                                            </div>
                                                            <div className="flex gap-2">
                                                                <Button
                                                                    size="sm"
                                                                    disabled={processing}
                                                                    onClick={() => {
                                                                        patch(
                                                                            AdminDeckController.updateCard.url({
                                                                                card: card.id,
                                                                            }),
                                                                            form,
                                                                            '卡片已更新',
                                                                        );
                                                                        setEditingCard(null);
                                                                    }}
                                                                >
                                                                    <Save />
                                                                    保存
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    onClick={() =>
                                                                        setEditingCard(null)
                                                                    }
                                                                >
                                                                    <X />
                                                                    取消
                                                                </Button>
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <div className="space-y-1 text-muted-foreground">
                                                            <p>答：{card.answer}</p>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}
