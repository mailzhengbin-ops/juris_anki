import {
    createSortedRowModel,
    FlexRender,
    rowSortingFeature,
    tableFeatures,
    useTable,
    type Column,
    type ColumnDef,
    type RowData,
    type SortingState,
} from '@tanstack/react-table';
import { useState } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/**
 * 通用 Data Table（@tanstack/react-table v9）。
 *
 * 声明用到的 features 与 rowModel——未注册的特性会被 tree-shaking 移出打包。
 * 当前仅启用列排序；新增能力（过滤/分页/行选择）时在此追加对应 feature。
 */
const features = tableFeatures({
    rowSortingFeature,
    sortedRowModel: createSortedRowModel(),
});

/** 列定义类型：让调用方与 features 保持类型一致。 */
export type DataTableColumns<TData extends RowData> = ColumnDef<
    typeof features,
    TData
>[];

/** 列实例（排序按钮等场景需要）。 */
export type DataTableColumn<TData extends RowData> = Column<
    typeof features,
    TData
>;

type DataTableProps<TData extends RowData> = {
    columns: DataTableColumns<TData>;
    data: TData[];
    emptyMessage?: string;
};

export function DataTable<TData extends RowData>({
    columns,
    data,
    emptyMessage = '暂无数据',
}: DataTableProps<TData>) {
    const [sorting, setSorting] = useState<SortingState>([]);

    const table = useTable({
        features,
        data,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
    });

    return (
        <Table>
            <TableHeader>
                {table.getHeaderGroups().map((headerGroup) => (
                    <TableRow key={headerGroup.id}>
                        {headerGroup.headers.map((header) => (
                            <TableHead key={header.id}>
                                {header.isPlaceholder ? null : (
                                    <FlexRender header={header} />
                                )}
                            </TableHead>
                        ))}
                    </TableRow>
                ))}
            </TableHeader>
            <TableBody>
                {table.getRowModel().rows.length > 0 ? (
                    table.getRowModel().rows.map((row) => (
                        <TableRow key={row.id}>
                            {row.getAllCells().map((cell) => (
                                <TableCell key={cell.id}>
                                    <FlexRender cell={cell} />
                                </TableCell>
                            ))}
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={columns.length}
                            className="h-24 text-center text-muted-foreground"
                        >
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
