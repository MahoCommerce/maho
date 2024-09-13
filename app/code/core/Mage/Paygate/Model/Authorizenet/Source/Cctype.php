<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Paygate
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Authorizenet Payment CC Types Source Model
 *
 * @category   Mage
 * @package    Mage_Paygate
 */
class Mage_Paygate_Model_Authorizenet_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
    #[\Override]
    public function getAllowedTypes()
    {
        return ['VI', 'MC', 'AE', 'DI', 'OT'];
    }
}
