<?php

/**
 * Read the fields of a catalog or a cart price rule from a request body into the rule.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;

trait PriceRuleFieldsTrait
{
    /**
     * Put each field of $fields that $body has into $model, as the column and the kind that $fields gives.
     * Add an error to $errors for each value of a wrong type: the model checks the values themselves.
     * A key that is neither in $fields nor in $otherFields is a 400.
     *
     * @param array<string, mixed> $body
     * @param array<string, array{string, 'text'|'text?'|'bool'|'int'|'number'|'number?'|'date'|'ids'}> $fields the column and the kind of each body field
     * @param list<string> $otherFields the keys that the caller reads itself, and the read-only keys
     */
    protected function readRuleFields(\Mage_Core_Model_Abstract $model, array $body, array $fields, array $otherFields, \Mage_Core_Exception_Input $errors): void
    {
        $unknown = array_diff(array_keys($body), array_keys($fields), $otherFields, ['extensions', '@context', '@id', '@type']);
        if ($unknown !== []) {
            throw new ExtraAttributesException(array_map(strval(...), array_values($unknown)));
        }

        foreach ($fields as $field => [$column, $kind]) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $value = $body[$field];
            [$isValid, $value, $type] = match ($kind) {
                'text' => [is_string($value), is_string($value) ? trim($value) : null, 'a text'],
                'text?' => [$value === null || is_string($value), $value, 'a text or null'],
                'bool' => [is_bool($value) || $value === 0 || $value === 1, (bool) $value, 'true or false'],
                'int' => [RequestInput::toInt($value) !== null, RequestInput::toInt($value), 'an integer'],
                'number' => [$this->readNumber($value) !== null, $this->readNumber($value), 'a number'],
                'number?' => [$value === null || $this->readNumber($value) !== null, $this->readNumber($value), 'a number or null'],
                'date' => [$value === null || is_string($value), $value === '' ? null : $value, 'a date in the format YYYY-MM-DD or null'],
                'ids' => [$this->readIdList($value) !== null, $this->readIdList($value), 'a list of IDs'],
            };
            if (!$isValid) {
                $errors->addError($column, "{$field} must be {$type}");
                continue;
            }
            if ($column === 'website_ids') {
                $this->assertWebsitesInScope($value);
            }
            $model->setData($column, $value);
        }
    }

    /**
     * A restricted token may only target the websites that its store allowlist maps to.
     *
     * @param list<int> $websiteIds
     */
    protected function assertWebsitesInScope(array $websiteIds): void
    {
        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        foreach ($allowedWebsiteIds === null ? [] : $websiteIds as $id) {
            if (!in_array($id, $allowedWebsiteIds, true)) {
                throw new AccessDeniedHttpException("Access denied for website: {$id}");
            }
        }
    }

    /**
     * Return $value as a list of unique IDs, or null when it is not a list of integers.
     *
     * @return list<int>|null
     */
    protected function readIdList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $ids = array_map(RequestInput::toInt(...), array_values($value));
        return in_array(null, $ids, true) ? null : array_values(array_unique($ids));
    }

    private function readNumber(mixed $value): ?float
    {
        return !is_bool($value) && is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
    }
}
