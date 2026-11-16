<?php

/**
 * Write a queued report export to the export storage, and tell the administrator where to download it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Request;

class Mage_Reports_Model_Queue_ExplorerExportHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Reports_Model_Queue_ExplorerExport $message): void
    {
        $helper = Mage::helper('reports');
        $doneParams = [];
        Mage::helper('adminnotification')->runAndNotify(
            function () use ($message, $helper, &$doneParams): string {
                $export = Mage::getModel('reports/explorer_export');
                $fileName = $export->newFileName($message->adminUserId);
                $path = tempnam(sys_get_temp_dir(), 'report');
                if ($path === false) {
                    throw new RuntimeException('Cannot create a temporary file for the report export.');
                }
                try {
                    $handle = fopen($path, 'w');
                    if ($handle === false) {
                        throw new RuntimeException('Cannot open a temporary file for the report export.');
                    }
                    $rows = Mage::getModel('reports/explorer_csv')->write(
                        $handle,
                        Request::fromArray($message->request),
                        Mage_Reports_Model_Explorer_Access::fromArray($message->access),
                        Mage_Reports_Service_Explorer::exportMaxRows(),
                    );
                    fclose($handle);
                    $export->getStorage()->copyFromLocalFile($path, $fileName);
                } finally {
                    @unlink($path);
                }
                $doneParams = ['file' => $fileName];
                return $helper->__(
                    'Open this message to download the file %s with %d rows. The file is deleted after %d days.',
                    $fileName,
                    $rows,
                    Mage_Reports_Model_Explorer_Export::FILE_LIFETIME_DAYS,
                );
            },
            $helper->__('The report export is ready'),
            $helper->__('The report export failed'),
            'adminhtml/report_explorer/index',
            $message->adminUserId,
            'report/explorer',
            'adminhtml/report_explorer/download',
            $doneParams,
        );
    }
}
