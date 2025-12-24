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

class Maho_Giftcard_Adminhtml_Giftcard_ProductController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'giftcard/manage';

    /**
     * Create gift card product(s) action
     */
    public function createAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('sales/giftcard');
        $this->_title(Mage::helper('giftcard')->__('Create Gift Card Products'));
        $this->renderLayout();
    }

    /**
     * Save gift card product(s)
     */
    public function saveAction(): void
    {
        try {
            $data = $this->getRequest()->getPost();

            if (!$data) {
                throw new Exception('No data provided');
            }

            $amounts = $data['amounts'] ?? '';
            $amountType = $data['amount_type'] ?? 'fixed'; // fixed or range
            $minAmount = isset($data['min_amount']) ? (float) $data['min_amount'] : null;
            $maxAmount = isset($data['max_amount']) ? (float) $data['max_amount'] : null;

            $createdProducts = [];

            if ($amountType === 'fixed') {
                // Create multiple products for fixed amounts
                $amountsArray = array_map('trim', explode(',', $amounts));

                foreach ($amountsArray as $amount) {
                    if ($amount === '' || !is_numeric($amount)) {
                        continue;
                    }

                    $product = $this->_createGiftCardProduct((float) $amount);
                    $createdProducts[] = $product->getName() . ' (SKU: ' . $product->getSku() . ')';
                }
            } else {
                // Create single product with custom option for amount range
                $product = $this->_createGiftCardProductWithRange($minAmount, $maxAmount);
                $createdProducts[] = $product->getName() . ' (SKU: ' . $product->getSku() . ')';
            }

            $this->_getSession()->addSuccess(
                $this->__('Created %d gift card product(s): %s', count($createdProducts), implode(', ', $createdProducts)),
            );

            $this->_redirect('*/giftcard/index');
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError('Failed to create gift card product: ' . $e->getMessage());
            $this->_redirect('*/*/create');
        }
    }

    /**
     * Create a gift card product for a fixed amount
     *
     * @return Mage_Catalog_Model_Product
     */
    protected function _createGiftCardProduct(float $amount)
    {
        $product = Mage::getModel('catalog/product');

        $sku = 'GIFTCARD-' . str_replace('.', '', (string) $amount);
        $name = 'Gift Card - ' . Mage::helper('core')->formatPrice($amount, false);

        $product->setStoreId(0)
            ->setAttributeSetId(4) // Default attribute set
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
            ->setSku($sku)
            ->setName($name)
            ->setDescription('Digital gift card that can be used as payment')
            ->setShortDescription('Gift Card')
            ->setPrice($amount)
            ->setWeight(0)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setTaxClassId(0) // No tax
            ->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 0,
                'is_in_stock' => 1,
            ])
            ->setData('is_giftcard', 1);

        $product->save();

        return $product;
    }

    /**
     * Create a gift card product with custom amount range
     *
     * @return Mage_Catalog_Model_Product
     */
    protected function _createGiftCardProductWithRange(?float $minAmount, ?float $maxAmount)
    {
        $product = Mage::getModel('catalog/product');

        $sku = 'GIFTCARD-CUSTOM';
        $name = 'Gift Card - Custom Amount';

        if ($minAmount && $maxAmount) {
            $name .= sprintf(
                ' (%s - %s)',
                Mage::helper('core')->formatPrice($minAmount, false),
                Mage::helper('core')->formatPrice($maxAmount, false),
            );
        }

        $product->setStoreId(0)
            ->setAttributeSetId(4)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
            ->setSku($sku)
            ->setName($name)
            ->setDescription('Digital gift card with custom amount')
            ->setShortDescription('Gift Card - Choose Your Amount')
            ->setPrice($minAmount ?? 25.00) // Default base price
            ->setWeight(0)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setTaxClassId(0)
            ->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 0,
                'is_in_stock' => 1,
            ])
            ->setData('is_giftcard', 1)
            ->setData('giftcard_min_amount', $minAmount)
            ->setData('giftcard_max_amount', $maxAmount);

        $product->save();

        // Add custom option for amount
        $option = Mage::getModel('catalog/product_option');
        $option->setProduct($product)
            ->setStoreId(0)
            ->setData([
                'title' => 'Gift Card Amount',
                'type' => 'field',
                'is_require' => 1,
                'sort_order' => 1,
                'price' => 0,
                'price_type' => 'fixed',
                'sku' => 'giftcard-amount',
                'max_characters' => 10,
            ]);

        $option->save();

        return $product;
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('giftcard/manage');
    }
}
