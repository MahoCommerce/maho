<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The matrix prints the rates it is given straight into its input fields, so a float has to
 * come out formatted the way the DECIMAL string it replaced did.
 */
class CurrencyRateMatrixProbe extends Mage_Adminhtml_Block_System_Currency_Rate_Matrix
{
    /**
     * @param array<string, array<string, float|string|null>> $rates
     * @return array<string, array<string, string|null>>
     */
    public function probeRates(array $rates): array
    {
        return $this->_prepareRates($rates);
    }
}

describe('Mage_Adminhtml_Block_System_Currency_Rate_Matrix::_prepareRates', function () {
    // Real for a store based in a hyperinflated currency.
    it('keeps a rate below 0.0001 out of scientific notation', function () {
        expect((new CurrencyRateMatrixProbe())->probeRates(['XTD' => ['XTE' => 0.0000238]]))
            ->toBe(['XTD' => ['XTE' => '0.0000238']]);
    });

    it('formats a whole float rate to four decimals', function () {
        expect((new CurrencyRateMatrixProbe())->probeRates(['XTD' => ['XTE' => 1.0]]))
            ->toBe(['XTD' => ['XTE' => '1.0000']]);
    });

    it('keeps an exponential string out of the input field too', function () {
        expect((new CurrencyRateMatrixProbe())->probeRates(['XTD' => ['XTE' => '2.38e-5']]))
            ->toBe(['XTD' => ['XTE' => '0.0000238']]);
    });

    // number_format() took the old else branch and fataled on this.
    it('leaves a field the operator typed nonsense into empty', function () {
        expect((new CurrencyRateMatrixProbe())->probeRates(['XTD' => ['XTE' => 'abc']]))
            ->toBe(['XTD' => ['XTE' => null]]);
    });

    it('leaves a field holding more than a float can hold empty', function () {
        expect((new CurrencyRateMatrixProbe())->probeRates(['XTD' => ['XTE' => '1e400']]))
            ->toBe(['XTD' => ['XTE' => null]]);
    });

    it('formats a decimal string the way it always has', function () {
        expect((new CurrencyRateMatrixProbe())->probeRates(['XTD' => ['XTE' => '1.250000000000']]))
            ->toBe(['XTD' => ['XTE' => '1.2500']]);
    });
});
