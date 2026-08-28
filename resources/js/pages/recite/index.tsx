import UserLayout from '@/layouts/user/user-layout';

export default function Recite() {
    return (
        <UserLayout title="背诵">
            <div className="flex flex-col items-center justify-center gap-2 py-24 text-center text-muted-foreground">
                <p className="text-lg font-medium text-foreground">
                    当前您没有选择背诵源
                </p>
                <p>请到选卡页面选择！</p>
            </div>
        </UserLayout>
    );
}
