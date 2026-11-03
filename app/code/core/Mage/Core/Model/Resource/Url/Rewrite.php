<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Url_Rewrite extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Tag table
     *
     * @var string
     */
    protected $_tagTable;

    #[\Override]
    protected function _construct()
    {
        $this->_init('core/url_rewrite', 'url_rewrite_id');
        $this->_tagTable = $this->getTable('url_rewrite_tag');
    }

    /**
     * Check the store view, the request path, the redirect type, the product and the category of $object.
     * A rewrite without a product or a category needs an ID path and a target path. The request path is stored
     * in lower case. _checkUnique() then refuses a request path or an ID path that another rewrite of the store
     * view has.
     *
     * @param Mage_Core_Model_Url_Rewrite $object
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $helper = Mage::helper('core');
        $errors = new Mage_Core_Exception_Input();

        $storeId = $object->getStoreId();
        if ($storeId === null || $storeId === '') {
            $errors->addError('store_id', $helper->__('The store view is required.'));
        } elseif (!array_key_exists((int) $storeId, Mage::app()->getStores())) {
            $errors->addError('store_id', $helper->__('The store view %s does not exist.', $storeId));
        }

        $requestPath = strtolower(trim((string) $object->getRequestPath()));
        $object->setRequestPath($requestPath);
        if ($requestPath === '') {
            $errors->addError('request_path', $helper->__('The request path is required.'));
        } else {
            try {
                Mage::helper('core/url_rewrite')->validateRequestPath($requestPath);
            } catch (Mage_Core_Exception $e) {
                $errors->addError('request_path', $e->getMessage());
            }
        }

        if ($object->getOptions() !== null && !in_array($object->getOptions(), Mage_Core_Model_Url_Rewrite::OPTIONS, true)) {
            $errors->addError('options', $helper->__('The redirect must be "" (no redirect), "R" (302) or "RP" (301).'));
        }

        $productId = $object->getProductId();
        if ($productId !== null && !Mage::getResourceModel('catalog/product_collection')->addIdFilter($productId)->getSize()) {
            $errors->addError('product_id', $helper->__('The product %s does not exist.', $productId));
        }
        $categoryId = $object->getCategoryId();
        if ($categoryId !== null && !Mage::getResourceModel('catalog/category_collection')->addIdFilter($categoryId)->getSize()) {
            $errors->addError('category_id', $helper->__('The category %s does not exist.', $categoryId));
        }
        if ($productId === null && $categoryId === null) {
            if (trim((string) $object->getIdPath()) === '') {
                $errors->addError('id_path', $helper->__('The ID path is required for a rewrite without a product or a category.'));
            }
            if (trim((string) $object->getTargetPath()) === '') {
                $errors->addError('target_path', $helper->__('The target path is required for a rewrite without a product or a category.'));
            }
        }

        $errors->throwIfErrors();
        return parent::_beforeSave($object);
    }

    /**
     * Initialize array fields
     *
     * @return $this
     */
    #[\Override]
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = [
            [
                'field' => ['id_path','store_id','is_system'],
                'title' => Mage::helper('core')->__('ID Path for Specified Store'),
            ],
            [
                'field' => ['request_path','store_id'],
                'title' => Mage::helper('core')->__('Request Path for Specified Store'),
            ],
        ];
        return $this;
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Core_Model_Url_Rewrite $object
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if (!is_null($object->getStoreId())) {
            $select->where('store_id = ?', $object->getStoreId());
            $select->order('is_system ' . Maho\Db\Select::SQL_DESC);
            $select->limit(1);
        }

        return $select;
    }

    /**
     * Retrieve request_path using id_path and current store's id.
     *
     * @param string $idPath
     * @param int|Mage_Core_Model_Store $store
     * @return string|false
     */
    public function getRequestPathByIdPath($idPath, $store)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $storeId = (int) $store->getId();
        } else {
            $storeId = (int) $store;
        }

        $select = $this->_getReadAdapter()->select();
        $select->from(['main_table' => $this->getMainTable()], 'request_path')
            ->where('main_table.store_id = :store_id')
            ->where('main_table.id_path = :id_path')
            ->limit(1);

        $bind = [
            'store_id' => $storeId,
            'id_path'  => $idPath,
        ];

        return $this->_getReadAdapter()->fetchOne($select, $bind);
    }

    /**
     * Load rewrite information for request
     * If $path is array - we must load all possible records and choose one matching earlier record in array
     *
     * @param   array|string $path
     * @return  Mage_Core_Model_Resource_Url_Rewrite
     */
    public function loadByRequestPath(Mage_Core_Model_Url_Rewrite $object, $path)
    {
        if (!is_array($path)) {
            $path = [strtolower($path)];
        }

        $pathBind = [];
        foreach ($path as $key => $url) {
            $pathBind['path' . $key] = strtolower($url);
        }
        // Form select
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select()
            ->from($this->getMainTable())
            ->where('request_path IN (:' . implode(', :', array_flip($pathBind)) . ')')
            ->where('store_id IN(?)', [Mage_Core_Model_App::ADMIN_STORE_ID, (int) $object->getStoreId()]);

        $items = $adapter->fetchAll($select, $pathBind);

        // Go through all found records and choose one with lowest penalty - earlier path in array, concrete store
        $mapPenalty = array_change_key_case(array_flip(array_values($path))); // we got mapping array(path => index), lower index - better
        $currentPenalty = null;
        $foundItem = null;
        foreach ($items as $item) {
            if (!array_key_exists($item['request_path'], $mapPenalty)) {
                continue;
            }
            $penalty = $mapPenalty[$item['request_path']] << 1 + ($item['store_id'] ? 0 : 1);
            if (!$foundItem || $currentPenalty > $penalty) {
                $foundItem = $item;
                $currentPenalty = $penalty;
                if (!$currentPenalty) {
                    break; // Found best matching item with zero penalty, no reason to continue
                }
            }
        }

        // Set data and finish loading
        if ($foundItem) {
            $object->setData($foundItem);
        }

        // Finish
        $this->unserializeFields($object);
        $this->_afterLoad($object);

        return $this;
    }
}
