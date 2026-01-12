<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Model_Method_Purchaseorder extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'purchaseorder';
    protected $_formBlockType = 'payment/form_purchaseorder';
    protected $_infoBlockType = 'payment/info_purchaseorder';

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Method_Purchaseorder
     */
    #[\Override]
    public function assignData($data)
    {
        if (!($data instanceof \Maho\DataObject)) {
            $data = new \Maho\DataObject($data);
        }

        $this->getInfoInstance()->setPoNumber($data->getPoNumber());
        return $this;
    }
}
