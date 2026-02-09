<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Preview extends Mage_Adminhtml_Block_Widget_Form
{
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

    #[\Override]
    protected function _prepareForm(): self
    {
        $feed = $this->_getFeed();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('preview_');

        $fieldset = $form->addFieldset('preview_fieldset', [
            'legend' => $this->__('Feed Preview'),
        ]);

        $fieldset->addField('preview_note', 'note', [
            'text' => '<p class="fm-help-text">' .
                $this->__('Preview shows sample output using your current template configuration. This uses real product data from your store.') .
                '</p>',
        ]);

        // Product count selector
        $fieldset->addField('preview_count', 'select', [
            'name' => 'preview_count',
            'label' => $this->__('Products to Preview'),
            'values' => [
                ['value' => '1', 'label' => '1 Product'],
                ['value' => '3', 'label' => '3 Products'],
                ['value' => '5', 'label' => '5 Products'],
            ],
            'value' => '3',
        ]);

        // Generate button
        $fieldset->addField('preview_button', 'note', [
            'text' => '<button type="button" id="generate-preview-btn" class="scalable">' .
                '<span>' . $this->__('Generate Preview') . '</span>' .
                '</button>',
        ]);

        // Preview output area
        $fieldset->addField('preview_output', 'note', [
            'text' => '<div id="preview-output-container" class="fm-preview-panel">' .
                '<pre id="preview-output" class="fm-preview-content"></pre>' .
                '</div>',
        ]);

        $this->setForm($form);
        return parent::_prepareForm();
    }

    #[\Override]
    protected function _afterToHtml($html)
    {
        $html .= $this->_getPreviewScript();
        return parent::_afterToHtml($html);
    }

    /**
     * Get JavaScript for preview generation
     */
    protected function _getPreviewScript(): string
    {
        $feed = $this->_getFeed();
        $previewUrl = $this->getUrl('*/*/preview', ['feed_id' => $feed->getId() ?: '__FEED_ID__']);

        return <<<SCRIPT
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var previewBtn = document.getElementById('generate-preview-btn');
            var previewOutput = document.getElementById('preview-output');
            var previewContainer = document.getElementById('preview-output-container');
            var previewCount = document.getElementById('preview_preview_count');

            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    generatePreview();
                });
            }

            async function generatePreview() {
                previewContainer.classList.add('visible');
                previewOutput.textContent = 'Generating preview...';
                previewOutput.style.color = '#888';

                // Collect form data
                var form = document.getElementById('edit_form');
                var formData = new FormData(form);
                formData.append('preview_count', previewCount ? previewCount.value : '3');

                try {
                    var response = await mahoFetch('{$previewUrl}', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.success) {
                        previewOutput.textContent = response.preview;
                        previewOutput.style.color = '#d4d4d4';

                        // Apply syntax highlighting for XML
                        highlightXml(previewOutput);
                    } else {
                        previewOutput.textContent = 'Error: ' + (response.message || 'Failed to generate preview');
                        previewOutput.style.color = '#f44336';
                    }
                } catch (error) {
                    previewOutput.textContent = 'Error: ' + error.message;
                    previewOutput.style.color = '#f44336';
                }
            }

            function highlightXml(element) {
                var text = element.textContent;

                // Simple regex-based XML syntax highlighting for preview only
                // Note: This is intentionally basic and not a full XML parser
                // It's sufficient for visual preview purposes but may not handle all edge cases
                text = text.replace(/(&lt;!--[\s\S]*?--&gt;)/g, '<span class="fm-syntax-comment">$1</span>');
                text = text.replace(/(&lt;\?[\s\S]*?\?&gt;)/g, '<span class="fm-syntax-keyword">$1</span>');
                text = text.replace(/(&lt;!\[CDATA\[[\s\S]*?\]\]&gt;)/g, '<span class="fm-syntax-string">$1</span>');
                text = text.replace(/(&lt;\/?)([\w:-]+)/g, '<span class="fm-syntax-keyword">$1</span><span class="fm-syntax-tag">$2</span>');
                text = text.replace(/([\w:-]+)(=)(&quot;[^&]*&quot;)/g, '<span class="fm-syntax-attr">$1</span>$2<span class="fm-syntax-string">$3</span>');

                element.innerHTML = text
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/&lt;span/g, '<span')
                    .replace(/&lt;\/span&gt;/g, '</span>');
            }
        });
        </script>
SCRIPT;
    }
}
