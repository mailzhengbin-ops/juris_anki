# 头像上传功能 — Spec

Status: implemented

## 背景

用户（含管理员，无代传）在个人资料页上传自己的头像，展示于导航栏、个人资料页与管理端用户列表。未上传时保持姓名首字母占位（现状）。

## 需求

1. **上传**：`POST settings/profile/avatar`（`profile.avatar.update`）
   - 校验：必填、图片、jpg/jpeg/png/webp、≤ 2MB（服务端兜底）
   - 前端裁剪：浏览器原生 canvas 居中裁切为 256×256（零新依赖），导出 webp 后上传
   - 存储：`public` 磁盘 `storage/avatars/`，需执行 `php artisan storage:link`
   - 重复上传：删除旧文件，避免孤儿堆积
   - 成功：toast + 重定向回资料页
2. **移除**：`DELETE settings/profile/avatar`（`profile.avatar.destroy`）
   - 删除存储文件并清空字段；无头像时幂等
   - 成功：toast + 重定向回资料页
3. **数据**：`users` 表新增 `avatar` 列（nullable）；模型提供 `avatar_url`（public URL）供共享 props 与序列化
4. **展示**：
   - 导航栏（app-header）与侧栏（user-info）改用 `avatar_url`，无值时首字母占位（现状 fallback）
   - 管理端用户列表（admin/users）新增头像列（只读展示，无代传）
5. **个人资料页**：头像区 = 当前头像/首字母预览 + 「选择图片」+「移除头像」（已有头像时）+「上传头像」（选图裁切后出现）+ 客户端大小预检与错误提示

## 非目标

- 不做拖拽/缩放裁剪交互（本期零依赖居中裁切，后续如需可引入裁剪库）
- 不引入 Gravatar 等默认头像服务
- 管理员不能代传头像

## 测试

- 后端 Feature tests（`tests/Feature/Settings/AvatarTest.php`）：未认证重定向、上传成功、非法类型 422、超 2MB 422、替换删旧文件、移除、幂等移除
- 前端 vitest（`resources/js/lib/avatar.test.ts`）：`centerCropRect` 几何（正方形/横图/竖图/奇数差值）
