<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

dataset('core and cms template filters', [
    'email' => [Mage_Core_Model_Email_Template_Filter::class],
    'cms' => [Mage_Cms_Model_Template_Filter::class],
]);

it('clears the variable processing guard when a variable getter raises an error', function (string $filterType): void {
    $filter = $this->getMockBuilder($filterType)
        ->disableOriginalConstructor()
        ->onlyMethods(['getEmailPathValidator'])
        ->getMock();
    $failure = new TypeError('The example label is unavailable.');
    $label = new class ($failure) extends Maho\DataObject {
        public function __construct(private readonly TypeError $failure) {}

        public function getText(): never
        {
            expect(Mage::registry('varProcessing'))->toBeTrue();
            throw $this->failure;
        }
    };
    $filter->setVariables(['label' => $label, 'sample' => 'Example']);

    try {
        $caught = null;
        try {
            $filter->filter('Label: {{var label.text}}.');
        } catch (TypeError $error) {
            $caught = $error;
        }

        expect($caught)->toBe($failure)
            ->and([
                'guard' => Mage::registry('varProcessing'),
                'next render' => $filter->filter('Label: {{var sample}}.'),
            ])->toBe(['guard' => null, 'next render' => 'Label: Example.']);
    } finally {
        Mage::unregister('varProcessing');
    }
})->with('core and cms template filters');

it('renders an empty variable when a variable getter raises an exception', function (string $filterType, bool $developerMode): void {
    $filter = $this->getMockBuilder($filterType)
        ->disableOriginalConstructor()
        ->onlyMethods(['getEmailPathValidator'])
        ->getMock();
    $label = new class extends Maho\DataObject {
        public function getText(): never
        {
            throw new RuntimeException('The example label is unavailable.');
        }
    };
    $filter->setVariables(['label' => $label]);
    $previousDeveloperMode = Mage::getIsDeveloperMode();
    Mage::setIsDeveloperMode($developerMode);
    try {
        expect($filter->filter('Label: {{var label.text}}.'))->toBe('Label: .')
            ->and(Mage::registry('varProcessing'))->toBeNull();
    } finally {
        Mage::setIsDeveloperMode($previousDeveloperMode);
        Mage::unregister('varProcessing');
    }
})->with('core and cms template filters')->with(['normal' => false, 'developer' => true]);
