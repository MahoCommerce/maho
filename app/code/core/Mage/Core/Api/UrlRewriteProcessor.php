<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;

final class UrlRewriteProcessor extends CrudProcessor
{
    private const SYSTEM_MESSAGE = 'This is a system URL rewrite. The catalog URL indexer generates and regenerates it, so it cannot be changed through the API. Create a custom rewrite instead.';

    #[\Override]
    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'UrlRewrite not found');
        if ($model->getData('is_system')) {
            throw new ValidationException(self::SYSTEM_MESSAGE, 'isSystem', 'ReadOnly');
        }

        return parent::processDelete($id, $user);
    }

    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var UrlRewrite $data */
        if (!$isNew && $model->getData('is_system')) {
            throw new ValidationException(self::SYSTEM_MESSAGE, 'isSystem', 'ReadOnly');
        }

        if ($isNew && $data->storeId === null) {
            throw ValidationException::requiredField('storeId');
        }
        if ($data->storeId !== null && !array_key_exists($data->storeId, \Mage::app()->getStores())) {
            throw ValidationException::invalidValue('storeId', "store view {$data->storeId} does not exist");
        }

        if ($isNew && ($data->requestPath === null || trim($data->requestPath) === '')) {
            throw ValidationException::requiredField('requestPath');
        }
        if ($data->requestPath !== null) {
            try {
                \Mage::helper('core/url_rewrite')->validateRequestPath($data->requestPath);
            } catch (\Mage_Core_Exception $e) {
                throw ValidationException::invalidValue('requestPath', $e->getMessage(), $e);
            }
        }

        if ($data->options !== null && !in_array($data->options, UrlRewrite::OPTIONS, true)) {
            throw ValidationException::invalidValue('options', 'must be "" (rewrite), "R" (302 redirect) or "RP" (301 redirect)');
        }

        $productId = $data->productId ?? ($isNew ? null : $model->getData('product_id'));
        $categoryId = $data->categoryId ?? ($isNew ? null : $model->getData('category_id'));
        if ($productId !== null && !\Mage::getModel('catalog/product')->load((int) $productId)->getId()) {
            throw ValidationException::invalidValue('productId', "product {$productId} does not exist");
        }
        if ($categoryId !== null && !\Mage::getModel('catalog/category')->load((int) $categoryId)->getId()) {
            throw ValidationException::invalidValue('categoryId', "category {$categoryId} does not exist");
        }

        if ($isNew && $productId === null && $categoryId === null) {
            if ($data->idPath === null || trim($data->idPath) === '') {
                throw new ValidationException('idPath is required when neither productId nor categoryId is given', 'idPath', 'NotBlank');
            }
            if ($data->targetPath === null || trim($data->targetPath) === '') {
                throw new ValidationException('targetPath is required when neither productId nor categoryId is given', 'targetPath', 'NotBlank');
            }
        }

        $requestPath = $data->requestPath ?? $model->getData('request_path');
        $storeId = $data->storeId ?? $model->getData('store_id');
        if ($requestPath !== null && $storeId !== null
            && $this->requestPathInUse(strtolower((string) $requestPath), (int) $storeId, $isNew ? null : (int) $model->getId())
        ) {
            throw new ValidationException(
                "A URL rewrite for request path '{$requestPath}' already exists in store view {$storeId}",
                'requestPath',
                'Unique',
            );
        }
    }

    /**
     * Mirrors Mage_Adminhtml_UrlrewriteController::saveAction(): a rewrite that
     * points at a product or category gets its id path and target path generated.
     */
    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        /** @var UrlRewrite $data */
        if (!$model->getId()) {
            $model->setData('is_system', 0);
        }
        if ($data->requestPath !== null) {
            $model->setData('request_path', strtolower(trim($data->requestPath)));
        }

        $productId = $model->getData('product_id');
        $categoryId = $model->getData('category_id');
        if ($productId === null && $categoryId === null) {
            return;
        }

        $product = $productId !== null ? \Mage::getModel('catalog/product')->load((int) $productId) : null;
        $category = $categoryId !== null ? \Mage::getModel('catalog/category')->load((int) $categoryId) : null;

        $catalogUrl = \Mage::getSingleton('catalog/url');
        $idPath = $catalogUrl->generatePath('id', $product, $category);
        $model->setData('id_path', $idPath);

        if (in_array($model->getData('options'), ['R', 'RP'], true)) {
            $rewrite = \Mage::getResourceModel('catalog/url')->getRewriteByIdPath($idPath, (int) $model->getData('store_id'));
            if (!$rewrite) {
                throw new ValidationException(
                    'The product or category has no URL in the chosen store view, so there is no page to redirect to. Check storeId, productId and categoryId.',
                    'productId',
                    'Invalid',
                );
            }
            if ($rewrite->getId() && (int) $rewrite->getId() !== (int) $model->getId()) {
                $model->setData('target_path', $rewrite->getRequestPath());
                return;
            }
        }

        $model->setData('target_path', $catalogUrl->generatePath('target', $product, $category));
    }

    private function requestPathInUse(string $requestPath, int $storeId, ?int $excludeId): bool
    {
        $collection = \Mage::getModel('core/url_rewrite')->getCollection()
            ->addFieldToFilter('request_path', $requestPath)
            ->addFieldToFilter('store_id', $storeId);
        if ($excludeId !== null) {
            $collection->addFieldToFilter('url_rewrite_id', ['neq' => $excludeId]);
        }

        return $collection->getSize() > 0;
    }
}
