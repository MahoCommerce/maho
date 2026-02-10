<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_SalesRule_Model_Quote_Nominal_Discount extends Mage_SalesRule_Model_Quote_Discount
{
    /**
     * Don't add amounts to address
     *
     * @var bool
     */
    protected $_canAddAmountToAddress = false;

    /**
     * Don't fetch anything
     *
     * @return array
     */
    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        return Mage_Sales_Model_Quote_Address_Total_Abstract::fetch($address);
    }

    /**
     * Get nominal items only
     *
     * @return array
     */
    #[\Override]
    protected function _getAddressItems(Mage_Sales_Model_Quote_Address $address)
    {
        return $address->getAllNominalItems();
    }
}
