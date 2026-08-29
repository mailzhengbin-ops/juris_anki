import { Head, usePage } from '@inertiajs/react';
import { CaretRight, Check, ListChecks, Trash } from '@phosphor-icons/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import DeckController from '@/actions/App/Http/Controllers/DeckController';
import ScopeController from '@/actions/App/Http/Controllers/ScopeController';
import SelectController from '@/actions/App/Http/Controllers/SelectController';
import MarkdownImportButton from '@/components/markdown-import-button';
import SourceTabs from '@/components/source-tabs';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAction } from '@/hooks/use-action';
import type { SourceTab } from '@/lib/recitation';
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

type ScopeCard = {
    id: number;
    question: string;
    checked: boolean;
    path?: string;
};

type ScopeSection = {
    id: number | string;
    name: string;
    cards: ScopeCard[];
};

/** 卡组仓库的展示 tab（纯本地状态，无服务端同步）。 */
const WAREHOUSE_TABS: Array<{ key: 'system' | 'user'; label: string }> = [
    { key: 'system', label: '系统卡组' },
    { key: 'user', label: '用户卡组' },
];

type PageProps = {
    warehouse: {
        systemDecks: DeckSummary[];
        userDecks: DeckSummary[];
    };
    selectedDeck: DeckSummary | null;
    activeSource: 'selected' | 'mistake' | null;
    selectedScope: ScopeSection[] | null;
    mistakeScope: ScopeSection[] | null;
    errors: Record<string, string>;
};

export default function Select() {
    const {
        warehouse,
        selectedDeck,
        activeSource,
        selectedScope,
        mistakeScope,
        errors,
    } = usePage<PageProps>().props;
    // 跟随 SourceTabs 的乐观切换展示对应源的范围树
    const [sourceTab, setSourceTab] = useState<SourceTab>(
        activeSource ?? 'selected',
    );
    // 当前打开的卡组树 Dialog 对应的背诵源；null 表示关闭
    const [treeSource, setTreeSource] = useState<SourceTab | null>(null);
    // 卡组详情 Dialog 中的卡组；null 表示关闭
    const [detail, setDetail] = useState<DeckSummary | null>(null);
    // 卡组仓库当前展示的 tab
    const [deckTab, setDeckTab] = useState<'system' | 'user'>('system');
    // 卡组树 Dialog 的本地勾选草稿（勾选的卡片 ID 集合；null 表示未打开）
    const [draft, setDraft] = useState<Set<number> | null>(null);
    const [expanded, setExpanded] = useState<Set<string>>(new Set());

    // Dialog 打开时用服务端范围初始化草稿；关闭时置空丢弃
    useEffect(() => {
        const tree = treeSource === 'selected' ? selectedScope : mistakeScope;
        setDraft(
            treeSource === null || tree === null
                ? null
                : new Set(
                      tree.flatMap((section) =>
                          section.cards
                              .filter((card) => card.checked)
                              .map((card) => card.id),
                      ),
                  ),
        );
    }, [treeSource, selectedScope, mistakeScope]);
    const { processing, submit } = useAction();

    const selectedSummary = countScope(selectedScope);
    const mistakeSummary = countScope(mistakeScope);
    const isUserDeck =
        detail !== null &&
        warehouse.userDecks.some((deck) => deck.id === detail.id);

    /** 点击源 tab：只切换当前背诵源的展示，范围调整由下方卡片的按钮触发。 */
    function handleSourceChange(next: SourceTab) {
        setSourceTab(next);
    }

    function countScope(tree: ScopeSection[] | null) {
        const total = tree?.reduce((n, s) => n + s.cards.length, 0) ?? 0;
        const checked =
            tree?.reduce(
                (n, s) => n + s.cards.filter((card) => card.checked).length,
                0,
            ) ?? 0;

        return { total, checked };
    }

    function toggleExpanded(sectionId: string) {
        setExpanded((prev) => {
            const next = new Set(prev);

            if (next.has(sectionId)) {
                next.delete(sectionId);
            } else {
                next.add(sectionId);
            }

            return next;
        });
    }

    /** 草稿内切换单卡勾选。 */
    function toggleCard(cardId: number, checked: boolean) {
        setDraft((prev) => {
            if (prev === null) {
                return prev;
            }
            const next = new Set(prev);

            checked ? next.add(cardId) : next.delete(cardId);

            return next;
        });
    }

    /** 草稿内整组切换（自选卡为子卡组，错题本为 forgotten/fuzzy 子组）。 */
    function toggleSection(section: ScopeSection, checked: boolean) {
        setDraft((prev) => {
            if (prev === null) {
                return prev;
            }
            const next = new Set(prev);

            for (const card of section.cards) {
                checked ? next.add(card.id) : next.delete(card.id);
            }

            return next;
        });
    }

    /** 草稿内全选/清空整个源。 */
    function toggleAll(sections: ScopeSection[], checked: boolean) {
        setDraft((prev) => {
            if (prev === null) {
                return prev;
            }
            const next = new Set(prev);

            for (const section of sections) {
                for (const card of section.cards) {
                    checked ? next.add(card.id) : next.delete(card.id);
                }
            }

            return next;
        });
    }

    /** 确认草稿：一次性提交当前源的完整勾选集。 */
    function confirmScope() {
        if (treeSource === null || draft === null) {
            return;
        }
        submit(
            ScopeController.apply(),
            { source: treeSource, card_ids: [...draft] },
            { error: '保存失败，请重试', onFinish: () => setTreeSource(null) },
        );
    }

    function setAsSelected(deck: DeckSummary) {
        submit(
            SelectController.setSelectedDeck(),
            { deck_id: deck.id },
            { error: '设置失败，请稍后重试' },
        );
    }

    function deleteDeck(deck: DeckSummary) {
        submit(DeckController.destroy({ deck: deck.id }), undefined, {
            confirm: `确定删除卡组「${deck.name}」吗？删除后不可恢复。`,
            onFinish: () => setDetail(null),
        });
    }

    /** 卡组树内容：全选/清空 + 子卡组（三态复选框）与卡片（复选框），只改本地草稿。 */
    function renderTree(
        sections: ScopeSection[],
        draft: Set<number>,
        onToggleCard: (cardId: number, checked: boolean) => void,
        onToggleSection: (section: ScopeSection, checked: boolean) => void,
        onToggleAll: (sections: ScopeSection[], checked: boolean) => void,
    ) {
        return (
            <>
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onToggleAll(sections, true)}
                    >
                        全选
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onToggleAll(sections, false)}
                    >
                        清空
                    </Button>
                </div>

                <div className="space-y-2">
                    {sections.map((section) => {
                        const sectionKey = String(section.id);
                        const checkedCount = section.cards.filter((card) =>
                            draft.has(card.id),
                        ).length;
                        const allChecked =
                            checkedCount === section.cards.length;
                        const partial = checkedCount > 0 && !allChecked;
                        const isExpanded = expanded.has(sectionKey);

                        return (
                            <div key={sectionKey} className="rounded-lg border">
                                <div className="flex items-center gap-2 p-3">
                                    <Checkbox
                                        checked={
                                            partial
                                                ? 'indeterminate'
                                                : allChecked
                                        }
                                        onCheckedChange={(value) =>
                                            onToggleSection(
                                                section,
                                                value === true,
                                            )
                                        }
                                    />
                                    <button
                                        type="button"
                                        className="flex flex-1 items-center gap-2 text-left"
                                        onClick={() =>
                                            toggleExpanded(sectionKey)
                                        }
                                    >
                                        <CaretRight
                                            className={cn(
                                                'size-4 transition-transform',
                                                isExpanded && 'rotate-90',
                                            )}
                                        />
                                        <span className="font-medium">
                                            {section.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {checkedCount}/
                                            {section.cards.length}
                                        </span>
                                    </button>
                                </div>

                                {isExpanded && (
                                    <ul className="space-y-1 border-t px-3 py-2">
                                        {section.cards.map((card) => (
                                            <li
                                                key={card.id}
                                                className="flex items-center gap-2 py-1"
                                            >
                                                <Checkbox
                                                    checked={draft.has(card.id)}
                                                    onCheckedChange={(value) =>
                                                        onToggleCard(
                                                            card.id,
                                                            value === true,
                                                        )
                                                    }
                                                />
                                                <span className="text-sm">
                                                    {card.question}
                                                </span>
                                                {card.path && (
                                                    <span className="ml-auto shrink-0 text-xs text-muted-foreground">
                                                        {card.path}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        );
                    })}
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="选卡" />

            <div className="space-y-8">
                {/* 当前在背模块 */}
                <section className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">当前在背</h2>
                        <SourceTabs
                            source={activeSource}
                            onSourceChange={handleSourceChange}
                        />
                    </div>

                    {sourceTab === 'selected' ? (
                        selectedDeck ? (
                            <Card>
                                <CardContent className="flex items-center justify-between gap-4 p-4 sm:p-6">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {selectedDeck.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            已选 {selectedSummary.checked}/
                                            {selectedSummary.total} 张卡片
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="shrink-0"
                                        onClick={() =>
                                            setTreeSource('selected')
                                        }
                                    >
                                        <ListChecks />
                                        调整范围
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card>
                                <CardContent className="p-6 text-center text-sm text-muted-foreground">
                                    尚未选择自选卡，请从下方卡组仓库选择
                                </CardContent>
                            </Card>
                        )
                    ) : mistakeSummary.total > 0 ? (
                        <Card>
                            <CardContent className="flex items-center justify-between gap-4 p-4 sm:p-6">
                                <div className="min-w-0">
                                    <p className="font-medium">错题本</p>
                                    <p className="text-sm text-muted-foreground">
                                        在册 {mistakeSummary.total} 张卡片
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={() => setTreeSource('mistake')}
                                >
                                    <ListChecks />
                                    调整范围
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="p-6 text-center text-sm text-muted-foreground">
                                错题本暂无卡片
                            </CardContent>
                        </Card>
                    )}

                    {/* 卡组树 Dialog（勾选背诵范围，点选即保存） */}
                    <Dialog
                        open={treeSource !== null}
                        onOpenChange={(open) => !open && setTreeSource(null)}
                    >
                        <DialogContent className="max-h-[85vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>
                                    {treeSource === 'selected'
                                        ? (selectedDeck?.name ?? '自选卡')
                                        : '错题本'}
                                </DialogTitle>
                                <DialogDescription>
                                    勾选要背诵的卡片，点击确认后保存
                                </DialogDescription>
                            </DialogHeader>

                            {treeSource === 'selected' && !selectedDeck && (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    尚未选择自选卡，请从下方卡组仓库选择
                                </p>
                            )}
                            {treeSource === 'mistake' &&
                                mistakeScope !== null &&
                                draft !== null &&
                                renderTree(
                                    mistakeScope,
                                    draft,
                                    toggleCard,
                                    toggleSection,
                                    toggleAll,
                                )}
                            {treeSource === 'selected' &&
                                selectedDeck &&
                                selectedScope !== null &&
                                draft !== null &&
                                renderTree(
                                    selectedScope,
                                    draft,
                                    toggleCard,
                                    toggleSection,
                                    toggleAll,
                                )}

                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setTreeSource(null)}
                                >
                                    取消
                                </Button>
                                <Button
                                    disabled={processing}
                                    onClick={confirmScope}
                                >
                                    确认
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </section>

                {/* 卡组仓库模块：系统/用户卡组 tab 切换，tab 内卡片两栏排布 */}
                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">卡组仓库</h2>
                        <div className="flex gap-2 rounded-lg bg-muted p-1">
                            {WAREHOUSE_TABS.map(({ key, label }) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setDeckTab(key)}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                        deckTab === key
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {deckTab === 'system' ? (
                        <DeckGrid
                            decks={warehouse.systemDecks}
                            selectedDeckId={selectedDeck?.id ?? null}
                            onOpenDeck={(deck) => setDetail(deck)}
                        />
                    ) : (
                        <div className="space-y-3">
                            {errors.document && (
                                <p className="text-sm text-destructive">
                                    {errors.document}
                                </p>
                            )}
                            <DeckGrid
                                decks={warehouse.userDecks}
                                selectedDeckId={selectedDeck?.id ?? null}
                                onOpenDeck={(deck) => setDetail(deck)}
                                leading={
                                    <MarkdownImportButton
                                        action={DeckController.import()}
                                        asCard
                                        label="导入 Markdown 卡组"
                                    />
                                }
                            />
                        </div>
                    )}
                </section>
            </div>

            {/* 卡组详情 Dialog */}
            <Dialog
                open={detail !== null}
                onOpenChange={(open) => !open && setDetail(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{detail?.name}</DialogTitle>
                        <DialogDescription>
                            共 {detail?.cards_count} 张卡片
                        </DialogDescription>
                    </DialogHeader>

                    <ul className="divide-y">
                        {detail?.sections.map((section) => (
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

                    {selectedDeck?.id === detail?.id ? (
                        <Button disabled className="w-full">
                            <Check />
                            当前自选卡
                        </Button>
                    ) : (
                        <Button
                            className="w-full"
                            disabled={processing}
                            onClick={() => detail && setAsSelected(detail)}
                        >
                            设为自选卡
                        </Button>
                    )}

                    {isUserDeck && (
                        <Button
                            variant="destructive"
                            className="w-full"
                            disabled={processing}
                            onClick={() => deleteDeck(detail)}
                        >
                            <Trash />
                            删除卡组
                        </Button>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

/** 卡组 tab 内的卡组网格（两栏排布），空时显示空态。 */
function DeckGrid({
    decks,
    selectedDeckId,
    onOpenDeck,
    leading,
}: {
    decks: DeckSummary[];
    selectedDeckId: number | null;
    onOpenDeck: (deck: DeckSummary) => void;
    /** 网格首位的附加元素（如用户卡组的导入入口）。 */
    leading?: ReactNode;
}) {
    if (decks.length === 0 && !leading) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                暂无卡组
            </p>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {leading}
            {decks.map((deck) => (
                <DeckCard
                    key={deck.id}
                    deck={deck}
                    selectedDeckId={selectedDeckId}
                    onOpen={() => onOpenDeck(deck)}
                />
            ))}
        </div>
    );
}

/** 卡组仓库网格中的单个卡组卡片。 */
function DeckCard({
    deck,
    selectedDeckId,
    onOpen,
}: {
    deck: DeckSummary;
    selectedDeckId: number | null;
    onOpen: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="w-full rounded-lg border p-3 text-left transition-colors hover:bg-accent/50"
        >
            <div className="flex items-center justify-between gap-2">
                <span className="truncate font-medium">{deck.name}</span>
                {selectedDeckId === deck.id && (
                    <Check className="size-4 shrink-0 text-primary" />
                )}
            </div>
            <span className="text-sm text-muted-foreground">
                {deck.cards_count} 张卡片
            </span>
        </button>
    );
}
