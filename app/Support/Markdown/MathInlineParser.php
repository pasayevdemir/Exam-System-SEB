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
     * Three guards, each earning its place against real question text:
     *
     * - a leading backslash is refused, so `\$` still means a literal dollar
     * - whitespace right inside the delimiters is refused, which keeps
     *   "costs $5 and $10" out of math mode
     * - a digit straight after the closing delimiter is refused, which stops
     *   "$5-$7" being read as the formula "5-" trailed by a stray 7
     */
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?<!\\\\)\$\$(.+?)\$\$|(?<!\\\\)\$([^\s$](?:[^$]*?[^\s$])?)\$(?!\d)');
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
