import { Link } from '@inertiajs/react';
import { FolderKanban, LayoutDashboard, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    dashboard as adminDashboard,
    decks as adminDecks,
    users as adminUsers,
} from '@/routes/admin';

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={adminDashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarMenu>
                    <SidebarMenuButton asChild>
                        <Link href={adminDashboard()} prefetch>
                            <LayoutDashboard />
                            <span>仪表盘</span>
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuButton asChild>
                        <Link href={adminDecks()} prefetch>
                            <FolderKanban />
                            <span>卡片管理</span>
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuButton asChild>
                        <Link href={adminUsers()} prefetch>
                            <Users />
                            <span>用户管理</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenu>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
