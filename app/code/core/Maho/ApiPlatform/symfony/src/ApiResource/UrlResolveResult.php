<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Provider\UrlResolverProvider;

#[ApiResource(
    shortName: 'UrlResolveResult',
    description: 'URL resolution result - maps URLs to their targets',
    provider: UrlResolverProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/url-resolver',
            description: 'Resolve a URL path to its target (use ?path=your-url)',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'resolveUrl',
            args: ['path' => ['type' => 'String!']],
            description: 'Resolve a URL path to its target',
            resolver: CustomQueryResolver::class,
        ),
    ],
)]
class UrlResolveResult
{
    /** @var string The resolved entity type: 'cms_page', 'category', 'product', or 'not_found' */
    public string $type = 'not_found';

    /** @var int|null The entity ID */
    public ?int $id = null;

    /** @var string|null The identifier/URL key */
    public ?string $identifier = null;

    /** @var string|null Redirect URL if this is a redirect */
    public ?string $redirectUrl = null;

    /** @var int Redirect type (301, 302) or 0 if not a redirect */
    public int $redirectType = 0;
}
