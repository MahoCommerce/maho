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
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

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
            'text' => $this->getLayout()
                ->createBlock('feedmanager/adminhtml_feed_edit_tab_mapping_xml')
                ->getBuilderHtml(),
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
            'text' => $this->getLayout()
                ->createBlock('feedmanager/adminhtml_feed_edit_tab_mapping_csv')
                ->getBuilderHtml(),
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
            'text' => $this->getLayout()
                ->createBlock('feedmanager/adminhtml_feed_edit_tab_mapping_json')
                ->getBuilderHtml(),
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

            // Helper to show/hide fieldset along with its collapsible header
            function toggleFieldsetWithHeader(fieldset, visible) {
                if (!fieldset) return;
                fieldset.style.display = visible ? 'block' : 'none';
                var header = fieldset.previousElementSibling;
                if (header && header.classList.contains('entry-edit-head')) {
                    header.style.display = visible ? '' : 'none';
                }
            }

            function updateContentMode() {
                if (!fileFormatSelect) return;

                var format = fileFormatSelect.value;
                var csvFieldset = document.getElementById('mapping_csv_builder_fieldset');
                var jsonFieldset = document.getElementById('mapping_json_builder_fieldset');

                // XML Builder
                toggleFieldsetWithHeader(xmlFieldset, format === 'xml');

                // CSV Builder
                toggleFieldsetWithHeader(csvFieldset, format === 'csv');

                // JSON Builder (also used for JSONL format)
                toggleFieldsetWithHeader(jsonFieldset, format === 'json' || format === 'jsonl');

                // Mapping fieldset (hide for all builder-based formats)
                toggleFieldsetWithHeader(mappingFieldset, false);
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
                                        var optItem = opt.options[oi];
                                        // Handle both object format {value, label} and simple strings
                                        var optValue = typeof optItem === 'object' ? optItem.value : oi;
                                        var optLabel = typeof optItem === 'object' ? optItem.label : optItem;
                                        var selected = currentVal === String(optValue) ? ' selected' : '';
                                        html += '<option value="' + optValue + '"' + selected + '>' + optLabel + '</option>';
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
     * Get default XML footer template
     */
    protected function _getDefaultXmlFooter(): string
    {
        return <<<'XML'
</channel>
</rss>
XML;
    }
}
