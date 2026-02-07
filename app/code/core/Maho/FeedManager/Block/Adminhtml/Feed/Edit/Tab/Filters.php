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
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

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
        $visibilityOptions = $this->_getVisibilityOptions();

        // For new feeds with no conditions, add a default "price > 0" condition
        $isNewFeed = !$feed->getId();
        if ($isNewFeed && empty($existingGroups)) {
            $existingGroups = [
                ['conditions' => [['attribute' => 'price', 'operator' => 'gt', 'value' => '0']]],
            ];
        }

        $html = '<div id="cond-groups-container">';
        $html .= $this->_getStyles();

        // Help text in notice message box
        $html .= '<ul class="messages"><li class="notice-msg"><ul><li>' .
                 '<strong>' . $this->__('How it works:') . '</strong> ' .
                 $this->__('Create condition groups that products must match. All groups must pass (AND logic). Within each group, if ANY condition matches, the group passes (OR logic).') .
                 '</li></ul></li></ul>';

        $html .= '<div id="groups-container">';

        if (empty($existingGroups)) {
            $html .= '<div id="cond-empty-state" class="cond-empty">' .
                     $this->__('No conditions defined. All products will be included (subject to common exclusions above).') .
                     '</div>';
        } else {
            foreach ($existingGroups as $groupIndex => $group) {
                $html .= $this->_renderGroup($groupIndex, $group, $attributeData, $operators, $categories, $productTypes, $visibilityOptions, count($existingGroups) > 1);
            }
        }

        $html .= '</div>';

        // Add group button
        $html .= '<div class="cond-add-group">' .
                 '<button type="button" class="add" onclick="FMConditions.addGroup(); return false;">' .
                 '<span>' . $this->__('Add Condition Group') . '</span>' .
                 '</button>' .
                 '</div>';

        $html .= '</div>';

        // JavaScript
        $html .= $this->_getJavaScript($attributeData, $operators, $categories, $productTypes, $visibilityOptions, count($existingGroups));

        return $html;
    }

    /**
     * Render a single condition group
     */
    protected function _renderGroup(int $groupIndex, array $group, array $attributes, array $operators, array $categories, array $productTypes, array $visibilityOptions, bool $showAndLabel = false): string
    {
        $conditions = $group['conditions'] ?? [];

        $html = '';

        // AND separator between groups
        if ($showAndLabel && $groupIndex > 0) {
            $html .= '<div class="logic-sep logic-and">' .
                     '<span class="logic-label">' . $this->__('AND') . '</span>' .
                     '</div>';
        }

        $html .= '<div class="cond-group" id="cond-group-' . $groupIndex . '" data-group-index="' . $groupIndex . '">';

        // Group header
        $html .= '<div class="cond-group-header">' .
                 '<span class="cond-group-title">' . $this->__('Condition Group') . '</span>' .
                 '<button type="button" class="delete" onclick="FMConditions.removeGroup(' . $groupIndex . '); return false;" title="' . $this->__('Remove Group') . '">' .
                 '<span>&#x2715;</span></button>' .
                 '</div>';

        $html .= '<div class="cond-group-body">';

        // Conditions
        $html .= '<div class="cond-list" id="cond-list-' . $groupIndex . '">';

        if (empty($conditions)) {
            $html .= $this->_renderCondition($groupIndex, 0, '', 'eq', '', $attributes, $operators, $categories, $productTypes, $visibilityOptions, false);
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
                    $visibilityOptions,
                    $condIndex > 0,
                );
            }
        }

        $html .= '</div>';

        // Add condition button
        $html .= '<button type="button" onclick="FMConditions.addCondition(' . $groupIndex . '); return false;">' .
                 '<span>' . $this->__('Add OR Condition') . '</span>' .
                 '</button>';

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render a single condition row
     */
    protected function _renderCondition(int $groupIndex, int $condIndex, string $attribute, string $operator, string $value, array $attributeData, array $operators, array $categories, array $productTypes, array $visibilityOptions, bool $showOrLabel = false): string
    {
        $html = '';

        // OR separator
        if ($showOrLabel) {
            $html .= '<div class="logic-sep logic-or">' .
                     '<span class="logic-label">' . $this->__('OR') . '</span>' .
                     '</div>';
        }

        $html .= '<div class="cond-row" id="cond-row-' . $groupIndex . '-' . $condIndex . '">';

        // Attribute select with optgroups
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][attribute]" class="attr-select" onchange="FMConditions.onAttributeChange(this)">';
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
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][operator]" class="op-select" onchange="FMConditions.onOperatorChange(this)">';
        foreach ($operators as $code => $label) {
            $selected = ($operator === $code) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml($code) . '"' . $selected . '>' . $this->escapeHtml($label) . '</option>';
        }
        $html .= '</select>';

        // Determine special attribute states
        $isCategory = ($attribute === 'category_ids');
        $isProductType = ($attribute === 'type_id');
        $isVisibility = ($attribute === 'visibility');
        $isStock = ($attribute === 'is_in_stock');
        $hideValue = in_array($operator, ['null', 'notnull']);
        $isSingleCategory = $isCategory && in_array($operator, ['eq', 'neq']);
        $isSingleProductType = $isProductType && in_array($operator, ['eq', 'neq']);
        $isSingleVisibility = $isVisibility && in_array($operator, ['eq', 'neq']);

        // Stock availability select (shown when attribute is is_in_stock)
        $stockStyle = (!$isStock || $hideValue) ? ' style="display:none"' : '';
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][stock_value]" ' .
                 'class="stock-select"' . $stockStyle . ' onchange="FMConditions.onStockChange(this)">';
        $html .= '<option value="1"' . ($value === '1' ? ' selected' : '') . '>' . $this->__('In Stock') . '</option>';
        $html .= '<option value="0"' . ($value === '0' ? ' selected' : '') . '>' . $this->__('Out of Stock') . '</option>';
        $html .= '</select>';

        // Product type select (shown when attribute is type_id)
        $productTypeStyle = (!$isProductType || $hideValue) ? ' style="display:none"' : '';
        $selectedTypes = $isProductType ? array_map('trim', explode(',', $value)) : [];
        $multipleTypeAttr = $isSingleProductType ? '' : ' multiple="multiple"';
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][type_value]" ' .
                 'class="type-select"' . $productTypeStyle . $multipleTypeAttr . ' onchange="FMConditions.onTypeChange(this)">';
        foreach ($productTypes as $typeCode => $typeLabel) {
            $selected = in_array($typeCode, $selectedTypes) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml($typeCode) . '"' . $selected . '>' . $this->escapeHtml($typeLabel) . '</option>';
        }
        $html .= '</select>';

        // Visibility select (shown when attribute is visibility)
        $visibilityStyle = (!$isVisibility || $hideValue) ? ' style="display:none"' : '';
        $selectedVisibilities = $isVisibility ? array_map('trim', explode(',', $value)) : [];
        $multipleVisAttr = $isSingleVisibility ? '' : ' multiple="multiple"';
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][visibility_value]" ' .
                 'class="visibility-select"' . $visibilityStyle . $multipleVisAttr . ' onchange="FMConditions.onVisibilityChange(this)">';
        foreach ($visibilityOptions as $visCode => $visLabel) {
            $selected = in_array((string) $visCode, $selectedVisibilities) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml((string) $visCode) . '"' . $selected . '>' . $this->escapeHtml($visLabel) . '</option>';
        }
        $html .= '</select>';

        // Category selector container
        $categoryContainerStyle = (!$isCategory || $hideValue) ? ' style="display:none"' : '';
        $selectedCategories = $isCategory ? array_map('trim', explode(',', $value)) : [];

        $html .= '<div class="cond-category-wrap"' . $categoryContainerStyle . '>';

        // Search filter input
        $html .= '<input type="text" class="input-text" placeholder="' . $this->__('Filter categories...') . '" onkeyup="FMConditions.filterCategories(this)" />';

        // Category select (single or multi based on operator)
        $multipleAttr = $isSingleCategory ? '' : ' multiple="multiple"';
        $html .= '<select name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][category_value]" ' .
                 'class="category-select"' . $multipleAttr . ' onchange="FMConditions.onCategoryChange(this)">';
        foreach ($categories as $catId => $catLabel) {
            $selected = in_array((string) $catId, $selectedCategories) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml((string) $catId) . '"' . $selected . '>' . $this->escapeHtml($catLabel) . '</option>';
        }
        $html .= '</select>';

        $html .= '</div>';

        // Text value input (hidden for null/notnull operators and special attributes)
        $valueStyle = ($hideValue || $isCategory || $isStock || $isProductType || $isVisibility) ? ' style="display:none"' : '';
        $html .= '<input type="text" name="condition_groups[' . $groupIndex . '][conditions][' . $condIndex . '][value]" ' .
                 'class="input-text value-input" value="' . $this->escapeHtml($value) . '" ' .
                 'placeholder="' . $this->__('Value') . '"' . $valueStyle . ' />';

        // Remove button
        $html .= '<button type="button" class="delete" onclick="FMConditions.removeCondition(' . $groupIndex . ', ' . $condIndex . '); return false;" title="' . $this->__('Remove') . '">' .
                 '<span>&#x2715;</span></button>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get CSS styles - styles are now in feedmanager.css
     */
    protected function _getStyles(): string
    {
        return '';
    }

    /**
     * Get JavaScript for dynamic conditions
     */
    protected function _getJavaScript(array $attributeData, array $operators, array $categories, array $productTypes, array $visibilityOptions, int $groupCount): string
    {
        $attributeGroupsJson = Mage::helper('core')->jsonEncode($attributeData['groups']);
        $attributesFlatJson = Mage::helper('core')->jsonEncode($attributeData['flat']);
        $operatorsJson = Mage::helper('core')->jsonEncode($operators);
        $categoriesJson = Mage::helper('core')->jsonEncode($categories);
        $productTypesJson = Mage::helper('core')->jsonEncode($productTypes);
        $visibilityOptionsJson = Mage::helper('core')->jsonEncode($visibilityOptions);
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
            visibilityOptions: {$visibilityOptionsJson},
            specialAttributes: {$specialAttrs},

            init: function() {
                document.querySelectorAll('.cond-group').forEach(function(group) {
                    var gIdx = parseInt(group.dataset.groupIndex);
                    var conditions = group.querySelectorAll('.cond-row');
                    this.conditionIndexes[gIdx] = conditions.length;
                }.bind(this));
            },

            addGroup: function() {
                var container = document.getElementById('groups-container');
                var emptyState = document.getElementById('cond-empty-state');
                if (emptyState) emptyState.remove();
                var groupHtml = this.getGroupHtml(this.groupIndex);
                container.insertAdjacentHTML('beforeend', groupHtml);
                this.conditionIndexes[this.groupIndex] = 1;
                this.groupIndex++;
                this.updateAndSeparators();
            },

            removeGroup: function(groupIndex) {
                var group = document.getElementById('cond-group-' + groupIndex);
                if (group) {
                    var prev = group.previousElementSibling;
                    if (prev && prev.classList.contains('logic-and')) prev.remove();
                    group.remove();
                    delete this.conditionIndexes[groupIndex];
                    this.updateAndSeparators();
                    this.checkEmpty();
                }
            },

            addCondition: function(groupIndex) {
                var condList = document.getElementById('cond-list-' + groupIndex);
                if (!condList) return;
                var condIndex = this.conditionIndexes[groupIndex] || 0;
                var condHtml = this.getConditionHtml(groupIndex, condIndex, true);
                condList.insertAdjacentHTML('beforeend', condHtml);
                this.conditionIndexes[groupIndex] = condIndex + 1;
            },

            removeCondition: function(groupIndex, condIndex) {
                var cond = document.getElementById('cond-row-' + groupIndex + '-' + condIndex);
                if (cond) {
                    var prev = cond.previousElementSibling;
                    if (prev && prev.classList.contains('logic-or')) prev.remove();
                    cond.remove();
                    var condList = document.getElementById('cond-list-' + groupIndex);
                    if (condList && condList.querySelectorAll('.cond-row').length === 0) {
                        this.removeGroup(groupIndex);
                    }
                }
            },

            // Operators valid for each attribute type
            selectOperators: ['eq', 'neq', 'in', 'nin'],
            stockOperators: ['eq'],

            onAttributeChange: function(select) {
                var condition = select.closest('.cond-row');
                var attrValue = select.value;
                var isCategory = (attrValue === 'category_ids');
                var isProductType = (attrValue === 'type_id');
                var isVisibility = (attrValue === 'visibility');
                var isStock = (attrValue === 'is_in_stock');
                var opSelect = condition.querySelector('.op-select');

                // Filter operators based on attribute type
                this.filterOperators(opSelect, attrValue);

                var hideValue = ['null', 'notnull'].indexOf(opSelect.value) !== -1;

                // Show/hide appropriate inputs
                var categoryContainer = condition.querySelector('.cond-category-wrap');
                var typeSelect = condition.querySelector('.type-select');
                var visibilitySelect = condition.querySelector('.visibility-select');
                var stockSelect = condition.querySelector('.stock-select');
                var valueInput = condition.querySelector('.value-input');

                if (categoryContainer) categoryContainer.style.display = (isCategory && !hideValue) ? '' : 'none';
                if (typeSelect) typeSelect.style.display = (isProductType && !hideValue) ? '' : 'none';
                if (visibilitySelect) visibilitySelect.style.display = (isVisibility && !hideValue) ? '' : 'none';
                if (stockSelect) stockSelect.style.display = (isStock && !hideValue) ? '' : 'none';
                if (valueInput) valueInput.style.display = (isCategory || isProductType || isVisibility || isStock || hideValue) ? 'none' : '';

                // Sync value when switching to stock
                if (isStock && stockSelect) {
                    valueInput.value = stockSelect.value;
                }
                // Sync value when switching to product type
                if (isProductType && typeSelect) {
                    this.onTypeChange(typeSelect);
                }
                // Sync value when switching to visibility
                if (isVisibility && visibilitySelect) {
                    this.onVisibilityChange(visibilitySelect);
                }
            },

            filterOperators: function(opSelect, attrValue) {
                var validOps = null;
                if (attrValue === 'category_ids' || attrValue === 'type_id' || attrValue === 'visibility') {
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
                var condition = select.closest('.cond-row');
                var attrSelect = condition.querySelector('.attr-select');
                var attrValue = attrSelect ? attrSelect.value : '';
                var isCategory = (attrValue === 'category_ids');
                var isProductType = (attrValue === 'type_id');
                var isVisibility = (attrValue === 'visibility');
                var isStock = (attrValue === 'is_in_stock');
                var hideValue = ['null', 'notnull'].indexOf(select.value) !== -1;

                var categoryContainer = condition.querySelector('.cond-category-wrap');
                var typeSelect = condition.querySelector('.type-select');
                var visibilitySelect = condition.querySelector('.visibility-select');
                var stockSelect = condition.querySelector('.stock-select');
                var valueInput = condition.querySelector('.value-input');
                var categorySelect = condition.querySelector('.category-select');

                if (categoryContainer) categoryContainer.style.display = (isCategory && !hideValue) ? '' : 'none';
                if (typeSelect) typeSelect.style.display = (isProductType && !hideValue) ? '' : 'none';
                if (visibilitySelect) visibilitySelect.style.display = (isVisibility && !hideValue) ? '' : 'none';
                if (stockSelect) stockSelect.style.display = (isStock && !hideValue) ? '' : 'none';
                if (valueInput) valueInput.style.display = (isCategory || isProductType || isVisibility || isStock || hideValue) ? 'none' : '';

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

                // Update visibility select multiple attribute based on operator
                if (isVisibility && visibilitySelect) {
                    var isMulti = ['in', 'nin'].indexOf(select.value) !== -1;
                    if (isMulti && !visibilitySelect.hasAttribute('multiple')) {
                        visibilitySelect.setAttribute('multiple', 'multiple');
                    } else if (!isMulti && visibilitySelect.hasAttribute('multiple')) {
                        visibilitySelect.removeAttribute('multiple');
                        if (visibilitySelect.selectedOptions.length > 1) {
                            var firstVal = visibilitySelect.selectedOptions[0].value;
                            visibilitySelect.value = firstVal;
                        }
                    }
                    this.onVisibilityChange(visibilitySelect);
                }
            },

            onCategoryChange: function(select) {
                var condition = select.closest('.cond-row');
                var valueInput = condition.querySelector('.value-input');
                if (valueInput) {
                    var selected = Array.from(select.selectedOptions).map(function(opt) { return opt.value; });
                    valueInput.value = selected.join(',');
                }
            },

            onTypeChange: function(select) {
                var condition = select.closest('.cond-row');
                var valueInput = condition.querySelector('.value-input');
                if (valueInput) {
                    var selected = Array.from(select.selectedOptions).map(function(opt) { return opt.value; });
                    valueInput.value = selected.join(',');
                }
            },

            onStockChange: function(select) {
                var condition = select.closest('.cond-row');
                var valueInput = condition.querySelector('.value-input');
                if (valueInput) valueInput.value = select.value;
            },

            onVisibilityChange: function(select) {
                var condition = select.closest('.cond-row');
                var valueInput = condition.querySelector('.value-input');
                if (valueInput) {
                    var selected = Array.from(select.selectedOptions).map(function(opt) { return opt.value; });
                    valueInput.value = selected.join(',');
                }
            },

            filterCategories: function(input) {
                var container = input.closest('.cond-category-wrap');
                var select = container.querySelector('.category-select');
                var filter = input.value.toLowerCase();
                Array.from(select.options).forEach(function(opt) {
                    var text = opt.textContent.toLowerCase();
                    opt.classList.toggle('hidden', filter && text.indexOf(filter) === -1);
                });
            },

            updateAndSeparators: function() {
                var groups = document.querySelectorAll('.cond-group');
                groups.forEach(function(group, index) {
                    var prev = group.previousElementSibling;
                    if (index > 0 && (!prev || !prev.classList.contains('logic-and'))) {
                        var separator = document.createElement('div');
                        separator.className = 'logic-sep logic-and';
                        separator.innerHTML = '<span class="logic-label">AND</span>';
                        group.parentNode.insertBefore(separator, group);
                    } else if (index === 0 && prev && prev.classList.contains('logic-and')) {
                        prev.remove();
                    }
                });
            },

            checkEmpty: function() {
                var container = document.getElementById('groups-container');
                if (container.querySelectorAll('.cond-group').length === 0) {
                    container.innerHTML = '<div id="cond-empty-state" class="cond-empty">No conditions defined. All products will be included (subject to common exclusions above).</div>';
                }
            },

            getGroupHtml: function(groupIndex) {
                return '<div class="cond-group" id="cond-group-' + groupIndex + '" data-group-index="' + groupIndex + '">' +
                    '<div class="cond-group-header"><span class="cond-group-title">Condition Group</span>' +
                    '<button type="button" class="delete" onclick="FMConditions.removeGroup(' + groupIndex + '); return false;" title="Remove Group"><span>&#x2715;</span></button></div>' +
                    '<div class="cond-group-body"><div class="cond-list" id="cond-list-' + groupIndex + '">' +
                    this.getConditionHtml(groupIndex, 0, false) +
                    '</div><button type="button" onclick="FMConditions.addCondition(' + groupIndex + '); return false;">' +
                    '<span>Add OR Condition</span></button></div></div>';
            },

            getConditionHtml: function(groupIndex, condIndex, showOr) {
                var html = '';
                if (showOr) {
                    html += '<div class="logic-sep logic-or"><span class="logic-label">OR</span></div>';
                }

                html += '<div class="cond-row" id="cond-row-' + groupIndex + '-' + condIndex + '">';

                // Attribute select with optgroups
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][attribute]" class="attr-select" onchange="FMConditions.onAttributeChange(this)">';
                html += '<option value="">-- Select Attribute --</option>';
                for (var groupLabel in this.attributeGroups) {
                    html += '<optgroup label="' + escapeHtml(groupLabel, true) + '">';
                    for (var code in this.attributeGroups[groupLabel]) {
                        html += '<option value="' + escapeHtml(code, true) + '">' + escapeHtml(this.attributeGroups[groupLabel][code]) + '</option>';
                    }
                    html += '</optgroup>';
                }
                html += '</select>';

                // Operator select
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][operator]" class="op-select" onchange="FMConditions.onOperatorChange(this)">';
                for (var op in this.operators) {
                    html += '<option value="' + escapeHtml(op, true) + '">' + escapeHtml(this.operators[op]) + '</option>';
                }
                html += '</select>';

                // Stock select (hidden by default via CSS)
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][stock_value]" class="stock-select" onchange="FMConditions.onStockChange(this)">';
                html += '<option value="1">In Stock</option><option value="0">Out of Stock</option></select>';

                // Product type select (hidden by default via CSS)
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][type_value]" class="type-select" onchange="FMConditions.onTypeChange(this)">';
                for (var typeCode in this.productTypes) {
                    html += '<option value="' + escapeHtml(typeCode, true) + '">' + escapeHtml(this.productTypes[typeCode]) + '</option>';
                }
                html += '</select>';

                // Visibility select (hidden by default via CSS)
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][visibility_value]" class="visibility-select" onchange="FMConditions.onVisibilityChange(this)">';
                for (var visCode in this.visibilityOptions) {
                    html += '<option value="' + escapeHtml(visCode, true) + '">' + escapeHtml(this.visibilityOptions[visCode]) + '</option>';
                }
                html += '</select>';

                // Category container (hidden by default via CSS)
                html += '<div class="cond-category-wrap">';
                html += '<input type="text" class="input-text" placeholder="Filter categories..." onkeyup="FMConditions.filterCategories(this)" />';
                html += '<select name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][category_value]" class="category-select" multiple="multiple" onchange="FMConditions.onCategoryChange(this)">';
                for (var catId in this.categories) {
                    html += '<option value="' + escapeHtml(catId, true) + '">' + escapeHtml(this.categories[catId]) + '</option>';
                }
                html += '</select></div>';

                // Value input
                html += '<input type="text" name="condition_groups[' + groupIndex + '][conditions][' + condIndex + '][value]" class="input-text value-input" placeholder="Value" />';

                // Remove button
                html += '<button type="button" class="delete" onclick="FMConditions.removeCondition(' + groupIndex + ', ' + condIndex + '); return false;" title="Remove"><span>&#x2715;</span></button>';

                html += '</div>';

                return html;
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
            'visibility' => $this->__('Visibility'),
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
        return ['category_ids', 'type_id', 'visibility', 'qty', 'is_in_stock'];
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
     * Get visibility options
     */
    protected function _getVisibilityOptions(): array
    {
        return [
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE => $this->__('Not Visible Individually'),
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG => $this->__('Catalog'),
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH => $this->__('Search'),
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH => $this->__('Catalog, Search'),
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
}
