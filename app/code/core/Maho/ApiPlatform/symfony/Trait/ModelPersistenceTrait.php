<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Trait;

use Mage;
use Mage_Core_Model_Abstract;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Wraps model save, delete, and load operations with consistent HTTP error handling.
 *
 * Provides safeSave() and safeDelete() which catch exceptions and rethrow as
 * UnprocessableEntityHttpException, secureAreaDelete() for models protected by
 * _protectFromNonAdmin(), and loadOrFail() for the common load-or-404 pattern.
 */
trait ModelPersistenceTrait
{
    /**
     * @throws UnprocessableEntityHttpException
     */
    protected function safeSave(Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        try {
            $model->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException("Failed to {$entityLabel}: " . $e->getMessage());
        }
    }

    /**
     * @throws UnprocessableEntityHttpException
     */
    protected function safeDelete(Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        try {
            $model->delete();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException("Failed to {$entityLabel}: " . $e->getMessage());
        }
    }

    /**
     * Delete with isSecureArea registry guard to bypass _protectFromNonAdmin() check.
     *
     * @throws UnprocessableEntityHttpException
     */
    protected function secureAreaDelete(Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        $wasSecure = Mage::registry('isSecureArea');
        if (!$wasSecure) {
            Mage::register('isSecureArea', true);
        }

        try {
            $model->delete();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException("Failed to {$entityLabel}: " . $e->getMessage());
        } finally {
            if (!$wasSecure) {
                Mage::unregister('isSecureArea');
            }
        }
    }

    /**
     * Load a model by ID or throw NotFoundHttpException.
     */
    protected function loadOrFail(string $modelAlias, int|string $id, string $notFoundMessage): Mage_Core_Model_Abstract
    {
        /** @var Mage_Core_Model_Abstract $model */
        $model = Mage::getModel($modelAlias);
        $model->load($id);

        if (!$model->getId()) {
            throw new NotFoundHttpException($notFoundMessage);
        }

        return $model;
    }
}
