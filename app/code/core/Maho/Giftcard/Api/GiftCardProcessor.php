<?php

/**
 * Give a gift card write of REST or GraphQL to the gift card service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

namespace Maho\Giftcard\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;

final class GiftCardProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GiftCard
    {
        $user = $this->requireUser();
        $input = RequestInput::of($context['args']['input'] ?? []);

        return match (true) {
            $operation->getName() === 'create' => $this->create($user, $input, null),
            $operation->getName() === 'adjustBalance' => $this->adjustBalance($user, $input),
            $operation->getName() === 'giftcard_send_email' => $this->sendEmail($user, (int) $uriVariables['id']),
            $operation->getName() === 'sendEmail' => $this->sendEmail($user, $input->int('giftCardId') ?? 0),
            isset($uriVariables['id']) => $this->update($user, (int) $uriVariables['id'], $data),
            default => $this->create($user, $input, $data),
        };
    }

    /**
     * Create a card from the GraphQL $input or from the REST $data. A card with no websites gets the website of the request.
     */
    private function create(ApiUser $user, RequestInput $input, ?GiftCard $data): GiftCard
    {
        /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = \Mage::getModel('giftcard/giftcard');
        if ($data !== null) {
            $data->applyToModel($giftcard);
            // Clients before 1.1.0 send one websiteId
            $websiteIds = $data->websiteIds ?? ($data->websiteId !== null ? [$data->websiteId] : null);
        } else {
            $initialBalance = $input->float('initialBalance') ?? 0.0;
            $giftcard->addData([
                'balance' => $initialBalance,
                'initial_balance' => $initialBalance,
                'code' => $input->string('code'),
                'recipient_name' => $input->string('recipientName'),
                'recipient_email' => $input->string('recipientEmail'),
                'sender_name' => $input->string('senderName'),
                'sender_email' => $input->string('senderEmail'),
                'message' => $input->string('message'),
                'expires_at' => $input->string('expiresAt'),
            ]);
            $websiteIds = $input->intList('websiteIds');
        }
        $giftcard->setWebsiteIds($websiteIds ?? [(int) StoreContext::getStore()->getWebsiteId()]);
        $this->assertAllWebsitesAllowed($giftcard->getWebsiteIds(), $user, 'gift card');

        /** @var \Maho_Giftcard_Service_Giftcard $service */
        $service = \Mage::getService('giftcard/giftcard');
        $service->save($giftcard);
        $this->logApiActivity('gift_card', 'create', null, $giftcard);

        return GiftCard::fromModel($service->getById((int) $giftcard->getId()));
    }

    /**
     * A PUT changes only the status and the balance. A read-modify-write PUT sends the stored websites back, so only a change is refused.
     */
    private function update(ApiUser $user, int $id, GiftCard $data): GiftCard
    {
        /** @var \Maho_Giftcard_Service_Giftcard $service */
        $service = \Mage::getService('giftcard/giftcard');
        $giftcard = $service->getById($id);
        $this->assertAllWebsitesAllowed($giftcard->getWebsiteIds(), $user, 'gift card');
        $oldData = $giftcard->getData();

        if ($data->websiteIds !== null
            && \Maho_Giftcard_Model_Giftcard::canonicalizeWebsiteIds($data->websiteIds) !== $giftcard->getWebsiteIds()
        ) {
            throw new \Mage_Core_Exception_Input()->addError('website_ids', \Mage::helper('giftcard')->__('websiteIds cannot be changed through update'));
        }

        $service->update($giftcard, $data->balance, $data->status, $data->comment ?? 'API balance adjustment');
        $this->logApiActivity('gift_card', 'update', $oldData, $giftcard);

        return GiftCard::fromModel($service->getById($id));
    }

    private function sendEmail(ApiUser $user, int $id): GiftCard
    {
        /** @var \Maho_Giftcard_Service_Giftcard $service */
        $service = \Mage::getService('giftcard/giftcard');
        $giftcard = $service->getById($id);
        $this->assertAllWebsitesAllowed($giftcard->getWebsiteIds(), $user, 'gift card');
        $service->sendEmail($giftcard);

        return GiftCard::fromModel($service->getById($id));
    }

    private function adjustBalance(ApiUser $user, RequestInput $input): GiftCard
    {
        $code = trim($input->string('code') ?? '');
        if ($code === '') {
            throw new \Mage_Core_Exception_Input()->addError('code', \Mage::helper('giftcard')->__('Gift card code is required'));
        }

        /** @var \Maho_Giftcard_Service_Giftcard $service */
        $service = \Mage::getService('giftcard/giftcard');
        $giftcard = $service->getByCode($code);
        $this->assertAllWebsitesAllowed($giftcard->getWebsiteIds(), $user, 'gift card');
        $service->update($giftcard, $input->float('newBalance') ?? 0.0, null, $input->string('comment'));

        return GiftCard::fromModel($giftcard);
    }
}
