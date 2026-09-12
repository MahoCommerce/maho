<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests;

use Mage;
use Mage_Core_Model_Config;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Boots the application once per process and gives every test a clean registry.
 *
 * Rebuilding the application per test costs about 14 ms, most of it reloading
 * the store structure, so tearDown() keeps the application and drops only what
 * the test built on top of it. A test that writes to the configuration
 * (Store::setConfig() edits the shared tree, saveConfig() edits the database)
 * gets the full reset instead, because only a rebuild restores the tree.
 *
 * A boot installs an error handler, and PHPUnit reports a test that ends on a
 * different handler stack than it started on as risky. So the test that booted
 * is the one that pops, and booting in setUpBeforeClass() keeps the common case
 * free of both a push and a pop.
 */
abstract class MahoTestCase extends BaseTestCase
{
    private static bool $triedWarmingConfigCache = false;

    private bool $bootedApplication = false;
    private int $configWritesAtStart = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::bootApplication();
    }

    /**
     * Boot the application on the cached configuration. A boot that finds no
     * cache writes one for the next process but goes on reading the tree it
     * built in memory, which a cache flush inside a test cannot reach, so that
     * first application is thrown away and a second one takes its place.
     */
    private static function bootApplication(): void
    {
        Mage::setRoot(\dirname(__DIR__));
        Mage::app();

        if (Mage::getConfig()->isCacheUsed() || self::$triedWarmingConfigCache) {
            return;
        }
        self::$triedWarmingConfigCache = true;

        Mage::reset();
        restore_error_handler();
        restore_exception_handler();
        Mage::setRoot(\dirname(__DIR__));
        Mage::app();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setMahoRoot();
        $this->bootedApplication = !Mage::isAppInitialized();
        self::bootApplication();
        $this->configWritesAtStart = Mage_Core_Model_Config::getWriteCount();
    }

    protected function setMahoRoot(): void
    {
        Mage::setRoot(\dirname(__DIR__));
    }

    protected function tearDown(): void
    {
        if (Mage_Core_Model_Config::getWriteCount() === $this->configWritesAtStart) {
            Mage::softReset();
        } else {
            Mage::reset();
        }

        if ($this->bootedApplication) {
            restore_error_handler();
            restore_exception_handler();
        }

        parent::tearDown();
    }
}
