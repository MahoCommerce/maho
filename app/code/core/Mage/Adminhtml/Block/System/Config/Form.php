<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2016-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Config_Form extends Mage_Adminhtml_Block_Widget_Form
{
    public const SCOPE_DEFAULT = 'default';
    public const SCOPE_WEBSITES = 'websites';
    public const SCOPE_STORES   = 'stores';

    /**
     * Config data array
     *
     * @var array
     */
    protected $_configData;

    /**
     * Adminhtml config data instance
     *
     * @var Mage_Adminhtml_Model_Config_Data
     */
    protected $_configDataObject;

    /**
     * @var \Maho\Simplexml\Element
     */
    protected $_configRoot;

    /**
     * @var Mage_Adminhtml_Model_Config
     */
    protected $_configFields;

    /**
     * @var Mage_Adminhtml_Block_System_Config_Form_Fieldset|false
     */
    protected $_defaultFieldsetRenderer;

    /**
     * @var Mage_Adminhtml_Block_System_Config_Form_Field|false
     */
    protected $_defaultFieldRenderer;

    /**
     * @var array
     */
    protected $_fieldsets = [];

    /**
     * Translated scope labels
     *
     * @var array
     */
    protected $_scopeLabels = [];

    public function __construct()
    {
        parent::__construct();
        $this->_scopeLabels = [
            self::SCOPE_DEFAULT  => Mage::helper('adminhtml')->__('[GLOBAL]'),
            self::SCOPE_WEBSITES => Mage::helper('adminhtml')->__('[WEBSITE]'),
            self::SCOPE_STORES   => Mage::helper('adminhtml')->__('[STORE VIEW]'),
        ];
    }

    /**
     * @return $this
     */
    protected function _initObjects()
    {
        $this->_configDataObject = Mage::getSingleton('adminhtml/config_data');
        $this->_configRoot = $this->_configDataObject->getConfigRoot();
        $this->_configData = $this->_configDataObject->load();

        $this->_configFields = Mage::getSingleton('adminhtml/config');

        $this->_defaultFieldsetRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_fieldset');
        $this->_defaultFieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        return $this;
    }

    /**
     * @return $this
     */
    public function initForm()
    {
        $this->_initObjects();

        $form = new \Maho\Data\Form();

        $sections = $this->_configFields->getSection(
            $this->getSectionCode(),
            $this->getWebsiteCode(),
            $this->getStoreCode(),
        );
        if (empty($sections)) {
            $sections = [];
        }
        foreach ($sections as $section) {
            /** @var \Maho\Simplexml\Element $section */
            if (!$this->_canShowField($section)) {
                continue;
            }
            foreach ($section->groups as $groups) {
                $groups = (array) $groups;
                usort($groups, [$this, '_sortForm']);

                foreach ($groups as $group) {
                    if (!$this->_canShowField($group)) {
                        continue;
                    }
                    $this->_initGroup($form, $group, $section);
                }
            }
        }

        $this->setForm($form);
        return $this;
    }

    /**
     * Init config group
     *
     * @param \Maho\Data\Form $form
     * @param \Maho\Simplexml\Element $group
     * @param \Maho\Simplexml\Element $section
     * @param \Maho\Data\Form\Element\Fieldset|null $parentElement
     */
    protected function _initGroup($form, $group, $section, $parentElement = null)
    {
        /** @var Mage_Adminhtml_Block_System_Config_Form_Fieldset $fieldsetRenderer */
        $fieldsetRenderer = $group->frontend_model
            ? Mage::getBlockSingleton((string) $group->frontend_model)
            : $this->_defaultFieldsetRenderer;
        $fieldsetRenderer->setForm($this)
            ->setConfigData($this->_configData);

        if ($this->_configFields->hasChildren($group, $this->getWebsiteCode(), $this->getStoreCode())) {
            $helperName = $this->_configFields->getAttributeModule($section, $group);
            $fieldsetConfig = ['legend' => Mage::helper($helperName)->__((string) $group->label)];
            if (!empty($group->comment)) {
                $fieldsetConfig['comment'] = $this->_prepareGroupComment($group, $helperName);
            }
            if (!empty($group->expanded)) {
                $fieldsetConfig['expanded'] = (bool) $group->expanded;
            }

            $fieldset = new \Maho\Data\Form\Element\Fieldset($fieldsetConfig);
            $fieldset->setId($section->getName() . '_' . $group->getName())
                ->setRenderer($fieldsetRenderer)
                ->setGroup($group);

            if ($parentElement) {
                $fieldset->setIsNested(true);
                $parentElement->addElement($fieldset);
            } else {
                $form->addElement($fieldset);
            }

            $this->_prepareFieldOriginalData($fieldset, $group);
            $this->_addElementTypes($fieldset);

            $this->_fieldsets[$group->getName()] = $fieldset;

            if ($group->clone_fields) {
                if ($group->clone_model) {
                    $cloneModel = Mage::getModel((string) $group->clone_model);
                } else {
                    Mage::throwException($this->__('Config form fieldset clone model required to be able to clone fields'));
                }
                foreach ($cloneModel->getPrefixes() as $prefix) {
                    $this->initFields($fieldset, $group, $section, $prefix['field'], $prefix['label']);
                }
            } else {
                $this->initFields($fieldset, $group, $section);
            }
        }
    }

    /**
     * Return dependency block object
     *
     * @return Mage_Adminhtml_Block_Widget_Form_Element_Dependence
     */
    protected function _getDependence()
    {
        if (!$this->getChild('element_dependense')) {
            $this->setChild(
                'element_dependense',
                $this->getLayout()->createBlock('adminhtml/widget_form_element_dependence'),
            );
        }
        return $this->getChild('element_dependense');
    }

    /**
     * Build the dependence array while resolving field names and checking element visibility
     *
     * @param \Maho\Simplexml\Element $node
     * @param \Maho\Simplexml\Element $group
     * @param \Maho\Simplexml\Element $section
     * @param string $fieldPrefix
     * @return array
     */
    protected function _buildDependenceCondition($node, $group, $section, $fieldPrefix = '')
    {
        $block = $this->_getDependence();

        // If we have a logical operator, recurse
        if (str_starts_with($node->getName(), 'condition') && isset($node['operator'])) {
            $operator = strtoupper((string) $node['operator']);
            if ($block->isLogicalOperator($operator)) {
                $conditions = [];
                foreach ($node->children() as $child) {
                    [$fieldId, $condition] = $this->_buildDependenceCondition($child, $group, $section, $fieldPrefix);
                    if ($block->isLogicalOperator($fieldId)) {
                        $conditions[] = $block->createCondition($fieldId, $condition);
                    } else {
                        $conditions[$fieldId] = $condition;
                    }
                }
                return [$operator, $conditions];
            }
            Mage::throwException($this->__("Invalid operator '%s', must be one of NOT, AND, OR, XOR", $operator));
        }

        // Conditions may reference fields in other groups by specifying a <fieldset> node
        if (isset($this->_fieldsets[(string) $node->fieldset])) {
            $fieldGroup = $this->_fieldsets[(string) $node->fieldset]->getGroup();
        } else {
            $fieldGroup = $group;
        }

        // Build the field's full path and DOM ID
        $fieldName = $fieldPrefix . $node->getName();
        $fieldPath = [
            $section->getName(),
            $fieldGroup->getName(),
            $fieldName,
        ];
        $fieldId = implode('_', $fieldPath);

        // Get the wanted value for the condition, can be multiple values if a separator attribute is provided
        $condition = (string) ($node->value ?? $node);
        if (isset($node['separator'])) {
            $condition = explode($node['separator'], $condition);
        }

        // If the field isn't shown in current scope, provide its value to the dependence block so conditions work properly
        if (!$this->_canShowField($fieldGroup->fields->$fieldName)) {
            $fieldConfigPath = implode('/', $fieldPath);
            $fieldConfigValue = Mage::getStoreConfig($fieldConfigPath, $this->getStoreCode());
            if ($fieldConfigValue) {
                $block->addFieldValue($fieldId, $fieldConfigValue);
            }
        }

        return [$fieldId, $condition];
    }

    /**
     * Init fieldset fields
     *
     * @param \Maho\Data\Form\Element\Fieldset $fieldset
     * @param \Maho\Simplexml\Element $group
     * @param \Maho\Simplexml\Element $section
     * @param string $fieldPrefix
     * @param string $labelPrefix
     * @throw Mage_Core_Exception
     * @return $this
     */
    public function initFields($fieldset, $group, $section, $fieldPrefix = '', $labelPrefix = '')
    {
        if (!$this->_configDataObject) {
            $this->_initObjects();
        }

        // Extends for config data
        $configDataAdditionalGroups = [];

        foreach ($group->fields as $elements) {
            $elements = (array) $elements;
            // sort either by sort_order or by child node values bypassing the sort_order
            if ($group->sort_fields && $group->sort_fields->by) {
                $fieldset->setSortElementsByAttribute(
                    (string) $group->sort_fields->by,
                    $group->sort_fields->direction_desc ? SORT_DESC : SORT_ASC,
                );
            } else {
                usort($elements, [$this, '_sortForm']);
            }

            foreach ($elements as $element) {
                if (!$this->_canShowField($element)) {
                    continue;
                }

                if ((string) $element->getAttribute('type') === 'group') {
                    $this->_initGroup($fieldset->getForm(), $element, $section, $fieldset);
                    continue;
                }

                /**
                 * Look for custom defined field path
                 */
                $path = (string) $element->config_path;
                if (empty($path)) {
                    $path = $section->getName() . '/' . $group->getName() . '/' . $fieldPrefix . $element->getName();
                } elseif (strrpos($path, '/') > 0) {
                    // Extend config data with new section group
                    $groupPath = substr($path, 0, strrpos($path, '/'));
                    if (!isset($configDataAdditionalGroups[$groupPath])) {
                        $this->_configData = $this->_configDataObject->extendConfig(
                            $groupPath,
                            false,
                            $this->_configData,
                        );
                        $configDataAdditionalGroups[$groupPath] = true;
                    }
                }

                $data = $this->_configDataObject->getConfigDataValue($path, $inherit, $this->_configData);
                /** @var Mage_Adminhtml_Block_System_Config_Form_Field $fieldRenderer */
                $fieldRenderer = $element->frontend_model
                    ? Mage::getBlockSingleton((string) $element->frontend_model)
                    : $this->_defaultFieldRenderer;

                $fieldRenderer->setForm($this);
                $fieldRenderer->setConfigData($this->_configData);

                $helperName = $this->_configFields->getAttributeModule($section, $group, $element);
                $fieldType  = (string) $element->frontend_type ?: 'text';
                $name  = 'groups[' . $group->getName() . '][fields][' . $fieldPrefix . $element->getName() . '][value]';
                $label =  Mage::helper($helperName)->__($labelPrefix) . ' '
                    . Mage::helper($helperName)->__((string) $element->label);
                $helper = Mage::helper('adminhtml/config');
                $backendClass = $helper->getBackendModelByFieldConfig($element);
                if ($backendClass) {
                    $model = Mage::getModel($backendClass);
                    if (!$model instanceof Mage_Core_Model_Config_Data) {
                        Mage::throwException('Invalid config field backend model: ' . (string) $element->backend_model);
                    }
                    $model->setPath($path)
                        ->setValue($data)
                        ->setWebsite($this->getWebsiteCode())
                        ->setStore($this->getStoreCode())
                        ->afterLoad();
                    $data = $model->getValue();
                }

                $comment = $this->_prepareFieldComment($element, $helperName, $data);
                $tooltip = $this->_prepareFieldTooltip($element, $helperName);
                $id = $section->getName() . '_' . $group->getName() . '_' . $fieldPrefix . $element->getName();

                if ($element->depends) {
                    $dependenceBlock = $this->_getDependence();
                    foreach ($element->depends->children() as $child) {
                        $result = $this->_buildDependenceCondition($child, $group, $section, $fieldPrefix);
                        if ($dependenceBlock->isLogicalOperator($result[0])) {
                            $dependenceBlock->addComplexFieldDependence($id, $result[0], $result[1]);
                        } else {
                            $dependenceBlock->addFieldDependence($id, $result[0], $result[1]);
                        }
                    }
                }
                $sharedClass = '';
                if ($element->shared && $element->config_path) {
                    $sharedClass = ' shared shared-' . str_replace('/', '-', $element->config_path);
                }

                $requiresClass = '';
                if ($element->requires) {
                    $requiresClass = ' requires';
                    foreach (explode(',', $element->requires) as $groupName) {
                        $requiresClass .= ' requires-' . $section->getName() . '_' . $groupName;
                    }
                }

                $field = $fieldset->addField($id, $fieldType, [
                    'name'                  => $name,
                    'label'                 => $label,
                    'comment'               => $comment,
                    'tooltip'               => $tooltip,
                    'value'                 => $data,
                    'inherit'               => $inherit,
                    'class'                 => $element->frontend_class . $sharedClass . $requiresClass,
                    'field_config'          => $element,
                    'scope'                 => $this->getScope(),
                    'scope_id'              => $this->getScopeId(),
                    'scope_label'           => $this->getScopeLabel($element),
                    'can_use_default_value' => $this->canUseDefaultValue((int) $element->show_in_default),
                    'can_use_website_value' => $this->canUseWebsiteValue((int) $element->show_in_website),
                ]);
                $this->_prepareFieldOriginalData($field, $element);

                if (isset($element->validate)) {
                    $field->addClass($element->validate);
                }

                if (isset($element->frontend_type)
                    && (string) $element->frontend_type === 'multiselect'
                    && isset($element->can_be_empty)
                ) {
                    $field->setCanBeEmpty(true);
                }

                $field->setRenderer($fieldRenderer);

                if ($element->source_model) {
                    // determine callback for the source model
                    $factoryName = (string) $element->source_model;
                    $method = false;
                    if (preg_match('/^([^:]+?)::([^:]+?)$/', $factoryName, $matches)) {
                        array_shift($matches);
                        [$factoryName, $method] = array_values($matches);
                    }

                    $sourceModel = Mage::getSingleton($factoryName);
                    if (!$sourceModel) {
                        Mage::throwException("Source model '{$factoryName}' is not found");
                    }
                    if ($sourceModel instanceof \Maho\DataObject) {
                        $sourceModel->setPath($path);
                    }

                    $optionArray = [];
                    if ($method) {
                        if ($fieldType == 'multiselect') {
                            $optionArray = $sourceModel->$method();
                        } else {
                            foreach ($sourceModel->$method() as $value => $label) {
                                $optionArray[] = ['label' => $label, 'value' => $value];
                            }
                        }
                    } else {
                        if (method_exists($sourceModel, 'toOptionArray')) {
                            $optionArray = $sourceModel->toOptionArray($fieldType == 'multiselect');
                        } else {
                            Mage::throwException("Missing method 'toOptionArray()' in source model '{$factoryName}'");
                        }
                    }

                    $field->setValues($optionArray);
                }
            }
        }
        return $this;
    }

    /**
     * Return config root node for current scope
     *
     * @return \Maho\Simplexml\Element
     */
    public function getConfigRoot()
    {
        if (empty($this->_configRoot)) {
            $this->_configRoot = Mage::getSingleton('adminhtml/config_data')->getConfigRoot();
        }
        return $this->_configRoot;
    }

    /**
     * Set "original_data" array to the element, composed from nodes with scalar values
     *
     * @param \Maho\Data\Form\Element\AbstractElement $field
     * @param \Maho\Simplexml\Element $xmlElement
     */
    protected function _prepareFieldOriginalData($field, $xmlElement)
    {
        $originalData = [];
        foreach ($xmlElement as $key => $value) {
            if (!$value->hasChildren()) {
                $originalData[$key] = (string) $value;
            }
        }
        $field->setOriginalData($originalData);
    }

    /**
     * Support models "getCommentText" method for field note generation
     *
     * @param Mage_Core_Model_Config_Element $element
     * @param string $helper
     * @return string
     */
    protected function _prepareFieldComment($element, $helper, $currentValue)
    {
        $comment = '';
        if ($element->comment) {
            $commentInfo = $element->comment->asArray();
            if (is_array($commentInfo)) {
                if (isset($commentInfo['model'])) {
                    $model = Mage::getModel($commentInfo['model']);
                    if ($model && method_exists($model, 'getCommentText')) {
                        $comment = $model->getCommentText($element, $currentValue);
                    }
                }
            } else {
                $comment = Mage::helper($helper)->__($commentInfo);
            }
        }
        return $comment;
    }

    /**
     * Support models "getCommentText" method for group note generation
     *
     * @param Mage_Core_Model_Config_Element $element
     * @param string $helper
     * @return string
     */
    protected function _prepareGroupComment($element, $helper)
    {
        return $this->_prepareFieldComment($element, $helper, null);
    }

    /**
     * Prepare additional comment for field like tooltip
     *
     * @param Mage_Core_Model_Config_Element $element
     * @param string $helper
     * @return string
     */
    protected function _prepareFieldTooltip($element, $helper)
    {
        if ($element->tooltip) {
            return Mage::helper($helper)->__((string) $element->tooltip);
        }
        if ($element->tooltip_block) {
            return $this->getLayout()->createBlock((string) $element->tooltip_block)->toHtml();
        }
        return '';
    }

    /**
     * Append dependence block at then end of form block
     */
    #[\Override]
    protected function _afterToHtml($html)
    {
        if ($this->_getDependence()) {
            $html .= $this->_getDependence()->toHtml();
        }
        $html = parent::_afterToHtml($html);
        return $html;
    }

    /**
     * @param \Maho\Simplexml\Element $a
     * @param \Maho\Simplexml\Element $b
     * @return int
     */
    protected function _sortForm($a, $b)
    {
        return (int) $a->sort_order <=> (int) $b->sort_order;
    }

    /**
     * @param \Maho\Simplexml\Element $field
     * @return bool
     */
    public function canUseDefaultValue($field)
    {
        if ($this->getScope() == self::SCOPE_STORES && $field) {
            return true;
        }
        if ($this->getScope() == self::SCOPE_WEBSITES && $field) {
            return true;
        }
        return false;
    }

    /**
     * @param \Maho\Simplexml\Element $field
     * @return bool
     */
    public function canUseWebsiteValue($field)
    {
        if ($this->getScope() == self::SCOPE_STORES && $field) {
            return true;
        }
        return false;
    }

    /**
     * Checking field visibility
     *
     * @param \Maho\Simplexml\Element $field
     * @return  bool
     */
    protected function _canShowField($field)
    {
        $ifModuleEnabled = trim((string) $field->if_module_enabled);
        if ($ifModuleEnabled && !$this->isModuleEnabled($ifModuleEnabled)) {
            return false;
        }
        return match ($this->getScope()) {
            self::SCOPE_DEFAULT => (bool) (int) $field->show_in_default,
            self::SCOPE_WEBSITES => (bool) (int) $field->show_in_website,
            self::SCOPE_STORES => (bool) (int) $field->show_in_store,
            default => true,
        };
    }

    /**
     * Retrieve current scope
     *
     * @return string
     */
    public function getScope()
    {
        $scope = $this->getData('scope');
        if (is_null($scope)) {
            if ($this->getStoreCode()) {
                $scope = self::SCOPE_STORES;
            } elseif ($this->getWebsiteCode()) {
                $scope = self::SCOPE_WEBSITES;
            } else {
                $scope = self::SCOPE_DEFAULT;
            }
            $this->setScope($scope);
        }

        return $scope;
    }

    /**
     * Retrieve label for scope
     *
     * @param Mage_Core_Model_Config_Element $element
     * @return string
     */
    public function getScopeLabel($element)
    {
        if ((int) $element->show_in_store === 1) {
            return $this->_scopeLabels[self::SCOPE_STORES];
        }
        if ((int) $element->show_in_website === 1) {
            return $this->_scopeLabels[self::SCOPE_WEBSITES];
        }
        return $this->_scopeLabels[self::SCOPE_DEFAULT];
    }

    /**
     * Get current scope code
     *
     * @return string
     */
    public function getScopeCode()
    {
        $scopeCode = $this->getData('scope_code');
        if (is_null($scopeCode)) {
            if ($this->getStoreCode()) {
                $scopeCode = $this->getStoreCode();
            } elseif ($this->getWebsiteCode()) {
                $scopeCode = $this->getWebsiteCode();
            } else {
                $scopeCode = '';
            }
            $this->setScopeCode($scopeCode);
        }

        return $scopeCode;
    }

    /**
     * Get current scope code
     *
     * @return int|string
     */
    public function getScopeId()
    {
        $scopeId = $this->getData('scope_id');
        if (is_null($scopeId)) {
            if ($this->getStoreCode()) {
                $scopeId = Mage::app()->getStore($this->getStoreCode())->getId();
            } elseif ($this->getWebsiteCode()) {
                $scopeId = Mage::app()->getWebsite($this->getWebsiteCode())->getId();
            } else {
                $scopeId = '';
            }
            $this->setScopeId($scopeId);
        }
        return $scopeId;
    }

    /**
     * @return array
     */
    #[\Override]
    protected function _getAdditionalElementTypes()
    {
        return [
            'export'        => Mage::getConfig()->getBlockClassName('adminhtml/system_config_form_field_export'),
            'import'        => Mage::getConfig()->getBlockClassName('adminhtml/system_config_form_field_import'),
            'allowspecific' => Mage::getConfig()
                ->getBlockClassName('adminhtml/system_config_form_field_select_allowspecific'),
            'image'         => Mage::getConfig()->getBlockClassName('adminhtml/system_config_form_field_image'),
            'file'          => Mage::getConfig()->getBlockClassName('adminhtml/system_config_form_field_file'),
        ];
    }

    /**
     * Temporary moved those $this->getRequest()->getParam('blabla') from the code across this block
     * to getBlala() methods to be later set from controller with setters
     */
    /**
     * @TODO delete this methods when {^see above^} is done
     * @return string
     */
    public function getSectionCode()
    {
        return $this->getRequest()->getParam('section', '');
    }

    /**
     * @TODO delete this methods when {^see above^} is done
     * @return string
     */
    public function getWebsiteCode()
    {
        return $this->getRequest()->getParam('website', '');
    }

    /**
     * @TODO delete this methods when {^see above^} is done
     * @return string
     */
    public function getStoreCode()
    {
        return $this->getRequest()->getParam('store', '');
    }
}
