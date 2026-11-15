<?php

/**
 * Submit, moderate and delete product reviews, for the storefront, the admin and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

use Mage_Review_Model_Review as Review;

class Mage_Review_Service_Review
{
    public const XML_PATH_GUEST_ALLOWED = 'catalog/review/allow_guest';
    public const XML_PATH_RATE_LIMIT = 'system/rate_limit/review_submit';

    public const RATE_LIMIT_WINDOW = 3600;

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Review
    {
        $review = Mage::getModel('review/review')->load($id);
        if (!$review->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('review')->__('Review not found'));
        }
        return $review;
    }

    public function isGuestAllowed(int $storeId): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_GUEST_ALLOWED, $storeId);
    }

    /**
     * Return the product $productId when the storefront of the store $storeId shows it.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getProduct(int $productId, int $storeId): Mage_Catalog_Model_Product
    {
        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
        if (!Mage::helper('catalog/product')->isVisibleInStore($product, $storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('review')->__('Product not found'));
        }
        return $product;
    }

    /**
     * Save the review that a customer or a guest writes on the storefront, with the status pending. The transport
     * sets the product ID, the title, the detail and the nickname. $votes gives the option ID by the rating ID.
     * $customerId is the customer who is logged in, or null for a guest.
     *
     * @param array<int|string, int|string> $votes
     * @param array<array-key, mixed> $fields the fields that the client sent, with the token of the CAPTCHA: the form POST or the API body
     * @throws Mage_Core_Exception_Forbidden when $customerId is null and the store does not accept reviews of guests
     * @throws Mage_Core_Exception_TooManyRequests when the client or the customer is over the limit
     * @throws Mage_Core_Exception_NoSuchEntity when the store does not show the product
     * @throws Mage_Core_Exception_Input with one error for each invalid field, or when the CAPTCHA refuses the token
     */
    public function submit(Review $review, array $votes, int $storeId, ?int $customerId, array $fields): Review
    {
        $helper = Mage::helper('review');
        if ($customerId === null && !$this->isGuestAllowed($storeId)) {
            throw new Mage_Core_Exception_Forbidden($helper->__('Only registered customers can write reviews.'));
        }
        Mage::helper('core')->assertCaptcha($fields);
        $this->attemptRateLimits($storeId, $customerId);
        $product = $this->getProduct((int) $review->getEntityPkValue(), $storeId);

        $review->setTitle(trim((string) $review->getTitle()))
            ->setDetail(trim((string) $review->getDetail()))
            ->setNickname(trim((string) $review->getNickname()));
        $errors = new Mage_Core_Exception_Input()->addValidationResult($review->validate(), 'review');
        // A store shows only its own ratings, so it takes a vote only for them
        $this->validateVotes($votes, $storeId, $errors);
        $errors->throwIfErrors();

        $review->setEntityId((int) $review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue((int) $product->getId())
            ->setStatusId(Review::STATUS_PENDING)
            ->setCustomerId($customerId)
            ->setStoreId($storeId)
            ->setStores([$storeId])
            ->save();
        $this->saveVotes($review, $votes, $customerId);
        $review->aggregate();
        return $review;
    }

    /**
     * Return the vote of $stars, from 1 to 5, for the first rating of the store $storeId: the option ID by the
     * rating ID. Return no vote when the store has no rating.
     *
     * @return array<int, int>
     * @throws Mage_Core_Exception_Input when $stars is out of range
     */
    public function getStarVotes(int $stars, int $storeId): array
    {
        if ($stars < 1 || $stars > 5) {
            throw new Mage_Core_Exception_Input()->addError('rating', Mage::helper('review')->__('Rating must be between 1 and 5'));
        }
        foreach ($this->getRatingOptions($storeId) as $ratingId => $optionIds) {
            return isset($optionIds[$stars - 1]) ? [$ratingId => $optionIds[$stars - 1]] : [];
        }
        return [];
    }

    /**
     * Save $review from the admin, and its votes: the option ID by the rating ID. A rating that has a vote
     * gets the new option.
     *
     * @param array<int|string, int|string> $votes
     * @throws Mage_Core_Exception_NoSuchEntity when the product of a new review does not exist
     * @throws Mage_Core_Exception_Input with one error for each invalid field, or for an option of another rating
     */
    public function save(Review $review, array $votes = []): Review
    {
        $errors = new Mage_Core_Exception_Input()->addValidationResult($review->validate(), 'review');
        $this->validateStatus($review->getStatusId(), $errors);
        $this->validateVotes($votes, null, $errors);
        $errors->throwIfErrors();
        if (!$review->getId()) {
            if (!Mage::getModel('catalog/product')->load((int) $review->getEntityPkValue())->getId()) {
                throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('review')->__('Product not found'));
            }
            $review->setEntityId((int) $review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE));
        }

        $review->save();
        $this->saveVotes($review, $votes, $review->getCustomerId());
        $review->aggregate();
        return $review;
    }

    /**
     * Set the status and the stores of $review, for each one that is not null.
     *
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception when both are null
     * @throws Mage_Core_Exception_Input when the status does not exist
     */
    public function moderate(Review $review, ?int $statusId, ?array $storeIds = null): Review
    {
        if ($statusId === null && !$storeIds) {
            throw new Mage_Core_Exception(Mage::helper('review')->__('Provide a status and/or stores to moderate'));
        }
        if ($statusId !== null) {
            $errors = new Mage_Core_Exception_Input();
            $this->validateStatus($statusId, $errors);
            $errors->throwIfErrors();
            $review->setStatusId($statusId);
        }
        if ($storeIds) {
            $review->setStores($storeIds);
        }
        $review->save();
        $review->aggregate();
        return $review;
    }

    /**
     * Delete $review. The resource model refreshes the rating summary of the product.
     */
    public function delete(Review $review): void
    {
        $review->delete();
    }

    private function validateStatus(?int $statusId, Mage_Core_Exception_Input $errors): void
    {
        if (!array_key_exists((int) $statusId, Mage::helper('review')->getReviewStatuses())) {
            $errors->addError('status_id', Mage::helper('review')->__('Please select a valid status.'));
        }
    }

    /**
     * Check that each option of $votes is an option of its rating. With $storeId, the rating must be a rating of
     * that store.
     *
     * @param array<int|string, int|string> $votes the option ID by the rating ID
     */
    private function validateVotes(array $votes, ?int $storeId, Mage_Core_Exception_Input $errors): void
    {
        if ($votes === []) {
            return;
        }
        $options = $this->getRatingOptions($storeId);
        foreach ($votes as $ratingId => $optionId) {
            if (!in_array((int) $optionId, $options[(int) $ratingId] ?? [], true)) {
                $errors->addError('ratings', Mage::helper('review')->__('Please select a valid rating.'));
                return;
            }
        }
    }

    /**
     * The limit of one account backs up the limit of the IP address, which a client can change.
     *
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function attemptRateLimits(int $storeId, ?int $customerId): void
    {
        $core = Mage::helper('core');
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_RATE_LIMIT, $storeId);
        $core->rateLimiter('review_submit', $limit, self::RATE_LIMIT_WINDOW, \Maho\Security\RateLimitScope::Ip)->attemptOrThrow();
        if ($customerId !== null) {
            $core->rateLimiterBy('review_submit', 'review_submit:customer:' . $customerId, $limit, self::RATE_LIMIT_WINDOW)
                ->attemptOrThrow();
        }
    }

    /**
     * @return array<int, list<int>> the option IDs of each product rating, by rating ID, in the order of the positions. With $storeId, only the ratings of that store
     */
    private function getRatingOptions(?int $storeId): array
    {
        $collection = Mage::getModel('rating/rating')->getResourceCollection()
            ->addEntityFilter(Review::ENTITY_PRODUCT_CODE);
        if ($storeId !== null) {
            $collection->setStoreFilter($storeId);
        }
        $ratings = $collection->load()->addOptionToItems();
        $options = [];
        foreach ($ratings as $rating) {
            foreach ((array) $rating->getOptions() as $option) {
                $options[(int) $rating->getId()][] = (int) $option->getId();
            }
        }
        return $options;
    }

    /**
     * @param array<int|string, int|string> $votes
     */
    private function saveVotes(Review $review, array $votes, ?int $customerId): void
    {
        $existing = Mage::getModel('rating/rating_option_vote')->getResourceCollection()
            ->setReviewFilter($review->getId())
            ->load();
        foreach ($votes as $ratingId => $optionId) {
            $vote = $existing->getItemByColumnValue('rating_id', $ratingId);
            $rating = Mage::getModel('rating/rating')->setReviewId((int) $review->getId());
            if ($vote) {
                $rating->setData('vote_id', (int) $vote->getId())->updateOptionVote((int) $optionId);
            } else {
                $rating->setRatingId((int) $ratingId)->addOptionVote((int) $optionId, $review->getEntityPkValue(), $customerId);
            }
        }
    }
}
