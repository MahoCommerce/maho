<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

describe('Maho_MediaCleaner_Adminhtml_MediacleanerController::flushvarimportexportAction()', function () {
    beforeEach(function (): void {
        $this->workingDir = Mage_ImportExport_Model_Import::getWorkingDir();
        if (!is_dir($this->workingDir)) {
            mkdir($this->workingDir, 0777, true);
        }
        $this->outside = sys_get_temp_dir() . '/maho_flush_target_' . uniqid();
        mkdir($this->outside, 0777, true);
        file_put_contents($this->outside . '/keep.csv', 'keep');
        $this->link = $this->workingDir . 'linked';
        symlink($this->outside, $this->link);
        file_put_contents($this->workingDir . 'old.csv', 'old');
        Mage::getSingleton('adminhtml/session')->getMessages(true);
    });

    afterEach(function (): void {
        if (is_link($this->link)) {
            unlink($this->link);
        }
        if (file_exists($this->workingDir . 'old.csv')) {
            unlink($this->workingDir . 'old.csv');
        }
        if (is_dir($this->outside)) {
            array_map(unlink(...), glob($this->outside . '/*') ?: []);
            rmdir($this->outside);
        }
    });

    it('deletes the files of the working folder and does not follow a symbolic link', function (): void {
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/index.php/admin/mediacleaner/flushvarimportexport'));
        $request->setRouteName('adminhtml')->setControllerName('mediacleaner')->setActionName('flushvarimportexport')->setDispatched(true);
        Mage::app()->setRequest($request);

        try {
            new Maho_MediaCleaner_Adminhtml_MediacleanerController($request, new Mage_Core_Controller_Response_Http())->flushvarimportexportAction();
        } catch (Throwable) {
            // The redirect at the end needs an admin URL. The flush ran before it.
        }

        expect(file_exists($this->outside . '/keep.csv'))->toBeTrue()
            ->and(file_exists($this->workingDir . 'old.csv'))->toBeFalse()
            ->and(Mage::getSingleton('adminhtml/session')->getMessages()->getErrors())->toBe([]);
    });
});
