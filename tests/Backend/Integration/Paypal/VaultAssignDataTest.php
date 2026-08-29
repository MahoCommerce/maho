<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

function vaultAssignDataCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1);
    $customer->setGroupId(1);
    $customer->setFirstname('Vault');
    $customer->setLastname('Owner');
    $customer->setEmail('vault-assign-' . bin2hex(random_bytes(4)) . '@example.com');
    $customer->save();
    return $customer;
}

function vaultAssignDataCreateToken(int $customerId): Maho_Paypal_Model_Vault_Token
{
    $paypalTokenId = 'test-token-' . bin2hex(random_bytes(8));
    $token = Mage::getModel('paypal/vault_token');
    $token->setCustomerId($customerId);
    $token->setPaypalTokenId($paypalTokenId);
    $token->setPaypalTokenIdHash(hash('sha256', $paypalTokenId));
    $token->setPaymentSourceType('paypal');
    $token->setPayerEmail('vault-assign@example.com');
    $token->save();
    return $token;
}

describe('paypal vault assignData ownership', function (): void {

    it('accepts a token owned by the quote customer without any session', function (): void {
        $customer = vaultAssignDataCreateCustomer();
        $token = vaultAssignDataCreateToken((int) $customer->getId());
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        try {
            $quote->setCustomerId((int) $customer->getId());
            $payment = $quote->getPayment();

            $method = new Maho_Paypal_Model_Method_Vault();
            $method->setInfoInstance($payment);
            $method->assignData(['vault_token_id' => (int) $token->getId()]);

            expect($payment->getAdditionalInformation('vault_token_id'))->toBe((int) $token->getId());
        } finally {
            $quote->delete();
            $token->delete();
            $customer->delete();
        }
    });

    it('rejects a token owned by another customer', function (): void {
        $owner = vaultAssignDataCreateCustomer();
        $other = vaultAssignDataCreateCustomer();
        $token = vaultAssignDataCreateToken((int) $owner->getId());
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        try {
            $quote->setCustomerId((int) $other->getId());
            $payment = $quote->getPayment();

            $method = new Maho_Paypal_Model_Method_Vault();
            $method->setInfoInstance($payment);
            $method->assignData(['vault_token_id' => (int) $token->getId()]);

            expect($payment->getAdditionalInformation('vault_token_id'))->toBeNull();
        } finally {
            $quote->delete();
            $token->delete();
            $other->delete();
            $owner->delete();
        }
    });

});
