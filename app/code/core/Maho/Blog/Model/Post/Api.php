<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Post_Api extends Mage_Api_Model_Resource_Abstract
{
    public function items(?array $filters = null): array
    {
        try {
            $collection = Mage::getModel('blog/post')->getCollection();
            if (!$collection instanceof Maho_Blog_Model_Resource_Post_Collection) {
                return [];
            }
            $collection->addAttributeToSelect('*');

            if (is_array($filters)) {
                try {
                    foreach ($filters as $field => $value) {
                        $collection->addFieldToFilter($field, $value);
                    }
                } catch (Mage_Core_Exception $e) {
                    $this->_fault('filters_invalid', $e->getMessage());
                }
            }

            $result = [];
            foreach ($collection as $post) {
                $result[] = $this->_getPostData($post);
            }

            return $result;
        } catch (Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
    }

    public function info(int $postId): array
    {
        try {
            $post = Mage::getModel('blog/post')->load($postId);

            if (!$post->getId()) {
                $this->_fault('post_not_exists');
            }

            return $this->_getPostData($post);
        } catch (Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
    }

    public function create(array $postData): int
    {
        try {
            $post = Mage::getModel('blog/post')
                ->setData($postData)
                ->save();

            return $post->getId();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        } catch (Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
    }

    public function update(int $postId, array $postData): true
    {
        try {
            $post = Mage::getModel('blog/post')->load($postId);

            if (!$post->getId()) {
                $this->_fault('post_not_exists');
            }

            $post->addData($postData)->save();

            return true;
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        } catch (Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
    }

    public function delete(int $postId): true
    {
        try {
            $post = Mage::getModel('blog/post')->load($postId);

            if (!$post->getId()) {
                $this->_fault('post_not_exists');
            }

            $post->delete();
            return true;
        } catch (Mage_Core_Exception $e) {
            $this->_fault('not_deleted', $e->getMessage());
        }
    }

    protected function _getPostData(Maho_Blog_Model_Post $post): array
    {
        return [
            'post_id' => $post->getId(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'url_key' => $post->getUrlKey(),
            'image' => $post->getImage(),
            'is_active' => $post->getIsActive(),
            'publish_date' => $post->getPublishDate(),
            'meta_title' => $post->getMetaTitle(),
            'meta_keywords' => $post->getMetaKeywords(),
            'meta_description' => $post->getMetaDescription(),
            'meta_robots' => $post->getMetaRobots(),
            'created_at' => $post->getCreatedAt(),
            'updated_at' => $post->getUpdatedAt(),
            'stores' => $post->getStores(),
        ];
    }
}
