<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
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
            'text' => '<div id="csv-settings-row" class="fm-csv-settings-row">' .
                '<div><label>' . $this->__('Delimiter') . '</label>' .
                '<select id="csv_delimiter" name="csv_delimiter">' .
                '<option value=","' . ($feed->getCsvDelimiter() === ',' || $feed->getCsvDelimiter() === null ? ' selected' : '') . '>' . $this->__('Comma (,)') . '</option>' .
                '<option value="&#9;"' . ($feed->getCsvDelimiter() === "\t" ? ' selected' : '') . '>' . $this->__('Tab') . '</option>' .
                '<option value="|"' . ($feed->getCsvDelimiter() === '|' ? ' selected' : '') . '>' . $this->__('Pipe (|)') . '</option>' .
                '<option value=";"' . ($feed->getCsvDelimiter() === ';' ? ' selected' : '') . '>' . $this->__('Semicolon (;)') . '</option>' .
                '</select></div>' .
                '<div><label>' . $this->__('Enclosure') . '</label>' .
                '<select id="csv_enclosure" name="csv_enclosure">' .
                '<option value="&quot;"' . ($feed->getCsvEnclosure() === '"' || $feed->getCsvEnclosure() === null ? ' selected' : '') . '>' . $this->__('Double Quote (")') . '</option>' .
                '<option value="&#39;"' . ($feed->getCsvEnclosure() === "'" ? ' selected' : '') . '>' . $this->__("Single Quote (')") . '</option>' .
                '<option value=""' . ($feed->getCsvEnclosure() === '' ? ' selected' : '') . '>' . $this->__('None') . '</option>' .
                '</select></div>' .
                '<div><label>' . $this->__('Include Header') . '</label>' .
                '<select id="csv_include_header" name="csv_include_header">' .
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

        // Add transformer data as hidden element
        $globalFieldset = $form->addFieldset('global_elements', [
            'legend' => '',
            'class' => 'fieldset-wide no-display',
        ]);
        $globalFieldset->addField('transformer_modal_container', 'note', [
            'text' => '<input type="hidden" id="editor_transformers" value="">' .
                $this->_getTransformerModalHtml(),
        ]);

        $this->setForm($form);
        return parent::_prepareForm();
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
     * Get transformer modal JavaScript data
     */
    protected function _getTransformerModalHtml(): string
    {
        $data = Maho_FeedManager_Model_Transformer::getTransformerDataForJs();
        $data['translations'] = [
            'configure_transformers' => $this->__('Configure Transformers'),
            'apply_transformers' => $this->__('Apply Transformers'),
            'input' => $this->__('Input'),
            'transformers' => $this->__('Transformers'),
            'output' => $this->__('Output'),
            'no_transformers' => $this->__('No transformers added. Click "Add Transformer" to start.'),
            'add_transformer' => $this->__('+ Add Transformer'),
        ];

        $transformerData = Mage::helper('core')->jsonEncode($data);

        return '<script type="text/javascript">var TransformerData = ' . $transformerData . ';</script>';
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
