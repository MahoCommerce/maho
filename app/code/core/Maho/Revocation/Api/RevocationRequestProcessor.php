<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

namespace Maho\Revocation\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Processor;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Give the declaration of the authenticated customer, or the processing decision of an admin,
 * to Maho_Revocation_Service_Request.
 */
final class RevocationRequestProcessor extends Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RevocationRequest
    {
        StoreContext::ensureStore();
        $service = \Mage::getService('revocation/request');
        $dto = $data instanceof RevocationRequest ? $data : null;

        if (isset($uriVariables['id'])) {
            $model = $service->getById((int) $uriVariables['id']);
            if ($dto?->adminNote !== null) {
                $model->setAdminNote($dto->adminNote);
            }
            return RevocationRequest::fromModel($dto?->processedStatus !== null && $dto->processedStatus !== ''
                ? $service->decide($model, $dto->processedStatus)
                : $service->save($model));
        }

        $args = $context['args']['input'] ?? [];
        $ip = (string) \Mage::helper('core/http')->getRemoteAddr();
        $model = $service->submitForCustomer($this->requireCustomerId(), new \Maho\DataObject([
            'order_id' => $args['orderId'] ?? $dto?->orderId,
            'order_reference' => $args['orderReference'] ?? $dto?->orderReference,
            'reason' => $args['reason'] ?? $dto?->reason,
            'ip' => $ip ?: null,
            'user_agent' => ($context['request'] ?? null)?->headers->get('User-Agent'),
            'locale' => \Mage::app()->getLocale()->getLocaleCode(),
            'store_id' => StoreContext::getStoreId(),
            'received_at_microtime' => microtime(true),
            'form_fields' => $args ?: $this->parseRequestBody($context['request'] ?? null),
        ]));
        // A bot that fills the honeypot gets the answer of a recorded declaration, with no ID. No row, no email.
        if ($model === null) {
            return $dto ?? new RevocationRequest();
        }

        return RevocationRequest::fromModel($model);
    }
}
