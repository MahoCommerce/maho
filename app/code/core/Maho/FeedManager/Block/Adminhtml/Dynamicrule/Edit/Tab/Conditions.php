<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Rule Conditions Tab - Rule Builder UI
 *
 * Provides a visual interface for building dynamic rule conditions and outputs.
 * Rules are structured as "output rows" that are evaluated top-to-bottom (OR logic).
 * Within each row, conditions are AND'd together.
 */
class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Edit_Tab_Conditions extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $rule = $this->_getRule();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('rule_');

        $fieldset = $form->addFieldset('conditions_fieldset', [
            'legend' => $this->__('Output Rules'),
        ]);

        // Hidden field to store rule_data JSON
        $fieldset->addField('rule_data', 'hidden', [
            'name' => 'rule_data',
        ]);

        // Rule builder UI as a note field
        $fieldset->addField('conditions_builder', 'note', [
            'label' => '',
            'text' => $this->_getRuleBuilderHtml(),
        ]);

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Build the rule builder HTML
     */
    protected function _getRuleBuilderHtml(): string
    {
        $rule = $this->_getRule();
        $outputRows = $rule->getOutputRows();
        $attributes = $this->_getProductAttributes();
        $operators = $this->_getOperators();

        // For new rules with no output rows, add a default empty row
        if (empty($outputRows)) {
            $outputRows = [
                ['conditions' => [], 'output_type' => 'static', 'output_value' => ''],
            ];
        }

        $html = '<div id="drule-builder-container">';
        $html .= $this->_getStyles();

        // Help text
        $html .= '<ul class="messages"><li class="notice-msg"><ul><li>' .
                 '<strong>' . $this->__('How it works:') . '</strong> ' .
                 $this->__('Rules are evaluated top to bottom. The first row where ALL conditions match determines the output. Add a row with no conditions at the end as a default/fallback.') .
                 '</li></ul></li></ul>';

        $html .= '<div id="drule-rows-container">';

        foreach ($outputRows as $rowIndex => $row) {
            $html .= $this->_renderOutputRow($rowIndex, $row, $attributes, $operators, $rowIndex > 0);
        }

        $html .= '</div>';

        // Add row button
        $html .= '<div class="drule-add-row">' .
                 '<button type="button" class="add" onclick="DRuleBuilder.addOutputRow(); return false;">' .
                 '<span>' . $this->__('Add Output Row') . '</span>' .
                 '</button>' .
                 '</div>';

        $html .= '</div>';

        // JavaScript
        $html .= $this->_getJavaScript($attributes, $operators, count($outputRows));

        return $html;
    }

    /**
     * Render a single output row
     */
    protected function _renderOutputRow(int $rowIndex, array $row, array $attributes, array $operators, bool $showOrLabel = false): string
    {
        $conditions = $row['conditions'] ?? [];
        $outputType = $row['output_type'] ?? Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_STATIC;
        $outputValue = $row['output_value'] ?? '';
        $outputAttribute = $row['output_attribute'] ?? '';

        $html = '';

        // OR separator between rows
        if ($showOrLabel) {
            $html .= '<div class="logic-sep logic-or">' .
                     '<span class="logic-label">' . $this->__('OR') . '</span>' .
                     '</div>';
        }

        $html .= '<div class="drule-output-row" id="drule-row-' . $rowIndex . '" data-row-index="' . $rowIndex . '">';

        // Row header with delete button
        $html .= '<div class="drule-row-header">' .
                 '<span class="drule-row-title">' . $this->__('Output Row') . '</span>' .
                 '<button type="button" class="delete" onclick="DRuleBuilder.removeOutputRow(' . $rowIndex . '); return false;" title="' . $this->__('Remove Row') . '">' .
                 '<span>&#x2715;</span></button>' .
                 '</div>';

        $html .= '<div class="drule-row-body">';

        // Conditions section
        $html .= '<div class="drule-conditions-section">';

        if (empty($conditions)) {
            $html .= '<div class="drule-no-conditions">' .
                     '<span class="drule-else-label">' . $this->__('ELSE (default - no conditions)') . '</span>' .
                     '</div>';
        } else {
            $html .= '<div class="drule-if-label">' . $this->__('IF') . '</div>';
            $html .= '<div class="drule-conditions-list" id="drule-conditions-' . $rowIndex . '">';

            foreach ($conditions as $condIndex => $condition) {
                $html .= $this->_renderCondition(
                    $rowIndex,
                    $condIndex,
                    $condition['attribute'] ?? '',
                    $condition['operator'] ?? 'eq',
                    $condition['value'] ?? '',
                    $attributes,
                    $operators,
                    $condIndex > 0,
                );
            }

            $html .= '</div>';
        }

        // Add condition button
        $html .= '<button type="button" class="drule-add-condition" onclick="DRuleBuilder.addCondition(' . $rowIndex . '); return false;">' .
                 '<span>' . $this->__('+ Add AND Condition') . '</span>' .
                 '</button>';

        $html .= '</div>'; // End conditions section

        // Output section
        $html .= '<div class="drule-output-section">';
        $html .= '<div class="drule-then-label">' . $this->__('THEN Output:') . '</div>';

        $html .= '<div class="drule-output-config">';

        // Output type select
        $html .= '<select class="drule-output-type" onchange="DRuleBuilder.onOutputTypeChange(this, ' . $rowIndex . ')">';
        $html .= '<option value="static"' . ($outputType === 'static' ? ' selected' : '') . '>' . $this->__('Static Value') . '</option>';
        $html .= '<option value="attribute"' . ($outputType === 'attribute' ? ' selected' : '') . '>' . $this->__('Product Attribute') . '</option>';
        $html .= '<option value="combined"' . ($outputType === 'combined' ? ' selected' : '') . '>' . $this->__('Combined (Prefix + Attribute)') . '</option>';
        $html .= '</select>';

        // Static value input
        $staticStyle = ($outputType !== 'static' && $outputType !== 'combined') ? ' style="display:none"' : '';
        $staticLabel = $outputType === 'combined' ? $this->__('Prefix:') : $this->__('Value:');
        $html .= '<label class="drule-static-label"' . $staticStyle . '>' . $staticLabel . '</label>';
        $html .= '<input type="text" class="input-text drule-output-value" value="' . $this->escapeHtml($outputValue) . '"' . $staticStyle . ' />';

        // Attribute select
        $attrStyle = ($outputType !== 'attribute' && $outputType !== 'combined') ? ' style="display:none"' : '';
        $html .= '<label class="drule-attr-label"' . $attrStyle . '>' . $this->__('Attribute:') . '</label>';
        $html .= '<select class="drule-output-attribute"' . $attrStyle . '>';
        $html .= '<option value="">' . $this->__('-- Select Attribute --') . '</option>';
        foreach ($attributes['flat'] as $code => $label) {
            $selected = ($outputAttribute === $code) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml($code) . '"' . $selected . '>' . $this->escapeHtml($label) . '</option>';
        }
        $html .= '</select>';

        $html .= '</div>'; // End output config

        $html .= '</div>'; // End output section

        $html .= '</div>'; // End row body
        $html .= '</div>'; // End output row

        return $html;
    }

    /**
     * Render a single condition row within an output row
     */
    protected function _renderCondition(int $rowIndex, int $condIndex, string $attribute, string $operator, string $value, array $attributes, array $operators, bool $showAndLabel = false): string
    {
        $html = '';

        // AND separator
        if ($showAndLabel) {
            $html .= '<div class="logic-sep logic-and-small">' .
                     '<span class="logic-label">' . $this->__('AND') . '</span>' .
                     '</div>';
        }

        $html .= '<div class="drule-condition" id="drule-cond-' . $rowIndex . '-' . $condIndex . '">';

        // Attribute select
        $html .= '<select class="drule-cond-attr" onchange="DRuleBuilder.onConditionChange(' . $rowIndex . ')">';
        $html .= '<option value="">' . $this->__('-- Select Attribute --') . '</option>';
        foreach ($attributes['groups'] as $groupLabel => $groupAttrs) {
            $html .= '<optgroup label="' . $this->escapeHtml($groupLabel) . '">';
            foreach ($groupAttrs as $code => $label) {
                $selected = ($attribute === $code) ? ' selected' : '';
                $html .= '<option value="' . $this->escapeHtml($code) . '"' . $selected . '>' . $this->escapeHtml($label) . '</option>';
            }
            $html .= '</optgroup>';
        }
        $html .= '</select>';

        // Operator select
        $html .= '<select class="drule-cond-op" onchange="DRuleBuilder.onConditionChange(' . $rowIndex . ')">';
        foreach ($operators as $code => $label) {
            $selected = ($operator === $code) ? ' selected' : '';
            $html .= '<option value="' . $this->escapeHtml($code) . '"' . $selected . '>' . $this->escapeHtml($label) . '</option>';
        }
        $html .= '</select>';

        // Value input (hidden for null/notnull operators)
        $hideValue = in_array($operator, ['null', 'notnull']);
        $valueStyle = $hideValue ? ' style="display:none"' : '';
        $html .= '<input type="text" class="input-text drule-cond-value" value="' . $this->escapeHtml($value) . '" placeholder="' . $this->__('Value') . '"' . $valueStyle . ' onchange="DRuleBuilder.onConditionChange(' . $rowIndex . ')" />';

        // Remove button
        $html .= '<button type="button" class="delete" onclick="DRuleBuilder.removeCondition(' . $rowIndex . ', ' . $condIndex . '); return false;" title="' . $this->__('Remove') . '">' .
                 '<span>&#x2715;</span></button>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get CSS styles
     */
    protected function _getStyles(): string
    {
        return '<style>
            /* Layout adjustments */
            #rule_conditions_fieldset .form-list td.label:has(label[for="rule_conditions_builder"]) { display: none; }
            #rule_conditions_fieldset .form-list td.value { width: 100%; }

            /* Output row container */
            .drule-output-row {
                background: #fff;
                border: 1px solid #ccc;
                margin-bottom: 0;
            }
            .drule-row-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 12px;
                background: #f6f6f6;
                border-bottom: 1px solid #ccc;
            }
            .drule-row-title { font-weight: bold; }
            .drule-row-body { padding: 12px; }

            /* Conditions section */
            .drule-conditions-section {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px dashed #ddd;
            }
            .drule-if-label {
                font-weight: bold;
                color: #1565c0;
                margin-bottom: 8px;
            }
            .drule-else-label {
                font-style: italic;
                color: #666;
            }
            .drule-no-conditions {
                padding: 10px;
                background: #f9f9f9;
                border: 1px dashed #ddd;
                text-align: center;
            }
            .drule-conditions-list {
                margin-bottom: 8px;
            }

            /* Condition row */
            .drule-condition {
                display: flex;
                gap: 6px;
                align-items: center;
                margin-bottom: 6px;
            }
            .drule-condition:last-child { margin-bottom: 0; }
            .drule-condition select.drule-cond-attr { width: 200px; }
            .drule-condition select.drule-cond-op { width: 150px; }
            .drule-condition .drule-cond-value { flex: 1; min-width: 120px; }

            /* Add condition button */
            .drule-add-condition {
                font-size: 12px;
                padding: 4px 10px;
                background: #fff;
                border: 1px solid #aaa;
                color: #333;
                cursor: pointer;
            }
            .drule-add-condition:hover { background: #f0f0f0; }

            /* Output section */
            .drule-output-section {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }
            .drule-then-label {
                font-weight: bold;
                color: #2e7d32;
            }
            .drule-output-config {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                align-items: center;
                flex: 1;
            }
            .drule-output-type { width: 180px; }
            .drule-output-value { flex: 1; min-width: 150px; }
            .drule-output-attribute { width: 200px; }
            .drule-static-label, .drule-attr-label {
                font-size: 12px;
                color: #666;
            }

            /* Logic separators */
            .logic-sep {
                display: flex;
                align-items: center;
                margin: 10px 0;
            }
            .logic-sep::before, .logic-sep::after {
                content: "";
                flex: 1;
                height: 1px;
                background: #ddd;
            }
            .logic-label {
                padding: 3px 10px;
                font-size: 10px;
                font-weight: bold;
                text-transform: uppercase;
                border-radius: 3px;
            }
            .logic-or .logic-label { background: #e3f2fd; color: #1565c0; }
            .logic-and-small { margin: 4px 0; }
            .logic-and-small::before, .logic-and-small::after { background: #f3e5f5; }
            .logic-and-small .logic-label { background: #f3e5f5; color: #7b1fa2; font-size: 9px; padding: 2px 8px; }

            /* Add row button */
            .drule-add-row { margin-top: 12px; }
        </style>';
    }

    /**
     * Get JavaScript for the rule builder
     */
    protected function _getJavaScript(array $attributes, array $operators, int $rowCount): string
    {
        $attributeGroupsJson = Mage::helper('core')->jsonEncode($attributes['groups']);
        $attributesFlatJson = Mage::helper('core')->jsonEncode($attributes['flat']);
        $operatorsJson = Mage::helper('core')->jsonEncode($operators);

        return <<<SCRIPT
        <script type="text/javascript">
        var DRuleBuilder = {
            rowIndex: {$rowCount},
            conditionIndexes: {},
            attributeGroups: {$attributeGroupsJson},
            attributesFlat: {$attributesFlatJson},
            operators: {$operatorsJson},

            init: function() {
                // Initialize condition indexes for existing rows
                document.querySelectorAll('.drule-output-row').forEach(function(row) {
                    var rIdx = parseInt(row.dataset.rowIndex);
                    var conditions = row.querySelectorAll('.drule-condition');
                    this.conditionIndexes[rIdx] = conditions.length;
                }.bind(this));

                // Build initial rule data
                this.updateRuleData();

                // Watch for form submission
                var form = document.getElementById('edit_form');
                if (form) {
                    form.addEventListener('submit', function() {
                        this.updateRuleData();
                    }.bind(this));
                }
            },

            addOutputRow: function() {
                var container = document.getElementById('drule-rows-container');
                var rowHtml = this.getOutputRowHtml(this.rowIndex, true);
                container.insertAdjacentHTML('beforeend', rowHtml);
                this.conditionIndexes[this.rowIndex] = 0;
                this.rowIndex++;
                this.updateRuleData();
            },

            removeOutputRow: function(rowIndex) {
                var row = document.getElementById('drule-row-' + rowIndex);
                if (row) {
                    var prev = row.previousElementSibling;
                    if (prev && prev.classList.contains('logic-or')) prev.remove();
                    row.remove();
                    delete this.conditionIndexes[rowIndex];
                    this.updateOrSeparators();
                    this.updateRuleData();
                }
            },

            addCondition: function(rowIndex) {
                var row = document.getElementById('drule-row-' + rowIndex);
                if (!row) return;

                // Check if there's a no-conditions placeholder
                var noConditions = row.querySelector('.drule-no-conditions');
                if (noConditions) {
                    // Replace with conditions list
                    var condSection = row.querySelector('.drule-conditions-section');
                    var addBtn = condSection.querySelector('.drule-add-condition');

                    var ifLabel = document.createElement('div');
                    ifLabel.className = 'drule-if-label';
                    ifLabel.textContent = 'IF';

                    var condList = document.createElement('div');
                    condList.className = 'drule-conditions-list';
                    condList.id = 'drule-conditions-' + rowIndex;

                    noConditions.replaceWith(ifLabel);
                    addBtn.parentNode.insertBefore(condList, addBtn);

                    this.conditionIndexes[rowIndex] = 0;
                }

                var condList = document.getElementById('drule-conditions-' + rowIndex);
                if (!condList) return;

                var condIndex = this.conditionIndexes[rowIndex] || 0;
                var condHtml = this.getConditionHtml(rowIndex, condIndex, condIndex > 0);
                condList.insertAdjacentHTML('beforeend', condHtml);
                this.conditionIndexes[rowIndex] = condIndex + 1;
                this.updateRuleData();
            },

            removeCondition: function(rowIndex, condIndex) {
                var cond = document.getElementById('drule-cond-' + rowIndex + '-' + condIndex);
                if (cond) {
                    var prev = cond.previousElementSibling;
                    if (prev && prev.classList.contains('logic-and-small')) prev.remove();
                    cond.remove();

                    // Check if conditions list is now empty
                    var condList = document.getElementById('drule-conditions-' + rowIndex);
                    if (condList && condList.querySelectorAll('.drule-condition').length === 0) {
                        // Replace with no-conditions placeholder
                        var row = document.getElementById('drule-row-' + rowIndex);
                        var condSection = row.querySelector('.drule-conditions-section');
                        var ifLabel = condSection.querySelector('.drule-if-label');

                        var noConditions = document.createElement('div');
                        noConditions.className = 'drule-no-conditions';
                        noConditions.innerHTML = '<span class="drule-else-label">ELSE (default - no conditions)</span>';

                        if (ifLabel) ifLabel.remove();
                        condList.replaceWith(noConditions);
                    }

                    this.updateRuleData();
                }
            },

            onConditionChange: function(rowIndex) {
                // Update value visibility based on operator
                var row = document.getElementById('drule-row-' + rowIndex);
                if (!row) return;

                row.querySelectorAll('.drule-condition').forEach(function(cond) {
                    var op = cond.querySelector('.drule-cond-op').value;
                    var valueInput = cond.querySelector('.drule-cond-value');
                    if (valueInput) {
                        valueInput.style.display = ['null', 'notnull'].indexOf(op) !== -1 ? 'none' : '';
                    }
                });

                this.updateRuleData();
            },

            onOutputTypeChange: function(select, rowIndex) {
                var row = document.getElementById('drule-row-' + rowIndex);
                if (!row) return;

                var outputType = select.value;
                var outputSection = row.querySelector('.drule-output-config');

                var staticLabel = outputSection.querySelector('.drule-static-label');
                var valueInput = outputSection.querySelector('.drule-output-value');
                var attrLabel = outputSection.querySelector('.drule-attr-label');
                var attrSelect = outputSection.querySelector('.drule-output-attribute');

                // Show/hide fields based on output type
                var showValue = (outputType === 'static' || outputType === 'combined');
                var showAttr = (outputType === 'attribute' || outputType === 'combined');

                if (staticLabel) staticLabel.style.display = showValue ? '' : 'none';
                if (valueInput) valueInput.style.display = showValue ? '' : 'none';
                if (attrLabel) attrLabel.style.display = showAttr ? '' : 'none';
                if (attrSelect) attrSelect.style.display = showAttr ? '' : 'none';

                // Update label text
                if (staticLabel) {
                    staticLabel.textContent = (outputType === 'combined') ? 'Prefix:' : 'Value:';
                }

                this.updateRuleData();
            },

            updateOrSeparators: function() {
                var rows = document.querySelectorAll('.drule-output-row');
                rows.forEach(function(row, index) {
                    var prev = row.previousElementSibling;
                    if (index > 0 && (!prev || !prev.classList.contains('logic-or'))) {
                        var separator = document.createElement('div');
                        separator.className = 'logic-sep logic-or';
                        separator.innerHTML = '<span class="logic-label">OR</span>';
                        row.parentNode.insertBefore(separator, row);
                    } else if (index === 0 && prev && prev.classList.contains('logic-or')) {
                        prev.remove();
                    }
                });
            },

            updateRuleData: function() {
                var outputRows = [];

                document.querySelectorAll('.drule-output-row').forEach(function(row) {
                    var conditions = [];
                    row.querySelectorAll('.drule-condition').forEach(function(cond) {
                        var attr = cond.querySelector('.drule-cond-attr').value;
                        var op = cond.querySelector('.drule-cond-op').value;
                        var val = cond.querySelector('.drule-cond-value').value;
                        if (attr) {
                            conditions.push({
                                attribute: attr,
                                operator: op,
                                value: val
                            });
                        }
                    });

                    var outputSection = row.querySelector('.drule-output-config');
                    var outputType = outputSection.querySelector('.drule-output-type').value;
                    var outputValue = outputSection.querySelector('.drule-output-value').value;
                    var outputAttribute = outputSection.querySelector('.drule-output-attribute').value;

                    outputRows.push({
                        conditions: conditions,
                        output_type: outputType,
                        output_value: outputValue,
                        output_attribute: outputAttribute || null
                    });
                });

                var ruleData = { output_rows: outputRows };
                var hiddenField = document.getElementById('rule_rule_data');
                if (hiddenField) {
                    hiddenField.value = JSON.stringify(ruleData);
                }
            },

            getOutputRowHtml: function(rowIndex, showOr) {
                var html = '';

                if (showOr) {
                    html += '<div class="logic-sep logic-or"><span class="logic-label">OR</span></div>';
                }

                html += '<div class="drule-output-row" id="drule-row-' + rowIndex + '" data-row-index="' + rowIndex + '">';
                html += '<div class="drule-row-header"><span class="drule-row-title">Output Row</span>';
                html += '<button type="button" class="delete" onclick="DRuleBuilder.removeOutputRow(' + rowIndex + '); return false;" title="Remove Row"><span>&#x2715;</span></button></div>';
                html += '<div class="drule-row-body">';

                // Conditions section - start with no conditions (default)
                html += '<div class="drule-conditions-section">';
                html += '<div class="drule-no-conditions"><span class="drule-else-label">ELSE (default - no conditions)</span></div>';
                html += '<button type="button" class="drule-add-condition" onclick="DRuleBuilder.addCondition(' + rowIndex + '); return false;"><span>+ Add AND Condition</span></button>';
                html += '</div>';

                // Output section
                html += '<div class="drule-output-section">';
                html += '<div class="drule-then-label">THEN Output:</div>';
                html += '<div class="drule-output-config">';
                html += '<select class="drule-output-type" onchange="DRuleBuilder.onOutputTypeChange(this, ' + rowIndex + ')">';
                html += '<option value="static">Static Value</option>';
                html += '<option value="attribute">Product Attribute</option>';
                html += '<option value="combined">Combined (Prefix + Attribute)</option>';
                html += '</select>';
                html += '<label class="drule-static-label">Value:</label>';
                html += '<input type="text" class="input-text drule-output-value" value="" />';
                html += '<label class="drule-attr-label" style="display:none">Attribute:</label>';
                html += '<select class="drule-output-attribute" style="display:none">';
                html += '<option value="">-- Select Attribute --</option>';
                for (var code in this.attributesFlat) {
                    html += '<option value="' + this.escapeHtml(code) + '">' + this.escapeHtml(this.attributesFlat[code]) + '</option>';
                }
                html += '</select>';
                html += '</div></div>';

                html += '</div></div>';

                return html;
            },

            getConditionHtml: function(rowIndex, condIndex, showAnd) {
                var html = '';

                if (showAnd) {
                    html += '<div class="logic-sep logic-and-small"><span class="logic-label">AND</span></div>';
                }

                html += '<div class="drule-condition" id="drule-cond-' + rowIndex + '-' + condIndex + '">';

                // Attribute select
                html += '<select class="drule-cond-attr" onchange="DRuleBuilder.onConditionChange(' + rowIndex + ')">';
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
                html += '<select class="drule-cond-op" onchange="DRuleBuilder.onConditionChange(' + rowIndex + ')">';
                for (var op in this.operators) {
                    html += '<option value="' + this.escapeHtml(op) + '">' + this.escapeHtml(this.operators[op]) + '</option>';
                }
                html += '</select>';

                // Value input
                html += '<input type="text" class="input-text drule-cond-value" placeholder="Value" onchange="DRuleBuilder.onConditionChange(' + rowIndex + ')" />';

                // Remove button
                html += '<button type="button" class="delete" onclick="DRuleBuilder.removeCondition(' + rowIndex + ', ' + condIndex + '); return false;" title="Remove"><span>&#x2715;</span></button>';

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
            DRuleBuilder.init();
        });
        </script>
SCRIPT;
    }

    /**
     * Get filterable product attributes grouped by type
     */
    protected function _getProductAttributes(): array
    {
        // Special/computed attributes
        $specialAttributes = [
            'qty' => $this->__('Quantity'),
            'is_in_stock' => $this->__('Is In Stock'),
            'type_id' => $this->__('Product Type'),
            'entity_id' => $this->__('Product ID'),
            'parent_id' => $this->__('Parent ID'),
        ];

        // Common product attributes
        $productAttributes = [
            'sku' => $this->__('SKU'),
            'name' => $this->__('Product Name'),
            'price' => $this->__('Price'),
            'special_price' => $this->__('Special Price'),
            'cost' => $this->__('Cost'),
            'weight' => $this->__('Weight'),
            'gtin' => $this->__('GTIN'),
            'mpn' => $this->__('MPN'),
            'brand' => $this->__('Brand'),
        ];

        // Get custom product attributes from EAV
        $eavAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        foreach ($eavAttributes as $attribute) {
            $code = $attribute->getAttributeCode();
            $label = $attribute->getFrontendLabel();
            if ($label && !isset($productAttributes[$code]) && !isset($specialAttributes[$code])) {
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
     * Get available operators
     */
    protected function _getOperators(): array
    {
        return Maho_FeedManager_Model_DynamicRule_Evaluator::getOperatorOptions();
    }

    protected function _getRule(): Maho_FeedManager_Model_DynamicRule
    {
        return Mage::registry('current_dynamic_rule') ?: Mage::getModel('feedmanager/dynamicRule');
    }
}
