<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * @return list<array{message_class: string, body: string}>
 */
function qieTakeMessages(string $messageClass): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('queue/message');
    $rows = $adapter->fetchAll(
        $adapter->select()->from($table, ['message_class', 'body'])->where('message_class = ?', $messageClass),
    );
    $adapter->delete($table, ['message_class = ?' => $messageClass]);

    return $rows;
}

/**
 * @return list<array{severity: string, title: string, description: string, url: string}>
 */
function qieTakeNotices(int $adminUserId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('adminnotification/inbox');
    $where = ['admin_user_id = ?' => $adminUserId, 'title LIKE ?' => 'The %'];

    $select = $adapter->select()->from($table, ['severity', 'title', 'description', 'url']);
    foreach ($where as $condition => $value) {
        $select->where($condition, $value);
    }
    $notices = $adapter->fetchAll($select);
    $adapter->delete($table, $where);

    return $notices;
}

function qieAdminUserId(): int
{
    return (int) Mage::getResourceModel('admin/user_collection')->setPageSize(1)->getFirstItem()->getId();
}

describe('Import/Export in the queue', function () {
    test('an export queues one message with the entity, the format and the filters only', function () {
        qieTakeMessages(Mage_ImportExport_Model_ExportMessage::class);

        Mage::getModel('importexport/export')
            ->setData([
                'entity' => 'catalog_category',
                'file_format' => 'csv',
                Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP => [],
                'form_key' => 'secret',
            ])
            ->queueExport(7);
        $messages = qieTakeMessages(Mage_ImportExport_Model_ExportMessage::class);

        expect($messages)->toHaveCount(1);
        $message = unserialize($messages[0]['body'], ['allowed_classes' => [Mage_ImportExport_Model_ExportMessage::class]]);
        expect($message->adminUserId)->toBe(7)
            ->and(array_keys($message->parameters))->toBe(['entity', 'file_format', Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP]);
    });

    test('an export of an unknown entity is refused before it reaches the queue', function () {
        $queue = fn() => Mage::getModel('importexport/export')
            ->setData(['entity' => 'unknown', 'file_format' => 'csv', Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP => []])
            ->queueExport();

        expect($queue)->toThrow(Mage_Core_Exception::class);
        expect(qieTakeMessages(Mage_ImportExport_Model_ExportMessage::class))->toBe([]);
    });

    test('a second import waits for the first one, and the guard reports it', function () {
        qieTakeMessages(Mage_ImportExport_Model_ImportMessage::class);
        expect(Mage_ImportExport_Model_Import::isImportInProgress())->toBeFalse();

        $import = Mage::getModel('importexport/import');
        $import->queueImportSource();
        $import->queueImportSource();
        $inProgress = Mage_ImportExport_Model_Import::isImportInProgress();
        $messages = qieTakeMessages(Mage_ImportExport_Model_ImportMessage::class);

        expect($messages)->toHaveCount(1)
            ->and($inProgress)->toBeTrue()
            ->and(Mage_ImportExport_Model_Import::isImportInProgress())->toBeFalse();
    });

    test('a validation is refused while a queued import waits, so it cannot replace the rows of that import', function () {
        qieTakeMessages(Mage_ImportExport_Model_ImportMessage::class);
        Mage::getModel('importexport/import')->queueImportSource();

        try {
            expect(fn() => Mage::getModel('importexport/import')->validateSource([]))
                ->toThrow(Mage_Core_Exception::class, 'An import waits or runs');
        } finally {
            qieTakeMessages(Mage_ImportExport_Model_ImportMessage::class);
        }
    });

    test('an import that runs counts as in progress, so a second import does not start', function () {
        qieTakeMessages(Mage_ImportExport_Model_ImportMessage::class);
        $lock = Mage::getSingleton('core/lock');

        expect($lock->acquire(Mage_ImportExport_Model_Import::IMPORT_LOCK))->toBeTrue();
        try {
            $inProgress = Mage_ImportExport_Model_Import::isImportInProgress();
            expect(fn() => Mage::getModel('importexport/import')->importSource())
                ->toThrow(Mage_Core_Exception::class, 'An import waits or runs');
        } finally {
            $lock->release(Mage_ImportExport_Model_Import::IMPORT_LOCK);
        }

        expect($inProgress)->toBeTrue()
            ->and(Mage_ImportExport_Model_Import::isImportInProgress())->toBeFalse();
    });

    test('the validate action refuses a new file while a queued import waits', function () {
        qieTakeMessages(Mage_ImportExport_Model_ImportMessage::class);
        Mage::getModel('importexport/import')->queueImportSource();

        $previousArea = Mage::getDesign()->getArea();
        Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
        Mage::app()->getLayout()->setArea('adminhtml');

        $request = new Mage_Core_Controller_Request_Http(
            Symfony\Component\HttpFoundation\Request::create(
                '/index.php/admin/import/validate',
                'POST',
                ['entity' => 'catalog_product', 'behavior' => 'append'],
            ),
        );
        $request->setRouteName('adminhtml')->setControllerName('import')->setActionName('validate')->setDispatched(true);
        Mage::app()->setRequest($request);

        try {
            $controller = new Mage_ImportExport_Adminhtml_ImportController($request, new Mage_Core_Controller_Response_Http());
            try {
                $controller->validateAction();
            } catch (Throwable) {
                // The render step can need an admin URL. The action adds the message before it renders.
            }
            $block = Mage::app()->getLayout()->getBlock('import.frame.result');

            expect($block)->toBeInstanceOf(Mage_ImportExport_Block_Adminhtml_Import_Frame_Result::class)
                ->and($block->getMessages()['error'])
                ->toBe(['An import waits or runs. Start a new import when it is done.']);
        } finally {
            qieTakeMessages(Mage_ImportExport_Model_ImportMessage::class);
            Mage::getDesign()->setArea($previousArea);
        }
    });

    test('an import with no checked rows sends one major notice and stops, because a retry does not fix it', function () {
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->delete($resource->getTableName('importexport/importdata'));
        $adminUserId = qieAdminUserId();
        qieTakeNotices($adminUserId);

        Mage::getModel('importexport/messageHandler')->import(new Mage_ImportExport_Model_ImportMessage($adminUserId));

        $notices = qieTakeNotices($adminUserId);
        expect($notices)->toHaveCount(1)
            ->and((int) $notices[0]['severity'])->toBe(Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR)
            ->and($notices[0]['title'])->toBe('The import failed')
            ->and($notices[0]['description'])->not->toContain('tries again')
            ->and(Mage_ImportExport_Model_Import::isImportInProgress())->toBeFalse();
    });

    test('a customer export copies the temporary file to the mount and deletes the temporary file', function () {
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(1)
            ->setEmail('qie-' . uniqid() . '@example.com')
            ->setFirstname('Qie')
            ->setLastname('Export')
            ->save();
        $pattern = sys_get_temp_dir() . '/importexport_*';
        $before = glob($pattern) ?: [];

        try {
            $export = Mage::getModel('importexport/export')->setData([
                'entity' => 'customer',
                'file_format' => 'csv',
                Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP => [],
            ]);
            $fileName = $export->exportToStorage();
            $content = $export->getStorage()->read($fileName);
            $export->getStorage()->delete($fileName);
            $after = glob($pattern) ?: [];
        } finally {
            $customer->delete();
        }

        expect($content)->toContain($customer->getEmail())
            ->and(array_values(array_diff($after, $before)))->toBe([]);
    });

    test('a finished export writes the file to the mount and sends a link to it', function () {
        $adminUserId = qieAdminUserId();
        qieTakeNotices($adminUserId);

        Mage::getModel('importexport/messageHandler')->export(new Mage_ImportExport_Model_ExportMessage([
            'entity' => 'catalog_category',
            'file_format' => 'csv',
            Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP => [],
        ], $adminUserId));
        $notices = qieTakeNotices($adminUserId);

        expect($notices)->toHaveCount(1)
            ->and((int) $notices[0]['severity'])->toBe(Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE)
            ->and($notices[0]['title'])->toBe('The export file is ready');

        preg_match('#/file/([a-z0-9_]+\.csv)#i', $notices[0]['url'], $match);
        expect($match)->toHaveCount(2);
        $export = Mage::getModel('importexport/export');
        $path = $export->getStoragePath($match[1]);
        expect($path)->not->toBeNull()
            ->and($export->getStorage()->read($path))->toContain('category_id');
        $export->getStorage()->delete($path);
    });

    test('an export of an unknown entity sends one major notice and stops, because a retry does not fix it', function () {
        $adminUserId = qieAdminUserId();
        qieTakeNotices($adminUserId);

        Mage::getModel('importexport/messageHandler')->export(new Mage_ImportExport_Model_ExportMessage([
            'entity' => 'unknown',
            'file_format' => 'csv',
            Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP => [],
        ], $adminUserId));

        $notices = qieTakeNotices($adminUserId);
        expect($notices)->toHaveCount(1)
            ->and((int) $notices[0]['severity'])->toBe(Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR)
            ->and($notices[0]['title'])->toBe('The export failed')
            ->and($notices[0]['description'])->not->toContain('tries again');
    });

    test('the download accepts only the plain name of an export file', function () {
        $export = Mage::getModel('importexport/export');

        expect($export->getStoragePath('catalog_category_20260101_000000_0123456789abcdef.csv'))
            ->toBe('catalog_category_20260101_000000_0123456789abcdef.csv');
        foreach (['', '../local.xml', 'a/b.csv', '..', '.csv', "x.csv\0", '/etc/passwd', 'x.csv/..'] as $name) {
            expect($export->getStoragePath($name))->toBeNull();
        }
    });

    test('the daily cleanup is registered as a cron job', function () {
        expect(Maho::getCompiledAttributes()['crontab']['importexport_delete_expired_exports'] ?? null)
            ->toMatchArray(['alias' => 'importexport/export', 'method' => 'deleteExpiredFiles']);
    });

    test('the daily cleanup deletes the export files that are older than the lifetime', function () {
        $export = Mage::getModel('importexport/export');
        $mount = $export->getStorage();
        $old = 'qie_old_' . uniqid() . '.csv';
        $new = 'qie_new_' . uniqid() . '.csv';
        $mount->write($old, 'old');
        $mount->write($new, 'new');
        $root = $mount->localRoot();
        if ($root === null) {
            $mount->delete($old);
            $mount->delete($new);
            $this->markTestSkipped('The export mount is not local, so the test cannot age a file.');
        }
        touch($root . '/' . $old, time() - (Mage_ImportExport_Model_Export::FILE_LIFETIME_DAYS + 1) * 86400);

        $export->deleteExpiredFiles();
        $oldExists = $mount->fileExists($old);
        $newExists = $mount->fileExists($new);
        $mount->delete($new);

        expect($oldExists)->toBeFalse()
            ->and($newExists)->toBeTrue();
    });
});
