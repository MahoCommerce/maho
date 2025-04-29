<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * System cache model, supporting id and tags prefix
 */
class Mage_Core_Model_Cache
{
    /**
     * Cache settings
     */
    public const DEFAULT_LIFETIME  = 7200;
    public const OPTIONS_CACHE_ID  = 'core_cache_options';
    public const INVALIDATED_TYPES = 'core_cache_invalidate';
    public const XML_PATH_TYPES    = 'global/cache/types';

    protected string $_idPrefix    = '';

    /**
     * Cache frontend API
     *
     * @var Varien_Cache_Core|Zend_Cache_Core
     */
    protected $_frontend;

    /**
     * Default cache backend type
     *
     * @var string
     */
    protected $_defaultBackend = 'File';

    /**
     * Default options for default backend
     *
     * @var array
     */
    protected $_defaultBackendOptions = [
        'hashed_directory_level'   => 1,
        'hashed_directory_perm'    => 0777,
        'file_name_prefix'         => 'mage',
    ];

    /**
     * List of available request processors
     *
     * @var array
     */
    protected $_requestProcessors = [];

    /**
     * Disallow cache saving
     *
     * @var bool
     */
    protected $_disallowSave = false;

    /**
     * List of allowed cache options
     *
     * @var array|null
     */
    protected $_allowedCacheOptions;

    /**
     * DB connection
     *
     * @var string|null
     */
    protected $_dbConnection = 'core_write';

    /**
     * Class constructor. Initialize cache instance based on options
     */
    public function __construct(array $options = [])
    {
        $this->_defaultBackendOptions['cache_dir'] = $options['cache_dir'] ?? Mage::getBaseDir('cache');
        /**
         * Initialize id prefix
         */
        $this->_idPrefix = $options['id_prefix'] ?? '';
        if (!$this->_idPrefix && isset($options['prefix'])) {
            $this->_idPrefix = $options['prefix'];
        }
        if (empty($this->_idPrefix)) {
            $this->_idPrefix = substr(md5(Mage::getConfig()->getOptions()->getEtcDir()), 0, 3) . '_';
        }

        $backend    = $this->_getBackendOptions($options);
        $frontend   = $this->_getFrontendOptions($options);

        $this->_frontend = Zend_Cache::factory(
            'Varien_Cache_Core',
            $backend['type'],
            $frontend,
            $backend['options'],
            true,
            true,
            true,
        );

        if (isset($options['request_processors'])) {
            $this->_requestProcessors = $options['request_processors'];
        }

        if (isset($options['disallow_save'])) {
            $this->_disallowSave = (bool) $options['disallow_save'];
        }
    }

    /**
     * Get cache backend options. Result array contain backend type ('type' key) and backend options ('options')
     *
     * @return  array
     */
    protected function _getBackendOptions(array $cacheOptions)
    {
        $type   = $cacheOptions['backend'] ?? $this->_defaultBackend;
        if (isset($cacheOptions['backend_options']) && is_array($cacheOptions['backend_options'])) {
            $options = $cacheOptions['backend_options'];
        } else {
            $options = [];
        }

        $backendType = false;
        switch (strtolower($type)) {
            default:
                if ($type != $this->_defaultBackend) {
                    try {
                        if (class_exists($type, true)) {
                            $implements = class_implements($type, true);
                            if (in_array('Zend_Cache_Backend_Interface', $implements)) {
                                $backendType = $type;
                            }
                        }
                    } catch (Exception $e) {
                    }
                }
        }

        if (!$backendType) {
            $backendType = $this->_defaultBackend;
            foreach ($this->_defaultBackendOptions as $option => $value) {
                if (!array_key_exists($option, $options)) {
                    $options[$option] = $value;
                }
            }
        }

        $backendOptions = ['type' => $backendType, 'options' => $options];
        return $backendOptions;
    }

    /**
     * Get options for database backend type
     *
     * @return array
     */
    protected function getDbAdapterOptions(array $options = [])
    {
        if (isset($options['connection'])) {
            $this->_dbConnection = $options['connection'];
        }

        $options['adapter_callback'] = [$this, 'getDbAdapter'];
        $options['data_table'] = Mage::getSingleton('core/resource')->getTableName('core/cache');
        $options['tags_table'] = Mage::getSingleton('core/resource')->getTableName('core/cache_tag');
        return $options;
    }

    /**
     * Get options of cache frontend (options of Zend_Cache_Core)
     *
     * @return  array
     */
    protected function _getFrontendOptions(array $cacheOptions)
    {
        $options = $cacheOptions['frontend_options'] ?? [];
        if (!array_key_exists('caching', $options)) {
            $options['caching'] = true;
        }
        if (!array_key_exists('lifetime', $options)) {
            $options['lifetime'] = $cacheOptions['lifetime'] ?? self::DEFAULT_LIFETIME;
        }
        if (!array_key_exists('automatic_cleaning_factor', $options)) {
            $options['automatic_cleaning_factor'] = 0;
        }
        $options['cache_id_prefix'] = $this->_idPrefix;
        return $options;
    }

    /**
     * Prepare unified valid identifier with prefix
     *
     * @param   string $id
     * @return  string
     */
    protected function _id($id)
    {
        if ($id) {
            $id = strtoupper($id);
        }
        return $id;
    }

    /**
     * Prepare cache tags.
     *
     * @param   array $tags
     * @return  array
     */
    protected function _tags($tags = [])
    {
        foreach ($tags as $key => $value) {
            $tags[$key] = $this->_id($value);
        }
        return $tags;
    }

    /**
     * Get cache frontend API object
     *
     * @return Varien_Cache_Core|Zend_Cache_Core
     */
    public function getFrontend()
    {
        return $this->_frontend;
    }

    /**
     * Load data from cache by id
     *
     * @param   string $id
     * @return  string|false
     */
    public function load($id)
    {
        return $this->getFrontend()->load($this->_id($id));
    }

    /**
     * Save data
     *
     * @param string $data
     * @param string $id
     * @param array $tags
     * @param null|false|int $lifeTime
     * @return bool
     */
    public function save($data, $id, $tags = [], $lifeTime = null)
    {
        if ($this->_disallowSave) {
            return true;
        }

        return $this->getFrontend()->save((string) $data, $this->_id($id), $this->_tags($tags), $lifeTime);
    }

    /**
     * Test data
     *
     * @param string $id
     * @return false|int
     */
    public function test($id)
    {
        return $this->getFrontend()->test($this->_id($id));
    }

    /**
     * Remove cached data by identifier
     *
     * @param   string $id
     * @return  bool
     */
    public function remove($id)
    {
        return $this->getFrontend()->remove($this->_id($id));
    }

    /**
     * Clean cached data by specific tag
     *
     * @param   array|string $tags
     * @return  bool
     */
    public function clean($tags = [])
    {
        $mode = Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG;
        if (!empty($tags)) {
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            return $this->getFrontend()->clean($mode, $this->_tags($tags));
        }

        return $this->flush();
    }

    /**
     * Flush cached data
     *
     * @return  bool
     */
    public function flush()
    {
        return $this->getFrontend()->clean();
    }

    /**
     * Get adapter for database cache backend model
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function getDbAdapter()
    {
        return Mage::getSingleton('core/resource')->getConnection($this->_dbConnection);
    }

    /**
     * Get cache resource model
     *
     * @return Mage_Core_Model_Resource_Cache
     */
    protected function _getResource()
    {
        return Mage::getResourceSingleton('core/cache');
    }

    /**
     * Initialize cache types options
     *
     * @return $this
     */
    protected function _initOptions()
    {
        $options = $this->load(self::OPTIONS_CACHE_ID);
        if ($options === false) {
            $options = $this->_getResource()->getAllOptions();
            if (is_array($options)) {
                $this->_allowedCacheOptions = $options;
                $this->save(serialize($this->_allowedCacheOptions), self::OPTIONS_CACHE_ID);
            } else {
                $this->_allowedCacheOptions = [];
            }
        } else {
            $this->_allowedCacheOptions = unserialize($options, ['allowed_classes' => false]);
        }

        if (Mage::getConfig()->getOptions()->getData('global_ban_use_cache')) {
            foreach ($this->_allowedCacheOptions as $key => $val) {
                $this->_allowedCacheOptions[$key] = false;
            }
        }

        return $this;
    }

    /**
     * Save cache usage options
     *
     * @param array $options
     * @return $this
     */
    public function saveOptions($options)
    {
        $this->remove(self::OPTIONS_CACHE_ID);
        $this->_getResource()->saveAllOptions($options);
        return $this;
    }

    /**
     * Check if cache can be used for specific data type
     *
     * @param string $typeCode
     * @return bool|array
     */
    public function canUse($typeCode)
    {
        if (is_null($this->_allowedCacheOptions)) {
            $this->_initOptions();
        }

        if (empty($typeCode)) {
            return $this->_allowedCacheOptions;
        }

        if (isset($this->_allowedCacheOptions[$typeCode])) {
            return (bool) $this->_allowedCacheOptions[$typeCode];
        } else {
            return false;
        }
    }

    /**
     * Disable cache usage for specific data type
     *
     * @param string $typeCode
     * @return $this
     */
    public function banUse($typeCode)
    {
        $this->_allowedCacheOptions[$typeCode] = false;
        return $this;
    }

    /**
     * Enable cache usage for specific data type
     *
     * @param string $typeCode
     * @return $this
     */
    public function unbanUse($typeCode)
    {
        $this->_allowedCacheOptions[$typeCode] = true;
        return $this;
    }

    /**
     * Get cache tags by cache type from configuration
     *
     * @param string $type
     * @return array
     */
    public function getTagsByType($type)
    {
        $path = self::XML_PATH_TYPES . '/' . $type . '/tags';
        $tagsConfig = Mage::getConfig()->getNode($path);
        if ($tagsConfig) {
            $tags = (string) $tagsConfig;
            $tags = explode(',', $tags);
        } else {
            $tags = false;
        }
        return $tags;
    }

    /**
     * Get information about all declared cache types
     *
     * @return array
     */
    public function getTypes()
    {
        $types = [];
        $config = Mage::getConfig()->getNode(self::XML_PATH_TYPES);
        if ($config) {
            foreach ($config->children() as $type => $node) {
                $types[$type] = new Varien_Object([
                    'id'            => $type,
                    'cache_type'    => Mage::helper('core')->__((string) $node->label),
                    'description'   => Mage::helper('core')->__((string) $node->description),
                    'tags'          => strtoupper((string) $node->tags),
                    'status'        => (int) $this->canUse($type),
                ]);
            }
        }
        return $types;
    }

    /**
     * Get invalidate types codes
     *
     * @return array
     */
    protected function _getInvalidatedTypes()
    {
        $types = $this->load(self::INVALIDATED_TYPES);
        if ($types) {
            $types = unserialize($types, ['allowed_classes' => false]);
        } else {
            $types = [];
        }
        return $types;
    }

    /**
     * Save invalidated cache types
     *
     * @param array $types
     * @return $this
     */
    protected function _saveInvalidatedTypes($types)
    {
        $this->save(serialize($types), self::INVALIDATED_TYPES);
        return $this;
    }

    /**
     * Get array of all invalidated cache types
     *
     * @return array
     */
    public function getInvalidatedTypes()
    {
        $invalidatedTypes = [];
        $types = $this->_getInvalidatedTypes();
        if ($types) {
            $allTypes = $this->getTypes();
            foreach (array_keys($types) as $type) {
                if (isset($allTypes[$type]) && $this->canUse($type)) {
                    $invalidatedTypes[$type] = $allTypes[$type];
                }
            }
        }
        return $invalidatedTypes;
    }

    /**
     * Mark specific cache type(s) as invalidated
     *
     * @param string|array $typeCode
     * @return $this
     */
    public function invalidateType($typeCode)
    {
        $types = $this->_getInvalidatedTypes();
        if (!is_array($typeCode)) {
            $typeCode = [$typeCode];
        }
        foreach ($typeCode as $code) {
            $types[$code] = 1;
        }
        $this->_saveInvalidatedTypes($types);
        return $this;
    }

    /**
     * Clean cached data for specific cache type
     *
     * @param string $typeCode
     * @return $this
     */
    public function cleanType($typeCode)
    {
        $tags = $this->getTagsByType($typeCode);
        $this->clean($tags);

        $types = $this->_getInvalidatedTypes();
        unset($types[$typeCode]);
        $this->_saveInvalidatedTypes($types);
        return $this;
    }

    /**
     * Try to get response body from cache storage with predefined processors
     *
     * @return bool
     */
    public function processRequest()
    {
        if (empty($this->_requestProcessors)) {
            return false;
        }

        $content = false;
        foreach ($this->_requestProcessors as $processor) {
            $processor = $this->_getProcessor($processor);
            if ($processor) {
                $content = $processor->extractContent($content);
            }
        }

        if ($content) {
            Mage::app()->getResponse()->appendBody($content);
            return true;
        }
        return false;
    }

    /**
     * Get request processor object
     * @param string $processor
     * @return object
     */
    protected function _getProcessor($processor)
    {
        return new $processor();
    }
}
