import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

/** Wayfinder action 的调用结果（含 url 与 HTTP method）。 */
export type ActionRoute = {
    url: string;
    method: 'post' | 'patch' | 'delete' | 'put';
};

/** Inertia 请求数据（跟随已安装版本的 router.post 签名）。 */
type Payload = Parameters<typeof router.post>[1];

type SubmitOptions = {
    /** 错误 toast 的兜底文案；优先展示服务端返回的第一条校验错误。 */
    error?: string;
    /** 发送前的确认文案；用户取消则不发请求。 */
    confirm?: string;
    /** 保持地址栏不变（POST 直返渲染时防止浏览器 URL 停留到 POST 路径）。 */
    preserveUrl?: boolean;
    /** 请求结束后的额外收尾（processing 复位之外）。 */
    onFinish?: () => void;
};

/**
 * Inertia 提交动作的统一入口：processing 生命周期、preserveScroll、错误 toast
 * 与破坏性操作确认全部内藏，调用方只提供 action 与数据。
 * 成功提示不走此 hook--由服务端 Inertia::flash 统一弹出（见 use-flash-toast）。
 */
export function useAction() {
    const [processing, setProcessing] = useState(false);

    function submit(
        action: ActionRoute,
        data?: Payload,
        options: SubmitOptions = {},
    ): void {
        if (options.confirm && !window.confirm(options.confirm)) {
            return;
        }

        setProcessing(true);

        router.visit(action.url, {
            method: action.method,
            data,
            preserveScroll: true,
            preserveUrl: options.preserveUrl,
            onError: (errors) => {
                toast.error(
                    Object.values(errors)[0] ??
                        options.error ??
                        '操作失败，请重试',
                );
            },
            onFinish: () => {
                setProcessing(false);
                options.onFinish?.();
            },
        });
    }

    return { processing, submit };
}
