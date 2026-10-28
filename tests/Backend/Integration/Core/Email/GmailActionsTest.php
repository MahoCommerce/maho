<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Gmail actions', function () {
    beforeEach(function () {
        $order = Mage::getModel('sales/order')
            ->setId(7)
            ->setStoreId((int) Mage::app()->getDefaultStoreView()->getId())
            ->setIncrementId('100000007');

        $shipment = new class extends Mage_Sales_Model_Order_Shipment {
            /** @var list<Mage_Sales_Model_Order_Shipment_Track> */
            public array $fakeTracks = [];

            #[\Override]
            public function getAllTracks($reload = false)
            {
                return $this->fakeTracks;
            }
        };
        $shipment->fakeTracks = [
            Mage::getModel('sales/order_shipment_track')
                ->setNumber('1Z999AA10123456784')
                ->setTitle('United Parcel Service'),
        ];

        $this->variables = ['order' => $order, 'shipment' => $shipment];
        $this->actions = Mage::getSingleton('core/email_gmail_actions');
    });

    it('builds a schema.org EmailMessage with a go-to action', function () {
        $markup = $this->actions->getMarkup($this->variables);

        expect($markup)->toStartWith('<script type="application/ld+json">');

        preg_match('#<script type="application/ld\+json">(.*)</script>#s', $markup, $matches);
        $payload = json_decode($matches[1], true);

        expect($payload)->toBeArray();
        expect($payload['@type'])->toBe('EmailMessage');
        expect($payload['potentialAction']['@type'])->toBe('ViewAction');
        expect($payload['potentialAction']['target'])->toContain('sales/order/view');
        expect($payload['description'])->toContain('1Z999AA10123456784');
    });

    it('puts the markup before the closing head tag, and only once', function () {
        $html = '<html><head><title>Order</title></head><body>Body</body></html>';
        $injected = $this->actions->inject($this->variables, $html);

        expect(substr_count($injected, 'application/ld+json'))->toBe(1);

        $scriptAt = strpos($injected, 'application/ld+json');
        $headAt = strpos($injected, '</head>');

        expect($scriptAt)->toBeInt()->toBeLessThan($headAt);
    });

    it('leaves the document alone when there is nothing to link', function () {
        $html = '<html><head><title>Order</title></head><body>Body</body></html>';

        $withoutShipment = $this->actions->inject(['order' => $this->variables['order']], $html);
        expect($withoutShipment)->toBe($html);

        $this->variables['shipment']->fakeTracks = [];
        expect($this->actions->inject($this->variables, $html))->toBe($html);

        $this->variables['shipment']->fakeTracks = [Mage::getModel('sales/order_shipment_track')];
        expect($this->actions->getMarkup($this->variables))->toBe('');
    });

    it('skips a fragment with no head', function () {
        expect($this->actions->inject($this->variables, '<p>Body</p>'))->toBe('<p>Body</p>');
    });

    it('keeps a markup the template already declares', function () {
        $html = '<html><head><script type="application/ld+json">{}</script></head><body>Body</body></html>';

        expect($this->actions->inject($this->variables, $html))->toBe($html);
    });
});
