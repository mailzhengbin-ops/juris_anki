import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import AdminUserController from '@/actions/App/Http/Controllers/Admin/AdminUserController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type UserDetail = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    last_login_at: string | null;
    created_at: string | null;
    decks_count: number;
    evaluations_count: number;
};

type PageProps = {
    user: UserDetail;
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('zh-CN', { hour12: false });
}

export default function AdminUserShow() {
    const { user } = usePage<PageProps>().props;
    const [processing, setProcessing] = useState(false);

    function deleteUser() {
        if (
            !window.confirm(
                `确定删除用户「${user.name}」（${user.email}）吗？其卡组、评价、任务、范围勾选等全部数据将被级联删除，且不可恢复。`,
            )
        ) {
            return;
        }

        setProcessing(true);
        router.delete(AdminUserController.destroy.url({ user: user.id }), {
            preserveScroll: true,
            onError: (errors) => {
                toast.error(Object.values(errors)[0] ?? '删除失败');
                setProcessing(false);
            },
            onSuccess: () => toast.success('用户已删除'),
        });
    }

    return (
        <>
            <Head title={`${user.name} - 用户管理`} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Link
                        href="/admin/users"
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        ← 返回列表
                    </Link>
                    <h2 className="text-lg font-semibold">{user.name}</h2>
                    {user.is_admin && (
                        <span className="rounded-full bg-accent px-2 py-0.5 text-xs">
                            管理员
                        </span>
                    )}
                </div>

                <Card>
                    <CardContent className="space-y-2 p-6 text-sm">
                        <div className="flex justify-between border-b py-2">
                            <span className="text-muted-foreground">邮箱</span>
                            <span>{user.email}</span>
                        </div>
                        <div className="flex justify-between border-b py-2">
                            <span className="text-muted-foreground">最近登录</span>
                            <span>{formatDate(user.last_login_at)}</span>
                        </div>
                        <div className="flex justify-between border-b py-2">
                            <span className="text-muted-foreground">注册时间</span>
                            <span>{formatDate(user.created_at)}</span>
                        </div>
                        <div className="flex justify-between border-b py-2">
                            <span className="text-muted-foreground">用户卡组数</span>
                            <span>{user.decks_count}</span>
                        </div>
                        <div className="flex justify-between py-2">
                            <span className="text-muted-foreground">评价记录数</span>
                            <span>{user.evaluations_count}</span>
                        </div>
                    </CardContent>
                </Card>

                <Button
                    variant="destructive"
                    disabled={processing}
                    onClick={deleteUser}
                >
                    删除用户
                </Button>
            </div>
        </>
    );
}
