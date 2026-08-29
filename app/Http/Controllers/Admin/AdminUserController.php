<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    /**
     * 用户列表（按昵称/邮箱搜索）。
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $users = User::query()
            ->when($search !== '', fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->get(['id', 'name', 'email', 'is_admin', 'last_login_at', 'created_at']);

        return Inertia::render('admin/users/index', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'avatar_url' => $user->avatar_url,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
            'search' => $search,
        ]);
    }

    /**
     * 用户详情。
     */
    public function show(User $user): Response
    {
        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'decks_count' => $user->decks()->count(),
                'evaluations_count' => $user->evaluations()->count(),
            ],
        ]);
    }

    /**
     * 删除用户（级联删除其全部数据）。唯一管理员不可删除。
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, '不能删除当前登录的账号');

        abort_if(
            $user->is_admin && User::where('is_admin', true)->count() <= 1,
            422,
            '不能删除唯一的管理员账号',
        );

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => '用户已删除']);

        return to_route('admin.users');
    }
}
