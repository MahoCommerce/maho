<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Mage\Core\Api\StoreConfigProvider;
use Mage\Core\Api\StoreProcessor;
use Mage\Core\Api\StoreProvider;
use Maho\Import\Importer\Stores;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\TransportParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin store pages, the store importer and the store API go through Mage_Core_Service_Store (#1348).
 */

const STORE_PARITY_WEBSITE = 'parity_web';

/**
 * Record each model that a transport saves, then save it.
 */
class StoreParitySpyService extends Mage_Core_Service_Store
{
    /** @var list<Mage_Core_Model_Website|Mage_Core_Model_Store_Group|Mage_Core_Model_Store> */
    public array $saved = [];

    #[\Override]
    public function save(Mage_Core_Model_Website|Mage_Core_Model_Store_Group|Mage_Core_Model_Store $model): Mage_Core_Model_Website|Mage_Core_Model_Store_Group|Mage_Core_Model_Store
    {
        $this->saved[] = $model;
        return parent::save($model);
    }
}

function storeParityCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'stores') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function storeParityStore(string $code): Mage_Core_Model_Store
{
    return Mage::getModel('core/store')->load($code, 'code');
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    deletePriceWebsite(STORE_PARITY_WEBSITE);
    $this->website = createPriceWebsite(STORE_PARITY_WEBSITE);
    $this->group = $this->website->getDefaultGroup();

    Mage::unregister('_service/core/store');
    $this->spy = new StoreParitySpyService();
    Mage::register('_service/core/store', $this->spy);
});

afterEach(function (): void {
    deletePriceWebsite(STORE_PARITY_WEBSITE);
});

it('saves a store view through the service on the admin page and in the importer, with the same rules', function (): void {
    $admin = TransportParity::admin(Mage_Adminhtml_System_StoreController::class, 'save', [
        'store_type' => 'store',
        'store_action' => 'add',
        'store' => [
            'store_id' => '',
            'group_id' => (string) $this->group->getId(),
            'website_id' => '1',
            'name' => '<b>Admin View</b>',
            'code' => 'parity_admin',
            'is_active' => '1',
            'sort_order' => '0',
        ],
    ]);

    $rootCategory = Mage::getModel('catalog/category')->load($this->group->getRootCategoryId())->getName();
    $path = storeParityCsv([
        ['website_code', 'group_name', 'root_category', 'store_code', 'store_name'],
        [STORE_PARITY_WEBSITE, $this->group->getName(), $rootCategory, 'parity_import', '<i>Import View</i>'],
    ]);
    new Stores()->import($path);
    unlink($path);

    $savedStores = array_values(array_map(
        fn(Mage_Core_Model_Store $store): ?string => $store->getCode(),
        array_filter($this->spy->saved, fn(object $model): bool => $model instanceof Mage_Core_Model_Store),
    ));
    expect($admin)->toBe(['success' => ['The store view has been saved'], 'errors' => []])
        ->and($savedStores)->toBe(['parity_admin', 'parity_import'])
        ->and(storeParityStore('parity_admin')->getName())->toBe('Admin View')
        ->and(storeParityStore('parity_import')->getName())->toBe('Import View')
        ->and((int) storeParityStore('parity_admin')->getWebsiteId())->toBe((int) $this->website->getId())
        ->and((int) storeParityStore('parity_import')->getWebsiteId())->toBe((int) $this->website->getId());
});

it('refuses a store view code with the same rule on the admin page and in the importer', function (): void {
    $admin = TransportParity::admin(Mage_Adminhtml_System_StoreController::class, 'save', [
        'store_type' => 'store',
        'store_action' => 'add',
        'store' => ['store_id' => '', 'group_id' => (string) $this->group->getId(), 'name' => 'Bad', 'code' => 'Bad Code', 'is_active' => '1'],
    ]);
    $path = storeParityCsv([['website_code', 'root_category', 'store_code'], [STORE_PARITY_WEBSITE, 'Root', 'Bad Code']]);

    expect($admin['errors'])->toBe(['The store code may contain only letters (a-z), numbers (0-9), underscore(_) or dash(-), the first character must be a letter'])
        ->and(fn() => new Stores()->validate($path))->toThrow(Maho\Import\RowException::class, Mage_Core_Model_Store::CODE_PATTERN);
    unlink($path);
});

it('refuses to delete the default store view, store group and website of a website', function (string $type, Closure $id, string $message): void {
    $admin = TransportParity::admin(Mage_Adminhtml_System_StoreController::class, 'delete' . ucfirst($type) . 'Post', ['item_id' => $id($this->website)]);

    expect($admin)->toBe(['success' => [], 'errors' => [$message]])
        ->and(storeParityStore(STORE_PARITY_WEBSITE)->getId())->not->toBeNull();
})->with([
    'the default store view' => ['store', fn(Mage_Core_Model_Website $website): int => (int) $website->getDefaultStore()->getId(), 'This store view cannot be deleted.'],
    'the default store group' => ['group', fn(Mage_Core_Model_Website $website): int => (int) $website->getDefaultGroupId(), 'This store cannot be deleted.'],
    'the default website' => ['website', fn(Mage_Core_Model_Website $website): int => (int) Mage::app()->getDefaultStoreView()->getWebsiteId(), 'This website cannot be deleted.'],
    'an unknown store view' => ['store', fn(Mage_Core_Model_Website $website): int => 9999, "Store view doesn't exist"],
]);

it('shows only the active store views in the API', function (): void {
    $store = storeParityStore(STORE_PARITY_WEBSITE);
    $store->setIsActive(false)->save();
    Mage::app()->reinitStores();
    $provider = new StoreProvider(TransportParity::adminSecurity());

    $codes = array_map(fn($dto): string => $dto->code, $provider->provide(new Get(), []));
    $item = TransportParity::api(fn() => $provider->provide(new Get(), ['id' => (int) $store->getId()]));
    $switch = TransportParity::api(fn() => new StoreProcessor(TransportParity::adminSecurity())
        ->process(null, new Post(), ['storeCode' => STORE_PARITY_WEBSITE]));
    $config = TransportParity::api(fn() => new StoreConfigProvider(TransportParity::adminSecurity())
        ->provide(new Get(), ['storeCode' => STORE_PARITY_WEBSITE]));

    expect($codes)->not->toContain(STORE_PARITY_WEBSITE)
        ->and($codes)->toContain(Mage::app()->getDefaultStoreView()->getCode())
        ->and($item)->toMatchArray(['status' => 404, 'message' => 'Store not found'])
        ->and($switch)->toMatchArray(['status' => 404, 'message' => "Store with code '" . STORE_PARITY_WEBSITE . "' not found"])
        ->and($config)->toMatchArray(['status' => 404, 'message' => "Store with code '" . STORE_PARITY_WEBSITE . "' not found"]);
});
