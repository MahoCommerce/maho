<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Config;

use ApiPlatform\Metadata\ApiResource as BaseApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\State\OptionsInterface;
use Attribute;

/**
 * Maho-flavoured API Platform resource attribute.
 *
 * Drop-in subclass of `ApiPlatform\Metadata\ApiResource` that adds Maho's
 * permission-registry metadata directly to the same attribute. API Platform's
 * own scanner uses `is_a(..., true)` / `IS_INSTANCEOF` (see
 * `AttributesResourceNameCollectionFactory` and `MetadataCollectionFactoryTrait`),
 * so subclass instances are picked up exactly like the parent, HTTP routing
 * and GraphQL surface work unchanged.
 *
 * Compiled at `composer dump-autoload` into `vendor/composer/maho_api_permissions.php`,
 * consumed by `Maho\ApiPlatform\Security\ApiPermissionRegistry`. Run
 * `composer dump-autoload` after adding/modifying this attribute.
 *
 * Most fields default to `null` and are auto-derived by the compiler:
 *   - `mahoId`            ← shortName, pluralized + kebab-cased ('Cart' → 'carts')
 *   - `mahoLabel`         ← title-cased mahoId
 *   - `mahoSection`       ← module segment of the namespace ('Mage\Catalog\Api\…' → 'Catalog')
 *   - `mahoOperations`    ← per-verb default labels for verbs present in `operations: [...]`
 *   - `mahoRestSegments`  ← `[$mahoId]` (augmented by your override)
 *   - `mahoGraphQlFields` ← camelCase `name`s from `graphQlOperations` (augmented by your override)
 *   - `mahoPublicRead`    ← `true` when every read operation has `security: 'true'`
 *
 * Set them explicitly only when defaults are wrong. `mahoCustomerScoped` has
 * no API Platform equivalent and must be set explicitly when needed.
 *
 * See the per-field `@param` lines on the constructor below for the full
 * semantics of each maho field.
 *
 * For forward-looking resources without a real DTO, declare on a stub class
 * with `operations: []` (explicit empty, *not* null) so API Platform sees
 * the resource but registers zero endpoints; only the maho fields are picked up.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApiResource extends BaseApiResource
{
    /**
     * Mirrors `ApiPlatform\Metadata\ApiResource::__construct` parameter-by-parameter
     * and forwards them to `parent::__construct` so API Platform sees the same
     * configuration. Maho-specific fields come *after* every parent parameter so
     * positional usage (rare) still maps cleanly to the parent contract.
     *
     * If API Platform adds a new constructor parameter we haven't mirrored here,
     * `tests/Backend/Integration/ApiPlatform/ApiResourceConstructorParityTest`
     * fails, keeping the mirror honest.
     *
     * The `@param` overrides for `$operations` and `$rules` exist to detach our
     * signature from the parent docblock, the parent annotates `$operations`
     * with a generic Operations type (which would require a `@template` tag we
     * don't carry) and `$rules` with `Illuminate\Contracts\Validation\Rule`
     * (Laravel, not a Maho dependency). Both are pure pass-through values; we
     * accept them as `mixed` and forward verbatim.
     *
     * @param ?string $mahoId
     *   Canonical permission identifier, the slug used in role grants
     *   (`carts/read`), in REST URL routing, and as the GraphQL field key.
     *   Defaults to the kebab-cased + pluralised `shortName` (`Cart` → `carts`,
     *   `CmsPage` → `cms-pages`). Set explicitly only for irregular plurals
     *   or when the auto-derivation collides with another resource.
     * @param ?string $mahoLabel
     *   Human-readable label shown in the admin role editor and OpenAPI docs.
     *   Defaults to title-cased `mahoId` (`cms-pages` → `Cms Pages`). Override
     *   for acronyms or branded casing (`CMS Pages`, `URL Resolver`).
     *
     * @param ?string $mahoSection
     *   Section heading the resource is grouped under in the admin role-editor
     *   tree (one level deep, `Catalog`, `Sales`, `Customers`, `Content`,
     *   `System`, `Other`). Defaults to the module segment of the namespace
     *   (`Mage\Catalog\Api\Foo` → `'Catalog'`). Override when the namespace
     *   doesn't match the desired UI grouping (e.g. permission stubs).
     *
     * @param array<string, string>|null $mahoOperations
     *   Per-verb permission labels rendered in the admin role-editor tree
     *   (`['read' => 'View', 'write' => 'Manage']`). Verbs are
     *   `read | create | write | delete`. Defaults are derived per-verb from
     *   the operations actually present in `operations: [...]`:
     *   `read → View`, `create → Create`, `write → Update`, `delete → Delete`.
     *   Override the whole map to customise display strings (`'write' => 'Submit'`).
     *
     * @param ?bool $mahoPublicRead
     *   Marks the resource as readable without authentication. Auto-derived to
     *   `true` when every read operation (Get/GetCollection or any HttpOperation
     *   with method GET/HEAD) carries `security: 'true'`. Set explicitly only
     *   when the read security expression doesn't use that literal form
     *   (e.g. a custom voter expression that's effectively public).
     *
     * @param bool $mahoCustomerScoped
     *   Marks the resource as bound to a logged-in customer (Cart, Order,
     *   Address, Wishlist, Review, NewsletterSubscription). Access is gated by
     *   the customer JWT, not admin role grants, the admin role editor surfaces
     *   these in a separate "Customer endpoints" informational panel rather than
     *   the grant tree. The parent's `description:` doubles as the prose shown
     *   for each entry, so write it action-oriented ("View cart, add/remove
     *   items, …"). No equivalent in API Platform, must be set explicitly.
     *
     * @param string[]|null $mahoRestSegments
     *   Additional top-level URL path segments that should resolve to this
     *   resource for permission checks. The default is `[$mahoId]` itself,
     *   `mahoRestSegments` is **augmenting**, so declare only the *extra*
     *   segments (e.g. Cart adds `'guest-carts'` because both `/carts/*` and
     *   `/guest-carts/*` map to the cart resource).
     *
     * @param string[]|null $mahoGraphQlFields
     *   Additional GraphQL field names that should resolve to this resource for
     *   permission checks. Auto-derived from `graphQlOperations[].name`,
     *   filtering out internal snake_case identifiers (`item_query`,
     *   `add_cart_item`). Augmenting, declare only fields the compiler can't
     *   see, e.g. handler-defined fields in `*MutationHandler` / `*QueryHandler`
     *   classes outside the DTO.
     *
     * @param mixed $operations
     *
     * @phpstan-param mixed $rules
     */
    public function __construct(
        // ---- Maho permission-registry fields (named-arg first; positional usage of the
        //      parent ApiResource ctor is impractical given its 70+ params and never
        //      seen in real code, so leading with the maho fields here just makes them
        //      surface first in IDE autocomplete and at the top of usage blocks).
        public ?string $mahoId = null,
        public ?string $mahoLabel = null,
        public ?string $mahoSection = null,
        public ?array $mahoOperations = null,
        public ?bool $mahoPublicRead = null,
        public bool $mahoCustomerScoped = false,
        public ?array $mahoRestSegments = null,
        public ?array $mahoGraphQlFields = null,
        // ---- Mirror of ApiPlatform\Metadata\ApiResource constructor ----
        ?string $uriTemplate = null,
        ?string $shortName = null,
        ?string $description = null,
        string|array|null $types = null,
        $operations = null,
        array|string|null $formats = null,
        array|string|null $inputFormats = null,
        array|string|null $outputFormats = null,
        $uriVariables = null,
        ?string $routePrefix = null,
        ?array $defaults = null,
        ?array $requirements = null,
        ?array $options = null,
        ?bool $stateless = null,
        ?string $sunset = null,
        ?string $acceptPatch = null,
        ?int $status = null,
        ?string $host = null,
        ?array $schemes = null,
        ?string $condition = null,
        ?string $controller = null,
        ?string $class = null,
        ?int $urlGenerationStrategy = null,
        ?string $deprecationReason = null,
        ?array $headers = null,
        ?array $cacheHeaders = null,
        ?array $normalizationContext = null,
        ?array $denormalizationContext = null,
        ?bool $collectDenormalizationErrors = null,
        ?array $hydraContext = null,
        bool|OpenApiOperation|null $openapi = null,
        ?array $validationContext = null,
        ?array $filters = null,
        $mercure = null,
        $messenger = null,
        $input = null,
        $output = null,
        ?array $order = null,
        ?bool $fetchPartial = null,
        ?bool $forceEager = null,
        ?bool $paginationClientEnabled = null,
        ?bool $paginationClientItemsPerPage = null,
        ?bool $paginationClientPartial = null,
        ?array $paginationViaCursor = null,
        ?bool $paginationEnabled = null,
        ?bool $paginationFetchJoinCollection = null,
        ?bool $paginationUseOutputWalkers = null,
        ?int $paginationItemsPerPage = null,
        ?int $paginationMaximumItemsPerPage = null,
        ?bool $paginationPartial = null,
        ?string $paginationType = null,
        string|\Stringable|null $security = null,
        ?string $securityMessage = null,
        string|\Stringable|null $securityPostDenormalize = null,
        ?string $securityPostDenormalizeMessage = null,
        string|\Stringable|null $securityPostValidation = null,
        ?string $securityPostValidationMessage = null,
        ?bool $compositeIdentifier = null,
        ?array $exceptionToStatus = null,
        ?bool $queryParameterValidationEnabled = null,
        ?array $links = null,
        ?array $graphQlOperations = null,
        $provider = null,
        $processor = null,
        ?OptionsInterface $stateOptions = null,
        mixed $rules = null,
        ?string $policy = null,
        array|string|null $middleware = null,
        array|Parameters|null $parameters = null,
        ?bool $strictQueryParameterValidation = null,
        ?bool $hideHydraOperation = null,
        ?bool $jsonStream = null,
        array $extraProperties = [],
        ?bool $map = null,
        ?array $mcp = null,
    ) {
        // Forward every locally-defined parameter except the maho-specific ones
        // to the parent constructor. get_defined_vars() keeps the parent forward
        // automatically in sync with the parameter list above, adding/removing a
        // parent arg only requires editing the signature, not the forward call.
        // The maho fields are unset explicitly so the spread maps cleanly to the
        // parent's named parameters.
        $parentArgs = get_defined_vars();
        unset(
            $parentArgs['mahoId'],
            $parentArgs['mahoLabel'],
            $parentArgs['mahoSection'],
            $parentArgs['mahoOperations'],
            $parentArgs['mahoPublicRead'],
            $parentArgs['mahoCustomerScoped'],
            $parentArgs['mahoRestSegments'],
            $parentArgs['mahoGraphQlFields'],
        );
        parent::__construct(...$parentArgs);
    }
}
