<?php

/**
 * The report request of the report:run and report:explain commands.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho\Report\Request;
use Symfony\Component\Console\Output\OutputInterface;

trait ReportCommandTrait
{
    /**
     * Build the request from a preset, a saved report or a JSON request, then apply the period options.
     *
     * @param array<string, mixed> $overrides
     * @throws \Mage_Core_Exception
     */
    private function reportRequest(?string $preset, ?int $savedId, ?string $json, array $overrides): Request
    {
        $sources = array_filter([$preset, $savedId, $json], static fn(mixed $value): bool => $value !== null && $value !== '');
        if (count($sources) !== 1) {
            throw new \Mage_Core_Exception('Give exactly one of --preset, --saved and --request.');
        }

        $service = Mage::getService('reports/explorer');
        if ($preset !== null && $preset !== '') {
            return $service->presetRequest($preset, \Mage_Reports_Model_Explorer_Access::full(), $overrides);
        }

        if ($savedId !== null) {
            $report = Mage::getModel('reports/savedReport')->load($savedId);
            if ($report->getId() === null) {
                throw new \Mage_Core_Exception_NoSuchEntity("The saved report with ID {$savedId} does not exist.");
            }
            $data = $report->getRequestData();
        } else {
            try {
                $data = Mage::helper('core')->jsonDecode((string) $json);
            } catch (\JsonException $e) {
                throw new \Mage_Core_Exception('The --request option is not valid JSON: ' . $e->getMessage());
            }
            if (!is_array($data)) {
                throw new \Mage_Core_Exception('The --request option must be a JSON object.');
            }
        }
        return $service->requestWith($data, $overrides);
    }

    private function writeReportError(OutputInterface $output, \Mage_Core_Exception $e): void
    {
        $errors = $e instanceof \Mage_Core_Exception_Input ? $e->getErrors() : [];
        if ($errors === []) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
        foreach ($errors as $error) {
            $output->writeln("<error>{$error['field']}: {$error['message']}</error>");
        }
    }
}
