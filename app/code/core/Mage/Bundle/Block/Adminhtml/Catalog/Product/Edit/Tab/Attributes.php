<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Bundle product attributes tab
 *
 * @method bool getCanEditPrice()
 */
class Mage_Bundle_Block_Adminhtml_Catalog_Product_Edit_Tab_Attributes extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Attributes
{
    /**
     * Prepare attributes form of bundle product
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareForm()
    {
        parent::_prepareForm();

        $specialPrice = $this->getForm()->getElement('special_price');
        if ($specialPrice) {
            $specialPrice->setRenderer(
                $this->getLayout()->createBlock('bundle/adminhtml_catalog_product_edit_tab_attributes_special')
                    ->setDisableChild(false),
            );
        }

        $sku = $this->getForm()->getElement('sku');
        if ($sku) {
            $sku->setRenderer(
                $this->getLayout()->createBlock('bundle/adminhtml_catalog_product_edit_tab_attributes_extend')
                    ->setDisableChild(false),
            );
        }

        $price = $this->getForm()->getElement('price');
        if ($price) {
            $price->setRenderer(
                $this->getLayout()->createBlock(
                    'bundle/adminhtml_catalog_product_edit_tab_attributes_extend',
                    'adminhtml.catalog.product.bundle.edit.tab.attributes.price',
                )->setDisableChild(true),
            );
        }

        $tax = $this->getForm()->getElement('tax_class_id');
        if ($tax) {
            $tax->setAfterElementHtml(
                '<script type="text/javascript">'
                . "
                function changeTaxClassId() {
                    if (document.getElementById('price_type').value == '" . Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC . "') {
                        document.getElementById('tax_class_id').disabled = true;
                        document.getElementById('tax_class_id').value = '0';
                        document.getElementById('tax_class_id').classList.remove('required-entry');
                        if (document.getElementById('advice-required-entry-tax_class_id')) {
                            document.getElementById('advice-required-entry-tax_class_id').remove();
                        }
                    } else {
                        document.getElementById('tax_class_id').disabled = false;
                        " . ($tax->getRequired() ? "document.getElementById('tax_class_id').classList.add('required-entry');" : '') . "
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    if (document.getElementById('price_type')) {
                        document.getElementById('price_type').addEventListener('change', changeTaxClassId);
                        changeTaxClassId();
                    }
                });
                "
                . '</script>',
            );
        }

        $weight = $this->getForm()->getElement('weight');
        if ($weight) {
            $weight->setRenderer(
                $this->getLayout()->createBlock('bundle/adminhtml_catalog_product_edit_tab_attributes_extend')
                    ->setDisableChild(true),
            );
        }

        $tierPrice = $this->getForm()->getElement('tier_price');
        if ($tierPrice) {
            $tierPrice->setRenderer(
                $this->getLayout()->createBlock('adminhtml/catalog_product_edit_tab_price_tier')
                    ->setPriceColumnHeader(Mage::helper('bundle')->__('Percent Discount'))
                    ->setPriceValidation('validate-greater-than-zero validate-percents'),
            );
        }

        $groupPrice = $this->getForm()->getElement('group_price');
        if ($groupPrice) {
            $groupPrice->setRenderer(
                $this->getLayout()->createBlock('adminhtml/catalog_product_edit_tab_price_group')
                    ->setPriceColumnHeader(Mage::helper('bundle')->__('Percent Discount'))
                    ->setIsPercent(true)
                    ->setPriceValidation('validate-greater-than-zero validate-percents'),
            );
        }

        $mapEnabled = $this->getForm()->getElement('msrp_enabled');
        if ($mapEnabled && $this->getCanEditPrice() !== false) {
            $mapEnabled->setAfterElementHtml(
                '<script type="text/javascript">'
                . "
                function changePriceTypeMap() {
                    if (document.getElementById('price_type').value == " . Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC . ") {
                        const msrpEnabled = document.getElementById('msrp_enabled');
                        msrpEnabled.value = " . Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Enabled::MSRP_ENABLE_NO . ";
                        msrpEnabled.disabled = true;
                        const msrpDisplayType = document.getElementById('msrp_display_actual_price_type');
                        msrpDisplayType.value = " . Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Price::TYPE_USE_CONFIG . ";
                        msrpDisplayType.disabled = true;
                        const msrp = document.getElementById('msrp');
                        msrp.value = '';
                        msrp.disabled = true;
                    } else {
                        document.getElementById('msrp_enabled').disabled = false;
                        document.getElementById('msrp_display_actual_price_type').disabled = false;
                        document.getElementById('msrp').disabled = false;
                    }
                }
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('price_type').addEventListener('change', changePriceTypeMap);
                    changePriceTypeMap();
                });
                "
                . '</script>',
            );
        }

        return $this;
    }

    /**
     * Get current product from registry
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        if (!$this->getData('product')) {
            $this->setData('product', Mage::registry('product'));
        }
        return $this->getData('product');
    }
}
