<?php

/**
 * Maho
 *
 * @package    Mage_Rating
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Rating_Model_Resource_Rating_Entity _getResource()
 * @method Mage_Rating_Model_Resource_Rating_Entity getResource()
 * @method string getEntityCode()
 * @method $this setEntityCode(string $value)
 */
class Mage_Rating_Model_Rating_Entity extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('rating/rating_entity');
    }

    /**
     * @param string $entityCode
     * @return string
     */
    public function getIdByCode($entityCode)
    {
        return $this->_getResource()->getIdByCode($entityCode);
    }
}
