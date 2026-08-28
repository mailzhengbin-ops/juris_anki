import { Form, Head } from '@inertiajs/react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';

export default function Login() {
    return (
        <>
            <Head title="登录" />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">邮箱</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="you@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">密码</Label>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            toast.info('密码重置暂未开通，敬请期待')
                                        }
                                        className="ml-auto text-sm text-muted-foreground underline-offset-4 hover:underline"
                                        tabIndex={5}
                                    >
                                        忘记密码？
                                    </button>
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="请输入密码"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">记住我</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                登录
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            还没有账号？{' '}
                            <TextLink href={register()} tabIndex={5}>
                                注册
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

        </>
    );
}

Login.layout = {
    title: '登录 Juris Anki',
    description: '输入邮箱和密码开始背诵',
};
