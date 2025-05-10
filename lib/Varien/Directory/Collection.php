<?php

/**
 * Maho
 *
 * @package    Varien_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Varien_Directory_IFactory as IFactory;

/**
 * Varien Directory Collection
 *
 * Example Usage:
 * ```php
 * $a = new Varien_Directory_Collection('/usr/home/vasily/dev/magento/lib', false);
 *
 * $a->addFilter("extension","php");
 *
 * $a->useFilter(true);
 *
 * print "-----------------------\n";
 * print_r($a->filesName());
 *
 * $a->setPath('/usr/home/vasily/dev/magento/lib/Varien/Image', true);
 * $a->useFilter(true);
 *
 * print "-----------------------\n";
 * print_r($a->filesName());
 *
 * print "-----------------------\n";
 * $filesObj = $a->filesObj();
 * print $filesObj[0]->fgets();
 * print $filesObj[0]->fgets();
 * print $filesObj[0]->fgets();
 * print $filesObj[0]->fgets();
 * print $filesObj[0]->fgets();
 * print $filesObj[0]->fgets();
 * ```
 */
class Varien_Directory_Collection extends Varien_Data_Collection implements IFactory
{
    /** @var string */
    protected $_path = '';

    /** @var string */
    protected $_dirName = '';

    /** @var int */
    protected $_recursionLevel = 0;

    /** @var bool */
    protected $_isRecursion;

    /** @var array */
    protected $_filters = [];

    /**
     * Constructor
     *
     * @param string $path - path to directory
     * @param bool $isRecursion - use or not recursion
     * @param int $recursionLevel - how many levels to load
     */
    public function __construct($path, $isRecursion = true, $recursionLevel = 0)
    {
        parent::__construct();
        $this->setPath($path);
        $this->_dirName = $this->lastDir();
        $this->setRecursion($isRecursion);
        $this->setRecursionLevel($recursionLevel);
        if ($this->getRecursion() || $this->getRecursionLevel() == 0) {
            $this->parseDir();
        }
    }

    /**
     * Get name of this directory
     *
     * @return string - name of this directory
     */
    public function getDirName()
    {
        return (string) $this->_dirName;
    }

    /**
     * Get recursion
     *
     * @return bool - is or not recursion
     */
    public function getRecursion()
    {
        return (bool) $this->_isRecursion;
    }

    /**
     * Get recursion level
     *
     * @return int - recursion level
     */
    public function getRecursionLevel()
    {
        return (int) $this->_recursionLevel;
    }

    /**
     * Get path
     *
     * @return string - path to this directory
     */
    public function getPath()
    {
        return (string) $this->_path;
    }

    /**
     * Set path to this directory
     *
     * @param string $path - path to this directory
     * @param ?bool $isRecursion - use or not recursion
     */
    public function setPath($path, $isRecursion = null)
    {
        if (!is_dir($path)) {
            throw new Exception("$path is not dir.");
        }
        if ($this->_path != $path && $this->_path != '') {
            $this->_path = $path;
            if ($isRecursion !== null) {
                $this->setRecursion($isRecursion);
            }
            $this->parseDir();
        } else {
            $this->_path = $path;
        }
    }

    /**
     * Set recursion
     *
     * @param bool $isRecursion - use or not recursion
     */
    public function setRecursion($isRecursion)
    {
        $this->_isRecursion = (bool) $isRecursion;
    }

    /**
     * Set level of recursion
     *
     * @param int $recursionLevel - level of recursion
     */
    public function setRecursionLevel($recursionLevel)
    {
        $this->_recursionLevel = (int) $recursionLevel;
    }

    /**
     * Get latest dir in the path
     *
     * @return string - latest dir in the path
     */
    public function lastDir()
    {
        return self::getLastDir($this->getPath());
    }

    /**
     * Get latest dir in the path
     *
     * @param string $path - path to directory
     * @return string - latest dir in the path
     */
    public static function getLastDir($path)
    {
        $last = strrpos($path, '/');
        return substr($path, $last + 1);
    }

    /**
     * Add item to collection
     */
    #[\Override]
    public function addItem(Varien_Object|IFactory $item)
    {
        if ($item instanceof IFactory) {
            $this->_items[] = $item;
        }
        return $this;
    }

    /**
     * Parse this directory
     */
    protected function parseDir()
    {
        $this->clear();
        $iter = new RecursiveDirectoryIterator($this->getPath());
        while ($iter->valid()) {
            $curr = (string) $iter->getSubPathname();
            if (!$iter->isDot() && $curr[0] != '.') {
                $this->addItem(
                    Varien_Directory_Factory::getFactory($iter->current(), $this->getRecursion(), $this->getRecursionLevel()),
                );
            }
            $iter->next();
        }
    }

    /**
     * Set filter using
     *
     * @param bool $useFilter - filter using
     */
    #[\Override]
    public function useFilter($useFilter)
    {
        $this->_renderFilters();
        $this->walk('useFilter', [$useFilter]);
    }

    /**
     * Get files names of current collection
     *
     * @return array - files names of current collection
     */
    public function filesName()
    {
        $files = [];
        $this->getFilesName($files);
        return $files;
    }

    /**
     * Get files names of current collection
     *
     * @param array &$files - array of files names
     */
    #[\Override]
    public function getFilesName(&$files)
    {
        $this->walk('getFilesName', [&$files]);
    }

    /**
     * Get files paths of current collection
     *
     * @return array - files paths of current collection
     */
    public function filesPaths()
    {
        $paths = [];
        $this->getFilesPaths($paths);
        return $paths;
    }

    /**
     * Get files paths of current collection
     *
     * @param array &$paths - array of files paths
     */
    #[\Override]
    public function getFilesPaths(&$paths)
    {
        $this->walk('getFilesPaths', [&$paths]);
    }

    /**
     * Get SplFileObject objects of files of current collection
     *
     * @return array - array of SplFileObject objects
     */
    public function filesObj()
    {
        $objs = [];
        $this->getFilesObj($objs);
        return $objs;
    }

    /**
     * Get SplFileObject objects of files of current collection
     *
     * @param array &$objs - array of SplFileObject objects
     */
    #[\Override]
    public function getFilesObj(&$objs)
    {
        $this->walk('getFilesObj', [&$objs]);
    }

    /**
     * Get names of dirs of current collection
     *
     * @return array - array of names of dirs
     */
    public function dirsName()
    {
        $dir = [];
        $this->getDirsName($dir);
        return $dir;
    }

    /**
     * Get names of dirs of current collection
     *
     * @param array &$dirs - array of names of dirs
     */
    #[\Override]
    public function getDirsName(&$dirs)
    {
        $this->walk('getDirsName', [&$dirs]);
        if ($this->getRecursionLevel() > 0) {
            $dirs[] = $this->getDirName();
        }
    }

    /**
     * Set filters for files
     *
     * @param array $filter - array of filters
     */
    protected function setFilesFilter($filter)
    {
        $this->walk('setFilesFilter', [$filter]);
    }

    /**
     * Convert collection to array
     *
     * @return array
     */
    public function __toArray()
    {
        $arr = [];
        $this->toArray($arr);
        return $arr;
    }

    /**
     * Convert collection to array
     *
     * @param array &$arr - this collection array
     * @return array - reference to $arr
     */
    #[\Override]
    public function toArray(&$arr = [])
    {
        if ($this->getRecursionLevel() > 0) {
            $arr[$this->getDirName()] = [];
            $this->walk('toArray', [&$arr[$this->getDirName()]]);
        } else {
            $this->walk('toArray', [&$arr]);
        }
        return $arr;
    }

    /**
     * Convert collection to XML
     *
     * @param bool $addOpenTag - add or not header of xml
     * @param string $rootName - root element name
     * @return string
     */
    public function __toXml($addOpenTag = true, $rootName = 'Struct')
    {
        $xml = '';
        $this->toXml($xml, null, $addOpenTag, $rootName);
        return $xml;
    }

    /**
     * Convert collection to XML
     *
     * @param string &$xml - xml
     * @param bool $addOpenTag - add or not header of xml
     * @param string $rootName - root element name
     * @return string - reference to $xml
     */
    #[\Override]
    public function toXml(&$xml = '', $recursionLevel = 0, $addOpenTag = true, $rootName = 'Struct')
    {
        if ($recursionLevel == 0) {
            $xml = '';
            if ($addOpenTag) {
                $xml .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            }
            $xml .= "<$rootName>\n";
        }
        $recursionLevel = $this->getRecursionLevel();
        $xml .= str_repeat("\t", $recursionLevel + 1) . "<$this->_dirName>\n";
        $this->walk('toXml', [&$xml, $recursionLevel, $addOpenTag, $rootName]);
        $xml .= str_repeat("\t", $recursionLevel + 1) . "</$this->_dirName>" . "\n";
        if ($recursionLevel == 0) {
            $xml .= "</$rootName>\n";
        }
        return $xml;
    }

    #[\Override]
    protected function _renderFilters()
    {
        $exts = [];
        $names = [];
        $regName = [];
        foreach ($this->_filters as $filter) {
            switch ($filter['field']) {
                case 'extension':
                    if (is_array($filter['value'])) {
                        foreach ($filter['value'] as $value) {
                            $exts[] = $value;
                        }
                    } else {
                        $exts[] = $filter['value'];
                    }
                    break;
                case 'name':
                    if (is_array($filter['value'])) {
                        foreach ($filter['value'] as $value) {
                            $names[] = $filter['value'];
                        }
                    } else {
                        $names[] = $filter['value'];
                    }
                    break;
                case 'regName':
                    if (is_array($filter['value'])) {
                        foreach ($filter['value'] as $value) {
                            $regName[] = $filter['value'];
                        }
                    } else {
                        $regName[] = $filter['value'];
                    }
                    break;
            }
        }
        $filter = [];
        if (count($exts) > 0) {
            $filter['extension'] = $exts;
        } else {
            $filter['extension'] = null;
        }
        if (count($names) > 0) {
            $filter['name'] = $names;
        } else {
            $filter['name'] = null;
        }
        if (count($regName) > 0) {
            $filter['regName'] = $regName;
        } else {
            $filter['regName'] = null;
        }
        $this->setFilesFilter($filter);
        return $this;
    }

    /**
     * Add collection filter
     *
     * @param string $field
     * @param string|array $value
     * @param ?string $type - unused value
     * @return $this
     */
    #[\Override]
    public function addFilter($field, $value, $type = null)
    {
        $filter = [];
        $filter['field']   = $field;
        $filter['value']   = $value;
        $this->_filters[] = $filter;
        $this->_isFiltersRendered = false;
        $this->walk('addFilter', [$field, $value]);
        return $this;
    }
}
