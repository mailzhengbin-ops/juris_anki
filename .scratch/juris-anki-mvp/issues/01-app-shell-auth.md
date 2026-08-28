# 01: 应用骨架与账号策略

**What to build:** 游客可注册（邮箱+昵称+密码）、登录、退出；登录后直接进入背诵页占位。用户端采用移动优先布局：移动端底部 tab（背诵/选卡/统计/我的），桌面顶部导航，四页均有占位。不启用邮箱验证与密码重置（登录页保留"忘记密码"入口但点击提示暂未开通）。未登录访问用户端页面一律跳转登录页；已登录访问首页重定向到背诵页。

**Blocked by:** None（可立即开始）

**Status:** ready-for-agent

- [ ] 游客可注册并登录成功，落地背诵页
- [ ] 注册字段为邮箱+昵称+密码，全程无邮箱验证步骤
- [ ] 登录页"忘记密码"点击提示暂未开通
- [ ] 移动端底部四 tab 导航、桌面顶部导航，背诵/选卡/统计/我的均有占位页
- [ ] 未登录访问用户端任一页面跳转登录页
- [ ] 已登录访问首页重定向到背诵页
- [ ] 原仪表盘页与其测试移除，邮箱验证相关中间件从路由清除
- [ ] 既有测试套件全绿

## Comments

- 2026-08-28 实施完成：应用骨架（移动底部 tab + 桌面顶部导航）、登录/注册页中文化、忘记密码入口 toast 提示、Fortify 关闭邮箱验证与密码重置、home 重定向 /recite、移除 dashboard 及 verify-email/forgot-password/reset-password 死代码；全量测试 29 通过。随提交附 ReciteTest（guest 重定向/authed 访问/home 重定向/select+stats guest 重定向）。
- 2026-08-28 code-review（Standards+Spec 双轴）修复：passkey 登录死路由 /dashboard 改命名路由 /recite；“我的”tab 纳入 UserLayout（settings 页从侧边栏布局迁至用户端布局，app.tsx 布局解析统一）；主导航提取共享配置 lib/user-nav；登录页死 status prop 清除；导航/文案补破折号；ReciteTest 拆分。settings 页（安全/外观）内容深度中文化移交工单 08。最终全量测试 30 通过。
