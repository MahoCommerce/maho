<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Api2_Post extends Mage_Api2_Model_Resource
{
    #[\Override]
    protected function _retrieve()
    {
        $post = $this->_loadPostById($this->getRequest()->getParam('id'));
        return $post->getData();
    }

    #[\Override]
    protected function _retrieveCollection()
    {
        /** @var Maho_Blog_Model_Resource_Post_Collection $collection */
        $collection = Mage::getResourceModel('blog/post_collection');
        $collection->addAttributeToSelect('*');
        
        $this->_applyCollectionModifiers($collection);
        
        $posts = [];
        foreach ($collection as $post) {
            $posts[] = $post->getData();
        }
        
        return $posts;
    }

    #[\Override]
    protected function _create(array $data)
    {
        try {
            $post = Mage::getModel('blog/post');
            $post->setData($data);
            $post->save();
            
            return $this->_getLocation($post);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }
    }

    #[\Override]
    protected function _update(array $data)
    {
        $post = $this->_loadPostById($this->getRequest()->getParam('id'));
        
        try {
            $post->addData($data);
            $post->save();
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }
    }

    #[\Override]
    protected function _delete()
    {
        $post = $this->_loadPostById($this->getRequest()->getParam('id'));
        
        try {
            $post->delete();
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }
    }

    protected function _loadPostById(int $postId): Maho_Blog_Model_Post
    {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post')->load($postId);
        
        if (!$post->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        
        return $post;
    }

    #[\Override]
    protected function _getLocation(Maho_Blog_Model_Post $post): string
    {
        return $this->_getSubModel('blog_post', $post->getId())->getResourceLocation();
    }
}