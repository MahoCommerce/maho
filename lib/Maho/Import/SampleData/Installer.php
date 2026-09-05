<?php

/**
 * Imports a sample data package in a fixed order: shared stores, attributes and config, media, then every pack.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\SampleData;

use Mage;
use Maho\Import\Importer\AbstractCmsImporter;
use Maho\Import\Importer\Attributes;
use Maho\Import\Importer\AttributeSets;
use Maho\Import\Importer\BlogPosts;
use Maho\Import\Importer\Categories;
use Maho\Import\Importer\CmsBlocks;
use Maho\Import\Importer\CmsPages;
use Maho\Import\Importer\Config;
use Maho\Import\Importer\Customers;
use Maho\Import\Importer\Products;
use Maho\Import\Importer\Ratings;
use Maho\Import\Importer\Reviews;
use Maho\Import\Importer\Stores;
use Maho\Import\ImporterInterface;
use Maho\Import\NullReporter;
use Maho\Import\Reporter;
use Maho\Import\Result;

final class Installer
{
    /** Files of one pack in import order; blocks run twice, since blocks and categories point at each other. */
    private const PACK_FILES = ['cms_blocks.csv', 'categories.csv', 'products.csv', 'reviews.csv', 'cms_blocks.csv', 'cms_pages.csv', 'blog_posts.csv'];

    private readonly Reporter $reporter;

    public function __construct(?Reporter $reporter = null)
    {
        $this->reporter = $reporter ?? new NullReporter();
    }

    /**
     * @param list<string>|null $packs pack names to import; null imports every pack
     */
    public function install(Package $package, ?array $packs = null, bool $reindex = true): Result
    {
        $packs ??= $package->packs();
        foreach ($packs as $pack) {
            if (!is_dir($package->packDir($pack))) {
                throw new \Maho\Exception("unknown pack '$pack' in " . $package->packsDir());
            }
        }
        $result = new Result();
        $steps = 5 + count($packs) + 2 + ($reindex ? 1 : 0);
        $done = 0;

        $this->step(++$done, $steps, 'Stores');
        $this->run($result, new Stores(), $package->sharedDir() . '/stores.csv');
        $this->reinitStores();
        Mage::app()->getCache()->cleanType('config');

        $this->step(++$done, $steps, 'Attributes');
        $this->run($result, new AttributeSets(), $package->sharedDir() . '/attribute_sets.csv');
        $options = [];
        if (is_file($package->sharedDir() . '/attribute_options.csv')) {
            $options[Attributes::OPTION_OPTIONS_CSV] = $package->sharedDir() . '/attribute_options.csv';
        }
        $this->run($result, new Attributes(), $package->sharedDir() . '/attributes.csv', $options);
        $this->clearEavCache();

        $this->step(++$done, $steps, 'Ratings');
        $this->run($result, new Ratings(), $package->sharedDir() . '/ratings.csv');

        $this->step(++$done, $steps, 'Configuration');
        $this->run($result, new Config(), $package->sharedDir() . '/config.csv');
        Mage::app()->getCache()->cleanType('config');
        $this->reinitStores();

        $this->step(++$done, $steps, 'Media');
        $this->copyMedia($package->mediaDir(), Mage::getBaseDir('media'));

        foreach ($packs as $pack) {
            $this->step(++$done, $steps, 'Pack ' . $pack);
            $this->installPack($result, $package->packDir($pack));
        }

        $this->step(++$done, $steps, 'Customers');
        $this->run($result, new Customers(), $package->sharedDir() . '/customers.csv');

        if ($reindex) {
            $this->step(++$done, $steps, 'Reindex');
            $this->reindexAll();
        }

        $this->step(++$done, $steps, 'Cache');
        Mage::app()->getCache()->flush();
        return $result;
    }

    private function installPack(Result $result, string $dir): void
    {
        foreach (self::PACK_FILES as $index => $file) {
            $path = $dir . '/' . $file;
            $options = match ($file) {
                'cms_blocks.csv' => [AbstractCmsImporter::OPTION_CONTENT_DIR => $dir . '/content', AbstractCmsImporter::OPTION_LENIENT_MACROS => $index === 0],
                'cms_pages.csv', 'blog_posts.csv' => [AbstractCmsImporter::OPTION_CONTENT_DIR => $dir . '/content'],
                'categories.csv' => [Categories::OPTION_MEDIA_DIR => $dir . '/media/catalog/category'],
                'products.csv' => [Products::OPTION_MEDIA_DIR => $dir . '/media/import'],
                default => [],
            };
            $importer = match ($file) {
                'cms_blocks.csv' => new CmsBlocks(),
                'categories.csv' => new Categories(),
                'products.csv' => new Products(),
                'reviews.csv' => new Reviews(),
                'cms_pages.csv' => new CmsPages(),
                'blog_posts.csv' => new BlogPosts(),
            };
            $this->run($result, $importer, $path, $options);
        }
        Mage::app()->getCache()->cleanType('config');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function run(Result $result, ImporterInterface $importer, string $path, array $options = []): void
    {
        if (!is_file($path)) {
            return;
        }
        $this->reporter->info('  ' . basename(dirname($path)) . '/' . basename($path));
        $partial = $importer->import($path, $options, $this->reporter);
        foreach ($partial->notices as $notice) {
            $this->reporter->warning($notice);
        }
        $partial->notices = [];
        $result->merge($partial);
    }

    private function step(int $done, int $total, string $label): void
    {
        $this->reporter->progress($done, $total, $label);
    }

    private function clearEavCache(): void
    {
        Mage::getSingleton('eav/config')->clear();
        Mage::unregister('_singleton/eav/config');
        Mage::unregister('_helper/eav');
        Mage::app()->getCache()->cleanType('eav');
        Mage::app()->getCache()->cleanType('config');
    }

    private function copyMedia(string $source, string $target): void
    {
        if (!is_dir($source)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            $destination = $target . '/' . $items->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
                    throw new \Maho\Exception("cannot create $destination");
                }
            } elseif (!copy($item->getPathname(), $destination)) {
                throw new \Maho\Exception("cannot copy {$item->getPathname()} to $destination");
            }
        }
    }

    /**
     * Reloads the stores and keeps the admin store current: a renamed store code must not leave a stale current store behind.
     */
    private function reinitStores(): void
    {
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
        Mage::app()->setCurrentStore(\Mage_Core_Model_Store::ADMIN_CODE);
    }

    private function reindexAll(): void
    {
        $this->reinitStores();
        foreach (Mage::getResourceModel('index/process_collection') as $process) {
            /** @var \Mage_Index_Model_Process $process */
            if ($process->isLocked()) {
                $process->unlock();
            }
            $process->reindexEverything();
        }
    }
}
