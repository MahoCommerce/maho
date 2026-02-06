<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\Country;
use Maho\ApiPlatform\ApiResource\Region;
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
     * @return Country[]|Country|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Country|null
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
     * Get all available countries
     *
     * @return Country[]
     */
    private function getCollection(): array
    {
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

        return $countries;
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
        $dto->available_regions = $this->getRegions($country->getCountryId());

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
