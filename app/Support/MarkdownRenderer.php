<?php

namespace App\Support;

use App\Support\Markdown\MathInlineParser;
use App\Support\Markdown\MathNode;
use App\Support\Markdown\MathNodeRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private static ?MarkdownConverter $converter = null;

    private static function getConverter(): MarkdownConverter
    {
        if (self::$converter === null) {
            // Question and answer text is admin-authored, but the rendered HTML
            // is echoed unescaped onto the student's exam page — so raw HTML is
            // escaped rather than passed through (CommonMark's default is
            // 'allow', which would make a pasted <script> a stored XSS), and
            // javascript:/data: links are dropped.
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
            ]);

            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);

            // Math has to be claimed while the cursor is still on the opening
            // `$`, or `$x_1 y_2$` loses its underscores to <em> before KaTeX
            // ever sees the formula.
            $environment->addInlineParser(new MathInlineParser, 100);
            $environment->addRenderer(MathNode::class, new MathNodeRenderer);

            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }

    public static function render(?string $text): string
    {
        // Not empty(): a question or answer whose whole text is "0" is valid
        // content, and empty("0") is true.
        if ($text === null || $text === '') {
            return '';
        }

        return self::getConverter()->convert($text)->getContent();
    }

    /**
     * render() minus the wrapping <p> when the result is a single paragraph.
     *
     * The answer options put rendered text inside a <label>, which may only
     * hold phrasing content — a <p> there is invalid and breaks the label's
     * click-to-select. Multi-block text (a list, a table) still comes back as
     * blocks; that is rare enough to leave to the browser's tolerance.
     */
    public static function renderInline(?string $text): string
    {
        $html = \trim(self::render($text));

        if (\preg_match('/^<p>(.*)<\/p>$/s', $html, $matches) && ! \str_contains($matches[1], '<p>')) {
            return $matches[1];
        }

        return $html;
    }

    /**
     * A one-line plain-text preview: markdown rendered, tags dropped, runs of
     * whitespace collapsed, then truncated.
     *
     * Truncating the *source* instead — Str::limit over raw markdown — cuts
     * code fences and emphasis in half, and the listing then shows the broken
     * syntax rather than the question. Cutting the rendered text avoids that.
     *
     * Returns text, not HTML — the caller must escape it ({{ }} does).
     */
    public static function plainText(?string $text, int $limit = 160): string
    {
        $plain = \html_entity_decode(
            \strip_tags(self::render($text)),
            \ENT_QUOTES | \ENT_HTML5,
            'UTF-8'
        );

        $plain = \trim((string) \preg_replace('/\s+/u', ' ', $plain));

        if (\mb_strlen($plain) <= $limit) {
            return $plain;
        }

        return \rtrim(\mb_substr($plain, 0, $limit)).'…';
    }
}
