<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer front  newsletter manage block
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Block_Newsletter extends Mage_Customer_Block_Account_Dashboard // Mage_Core_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('customer/form/newsletter.phtml');
    }

    /**
     * @return bool
     */
    public function getIsSubscribed()
    {
        return $this->getSubscriptionObject()->isSubscribed();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getAction()
    {
        return $this->getUrl('*/*/save');
    }
}
