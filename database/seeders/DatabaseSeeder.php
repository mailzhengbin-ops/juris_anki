<?php

namespace Database\Seeders;

use App\Models\Deck;
use App\Models\User;
use App\Services\DeckImportService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(DeckImportService $importService): void
    {
        $this->seedAdmin();
        $this->seedCriminalLawDeck($importService);
    }

    private function seedAdmin(): void
    {
        $email = config('app.admin.email');
        $password = config('app.admin.password');

        User::factory()->admin()->create([
            'name' => '管理员',
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * 演示系统卡组「刑法」：直接走导入管线读 card_template.md，
     * 模板本身即导入规则的活文档（种子执行即验证模板可被解析）。
     */
    private function seedCriminalLawDeck(DeckImportService $importService): void
    {
        if (Deck::system()->where('name', '刑法')->exists()) {
            return;
        }

        $importService->importFor(
            null,
            File::get(base_path('card_template.md')),
        );
    }
}
