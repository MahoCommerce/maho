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
 * @method Mage_Core_Model_Resource_Translate_String _getResource()
 * @method Mage_Core_Model_Resource_Translate_String getResource()
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getTranslate()
 * @method $this setTranslate(string $value)
 * @method array getStoreTranslations()
 * @method $this setStoreTranslations(array $value)
 * @method string getLocale()
 * @method $this setLocale(string $value)
 */
class Mage_Core_Model_Translate_String extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/translate_string');
    }

    /**
     * @param string $string
     * @return $this
     */
    public function setString($string)
    {
        $this->setData('string', $string);
        //$this->setData('string', strtolower($string));
        return $this;
    }

    /**
     * Retrieve string
     *
     * @return string
     */
    public function getString()
    {
        //return strtolower($this->getData('string'));
        return $this->getData('string');
    }
}
