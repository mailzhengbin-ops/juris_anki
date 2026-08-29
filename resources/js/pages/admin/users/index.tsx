import { Head, Link, router, usePage } from '@inertiajs/react';
import { MagnifyingGlass } from '@phosphor-icons/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type UserSummary = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    last_login_at: string | null;
    created_at: string | null;
};

type PageProps = {
    users: UserSummary[];
    search: string;
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('zh-CN', { hour12: false });
}

export default function AdminUsers() {
    const { users, search } = usePage<PageProps>().props;
    const [query, setQuery] = useState(search);

    function doSearch() {
        router.get('/admin/users', { q: query }, { preserveState: true });
    }

    return (
        <>
            <Head title="用户管理" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">用户管理</h2>
                        <p className="text-sm text-muted-foreground">
                            共 {users.length} 位用户
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && doSearch()}
                            placeholder="按昵称/邮箱搜索"
                            className="w-56"
                        />
                        <Button variant="outline" onClick={doSearch}>
                            <MagnifyingGlass />
                            搜索
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        昵称
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        邮箱
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        角色
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        最近登录
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        注册时间
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            {user.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            {user.is_admin ? (
                                                <span className="rounded-full bg-accent px-2 py-0.5 text-xs">
                                                    管理员
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    用户
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatDate(user.last_login_at)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatDate(user.created_at)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={`/admin/users/${user.id}`}
                                                className="text-sm text-primary hover:underline"
                                            >
                                                详情
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
