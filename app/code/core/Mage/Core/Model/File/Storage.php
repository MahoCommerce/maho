<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_File_Storage extends Mage_Core_Model_Abstract
{
    /**
     * Storage systems ids
     */
    public const STORAGE_MEDIA_FILE_SYSTEM         = 0;

    /**
     * Config paths for storing storage configuration
     */
    public const XML_PATH_MEDIA_RESOURCE_ALLOWLIST = 'default/system/media_storage_configuration/allowed_resources';
    public const XML_PATH_MEDIA_RESOURCE_IGNORED   = 'default/system/media_storage_configuration/ignored_resources';
    public const XML_PATH_MEDIA_LOADED_MODULES     = 'default/system/media_storage_configuration/loaded_modules';

    /**
     * @deprecated since 26.1, use XML_PATH_MEDIA_RESOURCE_ALLOWLIST instead
     */
    public const XML_PATH_MEDIA_RESOURCE_WHITELIST = self::XML_PATH_MEDIA_RESOURCE_ALLOWLIST;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'core_file_storage';


    /**
     * Retrieve storage model
     * Always returns filesystem storage model
     *
     * @param  int|null $storage
     * @param  array $params
     * @return Mage_Core_Model_File_Storage_File
     */
    public function getStorageModel($storage = null, $params = [])
    {
        $model = Mage::getModel('core/file_storage_file');

        if (isset($params['init']) && $params['init']) {
            $model->init();
        }

        return $model;
    }

}
