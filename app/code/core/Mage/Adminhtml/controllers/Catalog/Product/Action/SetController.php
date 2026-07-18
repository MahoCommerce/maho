<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Catalog_Product_Action_SetController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/sets';

    #[\Override]
    protected function _construct()
    {
        // Define module dependent translate
        $this->setUsedModuleName('Mage_Catalog');
    }

    #[Maho\Config\Route('/admin/catalog_product_action_set/save')]
    public function saveAction(): void
    {
        try {
            $data = $this->getRequest()->getPost();
            if (!isset($data['attribute_set']) || !is_array($data['product'])) {
                Mage::throwException($this->__('Invalid data'));
            }

            $collection = Mage::getResourceModel('catalog/product_collection');
            $rowCount = $collection->getConnection()->update(
                $collection->getTable('catalog/product'),
                ['attribute_set_id' => $data['attribute_set']],
                ['entity_id IN (?)' => $data['product']],
            );

            $this->_getSession()->addSuccess(
                $this->__('%d product(s) were updated', $rowCount),
            );
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Internal Error'));
        }
        $this->_redirect('*/catalog_product/', ['_current' => true]);
    }
}
