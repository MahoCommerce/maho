<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method array getCustomOptionUrlParams()
 */
class Mage_Catalog_Model_Product_Option_Type_File extends Mage_Catalog_Model_Product_Option_Type_Default
{
    /**
     * Url for custom option download controller
     * @var string
     */
    protected $_customOptionDownloadUrl = 'sales/download/downloadCustomOption';

    /**
     * @return bool
     */
    #[\Override]
    public function isCustomizedView()
    {
        return true;
    }

    /**
     * Return option html
     *
     * @param array $optionInfo
     * @return string
     */
    #[\Override]
    public function getCustomizedView($optionInfo)
    {
        try {
            if (isset($optionInfo['option_value'])) {
                return $this->_getOptionHtml($optionInfo['option_value']);
            }
            if (isset($optionInfo['value'])) {
                return $optionInfo['value'];
            }
        } catch (Exception $e) {
            return $optionInfo['value'];
        }
        return '';
    }

    /**
     * Returns additional params for processing options
     *
     * @return \Maho\DataObject
     */
    protected function _getProcessingParams()
    {
        $buyRequest = $this->getRequest();
        $params = $buyRequest->getData('_processing_params');
        /*
         * Notice check for params to be \Maho\DataObject - by using object we protect from
         * params being forged and contain data from user frontend input
         */
        if ($params instanceof \Maho\DataObject) {
            return $params;
        }
        return new \Maho\DataObject();
    }

    /**
     * Returns file info array if we need to get file from already existing file.
     * Or returns null, if we need to get file from uploaded array.
     *
     * @return null|array
     * @throws Mage_Core_Exception
     */
    protected function _getCurrentConfigFileInfo()
    {
        $option = $this->getOption();
        $optionId = $option->getId();
        $processingParams = $this->_getProcessingParams();
        $buyRequest = $this->getRequest();

        // Check maybe restore file from config requested
        $optionActionKey = 'options_' . $optionId . '_file_action';
        if ($buyRequest->getData($optionActionKey) === 'save_old') {
            $fileInfo = [];
            $currentConfig = $processingParams->getCurrentConfig();
            if ($currentConfig) {
                $fileInfo = $currentConfig->getData('options/' . $optionId);
            }
            return $fileInfo;
        }
        return null;
    }

    /**
     * Validate user input for option
     *
     * @param array $values All product option values, i.e. array (option_id => mixed, option_id => mixed...)
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function validateUserValue($values)
    {
        Mage::getSingleton('checkout/session')->setUseNotice(false);

        $this->setIsValid(true);
        $option = $this->getOption();

        /*
         * Check whether we receive uploaded file or restore file by: reorder/edit configuration or
         * previous configuration with no newly uploaded file
         */

        $fileInfo = $this->_getCurrentConfigFileInfo();

        if ($fileInfo !== null) {
            if (is_array($fileInfo) && $this->_validateFile($fileInfo)) {
                $value = $fileInfo;
            } else {
                $value = null;
            }
            $this->setUserValue($value);
            return $this;
        }

        // Process new uploaded file
        try {
            $this->_validateUploadedFile();
        } catch (Exception $e) {
            if ($this->getSkipCheckRequiredOption()) {
                $this->setUserValue(null);
                return $this;
            }
            Mage::throwException($e->getMessage());
        }
        return $this;
    }

    /**
     * Validate uploaded file
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _validateUploadedFile()
    {
        $option = $this->getOption();
        $processingParams = $this->_getProcessingParams();
        $file = $processingParams->getFilesPrefix() . 'options_' . $option->getId() . '_file';

        // Check for PHP file upload errors
        if (isset($_FILES[$file]) && $_FILES[$file]['error'] !== UPLOAD_ERR_OK) {
            $this->setIsValid(false);

            match ($_FILES[$file]['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => Mage::throwException(
                    Mage::helper('catalog')->__('The file you uploaded is larger than %s Megabytes allowed by server', $this->_bytesToMbytes($this->_getUploadMaxFilesize())),
                ),
                UPLOAD_ERR_PARTIAL => Mage::throwException(
                    Mage::helper('catalog')->__('The file was only partially uploaded. Please try again.'),
                ),
                UPLOAD_ERR_NO_FILE => $option->getIsRequire() ? Mage::throwException(
                    Mage::helper('catalog')->__('Please select a file for the required option "%s".', $option->getTitle()),
                ) : null,
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => Mage::throwException(
                    Mage::helper('catalog')->__('File upload failed. Please contact the store administrator.'),
                ),
                default => Mage::throwException(
                    Mage::helper('catalog')->__('File upload failed with error code: %s', $_FILES[$file]['error']),
                ),
            };
        }

        // Check if file was uploaded
        $fileUploaded = isset($_FILES[$file]) && isset($_FILES[$file]['tmp_name']) && !empty($_FILES[$file]['tmp_name']);

        if (!$option->getIsRequire() && !$fileUploaded) {
            $this->setUserValue(null);
            return $this;
        }

        if ($option->getIsRequire() && !$fileUploaded) {
            switch ($this->getProcessMode()) {
                case Mage_Catalog_Model_Product_Type_Abstract::PROCESS_MODE_FULL:
                    Mage::throwException(Mage::helper('catalog')->__('Please specify the product required option <em>%s</em>.', $option->getTitle()));
                    // exception thrown, no break
                    // no break
                default:
                    $this->setUserValue(null);
                    break;
            }
            return $this;
        }

        /**
         * Initialize uploader and validate
         */
        try {
            $uploader = new Mage_Core_Model_File_Uploader($file);

            // Get file info from $_FILES
            $fileInfo = $_FILES[$file];
            $fileInfo['title'] = $fileInfo['name'];
            $fileExtension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));

            // File extension validation
            $_allowed = $this->_parseExtensionsString($option->getFileExtension());
            $_forbidden = $this->_parseExtensionsString(Mage::getStoreConfig('catalog/custom_options/forbidden_extensions'));

            // ALWAYS check forbidden list first (security)
            if ($_forbidden !== null && in_array($fileExtension, array_map('strtolower', $_forbidden))) {
                Mage::throwException(
                    Mage::helper('catalog')->__('The following file extensions are not allowed for security reasons: %s', $fileExtension),
                );
            }

            if ($_allowed !== null) {
                // Check if any allowed extension is in the forbidden list
                if ($_forbidden !== null) {
                    $forbiddenFound = array_intersect(array_map('strtolower', $_allowed), array_map('strtolower', $_forbidden));
                    if (!empty($forbiddenFound)) {
                        Mage::throwException(Mage::helper('catalog')->__(
                            'The following file extensions are not allowed for security reasons: %s',
                            implode(', ', $forbiddenFound),
                        ));
                    }
                }

                // Validate the uploaded file extension against allowed list
                if (!in_array($fileExtension, array_map('strtolower', $_allowed))) {
                    Mage::throwException(
                        Mage::helper('catalog')->__('The following file extensions are not allowed for security reasons: %s', $fileExtension),
                    );
                }

                $uploader->setAllowedExtensions($_allowed);
            }

            // Disable file renaming and file dispersion - we handle the file name ourselves
            $uploader->setAllowRenameFiles(false);
            $uploader->setFilesDispersion(false);

            // Prepare file path
            $this->_initFilesystem();

            $fileName = Mage_Core_Model_File_Uploader::getCorrectFileName($fileInfo['name']);
            $dispersion = Mage_Core_Model_File_Uploader::getDispretionPath($fileName);

            $filePath = $dispersion;
            $fileHash = md5(file_get_contents($fileInfo['tmp_name']));
            $filePath .= DS . $fileHash . '.' . $fileExtension;
            $fileFullPath = $this->getQuoteTargetDir() . $filePath;

            // Queue the file for saving after product is added to cart
            $this->getProduct()->getTypeInstance(true)->addFileQueue([
                'operation' => 'receive_uploaded_file',
                'src_name'  => $file,
                'dst_name'  => $fileFullPath,
                'uploader'  => $uploader,
                'option'    => $this,
            ]);

            $_width = 0;
            $_height = 0;
            if (is_readable($fileInfo['tmp_name'])) {
                $_imageSize = @\Maho\Io::getImageSize($fileInfo['tmp_name']);
                if ($_imageSize) {
                    $_width = $_imageSize[0];
                    $_height = $_imageSize[1];

                    // Validate image dimensions if limits are set
                    if ($option->getImageSizeX() > 0 && $_width > $option->getImageSizeX()) {
                        Mage::throwException(
                            Mage::helper('catalog')->__("Maximum allowed image size for '%s' is %sx%s px.", $option->getTitle(), $option->getImageSizeX(), $option->getImageSizeY()),
                        );
                    }
                    if ($option->getImageSizeY() > 0 && $_height > $option->getImageSizeY()) {
                        Mage::throwException(
                            Mage::helper('catalog')->__("Maximum allowed image size for '%s' is %sx%s px.", $option->getTitle(), $option->getImageSizeX(), $option->getImageSizeY()),
                        );
                    }
                }
            }

            $this->setUserValue([
                'type'          => $fileInfo['type'],
                'title'         => $fileInfo['name'],
                'quote_path'    => $this->getQuoteTargetDir(true) . $filePath,
                'order_path'    => $this->getOrderTargetDir(true) . $filePath,
                'fullpath'      => $fileFullPath,
                'size'          => $fileInfo['size'],
                'width'         => $_width,
                'height'        => $_height,
                'secret_key'    => substr($fileHash, 0, 20),
            ]);
        } catch (Exception $e) {
            $this->setIsValid(false);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Validate file
     *
     * @param array $optionValue
     * @return bool
     * @throws Mage_Core_Exception
     */
    protected function _validateFile($optionValue)
    {
        $option = $this->getOption();
        /**
         * @see Mage_Catalog_Model_Product_Option_Type_File::_validateUploadFile()
         *              There setUserValue() sets correct fileFullPath only for
         *              quote_path. So we must form both full paths manually and
         *              check them.
         */
        $checkPaths = [];
        if (isset($optionValue['quote_path'])) {
            $checkPaths[] = Mage::getBaseDir() . $optionValue['quote_path'];
        }
        if (isset($optionValue['order_path']) && !$this->getUseQuotePath()) {
            $checkPaths[] = Mage::getBaseDir() . $optionValue['order_path'];
        }

        $fileFullPath = null;
        foreach ($checkPaths as $path) {
            if (is_file($path)) {
                $fileFullPath = $path;
                break;
            }
        }

        if ($fileFullPath === null) {
            return false;
        }

        $errors = [];

        // File extension validation - check this FIRST before trying to read the file
        $_allowed = $this->_parseExtensionsString($option->getFileExtension());
        $_forbidden = $this->_parseExtensionsString(Mage::getStoreConfig('catalog/custom_options/forbidden_extensions'));
        $extension = strtolower(pathinfo($fileFullPath, PATHINFO_EXTENSION));

        if ($_allowed !== null) {
            // Check if allowed extension is in forbidden list first
            if ($_forbidden !== null && in_array($extension, array_map('strtolower', $_forbidden))) {
                $errors[] = sprintf('The file extension "%s" is not allowed for security reasons.', $extension);
            } elseif (!in_array($extension, array_map('strtolower', $_allowed))) {
                $errors[] = sprintf('The file extension "%s" is not allowed.', $extension);
            }
        } else {
            // No specific allowed extensions - check forbidden list
            if ($_forbidden !== null && in_array($extension, array_map('strtolower', $_forbidden))) {
                $errors[] = sprintf('The file extension "%s" is not allowed for security reasons.', $extension);
            }
        }

        // If extension is invalid, don't proceed with further validations
        if (count($errors) > 0) {
            $this->setIsValid(false);
            Mage::throwException(implode("\n", $errors));
        }

        // Image size validation - only proceed if extension is valid
        $_dimentions = [];
        if ($option->getImageSizeX() > 0) {
            $_dimentions['maxwidth'] = $option->getImageSizeX();
        }
        if ($option->getImageSizeY() > 0) {
            $_dimentions['maxheight'] = $option->getImageSizeY();
        }
        if (count($_dimentions) > 0 && !$this->_isImage($fileFullPath)) {
            return false;
        }
        if (count($_dimentions) > 0) {
            $imageInfo = \Maho\Io::getImageSize($fileFullPath);
            if ($imageInfo !== false) {
                [$width, $height] = $imageInfo;
                if (isset($_dimentions['maxwidth']) && $width > $_dimentions['maxwidth']) {
                    $errors[] = sprintf('The image width (%d px) is too big (max %d px allowed).', $width, $_dimentions['maxwidth']);
                }
                if (isset($_dimentions['maxheight']) && $height > $_dimentions['maxheight']) {
                    $errors[] = sprintf('The image height (%d px) is too big (max %d px allowed).', $height, $_dimentions['maxheight']);
                }
            }
        }

        // Maximum filesize validation
        $maxSize = $this->_getUploadMaxFilesize();
        if (file_exists($fileFullPath)) {
            $fileSize = filesize($fileFullPath);
            if ($fileSize > $maxSize) {
                $errors[] = sprintf('The file is too big (%d bytes). Allowed maximum size is %d bytes.', $fileSize, $maxSize);
            }
        }

        if (count($errors) === 0) {
            return is_readable($fileFullPath)
                && isset($optionValue['secret_key'])
                && substr(md5(file_get_contents($fileFullPath)), 0, 20) == $optionValue['secret_key'];
        }

        $this->setIsValid(false);
        Mage::throwException(Mage::helper('catalog')->__('Please specify the product required option(s)'));
    }

    /**
     * Prepare option value for cart
     *
     * @return mixed Prepared option value
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function prepareForCart()
    {
        $option = $this->getOption();
        $optionId = $option->getId();
        $buyRequest = $this->getRequest();

        // Prepare value and fill buyRequest with option
        $requestOptions = $buyRequest->getOptions();
        if ($this->getIsValid() && $this->getUserValue() !== null) {
            $value = $this->getUserValue();

            // Save option in request, because we have no $_FILES['options']
            $requestOptions[$this->getOption()->getId()] = $value;
            $result = Mage::helper('core')->jsonEncode($value);
            try {
                Mage::helper('core')->jsonDecode($result);
            } catch (Exception $e) {
                Mage::throwException(Mage::helper('catalog')->__('File options format is not valid.'));
            }
        } else {
            /*
             * Clear option info from request, so it won't be stored in our db upon
             * unsuccessful validation. Otherwise some bad file data can happen in buyRequest
             * and be used later in reorders and reconfigurations.
             */
            if (is_array($requestOptions)) {
                unset($requestOptions[$this->getOption()->getId()]);
            }
            $result = null;
        }
        $buyRequest->setOptions($requestOptions);

        // Clear action key from buy request - we won't need it anymore
        $optionActionKey = 'options_' . $optionId . '_file_action';
        $buyRequest->unsetData($optionActionKey);

        return $result;
    }

    /**
     * Return formatted option value for quote option
     *
     * @param string $optionValue Prepared for cart option value
     * @return string
     */
    #[\Override]
    public function getFormattedOptionValue($optionValue)
    {
        if ($this->_formattedOptionValue === null) {
            try {
                $value = Mage::helper('core/unserializeArray')->unserialize($optionValue);

                $customOptionUrlParams = $this->getCustomOptionUrlParams() ?: [
                    'id'  => $this->getConfigurationItemOption()->getId(),
                    'key' => $value['secret_key'],
                ];

                $value['url'] = ['route' => $this->_customOptionDownloadUrl, 'params' => $customOptionUrlParams];

                $this->_formattedOptionValue = $this->_getOptionHtml($value);
                $this->getConfigurationItemOption()->setValue(Mage::helper('core')->jsonEncode($value));
                return $this->_formattedOptionValue;
            } catch (Exception $e) {
                return $optionValue;
            }
        }
        return $this->_formattedOptionValue;
    }

    /**
     * Format File option html
     *
     * @param string|array $optionValue Serialized string of option data or its data array
     * @return string
     * @throws Mage_Core_Exception
     */
    protected function _getOptionHtml($optionValue)
    {
        $value = $this->_unserializeValue($optionValue);
        try {
            if (isset($value) && isset($value['width']) && isset($value['height'])
                && $value['width'] > 0 && $value['height'] > 0
            ) {
                $sizes = $value['width'] . ' x ' . $value['height'] . ' ' . Mage::helper('catalog')->__('px.');
            } else {
                $sizes = '';
            }

            $urlRoute = empty($value['url']['route']) ? '' : $value['url']['route'];
            $urlParams = empty($value['url']['params']) ? '' : $value['url']['params'];
            $title = empty($value['title']) ? '' : $value['title'];

            return sprintf(
                '<a href="%s" target="_blank">%s</a> %s',
                $this->_getOptionDownloadUrl($urlRoute, $urlParams),
                Mage::helper('core')->escapeHtml($title),
                $sizes,
            );
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('catalog')->__('File options format is not valid.'));
        }
    }

    /**
     * Create a value from a storable representation
     *
     * @param mixed $value
     * @return array
     */
    protected function _unserializeValue($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && !empty($value)) {
            return Mage::helper('core/unserializeArray')->unserialize($value);
        }
        return [];
    }

    /**
     * Return printable option value
     *
     * @param string $optionValue Prepared for cart option value
     * @return string
     */
    #[\Override]
    public function getPrintableOptionValue($optionValue)
    {
        $value = $this->getFormattedOptionValue($optionValue);
        return $value === null ? '' : strip_tags($value);
    }

    /**
     * Return formatted option value ready to edit, ready to parse
     *
     * @param string $optionValue Prepared for cart option value
     * @return string
     */
    #[\Override]
    public function getEditableOptionValue($optionValue)
    {
        try {
            $value = Mage::helper('core/unserializeArray')->unserialize($optionValue);
            return sprintf(
                '%s [%d]',
                Mage::helper('core')->escapeHtml($value['title']),
                $this->getConfigurationItemOption()->getId(),
            );
        } catch (Exception $e) {
            return $optionValue;
        }
    }

    /**
     * Parse user input value and return cart prepared value
     *
     * @param string $optionValue
     * @param array $productOptionValues Values for product option
     * @return string|null
     */
    #[\Override]
    public function parseOptionValue($optionValue, $productOptionValues)
    {
        // search quote item option Id in option value
        if (preg_match('/\[([0-9]+)\]/', $optionValue, $matches)) {
            $confItemOptionId = $matches[1];
            $option = Mage::getModel('sales/quote_item_option')->load($confItemOptionId);
            try {
                return $option->getValue();
            } catch (Exception $e) {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Prepare option value for info buy request
     *
     * @param string $optionValue
     * @return mixed
     */
    #[\Override]
    public function prepareOptionValueForRequest($optionValue)
    {
        try {
            return Mage::helper('core/unserializeArray')->unserialize($optionValue);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Quote item to order item copy process
     *
     * @return $this
     */
    public function copyQuoteToOrder()
    {
        $quoteOption = $this->getConfigurationItemOption();
        try {
            $value = Mage::helper('core/unserializeArray')->unserialize($quoteOption->getValue());
            if (!isset($value['quote_path'])) {
                throw new Exception();
            }
            $quoteFileFullPath = Mage::getBaseDir() . $value['quote_path'];
            if (!is_file($quoteFileFullPath) || !is_readable($quoteFileFullPath)) {
                throw new Exception();
            }
            $orderFileFullPath = Mage::getBaseDir() . $value['order_path'];
            $dir = pathinfo($orderFileFullPath, PATHINFO_DIRNAME);
            $this->_createWriteableDir($dir);
            @copy($quoteFileFullPath, $orderFileFullPath);
        } catch (Exception $e) {
            return $this;
        }
        return $this;
    }

    /**
     * Main Destination directory
     *
     * @param bool $relative If true - returns relative path to the webroot
     * @return string
     */
    public function getTargetDir($relative = false)
    {
        $fullPath = Mage::getBaseDir('media') . DS . 'custom_options';
        return $relative ? str_replace(Mage::getBaseDir(), '', $fullPath) : $fullPath;
    }

    /**
     * Quote items destination directory
     *
     * @param bool $relative If true - returns relative path to the webroot
     * @return string
     */
    public function getQuoteTargetDir($relative = false)
    {
        return $this->getTargetDir($relative) . DS . 'quote';
    }

    /**
     * Order items destination directory
     *
     * @param bool $relative If true - returns relative path to the webroot
     * @return string
     */
    public function getOrderTargetDir($relative = false)
    {
        return $this->getTargetDir($relative) . DS . 'order';
    }

    /**
     * Set url to custom option download controller
     *
     * @param string $url
     * @return $this
     */
    public function setCustomOptionDownloadUrl($url)
    {
        $this->_customOptionDownloadUrl = $url;
        return $this;
    }

    /**
     * Directory structure initializing
     * @throws Mage_Core_Exception
     */
    protected function _initFilesystem()
    {
        $this->_createWriteableDir($this->getTargetDir());
        $this->_createWriteableDir($this->getQuoteTargetDir());
        $this->_createWriteableDir($this->getOrderTargetDir());

        // Directory listing and hotlink secure
        $io = new \Maho\Io\File();
        $io->cd($this->getTargetDir());
        if (!$io->fileExists($this->getTargetDir() . DS . '.htaccess')) {
            $io->streamOpen($this->getTargetDir() . DS . '.htaccess');
            $io->streamLock(true);
            $io->streamWrite("Order deny,allow\nDeny from all");
            $io->streamUnlock();
            $io->streamClose();
        }
    }

    /**
     * Create Writeable directory if it doesn't exist
     *
     * @param string $path Absolute directory path
     * @throws Mage_Core_Exception
     */
    protected function _createWriteableDir($path)
    {
        $io = new \Maho\Io\File();
        if (!$io->isWriteable($path) && !$io->mkdir($path, 0777, true)) {
            Mage::throwException(Mage::helper('catalog')->__("Cannot create writeable directory '%s'.", $path));
        }
    }

    /**
     * Return URL for option file download
     *
     * @param string $route
     * @param array $params
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _getOptionDownloadUrl($route, $params)
    {
        if (empty($params['_store']) && Mage::app()->getStore()->isAdmin()) {
            $order = Mage::registry('current_order');
            if (is_object($order)) {
                $params['_store'] = Mage::app()->getStore($order->getStoreId())->getCode();
            } else {
                $params['_store'] = Mage::app()->getDefaultStoreView()->getCode();
            }
        }
        return Mage::getUrl($route, $params);
    }

    /**
     * Parse file extensions string with various separators
     *
     * @param string $extensions String to parse
     * @return array|null
     */
    protected function _parseExtensionsString($extensions)
    {
        preg_match_all('/[a-z0-9]+/si', strtolower($extensions), $matches);
        if (count($matches[0]) > 0) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Simple check if file is image
     *
     * @param array|string $fileInfo - either file data from $_FILES or file path
     * @return bool
     */
    protected function _isImage($fileInfo)
    {
        // Maybe array with file info came in
        if (is_array($fileInfo)) {
            return strstr($fileInfo['type'], 'image/');
        }

        // File path came in - check the physical file
        if (!is_readable($fileInfo)) {
            return false;
        }
        $imageInfo = \Maho\Io::getImageSize($fileInfo);
        if (!$imageInfo) {
            return false;
        }
        return true;
    }

    /**
     * Max upload filesize in bytes
     *
     * @return int
     */
    protected function _getUploadMaxFilesize()
    {
        return min($this->_getBytesIniValue('upload_max_filesize'), $this->_getBytesIniValue('post_max_size'));
    }

    /**
     * Return php.ini setting value in bytes
     *
     * @param string $option php.ini Var name
     * @return int Setting value
     */
    protected function _getBytesIniValue($option)
    {
        $_bytes = @ini_get($option);

        return ini_parse_quantity($_bytes);
    }

    protected function _bytesToMbytes(int $bytes): int
    {
        return (int) ($bytes / (1024 * 1024));
    }
}
