<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

/**
 * Tripwire for the layering of #1348: a transport translates, a service decides. A transport is a
 * controller, an API class or a CLI command. It reads the request, calls one service and renders the
 * result. A service never reads the request or the session, a status code belongs to the API edge, and
 * a rate limit or a honeypot belongs to the service, so that no transport can forget it.
 *
 * Each rule has an allowlist of the files that broke it when the rule came in, with the number of
 * matches in each file. The allowlist only shrinks: a pull request that fixes a file lowers or removes
 * its entry, and the test fails until it does. Comments and docblocks are not scanned.
 */

/** Files that create a Symfony HttpException outside Maho_ApiPlatform. */
const LAYERING_HTTP_EXCEPTION_ALLOWLIST = [
    'app/code/core/Mage/Adminhtml/Api/GlobalSearchProvider.php' => 1,
    'app/code/core/Mage/Catalog/Api/AttributeSetProcessor.php' => 5,
    'app/code/core/Mage/Catalog/Api/BundleOptionProcessor.php' => 9,
    'app/code/core/Mage/Catalog/Api/CategoryImageProcessor.php' => 2,
    'app/code/core/Mage/Catalog/Api/CategoryProcessor.php' => 18,
    'app/code/core/Mage/Catalog/Api/CategoryProvider.php' => 1,
    'app/code/core/Mage/Catalog/Api/ConfigurableSetupProcessor.php' => 3,
    'app/code/core/Mage/Catalog/Api/DownloadableLinkProcessor.php' => 10,
    'app/code/core/Mage/Catalog/Api/GroupedProductLinkProcessor.php' => 3,
    'app/code/core/Mage/Catalog/Api/ProductAttributeProcessor.php' => 5,
    'app/code/core/Mage/Catalog/Api/ProductCustomOptionProcessor.php' => 10,
    'app/code/core/Mage/Catalog/Api/ProductCustomOptionProvider.php' => 4,
    'app/code/core/Mage/Catalog/Api/ProductGroupPriceProcessor.php' => 3,
    'app/code/core/Mage/Catalog/Api/ProductLinkProcessor.php' => 3,
    'app/code/core/Mage/Catalog/Api/ProductLinkProvider.php' => 1,
    'app/code/core/Mage/Catalog/Api/ProductMediaProcessor.php' => 11,
    'app/code/core/Mage/Catalog/Api/ProductProcessor.php' => 15,
    'app/code/core/Mage/Catalog/Api/ProductProvider.php' => 1,
    'app/code/core/Mage/Catalog/Api/ProductTierPriceProcessor.php' => 4,
    'app/code/core/Mage/CatalogInventory/Api/StockUpdateProcessor.php' => 8,
    'app/code/core/Mage/CatalogRule/Api/CatalogPriceRuleProcessor.php' => 1,
    'app/code/core/Mage/CatalogRule/Api/CatalogPriceRuleProvider.php' => 2,
    'app/code/core/Mage/Checkout/Api/CartProcessor.php' => 14,
    'app/code/core/Mage/Cms/Api/CmsPageProcessor.php' => 5,
    'app/code/core/Mage/Cms/Api/MediaProcessor.php' => 4,
    'app/code/core/Mage/Core/Api/ConfigSettingProcessor.php' => 2,
    'app/code/core/Mage/Core/Api/ConfigSettingProvider.php' => 3,
    'app/code/core/Mage/Core/Api/EmailTemplateProcessor.php' => 1,
    'app/code/core/Mage/Core/Api/EmailTemplateProvider.php' => 1,
    'app/code/core/Mage/Core/Api/StoreConfigProvider.php' => 2,
    'app/code/core/Mage/Core/Api/StoreProcessor.php' => 2,
    'app/code/core/Mage/Core/Api/StoreProvider.php' => 1,
    'app/code/core/Mage/Core/Api/UrlRewriteProcessor.php' => 3,
    'app/code/core/Mage/Customer/Api/AddressProcessor.php' => 18,
    'app/code/core/Mage/Customer/Api/AddressProvider.php' => 4,
    'app/code/core/Mage/Customer/Api/CustomerGroupProcessor.php' => 2,
    'app/code/core/Mage/Customer/Api/CustomerProcessor.php' => 33,
    'app/code/core/Mage/Index/Api/IndexProcessProcessor.php' => 2,
    'app/code/core/Mage/Index/Api/IndexProcessProvider.php' => 1,
    'app/code/core/Mage/Newsletter/Api/NewsletterProcessor.php' => 8,
    'app/code/core/Mage/Reports/Api/ReportQuery.php' => 2,
    'app/code/core/Mage/Reports/Api/ReportStatisticsProcessor.php' => 3,
    'app/code/core/Mage/Review/Api/ReviewProcessor.php' => 13,
    'app/code/core/Mage/Review/Api/ReviewProvider.php' => 4,
    'app/code/core/Mage/Sales/Api/CreditMemoProcessor.php' => 4,
    'app/code/core/Mage/Sales/Api/CreditMemoProvider.php' => 2,
    'app/code/core/Mage/Sales/Api/InvoiceProcessor.php' => 3,
    'app/code/core/Mage/Sales/Api/InvoiceProvider.php' => 6,
    'app/code/core/Mage/Sales/Api/OrderProcessor.php' => 7,
    'app/code/core/Mage/Sales/Api/ShipmentProcessor.php' => 9,
    'app/code/core/Mage/Sales/Api/ShipmentProvider.php' => 2,
    'app/code/core/Mage/SalesRule/Api/CartPriceRuleCouponProcessor.php' => 3,
    'app/code/core/Mage/SalesRule/Api/CartPriceRuleProcessor.php' => 2,
    'app/code/core/Mage/SalesRule/Api/CartPriceRuleProvider.php' => 2,
    'app/code/core/Mage/SalesRule/Api/CouponProcessor.php' => 17,
    'app/code/core/Mage/SalesRule/Api/CouponProvider.php' => 2,
    'app/code/core/Mage/Tax/Api/TaxClassProcessor.php' => 2,
    'app/code/core/Mage/Tax/Api/TaxRateProcessor.php' => 5,
    'app/code/core/Mage/Tax/Api/TaxRuleProcessor.php' => 4,
    'app/code/core/Mage/Wishlist/Api/WishlistProcessor.php' => 5,
    'app/code/core/Mage/Wishlist/Api/WishlistProvider.php' => 2,
    'app/code/core/Maho/Ai/Api/Chat/ChatController.php' => 17,
    'app/code/core/Maho/Blog/Api/BlogCategoryProcessor.php' => 4,
    'app/code/core/Maho/Blog/Api/BlogPost.php' => 2,
    'app/code/core/Maho/Blog/Api/BlogPostImageProcessor.php' => 2,
    'app/code/core/Maho/Giftcard/Api/GiftCardProcessor.php' => 12,
    'app/code/core/Maho/Revocation/Api/RevocationRequestProcessor.php' => 7,
    'app/code/core/Maho/Revocation/Api/RevocationRequestProvider.php' => 1,
    'app/code/core/Maho/SocialLogin/Api/SocialAuthProcessor.php' => 4,
];

/** Files in a Service folder that read the request, the superglobals or the session. */
const LAYERING_SERVICE_INPUT_ALLOWLIST = [
    'app/code/core/Mage/Sales/Model/Service/Quote.php' => 1,
];

/** Transport files that call a rate limiter or a honeypot check themselves. */
const LAYERING_TRANSPORT_GATE_ALLOWLIST = [
    'app/code/core/Mage/Adminhtml/controllers/IndexController.php' => 2,
    'app/code/core/Mage/Checkout/Api/CartProcessor.php' => 2,
    'app/code/core/Mage/Customer/Api/CustomerProcessor.php' => 9,
    'app/code/core/Mage/Customer/controllers/AccountController.php' => 4,
    'app/code/core/Mage/Downloadable/controllers/DownloadController.php' => 2,
    'app/code/core/Mage/Newsletter/Api/NewsletterProcessor.php' => 3,
    'app/code/core/Mage/Newsletter/controllers/SubscriberController.php' => 2,
    'app/code/core/Mage/Reports/Api/ReportStatisticsProcessor.php' => 1,
    'app/code/core/Mage/Review/Api/ReviewProcessor.php' => 2,
    'app/code/core/Mage/Sales/Api/OrderProvider.php' => 2,
    'app/code/core/Mage/SalesRule/Api/CouponProcessor.php' => 2,
    'app/code/core/Mage/Shipping/controllers/TrackingController.php' => 2,
    'app/code/core/Maho/ApiPlatform/Api/AuthTokenProcessor.php' => 5,
    'app/code/core/Maho/ApiPlatform/symfony/Controller/OAuthController.php' => 1,
    'app/code/core/Maho/ApiPlatform/symfony/Trait/RateLimitTrait.php' => 2,
    'app/code/core/Maho/Giftcard/Api/GiftCardProvider.php' => 1,
    'app/code/core/Maho/Giftcard/controllers/BalanceController.php' => 2,
    'app/code/core/Maho/Giftcard/controllers/CartController.php' => 1,
    'app/code/core/Maho/Revocation/controllers/IndexController.php' => 1,
    'app/code/core/Maho/SocialLogin/Api/SocialAuthProcessor.php' => 1,
    'app/code/core/Maho/SocialLogin/controllers/SocialController.php' => 1,
];

/**
 * @return array<string, string> the code of each PHP file under app/code/core and lib/MahoCLI, by
 *                               its path from the Maho root
 */
function layeringSources(): array
{
    static $sources = null;
    if ($sources !== null) {
        return $sources;
    }

    $root = dirname(__DIR__, 4) . '/';
    $sources = [];
    foreach (['app/code/core', 'lib/MahoCLI'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (str_ends_with($file->getPathname(), '.php')) {
                $sources[substr($file->getPathname(), strlen($root))] = (string) file_get_contents($file->getPathname());
            }
        }
    }
    ksort($sources);
    return $sources;
}

/**
 * Remove the comments and the docblocks from $code, so that a comment that names a forbidden call
 * does not count.
 */
function layeringStripComments(string $code): string
{
    $stripped = '';
    foreach (token_get_all($code) as $token) {
        if (is_string($token)) {
            $stripped .= $token;
        } elseif ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
            $stripped .= $token[1];
        }
    }
    return $stripped;
}

/**
 * Count the matches of $codePattern in each file whose path matches $pathPattern. $accept gets the
 * path and the match, and returns false for a match that the rule allows.
 *
 * @param (callable(string, array<int|string, string>): bool)|null $accept
 * @return array<string, int> the count of each file that has a match, by path
 */
function layeringMatches(string $pathPattern, string $codePattern, ?callable $accept = null): array
{
    $found = [];
    foreach (layeringSources() as $path => $code) {
        if (!preg_match($pathPattern, $path) || !preg_match($codePattern, $code)) {
            continue;
        }
        preg_match_all($codePattern, layeringStripComments($code), $matches, PREG_SET_ORDER);
        $count = count(array_filter($matches, fn(array $match): bool => $accept === null || $accept($path, $match)));
        if ($count > 0) {
            $found[$path] = $count;
        }
    }
    return $found;
}

/**
 * Compare the matches of a rule with its allowlist. $fix tells the developer how to fix a new match.
 *
 * @param array<string, int> $found
 * @param array<string, int> $allowed
 * @return list<string> one message for each difference
 */
function layeringRatchet(array $found, array $allowed, string $fix): array
{
    $messages = [];
    $sorted = $allowed;
    ksort($sorted);
    if (array_keys($sorted) !== array_keys($allowed)) {
        $messages[] = 'Sort the allowlist by path.';
    }

    foreach ($found as $path => $count) {
        $limit = $allowed[$path] ?? 0;
        if ($count > $limit) {
            $messages[] = "$path: $count matches, the allowlist permits $limit. $fix";
        }
    }
    foreach ($allowed as $path => $limit) {
        $count = $found[$path] ?? 0;
        if ($count === 0) {
            $messages[] = "$path: no matches. Remove its entry from the allowlist.";
        } elseif ($count < $limit) {
            $messages[] = "$path: $count matches, the allowlist permits $limit. Lower its entry to $count.";
        }
    }
    return $messages;
}

it('creates a Symfony HttpException only at the API edge', function () {
    // A transport detects a malformed request, so an Api/ class (a GraphQL handler too) may send a 400.
    $found = layeringMatches(
        '#^(?!app/code/core/Maho/ApiPlatform/)#',
        '#\bnew\s+\\\\?(?:[A-Za-z_]\w*\\\\)*(\w*HttpException)\s*\(#',
        fn(string $path, array $match): bool => !($match[1] === 'BadRequestHttpException'
            && preg_match('#^app/code/core/(Mage|Maho)/[A-Za-z]+/Api/#', $path)),
    );
    $messages = layeringRatchet(
        $found,
        LAYERING_HTTP_EXCEPTION_ALLOWLIST,
        'Throw a Mage_Core_Exception subclass: ApiExceptionListener maps it to the status code.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('keeps the request and the session out of every service', function () {
    $found = layeringMatches(
        '#^app/code/core/(?!Maho/ApiPlatform/).*/Service/#',
        '#\bgetRequest\s*\(|\bgetParams?\s*\(|\$_(?:FILES|POST|GET|REQUEST)\b'
            . '|Mage::get(?:Singleton|Model)\(\s*[\'"][a-z0-9_]+/session(?:_[a-z0-9_]+)?[\'"]'
            . '|\b(?:Mage|Maho)_[A-Za-z]+_Model_Session\w*#',
    );
    $messages = layeringRatchet(
        $found,
        LAYERING_SERVICE_INPUT_ALLOWLIST,
        'Read the input in the transport and write it into the model that the service takes.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('leaves rate limits and honeypot checks to the services', function () {
    // checkRateLimit() and checkRateLimitByIp() of RateLimitTrait call the rate limiter for the API classes.
    $found = layeringMatches(
        '#/controllers/|^app/code/core/(Mage|Maho)/[A-Za-z]+/Api/|^app/code/core/Maho/ApiPlatform/|^lib/MahoCLI/Commands/#',
        '#(?:->|::)\s*(?:rateLimiter|rateLimiterBy|ipRateLimiter|tooManyAttempts|isHoneypotTriggered'
            . '|checkRateLimit|checkRateLimitByIp)\s*\(#',
    );
    $messages = layeringRatchet(
        $found,
        LAYERING_TRANSPORT_GATE_ALLOWLIST,
        'Move the rate limit or the honeypot check into the service of the flow.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});
