<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Directory country format resource model
 *
 * @category   Mage
 * @package    Mage_Directory
 */
class Mage_Directory_Model_Resource_Country_Format_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/country_format');
    }

    /**
     * Set country filter
     *
     * @param string|Mage_Directory_Model_Country $country
     * @return $this
     */
    public function setCountryFilter($country)
    {
        if ($country instanceof Mage_Directory_Model_Country) {
            $countryId = $country->getId();
        } else {
            $countryId = $country;
        }

        return $this->addFieldToFilter('country_id', $countryId);
    }
}
