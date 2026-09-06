<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * A two-placeholder BETWEEN bound with one array puts the whole list in both slots, which
 * the adapter now rejects at run time. orWhere() is left alone: only where() and having()
 * chain with AND, so only there do the two halves keep the meaning of the range.
 */
final class SplitBetweenBindRector extends AbstractRector
{
    private const METHODS = ['where', 'having'];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Split a BETWEEN condition bound with one array into two single-placeholder conditions',
            [
                new CodeSample(
                    '$select->where(\'created_at BETWEEN ? AND ?\', [$from, $to]);',
                    '$select->where(\'created_at >= ?\', $from)->where(\'created_at <= ?\', $to);',
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        $method = null;
        foreach (self::METHODS as $candidate) {
            if ($this->isName($node->name, $candidate)) {
                $method = $candidate;
            }
        }
        if ($method === null) {
            return null;
        }

        $args = $node->getArgs();
        if (count($args) !== 2) {
            return null;
        }

        $condition = $args[0]->value;
        $value     = $args[1]->value;
        if (!$condition instanceof String_ || !$value instanceof Array_) {
            return null;
        }

        $items = $value->items;
        if (count($items) !== 2) {
            return null;
        }
        foreach ($items as $item) {
            if (!$item instanceof ArrayItem || $item->key !== null || $item->unpack) {
                return null;
            }
        }

        if (preg_match('/^(?<field>[^?]+?)\s+BETWEEN\s+\?\s+AND\s+\?$/i', trim($condition->value), $match) !== 1) {
            return null;
        }

        $lower = new MethodCall($node->var, $method, [
            new Arg(new String_($match['field'] . ' >= ?')),
            new Arg($items[0]->value),
        ]);

        return new MethodCall($lower, $method, [
            new Arg(new String_($match['field'] . ' <= ?')),
            new Arg($items[1]->value),
        ]);
    }
}
