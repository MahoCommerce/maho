<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
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
        || $this->isAllVisitorLoggingEnabled();
    }

    /**
     * Are all visitor events and activities should be logged
     */
    public function isAllVisitorLoggingEnabled(): bool
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_ALL;
    }

    /**
     * Are all visitor events and activities should be disabled
     */
    public function isVisitorLoggingDisabled(): bool
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_NONE;
    }
}
