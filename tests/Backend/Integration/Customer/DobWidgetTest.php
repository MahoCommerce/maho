<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A Date of Birth widget whose locale gives $format as its short date format.
 */
function dobWidgetWithFormat(string $format): Mage_Customer_Block_Widget_Dob
{
    $block = new class extends Mage_Customer_Block_Widget_Dob {
        public string $format = '';

        #[\Override]
        public function getDateFormat()
        {
            return $this->format;
        }
    };
    $block->format = $format;
    foreach (['d', 'm', 'y'] as $code) {
        $block->setDateInput($code, "[$code]");
    }
    return $block;
}

it('puts the day, month and year inputs in the order of the ICU date format of the locale', function (string $format, string $order) {
    expect(dobWidgetWithFormat($format)->getSortedDateInputs())->toBe($order);
})->with([
    'en_US' => ['M/d/yy', '[m][d][y]'],
    'it_IT' => ['dd/MM/yy', '[d][m][y]'],
    'de_DE' => ['dd.MM.yy', '[d][m][y]'],
    'ja_JP' => ['y/MM/dd', '[y][m][d]'],
]);

it('gives the saved date as YYYY-MM-DD, the value that the server reads in every locale', function () {
    expect(dobWidgetWithFormat('dd/MM/yy')->setDate('1990-04-16 00:00:00')->getIsoDate())->toBe('1990-04-16')
        ->and(dobWidgetWithFormat('dd/MM/yy')->setDate(null)->getIsoDate())->toBe('');
});
