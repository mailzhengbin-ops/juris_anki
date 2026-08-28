import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import {
    isUserNavItemActive,
    primaryNavItems,
    profileNavItem
    
} from '@/lib/user-nav';
import type {UserNavItem} from '@/lib/user-nav';
import { cn } from '@/lib/utils';

/** 用户端布局：桌面顶部导航 + 移动端底部 tab */
export default function UserLayout({ children }: PropsWithChildren) {
    const items: UserNavItem[] = [...primaryNavItems(), profileNavItem()];
    const { url } = usePage();

    return (
        <div className="min-h-svh bg-background">
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
                                    isUserNavItemActive(url, item) &&
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
                                isUserNavItemActive(url, item) &&
                                    'text-foreground',
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
