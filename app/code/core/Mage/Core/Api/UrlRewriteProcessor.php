<?php

/**
 * Give a URL rewrite write to the URL rewrite service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;

final class UrlRewriteProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?UrlRewrite
    {
        $user = $this->requireUser();
        $service = \Mage::getService('core/url_rewrite');
        $rewrite = isset($uriVariables['id'])
            ? $service->getById((int) $uriVariables['id'], $user->getAllowedStoreIds())
            : $service->newRewrite();
        $oldData = $rewrite->getId() ? $rewrite->getData() : null;

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($rewrite);
            $this->logApiActivity('url_rewrite', 'delete', $oldData, null);
            return null;
        }

        /** @var UrlRewrite $data */
        $body = $this->parseRequestBody($context['request'] ?? null);
        $data->applyToModel($rewrite, $oldData === null ? null : array_map(strval(...), array_keys($body)));
        if ($rewrite->getStoreId() !== null) {
            $this->validateEntityStoreAccess([(int) $rewrite->getStoreId()], $user, 'URL rewrite');
        }

        $service->save($rewrite);
        $this->logApiActivity('url_rewrite', $oldData === null ? 'create' : 'update', $oldData, $rewrite);

        return UrlRewrite::fromModel($rewrite);
    }
}
