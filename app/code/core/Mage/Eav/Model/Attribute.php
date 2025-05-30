<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Eav_Model_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    /**
     * Name of the module
     * Override it
     */
    //const MODULE_NAME = 'Mage_Eav';

    /**
     * Prefix of model events object
     *
     * @var string
     */
    protected $_eventObject = 'attribute';

    /**
     * Active Website instance
     *
     * @var Mage_Core_Model_Website|null
     */
    protected $_website;

    /**
     * Set active website instance
     *
     * @param Mage_Core_Model_Website|int $website
     * @return Mage_Eav_Model_Attribute
     */
    public function setWebsite($website)
    {
        $this->_website = Mage::app()->getWebsite($website);
        return $this;
    }

    /**
     * Return active website instance
     *
     * @return Mage_Core_Model_Website
     */
    public function getWebsite()
    {
        if (is_null($this->_website)) {
            $this->_website = Mage::app()->getWebsite();
        }

        return $this->_website;
    }

    #[\Override]
    protected function _afterSave()
    {
        Mage::getSingleton('eav/config')->clear();
        return parent::_afterSave();
    }

    /**
     * Return forms in which the attribute
     *
     * @return array
     */
    public function getUsedInForms()
    {
        $forms = $this->getData('used_in_forms');
        if (is_null($forms)) {
            $forms = $this->_getResource()->getUsedInForms($this);
            $this->setData('used_in_forms', $forms);
        }
        return $forms;
    }

    /**
     * Return validate rules
     *
     * @return array
     */
    public function getValidateRules()
    {
        $rules = $this->getData('validate_rules');
        if (is_array($rules)) {
            return $rules;
        } elseif (!empty($rules)) {
            return Mage::helper('core/unserializeArray')->unserialize($rules);
        }
        return [];
    }

    /**
     * Set validate rules
     *
     * @param array|string $rules
     * @return Mage_Eav_Model_Attribute
     */
    public function setValidateRules($rules)
    {
        if (empty($rules)) {
            $rules = null;
        } elseif (is_array($rules)) {
            $rules = serialize($rules);
        }
        $this->setData('validate_rules', $rules);

        return $this;
    }

    /**
     * Return scope value by key
     *
     * @param string $key
     * @return mixed
     */
    protected function _getScopeValue($key)
    {
        $scopeKey = sprintf('scope_%s', $key);
        return $this->getData($scopeKey) ?? $this->getData($key);
    }

    /**
     * Return is attribute value required
     *
     * @return mixed
     */
    public function getIsRequired()
    {
        return $this->_getScopeValue('is_required');
    }

    /**
     * Return is visible attribute flag
     *
     * @return mixed
     */
    public function getIsVisible()
    {
        return $this->_getScopeValue('is_visible');
    }

    /**
     * Return default value for attribute
     *
     * @return mixed
     */
    #[\Override]
    public function getDefaultValue()
    {
        return $this->_getScopeValue('default_value');
    }

    /**
     * Return count of lines for multiply line attribute
     *
     * @return mixed
     */
    public function getMultilineCount()
    {
        return $this->_getScopeValue('multiline_count');
    }
}
