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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Security: Replace getimagesize() with \Maho\Io::getImageSize()
 * This prevents phar:// deserialization attacks
 */
final class SecureGetImageSizeRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace getimagesize() with safe wrapper to prevent phar:// deserialization',
            [
                new CodeSample(
                    '$size = getimagesize($path);',
                    '$size = \\Maho\\Io::getImageSize($path);',
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

        if (!$this->isName($node, 'getimagesize')) {
            return null;
        }

        // Skip Maho\Io itself - it contains the safe wrapper implementation
        $filePath = $this->file->getFilePath();
        if (str_contains($filePath, 'lib/Maho/Io.php')) {
            return null;
        }

        $args = $node->getArgs();

        // No arguments - malformed call, skip
        if (count($args) === 0) {
            return null;
        }

        // Get the first argument (filename)
        $filenameArg = $args[0];

        // Build: \Maho\Io::getImageSize($filename)
        return new StaticCall(
            new FullyQualified(\Maho\Io::class),
            'getImageSize',
            [$filenameArg],
        );
    }
}
