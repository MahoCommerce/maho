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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class Mage_Core_Model_File_Validator_NotProtectedExtension
{
    /**
     * Protected file types
     *
     * @var array
     */
    protected $_protectedFileExtensions = [];

    public function __construct()
    {
        $this->_initProtectedFileExtensions();
    }

    /**
     * Initialize protected file extensions
     *
     * @return $this
     */
    protected function _initProtectedFileExtensions()
    {
        if (!$this->_protectedFileExtensions) {
            /** @var Mage_Core_Helper_Data $helper */
            $helper = Mage::helper('core');
            $extensions = $helper->getProtectedFileExtensions();
            if (is_string($extensions)) {
                $extensions = explode(',', $extensions);
            }
            foreach ($extensions as &$ext) {
                $ext = strtolower(trim($ext));
            }
            $this->_protectedFileExtensions = (array) $extensions;
        }
        return $this;
    }

    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * @param string $value         Extension of file
     * @return bool
     */
    public function isValid($value)
    {
        $value = strtolower(trim($value));

        if (in_array($value, $this->_protectedFileExtensions)) {
            return false;
        }

        return true;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getMessage()
    {
        return Mage::helper('core')->__('File with an extension is protected and cannot be uploaded');
    }
}
