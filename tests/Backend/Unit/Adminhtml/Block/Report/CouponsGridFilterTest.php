<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The coupons report filter form is a multiselect, so the grid must pass on every rule the
 * user picked. It used to read the first entry only and split it on commas.
 */

/** Run the grid's custom filter over $filterData and return the rule ids that the report collection received. */
function couponsRuleFilter(array $filterData): ?array
{
    $grid = Mage::app()->getLayout()->createBlock('adminhtml/report_sales_coupons_grid');
    $collection = new class extends Mage_SalesRule_Model_Resource_Report_Collection {
        public ?array $rulesList = null;

        #[\Override]
        public function addRuleFilter($rulesList)
        {
            $this->rulesList = $rulesList;
            return $this;
        }
    };

    Closure::bind(
        function ($collection, $filterData) {
            $this->_addCustomFilter($collection, $filterData);
        },
        $grid,
        $grid::class,
    )($collection, new Maho\DataObject($filterData));

    return $collection->rulesList;
}

describe('Mage_Adminhtml_Block_Report_Sales_Coupons_Grid::_addCustomFilter', function () {
    it('filters on every selected price rule', function () {
        expect(couponsRuleFilter(['price_rule_type' => '1', 'rules_list' => ['3', '7']]))
            ->toBe(['3', '7']);
    });

    it('filters on a single selected price rule', function () {
        expect(couponsRuleFilter(['price_rule_type' => '1', 'rules_list' => ['3']]))
            ->toBe(['3']);
    });

    it('does not filter when no price rule is selected', function () {
        expect(couponsRuleFilter(['price_rule_type' => '1']))->toBeNull();
    });

    it('does not filter when the report runs for any price rule', function () {
        expect(couponsRuleFilter(['rules_list' => ['3', '7']]))->toBeNull();
    });
});
