<?php

/**
 * Reads one kind of personal data of a subject, for the export of its data. The config node
 * global/privacycenter/exporters lists the exporters.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

abstract class Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    /** The fields that an export never holds: secrets that prove an identity, not personal data. */
    protected const array SECRET_FIELDS = [
        'password_hash', 'password', 'password_confirmation', 'rp_token', 'rp_token_created_at', 'twofa_secret',
        'confirmation', 'protect_code', 'subscriber_confirm_code', 'sharing_code', 'token_hash',
    ];

    /**
     * The title of the section in the export, which a person reads.
     */
    abstract public function getLabel(): string;

    /**
     * The data of $subject, as a list of records or as one record. Each value is a scalar, null, or an array of the
     * same shape.
     *
     * @return array<array-key, mixed>
     */
    abstract public function export(Maho_PrivacyCenter_Model_Subject $subject): array;

    /**
     * The fields of $data without the secrets and without the values that are not data, such as objects.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    protected function clean(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::SECRET_FIELDS, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->clean($value);
            } elseif ($value === null || is_scalar($value)) {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}
