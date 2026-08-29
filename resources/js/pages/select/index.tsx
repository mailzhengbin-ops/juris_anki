import { Head, usePage } from '@inertiajs/react';
import { Check, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import DeckController from '@/actions/App/Http/Controllers/DeckController';
import ScopeController from '@/actions/App/Http/Controllers/ScopeController';
import SelectController from '@/actions/App/Http/Controllers/SelectController';
import SourceTabs from '@/components/source-tabs';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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

type Tab = 'system' | 'user';

export default function Select() {
    const {
        warehouse,
        selectedDeck,
        activeSource,
        selectedScope,
        mistakeScope,
        errors,
    } = usePage<PageProps>().props;
    const [tab, setTab] = useState<Tab>('system');
    const [detail, setDetail] = useState<DeckSummary | null>(null);
    const [expanded, setExpanded] = useState<Set<string>>(new Set());
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { processing, submit } = useAction();
    const { processing: importing, submit: submitImport } = useAction();

    const decks =
        tab === 'system' ? warehouse.systemDecks : warehouse.userDecks;
    const current = detail ?? decks[0] ?? null;
    // 跟随 SourceTabs 的乐观切换展示对应源的范围树
    const [sourceTab, setSourceTab] = useState<SourceTab>(
        activeSource ?? 'selected',
    );

    function switchTab(next: Tab) {
        setTab(next);
        setDetail(null);
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

    function postScope(
        source: 'selected' | 'mistake',
        type: 'card' | 'section' | 'source',
        id: number | string | undefined,
        checked: boolean,
    ) {
        submit(
            ScopeController.toggle(),
            { source, type, id, checked },
            { error: '操作失败，请重试' },
        );
    }

    function setAsSelected(deck: DeckSummary) {
        submit(
            SelectController.setSelectedDeck(),
            { deck_id: deck.id },
            { error: '设置失败，请稍后重试' },
        );
    }

    function importDocument(file: File) {
        const formData = new FormData();
        formData.append('document', file);

        submitImport(DeckController.import(), formData, {
            error: '导入失败，请检查文档格式',
            onFinish: () => {
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    }

    function deleteDeck(deck: DeckSummary) {
        submit(DeckController.destroy({ deck: deck.id }), undefined, {
            confirm: `确定删除卡组「${deck.name}」吗？删除后不可恢复。`,
        });
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
                            onSourceChange={setSourceTab}
                        />
                    </div>

                    {(() => {
                        const scopeTree =
                            sourceTab === 'selected'
                                ? selectedScope
                                : mistakeScope;

                        if (!scopeTree) {
                            return (
                                <Card>
                                    <CardContent className="p-6 text-center text-sm text-muted-foreground">
                                        {sourceTab === 'selected'
                                            ? '尚未选择自选卡，请从下方卡组仓库选择'
                                            : '错题本暂无卡片'}
                                    </CardContent>
                                </Card>
                            );
                        }

                        return (
                            <>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            postScope(
                                                sourceTab,
                                                'source',
                                                undefined,
                                                true,
                                            )
                                        }
                                    >
                                        全选
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            postScope(
                                                sourceTab,
                                                'source',
                                                undefined,
                                                false,
                                            )
                                        }
                                    >
                                        清空
                                    </Button>
                                </div>

                                <div className="space-y-2">
                                    {scopeTree.map((section) => {
                                        const sectionKey = String(section.id);
                                        const checkedCount =
                                            section.cards.filter(
                                                (card) => card.checked,
                                            ).length;
                                        const allChecked =
                                            checkedCount ===
                                            section.cards.length;
                                        const partial =
                                            checkedCount > 0 && !allChecked;
                                        const isExpanded =
                                            expanded.has(sectionKey);

                                        return (
                                            <div
                                                key={sectionKey}
                                                className="rounded-lg border"
                                            >
                                                <div className="flex items-center gap-2 p-3">
                                                    <Checkbox
                                                        checked={
                                                            partial
                                                                ? 'indeterminate'
                                                                : allChecked
                                                        }
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            postScope(
                                                                sourceTab,
                                                                'section',
                                                                section.id,
                                                                value === true,
                                                            )
                                                        }
                                                    />
                                                    <button
                                                        type="button"
                                                        className="flex flex-1 items-center gap-2 text-left"
                                                        onClick={() =>
                                                            toggleExpanded(
                                                                sectionKey,
                                                            )
                                                        }
                                                    >
                                                        <ChevronRight
                                                            className={cn(
                                                                'size-4 transition-transform',
                                                                isExpanded &&
                                                                    'rotate-90',
                                                            )}
                                                        />
                                                        <span className="font-medium">
                                                            {section.name}
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {checkedCount}/
                                                            {
                                                                section.cards
                                                                    .length
                                                            }
                                                        </span>
                                                    </button>
                                                </div>

                                                {isExpanded && (
                                                    <ul className="space-y-1 border-t px-3 py-2">
                                                        {section.cards.map(
                                                            (card) => (
                                                                <li
                                                                    key={
                                                                        card.id
                                                                    }
                                                                    className="flex items-center gap-2 py-1"
                                                                >
                                                                    <Checkbox
                                                                        checked={
                                                                            card.checked
                                                                        }
                                                                        onCheckedChange={(
                                                                            value,
                                                                        ) =>
                                                                            postScope(
                                                                                sourceTab,
                                                                                'card',
                                                                                card.id,
                                                                                value ===
                                                                                    true,
                                                                            )
                                                                        }
                                                                    />
                                                                    <span className="text-sm">
                                                                        {
                                                                            card.question
                                                                        }
                                                                    </span>
                                                                    {card.path && (
                                                                        <span className="ml-auto shrink-0 text-xs text-muted-foreground">
                                                                            {
                                                                                card.path
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </>
                        );
                    })()}
                </section>

                {/* 卡组仓库模块 */}
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">卡组仓库</h2>

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
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
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
                                            <h3 className="text-xl font-semibold">
                                                {current.name}
                                            </h3>
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
                                                onClick={() =>
                                                    setAsSelected(current)
                                                }
                                                disabled={processing}
                                            >
                                                设为自选卡
                                            </Button>
                                        )}

                                        {tab === 'user' && (
                                            <Button
                                                variant="destructive"
                                                className="w-full"
                                                onClick={() =>
                                                    deleteDeck(current)
                                                }
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
                </section>
            </div>
        </>
    );
}
