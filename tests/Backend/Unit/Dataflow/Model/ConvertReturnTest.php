<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class ConvertReturnUpperProbe extends Maho\Convert\Container\AbstractContainer
{
    public function upper(): void
    {
        $this->setData(strtoupper((string) $this->getData()));
    }
}

final class DataflowConvertReturnUpperProbe extends Mage_Dataflow_Model_Convert_Container_Abstract
{
    public function upper(array $args = []): void
    {
        $this->setData(strtoupper((string) $this->getData()));
    }
}

it('returns the data of the container after Maho\Convert::convert()', function () {
    expect(Maho\Convert::convert(ConvertReturnUpperProbe::class, 'upper', 'abc'))->toBe('ABC');
});

it('returns the data of the container after Mage_Dataflow_Model_Convert::convert()', function () {
    expect(Mage_Dataflow_Model_Convert::convert(DataflowConvertReturnUpperProbe::class, 'upper', 'abc'))->toBe('ABC');
});
