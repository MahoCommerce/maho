<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
