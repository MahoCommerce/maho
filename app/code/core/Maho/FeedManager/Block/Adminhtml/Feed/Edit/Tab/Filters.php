<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Filters extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $feed = $this->_getFeed();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('feed_');

        // Common exclusions fieldset
        $exclusionsFieldset = $form->addFieldset('exclusions_fieldset', [
            'legend' => $this->__('Common Exclusions'),
        ]);

        $exclusionsFieldset->addField('exclude_disabled', 'select', [
            'name' => 'exclude_disabled',
            'label' => $this->__('Exclude Disabled Products'),
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
            'value' => $feed->getData('exclude_disabled') ?? 1,
            'note' => $this->__('Exclude products with status "Disabled" from the feed.'),
        ]);

        $exclusionsFieldset->addField('exclude_out_of_stock', 'select', [
            'name' => 'exclude_out_of_stock',
            'label' => $this->__('Exclude Out of Stock Products'),
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
            'value' => $feed->getData('exclude_out_of_stock') ?? 1,
            'note' => $this->__('Exclude products that are out of stock from the feed.'),
        ]);

        $exclusionsFieldset->addField('include_product_types', 'multiselect', [
            'name' => 'include_product_types',
            'label' => $this->__('Product Types'),
            'values' => $this->_getProductTypeOptionsForForm(),
            'value' => $feed->getData('include_product_types') ? explode(',', $feed->getData('include_product_types')) : ['simple'],
            'note' => $this->__('Select which product types to include in the feed. Leave empty for all types.'),
        ]);

        // Condition Groups fieldset (AND/OR logic)
        $rulesFieldset = $form->addFieldset('condition_groups_fieldset', [
            'legend' => $this->__('Product Conditions'),
        ]);

        $rulesFieldset->addField('condition_groups_note', 'note', [
            'label' => '',
            'text' => $this->_getConditionGroupsHtml(),
        ]);

        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * Build the AND/OR condition groups HTML
     */
    protected function _getConditionGroupsHtml(): string
    {
        $feed = $this->_getFeed();
        $existingGroups = $feed->getConditionGroupsArray();
        $attributeData = $this->_getProductAttributes();
        $operators = $this->_getOperators();
        $categories = $this->_getCategoryOptions();
        $productTypes = $this->_getProductTypeOptions();

        // For new feeds with no conditions, add a default "price > 0" condition
        $isNewFeed = !$feed->getId();
        if ($isNewFeed && empty($existingGroups)) {
            $existingGroups = [
                ['conditions' => [['attribute' => 'price', 'operator' => 'gt', 'value' => '0']]],
            ];
        }

        $html = '<div id="fm-condition-groups-container">';
        $html .= $this->_getStyles();

        // Help text
        $html .= '<div class="fm-help-text">' .
                 '<strong>' . $this->__('How it works:') . '</strong> ' .
                 $this->__('Create condition groups that products must match. All groups must pass (AND logic). Within each group, if ANY condition matches, the group passes (OR logic).') .
                 '</div>';

        $html .= '<div id="fm-groups-container">';

        if (empty($existingGroups)) {
            $html .= '<div id="fm-empty-state" class="fm-empty-state">' .
                     $this->__('No conditions defined. All products will be included (subject to common exclusions above).') .
                     '</div>';
        } else {
            foreach ($existingGroups as $groupIndex => $group) {
                $html .= $this->_renderGroup($groupIndex, $group, $attributeData, $operators, $categories, $productTypes, count($existingGroups) > 1);
            }
        }

        $html .= '</div>';

        // Add group button
        $html .= '<div class="fm-add-group-container">' .
                 '<button type="button" class="fm-btn fm-btn-primary" onclick="FMConditions.addGroup(); return false;">' .
                 '<span class="fm-btn-icon">+</span> ' . $this->__('Add Condition Group') .
                 '</button>' .
                 '</div>';

        $html .= '</div>';

        // JavaScript
        $html .= $this->_getJavaScript($attributeData, $operators, $categories, $productTypes, count($existingGroups));

        return $html;
    }

    /**
     * Render a single condition group
     */
    protected function _renderGroup(int $groupIndex, array $group, array $attributes, array $operators, array $categories, array $productTypes, bool $showAndLabel = false): string
    {
        $conditions = $group['conditions'] ?? [];

        $html = '';

        // AND separator between groups
        if ($showAndLabel && $groupIndex > 0) {
            $html .= '<div class="fm-logic-separator fm-and-separator">' .
                     '<span class="fm-logic-label">' . $this->__('AND') . '</span>' .
                     '</div>';
        }

        $html .= '<div class="fm-group" id="fm-group-' . $groupIndex . '" data-group-index="' . $groupIndex . '">';

        // Group header
        $html .= '<div class="fm-group-header">' .
                 '<span class="fm-group-title">' . $this->__('Condition Group') . '</span>' .
                 '<button type="button" class="fm-btn-icon-only fm-btn-danger" onclick="FMConditions.removeGroup(' . $groupIndex . '); return false;" title="' . $this->__('Remove Group') . '">' .
                 '&#x2715;</button>' .
                 '</div>';

        $html .= '<div class="fm-group-body">';

        // Conditions
        $html .= '<div class="fm-conditions-list" id="fm-conditions-' . $groupIndex . '">';

        if (empty($conditions)) {
            $html .= $this->_renderCondition($groupIndex, 0, '', 'eq', '', $attributes, $operators, $categories, $productTypes, false);
        } else {
            foreach ($conditions as $condIndex => $condition) {
                $html .= $this->_renderCondition(
                    $groupIndex,
                    $condIndex,
                    $condition['attribute'] ?? '',
                    $condition['operator'] ?? 'eq',
                    $condition['value'] ?? '',
                    $attributes,
                    $operators,
                    $categories,
                    $productTypes,
                    $condIndex > 0,
                );
            }
        }

        $html .= '</div>';

        // Add condition button
        $html .= '<button type="button" class="fm-btn fm-btn-small" onclick="FMConditions.addCondition(' . $groupIndex . '); return false;">' .
                 '<span class="fm-btn-icon">+</span> ' . $this->__('Add OR Condition') .
                 '</button>';

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render a single condition row
     */
    protected function _renderCondition(int $groupIndex, int $condIndex, string $attribute, string $operator, string $value, array $attributeData, array $operators, array $categories, array $productTypes, bool $showOrLabel = false): string
    {
        $html = '';

        // OR separator
        if ($showOrLabel) {
            $html .= '<div class="fm-logic-separator fm-or-separator">' .
                     '<span class="fm-logic-label fm-or-label">' . $this->__('OR') . '</span>' .
                     '</div>';
        }

        $html .= '<div class="fm-condition" id="fm-cond-' . $groupIndex . '-' . $condIndex . '">';

        // Attribute select with optgroups
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][attribute]" class="fm-select fm-attr-select" onchange="FMConditions.onAttributeChange(this)">';
        $html .= '<option value="">' . $this->__('-- Select Attribute --') . '</option>';
        foreach ($attributeData['groups'] as $groupLabel => $groupAttrs) {
            $html .= '<optgroup label="' . $this->escapeHtml($groupLabel) . '">';
            foreach ($groupAttrs as $code => $label) {
                $selected = ($attribute === $code) ? ' selected' : '';
                $html .= '<option value="' . $this->escapeHtml($code) . '"' . $selected . '>' . $this->escapeHtml($label) . '</option>';
            }
            $html .= '</optgroup>';
        }
        $html .= '</select>';

        // Operator select
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][operator]" class="fm-select fm-op-select" onchange="FMConditions.onOperatorChange(this)">';
        foreach ($operators as $code => $label) {
            $selected = ($operator === $code) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml($code) . '"' . $selected . '>' . $this->escapeHtml($label) . '</option>';
        }
        $html .= '</select>';

        // Determine special attribute states
        $isCategory = ($attribute === 'category_ids');
        $isProductType = ($attribute === 'type_id');
        $isStock = ($attribute === 'is_in_stock');
        $hideValue = in_array($operator, ['null', 'notnull']);
        $isSingleCategory = $isCategory && in_array($operator, ['eq', 'neq']);
        $isSingleProductType = $isProductType && in_array($operator, ['eq', 'neq']);

        // Stock availability select (shown when attribute is is_in_stock)
        $stockStyle = (!$isStock || $hideValue) ? ' style="display:none"' : '';
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][stock_value]" ' .
                 'class="fm-select fm-stock-select"' . $stockStyle . ' onchange="FMConditions.onStockChange(this)">';
        $html .= '<option value="1"' . ($value === '1' ? ' selected' : '') . '>' . $this->__('In Stock') . '</option>';
        $html .= '<option value="0"' . ($value === '0' ? ' selected' : '') . '>' . $this->__('Out of Stock') . '</option>';
        $html .= '</select>';

        // Product type select (shown when attribute is type_id)
        $productTypeStyle = (!$isProductType || $hideValue) ? ' style="display:none"' : '';
        $selectedTypes = $isProductType ? array_map('trim', explode(',', $value)) : [];
        $multipleTypeAttr = $isSingleProductType ? '' : ' multiple="multiple"';
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][type_value]" ' .
                 'class="fm-select fm-type-select"' . $productTypeStyle . $multipleTypeAttr . ' onchange="FMConditions.onTypeChange(this)">';
        foreach ($productTypes as $typeCode => $typeLabel) {
            $selected = in_array($typeCode, $selectedTypes) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml($typeCode) . '"' . $selected . '>' . $this->escapeHtml($typeLabel) . '</option>';
        }
        $html .= '</select>';

        // Category selector container
        $categoryContainerStyle = (!$isCategory || $hideValue) ? ' style="display:none"' : '';
        $selectedCategories = $isCategory ? array_map('trim', explode(',', $value)) : [];

        $html .= '<div class="fm-category-container"' . $categoryContainerStyle . '>';

        // Search filter input
        $html .= '<input type="text" class="fm-input fm-category-search" placeholder="' . $this->__('Filter categories...') . '" onkeyup="FMConditions.filterCategories(this)" />';

        // Category select (single or multi based on operator)
        $multipleAttr = $isSingleCategory ? '' : ' multiple="multiple"';
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][category_value]" ' .
                 'class="fm-select fm-category-select"' . $multipleAttr . ' onchange="FMConditions.onCategoryChange(this)">';
        foreach ($categories as $catId => $catLabel) {
            $selected = in_array((string) $catId, $selectedCategories) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml((string) $catId) . '"' . $selected . '>' . $this->escapeHtml($catLabel) . '</option>';
        }
        $html .= '</select>';

        $html .= '</div>';

        // Text value input (hidden for null/notnull operators and special attributes)
        $valueStyle = ($hideValue || $isCategory || $isStock || $isProductType) ? ' style="display:none"' : '';
        $html .= '<input type="text" name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][value]" ' .
                 'class="fm-input fm-value-input" value="' . $this->escapeHtml($value) . '" ' .
                 'placeholder="' . $this->__('Value') . '"' . $valueStyle . ' />';

        // Remove button
        $html .= '<button type="button" class="fm-btn-icon-only" onclick="FMConditions.removeCondition(' . $groupIndex . ', ' . $condIndex . '); return false;" title="' . $this->__('Remove') . '">' .
                 '&#x2715;</button>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get CSS styles
     */
    protected function _getStyles(): string
    {
        return '<style>
            /* Hide empty label and make value span full width for condition groups */
            #feed_condition_groups_fieldset .form-list td.label:has(label[for="feed_condition_groups_note"]) { display: none; }
            #feed_condition_groups_fieldset .form-list td.value { width: 100%; }
            .fm-help-text {
                padding: 12px 16px;
                margin-bottom: 16px;
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                border-radius: 6px;
                font-size: 13px;
                color: #0369a1;
                line-height: 1.5;
            }
            .fm-empty-state {
                padding: 32px;
                text-align: center;
                color: #9ca3af;
                font-size: 13px;
                background: #f8fafc;
                border: 1px dashed #d1d5db;
                border-radius: 6px;
            }
            .fm-group {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                margin-bottom: 0;
            }
            .fm-group-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 16px;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
                border-radius: 8px 8px 0 0;
            }
            .fm-group-title {
                font-weight: 600;
                font-size: 13px;
                color: #374151;
            }
            .fm-group-body {
                padding: 16px;
            }
            .fm-conditions-list {
                margin-bottom: 12px;
            }
            .fm-condition {
                display: flex;
                gap: 8px;
                align-items: center;
                margin-bottom: 8px;
            }
            .fm-condition:last-child {
                margin-bottom: 0;
            }
            .fm-select {
                padding: 8px 12px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 13px;
                background: #fff;
                height: 36px;
            }
            .fm-input {
                padding: 8px 12px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 13px;
                background: #fff;
                height: 36px;
                box-sizing: border-box;
            }
            .fm-select:focus, .fm-input:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
            }
            .fm-attr-select { width: 220px; }
            .fm-op-select { width: 150px; }
            .fm-stock-select { width: 140px; }
            .fm-type-select { width: 200px; min-height: auto; }
            .fm-type-select[multiple] { min-height: 100px; height: auto; }
            .fm-value-input { flex: 1; min-width: 150px; }
            .fm-category-container {
                display: flex;
                flex-direction: column;
                gap: 4px;
                flex: 1;
                min-width: 250px;
            }
            .fm-category-search {
                height: 32px;
                font-size: 12px;
                padding: 6px 10px;
            }
            .fm-category-select {
                width: 100%;
                min-height: 120px;
                height: auto;
            }
            .fm-category-select:not([multiple]) {
                min-height: auto;
                height: 36px;
            }
            .fm-category-select option.fm-hidden {
                display: none;
            }
            .fm-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                background: #fff;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 500;
                color: #374151;
                cursor: pointer;
                white-space: nowrap;
            }
            .fm-btn:hover {
                background: #f8fafc;
                border-color: #9ca3af;
            }
            .fm-btn-primary {
                background: #3b82f6;
                border-color: #3b82f6;
                color: #fff;
            }
            .fm-btn-primary:hover {
                background: #2563eb;
                border-color: #2563eb;
            }
            .fm-btn-small {
                padding: 6px 12px;
                font-size: 12px;
            }
            .fm-btn-icon {
                font-size: 14px;
                line-height: 1;
            }
            .fm-btn-icon-only {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                padding: 0;
                background: #fff;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                cursor: pointer;
                color: #6b7280;
                font-size: 14px;
            }
            .fm-btn-icon-only:hover {
                background: #f3f4f6;
                border-color: #9ca3af;
            }
            .fm-btn-danger:hover {
                background: #fef2f2;
                border-color: #ef4444;
                color: #ef4444;
            }
            .fm-logic-separator {
                display: flex;
                align-items: center;
                margin: 12px 0;
            }
            .fm-logic-separator::before,
            .fm-logic-separator::after {
                content: "";
                flex: 1;
                height: 1px;
                background: #e2e8f0;
            }
            .fm-logic-label {
                padding: 4px 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-radius: 4px;
            }
            .fm-and-separator .fm-logic-label {
                background: #dbeafe;
                color: #1d4ed8;
            }
            .fm-or-separator {
                margin: 8px 0;
            }
            .fm-or-separator::before,
            .fm-or-separator::after {
                background: #f3e8ff;
            }
            .fm-or-label {
                background: #f3e8ff;
                color: #7c3aed;
            }
            .fm-add-group-container {
                margin-top: 16px;
            }
        </style>';
    }

    /**
     * Get JavaScript for dynamic conditions
     */
    protected function _getJavaScript(array $attributeData, array $operators, array $categories, array $productTypes, int $groupCount): string
    {
        $attributeGroupsJson = Mage::helper('core')->jsonEncode($attributeData['groups']);
        $attributesFlatJson = Mage::helper('core')->jsonEncode($attributeData['flat']);
        $operatorsJson = Mage::helper('core')->jsonEncode($operators);
        $categoriesJson = Mage::helper('core')->jsonEncode($categories);
        $productTypesJson = Mage::helper('core')->jsonEncode($productTypes);
        $specialAttrs = Mage::helper('core')->jsonEncode($this->_getSpecialAttributes());

        return <<<SCRIPT
        <script type="text/javascript">
        var FMConditions = {
            groupIndex: {$groupCount},
            conditionIndexes: {},
            attributeGroups: {$attributeGroupsJson},
            attributes: {$attributesFlatJson},
            operators: {$operatorsJson},
            categories: {$categoriesJson},
            productTypes: {$productTypesJson},
            specialAttributes: {$specialAttrs},

            init: function() {
                document.querySelectorAll('.fm-group').forEach(function(group) {
                    var gIdx = parseInt(group.dataset.groupIndex);
                    var conditions = group.querySelectorAll('.fm-condition');
                    this.conditionIndexes[gIdx] = conditions.length;
                }.bind(this));
            },

            addGroup: function() {
                var container = document.getElementById('fm-groups-container');
                var emptyState = document.getElementById('fm-empty-state');
                if (emptyState) emptyState.remove();
                var groupHtml = this.getGroupHtml(this.groupIndex);
                container.insertAdjacentHTML('beforeend', groupHtml);
                this.conditionIndexes[this.groupIndex] = 1;
                this.groupIndex++;
                this.updateAndSeparators();
            },

            removeGroup: function(groupIndex) {
                var group = document.getElementById('fm-group-' + groupIndex);
                if (group) {
                    var prev = group.previousElementSibling;
                    if (prev && prev.classList.contains('fm-and-separator')) prev.remove();
                    group.remove();
                    delete this.conditionIndexes[groupIndex];
                    this.updateAndSeparators();
                    this.checkEmpty();
                }
            },

            addCondition: function(groupIndex) {
                var condList = document.getElementById('fm-conditions-' + groupIndex);
                if (!condList) return;
                var condIndex = this.conditionIndexes[groupIndex] || 0;
                var condHtml = this.getConditionHtml(groupIndex, condIndex, true);
                condList.insertAdjacentHTML('beforeend', condHtml);
                this.conditionIndexes[groupIndex] = condIndex + 1;
            },

            removeCondition: function(groupIndex, condIndex) {
                var cond = document.getElementById('fm-cond-' + groupIndex + '-' + condIndex);
                if (cond) {
                    var prev = cond.previousElementSibling;
                    if (prev && prev.classList.contains('fm-or-separator')) prev.remove();
                    cond.remove();
                    var condList = document.getElementById('fm-conditions-' + groupIndex);
                    if (condList && condList.querySelectorAll('.fm-condition').length === 0) {
                        this.removeGroup(groupIndex);
                    }
                }
            },

            // Operators valid for each attribute type
            selectOperators: ['eq', 'neq', 'in', 'nin'],
            stockOperators: ['eq'],

            onAttributeChange: function(select) {
                var condition = select.closest('.fm-condition');
                var attrValue = select.value;
                var isCategory = (attrValue === 'category_ids');
                var isProductType = (attrValue === 'type_id');
                var isStock = (attrValue === 'is_in_stock');
                var opSelect = condition.querySelector('.fm-op-select');

                // Filter operators based on attribute type
                this.filterOperators(opSelect, attrValue);

                var hideValue = ['null', 'notnull'].indexOf(opSelect.value) !== -1;

                // Show/hide appropriate inputs
                var categoryContainer = condition.querySelector('.fm-category-container');
                var typeSelect = condition.querySelector('.fm-type-select');
                var stockSelect = condition.querySelector('.fm-stock-select');
                var valueInput = condition.querySelector('.fm-value-input');

                if (categoryContainer) categoryContainer.style.display = (isCategory && !hideValue) ? '' : 'none';
                if (typeSelect) typeSelect.style.display = (isProductType && !hideValue) ? '' : 'none';
                if (stockSelect) stockSelect.style.display = (isStock && !hideValue) ? '' : 'none';
                if (valueInput) valueInput.style.display = (isCategory || isProductType || isStock || hideValue) ? 'none' : '';

                // Sync value when switching to stock
                if (isStock && stockSelect) {
                    valueInput.value = stockSelect.value;
                }
                // Sync value when switching to product type
                if (isProductType && typeSelect) {
                    this.onTypeChange(typeSelect);
                }
            },

            filterOperators: function(opSelect, attrValue) {
                var validOps = null;
                if (attrValue === 'category_ids' || attrValue === 'type_id') {
                    validOps = this.selectOperators;
                } else if (attrValue === 'is_in_stock') {
                    validOps = this.stockOperators;
                }

                var currentValue = opSelect.value;
                var needsReset = false;

                Array.from(opSelect.options).forEach(function(opt) {
                    if (validOps === null) {
                        opt.style.display = '';
                        opt.disabled = false;
                    } else {
                        var isValid = validOps.indexOf(opt.value) !== -1;
                        opt.style.display = isValid ? '' : 'none';
                        opt.disabled = !isValid;
                        if (!isValid && opt.value === currentValue) {
                            needsReset = true;
                        }
                    }
                });

                // Reset to first valid operator if current is invalid
                if (needsReset && validOps && validOps.length > 0) {
                    opSelect.value = validOps[0];
                    this.onOperatorChange(opSelect);
                }
            },

            onOperatorChange: function(select) {
                var condition = select.closest('.fm-condition');
                var attrSelect = condition.querySelector('.fm-attr-select');
                var attrValue = attrSelect ? attrSelect.value : '';
                var isCategory = (attrValue === 'category_ids');
                var isProductType = (attrValue === 'type_id');
                var isStock = (attrValue === 'is_in_stock');
                var hideValue = ['null', 'notnull'].indexOf(select.value) !== -1;

                var categoryContainer = condition.querySelector('.fm-category-container');
                var typeSelect = condition.querySelector('.fm-type-select');
                var stockSelect = condition.querySelector('.fm-stock-select');
                var valueInput = condition.querySelector('.fm-value-input');
                var categorySelect = condition.querySelector('.fm-category-select');

                if (categoryContainer) categoryContainer.style.display = (isCategory && !hideValue) ? '' : 'none';
                if (typeSelect) typeSelect.style.display = (isProductType && !hideValue) ? '' : 'none';
                if (stockSelect) stockSelect.style.display = (isStock && !hideValue) ? '' : 'none';
                if (valueInput) valueInput.style.display = (isCategory || isProductType || isStock || hideValue) ? 'none' : '';

                // Update category select multiple attribute based on operator
                if (isCategory && categorySelect) {
                    var isMulti = ['in', 'nin'].indexOf(select.value) !== -1;
                    if (isMulti && !categorySelect.hasAttribute('multiple')) {
                        categorySelect.setAttribute('multiple', 'multiple');
                    } else if (!isMulti && categorySelect.hasAttribute('multiple')) {
                        categorySelect.removeAttribute('multiple');
                        if (categorySelect.selectedOptions.length > 1) {
                            var firstVal = categorySelect.selectedOptions[0].value;
                            categorySelect.value = firstVal;
                        }
                    }
                    this.onCategoryChange(categorySelect);
                }

                // Update type select multiple attribute based on operator
                if (isProductType && typeSelect) {
                    var isMulti = ['in', 'nin'].indexOf(select.value) !== -1;
                    if (isMulti && !typeSelect.hasAttribute('multiple')) {
                        typeSelect.setAttribute('multiple', 'multiple');
                    } else if (!isMulti && typeSelect.hasAttribute('multiple')) {
                        typeSelect.removeAttribute('multiple');
                        if (typeSelect.selectedOptions.length > 1) {
                            var firstVal = typeSelect.selectedOptions[0].value;
                            typeSelect.value = firstVal;
                        }
                    }
                    this.onTypeChange(typeSelect);
                }
            },

            onCategoryChange: function(select) {
                var condition = select.closest('.fm-condition');
                var valueInput = condition.querySelector('.fm-value-input');
                if (valueInput) {
                    var selected = Array.from(select.selectedOptions).map(function(opt) { return opt.value; });
                    valueInput.value = selected.join(',');
                }
            },

            onTypeChange: function(select) {
                var condition = select.closest('.fm-condition');
                var valueInput = condition.querySelector('.fm-value-input');
                if (valueInput) {
                    var selected = Array.from(select.selectedOptions).map(function(opt) { return opt.value; });
                    valueInput.value = selected.join(',');
                }
            },

            onStockChange: function(select) {
                var condition = select.closest('.fm-condition');
                var valueInput = condition.querySelector('.fm-value-input');
                if (valueInput) valueInput.value = select.value;
            },

            filterCategories: function(input) {
                var container = input.closest('.fm-category-container');
                var select = container.querySelector('.fm-category-select');
                var filter = input.value.toLowerCase();
                Array.from(select.options).forEach(function(opt) {
                    var text = opt.textContent.toLowerCase();
                    opt.classList.toggle('fm-hidden', filter && text.indexOf(filter) === -1);
                });
            },

            updateAndSeparators: function() {
                var groups = document.querySelectorAll('.fm-group');
                groups.forEach(function(group, index) {
                    var prev = group.previousElementSibling;
                    if (index > 0 && (!prev || !prev.classList.contains('fm-and-separator'))) {
                        var separator = document.createElement('div');
                        separator.className = 'fm-logic-separator fm-and-separator';
                        separator.innerHTML = '<span class="fm-logic-label">AND</span>';
                        group.parentNode.insertBefore(separator, group);
                    } else if (index === 0 && prev && prev.classList.contains('fm-and-separator')) {
                        prev.remove();
                    }
                });
            },

            checkEmpty: function() {
                var container = document.getElementById('fm-groups-container');
                if (container.querySelectorAll('.fm-group').length === 0) {
                    container.innerHTML = '<div id="fm-empty-state" class="fm-empty-state">No conditions defined. All products will be included (subject to common exclusions above).</div>';
                }
            },

            getGroupHtml: function(groupIndex) {
                return '<div class="fm-group" id="fm-group-' + groupIndex + '" data-group-index="' + groupIndex + '">' +
                    '<div class="fm-group-header"><span class="fm-group-title">Condition Group</span>' +
                    '<button type="button" class="fm-btn-icon-only fm-btn-danger" onclick="FMConditions.removeGroup(' + groupIndex + '); return false;" title="Remove Group">&#x2715;</button></div>' +
                    '<div class="fm-group-body"><div class="fm-conditions-list" id="fm-conditions-' + groupIndex + '">' +
                    this.getConditionHtml(groupIndex, 0, false) +
                    '</div><button type="button" class="fm-btn fm-btn-small" onclick="FMConditions.addCondition(' + groupIndex + '); return false;">' +
                    '<span class="fm-btn-icon">+</span> Add OR Condition</button></div></div>';
            },

            getConditionHtml: function(groupIndex, condIndex, showOr) {
                var html = '';
                if (showOr) {
                    html += '<div class="fm-logic-separator fm-or-separator"><span class="fm-logic-label fm-or-label">OR</span></div>';
                }

                html += '<div class="fm-condition" id="fm-cond-' + groupIndex + '-' + condIndex + '">';

                // Attribute select with optgroups
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][attribute]" class="fm-select fm-attr-select" onchange="FMConditions.onAttributeChange(this)">';
                html += '<option value="">-- Select Attribute --</option>';
                for (var groupLabel in this.attributeGroups) {
                    html += '<optgroup label="' + this.escapeHtml(groupLabel) + '">';
                    for (var code in this.attributeGroups[groupLabel]) {
                        html += '<option value="' + this.escapeHtml(code) + '">' + this.escapeHtml(this.attributeGroups[groupLabel][code]) + '</option>';
                    }
                    html += '</optgroup>';
                }
                html += '</select>';

                // Operator select
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][operator]" class="fm-select fm-op-select" onchange="FMConditions.onOperatorChange(this)">';
                for (var op in this.operators) {
                    html += '<option value="' + this.escapeHtml(op) + '">' + this.escapeHtml(this.operators[op]) + '</option>';
                }
                html += '</select>';

                // Stock select (hidden by default)
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][stock_value]" class="fm-select fm-stock-select" style="display:none" onchange="FMConditions.onStockChange(this)">';
                html += '<option value="1">In Stock</option><option value="0">Out of Stock</option></select>';

                // Product type select (hidden by default)
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][type_value]" class="fm-select fm-type-select" style="display:none" onchange="FMConditions.onTypeChange(this)">';
                for (var typeCode in this.productTypes) {
                    html += '<option value="' + this.escapeHtml(typeCode) + '">' + this.escapeHtml(this.productTypes[typeCode]) + '</option>';
                }
                html += '</select>';

                // Category container (hidden by default)
                html += '<div class="fm-category-container" style="display:none">';
                html += '<input type="text" class="fm-input fm-category-search" placeholder="Filter categories..." onkeyup="FMConditions.filterCategories(this)" />';
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][category_value]" class="fm-select fm-category-select" multiple="multiple" onchange="FMConditions.onCategoryChange(this)">';
                for (var catId in this.categories) {
                    html += '<option value="' + this.escapeHtml(catId) + '">' + this.escapeHtml(this.categories[catId]) + '</option>';
                }
                html += '</select></div>';

                // Value input
                html += '<input type="text" name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][value]" class="fm-input fm-value-input" placeholder="Value" />';

                // Remove button
                html += '<button type="button" class="fm-btn-icon-only" onclick="FMConditions.removeCondition(' + groupIndex + ', ' + condIndex + '); return false;" title="Remove">&#x2715;</button>';

                html += '</div>';

                return html;
            },

            escapeHtml: function(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            FMConditions.init();
        });
        </script>
SCRIPT;
    }

    /**
     * Get filterable product attributes grouped by type
     *
     * Returns array with 'groups' for optgroups and 'flat' for JS
     */
    protected function _getProductAttributes(): array
    {
        // Special attributes with custom handling
        $specialAttributes = [
            'category_ids' => $this->__('Category'),
            'type_id' => $this->__('Product Type'),
            'qty' => $this->__('Quantity'),
            'is_in_stock' => $this->__('Stock Availability'),
        ];

        // Common product attributes
        $productAttributes = [
            'sku' => $this->__('SKU'),
            'name' => $this->__('Product Name'),
            'price' => $this->__('Price'),
            'special_price' => $this->__('Special Price'),
            'cost' => $this->__('Cost'),
            'weight' => $this->__('Weight'),
            'attribute_set_id' => $this->__('Attribute Set'),
            'visibility' => $this->__('Visibility'),
            'created_at' => $this->__('Created Date'),
            'updated_at' => $this->__('Updated Date'),
        ];

        // Get custom product attributes from EAV
        $eavAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        foreach ($eavAttributes as $attribute) {
            $code = $attribute->getAttributeCode();
            $label = $attribute->getFrontendLabel();
            // Skip if already defined or if it's status (handled by exclude_disabled)
            if ($label && !isset($productAttributes[$code]) && !isset($specialAttributes[$code]) && !in_array($code, ['status'])) {
                $productAttributes[$code] = $label;
            }
        }

        return [
            'groups' => [
                $this->__('Special Attributes') => $specialAttributes,
                $this->__('Product Attributes') => $productAttributes,
            ],
            'flat' => array_merge($specialAttributes, $productAttributes),
        ];
    }

    /**
     * Get special attribute codes that require custom handling
     */
    protected function _getSpecialAttributes(): array
    {
        return ['category_ids', 'type_id', 'qty', 'is_in_stock'];
    }

    /**
     * Get product type options
     */
    protected function _getProductTypeOptions(): array
    {
        return [
            'simple' => $this->__('Simple Product'),
            'configurable' => $this->__('Configurable Product'),
            'grouped' => $this->__('Grouped Product'),
            'bundle' => $this->__('Bundle Product'),
            'virtual' => $this->__('Virtual Product'),
            'downloadable' => $this->__('Downloadable Product'),
        ];
    }

    /**
     * Get product type options formatted for form multiselect
     */
    protected function _getProductTypeOptionsForForm(): array
    {
        $options = [];
        foreach ($this->_getProductTypeOptions() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    /**
     * Get available operators
     */
    protected function _getOperators(): array
    {
        return [
            'eq' => $this->__('Equals'),
            'neq' => $this->__('Not Equals'),
            'gt' => $this->__('Greater Than'),
            'gteq' => $this->__('Greater or Equal'),
            'lt' => $this->__('Less Than'),
            'lteq' => $this->__('Less or Equal'),
            'in' => $this->__('Is One Of'),
            'nin' => $this->__('Is Not One Of'),
            'like' => $this->__('Contains'),
            'nlike' => $this->__('Does Not Contain'),
            'null' => $this->__('Is Empty'),
            'notnull' => $this->__('Is Not Empty'),
        ];
    }

    /**
     * Get category options as flat list with hierarchy prefix
     */
    protected function _getCategoryOptions(): array
    {
        $options = [];
        $rootCategoryId = Mage::app()->getStore()->getRootCategoryId();
        if (!$rootCategoryId) {
            $rootCategoryId = 2; // Default root category
        }

        $categories = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('path', ['like' => '1/' . $rootCategoryId . '/%'])
            ->setOrder('path', 'ASC');

        foreach ($categories as $category) {
            $level = $category->getLevel() - 2; // Adjust for root/default
            $prefix = str_repeat('— ', max(0, $level));
            $options[$category->getId()] = $prefix . $category->getName();
        }

        return $options;
    }

    protected function _getFeed(): Maho_FeedManager_Model_Feed
    {
        return Mage::registry('current_feed') ?: Mage::getModel('feedmanager/feed');
    }
}
