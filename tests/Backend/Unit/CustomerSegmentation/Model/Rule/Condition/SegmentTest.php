<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Create a segment and a customer of website 1. Make the customer a member when $member is true.
 *
 * @return array{customer_id: int, segment_id: int}
 */
function ruleSegmentConditionFixture(bool $member, bool $active = true): array
{
    $customer = Mage::getModel('customer/customer')
        ->setEmail('rule.segment.' . uniqid('', true) . '@test.com')
        ->setFirstname('Rule')
        ->setLastname('Segment')
        ->setWebsiteId(1)
        ->setGroupId(1);
    $customer->save();

    $segment = Mage::getModel('customersegmentation/segment')
        ->setName('Rule Segment ' . uniqid())
        ->setWebsiteIds([1])
        ->setIsActive($active);
    $segment->save();

    if ($member) {
        Mage::getResourceModel('customersegmentation/segment')
            ->updateCustomerMembership($segment, [(int) $customer->getId()]);
    }

    return ['customer_id' => (int) $customer->getId(), 'segment_id' => (int) $segment->getId()];
}

describe('Rule Condition Segment', function () {
    beforeEach(function () {
        $this->segmentCondition = Mage::getModel('customersegmentation/rule_condition_segment');
    });

    test('can create new segment condition instance', function () {
        expect($this->segmentCondition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Rule_Condition_Segment::class);
        expect($this->segmentCondition->getType())->toBe('customersegmentation/rule_condition_segment');
    });

    test('has correct attribute options', function () {
        $this->segmentCondition->loadAttributeOptions();
        $options = $this->segmentCondition->getAttributeOption();

        expect($options)->toBeArray();
        expect($options)->toHaveKey('customer_segment');
        expect($options['customer_segment'])->toBe('Customer Segment');
    });

    test('has correct input type', function () {
        expect($this->segmentCondition->getInputType())->toBe('select');
    });

    test('has correct value element type', function () {
        expect($this->segmentCondition->getValueElementType())->toBe('select');
    });

    test('offers the active segments and not the inactive segments', function () {
        $active = ruleSegmentConditionFixture(false);
        $inactive = ruleSegmentConditionFixture(false, false);

        $values = array_map(intval(...), array_column($this->segmentCondition->getValueSelectOptions(), 'value'));

        expect($values)->toContain($active['segment_id'])
            ->and($values)->not->toContain($inactive['segment_id']);
    });

    test('validates the segment membership of a customer', function (bool $member, ?int $websiteId, bool $expected) {
        $fixture = ruleSegmentConditionFixture($member);
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator('==');
        $this->segmentCondition->setValue((string) $fixture['segment_id']);

        $object = new \Maho\DataObject([
            'customer_id' => $fixture['customer_id'],
            'store' => $websiteId === null ? null : new \Maho\DataObject(['website_id' => $websiteId]),
        ]);

        expect($this->segmentCondition->validate($object))->toBe($expected);
    })->with([
        'a member' => [true, null, true],
        'a customer outside the segment' => [false, null, false],
        'a member in the website of the membership' => [true, 1, true],
        'a member in another website' => [true, 2, false],
    ]);

    test('handles guest customers correctly', function () {
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator('==');
        $this->segmentCondition->setValue('1');

        // Create test object without customer ID (guest)
        $testObject = new \Maho\DataObject([
            'customer_id' => null,
            'store' => null,
        ]);

        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBe(false);

        // Test with customer ID 0 (also guest)
        $testObject->setCustomerId(0);
        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBe(false);
    });

    test('applies each operator to a member of the segment', function (string $operator, bool $expected) {
        $fixture = ruleSegmentConditionFixture(true);
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator($operator);
        $this->segmentCondition->setValue((string) $fixture['segment_id']);

        $object = new \Maho\DataObject(['customer_id' => $fixture['customer_id'], 'store' => null]);

        expect($this->segmentCondition->validate($object))->toBe($expected);
    })->with([
        'is' => ['==', true],
        'is not' => ['!=', false],
        'is one of' => ['()', true],
        'is not one of' => ['!()', false],
    ]);

    test('caches value select options', function () {
        $options = $this->segmentCondition->getValueSelectOptions();
        $later = ruleSegmentConditionFixture(false);

        $cached = $this->segmentCondition->getValueSelectOptions();

        expect($cached)->toEqual($options)
            ->and(array_map(intval(...), array_column($cached, 'value')))->not->toContain($later['segment_id']);
    });
});
