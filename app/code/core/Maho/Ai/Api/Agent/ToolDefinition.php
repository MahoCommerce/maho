<?php

/**
 * Builds the Symfony AI tool definitions of the admin assistant.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ToolDefinition
{
    /**
     * @param array<string, mixed>|null $parameters a JSON Schema object, nested items and properties allowed
     * @param array<string, mixed> $metadata
     */
    public static function create(
        ExecutionReference $reference,
        string $name,
        string $description,
        ?array $parameters = null,
        array $metadata = [],
    ): Tool {
        // TODO: remove this class when a release has https://github.com/symfony/ai/pull/2637. Then ContentGuideTool passes null, and only McpToolCatalog, whose schema is built at runtime, keeps this ignore.
        // @phpstan-ignore argument.type (the vendor JsonSchema type allows only flat properties)
        return new Tool($reference, $name, $description, $parameters, $metadata);
    }
}
