<?php

/**
 * Maho
 *
 * @package    Mage_Uploader
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Uploader_Block_Abstract extends Mage_Adminhtml_Block_Widget
{
    /**
     * Template used for uploader
     *
     * @var string
     */
    protected $_template = 'media/uploader.phtml';

    /**
     * @var Mage_Uploader_Model_Config_Misc
     */
    protected $_misc;

    /**
     * @var Mage_Uploader_Model_Config_Uploader|null
     */
    protected $_uploaderConfig;

    /**
     * @var Mage_Uploader_Model_Config_Browsebutton|null
     */
    protected $_browseButtonConfig;

    /**
     * @var Mage_Uploader_Model_Config_Misc|null
     */
    protected $_miscConfig;

    /**
     * @var array
     */
    protected $_idsMapping = [];

    /**
     * Default browse button ID suffix
     */
    public const DEFAULT_BROWSE_BUTTON_ID_SUFFIX = 'browse';

    /**
     * Constructor for uploader block
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId($this->getId() . '_Uploader');
    }

    /**
     * Helper for file manipulation
     *
     * @return Mage_Uploader_Helper_File
     */
    protected function _getHelper()
    {
        return Mage::helper('uploader/file');
    }

    /**
     * @return string
     */
    public function getJsonConfig()
    {
        return $this->helper('core')->jsonEncode([
            'uploaderConfig'    => $this->getUploaderConfig()->getData(),
            'elementIds'        => $this->_getElementIdsMapping(),
            'browseConfig'      => $this->getButtonConfig()->getData(),
            'miscConfig'        => $this->getMiscConfig()->getData(),
        ]);
    }

    /**
     * Get mapping of ids for front-end use
     *
     * @return array
     */
    protected function _getElementIdsMapping()
    {
        return $this->_idsMapping;
    }

    /**
     * Add mapping ids for front-end use
     *
     * @param array $additionalButtons
     * @return $this
     */
    protected function _addElementIdsMapping($additionalButtons = [])
    {
        $this->_idsMapping = array_merge($this->_idsMapping, $additionalButtons);

        return $this;
    }

    /**
     * Prepare layout, create buttons, set front-end elements ids
     *
     * @return Mage_Core_Block_Abstract
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'browse_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->addData([
                    'id'            => $this->getElementId(self::DEFAULT_BROWSE_BUTTON_ID_SUFFIX),
                    'label'         => Mage::helper('uploader')->__('Browse Files...'),
                    'type'          => 'button',
                ]),
        );

        $this->setChild(
            'delete_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->addData([
                    'id'      => '{{id}}',
                    'class'   => 'delete',
                    'type'    => 'button',
                    'label'   => Mage::helper('uploader')->__('Remove'),
                ]),
        );

        $this->_addElementIdsMapping([
            'container'         => $this->getHtmlId(),
            'templateFile'      => $this->getElementId('template'),
            'browse'            => $this->_prepareElementsIds([self::DEFAULT_BROWSE_BUTTON_ID_SUFFIX]),
        ]);

        return parent::_prepareLayout();
    }

    /**
     * Get browse button html
     *
     * @return string
     */
    public function getBrowseButtonHtml()
    {
        return $this->getChildHtml('browse_button');
    }

    /**
     * Get delete button html
     *
     * @return string
     */
    public function getDeleteButtonHtml()
    {
        return $this->getChildHtml('delete_button');
    }

    /**
     * Get uploader misc settings
     *
     * @return Mage_Uploader_Model_Config_Misc
     */
    public function getMiscConfig()
    {
        if (is_null($this->_miscConfig)) {
            $this->_miscConfig = Mage::getModel('uploader/config_misc');
        }
        return $this->_miscConfig;
    }

    /**
     * Get uploader general settings
     *
     * @return Mage_Uploader_Model_Config_Uploader
     */
    public function getUploaderConfig()
    {
        if (is_null($this->_uploaderConfig)) {
            $this->_uploaderConfig = Mage::getModel('uploader/config_uploader');
        }
        return $this->_uploaderConfig;
    }

    /**
     * Get browse button settings
     *
     * @return Mage_Uploader_Model_Config_Browsebutton
     */
    public function getButtonConfig()
    {
        if (is_null($this->_browseButtonConfig)) {
            $this->_browseButtonConfig = Mage::getModel('uploader/config_browsebutton');
        }
        return $this->_browseButtonConfig;
    }

    /**
     * Get button unique id
     *
     * @param string $suffix
     * @return string
     */
    public function getElementId($suffix)
    {
        return $this->getHtmlId() . '-' . $suffix;
    }

    /**
     * Prepare actual elements ids from suffixes
     *
     * @param array $targets $type => array($idsSuffixes)
     * @return array $type => array($htmlIds)
     */
    protected function _prepareElementsIds($targets)
    {
        return array_map([$this, 'getElementId'], array_unique(array_values($targets)));
    }
}
