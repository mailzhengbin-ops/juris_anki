import { Head } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';

type PageProps = {
    data: {
        users_count: number;
        system_decks_count: number;
    };
    environment: {
        php_version: string;
        server_software: string;
        database: string;
        laravel_version: string;
    };
};

export default function AdminDashboard({ data, environment }: PageProps) {
    return (
        <>
            <Head title="管理端仪表盘" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-lg font-semibold">仪表盘</h2>
                    <p className="text-sm text-muted-foreground">
                        Juris Anki 管理端
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardContent className="p-6">
                            <p className="text-sm text-muted-foreground">
                                网站用户数
                            </p>
                            <p className="text-3xl font-semibold">
                                {data.users_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <p className="text-sm text-muted-foreground">
                                系统卡组数
                            </p>
                            <p className="text-3xl font-semibold">
                                {data.system_decks_count}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="space-y-2 p-6 text-sm">
                        <div className="flex justify-between border-b py-2">
                            <span className="text-muted-foreground">
                                PHP 版本
                            </span>
                            <span>{environment.php_version}</span>
                        </div>
                        <div className="flex justify-between border-b py-2">
                            <span className="text-muted-foreground">
                                Web 服务器
                            </span>
                            <span>{environment.server_software}</span>
                        </div>
                        <div className="flex justify-between border-b py-2">
                            <span className="text-muted-foreground">
                                数据库
                            </span>
                            <span>{environment.database}</span>
                        </div>
                        <div className="flex justify-between py-2">
                            <span className="text-muted-foreground">
                                Laravel 版本
                            </span>
                            <span>{environment.laravel_version}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
