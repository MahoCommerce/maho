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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Query;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Provider\StoreConfigProvider;

#[ApiResource(
    shortName: 'StoreConfig',
    description: 'Store configuration for frontend initialization',
    provider: StoreConfigProvider::class,
    operations: [
        new Get(
            uriTemplate: '/store-config',
            description: 'Get current store configuration',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'storeConfig',
            args: [],
            description: 'Get current store configuration',
            resolver: CustomQueryResolver::class,
        ),
    ],
)]
class StoreConfig
{
    #[ApiProperty(identifier: true, description: 'Store code')]
    public string $id = 'default';

    #[ApiProperty(description: 'Store code')]
    public string $storeCode = 'default';

    #[ApiProperty(description: 'Store name')]
    public string $storeName = '';

    #[ApiProperty(description: 'Base currency code')]
    public string $baseCurrencyCode = 'AUD';

    #[ApiProperty(description: 'Default display currency code')]
    public string $defaultDisplayCurrencyCode = 'AUD';

    #[ApiProperty(description: 'Store locale (e.g., en_AU)')]
    public string $locale = 'en_AU';

    #[ApiProperty(description: 'Store timezone')]
    public string $timezone = 'Australia/Melbourne';

    #[ApiProperty(description: 'Weight unit (lbs, kgs)')]
    public string $weightUnit = 'kgs';

    #[ApiProperty(description: 'Store base URL')]
    public string $baseUrl = '';

    #[ApiProperty(description: 'Base media URL for product images')]
    public string $baseMediaUrl = '';

    /** @var string[] */
    #[ApiProperty(description: 'List of allowed country codes for addresses')]
    public array $allowedCountries = [];

    #[ApiProperty(description: 'Whether guest checkout is enabled')]
    public bool $isGuestCheckoutAllowed = true;

    #[ApiProperty(description: 'Whether newsletter subscription is enabled')]
    public bool $newsletterEnabled = true;

    #[ApiProperty(description: 'Whether wishlist is enabled')]
    public bool $wishlistEnabled = true;

    #[ApiProperty(description: 'Whether product reviews are enabled')]
    public bool $reviewsEnabled = true;

    #[ApiProperty(description: 'Full URL to the store logo image')]
    public ?string $logoUrl = null;

    #[ApiProperty(description: 'Alt text for the store logo')]
    public ?string $logoAlt = null;

    #[ApiProperty(description: 'Default page title for SEO')]
    public ?string $defaultTitle = null;

    #[ApiProperty(description: 'Default meta description for SEO')]
    public ?string $defaultDescription = null;
}
