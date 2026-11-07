<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The report export actions unpacked getCsv() and getExcel() into _prepareDownloadResponse().
 * Both methods return a string, so each of these exports ended in an error.
 */
describe('report export actions', function () {
    it('sends the export as a download', function (string $controllerClass, string $action, string $fileName, string $contentType) {
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/index.php/admin/report/' . $action));
        $request->setRouteName('adminhtml')->setActionName($action)->setDispatched(true);
        Mage::app()->setRequest($request);
        $response = new Mage_Core_Controller_Response_Http();

        new $controllerClass($request, $response)->{$action . 'Action'}();

        $headers = array_column($response->getHeaders(), 'value', 'name');
        expect($headers['Content-Disposition'])->toBe('attachment; filename="' . $fileName . '"')
            ->and($headers['Content-Type'])->toBe($contentType)
            ->and($response->getBody())->not->toBe('');
    })->with([
        'report grid CSV' => [Mage_Adminhtml_Report_CustomerController::class, 'exportAccountsCsv', 'new_accounts.csv', 'text/csv'],
        'report grid Excel' => [Mage_Adminhtml_Report_CustomerController::class, 'exportAccountsExcel', 'accounts.xml', 'application/vnd.ms-excel'],
    ]);
});
