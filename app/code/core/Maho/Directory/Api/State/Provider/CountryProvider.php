<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Directory\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\Directory\Api\Resource\Country;
use Maho\Directory\Api\Resource\Region;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Country State Provider - Fetches country and region data
 *
 * @implements ProviderInterface<Country>
 */
final class CountryProvider implements ProviderInterface
{
    /**
     * Provide country data based on operation type
     *
     * @return ArrayPaginator<Country>|Country|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator|Country|null
    {
        // Ensure valid store context (bootstraps Maho)
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

        return $this->mapToDto($mahoCountry);
    }

    /**
     * Get all available countries (cached for 1 hour)
     *
     * @return ArrayPaginator<Country>
     */
    private function getCollection(): ArrayPaginator
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
                return new ArrayPaginator(items: $countries, currentPage: 1, itemsPerPage: max($total, 300), totalItems: $total);
            }
        }

        $countries = [];

        // Get allowed countries from config
        /** @var \Mage_Directory_Model_Resource_Country_Collection $collection */
        $collection = \Mage::getModel('directory/country')->getCollection();

        // Filter to only allowed countries for the current store
        $allowedCountries = explode(',', \Mage::getStoreConfig('general/country/allow') ?? '');
        if (!empty($allowedCountries) && $allowedCountries[0] !== '') {
            // Use main_table prefix to avoid ambiguity when loadByStore() joins with name table
            $collection->addFieldToFilter('main_table.country_id', ['in' => $allowedCountries]);
        }

        $collection->loadByStore();

        foreach ($collection as $mahoCountry) {
            $countries[] = $this->mapToDto($mahoCountry);
        }

        // Sort by name
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

        return new ArrayPaginator(
            items: $countries,
            currentPage: 1,
            itemsPerPage: max($total, 300),
            totalItems: $total,
        );
    }

    /**
     * Map Maho country model to Country DTO
     */
    private function mapToDto(\Mage_Directory_Model_Country $country): Country
    {
        $dto = new Country();
        $dto->id = $country->getCountryId();
        $dto->name = $country->getName() ?? $country->getCountryId();
        $dto->iso2Code = $country->getIso2Code();
        $dto->iso3Code = $country->getIso3Code();

        // Get regions for this country
        $dto->availableRegions = $this->getRegions($country->getCountryId());

        return $dto;
    }

    /**
     * Convert Country DTO to array for caching
     */
    private function dtoToArray(Country $dto): array
    {
        return [
            'id' => $dto->id,
            'name' => $dto->name,
            'iso2Code' => $dto->iso2Code,
            'iso3Code' => $dto->iso3Code,
            'availableRegions' => array_map(fn(Region $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
            ], $dto->availableRegions),
        ];
    }

    /**
     * Reconstruct Country DTO from cached array
     */
    private function arrayToDto(array $data): Country
    {
        $dto = new Country();
        $dto->id = $data['id'] ?? '';
        $dto->name = $data['name'] ?? '';
        $dto->iso2Code = $data['iso2Code'] ?? '';
        $dto->iso3Code = $data['iso3Code'] ?? '';
        $dto->availableRegions = array_map(function (array $r) {
            $region = new Region();
            $region->id = $r['id'] ?? 0;
            $region->code = $r['code'] ?? '';
            $region->name = $r['name'] ?? '';
            return $region;
        }, $data['availableRegions'] ?? []);
        return $dto;
    }

    /**
     * Get regions for a country
     *
     * @return Region[]
     */
    private function getRegions(string $countryCode): array
    {
        $regions = [];

        /** @var \Mage_Directory_Model_Resource_Region_Collection $collection */
        $collection = \Mage::getModel('directory/region')->getCollection();
        $collection->addCountryFilter($countryCode);

        foreach ($collection as $mahoRegion) {
            $region = new Region();
            $region->id = (int) $mahoRegion->getRegionId();
            $region->code = $mahoRegion->getCode() ?? '';
            $region->name = $mahoRegion->getDefaultName() ?? $mahoRegion->getName() ?? '';
            $regions[] = $region;
        }

        // Sort by name
        usort($regions, fn($a, $b) => strcmp($a->name, $b->name));

        return $regions;
    }
}
