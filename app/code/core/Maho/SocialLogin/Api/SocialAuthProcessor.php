<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

namespace Maho\SocialLogin\Api;

use ApiPlatform\Metadata\Operation;
use Mage\Checkout\Api\CartService;
use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SocialAuthProcessor extends \Maho\ApiPlatform\Processor
{
    public function __construct(
        Security $security,
        private JwtService $jwtService,
        private CartService $cartService,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SocialAuth
    {
        StoreContext::ensureStore();
        $this->checkRateLimitByIp('social_auth', 'auth_token_ip', 60);

        if (!$data instanceof SocialAuth || !is_string($data->provider) || !is_string($data->providerToken)) {
            throw new BadRequestHttpException('provider and providerToken are required', null, 0, ['X-Api-Error-Code' => 'invalid_request']);
        }

        $nameHints = [
            'firstname' => (string) $data->firstName,
            'lastname' => (string) $data->lastName,
        ];

        try {
            // Headless nonce contract: the caller generates its own nonce, passes it
            // to the provider SDK, and repeats it here; when omitted (for example a
            // trusted server-side relay), the nonce constraint is skipped.
            $result = \Mage::getModel('sociallogin/service')->authenticate(
                $data->provider,
                $data->providerToken,
                (int) \Mage::app()->getStore()->getId(),
                is_string($data->nonce) && $data->nonce !== '' ? $data->nonce : null,
                $nameHints,
            );
        } catch (\Maho_SocialLogin_Model_ProviderUnavailableException $e) {
            // Transient upstream outage: retryable, not a credential rejection
            throw new ServiceUnavailableHttpException(30, $e->getMessage(), null, 0, ['X-Api-Error-Code' => 'temporarily_unavailable']);
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage(), null, 0, ['X-Api-Error-Code' => 'invalid_grant']);
        } catch (\Throwable $e) {
            \Mage::logException($e);
            throw new HttpException(500, 'An error occurred during authentication');
        }

        $customer = $result['customer'];

        // The storefront flow sends 2FA-enabled customers through a TOTP
        // challenge after the provider proves identity; the headless flow must
        // enforce the same second factor before a JWT is issued.
        if (\Mage::getStoreConfigFlag('customer/password/allow_2fa') && $customer->getTwofaEnabled()) {
            $code = is_string($data->twofaCode) ? trim($data->twofaCode) : '';
            if ($code === '') {
                throw new UnauthorizedHttpException('Bearer', 'Two-factor authentication code is required', null, 0, ['X-Api-Error-Code' => 'twofa_required']);
            }
            if (!\Mage::helper('core/security')->verifyTotpCode($customer->getTwofaSecret() ?? '', $code)) {
                throw new UnauthorizedHttpException('Bearer', 'Two-factor authentication code is invalid', null, 0, ['X-Api-Error-Code' => 'twofa_invalid']);
            }
        }

        // A fresh DTO so the inbound credential is never echoed back
        $dto = new SocialAuth();
        $dto->provider = $data->provider;
        $dto->token = $this->jwtService->generateCustomerToken($customer);
        $dto->tokenType = 'Bearer';
        $dto->expiresIn = $this->jwtService->getTokenExpiry();
        $dto->customer = [
            'id' => (int) $customer->getId(),
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
        ];
        $dto->isNewCustomer = $result['is_new'];
        if ($result['is_new']) {
            $dto->profileIncomplete = \Mage::helper('sociallogin')->hasIncompleteRequiredProfile($customer);
        }

        $guestCartMaskedId = $data->cartId;
        $cartId = null;
        $customerCart = null;
        if (CartService::isValidMaskedId($guestCartMaskedId)) {
            try {
                // CartService::mergeCarts enforces the guest-cart ownership guard,
                // re-collects totals, and deactivates the guest cart atomically.
                $customerCart = $this->cartService->mergeCarts($guestCartMaskedId, (int) $customer->getId());
                $cartId = (int) $customerCart->getId();
            } catch (\Exception $e) {
                \Mage::log('Cart merge failed: ' . $e->getMessage(), \Mage::LOG_WARNING);
            }
        }
        if (!$cartId) {
            $customerCart = \Mage::getModel('sales/quote')
                ->setSharedStoreIds([\Mage::app()->getStore()->getId()])
                ->loadByCustomer((int) $customer->getId());
            $cartId = $customerCart->getId() ? (int) $customerCart->getId() : null;
        }
        $dto->cartId = $cartId;
        $dto->cartMaskedId = $customerCart?->getData('masked_quote_id');
        $dto->cartItemsQty = $customerCart ? (float) $customerCart->getItemsQty() : 0;

        return $dto;
    }
}
