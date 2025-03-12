<?php

/**
 * Maho
 *
 * @package    Varien_File
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Varien_Directory_IFactory as IFactory;

/**
 * File Object
 *
 * @package    Varien_File
 */
class Varien_File_Object extends SplFileObject implements IFactory
{
    /** @var string */
    protected $_path;

    /** @var string */
    protected $_filename;

    /** @var ?array */
    protected $_filter;

    /** @var bool */
    protected $_isCorrect = true;

    /** @var ?bool */
    protected $filtered;

    /**
     * Constructor
     *
     * @param string $path - path to directory
     */
    public function __construct($path)
    {
        parent::__construct($path);
        $this->_path = $path;
        $this->_filename = basename($path);
    }

    /**
     * Get basename of file
     *
     * @param array &$files - array of files
     */
    #[\Override]
    public function getFilesName(&$files)
    {
        $files = [$this->getFilename()];
    }

    /**
     * Get basename of file
     */
    #[\Override]
    public function getFilename(): string
    {
        if ($this->_isCorrect) {
            return parent::getFilename();
        }
        return '';
    }

    /**
     * Gets the path without filename
     *
     * @param array &$paths - array of paths
     */
    #[\Override]
    public function getFilesPaths(&$paths)
    {
        $paths = [$this->getPath()];
    }

    /**
     * Gets the path without filename
     *
     * @param array &$paths - array of paths
     * @return string
     */
    public function getFilePath(&$paths)
    {
        $paths = [$this->getPath()];
        return $paths[0];
    }

    /**
     * Gets the path without filename
     */
    #[\Override]
    public function getPath(): string
    {
        if ($this->_isCorrect) {
            return parent::getPath();
        }
        return '';
    }

    /**
     * Use filter
     *
     * @param bool $useFilter - use or not filter
     */
    #[\Override]
    public function useFilter($useFilter)
    {
        if ($useFilter) {
            $this->renderFilter();
        } else {
            $this->_isCorrect = true;
            $this->filtered = false;
        }
    }

    /**
     * Get SplFileObject objects of this file
     *
     * @param array &$objs - array of gile objects
     */
    #[\Override]
    public function getFilesObj(&$objs)
    {
        if ($this->_isCorrect) {
            $objs[] = $this;
        }
    }

    /**
     * Get names of dirs of current collection
     *
     * @param array &$dirs - array of dirs
     */
    #[\Override]
    public function getDirsName(&$dirs)
    {
        $dirs = [$this->getDirName()];
        return $dirs[0];
    }

    /**
     * Get name of this directory
     *
     * @return string
     */
    public function getDirName()
    {
        $path = $this->getPath();
        $last = strrpos($path, '/');
        return substr($path, $last + 1);
    }

    /**
     * Set file filter
     *
     * @param array $filter - array of filter
     */
    public function setFilesFilter($filter)
    {
        $this->addFilter($filter);
    }

    /**
     * Set file filter
     *
     * @param array $filter - array of filter
     */
    public function addFilter($filter)
    {
        $this->_filter = $filter;
    }

    /**
     * Get extension of file
     *
     * @param string $fileName - name of file
     * @return string - extension of file
     */
    public static function getExt($fileName)
    {
        $path_parts = pathinfo($fileName);
        if (isset($path_parts['extension'])) {
            return $path_parts['extension'];
        } else {
            return '';
        }
    }

    /**
     * Get name of file
     *
     * @return string - name of file
     */
    public function getName()
    {
        return basename($this->_filename, '.' . $this->getExtension());
    }

    /**
     * Render filters
     */
    public function renderFilter()
    {
        if (isset($this->_filter) && count($this->_filter) > 0 && $this->filtered == false) {
            $this->filtered = true;
            if (isset($this->_filter['extension'])) {
                $filter = $this->_filter['extension'];
                if ($filter != null) {
                    if (is_array($filter)) {
                        if (!in_array($this->getExtension(), $filter)) {
                            $this->_isCorrect = false;
                        }
                    } else {
                        if ($this->getExtension() != $filter) {
                            $this->_isCorrect = false;
                        }
                    }
                }
            }
            if (isset($this->_filter['name'])) {
                $filter = $this->_filter['name'];
                if ($filter != null) {
                    if (is_array($filter)) {
                        if (!in_array($this->getName(), $filter)) {
                            $this->_isCorrect = false;
                        }
                    } else {
                        if ($this->getName() != $filter) {
                            $this->_isCorrect = false;
                        }
                    }
                }
            }

            if (isset($this->_filter['regName'])) {
                $filter = $this->_filter['regName'];

                if ($filter != null) {
                    foreach ($filter as $value) {
                        if (!preg_match($value, $this->getName())) {
                            $this->_isCorrect = false;
                        }
                    }
                }
            }
        }
    }

    /**
     * Convert collection to array
     *
     * @param array &$arr - export array
     */
    #[\Override]
    public function toArray(&$arr = [])
    {
        if ($this->_isCorrect) {
            $arr['files_in_dirs'][] = $this->_filename;
        }
        return $arr;
    }

    /**
     * Convert collection to XML
     *
     * @param string &$xml - export xml
     * @param int $recursionLevel - level of recursion
     * @param bool $addOpenTag - not used
     * @param string $rootName - not used
     */
    #[\Override]
    public function toXml(&$xml = '', $recursionLevel = 0, $addOpenTag = null, $rootName = null)
    {
        if ($this->_isCorrect) {
            $xml .= str_repeat("\t", $recursionLevel + 2) . "<fileName>{$this->_filename}</fileName>\n";
        }
        return $xml;
    }
}
