<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Deck;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * 演示系统卡组「刑法」：结构与 card_template.md 一致，供导入管线就绪前的开发使用。
     */
    private const CRIMINAL_LAW_DECK = [
        'name' => '刑法',
        'sections' => [
            '绪论' => [
                '刑法的任务' => '刑法的任务，是通过规定犯罪和刑罚，惩罚犯罪，保护国家安全、人民民主专政的政权和社会主义制度，保护国有财产、劳动群众集体所有的财产和公民私人所有的财产，保护公民的人身权利、民主权利和其他权利，维护社会秩序，保障社会主义建设事业的顺利进行。',
                '刑法的概念' => '刑法是规定犯罪、刑事责任和刑罚的法律，是统治阶级为了维护其阶级利益和统治秩序，根据自己的意志制定或者认可的，并以国家强制力保证实施的法律规范的总和。',
                '刑法的原则' => '刑法的基本原则是贯穿刑法始终、具有全局性和根本性的指导原则，包括罪刑法定原则、刑法适用平等原则和罪责刑相适应原则。其中，罪刑法定原则要求法律明文规定为犯罪行为的，依照法律定罪处刑；刑法适用平等原则要求对任何人犯罪，在适用法律上一律平等，不允许任何人有超越法律的特权；罪责刑相适应原则要求刑罚的轻重应当与犯罪分子所犯罪行和承担的刑事责任相适应。',
            ],
            '犯罪构成' => [
                '犯罪构成的概念' => '犯罪构成是依照我国刑法的规定，决定某一具体行为的社会危害性及其程度，并据以确定该行为构成犯罪所必须具备的一切主客观要件的总和。',
                '犯罪构成的要件' => '犯罪构成包括犯罪客体、犯罪客观方面、犯罪主体和犯罪主观方面四个要件。其中，犯罪客体是犯罪行为所侵犯的法益；犯罪客观方面是犯罪行为的客观表现；犯罪主体是实施犯罪行为并依法承担刑事责任的人；犯罪主观方面是行为人对其实施的犯罪行为及其结果所持的心理态度。',
            ],
        ],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedCriminalLawDeck();
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

    private function seedCriminalLawDeck(): void
    {
        if (Deck::whereNull('user_id')->where('name', self::CRIMINAL_LAW_DECK['name'])->exists()) {
            return;
        }

        $deck = Deck::create(['user_id' => null, 'name' => self::CRIMINAL_LAW_DECK['name']]);

        foreach (self::CRIMINAL_LAW_DECK['sections'] as $sectionName => $cards) {
            $section = Section::create([
                'deck_id' => $deck->id,
                'name' => $sectionName,
                'position' => $deck->sections()->count() + 1,
            ]);

            $position = 0;

            foreach ($cards as $question => $answer) {
                Card::create([
                    'section_id' => $section->id,
                    'question' => $question,
                    'answer' => $answer,
                    'position' => ++$position,
                ]);
            }
        }
    }
}
