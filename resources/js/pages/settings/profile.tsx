import { Form, Head, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { cropAvatarToSquare } from '@/lib/avatar';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

export default function Profile() {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [clientError, setClientError] = useState<string | null>(null);
    const form = useForm<{ avatar: File | null }>({ avatar: null });

    function clearPreview() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            setPreviewUrl(null);
        }
    }

    async function handleFileChange(event: ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setClientError(null);

        if (file.size > MAX_AVATAR_BYTES) {
            setClientError('图片大小不能超过 2MB');

            return;
        }

        try {
            const blob = await cropAvatarToSquare(file);
            const cropped = new File([blob], 'avatar.webp', { type: 'image/webp' });

            form.setData('avatar', cropped);
            clearPreview();
            setPreviewUrl(URL.createObjectURL(blob));
        } catch {
            setClientError('图片读取失败，请换一张图片试试');
        }
    }

    function uploadAvatar() {
        form.post(ProfileController.updateAvatar.url(), {
            onSuccess: () => {
                clearPreview();
                form.reset();
            },
        });
    }

    function removeAvatar() {
        router.delete(ProfileController.destroyAvatar.url());
    }

    return (
        <>
            <Head title="个人资料" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="个人资料"
                    description="修改你的昵称与邮箱"
                />

                <div className="space-y-4">
                    <div className="flex items-center gap-4">
                        <Avatar className="size-16">
                            {previewUrl ? (
                                <AvatarImage src={previewUrl} alt="头像预览" />
                            ) : (
                                auth.user.avatar_url && (
                                    <AvatarImage
                                        src={auth.user.avatar_url}
                                        alt={auth.user.name}
                                    />
                                )
                            )}
                            <AvatarFallback className="text-lg">
                                {getInitials(auth.user.name)}
                            </AvatarFallback>
                        </Avatar>

                        <div className="flex flex-col items-start gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (fileInputRef.current) {
                                        fileInputRef.current.value = '';
                                        fileInputRef.current.click();
                                    }
                                }}
                            >
                                选择图片
                            </Button>
                            {auth.user.avatar_url && !previewUrl && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={removeAvatar}
                                >
                                    移除头像
                                </Button>
                            )}
                        </div>
                    </div>

                    <Input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={handleFileChange}
                    />

                    {clientError && (
                        <p className="text-sm text-destructive">{clientError}</p>
                    )}
                    {form.errors.avatar && (
                        <InputError message={form.errors.avatar} />
                    )}

                    {previewUrl && (
                        <Button
                            type="button"
                            size="sm"
                            disabled={form.processing}
                            onClick={uploadAvatar}
                        >
                            {form.processing ? '上传中…' : '上传头像'}
                        </Button>
                    )}
                </div>

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">昵称</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="你的昵称"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">邮箱</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="you@example.com"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    保存
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}
