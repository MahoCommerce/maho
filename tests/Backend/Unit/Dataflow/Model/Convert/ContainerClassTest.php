<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class DataflowContainerProbe
{
    public static bool $constructed = false;

    public function __construct()
    {
        self::$constructed = true;
    }

    public function probe(): void {}
}

function dataflowProfileFromActions(string $actionsXml): Mage_Dataflow_Model_Convert_Profile
{
    $xml = '<convert version="1.0"><profile name="default">' . $actionsXml . '</profile></convert>';
    return Mage::getModel('core/convert')->importXml($xml)->getProfile('default');
}

beforeEach(function () {
    DataflowContainerProbe::$constructed = false;
});

describe('Dataflow profile XML types', function () {
    it('refuses an action type that is not a container before it creates the class', function (string $type) {
        expect(fn() => dataflowProfileFromActions('<action type="' . $type . '" method="probe"/>'))
            ->toThrow(Mage_Core_Exception::class, 'not a Dataflow container');
        expect(DataflowContainerProbe::$constructed)->toBeFalse();
    })->with([
        'class without a slash' => [DataflowContainerProbe::class],
        'data object' => [Maho\DataObject::class],
        'model alias of another model' => ['core/store'],
        'unknown class' => ['No_Such_Class'],
        'path' => ['../../etc/passwd'],
        'empty' => [''],
    ]);

    it('refuses a container type that is not a container before it creates the class', function () {
        $xml = '<convert version="1.0"><container name="x" type="' . DataflowContainerProbe::class . '"/></convert>';

        expect(fn() => Mage::getModel('core/convert')->importXml($xml))
            ->toThrow(Mage_Core_Exception::class, 'not a Dataflow container');
        expect(DataflowContainerProbe::$constructed)->toBeFalse();
    });

    it('accepts a real container as a model alias or as a class name', function (string $type) {
        $profile = dataflowProfileFromActions('<action type="' . $type . '" method="load"/>');

        $actions = (fn() => $this->_actions)->call($profile);
        expect($actions[0]->getContainer())->toBeInstanceOf(Mage_Dataflow_Model_Convert_Adapter_Io::class);
    })->with([
        'model alias' => ['dataflow/convert_adapter_io'],
        'class name' => [Mage_Dataflow_Model_Convert_Adapter_Io::class],
    ]);

    it('stops the action when the container has no such method', function () {
        $profile = dataflowProfileFromActions('<action type="dataflow/convert_adapter_io" method="doesNotExist"/>');

        $profile->run();

        $messages = array_map(fn($e) => $e->getMessage(), $profile->getExceptions());
        expect($messages)->toContain('Unable to run action method: doesNotExist');
        foreach ($messages as $message) {
            expect($message)->not->toStartWith('Starting ');
        }
    });

    it('refuses a class that is not a container in Mage_Dataflow_Model_Convert::convert()', function () {
        expect(fn() => Mage_Dataflow_Model_Convert::convert(DataflowContainerProbe::class, 'probe', []))
            ->toThrow(Mage_Core_Exception::class, 'not a Dataflow container');
        expect(DataflowContainerProbe::$constructed)->toBeFalse();
    });
});

describe('Maho\Convert profile XML types', function () {
    it('refuses a container type that is not a container before it creates the class', function () {
        $xml = '<convert><container name="x" type="' . DataflowContainerProbe::class . '"/></convert>';

        expect(fn() => (new Maho\Convert\Profile\Collection())->importXml($xml))
            ->toThrow(Maho\Convert\Exception::class, 'not a container');
        expect(DataflowContainerProbe::$constructed)->toBeFalse();
    });

    it('refuses an action class that is not a container before it creates the class', function () {
        $action = (new Maho\Convert\Action())->setParam('class', DataflowContainerProbe::class);

        expect(fn() => $action->getContainer())->toThrow(Maho\Convert\Exception::class, 'not a container');
        expect(DataflowContainerProbe::$constructed)->toBeFalse();
    });

    it('accepts a real container class', function () {
        $action = (new Maho\Convert\Action())->setParam('class', Maho\Convert\Container\Generic::class);
        $action->setProfile(new Maho\Convert\Profile());

        expect($action->getContainer())->toBeInstanceOf(Maho\Convert\Container\Generic::class);
    });
});
