<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The translator loads one set of translations for each context (area, locale, store, package, theme).
 * A load from the files and a load from the cache give the same set.
 */
function translatorWithFixtureFiles(): Mage_Core_Model_Translate
{
    return new class extends Mage_Core_Model_Translate {
        /** @var array<string, array<string, array<string, string>>> */
        private array $files = [
            Mage_Core_Model_App_Area::AREA_FRONTEND => [
                'first.csv' => ['Hello' => 'Hello from the first module'],
                'second.csv' => ['Hello' => 'Hello from the second module'],
            ],
            Mage_Core_Model_App_Area::AREA_ADMINHTML => [
                'first.csv' => ['Hello' => 'Hello from the admin'],
            ],
        ];

        #[\Override]
        public function getModulesConfig()
        {
            return [
                'Mage_First' => new \Maho\Simplexml\Element('<Mage_First><files><default>first.csv</default></files></Mage_First>'),
                'Mage_Second' => new \Maho\Simplexml\Element('<Mage_Second><files><default>second.csv</default></files></Mage_Second>'),
            ];
        }

        #[\Override]
        protected function _getModuleFilePath($module, $fileName)
        {
            return $fileName;
        }

        #[\Override]
        protected function _getFileData($file)
        {
            return $this->files[$this->getConfig(self::CONFIG_KEY_AREA)][$file] ?? [];
        }

        #[\Override]
        protected function _loadThemeTranslation()
        {
            return $this;
        }

        #[\Override]
        protected function _loadDbTranslation()
        {
            return $this;
        }

        #[\Override]
        protected function _canUseCache()
        {
            return false;
        }
    };
}

describe('Translator sets', function () {
    it('gives the same translations with and without a forced reload', function () {
        $forced = translatorWithFixtureFiles()->setLocale('en_US')->init(Mage_Core_Model_App_Area::AREA_FRONTEND, true)->getData();
        $loaded = translatorWithFixtureFiles()->setLocale('en_US')->init(Mage_Core_Model_App_Area::AREA_FRONTEND)->getData();

        expect($forced)->toBe($loaded)
            ->and($forced)->toHaveKey('Mage_Second' . Mage_Core_Model_Translate::SCOPE_SEPARATOR . 'Hello', 'Hello from the second module');
    });

    it('uses the cache key of the current context after a change of area', function () {
        $translator = translatorWithFixtureFiles()->setLocale('en_US');
        $translator->init(Mage_Core_Model_App_Area::AREA_FRONTEND);
        $translator->init(Mage_Core_Model_App_Area::AREA_ADMINHTML);

        expect($translator->getCacheId())->toContain('_' . Mage_Core_Model_App_Area::AREA_ADMINHTML . '_')
            ->and($translator->translate(['Hello']))->toBe('Hello from the admin');
    });

    it('uses the cache key of the current context after a change of locale', function () {
        $translator = translatorWithFixtureFiles()->setLocale('en_US');
        $translator->init(Mage_Core_Model_App_Area::AREA_FRONTEND);

        $translator->setLocale('de_DE')->init(Mage_Core_Model_App_Area::AREA_FRONTEND);

        expect($translator->getCacheId())->toContain('_de_DE_');
    });

    it('reads an empty set from the cache, and does not read the files again', function () {
        $translator = new class extends Mage_Core_Model_Translate {
            public int $fileReads = 0;

            #[\Override]
            public function getModulesConfig()
            {
                return ['Mage_First' => new \Maho\Simplexml\Element('<Mage_First><files><default>first.csv</default></files></Mage_First>')];
            }

            #[\Override]
            protected function _getFileData($file)
            {
                $this->fileReads++;
                return [];
            }

            #[\Override]
            protected function _loadThemeTranslation()
            {
                return $this;
            }

            #[\Override]
            protected function _loadDbTranslation()
            {
                return $this;
            }

            #[\Override]
            protected function _canUseCache()
            {
                return true;
            }
        };
        $translator->setLocale('xx_XX');

        try {
            $translator->init(Mage_Core_Model_App_Area::AREA_FRONTEND);
            $translator->init(Mage_Core_Model_App_Area::AREA_FRONTEND);

            expect($translator->fileReads)->toBe(1)
                ->and($translator->getData())->toBe([]);
        } finally {
            Mage::app()->removeCache($translator->getCacheId());
        }
    });
});
