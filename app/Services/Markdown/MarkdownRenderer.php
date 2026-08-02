<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Markdown;

use League\CommonMark\GithubFlavoredMarkdownConverter;

class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Defaults to PHP_INT_MAX; deeper nesting is flattened rather than parsed.
            'max_nesting_level' => 50,
        ]);

        return $converter->convert($markdown)->getContent();
    }
}
