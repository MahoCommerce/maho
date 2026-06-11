<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

namespace Mage\Directory\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Country State Provider - Fetches country and region data
 */
final class CountryProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * Provide country data based on operation type
     *
     * @return TraversablePaginator<Country>|Country|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Country|null
    {
        StoreContext::ensureStore();

        if ($operation instanceof CollectionOperationInterface) {
            return $this->getCollection();
        }

        return $this->getItem((string) $uriVariables['id']);
    }

    /**
     * Get a single country by ISO code
     */
    private function getItem(string $countryCode): ?Country
    {
        $countryCode = strtoupper($countryCode);

        /** @var \Mage_Directory_Model_Country $mahoCountry */
        $mahoCountry = \Mage::getModel('directory/country')->loadByCode($countryCode);

        if (!$mahoCountry->getId()) {
            return null;
        }

        return Country::fromModel($mahoCountry);
    }

    /**
     * Get all available countries (cached for 1 hour)
     *
     * @return TraversablePaginator<Country>
     */
    private function getCollection(): TraversablePaginator
    {
        $storeId = StoreContext::getStoreId();
        $cacheKey = 'api_countries_' . $storeId;

        // Try cache first (1-hour TTL)
        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                $countries = array_map(fn(array $c) => $this->arrayToDto($c), $data);
                $total = count($countries);
                return new TraversablePaginator(new \ArrayIterator($countries), 1, max($total, 300), $total);
            }
        }

        $countries = [];

        /** @var \Mage_Directory_Model_Resource_Country_Collection $collection */
        $collection = \Mage::getModel('directory/country')->getCollection();

        $allowedCountries = explode(',', \Mage::getStoreConfig('general/country/allow') ?? '');
        if (!empty($allowedCountries) && $allowedCountries[0] !== '') {
            $collection->addFieldToFilter('main_table.country_id', ['in' => $allowedCountries]);
        }

        $collection->loadByStore();

        foreach ($collection as $mahoCountry) {
            $countries[] = Country::fromModel($mahoCountry);
        }

        usort($countries, fn($a, $b) => strcmp($a->name, $b->name));

        // Cache for 1 hour
        $cacheData = array_map(fn(Country $c) => $this->dtoToArray($c), $countries);
        \Mage::app()->getCache()->save(
            json_encode($cacheData),
            $cacheKey,
            ['API_COUNTRIES'],
            3600,
        );

        $total = count($countries);

        return new TraversablePaginator(new \ArrayIterator($countries), 1, max($total, 300), $total);
    }

    /**
     * Convert Country DTO to array for caching
     */
    private function dtoToArray(Country $dto): array
    {
        return [
            'id'               => $dto->id,
            'name'             => $dto->name,
            'iso2Code'         => $dto->iso2Code,
            'iso3Code'         => $dto->iso3Code,
            // $dto->availableRegions is already array<int, array{id, code, name}>
            // (built as plain arrays by Country::afterLoad) - straight passthrough
            // for the cache round-trip, no Region DTO conversion needed.
            'availableRegions' => $dto->availableRegions,
        ];
    }

    /**
     * Reconstruct Country DTO from cached array
     */
    private function arrayToDto(array $data): Country
    {
        $dto = new Country();
        $dto->id               = $data['id'] ?? '';
        $dto->name             = $data['name'] ?? '';
        $dto->iso2Code         = $data['iso2Code'] ?? '';
        $dto->iso3Code         = $data['iso3Code'] ?? '';
        $dto->availableRegions = $data['availableRegions'] ?? [];
        return $dto;
    }
}
