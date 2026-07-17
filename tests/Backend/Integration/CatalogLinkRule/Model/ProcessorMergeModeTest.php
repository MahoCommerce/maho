<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Rule stub returning fixed source/target product IDs, bypassing the condition engine
 * so the processor's merge logic can be exercised deterministically.
 */
class Maho_CatalogLinkRule_Test_StubRule extends Maho_CatalogLinkRule_Model_Rule
{
    public array $sourceIds = [];
    public array $targetIds = [];

    #[\Override]
    public function getMatchingSourceProductIds(): array
    {
        return $this->sourceIds;
    }

    #[\Override]
    public function getMatchingTargetProductIds(?Mage_Catalog_Model_Product $sourceProduct = null): array
    {
        return $this->targetIds;
    }
}

/**
 * Exposes the protected per-link-type entry point.
 */
class Maho_CatalogLinkRule_Test_Processor extends Maho_CatalogLinkRule_Model_Processor
{
    public function runLinkType(int $linkTypeId, array $rules): void
    {
        $this->processLinkType($linkTypeId, $rules);
    }
}

function clrPositionAttrId(int $linkTypeId): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_link_attribute'), 'product_link_attribute_id')
            ->where('link_type_id = ?', $linkTypeId)
            ->where('product_link_attribute_code = ?', 'position'),
    );
}

function clrCreateProduct(string $sku): int
{
    $product = Mage::getModel('catalog/product');
    $product->setName($sku);
    $product->setSku($sku);
    $product->setPrice(10.00);
    $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setAttributeSetId(4);
    $product->setWebsiteIds([1]);
    $product->save();
    return (int) $product->getId();
}

function clrInsertLink(int $sourceId, int $targetId, int $linkTypeId, int $position, ?int $ruleId = null): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->insert($resource->getTableName('catalog/product_link'), [
        'product_id' => $sourceId,
        'linked_product_id' => $targetId,
        'link_type_id' => $linkTypeId,
        'rule_id' => $ruleId,
    ]);
    $linkId = $adapter->lastInsertId();
    $adapter->insert($resource->getTableName('catalog/product_link_attribute_int'), [
        'product_link_attribute_id' => clrPositionAttrId($linkTypeId),
        'link_id' => $linkId,
        'value' => $position,
    ]);
}

/**
 * @return array<int,int> linked_product_id => position, ordered by position
 */
function clrReadLinks(int $sourceId, int $linkTypeId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $select = $adapter->select()
        ->from(['l' => $resource->getTableName('catalog/product_link')], ['linked_product_id'])
        ->joinLeft(
            ['p' => $resource->getTableName('catalog/product_link_attribute_int')],
            'p.link_id = l.link_id AND p.product_link_attribute_id = ' . clrPositionAttrId($linkTypeId),
            ['position' => 'value'],
        )
        ->where('l.product_id = ?', $sourceId)
        ->where('l.link_type_id = ?', $linkTypeId)
        ->order('position ASC');

    $links = [];
    foreach ($adapter->fetchAll($select) as $row) {
        $links[(int) $row['linked_product_id']] = (int) $row['position'];
    }
    return $links;
}

function clrSetMergeMode(string $mode): void
{
    Mage::app()->getStore()->setConfig(Maho_CatalogLinkRule_Model_Processor::CONFIG_MERGE_MODE, $mode);
}

describe('CatalogLinkRule processor merge modes', function () {
    $linkType = Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED;

    test('replace mode (default) deletes existing links and inserts rule results', function () use ($linkType) {
        $source = clrCreateProduct('clr-src-' . uniqid());
        $manual = clrCreateProduct('clr-man-' . uniqid());
        $ruleTarget = clrCreateProduct('clr-tgt-' . uniqid());

        clrInsertLink($source, $manual, $linkType, 1);

        $rule = new Maho_CatalogLinkRule_Test_StubRule();
        $rule->sourceIds = [$source];
        $rule->targetIds = [$ruleTarget];

        clrSetMergeMode(Maho_CatalogLinkRule_Model_Processor::MODE_REPLACE);
        (new Maho_CatalogLinkRule_Test_Processor())->runLinkType($linkType, [$rule]);

        $links = clrReadLinks($source, $linkType);
        expect($links)->toHaveKey($ruleTarget);
        expect($links)->not->toHaveKey($manual);
    });

    test('merge mode keeps manual links first and appends rule results without duplicates', function () use ($linkType) {
        $source = clrCreateProduct('clr-src-' . uniqid());
        $manual = clrCreateProduct('clr-man-' . uniqid());
        $ruleTarget = clrCreateProduct('clr-tgt-' . uniqid());

        // Manual link sits at position 5; the rule also re-proposes the manual target.
        clrInsertLink($source, $manual, $linkType, 5);

        $rule = new Maho_CatalogLinkRule_Test_StubRule();
        $rule->setId(900001);
        $rule->sourceIds = [$source];
        $rule->targetIds = [$manual, $ruleTarget];

        clrSetMergeMode(Maho_CatalogLinkRule_Model_Processor::MODE_MERGE);
        (new Maho_CatalogLinkRule_Test_Processor())->runLinkType($linkType, [$rule]);

        $links = clrReadLinks($source, $linkType);

        // Both targets present, manual not duplicated.
        expect(array_keys($links))->toContain($manual, $ruleTarget);
        expect(count($links))->toBe(2);

        // Manual position preserved, rule result appended after it.
        expect($links[$manual])->toBe(5);
        expect($links[$ruleTarget])->toBeGreaterThan(5);
    });

    test('merge mode honours max_links across the combined set', function () use ($linkType) {
        $source = clrCreateProduct('clr-src-' . uniqid());
        $manual = clrCreateProduct('clr-man-' . uniqid());
        $ruleTarget = clrCreateProduct('clr-tgt-' . uniqid());

        clrInsertLink($source, $manual, $linkType, 1);

        $rule = new Maho_CatalogLinkRule_Test_StubRule();
        $rule->setId(900002);
        $rule->sourceIds = [$source];
        $rule->targetIds = [$ruleTarget];
        $rule->setData('max_links', 1); // already satisfied by the manual link

        clrSetMergeMode(Maho_CatalogLinkRule_Model_Processor::MODE_MERGE);
        (new Maho_CatalogLinkRule_Test_Processor())->runLinkType($linkType, [$rule]);

        $links = clrReadLinks($source, $linkType);
        expect(count($links))->toBe(1);
        expect($links)->toHaveKey($manual);
        expect($links)->not->toHaveKey($ruleTarget);
    });

    test('merge mode drops its own stale links on the next run but keeps manual links', function () use ($linkType) {
        $source = clrCreateProduct('clr-src-' . uniqid());
        $manual = clrCreateProduct('clr-man-' . uniqid());
        $first = clrCreateProduct('clr-t1-' . uniqid());
        $second = clrCreateProduct('clr-t2-' . uniqid());

        clrInsertLink($source, $manual, $linkType, 1); // manual link, must always survive

        clrSetMergeMode(Maho_CatalogLinkRule_Model_Processor::MODE_MERGE);

        // First run: rule links the source to $first
        $rule = new Maho_CatalogLinkRule_Test_StubRule();
        $rule->setId(900100);
        $rule->sourceIds = [$source];
        $rule->targetIds = [$first];
        (new Maho_CatalogLinkRule_Test_Processor())->runLinkType($linkType, [$rule]);

        $links = clrReadLinks($source, $linkType);
        expect(array_keys($links))->toContain($manual, $first);

        // Second run: the rule now targets $second; the stale $first link must be removed,
        // the manual link preserved.
        $rule2 = new Maho_CatalogLinkRule_Test_StubRule();
        $rule2->setId(900100);
        $rule2->sourceIds = [$source];
        $rule2->targetIds = [$second];
        (new Maho_CatalogLinkRule_Test_Processor())->runLinkType($linkType, [$rule2]);

        $links = clrReadLinks($source, $linkType);
        expect($links)->toHaveKey($manual);
        expect($links)->toHaveKey($second);
        expect($links)->not->toHaveKey($first);
    });

    test('deleting a rule removes its generated links but keeps manual ones', function () use ($linkType) {
        $source = clrCreateProduct('clr-src-' . uniqid());
        $manual = clrCreateProduct('clr-man-' . uniqid());
        $ruleTarget = clrCreateProduct('clr-tgt-' . uniqid());

        // Persist a real rule so it has an ID and can be deleted through the model.
        $rule = Mage::getModel('cataloglinkrule/rule');
        $rule->setName('clr-del-' . uniqid());
        $rule->setLinkTypeId($linkType);
        $rule->setIsActive(1);
        $rule->setPriority(0);
        $rule->setSortOrder('random');
        $rule->save();
        $ruleId = (int) $rule->getId();

        clrInsertLink($source, $manual, $linkType, 1);                  // manual (rule_id NULL)
        clrInsertLink($source, $ruleTarget, $linkType, 2, $ruleId);     // rule-generated

        expect(clrReadLinks($source, $linkType))->toHaveKeys([$manual, $ruleTarget]);

        $rule->delete();

        $links = clrReadLinks($source, $linkType);
        expect($links)->toHaveKey($manual);
        expect($links)->not->toHaveKey($ruleTarget);
    });

    test('deactivating a rule removes its generated links but keeps manual ones', function () use ($linkType) {
        $source = clrCreateProduct('clr-src-' . uniqid());
        $manual = clrCreateProduct('clr-man-' . uniqid());
        $ruleTarget = clrCreateProduct('clr-tgt-' . uniqid());

        $rule = Mage::getModel('cataloglinkrule/rule');
        $rule->setName('clr-deact-' . uniqid());
        $rule->setLinkTypeId($linkType);
        $rule->setIsActive(1);
        $rule->setPriority(0);
        $rule->setSortOrder('random');
        $rule->save();
        $ruleId = (int) $rule->getId();

        clrInsertLink($source, $manual, $linkType, 1);                  // manual (rule_id NULL)
        clrInsertLink($source, $ruleTarget, $linkType, 2, $ruleId);     // rule-generated

        expect(clrReadLinks($source, $linkType))->toHaveKeys([$manual, $ruleTarget]);

        $rule->setIsActive(0)->save();

        $links = clrReadLinks($source, $linkType);
        expect($links)->toHaveKey($manual);
        expect($links)->not->toHaveKey($ruleTarget);
    });

    test('merge mode removes rule links for products no longer matched, keeping manual links', function () use ($linkType) {
        $source = clrCreateProduct('clr-src-' . uniqid());
        $manual = clrCreateProduct('clr-man-' . uniqid());
        $target = clrCreateProduct('clr-tgt-' . uniqid());

        clrInsertLink($source, $manual, $linkType, 1); // manual link, must always survive

        clrSetMergeMode(Maho_CatalogLinkRule_Model_Processor::MODE_MERGE);

        // First run: the rule matches the source and links it to $target.
        $rule = new Maho_CatalogLinkRule_Test_StubRule();
        $rule->setId(900200);
        $rule->sourceIds = [$source];
        $rule->targetIds = [$target];
        (new Maho_CatalogLinkRule_Test_Processor())->runLinkType($linkType, [$rule]);
        expect(clrReadLinks($source, $linkType))->toHaveKeys([$manual, $target]);

        // Second run: the rule no longer matches the source at all; its generated link must be
        // purged as an orphan while the manual link survives.
        $rule2 = new Maho_CatalogLinkRule_Test_StubRule();
        $rule2->setId(900200);
        $rule2->sourceIds = [];
        $rule2->targetIds = [$target];
        (new Maho_CatalogLinkRule_Test_Processor())->runLinkType($linkType, [$rule2]);

        $links = clrReadLinks($source, $linkType);
        expect($links)->toHaveKey($manual);
        expect($links)->not->toHaveKey($target);
    });
});
