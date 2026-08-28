<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Support\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Emits the formula as escaped text in a marker span; KaTeX replaces the span's
 * contents in the browser (see resources/js/app.js). Rendering server-side would
 * mean shipping a PHP TeX engine for what the client already does well.
 */
final class MathNodeRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        MathNode::assertInstanceOf($node);
        \assert($node instanceof MathNode);

        return new HtmlElement('span', [
            'class' => 'ps-math',
            'data-display' => $node->isDisplay() ? '1' : '0',
        ], \htmlspecialchars($node->getLiteral(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }
}
