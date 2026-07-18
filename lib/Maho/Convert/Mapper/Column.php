<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Convert\Mapper;

class Column extends AbstractMapper
{
    #[\Override]
    public function map()
    {
        $data = $this->getData();
        $this->validateDataGrid($data);
        if ($this->getVars() && is_array($this->getVars())) {
            $attributesToSelect = $this->getVars();
        } else {
            $attributesToSelect = [];
        }
        $onlySpecified = (bool) $this->getVar('_only_specified') === true;
        $mappedData = [];
        foreach ($data as $i => $row) {
            $newRow = [];
            foreach ($row as $field => $value) {
                if (!$onlySpecified || isset($attributesToSelect[$field])) {
                    $newRow[$this->getVar($field, $field)] = $value;
                }
            }
            $mappedData[$i] = $newRow;
        }
        $this->setData($mappedData);
        return $this;
    }
}
