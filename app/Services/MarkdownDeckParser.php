<?php

namespace App\Services;

/**
 * 将 markdown 文档解析为卡组结构（卡组 -> 子卡组 -> 卡片）。
 *
 * 文档规范（见 readme.md 导入规则）：
 * - 一文档即一卡组：恰好一个 #（卡组名），至少一个 ##（子卡组），至少一个 ###（卡片问题），
 *   其后紧跟 ``` 围栏包裹的答案（不允许为空，按 markdown 原文保存）
 * - ## 只能出现在 # 之下；### 只能出现在 ## 之下；#### 及更深层级校验失败
 * - 标题与围栏之外的游离文本忽略；答案内不允许再出现 ```（围栏）
 * - 单文档最多 2000 张卡片
 *
 * 纯函数：无框架依赖，解析失败抛 MarkdownParseException（携带行号），由调用方转为验证错误。
 */
final class MarkdownDeckParser
{
    public const MAX_CARDS = 2000;

    /**
     * @return array{name: string, sections: array<int, array{name: string, cards: array<int, array{question: string, answer: string}>}>}
     *
     * @throws MarkdownParseException
     */
    public static function parse(string $content): array
    {
        $deckName = null;
        /** @var list<array{name: string, cards: list<array{question: string, answer: string|null}>}> */
        $sections = [];
        /** @var array{name: string, cards: list<array{question: string, answer: string|null}>}|null $currentSection */
        $currentSection = null;
        /** @var array{question: string, answer: string|null}|null $currentCard */
        $currentCard = null;
        $inAnswer = false;
        $answerLines = [];
        $cardCount = 0;

        // 必须带 u 修饰符：\R 在字节模式下会把 UTF-8 中文里出现的 0x85 字节误判为 NEL 换行
        /** @var list<string>|false */
        $lines = preg_split('/\R/u', $content);

        if ($lines === false) {
            self::fail(1, '文档无法解析');
        }

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if (preg_match('/^#{4,}\s/', $line)) {
                self::fail($lineNumber, '不支持四层及更深层级标题（####），卡组树只允许三层');
            }

            if (preg_match('/^###\s/', $line)) {
                self::closeAnswer($inAnswer, $lineNumber);

                if ($currentSection === null) {
                    self::fail($lineNumber, '### 只能出现在 ## 子卡组标题之下');
                }

                if ($currentCard !== null && $currentCard['answer'] === null) {
                    self::fail($lineNumber, '上一张卡片缺少答案围栏');
                }

                if (++$cardCount > self::MAX_CARDS) {
                    self::fail($lineNumber, sprintf('卡片数量超过上限（%d 张）', self::MAX_CARDS));
                }

                $currentSection['cards'][] = [
                    'question' => trim(substr($line, 3)),
                    'answer' => null,
                ];
                $currentCard = $currentSection['cards'][count($currentSection['cards']) - 1];

                continue;
            }

            if (preg_match('/^##\s/', $line)) {
                self::closeAnswer($inAnswer, $lineNumber);

                if ($deckName === null) {
                    self::fail($lineNumber, '## 只能出现在 # 卡组标题之下');
                }

                if ($currentSection !== null) {
                    $sections[] = $currentSection;
                }

                $currentSection = ['name' => trim(substr($line, 2)), 'cards' => []];
                $currentCard = null;

                continue;
            }

            if (preg_match('/^#\s/', $line)) {
                self::closeAnswer($inAnswer, $lineNumber);

                if ($deckName !== null) {
                    self::fail($lineNumber, '文档只能有一个 # 卡组标题');
                }

                $deckName = trim(substr($line, 1));

                continue;
            }

            if (trim($line) === '```') {
                if ($inAnswer) {
                    $answer = implode("\n", $answerLines);

                    if (trim($answer) === '') {
                        self::fail($lineNumber, '答案不允许为空');
                    }

                    if ($currentCard !== null && $currentSection !== null) {
                        $currentCard['answer'] = $answer;
                        $currentSection['cards'][count($currentSection['cards']) - 1] = $currentCard;
                    }

                    $inAnswer = false;
                    $answerLines = [];

                    continue;
                }

                if ($currentCard === null || $currentCard['answer'] !== null) {
                    self::fail($lineNumber, '``` 必须紧跟 ### 卡片问题之后');
                }

                $inAnswer = true;
                $answerLines = [];

                continue;
            }

            if ($inAnswer) {
                $answerLines[] = $line;
            }

            // 其余文本为游离文本，忽略
        }

        if ($inAnswer) {
            self::fail(count($lines), '答案围栏未闭合，缺少结尾 ```');
        }

        if ($currentCard !== null && $currentCard['answer'] === null) {
            self::fail(count($lines), '最后一张卡片缺少答案围栏');
        }

        if ($deckName === null) {
            self::fail(1, '缺少 # 卡组标题');
        }

        if ($currentSection !== null) {
            $sections[] = $currentSection;
        }

        if ($sections === []) {
            self::fail(1, '缺少 ## 子卡组标题');
        }

        return [
            'name' => $deckName,
            'sections' => $sections,
        ];
    }

    /**
     * 未闭合围栏检查（进入新结构或文档结束时调用）。
     */
    private static function closeAnswer(bool $inAnswer, int $lineNumber): void
    {
        if ($inAnswer) {
            self::fail($lineNumber, '答案围栏未闭合，缺少结尾 ```');
        }
    }

    /**
     * @throws MarkdownParseException
     */
    private static function fail(int $lineNumber, string $message): never
    {
        throw new MarkdownParseException($lineNumber, $message);
    }
}
