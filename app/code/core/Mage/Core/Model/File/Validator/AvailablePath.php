<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Filesystem\Path;

/**
 * Validator for check not protected/available path
 *
 * @deprecated since 26.1 Use Maho\Io::allowedPath() instead for simpler and more secure path validation.
 *
 * Mask symbols from path:
 * "?" - something directory with any name
 * "*" - something directory structure, which can not exist
 * Note: For set directory structure which must be exist, need to set mask "/?/{&#64;*}"
 * Mask symbols from filename:
 * "*" - something symbols in file name
 * Example:
 * <code>
 * //set available path
 * $validator->setAvailablePath(['/path/to/?/*fileMask.xml']);
 * $validator->isValid('/path/to/MyDir/Some-fileMask.xml'); //return true
 * $validator->setAvailablePath(['/path/to/{&#64;*}*.xml']);
 * $validator->isValid('/path/to/my.xml'); //return true, because directory structure can't exist
 * </code>
 */
class Mage_Core_Model_File_Validator_AvailablePath
{
    public array $protectedPaths = [];
    public array $availablePaths = [];

    protected array $messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $protectedPaths = null,
        ?array $availablePaths = null,
    ) {
        // Symfony constraint compatibility parameters (unused but kept for backward compatibility)
        unset($options, $groups, $payload);
        $this->protectedPaths = $protectedPaths ?? $this->protectedPaths;
        $this->availablePaths = $availablePaths ?? $this->availablePaths;
    }

    public function isValid(mixed $value): bool
    {
        $this->messages = [];

        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_string($value)) {
            $this->messages[] = Mage::helper('core')->__('Value must be a string.');
            return false;
        }

        $value = trim($value);

        if (!$this->availablePaths && !$this->protectedPaths) {
            $this->messages[] = Mage::helper('core')->__('Please set available and/or protected paths list(s) before validation.');
            return false;
        }

        // Block stream wrappers (phar://, http://, etc.) to prevent deserialization attacks
        if (!Path::isLocal($value)) {
            $this->messages[] = Mage::helper('core')->__('Path "%s" contains an invalid stream wrapper.', $value);
            return false;
        }

        if (preg_match('#\\..[\\\\/]#', $value)) {
            $this->messages[] = Mage::helper('core')->__('Path "%s" contains invalid parent directory references.', $value);
            return false;
        }

        //validation
        $protectedExtensions = Mage::helper('core/data')->getProtectedFileExtensions();
        $normalizedValue = str_replace(['/', '\\\\'], DS, $value);
        $valuePathInfo = pathinfo(ltrim($normalizedValue, '\\\\/'));
        $fileNameExtension = pathinfo($valuePathInfo['filename'], PATHINFO_EXTENSION);

        if (in_array($fileNameExtension, $protectedExtensions)) {
            $this->messages[] = Mage::helper('core')->__('Path "%s" is not available and cannot be used.', $value);
            return false;
        }

        if ($valuePathInfo['dirname'] == '.' || $valuePathInfo['dirname'] == DS) {
            $valuePathInfo['dirname'] = '';
        }

        if ($this->protectedPaths && !$this->_isValidByPaths($valuePathInfo, $this->protectedPaths, true)) {
            $this->messages[] = Mage::helper('core')->__('Path "%s" is protected and cannot be used.', $value);
            return false;
        }

        if ($this->availablePaths && !$this->_isValidByPaths($valuePathInfo, $this->availablePaths, false)) {
            $this->messages[] = Mage::helper('core')->__('Path "%s" is not available and cannot be used.', $value);
            return false;
        }

        return true;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getMessage(): string
    {
        return empty($this->messages) ? '' : $this->messages[0];
    }

    public function setProtectedPaths(array $protectedPaths): self
    {
        $this->protectedPaths = $protectedPaths;
        return $this;
    }

    public function setAvailablePaths(array $availablePaths): self
    {
        $this->availablePaths = $availablePaths;
        return $this;
    }

    public function setPaths(array $paths): self
    {
        if (isset($paths['available']) && is_array($paths['available'])) {
            $this->availablePaths = $paths['available'];
        }
        if (isset($paths['protected']) && is_array($paths['protected'])) {
            $this->protectedPaths = $paths['protected'];
        }
        return $this;
    }

    public function addProtectedPath(string $path): self
    {
        $this->protectedPaths[] = $path;
        return $this;
    }

    protected function _isValidByPaths(array $valuePathInfo, array $paths, bool $protected): bool
    {
        static $pathsData = [];

        foreach ($paths as $path) {
            $path = ltrim($path, '\\/');
            if (!isset($pathsData[$path]['regFilename'])) {
                $pathInfo = pathinfo($path);
                $options['file_mask'] = $pathInfo['basename'];
                if ($pathInfo['dirname'] == '.' || $pathInfo['dirname'] == DS) {
                    $pathInfo['dirname'] = '';
                } else {
                    $pathInfo['dirname'] = str_replace(['/', '\\'], DS, $pathInfo['dirname']);
                }
                $options['dir_mask'] = $pathInfo['dirname'];
                $pathsData[$path]['options'] = $options;
            } else {
                $options = $pathsData[$path]['options'];
            }

            //file mask
            if (str_contains($options['file_mask'], '*')) {
                if (!isset($pathsData[$path]['regFilename'])) {
                    //make regular
                    $reg = $options['file_mask'];
                    $reg = str_replace('.', '\\.', $reg);
                    $reg = str_replace('*', '.*?', $reg);
                    $reg = "/^($reg)$/";
                    $pathsData[$path]['regFilename'] = $reg;
                } else {
                    $reg = $pathsData[$path]['regFilename'];
                }
                $resultFile = preg_match($reg, $valuePathInfo['basename']);
            } else {
                $resultFile = ($options['file_mask'] == $valuePathInfo['basename']);
            }

            //directory mask
            $reg = $options['dir_mask'] . DS;
            if (!isset($pathsData[$path]['regDir'])) {
                //make regular
                $reg = str_replace('.', '\\.', $reg);
                $reg = str_replace('*\\', '||', $reg);
                $reg = str_replace('*/', '||', $reg);
                $reg = str_replace(DS, '[\\' . DS . ']', $reg);
                $reg = str_replace('?', '([^\\' . DS . ']+)', $reg);
                $reg = str_replace('||', '(.*[\\' . DS . '])?', $reg);
                $reg = "/^$reg$/";
                $pathsData[$path]['regDir'] = $reg;
            } else {
                $reg = $pathsData[$path]['regDir'];
            }
            $resultDir = preg_match($reg, $valuePathInfo['dirname'] . DS);
            if ($protected && ($resultDir && $resultFile)) {
                return false;
            }

            if (!$protected && ($resultDir && $resultFile)) {
                //return true because one match with available path mask
                return true;
            }
        }
        if ($protected) {
            return true;
        }
        //return false because no one match with available path mask
        return false;
    }
}
