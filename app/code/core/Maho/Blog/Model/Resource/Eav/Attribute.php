<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Eav_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    public const SCOPE_STORE   = 0;
    public const SCOPE_GLOBAL  = 1;
    public const SCOPE_WEBSITE = 2;

    /**
     * Event prefix
     */
    protected $_eventPrefix = 'blog_entity_attribute';

    /**
     * Event object name
     */
    protected $_eventObject = 'attribute';

    /**
     * Array with labels
     */
    protected static ?array $_labels = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('blog/attribute');
    }

    /**
     * Processing object before save data
     *
     * @throws Mage_Core_Exception
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->setData('modulePrefix', 'Maho_Blog');
        return parent::_beforeSave();
    }

    /**
     * Return is attribute global
     *
     * @return integer
     */
    public function getIsGlobal()
    {
        return $this->_getData('is_global');
    }

    /**
     * Retrieve attribute is global scope flag
     *
     * @return bool
     */
    public function isScopeGlobal()
    {
        return $this->getIsGlobal() == self::SCOPE_GLOBAL;
    }

    /**
     * Retrieve attribute is website scope website
     *
     * @return bool
     */
    public function isScopeWebsite()
    {
        return $this->getIsGlobal() == self::SCOPE_WEBSITE;
    }

    /**
     * Retrieve attribute is store scope flag
     *
     * @return bool
     */
    public function isScopeStore()
    {
        return !$this->isScopeGlobal() && !$this->isScopeWebsite();
    }
}
