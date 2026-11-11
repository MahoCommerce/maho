<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Wishlist\Api\WishlistProcessor;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The share form of the wishlist page and the API send a wishlist by email through
 * Mage_Wishlist_Service_Wishlist::share() (#1348). The API had no operation, and the controller held the rules.
 */

function wishlistShareSecurity(int $customerId): Security
{
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage = new TokenStorage();
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private TokenStorage $storage) {}

        #[\Override]
        public function get(string $id): mixed
        {
            return $id === 'security.authorization_checker'
                ? new AuthorizationChecker($this->storage, new AccessDecisionManager([new RoleVoter()]))
                : $this->storage;
        }

        #[\Override]
        public function has(string $id): bool
        {
            return in_array($id, ['security.token_storage', 'security.authorization_checker'], true);
        }
    });
}

/**
 * Post the share form of the wishlist page, as the browser does.
 *
 * @return array{accepted: bool, messages: list<string>}
 */
function wishlistShareViaPage(string $emails, string $message = ''): array
{
    Mage::unregister('wishlist');
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/wishlist/index/send', 'POST', ['emails' => $emails, 'message' => $message]));
    $request->setRouteName('wishlist')->setControllerName('index')->setActionName('send')->setDispatched(true);
    new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http())->sendAction();

    $success = [];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $item) {
        $success[] = $item->getText();
    }
    $errors = [];
    foreach (Mage::getSingleton('wishlist/session')->getMessages(true)->getItems() as $item) {
        $errors[] = $item->getText();
    }
    return ['accepted' => $success !== [] && $errors === [], 'messages' => $errors !== [] ? $errors : $success];
}

/**
 * Call POST /api/rest/v2/customers/me/wishlist/share, or the share mutation with $graphQl.
 *
 * @param list<string> $emails
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function wishlistShareViaApi(int $customerId, array $emails, string $message = '', bool $graphQl = false): array
{
    $body = ['emails' => $emails, 'message' => $message];
    $request = AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers/me/wishlist/share', $body);
    $processor = new WishlistProcessor(wishlistShareSecurity($customerId));

    return AdminApiParity::api($request, fn() => $graphQl
        ? $processor->process(null, new Post(name: 'share'), [], ['request' => $request, 'args' => ['input' => $body]])
        : $processor->process(new Mage\Wishlist\Api\WishlistItem(), new Post(name: 'share_wishlist'), [], ['request' => $request]));
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
    $store->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '1');
    $store->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_SHARE_RATE_LIMIT, '10');
    // The test sends no email
    $store->setConfig('system/smtp/enabled', '');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    Mage::getSingleton('customer/session')->getMessages(true);
    Mage::getSingleton('wishlist/session')->getMessages(true);

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) $store->getWebsiteId())
        ->setEmail('wishlist-share-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    Mage::unregister('wishlist');
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
});

it('refuses the same addresses with the same message on both transports', function (array $emails, string $message) {
    $customerId = (int) $this->customer->getId();

    $api = wishlistShareViaApi($customerId, $emails);
    expect(wishlistShareViaPage(implode(',', $emails)))->toBe(['accepted' => false, 'messages' => [$message]])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['emails'])
        ->and($api['errors'])->toBe([$message])
        ->and(Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId)->getShared())->not->toBeTrue();
})->with([
    'no address' => [[], "Email address can't be empty."],
    'more than 5 addresses' => [['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com', 'f@example.com'], 'Please enter no more than 5 email addresses.'],
    'an address that is not valid' => [['a@example.com', 'not-an-email'], 'Please input a valid email address.'],
]);

it('shares the wishlist on each transport', function (string $transport) {
    $customerId = (int) $this->customer->getId();

    if ($transport === 'page') {
        expect(wishlistShareViaPage('friend@example.com, other@example.com', "Look at this\n<b>list</b>"))
            ->toBe(['accepted' => true, 'messages' => ['Your Wishlist has been shared.']]);
    } else {
        $api = wishlistShareViaApi($customerId, ['friend@example.com', 'other@example.com'], 'Look at this', $transport === 'GraphQL');
        expect($api['errors'])->toBe([])
            ->and($api['result']->id)->toBe((int) Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId)->getId());
    }

    expect(Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId)->getShared())->toBeTrue();
})->with(['page', 'REST', 'GraphQL']);

it('counts the shares of one customer on the page and on the API together', function () {
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_SHARE_RATE_LIMIT, '1');
    $customerId = (int) $this->customer->getId();

    expect(wishlistShareViaPage('friend@example.com')['accepted'])->toBeTrue();

    $api = wishlistShareViaApi($customerId, ['friend@example.com']);
    expect($api['status'])->toBe(429)
        ->and($api['errors'])->toBe(['Too many requests. Please try again later.'])
        ->and(wishlistShareViaPage('friend@example.com'))
        ->toBe(['accepted' => false, 'messages' => ['Too many requests. Please try again later.']]);
});

it('lists the items of the shared wishlist in the email when no customer session has it, as in an API request', function () {
    $sku = 'wishlist-share-' . uniqid();
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)->setName('Shared wishlist product')->setUrlKey($sku)->setPrice(10.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $product->save();

    try {
        $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer((int) $this->customer->getId(), true);
        $wishlist->addNewItem(Mage::getModel('catalog/product')->setStoreId((int) Mage::app()->getStore()->getId())->load($product->getId()));
        $wishlist->save();
        Mage::getSingleton('customer/session')->logout();
        Mage::unregister('wishlist');
        $wishlist = Mage::getModel('wishlist/wishlist')->load($wishlist->getId());

        $html = Mage::app()->getLayout()->createBlock('wishlist/share_email_items')->setWishlist($wishlist)->toHtml();

        expect($html)->toContain('Shared wishlist product');
    } finally {
        Mage::register('isSecureArea', true, true);
        $product->delete();
        Mage::unregister('isSecureArea');
    }
});
