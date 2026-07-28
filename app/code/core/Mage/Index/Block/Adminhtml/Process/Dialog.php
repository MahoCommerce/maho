<?php

/**
 * Glue between the index screens and the shared job progress dialog.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

class Mage_Index_Block_Adminhtml_Process_Dialog extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _toHtml()
    {
        $reindexUrl = $this->getUrl('adminhtml/process/reindexProcess');
        $reindexAllUrl = $this->getUrl('adminhtml/process/reindexAll');
        $statusUrl = $this->getUrl('adminhtml/process/reindexStatus');

        $title = $this->jsQuoteEscape(Mage::helper('index')->__('Reindex Data'));
        $closeLabel = $this->jsQuoteEscape(Mage::helper('index')->__('Close'));
        $doneLabel = $this->jsQuoteEscape(Mage::helper('index')->__('Reindex complete'));
        $failedLabel = $this->jsQuoteEscape(Mage::helper('index')->__('Reindex finished with errors'));

        return <<<SCRIPT
        <script>
        function indexJobDialog(startUrl, startParams) {
            return new MahoJobDialog({
                title: '{$title}',
                startUrl: startUrl,
                statusUrl: '{$statusUrl}',
                startParams: startParams,
                width: 520,
                labels: {
                    close: '{$closeLabel}',
                    done: '{$doneLabel}',
                    failed: '{$failedLabel}',
                },
            });
        }

        function indexReindexProcess(processId) {
            indexJobDialog('{$reindexUrl}', { process: processId }).run();
        }

        function indexReindexAll() {
            indexJobDialog('{$reindexAllUrl}').run();
        }

        function indexReindexMassComplete(grid, massaction, transport) {
            let result;
            try {
                result = JSON.parse(transport.responseText);
            } catch (e) {
                result = { error: true, message: transport.responseText };
            }
            indexJobDialog().attach(result);
        }

        function indexMassChangeModeComplete() {
            location.reload();
        }
        </script>
        SCRIPT;
    }
}
