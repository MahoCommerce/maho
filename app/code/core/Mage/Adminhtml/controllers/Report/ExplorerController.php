<?php

/**
 * Reports > Explorer: the page, and the JSON endpoints that run, export and save its reports.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

use Maho\Report\Request;

class Mage_Adminhtml_Report_ExplorerController extends Mage_Adminhtml_Controller_Action
{
    public const string ADMIN_RESOURCE = 'report/explorer';

    /** The link of the export notice has no secret key. The action checks that the file belongs to the administrator. */
    #[\Override]
    protected $_publicActions = ['download'];

    #[Maho\Config\Route('/admin/report_explorer/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Explorer'));
        $this->loadLayout()
            ->_setActiveMenu('report/explorer')
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Explorer'), Mage::helper('reports')->__('Explorer'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/report_explorer/data', methods: ['POST'])]
    public function dataAction(): void
    {
        $this->respond(fn(): array => Mage::getService('reports/explorer')->run($this->readRequest(), $this->access()));
    }

    #[Maho\Config\Route('/admin/report_explorer/records', methods: ['POST'])]
    public function recordsAction(): void
    {
        $this->respond(function (): array {
            $params = $this->getRequest();
            $result = Mage::getService('reports/explorer')->records(
                $this->readRequest(),
                $this->access(),
                ($sort = (string) $params->getParam('sort')) === '' ? null : $sort,
                Mage_Reports_Model_Explorer_Runner::RECORDS_LIMIT,
                max(0, (int) $params->getParam('offset')),
            );
            if ($result['route'] !== null) {
                foreach ($result['rows'] as $index => $row) {
                    $result['rows'][$index]['url'] = $this->getUrl($result['route'], [$result['routeParam'] => $row['id']]);
                }
            }
            unset($result['route'], $result['routeParam']);
            return $result;
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/explain', methods: ['POST'])]
    public function explainAction(): void
    {
        $this->respond(fn(): array => Mage::getService('reports/explorer')->explain(
            $this->readRequest(),
            $this->access(),
            (string) $this->getRequest()->getParam('metric'),
        ));
    }

    #[Maho\Config\Route('/admin/report_explorer/export', methods: ['POST'])]
    public function exportAction(): void
    {
        $session = $this->_getSession();
        try {
            $request = $this->readRequest();
            $export = Mage::getService('reports/explorer')->export(
                $request,
                $this->access(),
                Mage::helper('adminnotification')->getCurrentAdminUserId(),
            );
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
            $this->_redirect('*/*/index');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/index');
            return;
        }

        if ($export['queued']) {
            $session->addSuccess($this->__('The report has many rows, so the export runs in the background. A message in the admin inbox links to the file when it is ready.'));
            $this->_redirect('*/*/index', ['_query' => ['state' => (string) $this->getRequest()->getParam('state')]]);
            return;
        }
        $this->_prepareDownloadResponse(
            $request->dataset . '_' . date('Ymd_His') . '.csv',
            ['type' => 'stream', 'value' => $export['stream']],
            'text/csv',
        );
    }

    #[Maho\Config\Route('/admin/report_explorer/savedSave', methods: ['POST'])]
    public function savedSaveAction(): void
    {
        $this->respond(function (int $adminUserId): array {
            $service = Mage::getService('reports/savedReport');
            $id = (int) $this->getRequest()->getParam('id');
            $report = $id > 0 ? $service->getById($id, $adminUserId) : $service->newReport();
            $report->setName((string) $this->getRequest()->getParam('name'))
                ->setIsShared((bool) $this->getRequest()->getParam('shared'))
                ->setRequestData($this->readRequest()->toArray());
            $service->save($report, $adminUserId, $this->access());
            return [
                'report' => Mage::helper('reports')->savedReportToArray($report, $adminUserId),
                'saved' => Mage::helper('reports')->savedReportsFor($adminUserId, $this->access()),
            ];
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/savedDelete', methods: ['POST'])]
    public function savedDeleteAction(): void
    {
        $this->respond(function (int $adminUserId): array {
            $service = Mage::getService('reports/savedReport');
            $service->delete($service->getById((int) $this->getRequest()->getParam('id'), $adminUserId), $adminUserId);
            return ['saved' => Mage::helper('reports')->savedReportsFor($adminUserId, $this->access())];
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/pin', methods: ['POST'])]
    public function pinAction(): void
    {
        $this->respond(function (int $adminUserId): array {
            $service = Mage::getService('reports/savedReport');
            $report = $service->getById((int) $this->getRequest()->getParam('id'), $adminUserId);
            if ($this->getRequest()->getParam('pinned')) {
                $service->pin($report, $adminUserId);
            } else {
                $service->unpin($report, $adminUserId);
            }
            return ['saved' => Mage::helper('reports')->savedReportsFor($adminUserId, $this->access()), 'dashboard' => $service->pinnedIds($adminUserId)];
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/annotationSave', methods: ['POST'])]
    public function annotationSaveAction(): void
    {
        $this->respond(function (int $adminUserId): array {
            $service = Mage::getService('reports/annotation');
            $id = (int) $this->getRequest()->getParam('id');
            $annotation = $id > 0 ? $service->getById($id) : $service->newAnnotation();
            $annotation->setAnnotationDate((string) $this->getRequest()->getParam('date'))
                ->setTitle((string) $this->getRequest()->getParam('title'));
            $service->save($annotation, $adminUserId);
            return ['id' => (int) $annotation->getId()];
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/annotationDelete', methods: ['POST'])]
    public function annotationDeleteAction(): void
    {
        $this->respond(function (int $adminUserId): array {
            $service = Mage::getService('reports/annotation');
            $service->delete($service->getById((int) $this->getRequest()->getParam('id')), $adminUserId);
            return [];
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/targets', methods: ['POST'])]
    public function targetsAction(): void
    {
        $this->respond(function (): array {
            $targets = [];
            foreach (Mage::getService('reports/target')->listFor((string) $this->getRequest()->getParam('dataset'), $this->access(), (string) $this->getRequest()->getParam('metric')) as $target) {
                $targets[] = ['id' => (int) $target->getId(), 'month' => substr((string) $target->getMonth(), 0, 7), 'storeId' => $target->getStoreId(), 'value' => $target->getValue()];
            }
            return ['targets' => $targets];
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/targetSave', methods: ['POST'])]
    public function targetSaveAction(): void
    {
        $this->respond(function (): array {
            $service = Mage::getService('reports/target');
            $params = $this->getRequest();
            $value = $params->getParam('value');
            $target = $service->newTarget()
                ->setDataset((string) $params->getParam('dataset'))
                ->setMetric((string) $params->getParam('metric'))
                ->setMonth((string) $params->getParam('month'))
                ->setStoreId((int) $params->getParam('store_id'))
                ->setValue(is_numeric($value) ? (float) $value : null);
            $service->save($target, $this->access());
            return ['id' => (int) $target->getId()];
        });
    }

    #[Maho\Config\Route('/admin/report_explorer/targetDelete', methods: ['POST'])]
    public function targetDeleteAction(): void
    {
        $this->respond(function (): array {
            $service = Mage::getService('reports/target');
            $service->delete($service->getById((int) $this->getRequest()->getParam('id'), $this->access()));
            return [];
        });
    }

    /**
     * Run an action of the page and answer with its JSON, or with the error that mahoFetch() shows.
     *
     * @param Closure(int): array<string, mixed> $action receives the ID of the administrator
     */
    private function respond(Closure $action): void
    {
        try {
            $this->getResponse()->setBodyJson($action((int) Mage::helper('adminnotification')->getCurrentAdminUserId()));
        } catch (Mage_Core_Exception_Input $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage(), 'errors' => $e->getErrors()]);
        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    #[Maho\Config\Route('/admin/report_explorer/download')]
    public function downloadAction(): void
    {
        $export = Mage::getModel('reports/explorer_export');
        $adminUserId = (int) Mage::helper('adminnotification')->getCurrentAdminUserId();
        $path = $export->getStoragePath((string) $this->getRequest()->getParam('file'), $adminUserId);
        $mount = $export->getStorage();
        if ($path === null || !$mount->fileExists($path)) {
            $this->_getSession()->addError($this->__(
                'The export file does not exist. A daily cron job deletes the report exports that are older than %d days.',
                Mage_Reports_Model_Explorer_Export::FILE_LIFETIME_DAYS,
            ));
            $this->_redirect('*/*/index');
            return;
        }
        $this->_prepareDownloadResponse(
            basename($path),
            ['type' => 'stream', 'value' => $mount->readStream($path)],
            'text/csv',
            $mount->fileSize($path),
        );
    }

    /**
     * The request of the page: a preset code, or the JSON of a request in the field "request".
     *
     * @throws Mage_Core_Exception_Input
     */
    private function readRequest(): Request
    {
        $preset = (string) $this->getRequest()->getParam('preset');
        if ($preset !== '') {
            return Mage::getService('reports/explorer')->presetRequest($preset, $this->access());
        }
        try {
            $data = Mage::helper('core')->jsonDecode((string) $this->getRequest()->getParam('request'));
        } catch (JsonException) {
            $data = null;
        }
        if (!is_array($data)) {
            throw new Mage_Core_Exception_Input($this->__('The report request is not valid.'))
                ->addError('request', $this->__('The report request is not valid JSON.'));
        }
        return Request::fromArray($data);
    }

    private function access(): Mage_Reports_Model_Explorer_Access
    {
        return Mage_Reports_Model_Explorer_Access::forAdmin(Mage::getSingleton('admin/session'));
    }
}
