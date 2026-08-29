/** 头像导出边长（正方形）。 */
export const AVATAR_SIZE = 256;

export type CropRect = {
    sx: number;
    sy: number;
    sWidth: number;
    sHeight: number;
};

/**
 * 源图中居中取出的最大正方形区域（中心裁切，无缩放交互）。
 */
export function centerCropRect(imageWidth: number, imageHeight: number): CropRect {
    const side = Math.min(imageWidth, imageHeight);

    return {
        sx: Math.floor((imageWidth - side) / 2),
        sy: Math.floor((imageHeight - side) / 2),
        sWidth: side,
        sHeight: side,
    };
}

/**
 * 将图片居中裁切成 AVATAR_SIZE 正方形，导出 webp Blob。
 * 仅浏览器可用（依赖 createImageBitmap / canvas）。
 */
export async function cropAvatarToSquare(file: File): Promise<Blob> {
    const bitmap = await createImageBitmap(file);

    try {
        const canvas = document.createElement('canvas');
        canvas.width = AVATAR_SIZE;
        canvas.height = AVATAR_SIZE;

        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('无法创建 2D 绘图上下文');
        }

        const { sx, sy, sWidth, sHeight } = centerCropRect(
            bitmap.width,
            bitmap.height,
        );

        context.drawImage(bitmap, sx, sy, sWidth, sHeight, 0, 0, AVATAR_SIZE, AVATAR_SIZE);

        return await new Promise<Blob>((resolve, reject) => {
            canvas.toBlob(
                (blob) => (blob ? resolve(blob) : reject(new Error('头像导出失败'))),
                'image/webp',
                0.9,
            );
        });
    } finally {
        bitmap.close();
    }
}
