import { Head, Link } from '@inertiajs/react';
import { BookOpenText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { register } from '@/routes';

export default function Welcome() {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-10 bg-background p-6">
            <Head title="Juris Anki" />

            <div className="flex flex-col items-center gap-4">
                <div className="flex size-16 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                    <BookOpenText className="size-8" />
                </div>
                <div className="flex flex-col items-center gap-2 text-center">
                    <h1 className="text-3xl font-bold tracking-tight">
                        Juris Anki
                    </h1>
                    <p className="max-w-sm text-muted-foreground">
                        法律硕士考试卡片背诵系统--自选范围、单遍过卡、错题攻坚
                    </p>
                </div>
            </div>

            <div className="flex w-full max-w-xs flex-col gap-3">
                <Button asChild size="lg">
                    <Link href={register()}>注册账号</Link>
                </Button>
                <Button asChild size="lg" variant="outline">
                    <Link href={login()}>登录</Link>
                </Button>
            </div>
        </div>
    );
}
