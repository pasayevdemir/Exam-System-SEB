<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private static ?MarkdownConverter $converter = null;

    private static function getConverter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }

    public static function render(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        return self::getConverter()->convert($text)->getContent();
    }
}
