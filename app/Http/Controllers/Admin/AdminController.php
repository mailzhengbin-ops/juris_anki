<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * 管理端仪表盘：数据类（用户数、系统卡组数）+ 环境类（运行环境）。
     */
    public function dashboard(Request $request): Response
    {
        return Inertia::render('admin/dashboard', [
            'data' => [
                'users_count' => User::count(),
                'system_decks_count' => Deck::system()->count(),
            ],
            'environment' => [
                'php_version' => PHP_VERSION,
                'server_software' => $request->server('SERVER_SOFTWARE') ?: 'CLI',
                'database' => config('database.default').' '.$this->databaseVersion(),
                'laravel_version' => app()->version(),
            ],
        ]);
    }

    private function databaseVersion(): string
    {
        try {
            return (string) DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            return '(unknown)';
        }
    }
}
