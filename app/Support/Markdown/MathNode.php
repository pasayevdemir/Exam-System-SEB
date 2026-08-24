<?php

namespace App\Support\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * A `$...$` / `$$...$$` span, kept as opaque text through the whole markdown
 * pass so KaTeX receives exactly what the author typed.
 */
final class MathNode extends AbstractInline
{
    private string $literal;

    private bool $display;

    public function __construct(string $literal, bool $display)
    {
        parent::__construct();

        $this->literal = $literal;
        $this->display = $display;
    }

    public function getLiteral(): string
    {
        return $this->literal;
    }

    public function isDisplay(): bool
    {
        return $this->display;
    }
}
