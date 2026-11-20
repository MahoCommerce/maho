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

    it('charges a token of the customer of the order', function (): void {
        $owner = vaultAssignDataCreateCustomer();
        $token = vaultAssignDataCreateToken((int) $owner->getId());
        $quote = createPricedQuote(loadSimplePricedProduct());

        try {
            $payment = Mage::getModel('sales/order_payment')
                ->setOrder(Mage::getModel('sales/order')->setCustomerId((int) $owner->getId()))
                ->setQuote($quote);
            $payment->setAdditionalInformation('vault_token_id', (int) $token->getId());

            $client = new class extends Maho_Paypal_Model_Api_Client {
                public array $sent = [];

                #[\Override]
                public function createOrder(array $orderRequest): array
                {
                    $this->sent = $orderRequest;
                    throw new \RuntimeException('pest stop');
                }
            };
            $method = new Maho_Paypal_Model_Method_Vault();
            $method->setInfoInstance($payment);
            $method->setApiClient($client);

            expect(fn() => $method->initialize('authorize', new \Maho\DataObject()))
                ->toThrow(Mage_Core_Exception::class, 'Failed to create PayPal order: pest stop');
            expect($client->sent['body']['payment_source']['paypal']['vault_id'] ?? null)
                ->toBe(Mage::getModel('paypal/vault_token')->load($token->getId())->getPaypalTokenId());
        } finally {
            $quote->delete();
            $token->delete();
            $owner->delete();
        }
    });

    it('refuses to charge a token that the customer removed', function (): void {
        $owner = vaultAssignDataCreateCustomer();
        $token = vaultAssignDataCreateToken((int) $owner->getId());
        $token->setIsActive(0)->save();

        try {
            $payment = Mage::getModel('sales/order_payment')
                ->setOrder(Mage::getModel('sales/order')->setCustomerId((int) $owner->getId()));
            $payment->setAdditionalInformation('vault_token_id', (int) $token->getId());

            $method = new Maho_Paypal_Model_Method_Vault();
            $method->setInfoInstance($payment);

            expect(fn() => $method->initialize('authorize', new \Maho\DataObject()))
                ->toThrow(Mage_Core_Exception::class, 'No saved payment methods available for this customer.');
        } finally {
            $token->delete();
            $owner->delete();
        }
    });

    it('clears the token of an earlier selection when the customer selects a token of another customer', function (): void {
        $owner = vaultAssignDataCreateCustomer();
        $other = vaultAssignDataCreateCustomer();
        $ownToken = vaultAssignDataCreateToken((int) $owner->getId());
        $otherToken = vaultAssignDataCreateToken((int) $other->getId());
        $quote = createPricedQuote(loadSimplePricedProduct());

        try {
            $quote->setCustomerId((int) $owner->getId());
            $payment = $quote->getPayment();
            $method = new Maho_Paypal_Model_Method_Vault();
            $method->setInfoInstance($payment);
            $method->assignData(['vault_token_id' => (int) $ownToken->getId()]);

            $method->assignData(['vault_token_id' => (int) $otherToken->getId()]);

            expect($payment->getAdditionalInformation('vault_token_id'))->toBeNull()
                ->and($payment->getAdditionalInformation('vault_label'))->toBeNull();
        } finally {
            $quote->delete();
            $otherToken->delete();
            $ownToken->delete();
            $other->delete();
            $owner->delete();
        }
    });

    it('refuses to charge a token of another customer that the order payment holds', function (): void {
        $owner = vaultAssignDataCreateCustomer();
        $other = vaultAssignDataCreateCustomer();
        $token = vaultAssignDataCreateToken((int) $owner->getId());

        try {
            $payment = Mage::getModel('sales/order_payment')
                ->setOrder(Mage::getModel('sales/order')->setCustomerId((int) $other->getId()));
            $payment->setAdditionalInformation('vault_token_id', (int) $token->getId());

            $method = new Maho_Paypal_Model_Method_Vault();
            $method->setInfoInstance($payment);

            expect(fn() => $method->initialize('authorize', new \Maho\DataObject()))
                ->toThrow(Mage_Core_Exception::class, 'No saved payment methods available for this customer.');
        } finally {
            $token->delete();
            $other->delete();
            $owner->delete();
        }
    });

});
