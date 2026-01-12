<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Catalog_Product_Action_AttributeController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/update_attributes';

    #[\Override]
    protected function _construct()
    {
        // Define module dependent translate
        $this->setUsedModuleName('Mage_Catalog');
    }

    public function editAction(): void
    {
        if (!$this->_validateProducts()) {
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Update product attributes
     */
    public function saveAction(): void
    {
        if (!$this->_validateProducts()) {
            return;
        }

        /* Collect Data */
        $inventoryData      = $this->getRequest()->getParam('inventory', []);
        $attributesData     = $this->getRequest()->getParam('attributes', []);
        $websiteRemoveData  = $this->getRequest()->getParam('remove_website_ids', []);
        $websiteAddData     = $this->getRequest()->getParam('add_website_ids', []);
        $attributeName      = '';

        /* Prepare inventory data item options (use config settings) */
        foreach (Mage::helper('cataloginventory')->getConfigItemOptions() as $option) {
            if (isset($inventoryData[$option]) && !isset($inventoryData['use_config_' . $option])) {
                $inventoryData['use_config_' . $option] = 0;
            }
        }

        try {
            if ($attributesData) {
                $dateFormat = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
                $storeId    = $this->_getHelper()->getSelectedStoreId();
                $data       = new \Maho\DataObject();

                foreach ($attributesData as $attributeCode => $value) {
                    $attribute = Mage::getSingleton('eav/config')
                        ->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeCode);
                    if (!$attribute->getAttributeId()) {
                        unset($attributesData[$attributeCode]);
                        continue;
                    }
                    $data->setData($attributeCode, $value);
                    $attributeName = $attribute->getFrontendLabel();
                    $attribute->getBackend()->validate($data);
                    if ($attribute->getBackendType() == 'datetime') {
                        if (!empty($value)) {
                            try {
                                // Parse date using IntlDateFormatter
                                $formatter = new IntlDateFormatter(
                                    Mage::app()->getLocale()->getLocaleCode(),
                                    IntlDateFormatter::SHORT,
                                    IntlDateFormatter::NONE,
                                );
                                $timestamp = $formatter->parse($value);
                                if ($timestamp !== false) {
                                    $value = date(Mage_Core_Model_Locale::DATE_FORMAT, $timestamp);
                                } else {
                                    throw new Exception('Invalid date format');
                                }
                            } catch (Exception $e) {
                                // Fallback: try to parse as standard format
                                $date = DateTime::createFromFormat($dateFormat, $value);
                                if ($date !== false) {
                                    $value = $date->format(Mage_Core_Model_Locale::DATE_FORMAT);
                                } else {
                                    $value = null;
                                }
                            }
                        } else {
                            $value = null;
                        }
                        $attributesData[$attributeCode] = $value;
                    } elseif ($attribute->getFrontendInput() == 'multiselect') {
                        // Check if 'Change' checkbox has been checked by admin for this attribute
                        $isChanged = (bool) $this->getRequest()->getPost($attributeCode . '_checkbox');
                        if (!$isChanged) {
                            unset($attributesData[$attributeCode]);
                            continue;
                        }
                        if (is_array($value)) {
                            $value = implode(',', $value);
                        }
                        $attributesData[$attributeCode] = $value;
                    }
                }

                Mage::getSingleton('catalog/product_action')
                    ->updateAttributes($this->_getHelper()->getProductIds(), $attributesData, $storeId);
            }
            if ($inventoryData) {
                /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->setProcessIndexEvents(false);
                $stockItemSaved = false;
                $changedProductIds = [];

                foreach ($this->_getHelper()->getProductIds() as $productId) {
                    $stockItem->setData([]);
                    $stockItem->loadByProduct($productId)
                        ->setProductId($productId);

                    $stockDataChanged = false;
                    foreach ($inventoryData as $k => $v) {
                        $stockItem->setDataUsingMethod($k, $v);
                        if ($stockItem->dataHasChangedFor($k)) {
                            $stockDataChanged = true;
                        }
                    }
                    if ($stockDataChanged) {
                        $stockItem->save();
                        $stockItemSaved = true;
                        $changedProductIds[] = $productId;
                    }
                }

                if ($stockItemSaved) {
                    Mage::getSingleton('index/indexer')->indexEvents(
                        Mage_CatalogInventory_Model_Stock_Item::ENTITY,
                        Mage_Index_Model_Event::TYPE_SAVE,
                    );

                    Mage::dispatchEvent('catalog_product_stock_item_mass_change', [
                        'products' => $changedProductIds,
                    ]);
                }
            }

            if ($websiteAddData || $websiteRemoveData) {
                /** @var Mage_Catalog_Model_Product_Action $actionModel */
                $actionModel = Mage::getSingleton('catalog/product_action');
                $productIds  = $this->_getHelper()->getProductIds();

                if ($websiteRemoveData) {
                    $actionModel->updateWebsites($productIds, $websiteRemoveData, 'remove');
                }
                if ($websiteAddData) {
                    $actionModel->updateWebsites($productIds, $websiteAddData, 'add');
                }

                Mage::dispatchEvent('catalog_product_to_website_change', [
                    'products' => $productIds,
                ]);

                $notice = Mage::getConfig()->getNode('adminhtml/messages/website_chnaged_indexers/label');
                if ($notice) {
                    $this->_getSession()->addNotice($this->__((string) $notice, $this->getUrl('adminhtml/process/list')));
                }
            }

            $this->_getSession()->addSuccess(
                $this->__('Total of %d record(s) were updated', count($this->_getHelper()->getProductIds())),
            );
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $this->_getSession()->addError($attributeName . ': ' . $e->getMessage());
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('An error occurred while updating the product(s) attributes.'));
        }

        $this->_redirect('*/catalog_product/', ['store' => $this->_getHelper()->getSelectedStoreId()]);
    }

    /**
     * Validate selection of products for massupdate
     *
     * @return bool
     */
    protected function _validateProducts()
    {
        $error = false;
        $productIds = $this->_getHelper()->getProductIds();
        if (!is_array($productIds)) {
            $error = $this->__('Please select products for attributes update');
        } elseif (!Mage::getModel('catalog/product')->isProductsHasSku($productIds)) {
            $error = $this->__('Some of the processed products have no SKU value defined. Please fill it prior to performing operations on these products.');
        }

        if ($error) {
            $this->_getSession()->addError($error);
            $this->_redirect('*/catalog_product/', ['_current' => true]);
        }

        return !$error;
    }

    /**
     * Retrieve data manipulation helper
     *
     * @return Mage_Adminhtml_Helper_Catalog_Product_Edit_Action_Attribute
     */
    #[\Override]
    protected function _getHelper()
    {
        return Mage::helper('adminhtml/catalog_product_edit_action_attribute');
    }

    /**
     * Attributes validation action
     */
    public function validateAction(): void
    {
        $response = new \Maho\DataObject();
        $response->setError(false);
        $attributesData = $this->getRequest()->getParam('attributes', []);
        $data = new \Maho\DataObject();

        try {
            if ($attributesData) {
                $dateFormat = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
                $storeId    = $this->_getHelper()->getSelectedStoreId();

                foreach ($attributesData as $attributeCode => $value) {
                    $attribute = Mage::getSingleton('eav/config')
                        ->getAttribute('catalog_product', $attributeCode);
                    if (!$attribute->getAttributeId()) {
                        unset($attributesData[$attributeCode]);
                        continue;
                    }
                    $data->setData($attributeCode, $value);
                    $attribute->getBackend()->validate($data);
                }
            }
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $response->setError(true);
            $response->setAttribute($e->getAttributeCode());
            $response->setMessage($e->getMessage());
        } catch (Mage_Core_Exception $e) {
            $response->setError(true);
            $response->setMessage($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('An error occurred while updating the product(s) attributes.'));
            $this->_initLayoutMessages('adminhtml/session');
            $response->setError(true);
            $response->setMessage($this->getLayout()->getMessagesBlock()->getGroupedHtml());
        }

        $this->getResponse()->setBody($response->toJson());
    }
}
