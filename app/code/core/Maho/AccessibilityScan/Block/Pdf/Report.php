<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Pdf_Report extends Mage_Core_Block_Pdf
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('accessibilityscan/pdf/report.phtml');
    }

    public function setScan(Maho_AccessibilityScan_Model_Scan $scan): self
    {
        return $this->setData('scan', $scan);
    }

    public function getScan(): Maho_AccessibilityScan_Model_Scan
    {
        return $this->getData('scan');
    }

    /**
     * @return array<string, list<Maho_AccessibilityScan_Model_Violation>>
     */
    public function getViolationsByImpact(): array
    {
        return $this->getScan()->getViolationsGroupedByImpact();
    }

    /**
     * The report ships its own styles inline in the template
     */
    #[\Override]
    protected function getCssContent(): string
    {
        return '';
    }
}
