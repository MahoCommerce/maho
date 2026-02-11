<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_PaypalUk
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_PaypalUk_Block_Bml_Form extends Mage_Paypal_Block_Bml_Form
{
    /**
     * Payment method code
     * @var string
     */
    protected $_methodCode = Mage_Paypal_Model_Config::METHOD_WPP_PE_BML;
}
