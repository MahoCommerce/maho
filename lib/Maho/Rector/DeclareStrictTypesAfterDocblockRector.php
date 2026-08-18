<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Rector;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Nop;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Maho convention: the file-level docblock comes first, declare(strict_types=1)
 * directly after it. SafeDeclareStrictTypesRector prepends the declare above
 * everything, which strands the docblock below it; this rule moves the docblock
 * back on top.
 */
final class DeclareStrictTypesAfterDocblockRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Move the file-level docblock above declare(strict_types=1)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 */
$installer = $this;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 */

declare(strict_types=1);

$installer = $this;
CODE_SAMPLE,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /**
     * @param FileNode $node
     */
    #[\Override]
    public function refactor(Node $node): ?FileNode
    {
        $declare = $node->stmts[0] ?? null;
        if (!$declare instanceof Declare_ || !$this->declaresStrictTypes($declare)) {
            return null;
        }
        // A commented declare already sits below the file docblock
        if ($declare->getComments() !== []) {
            return null;
        }

        foreach ($node->stmts as $index => $stmt) {
            if ($index === 0 || $stmt instanceof Nop && $stmt->getComments() === []) {
                continue;
            }
            $comments = $stmt->getComments();
            $first = $comments[0] ?? null;
            if ($first !== null && $this->isFileDocblock($first)) {
                // The trailing newline keeps a blank line between the docblock
                // and the declare, matching the hand-written core files
                $declare->setAttribute('comments', [new Comment\Doc($first->getText() . "\n")]);
                $stmt->setAttribute('comments', array_slice($comments, 1));
                return $node;
            }
            // Only the statement directly following the declare may carry the file docblock
            return null;
        }

        return null;
    }

    private function declaresStrictTypes(Declare_ $declare): bool
    {
        return array_any($declare->declares, fn($declareItem) => $this->isName($declareItem->key, 'strict_types'));
    }

    private function isFileDocblock(Comment $comment): bool
    {
        $text = $comment->getText();
        return str_starts_with($text, '/**')
            && (str_contains($text, 'SPDX-FileCopyrightText')
                || str_contains($text, '@copyright')
                || str_contains($text, '@package'));
    }
}
