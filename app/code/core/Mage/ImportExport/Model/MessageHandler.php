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
     * Write the export file to the export mount, and send a link to the file. The link holds the
     * name of the file, which the job knows only at the end, so this handler sends its own messages.
     *
     * @throws Throwable when the export failed for a reason other than the data, so the queue tries
     *                   the message again
     */
    #[Maho\Config\MessageHandler]
    public function export(Mage_ImportExport_Model_ExportMessage $message): void
    {
        $helper = Mage::helper('importexport');
        $notifier = Mage::helper('adminnotification');
        $aclResource = $message->adminUserId === null ? 'system/convert/export' : null;

        try {
            /** @var Mage_ImportExport_Model_Export $export */
            $export = Mage::getModel('importexport/export');
            $export->deleteExpiredFiles();
            $fileName = $export->setData($message->parameters)->exportToStorage();
        } catch (Throwable $e) {
            Mage::logException($e);
            $notifier->notifyAdmin(
                Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR,
                $helper->__('The export failed'),
                $notifier->getFailureText($e),
                'adminhtml/export/index',
                [],
                $message->adminUserId,
                $aclResource,
            );
            if ($e instanceof Mage_Core_Exception) {
                return;
            }
            throw $e;
        }

        $notifier->notifyAdmin(
            Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE,
            $helper->__('The export file is ready'),
            $helper->__('Open this message to download the file %s. The file stays for at least %d days.', $fileName, Mage_ImportExport_Model_Export::FILE_LIFETIME_DAYS),
            'adminhtml/export/download',
            ['file' => $fileName],
            $message->adminUserId,
            $aclResource,
        );
    }
}
