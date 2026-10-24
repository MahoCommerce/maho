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
