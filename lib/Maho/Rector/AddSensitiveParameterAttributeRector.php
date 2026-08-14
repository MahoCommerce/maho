<?php

/**
 * Ported from Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector (rector/rector 2.6.1).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-present Tomáš Votruba <https://tomasvotruba.cz>
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Maho\Rector;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersionFeature;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Add #[\SensitiveParameter] to parameters whose name is in the configured list.
 *
 * Rector deprecated this rule in 2.6.2, because
 * matching by parameter name is vague. We accept that trade-off: the list is short
 * and Maho-specific, and an over-broad match only hides a value from stack traces.
 */
final class AddSensitiveParameterAttributeRector extends AbstractRector implements ConfigurableRectorInterface, MinPhpVersionInterface
{
    public const string SENSITIVE_PARAMETERS = 'sensitive_parameters';

    /** @var string[] */
    private array $sensitiveParameters = [];

    public function __construct(
        private readonly PhpAttributeAnalyzer $phpAttributeAnalyzer,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     */
    #[\Override]
    public function configure(array $configuration): void
    {
        $this->sensitiveParameters = array_map(strval(...), (array) ($configuration[self::SENSITIVE_PARAMETERS] ?? []));
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add SensitiveParameter attribute to method and function configured parameters',
            [
                new ConfiguredCodeSample(
                    'public function run(string $password) {}',
                    'public function run(#[\SensitiveParameter] string $password) {}',
                    [self::SENSITIVE_PARAMETERS => ['password']],
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Param::class];
    }

    #[\Override]
    public function refactor(Node $node): ?Param
    {
        if (!$node instanceof Param) {
            return null;
        }

        if (!$this->isNames($node, $this->sensitiveParameters)) {
            return null;
        }

        if ($this->phpAttributeAnalyzer->hasPhpAttribute($node, 'SensitiveParameter')) {
            return null;
        }

        $node->attrGroups[] = new AttributeGroup([new Attribute(new FullyQualified('SensitiveParameter'))]);

        return $node;
    }

    #[\Override]
    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::SENSITIVE_PARAMETER_ATTRIBUTE;
    }
}
