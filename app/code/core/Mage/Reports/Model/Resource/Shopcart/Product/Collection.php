<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Shopcart_Product_Collection extends Mage_Reports_Model_Resource_Product_Collection
{
    /**
     * Join fields
     *
     * @return $this
     */
    #[\Override]
    protected function _joinFields()
    {
        parent::_joinFields();
        $this->addAttributeToSelect('price')
            ->addCartsCount()
            ->addOrdersCount();

        return $this;
    }

    /**
     * Set date range
     *
     * @param string $from
     * @param string $to
     * @return $this
     */
    public function setDateRange($from, $to)
    {
        $this->getSelect()->reset();
        return $this;
    }
}
