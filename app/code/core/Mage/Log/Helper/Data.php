<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Log_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_LOG_ENABLED = 'system/log/enable_log';

    protected $_moduleName = 'Mage_Log';

    /**
     * @var int
     */
    protected $_logLevel;

    /**
     * Mage_Log_Helper_Data constructor.
     */
    public function __construct(array $data = [])
    {
        $this->_logLevel = $data['log_level'] ?? Mage::getStoreConfigAsInt(self::XML_PATH_LOG_ENABLED);
    }

    /**
     * Are visitor should be logged
     *
     * @return bool
     */
    public function isVisitorLogEnabled()
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_VISITORS
        || $this->isLogEnabled();
    }

    /**
     * Are all events should be logged
     *
     * @return bool
     */
    public function isLogEnabled()
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_ALL;
    }

    /**
     * Are all events should be disabled
     *
     * @return bool
     */
    public function isLogDisabled()
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_NONE;
    }

    /**
     * Check if XML log configuration is managed by XML
     */
    public static function isLogConfigManagedByXml(): bool
    {
        return Mage_Core_Model_Logger::isLogConfigManagedByXml();
    }
}
