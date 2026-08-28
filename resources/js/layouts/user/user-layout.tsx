import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpenText,
    ListChecks,
    UserRound
    
} from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import { cn } from '@/lib/utils';
import { recite, select, stats } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

type UserNavItem = NavItem & {
    icon: LucideIcon;
    /** 底部 tab 激活匹配的前缀（默认等于 href） */
    matchPrefix?: string;
};

function useNavItems(): UserNavItem[] {
    return [
        { title: '背诵', href: recite(), icon: BookOpenText },
        { title: '选卡', href: select(), icon: ListChecks },
        { title: '统计', href: stats(), icon: BarChart3 },
        { title: '我的', href: editProfile(), icon: UserRound, matchPrefix: '/settings' },
    ];
}

function isActive(url: string, item: UserNavItem): boolean {
    const prefix = item.matchPrefix ?? item.href;

    return url === prefix || url.startsWith(`${prefix}/`);
}

export default function UserLayout({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    const items = useNavItems();
    const { url } = usePage();

    return (
        <div className="min-h-svh bg-background">
            <Head title={title} />

            {/* 桌面端顶部导航 */}
            <header className="sticky top-0 z-40 hidden border-b bg-background md:block">
                <div className="mx-auto flex h-14 w-full max-w-4xl items-center gap-6 px-6">
                    <span className="text-sm font-semibold">Juris Anki</span>
                    <nav className="flex items-center gap-1">
                        {items.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                                    isActive(url, item) &&
                                        'bg-accent font-medium text-foreground',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>
                </div>
            </header>

            <main className="mx-auto w-full max-w-4xl px-4 pt-4 pb-24 md:px-6 md:pt-8 md:pb-12">
                {children}
            </main>

            {/* 移动端底部 tab */}
            <nav className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t bg-background md:hidden">
                {items.map((item) => {
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            className={cn(
                                'flex flex-col items-center gap-1 py-2 text-xs text-muted-foreground',
                                isActive(url, item) && 'text-foreground',
                            )}
                        >
                            <Icon className="size-5" />
                            {item.title}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
