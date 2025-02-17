<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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

    public function saveAction()
    {
        $request = $this->getRequest();

        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->getConnection()
            ->update(
                $collection->getTable('catalog/product'),
                ['attribute_set_id' => $request->getParam('attribute_set')],
                'entity_id IN (' . implode(',', $request->getParam('product')) . ')',
            );

        $this->_getSession()->addSuccess(
            $this->__('Total of %d record(s) were updated', sizeof($request->getParam('product') ?? [])),
        );

        $this->_redirect('*/catalog_product/', [
            'store' => (int) $request->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID),
        ]);
    }
}
