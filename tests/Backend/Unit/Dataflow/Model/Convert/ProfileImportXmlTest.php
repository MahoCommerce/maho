<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Dataflow_Model_Convert_Profile_Abstract::importXml', function () {
    it('adds each action node to the profile and attaches it to the named container', function () {
        $profile = new Mage_Dataflow_Model_Convert_Profile();
        $container = $profile->addContainer('parser', new Mage_Dataflow_Model_Convert_Parser_Csv());

        $profile->importXml(new Maho\Simplexml\Element(
            '<profile><action use="parser" method="parse"><var name="delimiter">;</var></action></profile>',
        ));

        expect($container->getVar('delimiter'))->toBe(';')
            ->and($container->getProfile())->toBe($profile)
            ->and($container->getAction()->getParam('method'))->toBe('parse');
    });
});

it('finds a profile whose name contains both quote types', function (): void {
    $profiles = new Mage_Dataflow_Model_Convert_Profile_Collection();
    $profiles->importXml(new Maho\Simplexml\Element(
        '<config><profile name="O&apos;Brien &quot;VIP&quot;"></profile></config>',
    ));

    expect($profiles->getProfile('O\'Brien "VIP"'))->not->toBeNull()
        ->and($profiles->getProfile('missing'))->toBeNull();
});
