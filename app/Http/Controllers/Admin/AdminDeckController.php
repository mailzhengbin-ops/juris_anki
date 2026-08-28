<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminDeckController extends Controller
{
    /**
     * 系统卡组管理列表（占位，工单 10 实现）。
     */
    public function index(): Response
    {
        return Inertia::render('admin/decks/index');
    }
}
