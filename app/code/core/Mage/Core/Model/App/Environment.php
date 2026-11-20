<?php

/**
 * The current store, the design, the locale and the translations of the application at one moment.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final readonly class Mage_Core_Model_App_Environment
{
    /**
     * @param array{0: string|int|Mage_Core_Model_Store|null, 1: ?string, 2: ?string, 3: ?array} $design
     * @param array{0: ?string, 1: ?array, 2: ?string, 3: ?array, 4: ?array} $translator
     */
    private function __construct(
        private Mage_Core_Model_App $app,
        private int $storeId,
        private array $design,
        private string $localeCode,
        private array $translator,
    ) {}

    public static function capture(Mage_Core_Model_App $app): self
    {
        return new self(
            $app,
            (int) $app->getStore()->getId(),
            Mage::getDesign()->getState(),
            $app->getLocale()->getLocaleCode(),
            $app->getTranslator()->getState(),
        );
    }

    /**
     * Put back the state that capture() read.
     */
    public function restore(): void
    {
        // The design reads the current store when its own store is not set
        $this->app->setCurrentStore($this->storeId);
        Mage::getDesign()->setState($this->design);
        $this->app->getLocale()->setLocaleCode($this->localeCode);
        $this->app->getTranslator()->setState($this->translator);
    }
}
