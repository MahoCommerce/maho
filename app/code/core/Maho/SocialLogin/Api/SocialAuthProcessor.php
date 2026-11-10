<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

namespace Maho\SocialLogin\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SocialAuthProcessor extends \Maho\ApiPlatform\Processor
{
    private readonly \Mage_Checkout_Service_Cart $cartService;

    public function __construct(
        Security $security,
        private JwtService $jwtService,
    ) {
        parent::__construct($security);
        $this->cartService = \Mage::getService('checkout/cart');
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SocialAuth
    {
        StoreContext::ensureStore();

        if (!$data instanceof SocialAuth || !is_string($data->provider) || !is_string($data->providerToken)) {
            throw new BadRequestHttpException('provider and providerToken are required', null, 0, ['X-Api-Error-Code' => 'invalid_request']);
        }

        try {
            // Headless nonce contract: the caller generates its own nonce, passes it
            // to the provider SDK, and repeats it here; when omitted (for example a
            // trusted server-side relay), the nonce constraint is skipped.
            $result = \Mage::getService('sociallogin/identity')->authenticate(
                $data->provider,
                $data->providerToken,
                StoreContext::getStoreId(),
                \Maho_SocialLogin_Model_Channel::Api,
                is_string($data->nonce) && $data->nonce !== '' ? $data->nonce : null,
                ['firstname' => (string) $data->firstName, 'lastname' => (string) $data->lastName],
            );
        } catch (\Mage_Core_Exception_TooManyRequests $e) {
            throw $e;
        } catch (\Maho_SocialLogin_Model_ProviderUnavailableException $e) {
            // Transient upstream outage: retryable, not a credential rejection
            throw new ServiceUnavailableHttpException(30, $e->getMessage(), null, 0, ['X-Api-Error-Code' => 'temporarily_unavailable']);
        } catch (\Mage_Core_Exception $e) {
            // A token endpoint answers a refused grant with a 400 (RFC 6749, section 5.2)
            throw new BadRequestHttpException($e->getMessage(), null, 0, ['X-Api-Error-Code' => 'invalid_grant']);
        }

        $customer = $result['customer'];

        // A customer who uses 2FA gives the code before the token, as on the storefront
        $code = is_string($data->twofaCode) ? trim($data->twofaCode) : '';
        try {
            \Mage::getService('customer/customer')->verifySecondFactor($customer, $code);
        } catch (\Mage_Core_Exception $e) {
            if ($e->getCode() !== \Mage_Customer_Model_Customer::EXCEPTION_2FA_INVALID) {
                throw $e;
            }
            throw $code === ''
                ? new UnauthorizedHttpException('Bearer', 'Two-factor authentication code is required', $e, 0, ['X-Api-Error-Code' => 'twofa_required'])
                : new UnauthorizedHttpException('Bearer', 'Two-factor authentication code is invalid', $e, 0, ['X-Api-Error-Code' => 'twofa_invalid']);
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
        $dto->profileIncomplete = $result['is_new'] ? $result['profile_incomplete'] : null;

        $customerCart = $this->cartService->mergeAtLogin($data->cartId, (int) $customer->getId());
        $dto->cartId = $customerCart ? (int) $customerCart->getId() : null;
        $dto->cartMaskedId = $customerCart?->getData('masked_quote_id');
        $dto->cartItemsQty = $customerCart ? (float) $customerCart->getItemsQty() : 0;

        return $dto;
    }
}
