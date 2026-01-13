<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Security: Add allowed_classes => false to all unserialize() calls
 * This prevents object injection attacks via cache poisoning or other vectors
 */
final class SecureUnserializeRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add allowed_classes => false to unserialize() calls to prevent object injection',
            [
                new CodeSample(
                    '$data = unserialize($serialized);',
                    '$data = unserialize($serialized, [\'allowed_classes\' => false]);',
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall) {
            return null;
        }

        if (!$this->isName($node, 'unserialize')) {
            return null;
        }

        $args = $node->getArgs();

        // No arguments - malformed call, skip
        if (count($args) === 0) {
            return null;
        }

        // Already has second argument - check if it has allowed_classes
        if (count($args) >= 2) {
            $secondArg = $args[1]->value;

            // If it's an array, check for allowed_classes key
            if ($secondArg instanceof Array_) {
                foreach ($secondArg->items as $item) {
                    if ($item instanceof ArrayItem
                        && $item->key instanceof String_
                        && $item->key->value === 'allowed_classes'
                    ) {
                        // Already has allowed_classes, skip
                        return null;
                    }
                }

                // Add allowed_classes => false to existing array
                $secondArg->items[] = new ArrayItem(
                    new ConstFetch(new Name('false')),
                    new String_('allowed_classes'),
                );

                return $node;
            }

            // Second arg is not an array (e.g., variable), skip to be safe
            return null;
        }

        // Add second argument: ['allowed_classes' => false]
        $node->args[] = new Arg(
            new Array_([
                new ArrayItem(
                    new ConstFetch(new Name('false')),
                    new String_('allowed_classes'),
                ),
            ]),
        );

        return $node;
    }
}
