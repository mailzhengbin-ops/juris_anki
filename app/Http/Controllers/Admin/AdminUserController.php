<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    /**
     * 用户管理列表（占位，工单 11 实现）。
     */
    public function index(): Response
    {
        return Inertia::render('admin/users/index');
    }
}
