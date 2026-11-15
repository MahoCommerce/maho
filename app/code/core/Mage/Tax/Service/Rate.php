<?php

/**
 * Load, save, delete and import the tax rates, for the admin pages and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

class Mage_Tax_Service_Rate
{
    public function newRate(): Mage_Tax_Model_Calculation_Rate
    {
        /** @var Mage_Tax_Model_Calculation_Rate $rate */
        $rate = Mage::getModel('tax/calculation_rate');
        return $rate;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Mage_Tax_Model_Calculation_Rate
    {
        $rate = $this->newRate();
        $rate->load($id);
        if (!$rate->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('tax')->__('This tax rate no longer exists.'));
        }
        return $rate;
    }

    /**
     * Save $rate. The transport puts its input into the rate first. The store must have the country of the rate
     * and the store view of each title. The model checks the code, the rate and the zip range.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another rate has the code
     */
    public function save(Mage_Tax_Model_Calculation_Rate $rate): Mage_Tax_Model_Calculation_Rate
    {
        $helper = Mage::helper('tax');
        $errors = new Mage_Core_Exception_Input();
        $country = (string) $rate->getTaxCountryId();
        if ($country !== '' && !Mage::getModel('directory/country')->loadByCode($country)->getId()) {
            $errors->addError('tax_country_id', $helper->__('One of the country has invalid code.'));
        }
        foreach (array_keys($rate->getTitle() ?? []) as $storeId) {
            if (!array_key_exists((int) $storeId, Mage::app()->getStores())) {
                $errors->addError('title', $helper->__('Unknown store ID: %s', $storeId));
            }
        }
        $rate->validateRecord($errors)->throwIfErrors();
        $rate->save();
        return $rate;
    }

    /**
     * @throws Mage_Core_Exception_Conflict when a tax rule uses the rate
     */
    public function delete(Mage_Tax_Model_Calculation_Rate $rate): void
    {
        $rate->delete();
    }

    /**
     * Return the headers of the columns of a CSV file of tax rates, by field. A column with the code of each store view follows them.
     *
     * @return array<string, string>
     */
    public function getCsvHeaders(): array
    {
        $helper = Mage::helper('tax');
        return [
            'code' => $helper->__('Code'),
            'country_name' => $helper->__('Country'),
            'region_name' => $helper->__('State'),
            'tax_postcode' => $helper->__('Zip/Post Code'),
            'rate' => $helper->__('Rate'),
            'zip_is_range' => $helper->__('Zip/Post is Range'),
            'zip_from' => $helper->__('Range From'),
            'zip_to' => $helper->__('Range To'),
        ];
    }

    /**
     * Create or update the rate of each row of a CSV file of tax rates, by code. The first row holds the headers.
     * A row whose region the country does not have is skipped.
     *
     * @param list<list<string>> $rows
     * @return list<string> one message for each row that the import refuses
     * @throws Mage_Core_Exception when the headers do not match
     */
    public function import(array $rows): array
    {
        $helper = Mage::helper('tax');
        $headers = array_values($this->getCsvHeaders());
        $fileHeaders = $rows[0] ?? [];
        $titleColumns = [];
        foreach (Mage::getModel('core/store')->getCollection()->setLoadDefault(false) as $store) {
            $column = array_search($store->getCode(), array_slice($fileHeaders, count($headers), null, true), true);
            if ($column !== false) {
                $titleColumns[(int) $column] = (int) $store->getId();
            }
        }
        if (array_slice($fileHeaders, 0, count($headers)) !== $headers) {
            Mage::throwException($helper->__('Invalid file format upload attempt'));
        }

        $errors = [];
        $regions = [];
        foreach (array_slice($rows, 1) as $row) {
            // The end of a file can hold empty lines
            if (count($row) <= 1 && !strlen((string) ($row[0] ?? ''))) {
                continue;
            }
            if (count($row) < count($headers)) {
                $errors[] = $helper->__('Invalid file upload attempt');
                continue;
            }

            [$code, $country, $region, $postcode, $percent, $zipIsRange, $zipFrom, $zipTo] = $row;
            if (!isset($regions[$country])) {
                $regions[$country] = ['*' => 0];
                foreach (Mage::getModel('directory/region')->getCollection()->addCountryFilter($country) as $item) {
                    $regions[$country][$item->getCode()] = (int) $item->getRegionId();
                }
            }
            if (!isset($regions[$country][$region])) {
                continue;
            }

            $rate = $this->newRate()->loadByCode($code);
            $rate->addData([
                'code' => $code,
                'tax_country_id' => $country,
                'tax_region_id' => $regions[$country][$region],
                'tax_postcode' => $postcode === '' ? null : $postcode,
                'rate' => $percent,
                'zip_is_range' => $zipIsRange,
                'zip_from' => $zipFrom,
                'zip_to' => $zipTo,
            ]);
            $titles = [];
            foreach ($titleColumns as $column => $storeId) {
                $titles[$storeId] = (string) ($row[$column] ?? '');
            }
            $rate->setTitle($titles);
            try {
                $this->save($rate);
            } catch (Mage_Core_Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        return $errors;
    }
}
