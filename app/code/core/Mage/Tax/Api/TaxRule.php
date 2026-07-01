<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoLabel: 'Tax Rules',
    mahoSection: 'Tax',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'TaxRule',
    description: 'Tax Rule resource',
    provider: TaxRuleProvider::class,
    processor: TaxRuleProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/tax-rules/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/read')",
        ),
        new GetCollection(
            uriTemplate: '/tax-rules',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/read')",
        ),
        new Post(
            uriTemplate: '/tax-rules',
            processor: TaxRuleProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/write')",
            description: 'Creates a new tax rule',
        ),
        new Put(
            uriTemplate: '/tax-rules/{id}',
            processor: TaxRuleProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/write')",
            description: 'Updates a tax rule',
        ),
        new Delete(
            uriTemplate: '/tax-rules/{id}',
            processor: TaxRuleProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/delete')",
            description: 'Deletes a tax rule',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a tax rule by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get tax rules',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/read')",
        ),
        new Query(
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/read')",
            name: 'taxRule',
        ),
        new QueryCollection(
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rules/read')",
            name: 'taxRules',
        ),
    ],
)]
class TaxRule extends CrudResource
{
    public const MODEL = 'tax/calculation_rule';

    public const PRIMARY_KEY = 'tax_calculation_rule_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Tax_RuleController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Tax_RuleController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public string $code = '';

    public int $priority = 0;

    public int $position = 0;

    #[ApiProperty(extraProperties: ['modelField' => 'calculate_subtotal'])]
    public bool $calculateSubtotal = false;

    /**
     * Customer tax class IDs linked to this rule. Stored through the rule's
     * resource (the tax_calculation link table), not as a plain column.
     *
     * @var int[]
     */
    #[ApiProperty(extraProperties: ['modelField' => 'tax_customer_class'])]
    public array $customerTaxClassIds = [];

    /** @var int[] */
    #[ApiProperty(extraProperties: ['modelField' => 'tax_product_class'])]
    public array $productTaxClassIds = [];

    /** @var int[] */
    #[ApiProperty(extraProperties: ['modelField' => 'tax_rate'])]
    public array $taxRateIds = [];

    /**
     * Populate the association arrays after model data is mapped.
     *
     * These links live in the tax_calculation table, not on the rule row, so
     * they're read back through the rule model's resource-backed accessors
     * (which delegate to tax/calculation::getDistinct). Done here rather than
     * in the provider so create/update responses are enriched too — the
     * processor's buildResponse() reloads the model and calls fromModel(),
     * which invokes this hook.
     */
    public static function afterLoad(self $dto, object $model): void
    {
        if (!$model->getId()) {
            return;
        }

        /** @var \Mage_Tax_Model_Calculation_Rule $model */
        $dto->customerTaxClassIds = array_map('intval', $model->getCustomerTaxClasses());
        $dto->productTaxClassIds = array_map('intval', $model->getProductTaxClasses());
        $dto->taxRateIds = array_map('intval', $model->getRates());
    }
}
