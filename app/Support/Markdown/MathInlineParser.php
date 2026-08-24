<?php

namespace App\Support\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Claims `$...$` and `$$...$$` spans before any other inline processing runs.
 *
 * Without this, markdown gets to the formula first: `$x_1 y_2$` comes out as
 * `$x<em>1 y</em>2$` and KaTeX is handed something the author never wrote.
 */
final class MathInlineParser implements InlineParserInterface
{
    /**
     * Both delimiters refuse a leading backslash so `\$` still means a literal
     * dollar sign, and the inline form refuses whitespace right inside the
     * delimiters — that is what keeps "costs $5 and $10" out of math mode.
     */
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?<!\\\\)\$\$(.+?)\$\$|(?<!\\\\)\$([^\s$](?:[^$]*?[^\s$])?)\$');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $subMatches = $inlineContext->getSubMatches();

        $display = ($subMatches[0] ?? '') !== '';
        $literal = $display ? $subMatches[0] : ($subMatches[1] ?? '');

        if (\trim($literal) === '') {
            return false;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new MathNode(\trim($literal), $display));

        return true;
    }
}
