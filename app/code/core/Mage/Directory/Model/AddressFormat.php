<?php

/**
 * Postcode rules of each country, from the Google libaddressinput data in mahocommerce/directory-data.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

use Maho\DirectoryData\Paths;

class Mage_Directory_Model_AddressFormat
{
    /** @var array<string, array<string, string>|null> */
    protected static array $formats = [];

    /**
     * A country without a postcode rule accepts every postcode.
     */
    public function isValidPostcode(?string $countryId, ?string $postcode): bool
    {
        $postcode = trim((string) $postcode);
        $pattern = $this->getPostcodePattern((string) $countryId);
        if ($postcode === '' || $pattern === null) {
            return true;
        }

        // libaddressinput converts the postcode to upper case before it validates it
        return preg_match('~^(?:' . $pattern . ')$~i', $postcode) === 1;
    }

    public function getPostcodePattern(string $countryId): ?string
    {
        $zip = $this->getFormat($countryId)['zip'] ?? '';
        return $zip === '' ? null : $zip;
    }

    public function getPostcodeExample(string $countryId): ?string
    {
        $example = explode(',', $this->getFormat($countryId)['zipex'] ?? '')[0];
        return $example === '' ? null : $example;
    }

    /**
     * @param string[] $countryIds
     * @return array<string, array{pattern: string, example: ?string}>
     */
    public function getPostcodeFormats(array $countryIds): array
    {
        $formats = [];
        foreach ($countryIds as $countryId) {
            $pattern = $this->getPostcodePattern($countryId);
            if ($pattern !== null) {
                $formats[$countryId] = [
                    'pattern' => $pattern,
                    'example' => $this->getPostcodeExample($countryId),
                ];
            }
        }
        return $formats;
    }

    /**
     * @return array<string, string>|null
     */
    protected function getFormat(string $countryId): ?array
    {
        // The country id comes from the request and becomes part of a file path
        if (!preg_match('/^[A-Z]{2}$/', $countryId)) {
            return null;
        }

        if (!array_key_exists($countryId, self::$formats)) {
            $file = Paths::formatsFile($countryId);
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            self::$formats[$countryId] = is_array($data['country'] ?? null) ? $data['country'] : null;
        }

        return self::$formats[$countryId];
    }
}
