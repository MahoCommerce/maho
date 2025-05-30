<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Core_Model_Resource_Flag _getResource()
 * @method Mage_Core_Model_Resource_Flag getResource()
 * @method string getFlagCode()
 * @method $this setFlagCode(string $value)
 * @method int getState()
 * @method $this setState(int $value)
 * @method string getLastUpdate()
 * @method $this setLastUpdate(string $value)
 * @method bool hasFlagData()
 */
class Mage_Core_Model_Flag extends Mage_Core_Model_Abstract
{
    /**
     * Flag code
     *
     * @var string|null
     */
    protected $_flagCode = null;

    /**
     * Init resource model
     * Set flag_code if it is specified in arguments
     *
     */
    #[\Override]
    protected function _construct()
    {
        if ($this->hasData('flag_code')) {
            $this->_flagCode = $this->getData('flag_code');
        }
        $this->_init('core/flag');
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (is_null($this->_flagCode)) {
            Mage::throwException(Mage::helper('core')->__('Please define flag code.'));
        }

        $this->setFlagCode($this->_flagCode);
        $this->setLastUpdate(date(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT));

        return parent::_beforeSave();
    }

    /**
     * Retrieve flag data
     *
     * @return mixed
     */
    public function getFlagData()
    {
        if ($this->hasFlagData()) {
            return unserialize($this->getData('flag_data'), ['allowed_classes' => false]);
        } else {
            return null;
        }
    }

    /**
     * Set flag data
     *
     * @param mixed $value
     * @return $this
     */
    public function setFlagData($value)
    {
        return $this->setData('flag_data', serialize($value));
    }

    /**
     * load self (load by flag code)
     *
     * @return $this
     */
    public function loadSelf()
    {
        if (is_null($this->_flagCode)) {
            Mage::throwException(Mage::helper('core')->__('Please define flag code.'));
        }

        return $this->load($this->_flagCode, 'flag_code');
    }
}
