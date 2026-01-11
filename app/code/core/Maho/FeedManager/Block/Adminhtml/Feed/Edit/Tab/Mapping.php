<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $feed = $this->_getFeed();
        $platform = Maho_FeedManager_Model_Platform::getAdapter($feed->getPlatform() ?: 'custom');

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('mapping_');

        // Formats Settings
        $formatsFieldset = $form->addFieldset('formats_fieldset', [
            'legend' => $this->__('Formats & Regional Settings'),
        ]);

        $formatsFieldset->addField('format_preset', 'select', [
            'name' => 'format_preset',
            'label' => $this->__('Number Format Preset'),
            'values' => [
                ['value' => 'custom', 'label' => $this->__('Custom')],
                ['value' => 'english', 'label' => $this->__('English (1,234.56)')],
                ['value' => 'european', 'label' => $this->__('European (1.234,56)')],
                ['value' => 'swiss', 'label' => $this->__("Swiss (1'234.56)")],
                ['value' => 'indian', 'label' => $this->__('Indian (1,23,456.78)')],
            ],
            'value' => $feed->getData('format_preset') ?: 'english',
            'note' => $this->__('Quick preset for number formats. Select "Custom" to manually configure decimal and thousands separators.'),
        ]);

        $formatsFieldset->addField('price_currency', 'select', [
            'name' => 'price_currency',
            'label' => $this->__('Price Currency'),
            'values' => $this->_getCurrencyOptions(),
            'value' => $feed->getData('price_currency') ?: Mage::app()->getStore()->getBaseCurrencyCode(),
        ]);

        $formatsFieldset->addField('price_currency_suffix', 'select', [
            'name' => 'price_currency_suffix',
            'label' => $this->__('Append Currency to Prices'),
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
            'value' => $feed->getData('price_currency_suffix') ?? 1,
            'note' => $this->__('Append currency code (e.g., "295.00 USD") to price fields'),
        ]);

        $formatsFieldset->addField('price_decimals', 'text', [
            'name' => 'price_decimals',
            'label' => $this->__('Price Decimals'),
            'value' => $feed->getData('price_decimals') ?? '2',
            'class' => 'validate-digits',
            'note' => $this->__('Number of decimal places for prices'),
        ]);

        $formatsFieldset->addField('price_decimal_point', 'text', [
            'name' => 'price_decimal_point',
            'label' => $this->__('Price Decimal Point'),
            'value' => $feed->getData('price_decimal_point') ?: '.',
            'note' => $this->__('Character used as decimal point (usually . or ,)'),
        ]);

        $formatsFieldset->addField('price_thousands_sep', 'text', [
            'name' => 'price_thousands_sep',
            'label' => $this->__('Price Thousands Separator'),
            'value' => $feed->getData('price_thousands_sep') ?? '',
            'note' => $this->__('Character used as thousands separator (usually , or . or space). Leave empty for none.'),
        ]);

        $formatsFieldset->addField('tax_mode', 'select', [
            'name' => 'tax_mode',
            'label' => $this->__('Tax'),
            'values' => [
                ['value' => 'incl', 'label' => $this->__('Include Tax')],
                ['value' => 'excl', 'label' => $this->__('Exclude Tax')],
            ],
            'value' => $feed->getData('tax_mode') ?: 'incl',
        ]);

        $formatsFieldset->addField('exclude_category_url', 'select', [
            'name' => 'exclude_category_url',
            'label' => $this->__('Exclude Category from URL'),
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
            'value' => $feed->getData('exclude_category_url') ?? 1,
            'note' => $this->__('Use direct product URLs without category path'),
        ]);

        $formatsFieldset->addField('no_image_url', 'text', [
            'name' => 'no_image_url',
            'label' => $this->__('No Image URL'),
            'value' => $feed->getData('no_image_url'),
            'note' => $this->__('Fallback image URL when product has no image'),
        ]);

        // XML Builder Section (shown for XML format)
        $xmlFieldset = $form->addFieldset('xml_builder_fieldset', [
            'legend' => $this->__('XML Builder'),
            'class' => 'fieldset-wide',
        ]);

        $xmlFieldset->addField('xml_header', 'textarea', [
            'name' => 'xml_header',
            'label' => $this->__('Header'),
            'value' => $feed->getData('xml_header') ?: $this->_getDefaultXmlHeader(),
            'style' => 'width: 100% !important; height: 120px; font-family: monospace; font-size: 12px;',
            'note' => $this->__('Opening XML tags. Variables: {{store_name}}, {{store_url}}, {{generation_date}}'),
        ]);

        $xmlFieldset->addField('xml_item_tag', 'text', [
            'name' => 'xml_item_tag',
            'label' => $this->__('Item Tag'),
            'value' => $feed->getData('xml_item_tag') ?: 'item',
            'note' => $this->__('XML tag name that wraps each product (e.g., item, entry, product). Leave empty for no wrapper.'),
        ]);

        $xmlFieldset->addField('xml_builder', 'note', [
            'text' => $this->_getXmlBuilderHtml(),
        ]);

        $xmlFieldset->addField('xml_structure', 'hidden', [
            'name' => 'xml_structure',
            'value' => $feed->getXmlStructure(),
        ]);

        $xmlFieldset->addField('xml_footer', 'textarea', [
            'name' => 'xml_footer',
            'label' => $this->__('Footer'),
            'value' => $feed->getData('xml_footer') ?: $this->_getDefaultXmlFooter(),
            'style' => 'width: 100% !important; height: 80px; font-family: monospace; font-size: 12px;',
            'note' => $this->__('Closing XML tags'),
        ]);

        // CSV Builder Section (shown for CSV format)
        $csvFieldset = $form->addFieldset('csv_builder_fieldset', [
            'legend' => $this->__('CSV Builder'),
            'class' => 'fieldset-wide',
        ]);

        $csvFieldset->addField('csv_settings_note', 'note', [
            'text' => '<div id="csv-settings-row" style="display: flex; gap: 20px; margin-bottom: 15px;">' .
                '<div><label>' . $this->__('Delimiter') . '</label>' .
                '<select id="csv_delimiter" name="csv_delimiter" style="width: 100px;">' .
                '<option value=","' . ($feed->getCsvDelimiter() === ',' || $feed->getCsvDelimiter() === null ? ' selected' : '') . '>' . $this->__('Comma (,)') . '</option>' .
                '<option value="&#9;"' . ($feed->getCsvDelimiter() === "\t" ? ' selected' : '') . '>' . $this->__('Tab') . '</option>' .
                '<option value="|"' . ($feed->getCsvDelimiter() === '|' ? ' selected' : '') . '>' . $this->__('Pipe (|)') . '</option>' .
                '<option value=";"' . ($feed->getCsvDelimiter() === ';' ? ' selected' : '') . '>' . $this->__('Semicolon (;)') . '</option>' .
                '</select></div>' .
                '<div><label>' . $this->__('Enclosure') . '</label>' .
                '<select id="csv_enclosure" name="csv_enclosure" style="width: 100px;">' .
                '<option value="&quot;"' . ($feed->getCsvEnclosure() === '"' || $feed->getCsvEnclosure() === null ? ' selected' : '') . '>' . $this->__('Double Quote (")') . '</option>' .
                '<option value="&#39;"' . ($feed->getCsvEnclosure() === "'" ? ' selected' : '') . '>' . $this->__("Single Quote (')") . '</option>' .
                '<option value=""' . ($feed->getCsvEnclosure() === '' ? ' selected' : '') . '>' . $this->__('None') . '</option>' .
                '</select></div>' .
                '<div><label>' . $this->__('Include Header') . '</label>' .
                '<select id="csv_include_header" name="csv_include_header" style="width: 100px;">' .
                '<option value="1"' . ($feed->getCsvIncludeHeader() != 0 ? ' selected' : '') . '>' . $this->__('Yes') . '</option>' .
                '<option value="0"' . ($feed->getCsvIncludeHeader() == 0 && $feed->getCsvIncludeHeader() !== null ? ' selected' : '') . '>' . $this->__('No') . '</option>' .
                '</select></div>' .
                '</div>',
        ]);

        $csvFieldset->addField('csv_builder', 'note', [
            'text' => $this->_getCsvBuilderHtml(),
        ]);

        $csvFieldset->addField('csv_columns', 'hidden', [
            'name' => 'csv_columns',
            'value' => $feed->getCsvColumns(),
        ]);

        // JSON Builder Section (shown for JSON format)
        $jsonFieldset = $form->addFieldset('json_builder_fieldset', [
            'legend' => $this->__('JSON Builder'),
            'class' => 'fieldset-wide',
        ]);

        $jsonFieldset->addField('json_root_key_field', 'text', [
            'name' => 'json_root_key',
            'label' => $this->__('Root Array Key'),
            'value' => $feed->getJsonRootKey() ?: 'products',
            'note' => $this->__('The key name for the products array (e.g., "products", "items", "data")'),
        ]);

        $jsonFieldset->addField('json_builder', 'note', [
            'text' => $this->_getJsonBuilderHtml(),
        ]);

        $jsonFieldset->addField('json_structure', 'hidden', [
            'name' => 'json_structure',
            'value' => $feed->getJsonStructure(),
        ]);

        // Add transformer modal as a global element (always in DOM, not hidden)
        // Uses height:0 overflow:visible so modal can show while fieldset takes no space
        $globalFieldset = $form->addFieldset('global_elements', [
            'legend' => '',
            'class' => 'fieldset-wide',
            'style' => 'height: 0; overflow: visible; padding: 0; margin: 0; border: none;',
        ]);
        $globalFieldset->addField('transformer_modal_container', 'note', [
            'text' => '<input type="hidden" id="editor_transformers" value="">' .
                $this->_getTransformerModalHtml(),
        ]);

        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * Append format preset script after HTML
     */
    #[\Override]
    protected function _afterToHtml($html)
    {
        $html .= $this->_getFormatPresetScript();
        return parent::_afterToHtml($html);
    }

    /**
     * Get JavaScript for format preset and template switching functionality
     */
    protected function _getFormatPresetScript(): string
    {
        return <<<SCRIPT
        <style type="text/css">
        /* Ensure formats fieldset has proper label widths */
        #mapping_formats_fieldset .form-list td.label {
            width: 200px;
            min-width: 200px;
        }

        /* Make XML builder fieldset use block layout for full-width fields */
        #mapping_xml_builder_fieldset .form-list,
        #mapping_xml_builder_fieldset .form-list tbody {
            display: block;
        }
        #mapping_xml_builder_fieldset .form-list tr {
            display: block;
            margin-bottom: 15px;
        }
        #mapping_xml_builder_fieldset .form-list td.label {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0 0 5px 0;
            font-weight: 600;
        }
        #mapping_xml_builder_fieldset .form-list td.label label {
            float: none;
        }
        #mapping_xml_builder_fieldset .form-list td.value {
            display: block;
            width: 100%;
        }
        #mapping_xml_builder_fieldset .form-list .note {
            max-width: 100%;
        }

        /* Hide empty labels for builder rows */
        #mapping_xml_builder_fieldset tr:has(#xml-builder-container) td.label {
            display: none;
        }

        /* CSV/JSON builder fieldsets */
        #mapping_csv_builder_fieldset .form-list td.label,
        #mapping_json_builder_fieldset .form-list td.label {
            display: none;
        }
        #mapping_csv_builder_fieldset .form-list td.value,
        #mapping_json_builder_fieldset .form-list td.value {
            width: 100%;
        }

        /* Code editor styling - layered approach */
        .code-editor-wrapper {
            position: relative;
            width: 100%;
        }
        /* Textarea - transparent text, visible caret */
        .code-editor-wrapper textarea {
            font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace !important;
            font-size: 13px !important;
            line-height: 1.6 !important;
            box-sizing: border-box !important;
            background: transparent !important;
            color: transparent !important;
            caret-color: #f5e0dc !important;
            border: 1px solid #45475a !important;
            border-radius: 6px !important;
            padding: 12px 16px !important;
            tab-size: 2 !important;
            white-space: pre !important;
            overflow: auto !important;
            resize: vertical !important;
            position: relative;
            z-index: 2;
        }
        .code-editor-wrapper textarea:focus {
            outline: none !important;
            border-color: #89b4fa !important;
            box-shadow: 0 0 0 3px rgba(137, 180, 250, 0.15) !important;
        }
        .code-editor-wrapper textarea::selection {
            background: rgba(137, 180, 250, 0.3) !important;
        }
        /* Highlighted backdrop - always visible behind textarea */
        .code-highlight-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace !important;
            font-size: 13px !important;
            line-height: 1.6 !important;
            padding: 12px 16px !important;
            background: #1e1e2e;
            color: #cdd6f4;
            border: 1px solid transparent;
            border-radius: 6px;
            overflow: hidden;
            pointer-events: none;
            white-space: pre;
            tab-size: 2;
            z-index: 1;
        }
        /* Syntax highlighting colors - Catppuccin Mocha */
        .code-highlight-backdrop .xml-tag { color: #89b4fa; }
        .code-highlight-backdrop .xml-attr-name { color: #f9e2af; }
        .code-highlight-backdrop .xml-attr-value { color: #a6e3a1; }
        .code-highlight-backdrop .xml-cdata { color: #6c7086; }
        .code-highlight-backdrop .xml-comment { color: #6c7086; font-style: italic; }
        .code-highlight-backdrop .field-brace { color: #cba6f7; }
        .code-highlight-backdrop .field-key { color: #94e2d5; }
        .code-highlight-backdrop .field-value { color: #fab387; }
        .code-highlight-backdrop .template-var { color: #f5c2e7; }

        /* Field editor styling */
        #field-editor-container {
            margin-top: 15px;
        }
        #field-editor-container table {
            border-collapse: collapse;
        }
        #field-editor-container th {
            font-size: 12px;
            font-weight: 600;
            border-bottom: 2px solid #ccc;
            border-right: 1px solid rgba(0,0,0,0.08);
        }
        #field-editor-container th:last-child {
            border-right: none;
        }
        #field-editor-container td {
            vertical-align: middle;
            border-right: 1px solid rgba(0,0,0,0.05);
        }
        #field-editor-container td:last-child {
            border-right: none;
        }
        #field-editor-container input,
        #field-editor-container select {
            font-size: 12px;
            padding: 4px 6px;
        }
        .editor-mode-insert #editor_insert_btn { display: inline-block !important; }
        .editor-mode-insert #editor_update_btn { display: none !important; }
        .editor-mode-update #editor_insert_btn { display: none !important; }
        .editor-mode-update #editor_update_btn { display: inline-block !important; }

        /* Transformer Modal Styles - Compact */
        .transformer-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .transformer-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
        }
        .transformer-modal-content {
            position: relative;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            width: 600px;
            max-width: 90vw;
            min-height: 500px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .transformer-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .transformer-modal-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
        }
        .transformer-modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
            padding: 0;
            line-height: 1;
        }
        .transformer-modal-close:hover { color: #333; }
        .transformer-modal-body {
            padding: 12px 15px;
            overflow-y: auto;
            flex: 1;
        }
        .transformer-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 10px 15px;
            border-top: 1px solid #e0e0e0;
        }
        .transformer-pipeline {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 6px 10px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 11px;
        }
        .pipeline-label {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: 500;
        }
        .pipeline-arrow { color: #6c757d; }
        .transformer-chain-list {
            min-height: 60px;
            border: 1px dashed #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 10px;
        }
        .transformer-chain-list .no-transformers {
            text-align: center;
            color: #6c757d;
            margin: 12px 0;
            font-size: 12px;
        }
        .transformer-item {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 6px;
            overflow: hidden;
        }
        .transformer-item:last-child { margin-bottom: 0; }
        .transformer-item-header {
            display: flex;
            align-items: center;
            padding: 6px 10px;
            background: #f8f9fa;
            cursor: pointer;
        }
        .transformer-item-number {
            background: #6c757d;
            color: #fff;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            margin-right: 8px;
        }
        .transformer-item-name {
            flex: 1;
            font-weight: 500;
            font-size: 12px;
        }
        .transformer-item-actions {
            display: flex;
            gap: 3px;
        }
        .transformer-item-actions button {
            background: #fff;
            border: 1px solid #adb5bd;
            border-radius: 3px;
            width: 22px;
            height: 22px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #495057;
        }
        .transformer-item-actions button:hover { background: #e9ecef; border-color: #6c757d; }
        .transformer-item-actions .remove-btn { color: #dc3545; border-color: #dc3545; }
        .transformer-item-actions .remove-btn:hover { background: #f8d7da; }
        .transformer-item-options {
            padding: 8px 10px;
            border-top: 1px solid #dee2e6;
            display: none;
        }
        .transformer-item.expanded .transformer-item-options { display: block; }
        .transformer-option {
            margin-bottom: 6px;
        }
        .transformer-option:last-child { margin-bottom: 0; }
        .transformer-option label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            margin-bottom: 2px;
            color: #495057;
        }
        .transformer-option label .required { color: #dc3545; }
        .transformer-option input,
        .transformer-option select,
        .transformer-option textarea {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid #ced4da;
            border-radius: 3px;
            font-size: 12px;
        }
        .transformer-option select {
            padding: 0 8px;
            height: 26px;
        }
        .transformer-option textarea { min-height: 40px; resize: vertical; }
        .transformer-option .note {
            font-size: 10px;
            color: #6c757d;
            margin-top: 2px;
        }
        .transformer-add-section { text-align: center; }
        .transformer-dropdown-wrapper { position: relative; display: inline-block; }
        .transformer-dropdown {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 240px;
            max-height: 380px;
            overflow-y: auto;
            z-index: 100;
            margin-top: 4px;
        }
        .transformer-dropdown-category {
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            color: #6c757d;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .transformer-dropdown-item {
            padding: 6px 10px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
        }
        .transformer-dropdown-item:hover { background: #e9ecef; }
        .transformer-dropdown-item:last-child { border-bottom: none; }
        .transformer-dropdown-item-name { font-weight: 500; font-size: 12px; }
        .transformer-dropdown-item-desc { font-size: 10px; color: #6c757d; margin-top: 1px; }
        </style>
        <script type="text/javascript">
        // Field editor state
        var feedEditorState = {
            currentLineIndex: -1,
            mode: 'insert' // 'insert' or 'update'
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Number format presets
            var presets = {
                'english': { decimal_point: '.', thousands_sep: ',' },
                'european': { decimal_point: ',', thousands_sep: '.' },
                'swiss': { decimal_point: '.', thousands_sep: "'" },
                'indian': { decimal_point: '.', thousands_sep: ',' }
            };

            var presetSelect = document.getElementById('mapping_format_preset');
            var decimalPointField = document.getElementById('mapping_price_decimal_point');
            var thousandsSepField = document.getElementById('mapping_price_thousands_sep');

            if (presetSelect) {
                presetSelect.addEventListener('change', function() {
                    var preset = presets[this.value];
                    if (preset) {
                        if (decimalPointField) decimalPointField.value = preset.decimal_point;
                        if (thousandsSepField) thousandsSepField.value = preset.thousands_sep;
                    }
                });
            }

            // Template/Mapping mode switching based on file format
            var fileFormatSelect = document.getElementById('feed_file_format');
            var xmlFieldset = document.getElementById('mapping_xml_builder_fieldset');
            var mappingFieldset = document.getElementById('mapping_mapping_fieldset');

            function updateContentMode() {
                if (!fileFormatSelect) return;

                var format = fileFormatSelect.value;
                var csvFieldset = document.getElementById('mapping_csv_builder_fieldset');
                var jsonFieldset = document.getElementById('mapping_json_builder_fieldset');

                // XML Builder
                if (xmlFieldset) {
                    xmlFieldset.style.display = format === 'xml' ? 'block' : 'none';
                }

                // CSV Builder
                if (csvFieldset) {
                    csvFieldset.style.display = format === 'csv' ? 'block' : 'none';
                }

                // JSON Builder
                if (jsonFieldset) {
                    jsonFieldset.style.display = format === 'json' ? 'block' : 'none';
                }

                // Mapping fieldset (hide for all builder-based formats)
                if (mappingFieldset) {
                    mappingFieldset.style.display = 'none';
                }
            }

            // Initialize on page load
            updateContentMode();

            // Update when format changes
            if (fileFormatSelect) {
                fileFormatSelect.addEventListener('change', updateContentMode);
            }

            // Initialize code editor styling
            initCodeEditors();

            // Override TransformerModal.apply to handle CSV, JSON, and XML builder contexts
            if (typeof TransformerModal !== 'undefined') {
                var originalApply = TransformerModal.apply;
                TransformerModal.apply = function() {
                    var chainStr = TransformerModal.buildChainString();

                    // Check for CSV Builder context
                    if (typeof CsvBuilder !== 'undefined' && typeof CsvBuilder.currentColumnIndex !== 'undefined') {
                        CsvBuilder.columns[CsvBuilder.currentColumnIndex].transformers = chainStr;
                        CsvBuilder.render();
                        delete CsvBuilder.currentColumnIndex;
                        TransformerModal.close();
                        return;
                    }

                    // Check for JSON Builder context
                    if (typeof JsonBuilder !== 'undefined' && typeof JsonBuilder.currentNodePath !== 'undefined') {
                        var node = JsonBuilder.getNodeByPath(JsonBuilder.currentNodePath);
                        if (node) {
                            node.transformers = chainStr;
                        }
                        JsonBuilder.render();
                        JsonBuilder.showProperties(JsonBuilder.currentNodePath);
                        delete JsonBuilder.currentNodePath;
                        TransformerModal.close();
                        return;
                    }

                    // Check for XML Builder context
                    if (typeof XmlBuilder !== 'undefined' && typeof XmlBuilder.currentNodePath !== 'undefined') {
                        var node = XmlBuilder.getNodeByPath(XmlBuilder.currentNodePath);
                        if (node) {
                            node.transformers = chainStr;
                        }
                        XmlBuilder.render();
                        XmlBuilder.showProperties(XmlBuilder.currentNodePath);
                        delete XmlBuilder.currentNodePath;
                        TransformerModal.close();
                        return;
                    }

                    // Fall back to original behavior
                    originalApply.call(TransformerModal);
                };
            }
        });

        function initCodeEditors() {
            var textareaIds = ['mapping_xml_header', 'mapping_xml_footer'];

            textareaIds.forEach(function(id) {
                var textarea = document.getElementById(id);
                if (!textarea) return;

                // Wrap in code editor container
                var wrapper = document.createElement('div');
                wrapper.className = 'code-editor-wrapper';
                textarea.parentNode.insertBefore(wrapper, textarea);
                wrapper.appendChild(textarea);

                // Create syntax-highlighted backdrop (visible behind transparent textarea)
                var backdrop = document.createElement('div');
                backdrop.className = 'code-highlight-backdrop';
                backdrop.id = id + '_backdrop';
                wrapper.insertBefore(backdrop, textarea);

                // Update backdrop on input
                textarea.addEventListener('input', function() {
                    updateSyntaxHighlight(textarea, backdrop);
                });

                // Sync scroll position
                textarea.addEventListener('scroll', function() {
                    backdrop.scrollTop = textarea.scrollTop;
                    backdrop.scrollLeft = textarea.scrollLeft;
                });

                // Initial highlight
                updateSyntaxHighlight(textarea, backdrop);

                // Handle resize - use ResizeObserver if available
                if (typeof ResizeObserver !== 'undefined') {
                    var resizeObserver = new ResizeObserver(function() {
                        backdrop.style.height = textarea.offsetHeight + 'px';
                    });
                    resizeObserver.observe(textarea);
                }
            });
        }

        function updateSyntaxHighlight(textarea, backdrop) {
            var code = textarea.value;
            backdrop.innerHTML = highlightXML(code);
        }

        function highlightXML(code) {
            // Step 1: Escape HTML entities
            var escaped = code
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');

            // Step 2: Highlight field configurations {type="..." value="..." ...}
            escaped = escaped.replace(
                /\{([^}]+)\}/g,
                function(match, inner) {
                    var highlighted = inner.replace(
                        /(\w+)(=&quot;)([^&]*?)(&quot;)/g,
                        '<span class="field-key">\$1</span>\$2<span class="field-value">\$3</span>\$4'
                    );
                    return '<span class="field-brace">{</span>' + highlighted + '<span class="field-brace">}</span>';
                }
            );

            // Step 3: Highlight template variables {{...}}
            escaped = escaped.replace(
                /\{\{([^}]+)\}\}/g,
                '<span class="template-var">{{\$1}}</span>'
            );

            // Step 4: Highlight XML comments
            escaped = escaped.replace(
                /&lt;!--([\s\S]*?)--&gt;/g,
                '<span class="xml-comment">&lt;!--\$1--&gt;</span>'
            );

            // Step 5: Highlight CDATA sections
            escaped = escaped.replace(
                /&lt;!\[CDATA\[/g,
                '<span class="xml-cdata">&lt;![CDATA[</span>'
            );
            escaped = escaped.replace(
                /\]\]&gt;/g,
                '<span class="xml-cdata">]]&gt;</span>'
            );

            // Step 6: Highlight XML declaration
            escaped = escaped.replace(
                /&lt;\?(xml[^?]*)\?&gt;/gi,
                '&lt;?<span class="xml-tag">\$1</span>?&gt;'
            );

            // Step 7: Highlight XML tags and attributes
            escaped = escaped.replace(
                /&lt;(\/?)([\w:-]+)((?:\s+[\w:-]+(?:=&quot;[^&]*&quot;)?)*)\s*(\/?)\s*&gt;/g,
                function(match, leadingSlash, tagName, attrs, trailingSlash) {
                    var highlightedAttrs = attrs.replace(
                        /([\w:-]+)(=&quot;)([^&]*)(&quot;)/g,
                        ' <span class="xml-attr-name">\$1</span>\$2<span class="xml-attr-value">\$3</span>\$4'
                    );
                    return '&lt;' + leadingSlash + '<span class="xml-tag">' + tagName + '</span>' +
                           highlightedAttrs + trailingSlash + '&gt;';
                }
            );

            return escaped;
        }

        // Transformer Modal Object
        var TransformerModal = {
            chain: [],

            open: function() {
                // Parse current transformers from hidden field
                var transformersStr = document.getElementById('editor_transformers').value;
                this.chain = this.parseChain(transformersStr);

                // Render the modal
                this.renderChainList();
                this.renderDropdown();

                // Show modal
                document.getElementById('transformer-modal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            },

            close: function() {
                document.getElementById('transformer-modal').style.display = 'none';
                document.body.style.overflow = '';
                this.hideDropdown();
            },

            apply: function() {
                // Build chain string and save to hidden field
                var chainStr = this.buildChainString();
                document.getElementById('editor_transformers').value = chainStr;
                updateTransformersButtonLabel(chainStr);
                this.close();
            },

            parseChain: function(str) {
                if (!str) return [];
                var chain = [];
                var parts = str.split('|');
                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i].trim();
                    if (!part) continue;
                    var colonIdx = part.indexOf(':');
                    var code = colonIdx > -1 ? part.substring(0, colonIdx) : part;
                    var options = {};
                    if (colonIdx > -1) {
                        var optStr = part.substring(colonIdx + 1);
                        var optPairs = optStr.split(',');
                        for (var j = 0; j < optPairs.length; j++) {
                            var kv = optPairs[j].split('=');
                            if (kv.length === 2) {
                                options[kv[0].trim()] = kv[1].trim();
                            }
                        }
                    }
                    chain.push({ code: code, options: options });
                }
                return chain;
            },

            buildChainString: function() {
                var parts = [];
                for (var i = 0; i < this.chain.length; i++) {
                    var item = this.chain[i];
                    var str = item.code;
                    var optParts = [];
                    for (var key in item.options) {
                        if (item.options[key]) {
                            optParts.push(key + '=' + item.options[key]);
                        }
                    }
                    if (optParts.length > 0) {
                        str += ':' + optParts.join(',');
                    }
                    parts.push(str);
                }
                return parts.join('|');
            },

            renderChainList: function() {
                var container = document.getElementById('transformer-chain-list');
                if (this.chain.length === 0) {
                    container.innerHTML = '<p class="no-transformers">No transformers added. Click "Add Transformer" to start.</p>';
                    return;
                }

                var html = '';
                for (var i = 0; i < this.chain.length; i++) {
                    var item = this.chain[i];
                    var def = TransformerData.definitions[item.code];
                    if (!def) continue;

                    html += '<div class="transformer-item expanded" data-index="' + i + '">';
                    html += '<div class="transformer-item-header" onclick="TransformerModal.toggleItem(' + i + ')">';
                    html += '<span class="transformer-item-number">' + (i + 1) + '</span>';
                    html += '<span class="transformer-item-name">' + def.name + '</span>';
                    html += '<div class="transformer-item-actions">';
                    if (i > 0) {
                        html += '<button type="button" onclick="TransformerModal.moveUp(' + i + '); event.stopPropagation();" title="Move Up">↑</button>';
                    }
                    if (i < this.chain.length - 1) {
                        html += '<button type="button" onclick="TransformerModal.moveDown(' + i + '); event.stopPropagation();" title="Move Down">↓</button>';
                    }
                    html += '<button type="button" class="remove-btn" onclick="TransformerModal.remove(' + i + '); event.stopPropagation();" title="Remove">×</button>';
                    html += '</div></div>';

                    // Options
                    html += '<div class="transformer-item-options">';
                    if (def.options && Object.keys(def.options).length > 0) {
                        for (var optKey in def.options) {
                            var opt = def.options[optKey];
                            var currentVal = item.options[optKey] || '';
                            html += '<div class="transformer-option">';
                            html += '<label>' + opt.label + (opt.required ? ' <span class="required">*</span>' : '') + '</label>';

                            if (opt.type === 'select' && opt.options) {
                                html += '<select onchange="TransformerModal.updateOption(' + i + ', \\'' + optKey + '\\', this.value)">';
                                if (Array.isArray(opt.options)) {
                                    for (var oi = 0; oi < opt.options.length; oi++) {
                                        var selected = currentVal === String(oi) ? ' selected' : '';
                                        html += '<option value="' + oi + '"' + selected + '>' + opt.options[oi] + '</option>';
                                    }
                                } else {
                                    for (var optVal in opt.options) {
                                        var selected = currentVal === optVal ? ' selected' : '';
                                        html += '<option value="' + optVal + '"' + selected + '>' + opt.options[optVal] + '</option>';
                                    }
                                }
                                html += '</select>';
                            } else if (opt.type === 'textarea') {
                                html += '<textarea onchange="TransformerModal.updateOption(' + i + ', \\'' + optKey + '\\', this.value)">' + this.escapeHtml(currentVal) + '</textarea>';
                            } else {
                                html += '<input type="text" value="' + this.escapeHtml(currentVal) + '" onchange="TransformerModal.updateOption(' + i + ', \\'' + optKey + '\\', this.value)">';
                            }

                            if (opt.note) {
                                html += '<div class="note">' + opt.note + '</div>';
                            }
                            html += '</div>';
                        }
                    } else {
                        html += '<p style="color: #6c757d; font-size: 12px; margin: 0;">No options for this transformer.</p>';
                    }
                    html += '</div></div>';
                }

                container.innerHTML = html;
            },

            renderDropdown: function() {
                var dropdown = document.getElementById('transformer-dropdown');
                var html = '';

                for (var category in TransformerData.categories) {
                    html += '<div class="transformer-dropdown-category">' + category + '</div>';
                    for (var code in TransformerData.categories[category]) {
                        var t = TransformerData.categories[category][code];
                        html += '<div class="transformer-dropdown-item" onclick="TransformerModal.add(\\'' + code + '\\')">';
                        html += '<div class="transformer-dropdown-item-name">' + t.name + '</div>';
                        html += '<div class="transformer-dropdown-item-desc">' + t.description + '</div>';
                        html += '</div>';
                    }
                }

                dropdown.innerHTML = html;
            },

            toggleDropdown: function() {
                var dropdown = document.getElementById('transformer-dropdown');
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            },

            hideDropdown: function() {
                document.getElementById('transformer-dropdown').style.display = 'none';
            },

            add: function(code) {
                this.chain.push({ code: code, options: {} });
                this.renderChainList();
                this.hideDropdown();
            },

            remove: function(index) {
                this.chain.splice(index, 1);
                this.renderChainList();
            },

            moveUp: function(index) {
                if (index > 0) {
                    var temp = this.chain[index];
                    this.chain[index] = this.chain[index - 1];
                    this.chain[index - 1] = temp;
                    this.renderChainList();
                }
            },

            moveDown: function(index) {
                if (index < this.chain.length - 1) {
                    var temp = this.chain[index];
                    this.chain[index] = this.chain[index + 1];
                    this.chain[index + 1] = temp;
                    this.renderChainList();
                }
            },

            toggleItem: function(index) {
                var items = document.querySelectorAll('.transformer-item');
                if (items[index]) {
                    items[index].classList.toggle('expanded');
                }
            },

            updateOption: function(index, key, value) {
                if (this.chain[index]) {
                    this.chain[index].options[key] = value;
                }
            },

            escapeHtml: function(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        };

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.transformer-dropdown-wrapper')) {
                TransformerModal.hideDropdown();
            }
        });
        </script>
SCRIPT;
    }

    /**
     * Get available currency options from store configuration
     */
    protected function _getCurrencyOptions(): array
    {
        $options = [];
        $currencies = Mage::app()->getStore()->getAvailableCurrencyCodes(true);
        $currencyNames = Mage::app()->getLocale()->getOptionCurrencies();

        // Build a lookup array from currency names
        $namesLookup = [];
        foreach ($currencyNames as $currency) {
            $namesLookup[$currency['value']] = $currency['label'];
        }

        foreach ($currencies as $code) {
            $label = $namesLookup[$code] ?? $code;
            $options[] = ['value' => $code, 'label' => "{$code} - {$label}"];
        }

        return $options;
    }

    /**
     * Get transformer modal HTML
     */
    protected function _getTransformerModalHtml(): string
    {
        $transformerData = Mage::helper('core')->jsonEncode(
            Maho_FeedManager_Model_Transformer::getTransformerDataForJs(),
        );

        return '
        <div id="transformer-modal" class="transformer-modal" style="display: none;">
            <div class="transformer-modal-overlay" onclick="TransformerModal.close()"></div>
            <div class="transformer-modal-content">
                <div class="transformer-modal-header">
                    <h3>' . $this->__('Configure Transformers') . '</h3>
                    <button type="button" class="transformer-modal-close" onclick="TransformerModal.close()">&times;</button>
                </div>
                <div class="transformer-modal-body">
                    <div class="transformer-pipeline">
                        <span class="pipeline-label">' . $this->__('Input') . '</span>
                        <span class="pipeline-arrow">→</span>
                        <span class="pipeline-label">' . $this->__('Transformers') . '</span>
                        <span class="pipeline-arrow">→</span>
                        <span class="pipeline-label">' . $this->__('Output') . '</span>
                    </div>
                    <div id="transformer-chain-list" class="transformer-chain-list">
                        <p class="no-transformers">' . $this->__('No transformers added. Click "Add Transformer" to start.') . '</p>
                    </div>
                    <div class="transformer-add-section">
                        <div class="transformer-dropdown-wrapper">
                            <button type="button" id="add-transformer-btn" class="scalable add" onclick="TransformerModal.toggleDropdown()">
                                <span>' . $this->__('+ Add Transformer') . '</span>
                            </button>
                            <div id="transformer-dropdown" class="transformer-dropdown" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="transformer-modal-footer">
                    <button type="button" class="scalable back" onclick="TransformerModal.close()">
                        <span>' . $this->__('Cancel') . '</span>
                    </button>
                    <button type="button" class="scalable save" onclick="TransformerModal.apply()">
                        <span>' . $this->__('Apply Transformers') . '</span>
                    </button>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            var TransformerData = ' . $transformerData . ';
        </script>';
    }

    /**
     * Get product attribute options for editor dropdown
     */
    protected function _getProductAttributeOptionsForEditor(): string
    {
        $html = '<optgroup label="' . $this->__('Product Attributes') . '">';

        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        foreach ($attributes as $attribute) {
            $label = $attribute->getFrontendLabel();
            $code = $attribute->getAttributeCode();
            if ($label) {
                $html .= '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($label) . '</option>';
            }
        }
        $html .= '</optgroup>';

        // Price attributes
        $html .= '<optgroup label="' . $this->__('Price') . '">';
        $html .= '<option value="price">Price</option>';
        $html .= '<option value="special_price">Special Price</option>';
        $html .= '<option value="final_price">Final Price</option>';
        $html .= '<option value="min_price">Minimal Price</option>';
        $html .= '<option value="max_price">Maximal Price</option>';
        $html .= '</optgroup>';

        // Other attributes
        $html .= '<optgroup label="' . $this->__('Other Attributes') . '">';
        $html .= '<option value="sku">SKU</option>';
        $html .= '<option value="url">URL</option>';
        $html .= '<option value="image">Base Image</option>';
        $html .= '<option value="category">Category</option>';
        $html .= '<option value="category_id">Category ID</option>';
        $html .= '<option value="is_in_stock">In Stock</option>';
        $html .= '<option value="qty">Qty</option>';
        $html .= '<option value="parent_id">Parent ID</option>';
        $html .= '<option value="entity_id">Product ID</option>';
        $html .= '<option value="type_id">Type ID</option>';
        $html .= '</optgroup>';

        // Images
        $html .= '<optgroup label="' . $this->__('Images') . '">';
        for ($i = 1; $i <= 10; $i++) {
            $html .= '<option value="image_' . $i . '">Image ' . $i . '</option>';
        }
        $html .= '</optgroup>';

        return $html;
    }

    /**
     * Get default XML header template
     */
    protected function _getDefaultXmlHeader(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
    <title>{{store_name}}</title>
    <link>{{store_url}}</link>
    <description>Product Feed - Generated {{generation_date}}</description>
XML;
    }

    /**
     * Get default XML item template
     */
    protected function _getDefaultXmlItemTemplate(): string
    {
        return <<<'XML'
    <item>
        <g:id>{type="attribute" value="sku" format="as_is" length="50" optional="no" parent="no"}</g:id>
        <g:title><![CDATA[{type="attribute" value="name" format="html_escape" length="150" optional="no" parent="no"}]]></g:title>
        <g:description><![CDATA[{type="attribute" value="description" format="html_escape" length="5000" optional="yes" parent="yes"}]]></g:description>
        <g:link>{type="attribute" value="url" format="as_is" length="2000" optional="no" parent="yes"}</g:link>
        <g:image_link>{type="attribute" value="image" format="base" length="500" optional="yes" parent="yes"}</g:image_link>
        <g:availability>{type="attribute" value="is_in_stock" format="as_is" length="" optional="no" parent="no"}</g:availability>
        <g:price>{type="attribute" value="price" format="price" length="" optional="no" parent="yes"}</g:price>
        <g:brand><![CDATA[{type="attribute" value="brand" format="html_escape" length="" optional="yes" parent="no"}]]></g:brand>
        <g:condition>{type="text" value="new" format="as_is" length="" optional="yes" parent="no"}</g:condition>
    </item>
XML;
    }

    /**
     * Get default XML footer template
     */
    protected function _getDefaultXmlFooter(): string
    {
        return <<<'XML'
</channel>
</rss>
XML;
    }

    /**
     * Get CSV Builder HTML
     */
    protected function _getCsvBuilderHtml(): string
    {
        $feed = $this->_getFeed();
        $columns = $feed->getCsvColumns();
        $columnsData = $columns ? Mage::helper('core')->jsonDecode($columns) : [];
        $sourceTypes = Maho_FeedManager_Model_Mapper::getSourceTypeOptions();
        $attributeOptions = $this->_getProductAttributeOptionsForEditor();
        $platformOptions = $this->_getPlatformPresetOptions();

        return '
        <div id="csv-builder-container">
            <div class="csv-toolbar" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                <select id="csv-preset-select" onchange="CsvBuilder.loadPreset(this.value)" style="min-width: 150px;">
                    <option value="">' . $this->__('Load Preset...') . '</option>
                    ' . $platformOptions . '
                </select>
                <span id="csv-platform-badge" style="display: ' . ($feed->getPlatform() && $feed->getPlatform() !== 'custom' ? 'inline-block' : 'none') . '; padding: 3px 8px; background: #e3f2fd; color: #1565c0; border-radius: 3px; font-size: 11px; font-weight: 500;">' . ucfirst($feed->getPlatform() ?: '') . '</span>
                <button type="button" class="scalable" onclick="CsvBuilder.showImportModal()">
                    <span>' . $this->__('Import CSV') . '</span>
                </button>
                <div style="flex-grow: 1;"></div>
                <button type="button" class="scalable" onclick="CsvBuilder.togglePreview()">
                    <span id="csv-preview-toggle-label">' . $this->__('Show Preview') . '</span>
                </button>
            </div>

            <div id="csv-grid-container">
                <table class="data csv-grid" id="csv-grid" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr class="headings">
                            <th style="width: 30px;"></th>
                            <th>' . $this->__('Column Name') . '</th>
                            <th>' . $this->__('Source Type') . '</th>
                            <th>' . $this->__('Source Value') . '</th>
                            <th title="' . $this->__('Use parent product value') . '" style="width: 80px; text-align: center;">Parent</th>
                            <th>' . $this->__('Transformers') . '</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="csv-grid-body">
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 12px; background: #fafafa; border-top: 1px solid #ddd;">
                                <button type="button" class="scalable add" onclick="CsvBuilder.addColumn()">
                                    <span>' . $this->__('+ Add Column') . '</span>
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div id="csv-preview-panel" style="display: none; margin-top: 15px; border: 1px solid #ccc; border-radius: 4px; overflow: hidden;">
                <div style="padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ccc; display: flex; align-items: center; gap: 10px;">
                    <span style="font-weight: 500;">' . $this->__('Preview') . '</span>
                    <span style="color: #666;">(<span id="csv-preview-count">0</span> ' . $this->__('sample products') . ')</span>
                    <button type="button" class="scalable" onclick="CsvBuilder.refreshPreview()"><span>' . $this->__('Refresh') . '</span></button>
                    <button type="button" class="scalable" onclick="CsvBuilder.copyPreview()"><span>' . $this->__('Copy') . '</span></button>
                </div>
                <pre id="csv-preview-content" style="margin: 0; padding: 15px; max-height: 400px; overflow: auto; font-size: 12px; background: #1e1e1e; color: #d4d4d4; white-space: pre; word-wrap: normal;"></pre>
            </div>
        </div>

        <div id="csv-import-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; min-width: 500px;">
                <h3 style="margin-top: 0;">' . $this->__('Import CSV Structure') . '</h3>
                <p>' . $this->__('Paste a header row or sample CSV:') . '</p>
                <textarea id="csv-import-input" style="width: 100%; height: 100px; font-family: monospace;" placeholder="id,title,price,description,link"></textarea>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" class="scalable" onclick="CsvBuilder.hideImportModal()">
                        <span>' . $this->__('Cancel') . '</span>
                    </button>
                    <button type="button" class="scalable save" onclick="CsvBuilder.importColumns()">
                        <span>' . $this->__('Import Columns') . '</span>
                    </button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        var CsvBuilder = {
            columns: ' . Mage::helper('core')->jsonEncode($columnsData) . ',
            sourceTypes: ' . Mage::helper('core')->jsonEncode($sourceTypes) . ',
            attributeOptionsHtml: ' . Mage::helper('core')->jsonEncode($attributeOptions) . ',
            previewUrl: "' . $this->getUrl('*/*/csvPreview') . '",
            presetUrl: "' . $this->getUrl('*/*/platformPreset') . '",
            feedId: ' . (int) $feed->getId() . ',

            init: function() {
                this.render();
            },

            render: function() {
                var tbody = document.getElementById("csv-grid-body");
                if (!tbody) return;
                tbody.innerHTML = "";

                for (var i = 0; i < this.columns.length; i++) {
                    tbody.appendChild(this.createRow(this.columns[i], i));
                }

                this.updateHiddenField();
            },

            createRow: function(col, index) {
                var tr = document.createElement("tr");
                tr.className = "csv-row";
                tr.dataset.index = index;
                tr.draggable = true;

                // Drag handle
                var tdDrag = document.createElement("td");
                tdDrag.innerHTML = "<span class=\\"csv-drag-handle\\" style=\\"cursor: move; color: #999;\\">⋮⋮</span>";
                tdDrag.style.textAlign = "center";
                tr.appendChild(tdDrag);

                // Column name
                var tdName = document.createElement("td");
                tdName.innerHTML = "<input type=\\"text\\" class=\\"input-text\\" value=\\"" + this.escapeHtml(col.name || "") + "\\" onchange=\\"CsvBuilder.updateColumn(" + index + ", \'name\', this.value)\\" style=\\"width: 100%;\\">";
                tr.appendChild(tdName);

                // Source type
                var tdType = document.createElement("td");
                var typeSelect = "<select onchange=\\"CsvBuilder.updateColumn(" + index + ", \'source_type\', this.value); CsvBuilder.render();\\" style=\\"width: 100%;\\">";
                for (var key in this.sourceTypes) {
                    var selected = col.source_type === key ? " selected" : "";
                    typeSelect += "<option value=\\"" + key + "\\"" + selected + ">" + this.sourceTypes[key] + "</option>";
                }
                typeSelect += "</select>";
                tdType.innerHTML = typeSelect;
                tr.appendChild(tdType);

                // Source value
                var tdValue = document.createElement("td");
                if (col.source_type === "attribute" || col.source_type === "custom_field" || !col.source_type) {
                    var selectHtml = this.attributeOptionsHtml.replace(new RegExp("value=\\"" + this.escapeHtml(col.source_value || "") + "\\""), "value=\\"" + this.escapeHtml(col.source_value || "") + "\\" selected");
                    tdValue.innerHTML = "<select onchange=\\"CsvBuilder.updateColumn(" + index + ", \'source_value\', this.value)\\" style=\\"width: 100%;\\">" + selectHtml + "</select>";
                } else {
                    var placeholder = col.source_type === "combined" ? "{{manufacturer}} - {{name}}" : "";
                    tdValue.innerHTML = "<input type=\\"text\\" class=\\"input-text\\" value=\\"" + this.escapeHtml(col.source_value || "") + "\\" onchange=\\"CsvBuilder.updateColumn(" + index + ", \'source_value\', this.value)\\" placeholder=\\"" + placeholder + "\\" style=\\"width: 100%;\\">";
                }
                tr.appendChild(tdValue);

                // Use Parent select
                var tdParent = document.createElement("td");
                tdParent.style.textAlign = "center";
                var parentVal = col.use_parent || "";
                var selectHtml = "<select style=\\"width: 70px; font-size: 11px; padding: 2px;\\" onchange=\\"CsvBuilder.updateColumn(" + index + ", \'use_parent\', this.value)\\">";
                selectHtml += "<option value=\\"\\"" + (parentVal === "" ? " selected" : "") + ">—</option>";
                selectHtml += "<option value=\\"if_empty\\"" + (parentVal === "if_empty" ? " selected" : "") + ">' . addslashes($this->__('If empty')) . '</option>";
                selectHtml += "<option value=\\"always\\"" + (parentVal === "always" ? " selected" : "") + ">' . addslashes($this->__('Always')) . '</option>";
                selectHtml += "</select>";
                tdParent.innerHTML = selectHtml;
                tr.appendChild(tdParent);

                // Transformers
                var tdTransform = document.createElement("td");
                var transformCount = col.transformers ? col.transformers.split("|").filter(function(t) { return t.trim(); }).length : 0;
                var btnLabel = transformCount > 0 ? transformCount + " ✎" : "+ Add";
                tdTransform.innerHTML = "<button type=\\"button\\" class=\\"scalable\\" onclick=\\"CsvBuilder.openTransformers(" + index + ")\\" style=\\"white-space: nowrap;\\"><span>" + btnLabel + "</span></button>";
                tr.appendChild(tdTransform);

                // Actions (duplicate + delete)
                var tdActions = document.createElement("td");
                tdActions.innerHTML = "<button type=\\"button\\" class=\\"scalable\\" onclick=\\"CsvBuilder.duplicateColumn(" + index + ")\\" title=\\"Duplicate\\" style=\\"padding: 2px 8px; margin-right: 4px;\\"><span>⧉</span></button>" +
                    "<button type=\\"button\\" class=\\"scalable delete\\" onclick=\\"CsvBuilder.removeColumn(" + index + ")\\" title=\\"Delete\\" style=\\"padding: 2px 8px;\\"><span>×</span></button>";
                tr.appendChild(tdActions);

                // Drag events
                var self = this;
                tr.addEventListener("dragstart", function(e) {
                    e.dataTransfer.setData("text/plain", index);
                    tr.classList.add("dragging");
                });
                tr.addEventListener("dragend", function() {
                    tr.classList.remove("dragging");
                });
                tr.addEventListener("dragover", function(e) {
                    e.preventDefault();
                });
                tr.addEventListener("drop", function(e) {
                    e.preventDefault();
                    var fromIndex = parseInt(e.dataTransfer.getData("text/plain"));
                    var toIndex = parseInt(tr.dataset.index);
                    self.moveColumn(fromIndex, toIndex);
                });

                return tr;
            },

            addColumn: function() {
                this.columns.push({name: "", source_type: "attribute", source_value: "", use_parent: "", transformers: ""});
                this.render();
            },

            duplicateColumn: function(index) {
                var original = this.columns[index];
                var copy = JSON.parse(JSON.stringify(original));
                copy.name = copy.name + "_copy";
                this.columns.splice(index + 1, 0, copy);
                this.render();
            },

            removeColumn: function(index) {
                this.columns.splice(index, 1);
                this.render();
            },

            updateColumn: function(index, field, value) {
                this.columns[index][field] = value;
                this.updateHiddenField();
            },

            moveColumn: function(fromIndex, toIndex) {
                if (fromIndex === toIndex) return;
                var col = this.columns.splice(fromIndex, 1)[0];
                this.columns.splice(toIndex, 0, col);
                this.render();
            },

            openTransformers: function(index) {
                CsvBuilder.currentColumnIndex = index;
                var current = this.columns[index].transformers || "";
                document.getElementById("editor_transformers").value = current;
                TransformerModal.open();
            },

            updateHiddenField: function() {
                var field = document.getElementById("mapping_csv_columns");
                if (field) {
                    field.value = JSON.stringify(this.columns);
                }
            },

            loadPreset: function(platform) {
                if (!platform) return;
                var self = this;

                mahoFetch(this.presetUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        platform: platform,
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        alert("Error: " + data.message);
                    } else if (data.columns) {
                        self.columns = data.columns;
                        self.render();
                        // Update platform field in General tab
                        self.updatePlatform(data.platform);
                    }
                })
                .catch(function(err) {
                    alert("Error loading preset: " + err.message);
                });

                document.getElementById("csv-preset-select").value = "";
            },

            updatePlatform: function(platform) {
                var platformField = document.getElementById("platform") || document.querySelector("input[name=\'platform\']");
                if (platformField) {
                    platformField.value = platform;
                }
                var badge = document.getElementById("csv-platform-badge");
                if (badge) {
                    badge.textContent = platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : "";
                    badge.style.display = platform ? "inline-block" : "none";
                }
            },

            showImportModal: function() {
                document.getElementById("csv-import-modal").style.display = "block";
            },

            hideImportModal: function() {
                document.getElementById("csv-import-modal").style.display = "none";
                document.getElementById("csv-import-input").value = "";
            },

            importColumns: function() {
                var input = document.getElementById("csv-import-input").value.trim();
                if (!input) return;

                // Parse first line as headers
                var firstLine = input.split("\\n")[0];
                var headers = firstLine.split(/[,\\t;|]/).map(function(h) {
                    return h.trim().replace(/^["\']+|["\']+$/g, "");
                });

                this.columns = headers.map(function(h) {
                    return {name: h, source_type: "attribute", source_value: "", use_parent: "", transformers: ""};
                });

                this.render();
                this.hideImportModal();
            },

            togglePreview: function() {
                var panel = document.getElementById("csv-preview-panel");
                var label = document.getElementById("csv-preview-toggle-label");
                if (panel.style.display === "none") {
                    panel.style.display = "block";
                    label.textContent = "' . $this->__('Hide Preview') . '";
                    this.refreshPreview();
                } else {
                    panel.style.display = "none";
                    label.textContent = "' . $this->__('Show Preview') . '";
                }
            },

            refreshPreview: function() {
                var content = document.getElementById("csv-preview-content");
                content.textContent = "' . $this->__('Loading...') . '";

                mahoFetch(this.previewUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        id: this.feedId,
                        columns: JSON.stringify(this.columns),
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        content.textContent = "Error: " + data.message;
                    } else {
                        content.textContent = data.preview;
                        document.getElementById("csv-preview-count").textContent = data.count;
                    }
                })
                .catch(function(err) {
                    content.textContent = "Error loading preview";
                });
            },

            copyPreview: function() {
                var content = document.getElementById("csv-preview-content").textContent;
                navigator.clipboard.writeText(content);
            },

            escapeHtml: function(str) {
                if (!str) return "";
                return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            }
        };

        document.addEventListener("DOMContentLoaded", function() {
            CsvBuilder.init();
        });
        </script>

        <style>
        #csv-builder-container { max-width: 100%; }
        #csv-grid-container { max-width: 100%; overflow-x: auto; }
        .csv-grid { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 700px; }
        .csv-grid th, .csv-grid td { padding: 8px; border: 1px solid #ddd; overflow: hidden; text-overflow: ellipsis; }
        .csv-grid th { background: #f5f5f5; text-align: left; font-weight: 600; }
        .csv-grid th:nth-child(1), .csv-grid td:nth-child(1) { width: 30px; }
        .csv-grid th:nth-child(2), .csv-grid td:nth-child(2) { width: 18%; }
        .csv-grid th:nth-child(3), .csv-grid td:nth-child(3) { width: 15%; }
        .csv-grid th:nth-child(4), .csv-grid td:nth-child(4) { width: auto; }
        .csv-grid th:nth-child(5), .csv-grid td:nth-child(5) { width: 100px; }
        .csv-grid th:nth-child(6), .csv-grid td:nth-child(6) { width: 70px; }
        .csv-grid input, .csv-grid select { width: 100%; box-sizing: border-box; }
        .csv-row:hover { background: #f9f9f9; }
        .csv-row.dragging { opacity: 0.5; }
        .csv-drag-handle:hover { color: #333 !important; }
        #csv-preview-panel { max-width: 100%; }
        #csv-preview-panel pre { overflow-x: auto; white-space: pre; }
        </style>
        ';
    }

    /**
     * Get JSON Builder HTML - Tree view with properties panel
     */
    protected function _getJsonBuilderHtml(): string
    {
        $feed = $this->_getFeed();
        $structure = $feed->getJsonStructure();
        $structureData = $structure ? Mage::helper('core')->jsonDecode($structure) : new stdClass();
        $sourceTypes = Maho_FeedManager_Model_Mapper::getSourceTypeOptions();
        $attributeOptions = $this->_getProductAttributeOptionsForEditor();
        $platformOptions = $this->_getPlatformPresetOptions();

        return '
        <div id="json-builder-container">
            <div class="json-toolbar" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                <select id="json-preset-select" onchange="JsonBuilder.loadPreset(this.value)" style="min-width: 150px;">
                    <option value="">' . $this->__('Load Preset...') . '</option>
                    ' . $platformOptions . '
                </select>
                <span id="json-platform-badge" style="display: ' . ($feed->getPlatform() && $feed->getPlatform() !== 'custom' ? 'inline-block' : 'none') . '; padding: 3px 8px; background: #e3f2fd; color: #1565c0; border-radius: 3px; font-size: 11px; font-weight: 500;">' . ucfirst($feed->getPlatform() ?: '') . '</span>
                <button type="button" class="scalable" onclick="JsonBuilder.showImportModal()">
                    <span>' . $this->__('Import JSON') . '</span>
                </button>
                <div style="flex-grow: 1;"></div>
                <button type="button" class="scalable" onclick="JsonBuilder.togglePreview()">
                    <span id="json-preview-toggle-label">' . $this->__('Show Preview') . '</span>
                </button>
            </div>

            <div id="json-builder-panels" style="display: flex; gap: 20px; max-height: 600px; overflow-y: auto; align-items: flex-start;">
                <!-- Tree Panel -->
                <div id="json-tree-panel" style="flex: 1; border: 1px solid #ddd; border-radius: 4px; min-height: 400px;">
                    <div style="padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ddd; font-weight: 600;">
                        ' . $this->__('Structure') . '
                    </div>
                    <div id="json-tree" style="padding: 10px; min-height: 300px;">
                    </div>
                    <div style="padding: 10px; border-top: 1px solid #ddd; display: flex; gap: 10px;">
                        <button type="button" class="scalable add" onclick="JsonBuilder.addField()">
                            <span>' . $this->__('+ Field') . '</span>
                        </button>
                        <button type="button" class="scalable" onclick="JsonBuilder.addObject()">
                            <span>' . $this->__('+ Object') . '</span>
                        </button>
                        <button type="button" class="scalable" onclick="JsonBuilder.addArray()">
                            <span>' . $this->__('+ Array') . '</span>
                        </button>
                    </div>
                </div>

                <!-- Properties Panel -->
                <div id="json-properties-panel" style="width: 350px; border: 1px solid #ddd; border-radius: 4px; position: sticky; top: 0; align-self: flex-start; max-height: 580px; overflow-y: auto;">
                    <div style="padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ddd; font-weight: 600; position: sticky; top: 0; z-index: 1;">
                        ' . $this->__('Properties') . '
                    </div>
                    <div id="json-properties-content" style="padding: 15px;">
                        <p style="color: #666; text-align: center;">' . $this->__('Select a node to edit its properties') . '</p>
                    </div>
                </div>
            </div>

            <div id="json-preview-panel" style="display: none; margin-top: 15px; border: 1px solid #ccc; border-radius: 4px; overflow: hidden;">
                <div style="padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ccc; display: flex; align-items: center; gap: 10px;">
                    <span style="font-weight: 500;">' . $this->__('Preview') . '</span>
                    <button type="button" class="scalable" onclick="JsonBuilder.refreshPreview()"><span>' . $this->__('Refresh') . '</span></button>
                    <button type="button" class="scalable" onclick="JsonBuilder.copyPreview()"><span>' . $this->__('Copy') . '</span></button>
                    <button type="button" class="scalable" onclick="JsonBuilder.validateJson()"><span>' . $this->__('Validate') . '</span></button>
                    <span id="json-validation-status" style="font-size: 12px;"></span>
                </div>
                <pre id="json-preview-content" style="margin: 0; padding: 15px; max-height: 400px; overflow: auto; font-size: 12px; background: #1e1e1e; color: #d4d4d4; white-space: pre; word-wrap: normal;"></pre>
            </div>
        </div>

        <div id="json-import-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; min-width: 500px;">
                <h3 style="margin-top: 0;">' . $this->__('Import JSON Structure') . '</h3>
                <p>' . $this->__('Paste a sample JSON object:') . '</p>
                <textarea id="json-import-input" style="width: 100%; height: 150px; font-family: monospace;" placeholder=\'{"id": "SKU123", "title": "Product Name"}\'></textarea>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" class="scalable" onclick="JsonBuilder.hideImportModal()"><span>' . $this->__('Cancel') . '</span></button>
                    <button type="button" class="scalable save" onclick="JsonBuilder.importStructure()"><span>' . $this->__('Import') . '</span></button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        var JsonBuilder = {
            structure: ' . Mage::helper('core')->jsonEncode($structureData) . ',
            sourceTypes: ' . Mage::helper('core')->jsonEncode($sourceTypes) . ',
            attributeOptionsHtml: ' . Mage::helper('core')->jsonEncode($attributeOptions) . ',
            selectedPath: null,
            previewUrl: "' . $this->getUrl('*/*/jsonPreview') . '",
            presetUrl: "' . $this->getUrl('*/*/platformPreset') . '",
            feedId: ' . (int) $feed->getId() . ',

            init: function() {
                if (!this.structure || Object.keys(this.structure).length === 0) {
                    this.structure = {};
                }
                this.render();
            },

            render: function() {
                var tree = document.getElementById("json-tree");
                if (!tree) return;
                tree.innerHTML = this.renderNode(this.structure, "", 0);
                this.updateHiddenField();
            },

            renderNode: function(node, path, depth) {
                var html = "";
                var indent = depth * 20;

                for (var key in node) {
                    if (!node.hasOwnProperty(key)) continue;
                    var item = node[key];
                    var itemPath = path ? path + "." + key : key;
                    var isSelected = this.selectedPath === itemPath;
                    var nodeClass = "json-node" + (isSelected ? " selected" : "");

                    if (item.type === "object" && item.properties) {
                        html += "<div class=\\"" + nodeClass + "\\" style=\\"padding-left: " + indent + "px;\\" onclick=\\"JsonBuilder.selectNode(\'" + itemPath + "\')\\" data-path=\\"" + itemPath + "\\">";
                        html += "<span class=\\"json-toggle\\" onclick=\\"JsonBuilder.toggleNode(event, \'" + itemPath + "\')\\">&blacktriangledown;</span> ";
                        html += "<span class=\\"json-key\\">" + this.escapeHtml(key) + "</span> <span class=\\"json-type\\">(object)</span>";
                        html += "</div>";
                        html += "<div class=\\"json-children\\" id=\\"json-children-" + itemPath.replace(/\\./g, "-") + "\\">";
                        html += this.renderNode(item.properties, itemPath + ".properties", depth + 1);
                        html += "</div>";
                    } else if (item.type === "array") {
                        html += "<div class=\\"" + nodeClass + "\\" style=\\"padding-left: " + indent + "px;\\" onclick=\\"JsonBuilder.selectNode(\'" + itemPath + "\')\\" data-path=\\"" + itemPath + "\\">";
                        html += "<span class=\\"json-toggle\\" onclick=\\"JsonBuilder.toggleNode(event, \'" + itemPath + "\')\\">&blacktriangledown;</span> ";
                        html += "<span class=\\"json-key\\">" + this.escapeHtml(key) + "</span> <span class=\\"json-type\\">(array)</span>";
                        html += "</div>";
                        if (item.items) {
                            html += "<div class=\\"json-children\\" id=\\"json-children-" + itemPath.replace(/\\./g, "-") + "\\">";
                            html += "<div class=\\"json-node\\" style=\\"padding-left: " + (indent + 20) + "px; color: #666;\\">";
                            html += "└─ " + (item.items.type || "string");
                            html += "</div>";
                            html += "</div>";
                        }
                    } else {
                        html += "<div class=\\"" + nodeClass + "\\" style=\\"padding-left: " + indent + "px;\\" onclick=\\"JsonBuilder.selectNode(\'" + itemPath + "\')\\" data-path=\\"" + itemPath + "\\">";
                        html += "<span class=\\"json-key\\">" + this.escapeHtml(key) + "</span> <span class=\\"json-type\\">(" + (item.type || "string") + ")</span>";
                        html += "</div>";
                    }
                }

                return html || "<p style=\\"color: #666; padding: 10px;\\">' . addslashes($this->__('Empty. Add fields using the buttons below.')) . '</p>";
            },

            selectNode: function(path) {
                this.selectedPath = path;
                this.render();
                this.showProperties(path);
            },

            showProperties: function(path) {
                var node = this.getNodeByPath(path);
                if (!node) return;

                var panel = document.getElementById("json-properties-content");
                var keyName = path.split(".").pop();

                var html = "<div style=\\"margin-bottom: 15px;\\">" +
                    "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Key') . '</label>" +
                    "<input type=\\"text\\" class=\\"input-text\\" value=\\"" + this.escapeHtml(keyName) + "\\" onchange=\\"JsonBuilder.updateKey(\'" + path + "\', this.value)\\" style=\\"width: 100%;\\">" +
                    "</div>" +
                    "<div style=\\"margin-bottom: 15px;\\">" +
                    "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Type') . '</label>" +
                    "<select onchange=\\"JsonBuilder.updateNodeProp(\'" + path + "\', \'type\', this.value)\\" style=\\"width: 100%;\\">" +
                    "<option value=\\"string\\"" + (node.type === "string" || !node.type ? " selected" : "") + ">' . $this->__('String') . '</option>" +
                    "<option value=\\"number\\"" + (node.type === "number" ? " selected" : "") + ">' . $this->__('Number') . '</option>" +
                    "<option value=\\"boolean\\"" + (node.type === "boolean" ? " selected" : "") + ">' . $this->__('Boolean') . '</option>" +
                    "<option value=\\"object\\"" + (node.type === "object" ? " selected" : "") + ">' . $this->__('Object') . '</option>" +
                    "<option value=\\"array\\"" + (node.type === "array" ? " selected" : "") + ">' . $this->__('Array') . '</option>" +
                    "</select>" +
                    "</div>";

                if (node.type !== "object" && node.type !== "array") {
                    html += "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Source Type') . '</label>" +
                        "<select onchange=\\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_type\', this.value); JsonBuilder.showProperties(\'" + path + "\');\\" style=\\"width: 100%;\\">" +
                        this.buildSourceTypeOptions(node.source_type) +
                        "</select>" +
                        "</div>" +
                        "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Source Value') . '</label>";

                    // Show attribute dropdown or text input based on source type
                    if (node.source_type === "attribute") {
                        var selectHtml = this.attributeOptionsHtml.replace(new RegExp("value=\\"" + this.escapeHtml(node.source_value || "") + "\\""), "value=\\"" + this.escapeHtml(node.source_value || "") + "\\" selected");
                        html += "<select onchange=\\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\\" style=\\"width: 100%;\\">" + selectHtml + "</select>";
                    } else {
                        var placeholder = node.source_type === "combined" ? "{{name}} - {{sku}}" : "";
                        html += "<input type=\\"text\\" class=\\"input-text\\" value=\\"" + this.escapeHtml(node.source_value || "") + "\\" onchange=\\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\\" placeholder=\\"" + placeholder + "\\" style=\\"width: 100%;\\">";
                    }

                    html += "</div>" +
                        "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Use Parent') . '</label>" +
                        "<select onchange=\\"JsonBuilder.updateNodeProp(\'" + path + "\', \'use_parent\', this.value)\\" style=\\"width: 100%;\\">" +
                        "<option value=\\"\\"" + (!node.use_parent ? " selected" : "") + ">—</option>" +
                        "<option value=\\"if_empty\\"" + (node.use_parent === "if_empty" ? " selected" : "") + ">' . $this->__('If empty') . '</option>" +
                        "<option value=\\"always\\"" + (node.use_parent === "always" ? " selected" : "") + ">' . $this->__('Always') . '</option>" +
                        "</select>" +
                        "</div>" +
                        "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Transformers') . '</label>" +
                        "<button type=\\"button\\" class=\\"scalable\\" onclick=\\"JsonBuilder.openTransformers(\'" + path + "\')\\"><span>" + (node.transformers ? node.transformers.split("|").length + " transforms" : "+ Add") + "</span></button>" +
                        "</div>";
                }

                html += "<div style=\\"border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px; display: flex; gap: 10px;\\">" +
                    "<button type=\\"button\\" class=\\"scalable\\" onclick=\\"JsonBuilder.duplicateNode(\'" + path + "\')\\"><span>' . $this->__('Duplicate') . '</span></button>" +
                    "<button type=\\"button\\" class=\\"scalable delete\\" onclick=\\"JsonBuilder.deleteNode(\'" + path + "\')\\"><span>' . $this->__('Delete') . '</span></button>" +
                    "</div>";

                panel.innerHTML = html;
            },

            buildSourceTypeOptions: function(selected) {
                var html = "";
                for (var key in this.sourceTypes) {
                    html += "<option value=\\"" + key + "\\"" + (selected === key ? " selected" : "") + ">" + this.sourceTypes[key] + "</option>";
                }
                return html;
            },

            escapeHtml: function(str) {
                if (!str) return "";
                return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            },

            getNodeByPath: function(path) {
                var parts = path.split(".");
                var current = this.structure;
                for (var i = 0; i < parts.length; i++) {
                    if (!current[parts[i]]) return null;
                    current = current[parts[i]];
                }
                return current;
            },

            setNodeByPath: function(path, value) {
                var parts = path.split(".");
                var current = this.structure;
                for (var i = 0; i < parts.length - 1; i++) {
                    current = current[parts[i]];
                }
                current[parts[parts.length - 1]] = value;
            },

            updateNodeProp: function(path, prop, value) {
                var node = this.getNodeByPath(path);
                if (node) {
                    node[prop] = value;
                    this.render();
                    this.showProperties(path);
                }
            },

            updateKey: function(path, newKey) {
                var parts = path.split(".");
                var oldKey = parts.pop();
                var parent = parts.length > 0 ? this.getNodeByPath(parts.join(".")) : this.structure;

                if (parent && parent[oldKey]) {
                    parent[newKey] = parent[oldKey];
                    delete parent[oldKey];
                    this.selectedPath = parts.length > 0 ? parts.join(".") + "." + newKey : newKey;
                    this.render();
                    this.showProperties(this.selectedPath);
                }
            },

            addField: function() {
                var name = "field_" + Object.keys(this.structure).length;
                this.structure[name] = {type: "string", source_type: "attribute", source_value: ""};
                this.render();
                this.selectNode(name);
            },

            addObject: function() {
                var name = "object_" + Object.keys(this.structure).length;
                this.structure[name] = {type: "object", properties: {}};
                this.render();
                this.selectNode(name);
            },

            addArray: function() {
                var name = "array_" + Object.keys(this.structure).length;
                this.structure[name] = {type: "array", items: {type: "string", source_type: "attribute", source_value: ""}};
                this.render();
                this.selectNode(name);
            },

            deleteNode: function(path) {
                var parts = path.split(".");
                var key = parts.pop();
                var parent = parts.length > 0 ? this.getNodeByPath(parts.join(".")) : this.structure;
                if (parent) {
                    delete parent[key];
                    this.selectedPath = null;
                    this.render();
                    document.getElementById("json-properties-content").innerHTML = "<p style=\\"color: #666; text-align: center;\\">' . addslashes($this->__('Select a node to edit its properties')) . '</p>";
                }
            },

            duplicateNode: function(path) {
                var parts = path.split(".");
                var key = parts.pop();
                var parent = parts.length > 0 ? this.getNodeByPath(parts.join(".")) : this.structure;
                if (parent && parent[key]) {
                    var copy = JSON.parse(JSON.stringify(parent[key]));
                    var newKey = key + "_copy";
                    var counter = 1;
                    while (parent[newKey]) {
                        newKey = key + "_copy" + counter;
                        counter++;
                    }
                    parent[newKey] = copy;
                    var newPath = parts.length > 0 ? parts.join(".") + "." + newKey : newKey;
                    this.render();
                    this.selectNode(newPath);
                }
            },

            toggleNode: function(e, path) {
                e.stopPropagation();
                var children = document.getElementById("json-children-" + path.replace(/\\./g, "-"));
                var toggle = e.target;
                if (children) {
                    if (children.style.display === "none") {
                        children.style.display = "block";
                        toggle.innerHTML = "&blacktriangledown;";
                    } else {
                        children.style.display = "none";
                        toggle.innerHTML = "&blacktriangleright;";
                    }
                }
            },

            updateHiddenField: function() {
                var field = document.getElementById("mapping_json_structure");
                if (field) {
                    field.value = JSON.stringify(this.structure);
                }
            },

            openTransformers: function(path) {
                JsonBuilder.currentNodePath = path;
                var node = this.getNodeByPath(path);
                document.getElementById("editor_transformers").value = node.transformers || "";
                TransformerModal.open();
            },

            loadPreset: function(platform) {
                if (!platform) return;
                var self = this;

                mahoFetch(this.presetUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        platform: platform,
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        alert("Error: " + data.message);
                    } else if (data.structure) {
                        self.structure = data.structure;
                        self.selectedPath = null;
                        self.render();
                        document.getElementById("json-properties-content").innerHTML = "<p style=\\"color: #666; text-align: center;\\">' . addslashes($this->__('Select a node to edit its properties')) . '</p>";
                        // Update platform field in General tab
                        self.updatePlatform(data.platform);
                    }
                })
                .catch(function(err) {
                    alert("Error loading preset: " + err.message);
                });

                document.getElementById("json-preset-select").value = "";
            },

            updatePlatform: function(platform) {
                var platformField = document.getElementById("platform") || document.querySelector("input[name=\'platform\']");
                if (platformField) {
                    platformField.value = platform;
                }
                var badge = document.getElementById("json-platform-badge");
                if (badge) {
                    badge.textContent = platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : "";
                    badge.style.display = platform ? "inline-block" : "none";
                }
            },

            showImportModal: function() {
                document.getElementById("json-import-modal").style.display = "block";
            },

            hideImportModal: function() {
                document.getElementById("json-import-modal").style.display = "none";
                document.getElementById("json-import-input").value = "";
            },

            importStructure: function() {
                try {
                    var input = document.getElementById("json-import-input").value.trim();
                    var parsed = JSON.parse(input);
                    this.structure = this.convertToBuilderFormat(parsed);
                    this.render();
                    this.hideImportModal();
                } catch (e) {
                    alert("Invalid JSON: " + e.message);
                }
            },

            convertToBuilderFormat: function(obj) {
                var result = {};
                for (var key in obj) {
                    var val = obj[key];
                    if (Array.isArray(val)) {
                        result[key] = {type: "array", items: {type: "string", source_type: "attribute", source_value: ""}};
                    } else if (typeof val === "object" && val !== null) {
                        result[key] = {type: "object", properties: this.convertToBuilderFormat(val)};
                    } else if (typeof val === "number") {
                        result[key] = {type: "number", source_type: "attribute", source_value: ""};
                    } else if (typeof val === "boolean") {
                        result[key] = {type: "boolean", source_type: "attribute", source_value: ""};
                    } else {
                        result[key] = {type: "string", source_type: "attribute", source_value: ""};
                    }
                }
                return result;
            },

            togglePreview: function() {
                var panel = document.getElementById("json-preview-panel");
                var label = document.getElementById("json-preview-toggle-label");
                if (panel.style.display === "none") {
                    panel.style.display = "block";
                    label.textContent = "' . $this->__('Hide Preview') . '";
                    this.refreshPreview();
                } else {
                    panel.style.display = "none";
                    label.textContent = "' . $this->__('Show Preview') . '";
                }
            },

            refreshPreview: function() {
                var content = document.getElementById("json-preview-content");
                content.textContent = "' . $this->__('Loading...') . '";

                mahoFetch(this.previewUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        id: this.feedId,
                        structure: JSON.stringify(this.structure),
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        content.textContent = "Error: " + data.message;
                    } else {
                        content.textContent = data.preview;
                    }
                })
                .catch(function(err) {
                    content.textContent = "Error loading preview";
                });
            },

            copyPreview: function() {
                navigator.clipboard.writeText(document.getElementById("json-preview-content").textContent);
            },

            validateJson: function() {
                var content = document.getElementById("json-preview-content").textContent;
                var status = document.getElementById("json-validation-status");

                if (!content || content.trim() === "") {
                    status.innerHTML = \'<span style="color: #666;">' . $this->__('No content to validate') . '</span>\';
                    return;
                }

                try {
                    JSON.parse(content);
                    status.innerHTML = \'<span style="color: #2e7d32;">&#10004; ' . $this->__('Valid JSON') . '</span>\';
                } catch (e) {
                    var errorMsg = e.message || "' . $this->__('Invalid JSON') . '";
                    status.innerHTML = \'<span style="color: #c62828;">&#10008; \' + this.escapeHtml(errorMsg) + \'</span>\';
                }
            },

            escapeHtml: function(str) {
                return String(str || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            }
        };

        document.addEventListener("DOMContentLoaded", function() {
            JsonBuilder.init();
        });
        </script>

        <style>
        .json-node { padding: 5px 10px; cursor: pointer; border-radius: 3px; margin: 2px 0; }
        .json-node:hover { background: #f5f5f5; }
        .json-node.selected { background: #e3f2fd; }
        .json-key { font-weight: 600; color: #1976d2; }
        .json-type { color: #666; font-size: 11px; }
        .json-toggle { cursor: pointer; color: #666; }
        .json-children { margin-left: 10px; }
        </style>
        ';
    }

    /**
     * Get XML Builder HTML - Tree view with properties panel (similar to JSON Builder)
     */
    protected function _getXmlBuilderHtml(): string
    {
        $feed = $this->_getFeed();
        $structure = $feed->getXmlStructure();
        $structureData = $structure ? Mage::helper('core')->jsonDecode($structure) : $this->_getDefaultXmlStructure();
        $sourceTypes = Maho_FeedManager_Model_Mapper::getSourceTypeOptions();
        $attributeOptions = $this->_getProductAttributeOptionsForEditor();
        $platformOptions = $this->_getPlatformPresetOptions();

        return '
        <div id="xml-builder-container">
            <div class="xml-toolbar" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                <select id="xml-preset-select" onchange="XmlBuilder.loadPreset(this.value)" style="min-width: 150px;">
                    <option value="">' . $this->__('Load Preset...') . '</option>
                    ' . $platformOptions . '
                </select>
                <span id="xml-platform-badge" style="display: ' . ($feed->getPlatform() && $feed->getPlatform() !== 'custom' ? 'inline-block' : 'none') . '; padding: 3px 8px; background: #e3f2fd; color: #1565c0; border-radius: 3px; font-size: 11px; font-weight: 500;">' . ucfirst($feed->getPlatform() ?: '') . '</span>
                <button type="button" class="scalable" onclick="XmlBuilder.showImportModal()">
                    <span>' . $this->__('Import XML') . '</span>
                </button>
                <div style="flex-grow: 1;"></div>
                <button type="button" class="scalable" onclick="XmlBuilder.togglePreview()">
                    <span id="xml-preview-toggle-label">' . $this->__('Show Preview') . '</span>
                </button>
            </div>

            <div id="xml-builder-panels" style="display: flex; gap: 20px; max-height: 600px; overflow-y: auto; align-items: flex-start;">
                <!-- Tree Panel -->
                <div id="xml-tree-panel" style="flex: 1; border: 1px solid #ddd; border-radius: 4px; min-height: 400px;">
                    <div style="padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ddd; font-weight: 600;">
                        ' . $this->__('Structure') . '
                    </div>
                    <div id="xml-tree" style="padding: 10px; min-height: 300px;">
                    </div>
                    <div style="padding: 10px; border-top: 1px solid #ddd; display: flex; gap: 10px;">
                        <button type="button" class="scalable add" onclick="XmlBuilder.addElement()">
                            <span>' . $this->__('+ Element') . '</span>
                        </button>
                        <button type="button" class="scalable" onclick="XmlBuilder.addGroup()">
                            <span>' . $this->__('+ Group') . '</span>
                        </button>
                    </div>
                </div>

                <!-- Properties Panel -->
                <div id="xml-properties-panel" style="width: 350px; border: 1px solid #ddd; border-radius: 4px; position: sticky; top: 0; align-self: flex-start; max-height: 580px; overflow-y: auto;">
                    <div style="padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ddd; font-weight: 600; position: sticky; top: 0; z-index: 1;">
                        ' . $this->__('Properties') . '
                    </div>
                    <div id="xml-properties-content" style="padding: 15px;">
                        <p style="color: #666; text-align: center;">' . $this->__('Select an element to edit its properties') . '</p>
                    </div>
                </div>
            </div>

            <div id="xml-preview-panel" style="display: none; margin-top: 15px; border: 1px solid #ccc; border-radius: 4px; overflow: hidden;">
                <div style="padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ccc; display: flex; align-items: center; gap: 10px;">
                    <span style="font-weight: 500;">' . $this->__('Preview') . '</span>
                    <button type="button" class="scalable" onclick="XmlBuilder.refreshPreview()"><span>' . $this->__('Refresh') . '</span></button>
                    <button type="button" class="scalable" onclick="XmlBuilder.copyPreview()"><span>' . $this->__('Copy') . '</span></button>
                    <button type="button" class="scalable" onclick="XmlBuilder.validateXml()"><span>' . $this->__('Validate') . '</span></button>
                    <span id="xml-validation-status" style="font-size: 12px;"></span>
                    <span style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" id="xml-full-preview" onchange="XmlBuilder.toggleFullPreview(this.checked)" />
                            ' . $this->__('Full Document') . '
                        </label>
                    </span>
                </div>
                <pre id="xml-preview-content" style="margin: 0; padding: 15px; max-height: 400px; overflow: auto; font-size: 12px; background: #1e1e1e; color: #d4d4d4; white-space: pre; word-wrap: normal;"></pre>
            </div>
        </div>

        <div id="xml-import-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; min-width: 500px;">
                <h3 style="margin-top: 0;">' . $this->__('Import XML Structure') . '</h3>
                <p>' . $this->__('Paste a sample XML item:') . '</p>
                <textarea id="xml-import-input" style="width: 100%; height: 150px; font-family: monospace;" placeholder=\'<item><g:id>SKU123</g:id><title>Product Name</title></item>\'></textarea>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" class="scalable" onclick="XmlBuilder.hideImportModal()"><span>' . $this->__('Cancel') . '</span></button>
                    <button type="button" class="scalable save" onclick="XmlBuilder.importStructure()"><span>' . $this->__('Import') . '</span></button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        var XmlBuilder = {
            structure: ' . Mage::helper('core')->jsonEncode($structureData) . ',
            sourceTypes: ' . Mage::helper('core')->jsonEncode($sourceTypes) . ',
            attributeOptionsHtml: ' . Mage::helper('core')->jsonEncode($attributeOptions) . ',
            selectedPath: null,
            previewUrl: "' . $this->getUrl('*/*/xmlPreview') . '",
            presetUrl: "' . $this->getUrl('*/*/platformPreset') . '",
            feedId: ' . (int) $feed->getId() . ',
            fullPreview: false,

            init: function() {
                if (!this.structure || !Array.isArray(this.structure) || this.structure.length === 0) {
                    this.structure = [];
                }
                this.render();
            },

            render: function() {
                var tree = document.getElementById("xml-tree");
                if (!tree) return;
                tree.innerHTML = this.renderNodes(this.structure, "", 0);
                this.updateHiddenField();
            },

            renderNodes: function(nodes, pathPrefix, depth) {
                var html = "";
                var indent = depth * 20;

                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];
                    var itemPath = pathPrefix ? pathPrefix + "." + i : String(i);
                    var isSelected = this.selectedPath === itemPath;
                    var nodeClass = "xml-node" + (isSelected ? " selected" : "");

                    if (node.children && node.children.length > 0) {
                        html += "<div class=\\"" + nodeClass + "\\" style=\\"padding-left: " + indent + "px;\\" onclick=\\"XmlBuilder.selectNode(\'" + itemPath + "\')\\" data-path=\\"" + itemPath + "\\">";
                        html += "<span class=\\"xml-toggle\\" onclick=\\"XmlBuilder.toggleNode(event, \'" + itemPath + "\')\\">&blacktriangledown;</span> ";
                        html += "<span class=\\"xml-tag\\">&lt;" + this.escapeHtml(node.tag) + "&gt;</span>";
                        if (node.cdata) html += " <span class=\\"xml-badge\\">CDATA</span>";
                        html += "</div>";
                        html += "<div class=\\"xml-children\\" id=\\"xml-children-" + itemPath.replace(/\\./g, "-") + "\\">";
                        html += this.renderNodes(node.children, itemPath + ".children", depth + 1);
                        html += "</div>";
                    } else {
                        html += "<div class=\\"" + nodeClass + "\\" style=\\"padding-left: " + indent + "px;\\" onclick=\\"XmlBuilder.selectNode(\'" + itemPath + "\')\\" data-path=\\"" + itemPath + "\\">";
                        html += "<span class=\\"xml-tag\\">&lt;" + this.escapeHtml(node.tag) + "&gt;</span>";
                        if (node.cdata) html += " <span class=\\"xml-badge\\">CDATA</span>";
                        if (node.optional) html += " <span class=\\"xml-badge optional\\">optional</span>";
                        html += "</div>";
                    }
                }

                return html || "<p style=\\"color: #666; padding: 10px;\\">' . addslashes($this->__('Empty. Add elements using the buttons below.')) . '</p>";
            },

            selectNode: function(path) {
                this.selectedPath = path;
                this.render();
                this.showProperties(path);
            },

            showProperties: function(path) {
                var node = this.getNodeByPath(path);
                if (!node) return;

                var panel = document.getElementById("xml-properties-content");

                var html = "<div style=\\"margin-bottom: 15px;\\">" +
                    "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Tag Name') . '</label>" +
                    "<input type=\\"text\\" class=\\"input-text\\" value=\\"" + this.escapeHtml(node.tag) + "\\" onchange=\\"XmlBuilder.updateNodeProp(\'" + path + "\', \'tag\', this.value)\\" style=\\"width: 100%;\\" placeholder=\\"g:id\\">" +
                    "</div>";

                // Show element properties if no children array, group properties if children array exists (even if empty)
                if (!Array.isArray(node.children)) {
                    html += "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Source Type') . '</label>" +
                        "<select onchange=\\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_type\', this.value); XmlBuilder.showProperties(\'" + path + "\');\\" style=\\"width: 100%;\\">" +
                        this.buildSourceTypeOptions(node.source_type) +
                        "</select>" +
                        "</div>" +
                        "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Source Value') . '</label>";

                    if (node.source_type === "attribute" || !node.source_type) {
                        var selectHtml = this.attributeOptionsHtml.replace(new RegExp("value=\\"" + this.escapeHtml(node.source_value || "") + "\\""), "value=\\"" + this.escapeHtml(node.source_value || "") + "\\" selected");
                        html += "<select onchange=\\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\\" style=\\"width: 100%;\\">" + selectHtml + "</select>";
                    } else {
                        var placeholder = node.source_type === "combined" ? "{{name}} - {{sku}}" : "";
                        html += "<input type=\\"text\\" class=\\"input-text\\" value=\\"" + this.escapeHtml(node.source_value || "") + "\\" onchange=\\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\\" placeholder=\\"" + placeholder + "\\" style=\\"width: 100%;\\">";
                    }

                    html += "</div>" +
                        "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Use Parent') . '</label>" +
                        "<select onchange=\\"XmlBuilder.updateNodeProp(\'" + path + "\', \'use_parent\', this.value)\\" style=\\"width: 100%;\\">" +
                        "<option value=\\"\\"" + (!node.use_parent ? " selected" : "") + ">—</option>" +
                        "<option value=\\"if_empty\\"" + (node.use_parent === "if_empty" ? " selected" : "") + ">' . $this->__('If empty') . '</option>" +
                        "<option value=\\"always\\"" + (node.use_parent === "always" ? " selected" : "") + ">' . $this->__('Always') . '</option>" +
                        "</select>" +
                        "<p style=\\"margin: 4px 0 0; font-size: 11px; color: #888;\\">' . $this->__('For simple products, use parent (configurable) value') . '</p>" +
                        "</div>" +
                        "<div style=\\"margin-bottom: 15px;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Transformers') . '</label>" +
                        "<button type=\\"button\\" class=\\"scalable\\" onclick=\\"XmlBuilder.openTransformers(\'" + path + "\')\\"><span>" + (node.transformers ? node.transformers.split("|").length + " transforms" : "+ Add") + "</span></button>" +
                        "</div>" +
                        "<div style=\\"display: flex; gap: 15px; margin-bottom: 12px;\\">" +
                        "<div style=\\"flex: 1;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('CDATA') . '</label>" +
                        "<select onchange=\\"XmlBuilder.updateNodeProp(\'" + path + "\', \'cdata\', this.value === \'1\')\\" style=\\"width: 100%;\\">" +
                        "<option value=\\"0\\"" + (!node.cdata ? " selected" : "") + ">' . $this->__('No') . '</option>" +
                        "<option value=\\"1\\"" + (node.cdata ? " selected" : "") + ">' . $this->__('Yes') . '</option>" +
                        "</select>" +
                        "</div>" +
                        "<div style=\\"flex: 1;\\">" +
                        "<label style=\\"font-weight: 600; display: block; margin-bottom: 5px;\\">' . $this->__('Optional') . '</label>" +
                        "<select onchange=\\"XmlBuilder.updateNodeProp(\'" + path + "\', \'optional\', this.value === \'1\')\\" style=\\"width: 100%;\\">" +
                        "<option value=\\"0\\"" + (!node.optional ? " selected" : "") + ">' . $this->__('No') . '</option>" +
                        "<option value=\\"1\\"" + (node.optional ? " selected" : "") + ">' . $this->__('Yes') . '</option>" +
                        "</select>" +
                        "</div>" +
                        "</div>" +
                        "<p style=\\"margin: 0 0 15px; font-size: 11px; color: #888;\\">' . $this->__('CDATA wraps special characters. Optional skips element if value is empty.') . '</p>";
                } else {
                    html += "<p style=\\"color: #666; font-size: 11px;\\">' . $this->__('This is a group element. Add child elements by selecting it and clicking + Element.') . '</p>" +
                        "<div style=\\"margin-top: 15px;\\">" +
                        "<button type=\\"button\\" class=\\"scalable add\\" onclick=\\"XmlBuilder.addChildElement(\'" + path + "\')\\"><span>' . $this->__('+ Child Element') . '</span></button>" +
                        "</div>";
                }

                html += "<div style=\\"border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px; display: flex; gap: 10px;\\">" +
                    "<button type=\\"button\\" class=\\"scalable\\" onclick=\\"XmlBuilder.duplicateNode(\'" + path + "\')\\"><span>' . $this->__('Duplicate') . '</span></button>" +
                    "<button type=\\"button\\" class=\\"scalable delete\\" onclick=\\"XmlBuilder.deleteNode(\'" + path + "\')\\"><span>' . $this->__('Delete') . '</span></button>" +
                    "</div>";

                panel.innerHTML = html;
            },

            buildSourceTypeOptions: function(selected) {
                var html = "";
                for (var key in this.sourceTypes) {
                    html += "<option value=\\"" + key + "\\"" + (selected === key ? " selected" : "") + ">" + this.sourceTypes[key] + "</option>";
                }
                return html;
            },

            getNodeByPath: function(path) {
                var parts = path.split(".");
                var current = this.structure;
                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i];
                    if (part === "children") {
                        current = current.children;
                    } else {
                        current = current[parseInt(part)];
                    }
                    if (!current) return null;
                }
                return current;
            },

            getParentAndIndex: function(path) {
                var parts = path.split(".");
                var index = parseInt(parts.pop());
                if (parts.length === 0) {
                    return { parent: this.structure, index: index };
                }
                var parentPath = parts.join(".");
                var parent = this.getNodeByPath(parentPath);
                return { parent: parent, index: index };
            },

            updateNodeProp: function(path, prop, value) {
                var node = this.getNodeByPath(path);
                if (node) {
                    node[prop] = value;
                    this.render();
                    this.showProperties(path);
                }
            },

            addElement: function() {
                this.structure.push({
                    tag: "element_" + this.structure.length,
                    source_type: "attribute",
                    source_value: "",
                    cdata: false,
                    optional: false
                });
                var newPath = String(this.structure.length - 1);
                this.render();
                this.selectNode(newPath);
            },

            addGroup: function() {
                this.structure.push({
                    tag: "group_" + this.structure.length,
                    children: []
                });
                var newPath = String(this.structure.length - 1);
                this.render();
                this.selectNode(newPath);
            },

            addChildElement: function(parentPath) {
                var parent = this.getNodeByPath(parentPath);
                if (!parent || !parent.children) return;
                parent.children.push({
                    tag: "element_" + parent.children.length,
                    source_type: "attribute",
                    source_value: "",
                    cdata: false,
                    optional: false
                });
                var newPath = parentPath + ".children." + (parent.children.length - 1);
                this.render();
                this.selectNode(newPath);
            },

            deleteNode: function(path) {
                var info = this.getParentAndIndex(path);
                if (info.parent && Array.isArray(info.parent)) {
                    info.parent.splice(info.index, 1);
                    this.selectedPath = null;
                    this.render();
                    document.getElementById("xml-properties-content").innerHTML = "<p style=\\"color: #666; text-align: center;\\">' . addslashes($this->__('Select an element to edit its properties')) . '</p>";
                }
            },

            duplicateNode: function(path) {
                var info = this.getParentAndIndex(path);
                if (info.parent && Array.isArray(info.parent)) {
                    var original = info.parent[info.index];
                    var copy = JSON.parse(JSON.stringify(original));
                    copy.tag = original.tag + "_copy";
                    info.parent.splice(info.index + 1, 0, copy);
                    var newPath = path.replace(/\\d+$/, String(info.index + 1));
                    this.render();
                    this.selectNode(newPath);
                }
            },

            toggleNode: function(e, path) {
                e.stopPropagation();
                var children = document.getElementById("xml-children-" + path.replace(/\\./g, "-"));
                var toggle = e.target;
                if (children) {
                    if (children.style.display === "none") {
                        children.style.display = "block";
                        toggle.innerHTML = "&blacktriangledown;";
                    } else {
                        children.style.display = "none";
                        toggle.innerHTML = "&blacktriangleright;";
                    }
                }
            },

            updateHiddenField: function() {
                var field = document.getElementById("mapping_xml_structure");
                if (field) {
                    field.value = JSON.stringify(this.structure);
                }
            },

            openTransformers: function(path) {
                XmlBuilder.currentNodePath = path;
                var node = this.getNodeByPath(path);
                document.getElementById("editor_transformers").value = node.transformers || "";
                TransformerModal.open();
            },

            loadPreset: function(platform) {
                if (!platform) return;
                var self = this;

                mahoFetch(this.presetUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        platform: platform,
                        format: "xml",
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        alert("Error: " + data.message);
                    } else if (data.structure) {
                        self.structure = data.structure;
                        self.selectedPath = null;
                        self.render();
                        document.getElementById("xml-properties-content").innerHTML = "<p style=\\"color: #666; text-align: center;\\">' . addslashes($this->__('Select an element to edit its properties')) . '</p>";
                        // Update platform field in General tab
                        self.updatePlatform(data.platform);
                    }
                })
                .catch(function(err) {
                    alert("Error loading preset: " + err.message);
                });

                document.getElementById("xml-preset-select").value = "";
            },

            updatePlatform: function(platform) {
                var platformField = document.getElementById("platform") || document.querySelector("input[name=\'platform\']");
                if (platformField) {
                    platformField.value = platform;
                }
                var badge = document.getElementById("xml-platform-badge");
                if (badge) {
                    badge.textContent = platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : "";
                    badge.style.display = platform ? "inline-block" : "none";
                }
            },

            showImportModal: function() {
                document.getElementById("xml-import-modal").style.display = "block";
            },

            hideImportModal: function() {
                document.getElementById("xml-import-modal").style.display = "none";
                document.getElementById("xml-import-input").value = "";
            },

            importStructure: function() {
                try {
                    var input = document.getElementById("xml-import-input").value.trim();
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(input, "text/xml");
                    var errorNode = doc.querySelector("parsererror");
                    if (errorNode) {
                        throw new Error("Invalid XML");
                    }
                    this.structure = this.convertXmlToStructure(doc.documentElement);
                    this.render();
                    this.hideImportModal();
                } catch (e) {
                    alert("Invalid XML: " + e.message);
                }
            },

            convertXmlToStructure: function(element) {
                var result = [];
                var children = element.children;
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    var node = {
                        tag: child.tagName,
                        source_type: "attribute",
                        source_value: ""
                    };
                    if (child.children.length > 0) {
                        node.children = this.convertXmlToStructure(child);
                    }
                    result.push(node);
                }
                return result;
            },

            togglePreview: function() {
                var panel = document.getElementById("xml-preview-panel");
                var label = document.getElementById("xml-preview-toggle-label");
                if (panel.style.display === "none") {
                    panel.style.display = "block";
                    label.textContent = "' . $this->__('Hide Preview') . '";
                    this.refreshPreview();
                } else {
                    panel.style.display = "none";
                    label.textContent = "' . $this->__('Show Preview') . '";
                }
            },

            refreshPreview: function() {
                var content = document.getElementById("xml-preview-content");
                content.textContent = "' . $this->__('Loading...') . '";

                mahoFetch(this.previewUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        id: this.feedId,
                        structure: JSON.stringify(this.structure),
                        full_preview: this.fullPreview ? 1 : 0,
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        content.textContent = "Error: " + data.message;
                    } else {
                        content.textContent = data.preview;
                    }
                })
                .catch(function(err) {
                    content.textContent = "Error loading preview";
                });
            },

            toggleFullPreview: function(checked) {
                this.fullPreview = checked;
                this.refreshPreview();
            },

            copyPreview: function() {
                navigator.clipboard.writeText(document.getElementById("xml-preview-content").textContent);
            },

            validateXml: function() {
                var content = document.getElementById("xml-preview-content").textContent;
                var status = document.getElementById("xml-validation-status");

                if (!content || content.trim() === "") {
                    status.innerHTML = \'<span style="color: #666;">' . $this->__('No content to validate') . '</span>\';
                    return;
                }

                // Wrap in root element if not full preview (items only)
                var xmlToValidate = content;
                if (!this.fullPreview) {
                    xmlToValidate = "<root>" + content + "</root>";
                }

                var parser = new DOMParser();
                var doc = parser.parseFromString(xmlToValidate, "application/xml");
                var parseError = doc.querySelector("parsererror");

                if (parseError) {
                    var errorText = parseError.textContent || "' . $this->__('Invalid XML') . '";
                    // Extract just the error message, not the full verbose output
                    var match = errorText.match(/error[^:]*:\\s*(.+?)(?:\\n|$)/i);
                    var shortError = match ? match[1].trim() : "' . $this->__('Invalid XML structure') . '";
                    status.innerHTML = \'<span style="color: #c62828;">&#10008; \' + this.escapeHtml(shortError) + \'</span>\';
                } else {
                    status.innerHTML = \'<span style="color: #2e7d32;">&#10004; ' . $this->__('Valid XML') . '</span>\';
                }
            },

            escapeHtml: function(str) {
                return String(str || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            }
        };

        document.addEventListener("DOMContentLoaded", function() {
            XmlBuilder.init();
        });
        </script>

        <style>
        .xml-node { padding: 5px 10px; cursor: pointer; border-radius: 3px; margin: 2px 0; }
        .xml-node:hover { background: #f5f5f5; }
        .xml-node.selected { background: #e8f5e9; }
        .xml-tag { font-weight: 600; color: #2e7d32; }
        .xml-badge { font-size: 10px; padding: 2px 5px; background: #e0e0e0; border-radius: 3px; color: #666; }
        .xml-badge.optional { background: #fff3e0; color: #e65100; }
        .xml-toggle { cursor: pointer; color: #666; }
        .xml-children { margin-left: 10px; }
        </style>
        ';
    }

    /**
     * Get default XML structure for new feeds
     */
    protected function _getDefaultXmlStructure(): array
    {
        return [
            ['tag' => 'g:id', 'source_type' => 'attribute', 'source_value' => 'sku', 'cdata' => false, 'optional' => false],
            ['tag' => 'g:title', 'source_type' => 'attribute', 'source_value' => 'name', 'cdata' => true, 'optional' => false],
            ['tag' => 'g:description', 'source_type' => 'attribute', 'source_value' => 'description', 'cdata' => true, 'optional' => true, 'use_parent' => 'if_empty'],
            ['tag' => 'g:link', 'source_type' => 'attribute', 'source_value' => 'url', 'cdata' => false, 'optional' => false, 'use_parent' => 'if_empty'],
            ['tag' => 'g:image_link', 'source_type' => 'attribute', 'source_value' => 'image', 'cdata' => false, 'optional' => true, 'use_parent' => 'if_empty'],
            ['tag' => 'g:availability', 'source_type' => 'attribute', 'source_value' => 'is_in_stock', 'cdata' => false, 'optional' => false],
            ['tag' => 'g:price', 'source_type' => 'attribute', 'source_value' => 'price', 'cdata' => false, 'optional' => false],
            ['tag' => 'g:brand', 'source_type' => 'attribute', 'source_value' => 'brand', 'cdata' => true, 'optional' => true],
            ['tag' => 'g:condition', 'source_type' => 'static', 'source_value' => 'new', 'cdata' => false, 'optional' => true],
        ];
    }

    /**
     * Get platform preset options for dropdown
     */
    protected function _getPlatformPresetOptions(): string
    {
        $platforms = [
            'google' => 'Google Shopping',
            'facebook' => 'Facebook/Meta',
            'custom' => 'Custom',
        ];

        $html = '';
        foreach ($platforms as $code => $label) {
            $html .= '<option value="' . $code . '">' . $this->escapeHtml($label) . '</option>';
        }
        return $html;
    }

    protected function _getFeed(): Maho_FeedManager_Model_Feed
    {
        return Mage::registry('current_feed') ?: Mage::getModel('feedmanager/feed');
    }
}
