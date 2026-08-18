<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Fixtures whose construction reaches back into their own alias, the way
 * Mage_Core_Model_Resource does when tracer init runs inside its constructor.
 */
class Maho_Test_Reentrancy_Model extends Mage_Core_Model_Abstract
{
    public static bool $reentered = false;
    public static ?self $inner = null;

    public function __construct(array $args = [])
    {
        if (!self::$reentered) {
            self::$reentered = true;
            self::$inner = Mage::getSingleton('mahotestreentrancy/model');
        }
        parent::__construct($args);
    }
}

class Maho_Test_Reentrancy_Resource_Model
{
    public static bool $reentered = false;
    public static ?self $inner = null;

    public function __construct(array $args = [])
    {
        if (!self::$reentered) {
            self::$reentered = true;
            self::$inner = Mage::getResourceSingleton('mahotestreentrancy/model');
        }
    }
}

class Maho_Test_Reentrancy_Helper_Data extends Mage_Core_Helper_Abstract
{
    public static bool $reentered = false;
    public static ?self $inner = null;

    public function __construct()
    {
        if (!self::$reentered) {
            self::$reentered = true;
            self::$inner = Mage::helper('mahotestreentrancy');
        }
    }
}

class Maho_Test_Reentrancy_Resource_Helper extends Mage_Core_Model_Resource_Helper_Abstract
{
    public static bool $reentered = false;
    public static ?self $inner = null;

    public function __construct($module)
    {
        parent::__construct($module);
        if (!self::$reentered) {
            self::$reentered = true;
            self::$inner = Mage::getResourceHelper('mahotestreentrancy');
        }
    }

    #[\Override]
    public function addLikeEscape($value, $options = [])
    {
        return $value;
    }

    #[\Override]
    public function castField($field)
    {
        return $field;
    }
}

beforeEach(function () {
    Maho_Test_Reentrancy_Model::$reentered = false;
    Maho_Test_Reentrancy_Model::$inner = null;
    Maho_Test_Reentrancy_Resource_Model::$reentered = false;
    Maho_Test_Reentrancy_Resource_Model::$inner = null;
    Maho_Test_Reentrancy_Helper_Data::$reentered = false;
    Maho_Test_Reentrancy_Helper_Data::$inner = null;
    Maho_Test_Reentrancy_Resource_Helper::$reentered = false;
    Maho_Test_Reentrancy_Resource_Helper::$inner = null;

    $config = Mage::getConfig();
    $config->setNode('global/models/mahotestreentrancy/class', 'Maho_Test_Reentrancy');
    $config->setNode('global/models/mahotestreentrancy/resourceModel', 'mahotestreentrancy_resource');
    $config->setNode('global/models/mahotestreentrancy_resource/class', 'Maho_Test_Reentrancy_Resource');
    $config->setNode('global/helpers/mahotestreentrancy/class', 'Maho_Test_Reentrancy_Helper');

    // The resource helper class name carries the connection engine, so bind the
    // fixture to whatever name this backend resolves to.
    $resourceHelperClass = $config->getResourceHelperClassName('mahotestreentrancy');
    if (is_string($resourceHelperClass) && !class_exists($resourceHelperClass)) {
        class_alias(Maho_Test_Reentrancy_Resource_Helper::class, $resourceHelperClass);
    }
});

describe('a constructor that re-enters its own singleton', function () {
    it('keeps the first instance for getSingleton()', function () {
        $singleton = Mage::getSingleton('mahotestreentrancy/model');

        expect(Maho_Test_Reentrancy_Model::$inner)->toBeInstanceOf(Maho_Test_Reentrancy_Model::class)
            ->and($singleton)->toBe(Maho_Test_Reentrancy_Model::$inner)
            ->and(Mage::getSingleton('mahotestreentrancy/model'))->toBe($singleton);
    });

    it('keeps the first instance for getResourceSingleton()', function () {
        $singleton = Mage::getResourceSingleton('mahotestreentrancy/model');

        expect(Maho_Test_Reentrancy_Resource_Model::$inner)->toBeInstanceOf(Maho_Test_Reentrancy_Resource_Model::class)
            ->and($singleton)->toBe(Maho_Test_Reentrancy_Resource_Model::$inner)
            ->and(Mage::getResourceSingleton('mahotestreentrancy/model'))->toBe($singleton);
    });

    it('keeps the first instance for helper()', function () {
        $helper = Mage::helper('mahotestreentrancy');

        expect(Maho_Test_Reentrancy_Helper_Data::$inner)->toBeInstanceOf(Maho_Test_Reentrancy_Helper_Data::class)
            ->and($helper)->toBe(Maho_Test_Reentrancy_Helper_Data::$inner)
            ->and(Mage::helper('mahotestreentrancy'))->toBe($helper);
    });

    it('keeps the first instance for getResourceHelper()', function () {
        $helper = Mage::getResourceHelper('mahotestreentrancy');

        expect(Maho_Test_Reentrancy_Resource_Helper::$inner)->toBeInstanceOf(Maho_Test_Reentrancy_Resource_Helper::class)
            ->and($helper)->toBe(Maho_Test_Reentrancy_Resource_Helper::$inner)
            ->and(Mage::getResourceHelper('mahotestreentrancy'))->toBe($helper);
    });

    it('still fails when the caller asked for constructor arguments', function () {
        expect(fn() => Mage::getSingleton('mahotestreentrancy/model', ['foo' => 'bar']))
            ->toThrow(Mage_Core_Exception::class, 'Mage registry key _singleton/mahotestreentrancy/model already exists');
    });

    it('still fails when the resource caller asked for constructor arguments', function () {
        expect(fn() => Mage::getResourceSingleton('mahotestreentrancy/model', ['foo' => 'bar']))
            ->toThrow(Mage_Core_Exception::class, 'Mage registry key _resource_singleton/mahotestreentrancy/model already exists');
    });
});
