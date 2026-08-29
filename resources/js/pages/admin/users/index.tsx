import { Head, router, usePage } from '@inertiajs/react';
import { MagnifyingGlass } from '@phosphor-icons/react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { columns, type UserSummary } from './columns';

type PageProps = {
    users: UserSummary[];
    search: string;
};

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
                        <DataTable columns={columns} data={users} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
