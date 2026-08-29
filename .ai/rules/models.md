---
paths:
  - 'app/Models/**'
---

# Models

## 模型 Append 属性类名是 Appends（复数）
Laravel 模型属性的访问器追加类名是 `Illuminate\Database\Eloquent\Attributes\Appends`（复数），不是 `Append`。写错类名会被静默忽略（不报错），导致序列化缺字段——前端拿不到追加属性。验证：tinker 里 `(new Model)->getAppends()` 应为非空。
