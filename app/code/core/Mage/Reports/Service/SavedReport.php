<?php

/**
 * The saved reports: private to their owner, or shared with every administrator who may read their dataset.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Request;

class Mage_Reports_Service_SavedReport
{
    public const NAME_MAX_LENGTH = 255;

    public function newReport(): Mage_Reports_Model_SavedReport
    {
        return Mage::getModel('reports/savedReport')->setIsShared(false);
    }

    /**
     * A saved report that $adminUserId may read: one of their own, or a shared one.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?int $adminUserId): Mage_Reports_Model_SavedReport
    {
        $report = $this->newReport()->load($id);
        if ($report->getId() === null || (!$report->getIsShared() && ($adminUserId === null || $report->getAdminUserId() !== $adminUserId))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('reports')->__('The saved report with ID %s does not exist.', $id));
        }
        return $report;
    }

    /**
     * The shared reports and the reports of $adminUserId, newest first, on datasets that $access allows.
     *
     * @return list<Mage_Reports_Model_SavedReport>
     */
    public function listFor(?int $adminUserId, Mage_Reports_Model_Explorer_Access $access): array
    {
        $collection = Mage::getResourceModel('reports/savedReport_collection')
            ->addVisibilityFilter($adminUserId)
            ->setOrder('updated_at', 'DESC');
        $registry = Mage::getSingleton('reports/explorer_registry');
        $reports = [];
        foreach ($collection as $report) {
            $dataset = $registry->getDataset((string) ($report->getRequestData()['dataset'] ?? ''));
            if ($dataset !== null && $access->allowsDataset($dataset)) {
                $reports[] = $report;
            }
        }
        return $reports;
    }

    /**
     * Check and save a report. A new report belongs to $adminUserId. Only its owner changes it.
     *
     * @throws Mage_Core_Exception_Forbidden
     * @throws Mage_Core_Exception_Input
     */
    public function save(Mage_Reports_Model_SavedReport $report, int $adminUserId, Mage_Reports_Model_Explorer_Access $access): Mage_Reports_Model_SavedReport
    {
        $helper = Mage::helper('reports');
        if ($report->getId() !== null) {
            $this->assertOwner($report, $adminUserId);
        } else {
            $report->setAdminUserId($adminUserId);
        }

        $errors = new Mage_Core_Exception_Input($helper->__('The saved report is not valid.'));
        $name = trim((string) $report->getName());
        if ($name === '') {
            $errors->addError('name', $helper->__('The name is required.'));
        } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors->addError('name', $helper->__('The name has at most %d characters.', self::NAME_MAX_LENGTH));
        }
        $data = $report->getRequestData();
        if ($data === []) {
            $errors->addError('definition', $helper->__('The report definition is required.'));
        }
        $errors->throwIfErrors();

        $request = Request::fromArray($data);
        $registry = Mage::getSingleton('reports/explorer_registry');
        Mage::getSingleton('reports/explorer_validator')->validate(
            $request,
            $registry->getDataset($request->dataset),
            $access,
            Mage_Reports_Model_Explorer_Runner::maxRows(),
        );

        $report->setName($name)
            ->setRequestData($request->with(['offset' => 0])->toArray())
            ->setIsShared((bool) $report->getIsShared())
            ->save();
        return $report;
    }

    /** @throws Mage_Core_Exception_Forbidden */
    public function delete(Mage_Reports_Model_SavedReport $report, int $adminUserId): void
    {
        $this->assertOwner($report, $adminUserId);
        $report->delete();
    }

    /**
     * Put a saved report on the dashboard of $adminUserId, after the reports that are on it. The report must be one
     * that the administrator sees: their own, or a shared one.
     */
    public function pin(Mage_Reports_Model_SavedReport $report, int $adminUserId): void
    {
        if ($this->isPinned($report, $adminUserId)) {
            return;
        }
        $last = Mage::getResourceModel('reports/dashboardItem_collection')
            ->addFieldToFilter('admin_user_id', $adminUserId)
            ->setOrder('position', 'DESC')
            ->setPageSize(1)
            ->getFirstItem();
        Mage::getModel('reports/dashboardItem')
            ->setAdminUserId($adminUserId)
            ->setSavedReportId((int) $report->getId())
            ->setPosition($last instanceof Mage_Reports_Model_DashboardItem ? (int) $last->getPosition() + 1 : 0)
            ->save();
    }

    public function unpin(Mage_Reports_Model_SavedReport $report, int $adminUserId): void
    {
        $items = Mage::getResourceModel('reports/dashboardItem_collection')
            ->addFieldToFilter('admin_user_id', $adminUserId)
            ->addFieldToFilter('saved_report_id', (int) $report->getId());
        foreach ($items as $item) {
            $item->delete();
        }
    }

    public function isPinned(Mage_Reports_Model_SavedReport $report, int $adminUserId): bool
    {
        return in_array((int) $report->getId(), $this->pinnedIds($adminUserId), true);
    }

    /**
     * The saved report IDs on the dashboard of $adminUserId, in their order.
     *
     * @return list<int>
     */
    public function pinnedIds(int $adminUserId): array
    {
        $ids = [];
        $items = Mage::getResourceModel('reports/dashboardItem_collection')
            ->addFieldToFilter('admin_user_id', $adminUserId)
            ->setOrder('position', 'ASC');
        foreach ($items as $item) {
            if ($item instanceof Mage_Reports_Model_DashboardItem) {
                $ids[] = (int) $item->getSavedReportId();
            }
        }
        return $ids;
    }

    /**
     * The saved reports on the dashboard of $adminUserId, in their order. A report that the administrator no longer
     * sees, because its owner stopped sharing it or its dataset is no longer allowed, is left out.
     *
     * @return list<Mage_Reports_Model_SavedReport>
     */
    public function dashboardFor(int $adminUserId, Mage_Reports_Model_Explorer_Access $access): array
    {
        $visible = [];
        foreach ($this->listFor($adminUserId, $access) as $report) {
            $visible[(int) $report->getId()] = $report;
        }
        $reports = [];
        foreach ($this->pinnedIds($adminUserId) as $id) {
            if (isset($visible[$id])) {
                $reports[] = $visible[$id];
            }
        }
        return $reports;
    }

    /** @throws Mage_Core_Exception_Forbidden */
    private function assertOwner(Mage_Reports_Model_SavedReport $report, int $adminUserId): void
    {
        if ($report->getAdminUserId() !== $adminUserId) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('reports')->__('Only the owner of a saved report can change or delete it.'));
        }
    }
}
