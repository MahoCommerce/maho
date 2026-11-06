<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Make a grid block for an admin request. A value of null leaves the chip parameter out of the request.
 */
function revocationSuppressionGrid(?string $suppressed): Maho_Revocation_Block_Adminhtml_Request_Grid
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('http://localhost/admin/sales_revocation/grid'),
    );
    $request->setRouteName('adminhtml')->setControllerName('sales_revocation')->setActionName('grid')->setDispatched(true);
    if ($suppressed !== null) {
        $request->setParam(Maho_Revocation_Block_Adminhtml_Request_Grid::VAR_NAME_SUPPRESSED, $suppressed);
    }
    Mage::app()->setRequest($request);

    $grid = Mage::app()->getLayout()->createBlock('revocation/adminhtml_request_grid');
    assert($grid instanceof Maho_Revocation_Block_Adminhtml_Request_Grid);
    return $grid;
}

/**
 * Prepare the grid collection as the CSV export does, without loading it.
 *
 * @return list<int>
 */
function revocationSuppressionGridIds(Maho_Revocation_Block_Adminhtml_Request_Grid $grid, array $requestIds): array
{
    (new ReflectionProperty(Mage_Adminhtml_Block_Widget_Grid::class, '_isExport'))->setValue($grid, true);
    (new ReflectionMethod($grid, '_prepareGrid'))->invoke($grid);

    $collection = $grid->getCollection();
    $collection->addFieldToFilter('request_id', ['in' => $requestIds]);
    return array_map(intval(...), $collection->getAllIds());
}

function revocationSuppressionCountInDb(): int
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $select = $adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('revocation/request'), [new Maho\Db\Expr('COUNT(*)')])
        ->where('suppressed_at IS NOT NULL');
    return (int) $adapter->fetchOne($select);
}

describe('Revocation grid suppression chip', function () {
    beforeEach(function () {
        revocationSuppressionGrid(null)->resetSavedParametersInSession();
        $now = Mage::app()->getLocale()->formatDateForDb('now');

        $this->suppressed = Mage::getModel('revocation/request');
        $this->suppressed->setStoreId(1)
            ->setOrderReference('100000001')
            ->setCustomerName('Max Mustermann')
            ->setEmail('chip-suppressed-' . uniqid() . '@example.com')
            ->setVerified(0)
            ->setReceivedAt($now)
            ->setSuppressedAt($now)
            ->setSuppressedReason(Maho_Revocation_Model_Request::SUPPRESSED_REASON_RATE_LIMIT)
            ->save();

        $this->delivered = Mage::getModel('revocation/request');
        $this->delivered->setStoreId(1)
            ->setOrderReference('100000002')
            ->setCustomerName('Erika Mustermann')
            ->setEmail('chip-delivered-' . uniqid() . '@example.com')
            ->setVerified(0)
            ->setReceivedAt($now)
            ->save();

        $this->requestIds = [(int) $this->suppressed->getId(), (int) $this->delivered->getId()];
    });

    afterEach(function () {
        revocationSuppressionGrid(null)->resetSavedParametersInSession();
        $this->suppressed->delete();
        $this->delivered->delete();
    });

    it('shows only the requests with a suppressed receipt email when the chip is on', function () {
        $ids = revocationSuppressionGridIds(revocationSuppressionGrid('1'), $this->requestIds);

        expect($ids)->toBe([(int) $this->suppressed->getId()]);
    });

    it('shows all requests when the chip is off', function () {
        $ids = revocationSuppressionGridIds(revocationSuppressionGrid('0'), $this->requestIds);
        sort($ids);

        expect($ids)->toBe($this->requestIds);
    });

    it('keeps the chip state for a grid reload that does not send it', function () {
        expect(revocationSuppressionGrid('1')->isSuppressedOnly())->toBeTrue();

        $ids = revocationSuppressionGridIds(revocationSuppressionGrid(null), $this->requestIds);
        expect($ids)->toBe([(int) $this->suppressed->getId()]);

        expect(revocationSuppressionGrid('0')->isSuppressedOnly())->toBeFalse()
            ->and(revocationSuppressionGrid(null)->isSuppressedOnly())->toBeFalse();
    });

    it('applies the chip together with a column filter', function (string $fixture, bool $found) {
        $grid = revocationSuppressionGrid('1');
        $grid->getRequest()->setParam(
            $grid->getVarNameFilter(),
            base64_encode(http_build_query(['email' => $this->{$fixture}->getEmail()])),
        );

        expect(revocationSuppressionGridIds($grid, $this->requestIds))
            ->toBe($found ? [(int) $this->suppressed->getId()] : []);
    })->with([
        'email of the suppressed request' => ['suppressed', true],
        'email of the delivered request' => ['delivered', false],
    ]);

    it('exports only the suppressed requests to CSV when the chip is on', function () {
        $file = revocationSuppressionGrid('1')->getCsvFile();
        $csv = (string) file_get_contents($file['value']);
        unlink($file['value']);

        expect($csv)->toContain($this->suppressed->getEmail())
            ->and($csv)->not->toContain($this->delivered->getEmail());
    });

    it('exports all requests to CSV when the chip is off', function () {
        $file = revocationSuppressionGrid('0')->getCsvFile();
        $csv = (string) file_get_contents($file['value']);
        unlink($file['value']);

        expect($csv)->toContain($this->suppressed->getEmail())
            ->and($csv)->toContain($this->delivered->getEmail());
    });

    it('counts all requests with a suppressed receipt email', function () {
        $grid = revocationSuppressionGrid('0');
        $count = $grid->getSuppressedCount();

        expect($count)->toBeGreaterThanOrEqual(1)
            ->and($count)->toBe(revocationSuppressionCountInDb());

        $this->delivered->setSuppressedAt(Mage::app()->getLocale()->formatDateForDb('now'))->save();

        expect(revocationSuppressionGrid('0')->getSuppressedCount())->toBe($count + 1);
    });

    it('renders the chip with the count and marks the active choice', function () {
        $design = Mage::getDesign();
        $previousArea = $design->getArea();
        $design->setArea('adminhtml')->setPackageName('default')->setTheme('default');

        try {
            $grid = revocationSuppressionGrid('1');
            $html = $grid->getMainButtonsHtml();
        } finally {
            $design->setArea($previousArea);
        }

        expect($html)->toContain('d-badge')
            ->and($html)->toContain('>' . $grid->getSuppressedCount() . '<')
            ->and(substr_count($html, 'aria-pressed="true"'))->toBe(1)
            ->and($html)->toMatch('/aria-pressed="true"[^>]*>\s*' . preg_quote($grid->__('Email Suppressed'), '/') . '/')
            ->and($html)->toContain("addVarToUrl('suppressed', '0')");
    });
});
