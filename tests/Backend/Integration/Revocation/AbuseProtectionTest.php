<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

function revocation_dispatch_submit(array $post): Mage_Core_Controller_Response_Http
{
    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('http://localhost/revocation/submit', 'POST', $post),
    );
    $response = new Mage_Core_Controller_Response_Http();
    $controller = new Maho_Revocation_IndexController($request, $response);
    $controller->submitAction();
    return $response;
}

function revocation_valid_post(array $overrides = []): array
{
    return array_merge([
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        // Render token old enough to pass the min-submit-seconds gate
        'frt' => Mage::helper('core')->encrypt((string) (time() - 60)),
        Mage::helper('core')->getHoneypotFieldName() => '',
        'customer_name' => 'Max Mustermann',
        'email' => 'gate-' . uniqid() . '@example.com',
        'order_reference' => (string) random_int(100000000, 199999999),
        'reason' => '',
    ], $overrides);
}

function revocation_count_rows_for_email(string $email): int
{
    return Mage::getResourceModel('revocation/request_collection')
        ->addFieldToFilter('email', $email)
        ->getSize();
}

describe('Revocation email normalization', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('revocation');
    });

    it('treats gmail plus-addresses and dotted local parts as the same recipient', function () {
        expect($this->helper->normalizeEmail('Victim+1@Gmail.com'))->toBe('victim@gmail.com');
        expect($this->helper->normalizeEmail('vic.tim@gmail.com'))->toBe('victim@gmail.com');
        expect($this->helper->normalizeEmail('v.i.c.t.i.m+abc@googlemail.com'))->toBe('victim@googlemail.com');
    });

    it('treats subaddressing on non-gmail domains as the same recipient but keeps dots', function () {
        expect($this->helper->normalizeEmail('user+tag@example.com'))->toBe('user@example.com');
        expect($this->helper->normalizeEmail('first.last@example.com'))->toBe('first.last@example.com');
    });

    it('rate-limits normalized aliases of the same inbox together', function () {
        $local = 'victim' . uniqid();
        expect($this->helper->isRecipientRateLimited("{$local}+a@gmail.com"))->toBeFalse();
        expect($this->helper->isRecipientRateLimited("{$local}+b@gmail.com"))->toBeTrue();
        expect($this->helper->isRecipientRateLimited(substr($local, 0, 6) . '.' . substr($local, 6) . '@gmail.com'))->toBeTrue();
    });
});

describe('Revocation per-IP rate limit', function () {
    beforeEach(function () {
        // Core resolves the client IP itself now, so the key is constant within the run;
        // clear carry-over hits for this test client before counting.
        Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    });

    it('blocks after the configured number of submissions per hour', function () {
        $helper = Mage::helper('revocation');

        for ($i = 0; $i < 5; $i++) {
            expect($helper->isIpRateLimited())->toBeFalse();
        }
        expect($helper->isIpRateLimited())->toBeTrue();
    });

    it('is disabled when the limit is configured to 0', function () {
        Mage::app()->getStore()->setConfig('revocation/abuse/ip_rate_limit_per_hour', '0');
        $helper = Mage::helper('revocation');

        for ($i = 0; $i < 20; $i++) {
            expect($helper->isIpRateLimited())->toBeFalse();
        }
    });
});

describe('Revocation controller abuse gates', function () {
    beforeEach(function () {
        $store = Mage::app()->getStore();
        $store->setConfig('revocation/general/enabled', '1');
        $store->setConfig('trans_email/ident_general/email', 'shop@example.com');
        $store->setConfig('trans_email/ident_general/name', 'Test Shop');
    });

    it('accepts a legitimate submission end-to-end and redirects to the success page', function () {
        $post = revocation_valid_post();
        $response = revocation_dispatch_submit($post);

        expect($response->isRedirect())->toBeTrue();
        expect(revocation_count_rows_for_email($post['email']))->toBe(1);

        $success = Mage::getSingleton('core/session')->getRevocationSuccess(true);
        expect($success)->toBeArray();
        expect($success['request_id'])->toBeGreaterThan(0);

        Mage::getModel('revocation/request')->load($success['request_id'])->delete();
    });

    it('silently drops submissions with the honeypot field filled', function () {
        $post = revocation_valid_post([Mage::helper('core')->getHoneypotFieldName() => 'http://spam.example']);
        $response = revocation_dispatch_submit($post);

        expect($response->isRedirect())->toBeTrue();
        expect(revocation_count_rows_for_email($post['email']))->toBe(0);

        // Indistinguishable from a real submit: the success payload is still populated
        $success = Mage::getSingleton('core/session')->getRevocationSuccess(true);
        expect($success)->toBeArray();
        expect($success['request_id'])->toBeGreaterThan(0);
    });

    it('silently drops submissions faster than the configured min_submit_seconds', function () {
        $post = revocation_valid_post(['frt' => Mage::helper('core')->encrypt((string) time())]);
        $response = revocation_dispatch_submit($post);

        expect($response->isRedirect())->toBeTrue();
        expect(revocation_count_rows_for_email($post['email']))->toBe(0);
        Mage::getSingleton('core/session')->getRevocationSuccess(true);
    });

    it('silently drops submissions with a missing or invalid render token', function () {
        $post = revocation_valid_post(['frt' => 'garbage']);
        revocation_dispatch_submit($post);
        expect(revocation_count_rows_for_email($post['email']))->toBe(0);
        Mage::getSingleton('core/session')->getRevocationSuccess(true);
    });

    it('rejects submissions that fail the form-key check without writing a row', function () {
        $post = revocation_valid_post(['form_key' => 'wrong-key']);
        $response = revocation_dispatch_submit($post);

        expect($response->isRedirect())->toBeTrue();
        expect(revocation_count_rows_for_email($post['email']))->toBe(0);
        expect(Mage::getSingleton('core/session')->getRevocationSuccess(true))->toBeNull();
    });

    it('returns a generic error when the per-IP rate limit is exceeded and writes no row', function () {
        $post = revocation_valid_post();
        $ids = [];

        // Pin one IP for all six dispatches
        $ip = '10.99.' . random_int(0, 255) . '.' . random_int(1, 254);
        for ($i = 0; $i < 6; $i++) {
            $post['email'] = 'iplimit-' . uniqid() . '@example.com';
            $_SERVER['REMOTE_ADDR'] = $ip;
            $request = new Mage_Core_Controller_Request_Http(
                SymfonyRequest::create('http://localhost/revocation/submit', 'POST', $post),
            );
            $response = new Mage_Core_Controller_Response_Http();
            (new Maho_Revocation_IndexController($request, $response))->submitAction();

            $success = Mage::getSingleton('core/session')->getRevocationSuccess(true);
            if ($i < 5) {
                expect(revocation_count_rows_for_email($post['email']))->toBe(1);
                $ids[] = $success['request_id'];
            } else {
                expect(revocation_count_rows_for_email($post['email']))->toBe(0);
                expect($success)->toBeNull();
            }
        }

        foreach ($ids as $id) {
            Mage::getModel('revocation/request')->load($id)->delete();
        }
    });

    it('does nothing when the module is disabled', function () {
        Mage::app()->getStore()->setConfig('revocation/general/enabled', '0');
        $post = revocation_valid_post();
        revocation_dispatch_submit($post);
        expect(revocation_count_rows_for_email($post['email']))->toBe(0);
    });
});
