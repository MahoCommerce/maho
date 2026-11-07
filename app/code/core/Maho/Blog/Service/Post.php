<?php

/**
 * Load, save and delete blog posts for the admin pages, the storefront, the API and the importer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Service_Post
{
    /**
     * Return an unsaved post that is active on all store views.
     */
    public function newPost(): Maho_Blog_Model_Post
    {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post');
        return $post->setData('is_active', 1)->setData('stores', [Mage_Core_Model_App::ADMIN_STORE_ID]);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Maho_Blog_Model_Post
    {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post');
        if ($id > 0) {
            $post->load($id);
        }
        if (!$post->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('blog')->__('This post no longer exists.'));
        }
        return $post;
    }

    /**
     * The blog is on for the store view $storeId when the module is enabled and its output is not disabled there.
     */
    public function isEnabled(int $storeId): bool
    {
        return Mage::helper('blog')->isModuleEnabled()
            && !Mage::getStoreConfigFlag('advanced/modules_disable_output/Maho_Blog', $storeId);
    }

    /**
     * Load the post $id that a visitor of the store view $storeId can read: active, published and in that store view.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the blog is off for $storeId or the visitor cannot read the post
     */
    public function getVisibleById(int $id, int $storeId): Maho_Blog_Model_Post
    {
        $this->checkEnabled($storeId);
        $post = $this->getById($id);
        if (!$post->getIsActive() || !$this->isPublished($post, $storeId)
            || array_intersect([Mage_Core_Model_App::ADMIN_STORE_ID, $storeId], array_map(intval(...), $post->getStores())) === []
        ) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('blog')->__('This post no longer exists.'));
        }
        return $post;
    }

    /**
     * Keep the posts of $collection that a visitor of the store view $storeId can read: active and published.
     * The caller adds the store view filter.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the blog is off for $storeId
     */
    public function addVisibleFilter(Maho_Blog_Model_Resource_Post_Collection $collection, int $storeId): Maho_Blog_Model_Resource_Post_Collection
    {
        $this->checkEnabled($storeId);
        return $collection->addPublishedFilter($storeId);
    }

    /**
     * publish_date is a date of the store view, so a post published today shows from 00:00 in the time zone of the store.
     */
    public function isPublished(Maho_Blog_Model_Post $post, int $storeId): bool
    {
        $publishDate = $post->getPublishDate();
        return empty($publishDate)
            || $publishDate <= Mage::app()->getLocale()->utcToStore($storeId)->format(Mage_Core_Model_Locale::DATE_FORMAT);
    }

    /**
     * Save $post. The transport puts its input into the post first. The resource model checks the fields.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another post of the same store view has the URL key
     */
    public function save(Maho_Blog_Model_Post $post): Maho_Blog_Model_Post
    {
        $post->save();
        return $post;
    }

    public function delete(Maho_Blog_Model_Post $post): void
    {
        $post->delete();
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    protected function checkEnabled(int $storeId): void
    {
        if (!$this->isEnabled($storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('blog')->__('The blog is not enabled for this store view.'));
        }
    }
}
