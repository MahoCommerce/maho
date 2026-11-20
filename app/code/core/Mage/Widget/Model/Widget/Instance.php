<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

declare(strict_types=1);

/**
 * @method Mage_Widget_Model_Resource_Widget_Instance _getResource()
 * @method Mage_Widget_Model_Resource_Widget_Instance getResource()
 * @method Mage_Widget_Model_Resource_Widget_Instance_Collection getCollection()
 */
class Mage_Widget_Model_Widget_Instance extends Mage_Core_Model_Abstract
{
    public const SPECIFIC_ENTITIES = 'specific';
    public const ALL_ENTITIES      = 'all';

    public const DEFAULT_LAYOUT_HANDLE            = 'default';
    public const PRODUCT_LAYOUT_HANDLE            = 'catalog_product_view';
    public const SINGLE_PRODUCT_LAYOUT_HANLDE     = 'PRODUCT_{{ID}}';
    public const PRODUCT_TYPE_LAYOUT_HANDLE       = 'PRODUCT_TYPE_{{TYPE}}';
    public const ANCHOR_CATEGORY_LAYOUT_HANDLE    = 'catalog_category_layered';
    public const NOTANCHOR_CATEGORY_LAYOUT_HANDLE = 'catalog_category_default';
    public const SINGLE_CATEGORY_LAYOUT_HANDLE    = 'CATEGORY_{{ID}}';

    public const XML_NODE_RELATED_CACHE = 'global/widget/related_cache_types';

    /**
     * Reasons to remove a stored layout update, see getLayoutUpdateRemovalReason()
     */
    public const LAYOUT_UPDATE_UNDECLARED_TYPE = 'undeclared widget type';

    public const LAYOUT_UPDATE_UNEXPECTED_XML = 'unexpected layout update XML';

    protected array $_layoutHandles = [];

    protected array $_specificEntitiesLayoutHandles = [];

    /**
     * @var \Maho\Simplexml\Element
     */
    protected $_widgetConfigXml = null;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'widget_widget_instance';

    /**
     * Internal Constructor
     */
    #[\Override]
    protected function _construct()
    {
        $this->_cacheTag = 'widget_instance';
        parent::_construct();
        $this->_init('widget/widget_instance');
        $this->_layoutHandles = [
            'anchor_categories' => self::ANCHOR_CATEGORY_LAYOUT_HANDLE,
            'notanchor_categories' => self::NOTANCHOR_CATEGORY_LAYOUT_HANDLE,
            'all_products' => self::PRODUCT_LAYOUT_HANDLE,
            'all_pages' => self::DEFAULT_LAYOUT_HANDLE,
        ];
        $this->_specificEntitiesLayoutHandles = [
            'anchor_categories' => self::SINGLE_CATEGORY_LAYOUT_HANDLE,
            'notanchor_categories' => self::SINGLE_CATEGORY_LAYOUT_HANDLE,
            'all_products' => self::SINGLE_PRODUCT_LAYOUT_HANLDE,
        ];
        foreach (Mage_Catalog_Model_Product_Type::getTypes() as $typeId => $type) {
            $layoutHandle = str_replace('{{TYPE}}', $typeId, self::PRODUCT_TYPE_LAYOUT_HANDLE);
            $this->_layoutHandles[$typeId . '_products'] = $layoutHandle;
            $this->_specificEntitiesLayoutHandles[$typeId . '_products'] = self::SINGLE_PRODUCT_LAYOUT_HANLDE;
        }
    }

    /**
     * Init mapping array of short fields to
     * its full names
     *
     * @return \Maho\DataObject
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        $this->_oldFieldsMap = [
            'type' => 'instance_type',
        ];
        return $this;
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->isTypeDeclared()) {
            Mage::log(
                sprintf('Refused to save widget instance %s: no widget.xml declares its type.', $this->getLogLabel()),
                Mage::LOG_WARNING,
            );
            Mage::throwException(Mage::helper('widget')->__('Wrong widget type specified.'));
        }

        $pageGroupIds = [];
        $tmpPageGroups = [];
        $pageGroups = $this->getData('page_groups');
        if ($pageGroups) {
            foreach ($pageGroups as $pageGroup) {
                $pageGroupData = $this->validatePageGroup($pageGroup);
                if ($pageGroupData !== null) {
                    if ($pageGroupData['page_id']) {
                        $pageGroupIds[] = $pageGroupData['page_id'];
                    }
                    if ($pageGroup['page_group'] == 'pages') {
                        $layoutHandle = $pageGroupData['layout_handle'];
                    } else {
                        $layoutHandle = $this->_layoutHandles[$pageGroup['page_group']];
                    }
                    $pageGroupData['template'] ??= '';
                    $tmpPageGroup = [
                        'page_id' => $pageGroupData['page_id'],
                        'group' => $pageGroup['page_group'],
                        'layout_handle' => $layoutHandle,
                        'for' => $pageGroupData['for'],
                        'block_reference' => $pageGroupData['block'],
                        'entities' => '',
                        'layout_handle_updates' => [$layoutHandle],
                        'template' => $pageGroupData['template'] ?: '',
                    ];
                    if ($pageGroupData['for'] == self::SPECIFIC_ENTITIES) {
                        $layoutHandleUpdates = [];
                        foreach (explode(',', $pageGroupData['entities']) as $entity) {
                            $layoutHandleUpdates[] = str_replace(
                                '{{ID}}',
                                $entity,
                                $this->_specificEntitiesLayoutHandles[$pageGroup['page_group']],
                            );
                        }
                        $tmpPageGroup['entities'] = $pageGroupData['entities'];
                        $tmpPageGroup['layout_handle_updates'] = $layoutHandleUpdates;
                    }
                    $tmpPageGroups[] = $tmpPageGroup;
                }
            }
        }
        if (is_array($this->getData('store_ids'))) {
            $this->setData('store_ids', implode(',', $this->getData('store_ids')));
        }
        if (is_array($this->getData('widget_parameters'))) {
            $this->setData('widget_parameters', Mage::helper('core')->jsonEncode($this->getData('widget_parameters')));
        }
        $this->setData('page_groups', $tmpPageGroups);
        $this->setData('page_group_ids', $pageGroupIds);

        return parent::_beforeSave();
    }

    /**
     * Validate widget instance data
     *
     * @return string|bool
     */
    public function validate()
    {
        if ($this->isCompleteToCreate()) {
            if (!$this->isTypeDeclared()) {
                return Mage::helper('widget')->__('Wrong widget type specified.');
            }
            return true;
        }
        return Mage::helper('widget')->__('Widget instance is not full complete to create.');
    }

    /**
     * Check if widget instance has required data (other data depends on it)
     *
     * @return bool
     */
    public function isCompleteToCreate()
    {
        return (bool) ($this->getType() && $this->getPackageTheme());
    }

    /**
     * Setter
     * Prepare widget type
     *
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->setData('type', $type);
        $this->_prepareType();
        return $this;
    }

    /**
     * Getter
     * Prepare widget type
     *
     * @return string
     */
    public function getType()
    {
        $this->_prepareType();
        return $this->_getData('type');
    }

    /**
     * Replace '-' to '/', if was passed from request(GET request)
     *
     * @return $this
     */
    protected function _prepareType()
    {
        if (str_contains((string) $this->_getData('type'), '-')) {
            $this->setData('type', str_replace('-', '/', $this->_getData('type')));
        }
        return $this;
    }

    /**
     * Setter
     * Prepare widget package theme
     *
     * @param string $packageTheme
     * @return $this
     */
    public function setPackageTheme($packageTheme)
    {
        $this->setData('package_theme', $packageTheme);
        return $this;
    }

    /**
     * Getter
     * Prepare widget package theme
     *
     * @return string
     */
    public function getPackageTheme()
    {
        return $this->_getData('package_theme');
    }

    /**
     * Getter.
     * If not set return default
     *
     * @return string
     */
    public function getArea()
    {
        if (!$this->_getData('area')) {
            return Mage_Core_Model_Design_Package::DEFAULT_AREA;
        }
        return $this->_getData('area');
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getPackage()
    {
        if (!$this->_getData('package')) {
            $this->_parsePackageTheme();
        }
        return $this->_getData('package');
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getTheme()
    {
        if (!$this->_getData('theme')) {
            $this->_parsePackageTheme();
        }
        return $this->_getData('theme');
    }

    /**
     * Parse packageTheme and set parsed package and theme
     *
     * @return $this
     */
    protected function _parsePackageTheme()
    {
        if ($this->getPackageTheme() && strpos($this->getPackageTheme(), '/')) {
            [$package, $theme] = explode('/', $this->getPackageTheme());
            $this->setData('package', $package);
            $this->setData('theme', $theme);
        }
        return $this;
    }

    /**
     * Getter
     * Explode to array if string is set
     *
     * @return array
     */
    public function getStoreIds()
    {
        if (is_string($this->getData('store_ids'))) {
            return explode(',', $this->getData('store_ids'));
        }
        return $this->getData('store_ids');
    }

    /**
     * Getter
     * Unserialize if serialized string is set
     *
     * @return array
     */
    public function getWidgetParameters()
    {
        if (is_string($this->getData('widget_parameters'))) {
            try {
                return Mage::helper('core/unserializeArray')->unserialize($this->getData('widget_parameters'));
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return (is_array($this->getData('widget_parameters'))) ? $this->getData('widget_parameters') : [];
    }

    /**
     * Retrieve option array of widget types
     *
     * @return array
     */
    public function getWidgetsOptionArray()
    {
        $widgets = [];
        $widgetsArr = Mage::getSingleton('widget/widget')->getWidgetsArray();
        foreach ($widgetsArr as $widget) {
            $widgets[] = [
                'value' => $widget['type'],
                'label' => $widget['name'],
            ];
        }
        return $widgets;
    }

    /**
     * Load widget XML config and merge with theme widget config
     *
     * @return \Maho\Simplexml\Element|null
     */
    public function getWidgetConfig()
    {
        if ($this->_widgetConfigXml === null) {
            $this->_widgetConfigXml = Mage::getSingleton('widget/widget')
                ->getXmlElementByType($this->getType());
            if ($this->_widgetConfigXml) {
                $configFile = Mage::getSingleton('core/design_package')->getBaseDir([
                    '_area'    => $this->getArea(),
                    '_package' => $this->getPackage(),
                    '_theme'   => $this->getTheme(),
                    '_type'    => 'etc',
                ]) . DS . 'widget.xml';
                $configFile = Maho::findFile($configFile);
                if ($configFile !== false && is_readable($configFile)) {
                    $themeWidgetsConfig = new \Maho\Simplexml\Config();
                    $themeWidgetsConfig->loadFile($configFile);
                    if ($themeWidgetTypeConfig = $themeWidgetsConfig->getNode($this->_widgetConfigXml->getName())) {
                        $this->_widgetConfigXml->extend($themeWidgetTypeConfig);
                    }
                }
            }
        }
        return $this->_widgetConfigXml;
    }

    /**
     * Retrieve widget available templates
     *
     * @return array
     */
    public function getWidgetTemplates()
    {
        $templates = [];
        if ($this->getWidgetConfig() && ($configTemplates = $this->getWidgetConfig()->parameters->template)) {
            if ($configTemplates->values && $configTemplates->values->children()) {
                foreach ($configTemplates->values->children() as $name => $template) {
                    $helper = $template->getAttribute('module') ?: 'widget';
                    $templates[(string) $name] = [
                        'value' => (string) $template->value,
                        'label' => Mage::helper($helper)->__((string) $template->label),
                    ];
                }
            } elseif ($configTemplates->value) {
                $templates['default'] = [
                    'value' => (string) $configTemplates->value,
                    'label' => Mage::helper('widget')->__('Default Template'),
                ];
            }
        }
        return $templates;
    }

    /**
     * Retrieve blocks that widget support
     *
     * @return array
     */
    public function getWidgetSupportedBlocks()
    {
        $blocks = [];
        if ($this->getWidgetConfig() && ($supportedBlocks = $this->getWidgetConfig()->supported_blocks)) {
            foreach ($supportedBlocks->children() as $block) {
                $blocks[] = (string) $block->block_name;
            }
        }
        return $blocks;
    }

    /**
     * Retrieve widget templates that supported by given block reference
     *
     * @param string $blockReference
     * @return array
     */
    public function getWidgetSupportedTemplatesByBlock($blockReference)
    {
        $templates = [];
        $template = null;
        $widgetTemplates = $this->getWidgetTemplates();
        if ($this->getWidgetConfig()) {
            if (!($supportedBlocks = $this->getWidgetConfig()->supported_blocks)) {
                return $widgetTemplates;
            }
            foreach ($supportedBlocks->children() as $block) {
                if ((string) $block->block_name == $blockReference) {
                    if ($block->template && $block->template->children()) {
                        foreach ($block->template->children() as $template) {
                            if (isset($widgetTemplates[(string) $template])) {
                                $templates[] = $widgetTemplates[(string) $template];
                            }
                        }
                    } else {
                        $templates[] = $widgetTemplates[(string) $template];
                    }
                }
            }
        } else {
            return $widgetTemplates;
        }
        return $templates;
    }

    /**
     * Generate layout update xml
     *
     * The result is empty for an incomplete new instance, for a template that cannot be read,
     * and for a type that no widget.xml declares.
     *
     * @param string $blockReference
     * @param string $templatePath
     * @return string
     */
    public function generateLayoutUpdateXml($blockReference, $templatePath = '')
    {
        $templatePath = (string) $templatePath;
        if (!$this->isValidTemplatePath($templatePath) || !$this->isValidBlockReference($blockReference)) {
            Mage::throwException(Mage::helper('widget')->__('Templatepath or block reference contain special characters.'));
        }

        if ((!$this->getId() && !$this->isCompleteToCreate()) || !$this->isTypeDeclared()) {
            return '';
        }

        $templateFilename = Mage::getSingleton('core/design_package')->getTemplateFilename($templatePath, [
            '_area'    => $this->getArea(),
            '_package' => $this->getPackage(),
            '_theme'   => $this->getTheme(),
        ]);
        if ($templatePath && !is_readable($templateFilename)) {
            return '';
        }
        $parameters = $this->getWidgetParameters();
        $xml = '<reference name="' . $this->escapeXml($blockReference) . '">';
        $template = '';
        if (isset($parameters['template'])) {
            unset($parameters['template']);
        }
        if ($templatePath) {
            $template = ' template="' . $this->escapeXml($templatePath) . '"';
        }

        $hash = Mage::helper('core')->uniqHash();
        $xml .= '<block type="' . $this->escapeXml($this->getType()) . '" name="' . $this->escapeXml($hash) . '"' . $template . '>';
        foreach ($parameters as $name => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            if ($name && strlen((string) $value)) {
                $xml .= '<action method="setData">'
                    . '<name>' . $this->escapeXml($name) . '</name>'
                    . '<value>' . $this->escapeXml($value) . '</value>'
                    . '</action>';
            }
        }
        $xml .= '</block></reference>';

        return $xml;
    }

    /**
     * Check one entry of the page groups (the "Display on" rules) and return its data
     *
     * The admin form and _beforeSave() use this check, so that both skip or refuse the same entries.
     *
     * @return array<mixed>|null the data of the page group, or null when the entry holds no data
     * @throws Mage_Core_Exception when the entry is not valid
     */
    public function validatePageGroup(mixed $pageGroup): ?array
    {
        if (!is_array($pageGroup) || !isset($pageGroup['page_group'])) {
            return null;
        }

        $code = $pageGroup['page_group'];
        if (!is_string($code)) {
            Mage::throwException(Mage::helper('widget')->__('Wrong widget display option specified.'));
        }

        if (!isset($pageGroup[$code])) {
            return null;
        }

        $pageGroupData = $pageGroup[$code];
        if (!is_array($pageGroupData)) {
            Mage::throwException(Mage::helper('widget')->__('Wrong widget display option specified.'));
        }

        $this->_validatePageGroupData($code, $pageGroupData);
        return $pageGroupData;
    }

    /**
     * @param array<mixed> $pageGroupData
     * @throws Mage_Core_Exception
     */
    protected function _validatePageGroupData(string $code, array $pageGroupData): void
    {
        if ($code !== 'pages' && !isset($this->_layoutHandles[$code])) {
            Mage::throwException(Mage::helper('widget')->__('Wrong widget display option specified.'));
        }

        $for = $pageGroupData['for'] ?? self::ALL_ENTITIES;
        if ($for !== self::ALL_ENTITIES && $for !== self::SPECIFIC_ENTITIES) {
            Mage::throwException(Mage::helper('widget')->__('Wrong widget display option specified.'));
        }

        if ($for === self::SPECIFIC_ENTITIES && !isset($this->_specificEntitiesLayoutHandles[$code])) {
            Mage::throwException(Mage::helper('widget')->__('Wrong widget display option specified.'));
        }

        if (!$this->isValidBlockReference($pageGroupData['block'] ?? '')
            || !$this->isValidTemplatePath($pageGroupData['template'] ?? '')
        ) {
            Mage::throwException(Mage::helper('widget')->__('Templatepath or block reference contain special characters.'));
        }
    }

    /**
     * Check that a block reference (a layout block name) has no characters that have a meaning in XML or HTML
     */
    public function isValidBlockReference(mixed $blockReference): bool
    {
        return $this->hasNoMarkupCharacters($blockReference);
    }

    /**
     * Check that a template path has no characters that have a meaning in XML or HTML
     *
     * The design package keeps the path inside the theme. This check does not do that.
     */
    public function isValidTemplatePath(mixed $templatePath): bool
    {
        return $this->hasNoMarkupCharacters($templatePath);
    }

    protected function hasNoMarkupCharacters(mixed $value): bool
    {
        return is_string($value) && $value === htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Check that a widget.xml of an enabled module declares the type of this instance
     *
     * Only a declared type can go on the storefront. An instance of another type, such as one that an
     * older release saved, can be loaded and deleted, but it cannot be saved and gets no layout update.
     */
    public function isTypeDeclared(): bool
    {
        return Mage::getSingleton('widget/widget')->isWidgetTypeDeclared($this->getType());
    }

    /**
     * Check that a stored layout update has the shape that generateLayoutUpdateXml() gives for the type of this instance
     *
     * That shape is one <reference> with one <block> of the type of this instance. The only children of the
     * block are <action method="setData"> elements, each with a <name> and a <value>.
     */
    public function isGeneratedLayoutUpdateXml(mixed $xml): bool
    {
        if (!is_string($xml)) {
            return false;
        }

        // generateLayoutUpdateXml() gives an empty update for an incomplete instance, and it adds nothing
        if (trim($xml) === '') {
            return true;
        }

        return $this->isTypeDeclared() && $this->getGeneratedLayoutUpdateBlockType($xml) === $this->getType();
    }

    /**
     * Give the reason to remove a stored layout update of this instance, or null to keep it
     *
     * @return self::LAYOUT_UPDATE_UNDECLARED_TYPE|self::LAYOUT_UPDATE_UNEXPECTED_XML|null
     */
    public function getLayoutUpdateRemovalReason(mixed $xml): ?string
    {
        if ($this->isGeneratedLayoutUpdateXml($xml)) {
            return null;
        }

        return $this->isTypeDeclared() ? self::LAYOUT_UPDATE_UNEXPECTED_XML : self::LAYOUT_UPDATE_UNDECLARED_TYPE;
    }

    /**
     * Return the block type of a layout update that has the shape of generateLayoutUpdateXml(), or null for other XML
     *
     * This method does not check the type. isGeneratedLayoutUpdateXml() compares it with the type of this instance.
     */
    public function getGeneratedLayoutUpdateBlockType(mixed $xml): ?string
    {
        if (!is_string($xml) || trim($xml) === '') {
            return null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML('<root>' . $xml . '</root>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            return null;
        }

        $references = $this->getChildElements($document->documentElement);
        if ($references === null || count($references) !== 1
            || !$this->isPlainElement($references[0], 'reference', ['name'], ['name'])
            || !$this->isValidBlockReference($references[0]->getAttribute('name'))
        ) {
            return null;
        }

        $blocks = $this->getChildElements($references[0]);
        if ($blocks === null || count($blocks) !== 1
            || !$this->isPlainElement($blocks[0], 'block', ['type', 'name', 'template'], ['type', 'name'])
            || ($blocks[0]->hasAttribute('template') && !$this->isValidTemplatePath($blocks[0]->getAttribute('template')))
        ) {
            return null;
        }

        $actions = $this->getChildElements($blocks[0]);
        if ($actions === null) {
            return null;
        }

        foreach ($actions as $action) {
            if (!$this->isPlainElement($action, 'action', ['method'], ['method'])
                || $action->getAttribute('method') !== 'setData'
            ) {
                return null;
            }

            $arguments = $this->getChildElements($action);
            if ($arguments === null || count($arguments) !== 2
                || !$this->isPlainElement($arguments[0], 'name', [])
                || !$this->isPlainElement($arguments[1], 'value', [])
                || $this->getChildElements($arguments[0]) !== []
                || $this->getChildElements($arguments[1]) !== []
                // generateLayoutUpdateXml() never sets the template, or a parameter without a name, through setData
                || in_array($arguments[0]->textContent, ['', 'template'], true)
            ) {
                return null;
            }
        }

        return $blocks[0]->getAttribute('type');
    }

    /**
     * Return the child elements of a node, or null when it has a child that is not an element, a text or a CDATA section
     *
     * Text is allowed only where it cannot hold markup: white space between elements, or the text of an
     * element that has no child elements.
     *
     * @return list<DOMElement>|null
     */
    protected function getChildElements(DOMElement $parent): ?array
    {
        $elements = [];
        $hasText = false;
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $elements[] = $child;
            } elseif ($child instanceof DOMText) {
                // DOMCdataSection extends DOMText
                $hasText = $hasText || trim($child->data) !== '';
            } else {
                return null;
            }
        }

        if ($hasText && $elements !== []) {
            return null;
        }

        return $elements;
    }

    /**
     * Check the name of an element, that it has no namespace, and that it has only the allowed and all the required attributes
     *
     * @param list<string> $allowedAttributes
     * @param list<string> $requiredAttributes
     */
    protected function isPlainElement(DOMElement $element, string $name, array $allowedAttributes, array $requiredAttributes = []): bool
    {
        if ($element->namespaceURI !== null || $element->nodeName !== $name) {
            return false;
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof DOMAttr
                || $attribute->namespaceURI !== null
                || !in_array($attribute->nodeName, $allowedAttributes, true)
            ) {
                return false;
            }
        }
        return array_all($requiredAttributes, fn($attribute) => $element->getAttribute($attribute) !== '');
    }

    /**
     * Escape a value for the text or a quoted attribute of layout update XML
     */
    protected function escapeXml(mixed $value): string
    {
        $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        // XML 1.0 does not allow control characters, not even escaped, so the layout would not load
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', "\u{FFFD}", $escaped);
    }

    protected function getLogLabel(): string
    {
        $id = $this->getId();
        return $id === null || $id === '' ? '(new)' : '#' . $id;
    }

    /**
     * Clean related cache types
     *
     * @return $this
     */
    protected function _cleanCache()
    {
        $types = Mage::getConfig()->getNode(self::XML_NODE_RELATED_CACHE);
        if ($types) {
            foreach (array_keys($types->asArray()) as $type) {
                Mage::app()->getCache()->cleanType($type);
            }
        }
        return $this;
    }

    /**
     * Clean related cache if instance contain layout updates
     */
    #[\Override]
    protected function _afterSave()
    {
        if ($this->dataHasChangedFor('page_groups') || $this->dataHasChangedFor('widget_parameters')) {
            $this->_cleanCache();
        }
        return parent::_afterSave();
    }

    /**
     * Clean related cache if instance contain layout updates
     */
    #[\Override]
    protected function _beforeDelete()
    {
        if ($this->getPageGroups()) {
            $this->_cleanCache();
        }
        return parent::_beforeDelete();
    }

    public function getPageGroups(): ?array
    {
        return $this->getData('page_groups');
    }

    public function setPageGroups(?array $value): static
    {
        return $this->setData('page_groups', $value);
    }

    public function getSortOrder(): ?int
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (int) $value;
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }

    public function setStoreIds(array|string|null $value): static
    {
        return $this->setData('store_ids', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function setWidgetParameters(array|string|null $value): static
    {
        return $this->setData('widget_parameters', $value);
    }

}
