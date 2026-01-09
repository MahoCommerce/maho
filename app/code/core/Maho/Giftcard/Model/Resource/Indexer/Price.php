<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Gift Card Price Indexer Resource Model
 *
 * Indexes gift card products with their minimum price calculated from
 * allowed amounts or minimum custom amount.
 */
class Maho_Giftcard_Model_Resource_Indexer_Price extends Mage_Catalog_Model_Resource_Product_Indexer_Price_Default
{
    /**
     * Reindex temporary (price result data) for all products
     */
    #[\Override]
    public function reindexAll()
    {
        $this->useIdxTable(true);
        $this->beginTransaction();
        try {
            $this->_prepareGiftcardPriceData();
            $this->_applyCustomOption();
            $this->_movePriceDataToIndexTable();
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $this;
    }

    /**
     * Reindex temporary (price result data) for defined product(s)
     *
     * @param int|array $entityIds
     */
    #[\Override]
    public function reindexEntity($entityIds)
    {
        $this->_prepareGiftcardPriceData($entityIds);
        $this->_applyCustomOption();
        $this->_movePriceDataToIndexTable();

        return $this;
    }

    /**
     * Prepare gift card price index data
     *
     * Gift cards don't use the standard price attribute. Instead, they calculate
     * their price from giftcard_amounts (comma-separated values) or
     * giftcard_min_amount/giftcard_max_amount for custom amounts.
     *
     * @param int|array|null $entityIds
     */
    protected function _prepareGiftcardPriceData($entityIds = null): self
    {
        $this->_prepareDefaultFinalPriceTable();

        $write = $this->_getWriteAdapter();
        $select = $write->select()
            ->from(['e' => $this->getTable('catalog/product')], ['entity_id'])
            ->join(
                ['cg' => $this->getTable('customer/customer_group')],
                '',
                ['customer_group_id'],
            )
            ->join(
                ['cw' => $this->getTable('core/website')],
                '',
                ['website_id'],
            )
            ->join(
                ['cwd' => $this->_getWebsiteDateTable()],
                'cw.website_id = cwd.website_id',
                [],
            )
            ->join(
                ['csg' => $this->getTable('core/store_group')],
                'csg.website_id = cw.website_id AND cw.default_group_id = csg.group_id',
                [],
            )
            ->join(
                ['cs' => $this->getTable('core/store')],
                'csg.default_store_id = cs.store_id AND cs.store_id != 0',
                [],
            )
            ->join(
                ['pw' => $this->getTable('catalog/product_website')],
                'pw.product_id = e.entity_id AND pw.website_id = cw.website_id',
                [],
            )
            ->joinLeft(
                ['tp' => $this->_getTierPriceIndexTable()],
                'tp.entity_id = e.entity_id AND tp.website_id = cw.website_id'
                    . ' AND tp.customer_group_id = cg.customer_group_id',
                [],
            )
            ->joinLeft(
                ['gp' => $this->_getGroupPriceIndexTable()],
                'gp.entity_id = e.entity_id AND gp.website_id = cw.website_id'
                    . ' AND gp.customer_group_id = cg.customer_group_id',
                [],
            )
            ->where('e.type_id = ?', $this->getTypeId());

        // Add enable products limitation
        $statusCond = $write->quoteInto('=?', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        $this->_addAttributeToSelect($select, 'status', 'e.entity_id', 'cs.store_id', $statusCond, true);

        if ($this->isModuleEnabled('Mage_Tax')) {
            $taxClassId = $this->_addAttributeToSelect($select, 'tax_class_id', 'e.entity_id', 'cs.store_id');
        } else {
            $taxClassId = new Maho\Db\Expr('0');
        }
        $select->columns(['tax_class_id' => $taxClassId]);

        // Get gift card specific attributes
        $giftcardAmounts = $this->_addAttributeToSelect($select, 'giftcard_amounts', 'e.entity_id', 'cs.store_id');
        $giftcardMinAmount = $this->_addAttributeToSelect($select, 'giftcard_min_amount', 'e.entity_id', 'cs.store_id');
        $giftcardMaxAmount = $this->_addAttributeToSelect($select, 'giftcard_max_amount', 'e.entity_id', 'cs.store_id');

        // For gift cards, we need to calculate the price dynamically
        // Since we can't do complex string parsing in SQL, we'll use a placeholder
        // and update it via PHP after the initial insert
        // For now, use 0 as placeholder - we'll update via observer event
        $price = new Maho\Db\Expr('0');
        $finalPrice = new Maho\Db\Expr('0');

        $select->columns([
            'orig_price'       => $price,
            'price'            => $finalPrice,
            'min_price'        => $finalPrice,
            'max_price'        => $finalPrice,
            'tier_price'       => new Maho\Db\Expr('tp.min_price'),
            'base_tier'        => new Maho\Db\Expr('tp.min_price'),
            'group_price'      => new Maho\Db\Expr('gp.price'),
            'base_group_price' => new Maho\Db\Expr('gp.price'),
        ]);

        if (!is_null($entityIds)) {
            $select->where('e.entity_id IN(?)', $entityIds);
        }

        Mage::dispatchEvent('prepare_catalog_product_index_select', [
            'select'        => $select,
            'entity_field'  => new Maho\Db\Expr('e.entity_id'),
            'website_field' => new Maho\Db\Expr('cw.website_id'),
            'store_field'   => new Maho\Db\Expr('cs.store_id'),
        ]);

        $query = $select->insertFromSelect($this->_getDefaultFinalPriceTable(), [], false);
        $write->query($query);

        // Now update the prices based on gift card amounts
        $this->_updateGiftcardPrices($entityIds);

        Mage::dispatchEvent('prepare_catalog_product_price_index_table', [
            'index_table'       => ['i' => $this->_getDefaultFinalPriceTable()],
            'select'            => $write->select()->join(
                ['wd' => $this->_getWebsiteDateTable()],
                'i.website_id = wd.website_id',
                [],
            ),
            'entity_id'         => 'i.entity_id',
            'customer_group_id' => 'i.customer_group_id',
            'website_id'        => 'i.website_id',
            'website_date'      => 'wd.website_date',
            'update_fields'     => ['price', 'min_price', 'max_price'],
        ]);

        return $this;
    }

    /**
     * Update gift card prices based on their allowed amounts
     *
     * @param int|array|null $entityIds
     */
    protected function _updateGiftcardPrices($entityIds = null): void
    {
        $write = $this->_getWriteAdapter();
        $table = $this->_getDefaultFinalPriceTable();

        // Get all gift card products that need price updates
        $select = $write->select()
            ->from(['i' => $table], ['entity_id'])
            ->join(
                ['e' => $this->getTable('catalog/product')],
                'e.entity_id = i.entity_id',
                [],
            )
            ->where('e.type_id = ?', $this->getTypeId())
            ->group('i.entity_id');

        if (!is_null($entityIds)) {
            $select->where('i.entity_id IN(?)', $entityIds);
        }

        $productIds = $write->fetchCol($select);

        if (empty($productIds)) {
            return;
        }

        // Load gift card attributes for these products
        $amountsAttr = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'giftcard_amounts');
        $minAmountAttr = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'giftcard_min_amount');
        $maxAmountAttr = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'giftcard_max_amount');

        // Get attribute values for all products
        $productPrices = [];

        foreach ($productIds as $productId) {
            $product = Mage::getModel('catalog/product')->load($productId);

            $minPrice = 0.0;
            $maxPrice = 0.0;

            // Check fixed amounts
            $amounts = $product->getData('giftcard_amounts');
            if ($amounts) {
                $amountsArray = array_map('trim', explode(',', $amounts));
                $amountsArray = array_filter($amountsArray, fn($a) => is_numeric($a) && $a > 0);
                if ($amountsArray !== []) {
                    $amountsArray = array_map('floatval', $amountsArray);
                    $minPrice = min($amountsArray);
                    $maxPrice = max($amountsArray);
                }
            }

            // Check custom amount range
            $giftcardType = $product->getData('giftcard_type');
            if ($giftcardType === 'custom' || $giftcardType === 'combined') {
                $customMin = (float) $product->getData('giftcard_min_amount');
                $customMax = (float) $product->getData('giftcard_max_amount');

                if ($customMin > 0) {
                    $minPrice = ($minPrice > 0) ? min($minPrice, $customMin) : $customMin;
                }
                if ($customMax > 0) {
                    $maxPrice = max($maxPrice, $customMax);
                }
            }

            // If we still have no price, fallback to 0
            if ($minPrice <= 0) {
                $minPrice = 0;
            }
            if ($maxPrice <= 0) {
                $maxPrice = $minPrice;
            }

            $productPrices[$productId] = [
                'min' => $minPrice,
                'max' => $maxPrice,
            ];
        }

        // Update prices in the index table
        foreach ($productPrices as $productId => $prices) {
            $write->update(
                $table,
                [
                    'orig_price' => $prices['min'],
                    'price'      => $prices['min'],
                    'min_price'  => $prices['min'],
                    'max_price'  => $prices['max'],
                ],
                [
                    'entity_id = ?' => $productId,
                ],
            );
        }
    }
}
