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
    private function __construct(
        private Mage_Core_Model_App $app,
        private int $storeId,
        private Mage_Core_Model_Design_State $design,
        private string $localeCode,
        private Mage_Core_Model_Translate_State $translator,
    ) {}

    public static function capture(Mage_Core_Model_App $app): self
    {
        return new self(
            app: $app,
            storeId: (int) $app->getStore()->getId(),
            design: Mage::getDesign()->getState(),
            localeCode: $app->getLocale()->getLocaleCode(),
            translator: $app->getTranslator()->getState(),
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
