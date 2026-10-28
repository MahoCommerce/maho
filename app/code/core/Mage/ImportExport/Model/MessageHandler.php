<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

class Mage_ImportExport_Model_MessageHandler
{
    /**
     * @throws Throwable when the import failed for a reason other than the data, so the queue tries
     *                   the message again
     */
    #[Maho\Config\MessageHandler]
    public function import(Mage_ImportExport_Model_ImportMessage $message): void
    {
        $helper = Mage::helper('importexport');
        Mage::helper('adminnotification')->runAndNotify(
            function () use ($helper): string {
                /** @var Mage_ImportExport_Model_Import $import */
                $import = Mage::getModel('importexport/import');
                $import->importSource();
                $import->invalidateIndex();
                return $helper->__('The import of the entity "%s" is done.', $import->getEntity());
            },
            $helper->__('The import is done'),
            $helper->__('The import failed'),
            'adminhtml/import/index',
            $message->adminUserId,
            'system/convert/import',
        );
    }

    /**
     * Write the export file to the export mount, and send a link to the file.
     *
     * @throws Throwable when the export failed for a reason other than the data, so the queue tries
     *                   the message again
     */
    #[Maho\Config\MessageHandler]
    public function export(Mage_ImportExport_Model_ExportMessage $message): void
    {
        $helper = Mage::helper('importexport');
        $doneParams = [];
        Mage::helper('adminnotification')->runAndNotify(
            function () use ($helper, $message, &$doneParams): string {
                /** @var Mage_ImportExport_Model_Export $export */
                $export = Mage::getModel('importexport/export');
                $fileName = $export->setData($message->parameters)->exportToStorage();
                $doneParams = ['file' => $fileName];
                return $helper->__('Open this message to download the file %s. The file stays for at least %d days.', $fileName, Mage_ImportExport_Model_Export::FILE_LIFETIME_DAYS);
            },
            $helper->__('The export file is ready'),
            $helper->__('The export failed'),
            'adminhtml/export/index',
            $message->adminUserId,
            'system/convert/export',
            'adminhtml/export/download',
            $doneParams,
        );
    }
}
