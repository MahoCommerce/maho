<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Model_Observer
{
    public const CRON_STRING_PATH = 'crontab/jobs/currency_rates_update/schedule/cron_expr';
    public const IMPORT_ENABLE = 'currency/import/enabled';
    public const IMPORT_SERVICE = 'currency/import/service';

    public const XML_PATH_ERROR_TEMPLATE = 'currency/import/error_email_template';
    public const XML_PATH_ERROR_IDENTITY = 'currency/import/error_email_identity';
    public const XML_PATH_ERROR_RECIPIENT = 'currency/import/error_email';

    /**
     * @throws Mage_Core_Exception
     */
    #[Maho\Config\CronJob('currency_rates_update', configPath: 'crontab/jobs/currency_rates_update/schedule/cron_expr')]
    public function scheduledUpdateCurrencyRates()
    {
        if (!Mage::getStoreConfig(self::IMPORT_ENABLE) || !Mage::getStoreConfig(self::CRON_STRING_PATH)) {
            return;
        }

        $importWarnings = [];
        $rates = null;
        $usedService = null;

        foreach ($this->_getImportServiceChain() as $service) {
            $serviceWarnings = [];
            $rates = $this->_fetchRatesFromService($service['model'], $serviceWarnings);

            if ($rates !== null) {
                $usedService = $service['name'];
                break;
            }

            foreach ($serviceWarnings as $warning) {
                $importWarnings[] = $service['name'] . ': ' . $warning;
            }
        }

        if ($rates === null) {
            if (!$importWarnings) {
                $importWarnings[] = Mage::helper('directory')->__('FATAL ERROR:') . ' ' . Mage::helper('directory')->__('Invalid Import Service specified.');
            }
            $this->_sendImportWarnings($importWarnings);
            return;
        }

        Mage::getModel('directory/currency')->saveRates($rates);

        if ($importWarnings) {
            array_unshift($importWarnings, Mage::helper('directory')->__('Rates were imported from "%s" after the services below failed.', $usedService));
            $this->_sendImportWarnings($importWarnings);
        }
    }

    /**
     * Everything that memoises what the rate table answered: the resource's own lookups, the
     * group-price backend's per-website rates, and each live store's serveable map, current
     * currency and price filter. A table write has to reach all of them, or a long-lived
     * process serves the rates it started with.
     */
    #[Maho\Config\Observer('directory_currency_rates_save_after')]
    public function clearStoreCurrencyMemos(\Maho\Event\Observer $observer): void
    {
        // saveRates() already did this before dispatching, but the event is what a caller
        // writing the table by other means announces the write with, so it clears both halves
        // rather than leaving the resource answering from before the write it just heard about.
        Mage_Directory_Model_Resource_Currency::clearRateCache();
        Mage_Catalog_Model_Product_Attribute_Backend_Groupprice_Abstract::clearWebsiteCurrencyRates();

        foreach (Mage::app()->getStores(true) as $store) {
            $store->clearCurrencyRateMemos();
        }
    }

    /**
     * Services to try, in order: the configured one first, then every other enabled one.
     *
     * @return array<string, array{name: string, model: string, sort_order: int}>
     */
    protected function _getImportServiceChain(): array
    {
        $services = Mage::helper('directory')->getCurrencyImportServices();

        $configured = (string) Mage::getStoreConfig(self::IMPORT_SERVICE);
        if ($configured === '' || !isset($services[$configured])) {
            return $services;
        }

        return [$configured => $services[$configured]] + $services;
    }

    /**
     * @param array $warnings Filled with whatever went wrong, when the import did not go through
     * @return array|null Rates, or null when this service could not deliver a complete set
     */
    protected function _fetchRatesFromService(string $model, array &$warnings): ?array
    {
        $importModel = Mage::getModel($model);

        if (!$importModel instanceof Mage_Directory_Model_Currency_Import_Abstract) {
            $warnings[] = Mage::helper('directory')->__('Unable to initialize the import model.');
            return null;
        }

        try {
            $rates = $importModel->fetchRates();
        } catch (Exception $e) {
            Mage::logException($e);
            $warnings[] = $e->getMessage();
            return null;
        }

        $warnings = array_merge($warnings, $importModel->getMessages());

        return $warnings === [] && $rates !== [] ? $rates : null;
    }

    protected function _sendImportWarnings(array $importWarnings): void
    {
        /** @var Mage_Core_Model_Email_Template $mailTemplate */
        $mailTemplate = Mage::getModel('core/email_template');
        $mailTemplate
            ->setDesignConfig([
                'area' => Mage_Core_Model_App_Area::AREA_FRONTEND,
            ])
            ->sendTransactional(
                Mage::getStoreConfig(self::XML_PATH_ERROR_TEMPLATE),
                Mage::getStoreConfig(self::XML_PATH_ERROR_IDENTITY),
                Mage::getStoreConfig(self::XML_PATH_ERROR_RECIPIENT),
                null,
                [
                    'warnings' => implode("\n", $importWarnings),
                ],
            );
    }
}
