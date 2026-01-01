<?php

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Tax_Block_Checkout_Subtotal extends Mage_Checkout_Block_Total_Default
{
    /**
     *  Template for the block
     *
     * @var string
     */
    protected $_template = 'tax/checkout/subtotal.phtml';

    /**
     * The factory instance to get helper
     *
     * @var Mage_Core_Model_Factory|null
     */
    protected $_factory;

    /**
     * Initialize factory instance
     */
    public function __construct(array $args = [])
    {
        $this->_factory = empty($args['factory']) ? Mage::getSingleton('core/factory') : $args['factory'];
    }

    /**
     * @return bool
     */
    public function displayBoth()
    {
        return Mage::getSingleton('tax/config')->displayCartSubtotalBoth($this->getStore());
    }
}
