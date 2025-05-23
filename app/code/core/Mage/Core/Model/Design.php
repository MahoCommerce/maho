<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Core_Model_Resource_Design _getResource()
 * @method Mage_Core_Model_Resource_Design getResource()
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getDesign()
 * @method $this setDesign(string $value)
 * @method string getDateFrom()
 * @method $this setDateFrom(string $value)
 * @method string getDateTo()
 * @method $this setDateTo(string $value)
 * @method string getPackage()
 * @method string getTheme()
 */
class Mage_Core_Model_Design extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/design');
    }

    /**
     * @return $this
     */
    public function validate()
    {
        $this->getResource()->validate($this);
        return $this;
    }

    /**
     * @param int $storeId
     * @param string|null $date
     * @return $this
     */
    public function loadChange($storeId, $date = null)
    {
        $result = $this->getResource()
            ->loadChange($storeId, $date);

        if (!empty($result)) {
            if (!empty($result['design'])) {
                $tmp = explode('/', $result['design']);
                $result['package'] = $tmp[0];
                $result['theme'] = $tmp[1];
            }

            $this->setData($result);
        }

        return $this;
    }
}
