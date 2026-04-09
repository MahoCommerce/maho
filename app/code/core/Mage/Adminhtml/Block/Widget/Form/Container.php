<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Form_Container extends Mage_Adminhtml_Block_Widget_Container
{
    protected $_objectId = 'id';
    protected $_formScripts = [];
    protected $_formInitScripts = [];
    protected $_mode = 'edit';
    protected $_blockGroup = 'adminhtml';

    protected ?string $_gridNavigationId = null;
    protected string $_gridNavigationRoute = '*/*/view';
    protected ?array $_gridNavigation = null;

    public function __construct()
    {
        parent::__construct();

        if (!$this->hasData('template')) {
            $this->setTemplate('widget/form/container.phtml');
        }

        $this->_addButton('back', [
            'label'     => Mage::helper('adminhtml')->__('Back'),
            'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getBackUrl()),
            'class'     => 'back',
        ], -1);
        $this->_addButton('reset', [
            'label'     => Mage::helper('adminhtml')->__('Reset'),
            'onclick'   => 'setLocation(window.location.href)',
        ], -1);

        $objId = $this->getRequest()->getParam($this->_objectId);

        if (!empty($objId)) {
            $this->_addButton('delete', [
                'label'     => Mage::helper('adminhtml')->__('Delete'),
                'class'     => 'delete',
                'onclick'   => Mage::helper('core/js')->getDeleteConfirmJs($this->getDeleteUrl()),
            ]);
        }

        $this->_addButton('save', [
            'label'     => Mage::helper('adminhtml')->__('Save'),
            'onclick'   => 'editForm.submit();',
            'class'     => 'save',
        ], 1);
    }

    public function getGridNavigation(): ?array
    {
        if ($this->_gridNavigation !== null) {
            return $this->_gridNavigation ?: null;
        }

        $this->_gridNavigation = [];

        $currentId = (int) $this->getRequest()->getParam($this->_objectId);
        if (!$this->_gridNavigationId || !$currentId) {
            return null;
        }

        $navData = Mage::getSingleton('adminhtml/session')->getData($this->_gridNavigationId . '_nav');
        if (!$navData || empty($navData['sql'])) {
            return null;
        }

        try {
            $sql = "SELECT * FROM ({$navData['sql']}) AS _nav WHERE {$navData['id_field']} = ?";
            $row = Mage::getSingleton('core/resource')->getConnection('core_read')
                ->fetchRow($sql, [...$navData['bind'], $currentId]);
            if (!$row) {
                return null;
            }

            $prevId = $row['prev_id'] !== null ? (int) $row['prev_id'] : null;
            $nextId = $row['next_id'] !== null ? (int) $row['next_id'] : null;

            $this->_gridNavigation = [
                'prev_id'  => $prevId,
                'next_id'  => $nextId,
                'prev_url' => $prevId !== null ? $this->getNavigationUrl($prevId) : null,
                'next_url' => $nextId !== null ? $this->getNavigationUrl($nextId) : null,
                'position' => (int) $row['position'],
                'total'    => (int) $row['total'],
            ];

            return $this->_gridNavigation;
        } catch (\Exception $e) {
            Mage::log('Grid navigation error: ' . $e->getMessage(), Mage::LOG_WARNING);
            return null;
        }
    }

    protected function getNavigationUrl(int $entityId): string
    {
        return $this->getUrl($this->_gridNavigationRoute, [$this->_objectId => $entityId]);
    }

    #[\Override]
    protected function _prepareLayout()
    {
        if ($this->_blockGroup && $this->_controller && $this->_mode) {
            $this->setChild('form', $this->getLayout()->createBlock($this->_blockGroup
                . '/'
                . $this->_controller
                . '_'
                . $this->_mode
                . '_form'));
        }
        return parent::_prepareLayout();
    }

    /**
     * Get URL for back (reset) button
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('*/*/');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getDeleteUrl()
    {
        return $this->getUrl('*/*/delete', [
            $this->_objectId => $this->getRequest()->getParam($this->_objectId),
            Mage_Core_Model_Url::FORM_KEY => $this->getFormKey(),
        ]);
    }

    /**
     * Get form save URL
     *
     * @deprecated
     * @see getFormActionUrl()
     * @return string
     */
    public function getSaveUrl()
    {
        return $this->getFormActionUrl();
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        if ($this->hasFormActionUrl()) {
            return $this->getData('form_action_url');
        }
        return $this->getUrl('*/' . $this->_controller . '/save');
    }

    /**
     * @return string
     */
    public function getFormHtml()
    {
        $this->getChild('form')->setData('action', $this->getSaveUrl());
        return $this->getChildHtml('form');
    }

    /**
     * @return string
     */
    public function getFormInitScripts()
    {
        if (!empty($this->_formInitScripts) && is_array($this->_formInitScripts)) {
            return '<script type="text/javascript">' . implode("\n", $this->_formInitScripts) . '</script>';
        }
        return '';
    }

    /**
     * @return string
     */
    public function getFormScripts()
    {
        if (!empty($this->_formScripts) && is_array($this->_formScripts)) {
            return '<script type="text/javascript">' . implode("\n", $this->_formScripts) . '</script>';
        }
        return '';
    }

    /**
     * @return string
     */
    public function getHeaderWidth()
    {
        return '';
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderHtml()
    {
        return '<h3 class="' . $this->getHeaderCssClass() . '">' . $this->getHeaderText() . '</h3>';
    }

    /**
     * Set data object and pass it to form
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    public function setDataObject($object)
    {
        $this->getChild('form')->setDataObject($object);
        return $this->setData('data_object', $object);
    }
}
