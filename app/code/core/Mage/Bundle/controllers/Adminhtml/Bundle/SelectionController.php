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

class Mage_Bundle_Adminhtml_Bundle_SelectionController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/products';

    #[\Override]
    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Bundle');
    }

    /**
     * @return Mage_Core_Controller_Response_Http
     */
    public function searchAction()
    {
        return $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('bundle/adminhtml_catalog_product_edit_tab_bundle_option_search')
                ->setIndex($this->getRequest()->getParam('index'))
                ->setFirstShow(true)
                ->toHtml(),
        );
    }

    /**
     * @return Mage_Core_Controller_Response_Http
     */
    public function gridAction()
    {
        return $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock(
                    'bundle/adminhtml_catalog_product_edit_tab_bundle_option_search_grid',
                    'adminhtml.catalog.product.edit.tab.bundle.option.search.grid',
                )
                ->setIndex($this->getRequest()->getParam('index'))
                ->toHtml(),
        );
    }
}
