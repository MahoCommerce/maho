<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Payment_Model_Method_Ccsave extends Mage_Payment_Model_Method_Cc
{
    protected $_code        = 'ccsave';
    protected $_canSaveCc   = true;
    protected $_formBlockType = 'payment/form_ccsave';
    protected $_infoBlockType = 'payment/info_ccsave';
}
