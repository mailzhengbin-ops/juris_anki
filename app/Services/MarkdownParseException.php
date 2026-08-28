<?php

namespace App\Services;

use RuntimeException;

/**
 * markdown 解析失败，携带行号信息（纯异常，无框架依赖）。
 */
final class MarkdownParseException extends RuntimeException
{
    public function __construct(
        public readonly int $lineNumber,
        string $message,
    ) {
        parent::__construct(sprintf('第 %d 行：%s', $lineNumber, $message));
    }
}
