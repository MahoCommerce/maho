<?php

/**
 * Customers in the Mage_ImportExport layout.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Maho\Import\CsvFile;

class Customers extends AbstractImportExportImporter
{
    #[\Override]
    protected function entityCode(): string
    {
        return 'customer';
    }

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['email', '_website'];
    }

    #[\Override]
    protected function checkRow(CsvFile $file, int $line, array $row, array $options): void
    {
        if (($row['email'] ?? '') === '') {
            return;
        }
        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $this->fail($file, $line, "email '{$row['email']}' is not valid");
        }
        $this->at($file, $line, fn() => $this->resolver->websiteId($this->requireValue($file, $line, $row, '_website')));
        if (($row['_store'] ?? '') !== '') {
            $this->at($file, $line, fn() => $this->resolver->storeId($row['_store']));
        }
    }
}
