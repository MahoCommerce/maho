<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;

uses(Tests\MahoBackendTestCase::class);

/**
 * A tool derived from a create once read the record before the processor ran. The provider got
 * ID 0 and answered 404, so the create tool of cart price rules never created a rule.
 */
function resolvedOperation(Operation $source): Operation
{
    $class = stdClass::class;
    $factory = new class ($class, $source) implements ResourceMetadataCollectionFactoryInterface {
        public function __construct(private string $class, private Operation $source) {}

        #[\Override]
        public function create(string $resourceClass): ResourceMetadataCollection
        {
            $resource = new ApiResource(class: $this->class)->withOperations(new Operations(['source' => $this->source->withClass($this->class)->withName('source')]));

            return new ResourceMetadataCollection($this->class, [$resource]);
        }
    };
    $tool = new McpTool(name: 'tool', class: $class, extraProperties: [SourceOperationResolver::SOURCE_OPERATION => 'source']);

    return new SourceOperationResolver($factory)->resolve($tool);
}

it('reads the record over MCP only when REST reads it', function (): void {
    expect(resolvedOperation(new Post(uriTemplate: '/rules'))->canRead())->toBeFalse();
    expect(resolvedOperation(new Put(uriTemplate: '/rules/{id}', uriVariables: ['id']))->canRead())->toBeTrue();
    expect(resolvedOperation(new Get(uriTemplate: '/rules/{id}', uriVariables: ['id']))->canRead())->toBeTrue();
    expect(resolvedOperation(new GetCollection(uriTemplate: '/rules'))->canRead())->toBeTrue();
    expect(resolvedOperation(new Post(uriTemplate: '/rules', read: true))->canRead())->toBeTrue();
});
