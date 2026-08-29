import { Link } from '@inertiajs/react';
import {
    ArrowsDownUpIcon,
    CaretDownIcon,
    CaretUpIcon,
} from '@phosphor-icons/react';
import type { ReactNode } from 'react';
import type {
    DataTableColumn,
    DataTableColumns,
} from '@/components/data-table';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/hooks/use-initials';

export type UserSummary = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    avatar_url: string | null;
    last_login_at: string | null;
    created_at: string | null;
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('zh-CN', { hour12: false });
}

/** 可排序列头：点击切换升/降序，未排序显示中立箭头。 */
function SortableHeader({
    column,
    children,
}: {
    column: DataTableColumn<UserSummary>;
    children: ReactNode;
}) {
    const sorted = column.getIsSorted();

    return (
        <Button
            variant="ghost"
            size="sm"
            className="-ml-2 h-8 text-muted-foreground"
            onClick={() => column.toggleSorting(sorted === 'asc')}
        >
            {children}
            {sorted === 'asc' ? (
                <CaretUpIcon />
            ) : sorted === 'desc' ? (
                <CaretDownIcon />
            ) : (
                <ArrowsDownUpIcon />
            )}
        </Button>
    );
}

export const columns: DataTableColumns<UserSummary> = [
    {
        id: 'avatar',
        header: '头像',
        cell: ({ row }) => (
            <Avatar className="size-8">
                {row.original.avatar_url && (
                    <AvatarImage
                        src={row.original.avatar_url}
                        alt={row.original.name}
                    />
                )}
                <AvatarFallback className="text-xs">
                    {getInitials(row.original.name)}
                </AvatarFallback>
            </Avatar>
        ),
    },
    {
        accessorKey: 'name',
        header: ({ column }) => (
            <SortableHeader column={column}>昵称</SortableHeader>
        ),
    },
    {
        accessorKey: 'email',
        header: '邮箱',
        cell: ({ getValue }) => (
            <span className="text-muted-foreground">
                {getValue() as string}
            </span>
        ),
    },
    {
        accessorKey: 'is_admin',
        header: '角色',
        cell: ({ row }) =>
            row.original.is_admin ? (
                <Badge>管理员</Badge>
            ) : (
                <span className="text-muted-foreground">用户</span>
            ),
    },
    {
        accessorKey: 'last_login_at',
        header: '最近登录',
        cell: ({ getValue }) => formatDate(getValue() as string | null),
    },
    {
        accessorKey: 'created_at',
        header: ({ column }) => (
            <SortableHeader column={column}>注册时间</SortableHeader>
        ),
        cell: ({ getValue }) => formatDate(getValue() as string | null),
    },
    {
        id: 'actions',
        header: () => null,
        cell: ({ row }) => (
            <div className="text-right">
                <Link
                    href={`/admin/users/${row.original.id}`}
                    className="text-sm text-primary hover:underline"
                >
                    详情
                </Link>
            </div>
        ),
    },
];
