<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeckImportService
{
    public const MAX_DOCUMENT_BYTES = 2 * 1024 * 1024;

    /**
     * 解析并原子创建卡组。拥有者为 null 时创建系统卡组，否则创建用户卡组。
     *
     * @throws ValidationException
     */
    public function importFor(?User $owner, string $content): Deck
    {
        if (strlen($content) > self::MAX_DOCUMENT_BYTES) {
            throw ValidationException::withMessages([
                'document' => '文档超过 2MB 上限',
            ]);
        }

        try {
            $parsed = MarkdownDeckParser::parse($content);
        } catch (MarkdownParseException $e) {
            throw ValidationException::withMessages(['document' => $e->getMessage()]);
        }

        $duplicate = Deck::where('user_id', $owner?->id)
            ->where('name', $parsed['name'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'document' => sprintf('已存在同名卡组「%s」，请修改文档标题后重新导入', $parsed['name']),
            ]);
        }

        return DB::transaction(function () use ($owner, $parsed) {
            $deck = Deck::create([
                'user_id' => $owner?->id,
                'name' => $parsed['name'],
            ]);

            foreach ($parsed['sections'] as $sectionIndex => $section) {
                $createdSection = $deck->sections()->create([
                    'name' => $section['name'],
                    'position' => $sectionIndex + 1,
                ]);

                foreach ($section['cards'] as $cardIndex => $card) {
                    $createdSection->cards()->create([
                        'question' => $card['question'],
                        'answer' => $card['answer'],
                        'position' => $cardIndex + 1,
                    ]);
                }
            }

            return $deck;
        });
    }
}
