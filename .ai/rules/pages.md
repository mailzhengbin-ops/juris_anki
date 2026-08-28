---
paths:
  - 'resources/js/pages/**'
---

# Pages

## 布局间距由布局层统一提供，页面不自包外层容器
AppSidebarLayout（管理端侧边栏布局）只提供框架（侧边栏+面包屑条+内容容器），不提供内边距；布局层已在 AppContent 内对内容区统一包裹 p-4 md:p-6（app-sidebar-layout.tsx）。新建管理端页面不要再自行包外层间距容器，避免双重 padding；页面内容直接用 space-y/grid 等排布。用户端页面由 UserLayout 的 main 统一提供间距，同样无需自包。写页面时必须先激活 tailwindcss-development / inertia-react-development 技能（CLAUDE.md 强制）。
