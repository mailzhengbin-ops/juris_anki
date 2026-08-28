import {
    BarChart3,
    BookOpenText,
    ListChecks,
    UserRound
    
} from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import { recite, select, stats } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

export type UserNavItem = NavItem & {
    icon: LucideIcon;
    /** 激活匹配的前缀（默认等于 href） */
    matchPrefix?: string;
};

/** 背诵/选卡/统计三项主导航（用户端 tab 与侧边栏共用） */
export function primaryNavItems(): UserNavItem[] {
    return [
        { title: '背诵', href: recite(), icon: BookOpenText },
        { title: '选卡', href: select(), icon: ListChecks },
        { title: '统计', href: stats(), icon: BarChart3 },
    ];
}

/** “我的”导航项（指向设置页） */
export function profileNavItem(): UserNavItem {
    return {
        title: '我的',
        href: editProfile(),
        icon: UserRound,
        matchPrefix: '/settings',
    };
}

export function isUserNavItemActive(url: string, item: UserNavItem): boolean {
    const prefix = item.matchPrefix ?? item.href;
    const href = typeof prefix === 'string' ? prefix : prefix.url;

    return url === href || url.startsWith(`${href}/`);
}
