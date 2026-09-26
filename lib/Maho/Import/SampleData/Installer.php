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
use Maho\Import\Importer\Orders;
use Maho\Import\Importer\ProductViews;
use Maho\Import\Importer\Products;
use Maho\Import\Importer\Ratings;
use Maho\Import\Importer\Reviews;
use Maho\Import\Importer\SearchTerms;
use Maho\Import\Importer\Stores;
use Maho\Import\ImporterInterface;
use Maho\Import\NullReporter;
use Maho\Import\Reporter;
use Maho\Import\Result;

final class Installer
{
    /** Files of one pack in import order; blocks run twice, since blocks and categories point at each other. */
    private const PACK_FILES = ['cms_blocks.csv', 'categories.csv', 'products.csv', 'reviews.csv', 'cms_blocks.csv', 'cms_pages.csv', 'blog_posts.csv'];

    /** Files of one pack that record store activity; they run after the customers, since an order names its customer. */
    private const ACTIVITY_FILES = ['orders.csv', 'product_views.csv', 'search_terms.csv'];

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
        $steps = 5 + count($packs) + 3 + ($reindex ? 1 : 0);
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
        $this->copyMedia($package->mediaDir(), Mage::getStorage('media'));

        foreach ($packs as $pack) {
            $this->step(++$done, $steps, 'Pack ' . $pack);
            $this->installPack($result, $package->packDir($pack));
        }

        $this->step(++$done, $steps, 'Customers');
        $this->run($result, new Customers(), $package->sharedDir() . '/customers.csv');

        $this->step(++$done, $steps, 'Activity');
        $this->installActivity($result, array_map($package->packDir(...), $packs));

        if ($reindex) {
            $this->step(++$done, $steps, 'Reindex');
            $this->reindexAll();
        }

        $this->step(++$done, $steps, 'Cache');
        Mage::app()->getCache()->flush();
        $this->reporter->finish();
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
                'products.csv' => [Products::OPTION_MEDIA_DIR => $dir . '/media/import', Products::OPTION_TRUSTED_MEDIA => true],
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
     * Imports the orders, product views and search terms of every pack, then refreshes the report statistics once.
     *
     * @param list<string> $dirs
     */
    private function installActivity(Result $result, array $dirs): void
    {
        $activity = new Result();
        foreach ($dirs as $dir) {
            foreach (self::ACTIVITY_FILES as $file) {
                $importer = match ($file) {
                    'orders.csv' => new Orders(),
                    'product_views.csv' => new ProductViews(),
                    'search_terms.csv' => new SearchTerms(),
                };
                $options = match ($file) {
                    'orders.csv' => [Orders::OPTION_SKIP_STATISTICS => true],
                    'product_views.csv' => [ProductViews::OPTION_SKIP_STATISTICS => true],
                    default => [],
                };
                // The search terms feed no statistic, so they do not count for the refresh.
                $this->run($file === 'search_terms.csv' ? $result : $activity, $importer, $dir . '/' . $file, $options);
            }
        }
        if ($activity->created + $activity->updated > 0) {
            Mage::getModel('reports/statistics')->refreshLifetime([...Orders::STATISTICS, ...ProductViews::STATISTICS]);
        }
        $result->merge($activity);
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
        $this->reporter->step($done, $total, $label);
    }

    private function clearEavCache(): void
    {
        Mage::getSingleton('eav/config')->clear();
        Mage::unregister('_singleton/eav/config');
        Mage::unregister('_helper/eav');
        Mage::app()->getCache()->cleanType('eav');
        Mage::app()->getCache()->cleanType('config');
    }

    private function copyMedia(string $source, \Maho\Storage\Mount $target): void
    {
        if (!is_dir($source)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($items as $item) {
            $path = str_replace('\\', '/', $items->getSubPathname());
            try {
                \Maho\Storage\Mount::copyLocalFile($item->getPathname(), $target, $path);
            } catch (\Throwable $e) {
                throw new \Maho\Exception("cannot copy {$item->getPathname()} to $path: {$e->getMessage()}", 0, $e);
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
