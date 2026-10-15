<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

class Maho_MediaCleaner_Block_Adminhtml_Mediacleaner extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'mediacleaner';
        $this->_controller = 'adminhtml_mediacleaner';
        $this->_headerText = Mage::helper('mediacleaner')->__('Media Cleaner');

        parent::__construct();
        $this->setTemplate('mediacleaner/mediacleaner.phtml');
        $this->_removeButton('add');

        $helper = Mage::helper('mediacleaner');
        $this->_addButton('reset', [
            'label'   => $helper->__('Reset Results'),
            'onclick' => "setLocation('{$this->getActionUrl('reset')}')",
        ]);
    }

    /**
     * Dropdowns rendered in the header, in display order (Scan, then Flush).
     */
    public function getDropdowns(): array
    {
        $helper = Mage::helper('mediacleaner');

        return [
            [
                'label' => $helper->__('Scan'),
                'items' => array_map(
                    fn(string $type, string $label): array => ['label' => $label, 'url' => '#', 'onclick' => "mediaCleanerScan('{$type}'); return false;"],
                    array_keys($types = Mage::getModel('mediacleaner/scanner')->getTypes()),
                    $types,
                ),
            ],
            [
                'label' => $helper->__('Flush'),
                'items' => $this->buildItems([
                    'flushmediatmp'        => 'media/tmp',
                    'flushmediaimport'     => 'media/import',
                    'flushvarexport'       => 'var/export',
                    'flushvarimportexport' => 'var/importexport',
                ]),
            ],
        ];
    }

    /**
     * The script that runs a scan or a mass delete in a MahoJobDialog.
     */
    public function getScanScriptHtml(): string
    {
        $helper = Mage::helper('mediacleaner');
        $config = Mage::helper('core')->jsonEncode([
            'title' => $helper->__('Media Cleaner'),
            'startUrl' => $this->getUrl('*/*/scan'),
            'statusUrl' => $this->getUrl('*/*/scanStatus'),
            'width' => 520,
            'labels' => [
                'close' => $helper->__('Close'),
                'done' => $helper->__('Done'),
                'failed' => $helper->__('Finished with errors'),
                'incomplete' => $helper->__('Did not complete'),
                'requestFailed' => $helper->__('Request failed'),
            ],
        ]);

        return <<<HTML
            <script>
            function mediaCleanerScan(type) {
                new MahoJobDialog({ ...{$config}, startParams: { type } }).run();
            }

            function mediaCleanerMassDeleteComplete(grid, massaction, transport) {
                let result;
                try {
                    result = JSON.parse(transport.responseText);
                } catch (e) {
                    result = { error: true, message: transport.responseText };
                }
                new MahoJobDialog({$config}).attach(result);
            }
            </script>
            HTML;
    }

    protected function buildItems(array $actionLabels): array
    {
        $items = [];
        foreach ($actionLabels as $action => $label) {
            $items[] = ['label' => $label, 'url' => $this->getActionUrl($action)];
        }
        return $items;
    }

    protected function getActionUrl(string $action): string
    {
        return $this->getUrl('*/*/' . $action);
    }
}
