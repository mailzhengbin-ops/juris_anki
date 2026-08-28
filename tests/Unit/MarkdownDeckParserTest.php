<?php

use App\Services\MarkdownDeckParser;
use App\Services\MarkdownParseException;

function assertParseFails(string $content, string $expectedFragment): void
{
    try {
        MarkdownDeckParser::parse($content);
        test()->fail('Expected MarkdownParseException but parse succeeded.');
    } catch (MarkdownParseException $e) {
        expect($e->getMessage())->toContain($expectedFragment);
    }
}

test('a valid template parses into the deck structure', function () {
    $content = file_get_contents(dirname(__DIR__, 2).'/card_template.md');

    $parsed = MarkdownDeckParser::parse($content);

    expect($parsed['name'])->toBe('刑法')
        ->and($parsed['sections'])->toHaveCount(2)
        ->and($parsed['sections'][0]['name'])->toBe('绪论')
        ->and($parsed['sections'][0]['cards'])->toHaveCount(3)
        ->and($parsed['sections'][0]['cards'][0]['question'])->toBe('刑法的任务')
        ->and($parsed['sections'][0]['cards'][0]['answer'])->toContain('保护国家安全')
        ->and($parsed['sections'][1]['name'])->toBe('犯罪构成')
        ->and($parsed['sections'][1]['cards'])->toHaveCount(3);
});

test('stray text outside headings and fences is ignored', function () {
    $content = <<<'MD'
# 刑法

这段游离文本应该被忽略

## 绪论

这也是游离文本

### 刑法的任务
```
答案一
```
MD;

    $parsed = MarkdownDeckParser::parse($content);

    expect($parsed['name'])->toBe('刑法')
        ->and($parsed['sections'][0]['cards'])->toHaveCount(1);
});

test('duplicate deck headings fail', function () {
    assertParseFails("# 刑法\n# 民法\n", '只能有一个 #');
});

test('a fourth-level heading fails and reports its line number', function () {
    assertParseFails("# 刑法\n## 绪论\n#### 太深的层级\n", '第 3 行');
});

test('a section heading before the deck heading fails', function () {
    assertParseFails("## 绪论\n", '## 只能出现在 #');
});

test('a card heading before any section fails', function () {
    assertParseFails("# 刑法\n### 刑法的任务\n", '### 只能出现在 ##');
});

test('an empty answer fails and reports the closing fence line', function () {
    assertParseFails("# 刑法\n## 绪论\n### 刑法的任务\n```\n```\n", '第 5 行');
});

test('a card without an answer fence fails', function () {
    assertParseFails("# 刑法\n## 绪论\n### 刑法的任务\n", '缺少答案围栏');
});

test('an unclosed answer fence fails', function () {
    assertParseFails("# 刑法\n## 绪论\n### 刑法的任务\n```\n答案\n", '围栏未闭合');
});

test('a fence inside an answer fails (empty answer on closing)', function () {
    assertParseFails("# 刑法\n## 绪论\n### 刑法的任务\n```\n```\n```\n答案\n", '答案不允许为空');
});

test('a document without any section fails', function () {
    assertParseFails("# 刑法\n", '缺少 ##');
});

test('a document without a deck heading fails at the first section heading', function () {
    assertParseFails("## 绪论\n### 任务\n```\n答案\n```\n", '## 只能出现在 #');
});

test('a deck with more than the card limit fails', function () {
    $content = "# 刑法\n## 绪论\n";
    $content .= str_repeat("### 问题\n```\n答案\n```\n", MarkdownDeckParser::MAX_CARDS + 1);

    assertParseFails($content, '卡片数量超过上限');
});

test('a deck exactly at the card limit parses', function () {
    $content = "# 刑法\n## 绪论\n";
    $content .= str_repeat("### 问题\n```\n答案\n```\n", MarkdownDeckParser::MAX_CARDS);

    $parsed = MarkdownDeckParser::parse($content);

    expect($parsed['sections'][0]['cards'])->toHaveCount(MarkdownDeckParser::MAX_CARDS);
});
