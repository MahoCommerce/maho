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

class Mage_Adminhtml_TaxController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/config/tax';

    /**
     * Set tax ignore notification flag and redirect back
     */
    public function ignoreTaxNotificationAction(): void
    {
        $section = $this->getRequest()->getParam('section');
        if ($section && preg_match('/^[a-zA-Z0-9_]+$/', $section)) {
            Mage::helper('tax')->setIsIgnored('tax/ignore_notification/' . $section, true);
        }
        $this->_redirectReferer();
    }
}
