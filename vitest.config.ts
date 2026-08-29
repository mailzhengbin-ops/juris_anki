import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * 前端测试配置：纯函数模块（lib/）在 node 环境直接测，无需 DOM 与
 * vite.config.ts 的框架插件（laravel/inertia/react 编译器等）。
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
