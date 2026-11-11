<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Review\Api\Review;
use Mage\Review\Api\ReviewProcessor;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CaptchaStub;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The review form of the storefront, the API and the admin review pages write product reviews through
 * Mage_Review_Service_Review (#1348). Each test gives the same input to the transports and compares the outcome.
 */

function reviewParityCustomerSecurity(int $customerId): Security
{
    $storage = new TokenStorage();
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private TokenStorage $storage) {}

        #[\Override]
        public function get(string $id): mixed
        {
            return $this->storage;
        }

        #[\Override]
        public function has(string $id): bool
        {
            return $id === 'security.token_storage';
        }
    });
}

/**
 * Post the review form of the product page, as the browser does, with the token of the CAPTCHA widget in $captchaToken.
 *
 * @param array<int, int> $ratings the option ID by the rating ID
 * @return array{accepted: bool, messages: list<string>}
 */
function reviewViaForm(int $productId, string $title, array $ratings = [], ?string $captchaToken = null): array
{
    Mage::unregister('current_product');
    Mage::unregister('product');
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/review/product/post', 'POST', array_filter([
        'id' => $productId,
        'title' => $title,
        'detail' => 'Parity detail',
        'nickname' => 'Parity',
        'ratings' => $ratings,
        'maho_captcha' => $captchaToken,
    ], fn($value) => $value !== null)));
    $request->setRouteName('review')->setControllerName('product')->setActionName('post')->setDispatched(true);
    new Mage_Review_ProductController($request, new Mage_Core_Controller_Response_Http())->postAction();

    $success = [];
    $errors = [];
    foreach (Mage::getSingleton('core/session')->getMessages(true)->getItems() as $message) {
        if ($message->getType() === Mage_Core_Model_Message::SUCCESS) {
            $success[] = $message->getText();
        } else {
            $errors[] = $message->getText();
        }
    }
    return ['accepted' => $success !== [] && $errors === [], 'messages' => $errors !== [] ? $errors : $success];
}

/**
 * Call POST /api/rest/v2/products/{productId}/reviews with the token of $customerId.
 * With $graphQl, call the submitReview mutation, with the input in the arguments.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function reviewViaApi(int $customerId, int $productId, string $title, ?int $rating = 4, ?string $captchaToken = null, bool $graphQl = false): array
{
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/products/{$productId}/reviews");
    $processor = new ReviewProcessor(reviewParityCustomerSecurity($customerId));
    if ($graphQl) {
        $input = ['productId' => $productId, 'title' => $title, 'detail' => 'Parity detail', 'nickname' => 'Parity'];
        $input += array_filter(['rating' => $rating, 'captchaToken' => $captchaToken], fn($value) => $value !== null);
        return AdminApiParity::api($request, fn() => $processor
            ->process(new Review(), new Mutation(name: 'submit'), [], ['request' => $request, 'args' => ['input' => $input]]));
    }

    $data = new Review();
    $data->title = $title;
    $data->detail = 'Parity detail';
    $data->nickname = 'Parity';
    $data->rating = $rating;
    $data->captchaToken = $captchaToken;

    return AdminApiParity::api($request, fn() => $processor
        ->process($data, new Post(), ['productId' => $productId], ['request' => $request]));
}

/**
 * @return list<Mage_Review_Model_Review>
 */
function reviewParityReviews(string $title): array
{
    $collection = Mage::getModel('review/review')->getCollection();
    $collection->getSelect()->where('detail.title = ?', $title);
    return array_values($collection->getItems());
}

/**
 * @return array<int, int> the option ID by the rating ID of each vote of $reviewId
 */
function reviewParityVotes(int $reviewId): array
{
    $votes = [];
    foreach (Mage::getModel('rating/rating_option_vote')->getResourceCollection()->setReviewFilter($reviewId) as $vote) {
        $votes[(int) $vote->getRatingId()] = (int) $vote->getOptionId();
    }
    return $votes;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    // A message that an earlier test left in the session is not an outcome of this test
    Mage::getSingleton('core/session')->getMessages(true);
    Mage::getSingleton('customer/session')->getMessages(true);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    // The customer of the test logs in without the confirmation email
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
    $this->storeId = (int) Mage::app()->getStore()->getId();
    Mage::app()->getStore()->setConfig(Mage_Review_Service_Review::XML_PATH_GUEST_ALLOWED, '1');
    Mage::app()->getStore()->setConfig(Mage_Review_Service_Review::XML_PATH_RATE_LIMIT, '10');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    $this->product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->addWebsiteFilter((int) Mage::app()->getStore()->getWebsiteId())
        ->setPageSize(1)
        ->getFirstItem();
    if (!$this->product->getId()) {
        $this->markTestSkipped('No visible product available for testing');
    }

    $this->rating = Mage::getModel('rating/rating')
        ->setRatingCode('review-parity-' . uniqid())
        ->setEntityId((int) Mage::getModel('rating/rating')->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
        ->setStores([$this->storeId]);
    $this->rating->save();
    for ($value = 1; $value <= 5; $value++) {
        Mage::getModel('rating/rating_option')
            ->setRatingId((int) $this->rating->getId())
            ->setCode((string) $value)
            ->setValue($value)
            ->setPosition($value)
            ->save();
    }

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setEmail('review-parity-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    $this->title = 'Review parity ' . uniqid();
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    Mage::register('isSecureArea', true, true);
    foreach (reviewParityReviews($this->title) as $review) {
        $review->delete();
    }
    $this->rating?->delete();
    $this->customer?->delete();
    Mage::unregister('isSecureArea');
});

it('saves a pending review of the customer in the current store on both transports', function () {
    $votes = Mage::getService('review/review')->getStarVotes(4, $this->storeId);

    expect(reviewViaForm((int) $this->product->getId(), $this->title, $votes))
        ->toBe(['accepted' => true, 'messages' => ['Your review has been accepted for moderation.']])
        ->and(reviewViaApi((int) $this->customer->getId(), (int) $this->product->getId(), $this->title)['status'])->toBe(200);

    $reviews = reviewParityReviews($this->title);
    expect($reviews)->toHaveCount(2);
    foreach ($reviews as $review) {
        $review = Mage::getService('review/review')->getById((int) $review->getId());
        expect($review->getStatusId())->toBe(Mage_Review_Model_Review::STATUS_PENDING)
            ->and($review->getCustomerId())->toBe((int) $this->customer->getId())
            ->and($review->getStoreId())->toBe($this->storeId)
            ->and(reviewParityVotes((int) $review->getId()))->toBe($votes);
    }
});

it('saves a review with no vote when the rating is missing, on both transports', function () {
    expect(reviewViaForm((int) $this->product->getId(), $this->title)['accepted'])->toBeTrue()
        ->and(reviewViaApi((int) $this->customer->getId(), (int) $this->product->getId(), $this->title, null)['status'])->toBe(200)
        ->and(reviewViaApi((int) $this->customer->getId(), (int) $this->product->getId(), $this->title, null, graphQl: true)['status'])->toBe(200);

    $reviews = reviewParityReviews($this->title);
    expect($reviews)->toHaveCount(3);
    foreach ($reviews as $review) {
        expect(reviewParityVotes((int) $review->getId()))->toBe([]);
    }
});

it('refuses stars out of 1 to 5 on the API with an error on rating', function (int $rating, bool $graphQl) {
    $api = reviewViaApi((int) $this->customer->getId(), (int) $this->product->getId(), $this->title, $rating, graphQl: $graphQl);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['rating'])
        ->and($api['errors'])->toBe(['Rating must be between 1 and 5'])
        ->and(reviewParityReviews($this->title))->toBe([]);
})->with([[0, false], [6, false], [0, true], [6, true]]);

it('rejects an empty title with the same message on both transports', function () {
    $api = reviewViaApi((int) $this->customer->getId(), (int) $this->product->getId(), '   ');

    expect(reviewViaForm((int) $this->product->getId(), ''))->toBe(['accepted' => false, 'messages' => ["Review summary can't be empty"]])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['title'])
        ->and($api['errors'])->toBe(["Review summary can't be empty"]);
});

it('refuses a nickname that the column cannot hold on both transports', function () {
    $review = Mage::getModel('review/review')
        ->setEntityPkValue((int) $this->product->getId())
        ->setTitle($this->title)
        ->setDetail('Parity detail')
        ->setNickname(str_repeat('n', Mage_Review_Model_Review::MAX_NICKNAME_LENGTH + 1));

    expect(fn() => Mage::getService('review/review')->submit($review, [], $this->storeId, null, []))
        ->toThrow(Mage_Core_Exception_Input::class, 'Nickname cannot exceed 128 characters');
});

it('refuses the same nickname when a caller saves the review model with no service', function () {
    $review = Mage::getModel('review/review');
    $review->setEntityId((int) $review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
        ->setEntityPkValue((int) $this->product->getId())
        ->setStatusId(Mage_Review_Model_Review::STATUS_PENDING)
        ->setStoreId($this->storeId)
        ->setStores([$this->storeId])
        ->setTitle($this->title)
        ->setDetail('Parity detail')
        ->setNickname(str_repeat('n', Mage_Review_Model_Review::MAX_NICKNAME_LENGTH + 1));

    expect(fn() => $review->save())->toThrow(Mage_Core_Exception_Input::class, 'Nickname cannot exceed 128 characters')
        ->and(reviewParityReviews($this->title))->toBe([]);

    $review->setNickname('Parity')->save();
    $stored = Mage::getService('review/review')->getById((int) $review->getId());
    expect($stored->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)->save()->getStatusId())
        ->toBe(Mage_Review_Model_Review::STATUS_APPROVED);
});

it('refuses a product that the store does not show on both transports', function () {
    $hidden = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
        ->setPageSize(1)
        ->getFirstItem();
    if (!$hidden->getId()) {
        $this->markTestSkipped('No product that is not visible individually');
    }

    $api = reviewViaApi((int) $this->customer->getId(), (int) $hidden->getId(), $this->title);
    $form = reviewViaForm((int) $hidden->getId(), $this->title);

    expect($api['status'])->toBe(404)
        ->and($form['accepted'])->toBeFalse()
        ->and(reviewParityReviews($this->title))->toBe([]);
});

it('refuses a vote for a rating that the store does not show', function () {
    $this->rating->setStores([])->save();

    expect(reviewViaForm((int) $this->product->getId(), $this->title, [(int) $this->rating->getId() => 1]))
        ->toBe(['accepted' => false, 'messages' => ['Please select a valid rating.']])
        ->and(reviewParityReviews($this->title))->toBe([]);
});

it('shares the limit of one customer between the form and the API', function () {
    Mage::app()->getStore()->setConfig(Mage_Review_Service_Review::XML_PATH_RATE_LIMIT, '1');

    expect(reviewViaForm((int) $this->product->getId(), $this->title)['accepted'])->toBeTrue();

    $api = reviewViaApi((int) $this->customer->getId(), (int) $this->product->getId(), $this->title);
    expect($api['status'])->toBe(429)
        ->and($api['errors'])->toBe(['Too many requests. Please try again later.']);
});

it('refuses a guest when the store accepts reviews of customers only', function () {
    Mage::app()->getStore()->setConfig(Mage_Review_Service_Review::XML_PATH_GUEST_ALLOWED, '0');
    $review = Mage::getModel('review/review')->setEntityPkValue((int) $this->product->getId())->setTitle($this->title);

    expect(fn() => Mage::getService('review/review')->submit($review, [], $this->storeId, null, []))
        ->toThrow(Mage_Core_Exception_Forbidden::class);
});

describe('the CAPTCHA', function () {
    afterEach(fn() => CaptchaStub::uninstall());

    it('refuses on the form and on the API a review with a token that the CAPTCHA module refuses', function (?string $token) {
        $captcha = CaptchaStub::install();

        $api = reviewViaApi((int) $this->customer->getId(), (int) $this->product->getId(), $this->title, captchaToken: $token);
        expect(reviewViaForm((int) $this->product->getId(), $this->title, captchaToken: $token))
            ->toBe(['accepted' => false, 'messages' => ['Incorrect CAPTCHA.']])
            ->and($api['status'])->toBe(422)
            ->and($api['fields'])->toBe(['captchaToken'])
            ->and($api['errors'])->toBe(['Incorrect CAPTCHA.'])
            ->and(reviewParityReviews($this->title))->toBe([])
            ->and($captcha->checks)->toHaveCount(2);
    })->with(['a wrong token' => 'wrong', 'no token' => null]);

    it('saves the review of each transport with a token that the CAPTCHA module accepts', function (string $transport) {
        $captcha = CaptchaStub::install();
        $customerId = (int) $this->customer->getId();
        $productId = (int) $this->product->getId();

        $accepted = match ($transport) {
            'form' => reviewViaForm($productId, $this->title, captchaToken: CaptchaStub::SOLVED)['accepted'],
            'REST' => reviewViaApi($customerId, $productId, $this->title, captchaToken: CaptchaStub::SOLVED)['status'] === 200,
            'GraphQL' => reviewViaApi($customerId, $productId, $this->title, captchaToken: CaptchaStub::SOLVED, graphQl: true)['status'] === 200,
        };
        expect($accepted)->toBeTrue()
            ->and(reviewParityReviews($this->title))->toHaveCount(1)
            ->and($captcha->checks)->toHaveCount(1);
    })->with(['form', 'REST', 'GraphQL']);
});

describe('moderation', function () {
    it('approves a review the same way from the API and from the admin grid', function () {
        $service = Mage::getService('review/review');
        $reviews = [];
        foreach (['api', 'admin'] as $transport) {
            $review = Mage::getModel('review/review')
                ->setEntityPkValue((int) $this->product->getId())
                ->setTitle($this->title)
                ->setDetail('Parity detail')
                ->setNickname('Parity');
            $reviews[$transport] = (int) $service->submit($review, [], $this->storeId, (int) $this->customer->getId(), [])->getId();
        }

        $data = new Review();
        $data->status = 'approved';
        $request = AdminApiParity::jsonRequest('PUT', "/api/rest/v2/reviews/{$reviews['api']}", ['status' => 'approved']);
        $api = AdminApiParity::api($request, fn() => new ReviewProcessor(AdminApiParity::adminSecurity())
            ->process($data, new Put(), ['id' => $reviews['api']], ['request' => $request]));
        $admin = AdminApiParity::admin(Mage_Adminhtml_Catalog_Product_ReviewController::class, 'massUpdateStatus', [
            'reviews' => [$reviews['admin']],
            'status' => Mage_Review_Model_Review::STATUS_APPROVED,
        ]);

        expect($api['status'])->toBe(200)
            ->and($api['result']->status)->toBe('approved')
            ->and($admin['errors'])->toBe([])
            ->and($service->getById($reviews['admin'])->getStatusId())->toBe(Mage_Review_Model_Review::STATUS_APPROVED);
    });

    it('refuses a review that does not exist and creates nothing', function () {
        $table = Mage::getSingleton('core/resource')->getTableName('review/review');
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $count = (int) $read->fetchOne($read->select()->from($table, [new Maho\Db\Expr('COUNT(*)')]));

        $admin = AdminApiParity::admin(Mage_Adminhtml_Catalog_Product_ReviewController::class, 'massUpdateStatus', [
            'reviews' => [999999999],
            'status' => Mage_Review_Model_Review::STATUS_APPROVED,
        ]);

        expect($admin['errors'])->toBe(['Review not found'])
            ->and((int) $read->fetchOne($read->select()->from($table, [new Maho\Db\Expr('COUNT(*)')])))->toBe($count);
    });
});
