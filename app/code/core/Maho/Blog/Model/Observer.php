<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Observer
{
    public function noRoute(Varien_Event_Observer $observer): self
    {
        $observer->getEvent()->getStatus()
            ->setLoaded(true)
            ->setForwardModule('blog')
            ->setForwardController('index')
            ->setForwardAction('noRoute');
        return $this;
    }
}
