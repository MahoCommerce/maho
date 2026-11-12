<?php

/**
 * Fetch the currency rates from an import service, for the admin and the cron.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

class Mage_Directory_Service_Currency
{
    /**
     * Fetch the rates from the enabled import service $code. Each warning names a rate that the service did not give.
     *
     * @return array{rates: array<string, array<string, float|null>>, warnings: list<string>}
     * @throws Mage_Core_Exception when $code is not an enabled import service
     * @throws RuntimeException when the model of the service is not an import model
     */
    public function fetchRates(string $code): array
    {
        $helper = Mage::helper('directory');
        $services = $helper->getCurrencyImportServices();
        if (!isset($services[$code])) {
            throw new Mage_Core_Exception($helper->__('Invalid Import Service specified.'));
        }

        $importModel = Mage::getModel($services[$code]['model']);
        if (!$importModel instanceof Mage_Directory_Model_Currency_Import_Abstract) {
            throw new RuntimeException($helper->__('Unable to initialize the import model.'));
        }

        $rates = $importModel->fetchRates();
        return ['rates' => $rates, 'warnings' => array_values($importModel->getMessages())];
    }
}
