<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_AjaxController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Ajax action for inline translation
     */
    public function translateAction(): void
    {
        $translation = $this->getRequest()->getPost('translate');
        $area = $this->getRequest()->getPost('area');

        //filtering
        /** @var Mage_Core_Model_Input_Filter_MaliciousCode $filter */
        $filter = Mage::getModel('core/input_filter_maliciousCode');
        foreach ($translation as &$item) {
            $item['custom'] = $filter->filter($item['custom']);
        }

        echo Mage::helper('core/translate')->apply($translation, $area);
        exit();
    }

    /**
     * Allow access to all admin users
     *
     * Inline translation is a dev tool available to any authenticated admin.
     * The feature is gated by its own config flag (dev/translate_inline).
     *
     * @return true
     */
    #[\Override]
    protected function _isAllowed()
    {
        return true;
    }
}
