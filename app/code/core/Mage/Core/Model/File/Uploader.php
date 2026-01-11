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

class Mage_Core_Model_File_Uploader extends \Maho\File\Uploader
{
    /**
     * Flag, that defines should DB processing be skipped
     *
     * @var bool
     */
    protected $_skipDbProcessing = false;

    /**
     * Max file name length
     *
     * @var int
     */
    protected $_fileNameMaxLength = 200;

    /**
     * Save file to storage
     *
     * @param  array $result
     * @return $this
     */
    #[\Override]
    protected function _afterSave($result)
    {
        if (empty($result['path']) || empty($result['file'])) {
            return $this;
        }

        return $this;
    }

    /**
     * Getter/Setter for _skipDbProcessing flag
     *
     * @param null|bool $flag
     * @return bool|Mage_Core_Model_File_Uploader
     */
    public function skipDbProcessing($flag = null)
    {
        if (is_null($flag)) {
            return $this->_skipDbProcessing;
        }
        $this->_skipDbProcessing = (bool) $flag;
        return $this;
    }

    /**
     * Check protected/allowed extension
     *
     * @param string $extension
     * @return bool
     */
    #[\Override]
    public function checkAllowedExtension($extension)
    {
        //validate with protected file types
        /** @var Mage_Core_Model_File_Validator_NotProtectedExtension $validator */
        $validator = Mage::getSingleton('core/file_validator_notProtectedExtension');
        if (!$validator->isValid($extension)) {
            return false;
        }

        return parent::checkAllowedExtension($extension);
    }

    /**
     * Used to save uploaded file into destination folder with
     * original or new file name (if specified).
     * Added file name length validation.
     *
     * @param string $destinationFolder
     * @param string|null $newFileName
     * @return array|bool
     * @throws Exception
     */
    #[\Override]
    public function save($destinationFolder, $newFileName = null)
    {
        $fileName = $newFileName ?? $this->_file['name'];
        if (strlen($fileName) > $this->_fileNameMaxLength) {
            throw new Exception(
                Mage::helper('core')->__('File name is too long. Maximum length is %s.', $this->_fileNameMaxLength),
            );
        }
        return parent::save($destinationFolder, $newFileName);
    }
}
