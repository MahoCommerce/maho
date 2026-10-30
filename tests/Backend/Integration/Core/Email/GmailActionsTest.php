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
            ->setId(4242)
            ->setStoreId((int) Mage::app()->getDefaultStoreView()->getId())
            ->setIncrementId('900000042')
            ->setProtectCode('a1b2c3');

        $shipment = new class extends Mage_Sales_Model_Order_Shipment {
            /** @var list<Mage_Sales_Model_Order_Shipment_Track> */
            public array $fakeTracks = [];

            #[\Override]
            public function getAllTracks($reload = false)
            {
                return $this->fakeTracks;
            }
        };
        $shipment->setId(77)->setOrder($order);
        $shipment->fakeTracks = [
            // The example tracking number from the carrier documentation.
            Mage::getModel('sales/order_shipment_track')
                ->setNumber('1Z999AA10123456784')
                ->setTitle('United Parcel Service'),
        ];

        $this->variables = ['order' => $order, 'shipment' => $shipment];
        $this->actions = Mage::getSingleton('core/email_gmail_actions');
        $this->payload = function (string $markup): array {
            preg_match('#<script type="application/ld\+json">(.*)</script>#s', $markup, $matches);
            return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        };
    });

    it('links the tracking page of the store when the carrier has no page', function () {
        $markup = $this->actions->getMarkup($this->variables);

        expect($markup)->toStartWith('<script type="application/ld+json">');

        $payload = ($this->payload)($markup);
        $hash = Mage::helper('core')->urlEncode('ship_id:77:a1b2c3');

        expect($payload['@type'])->toBe('EmailMessage');
        expect($payload['potentialAction']['@type'])->toBe('TrackAction');
        expect($payload['potentialAction']['target'])->toContain('shipping/tracking/popup');
        expect($payload['potentialAction']['target'])->toContain($hash);
        expect($payload['description'])->toContain('1Z999AA10123456784');
    });

    it('links the carrier page when the track exposes one', function () {
        $track = new class extends Mage_Sales_Model_Order_Shipment_Track {
            public function getTrackingUrl(): ?string
            {
                return 'https://www.ups.com/track?tracknum=1Z999AA10123456784';
            }
        };
        $track->setNumber('1Z999AA10123456784')->setTitle('United Parcel Service');
        $this->variables['shipment']->fakeTracks = [$track];

        $payload = ($this->payload)($this->actions->getMarkup($this->variables));

        expect($payload['potentialAction']['@type'])->toBe('TrackAction');
        expect($payload['potentialAction']['target'])
            ->toBe('https://www.ups.com/track?tracknum=1Z999AA10123456784');
    });

    it('links the tracking page of the store when the carrier page is empty', function () {
        $track = new class extends Mage_Sales_Model_Order_Shipment_Track {
            public function getTrackingUrl(): ?string
            {
                return null;
            }
        };
        $track->setNumber('1Z999AA10123456784');
        $this->variables['shipment']->fakeTracks = [$track];

        $payload = ($this->payload)($this->actions->getMarkup($this->variables));

        expect($payload['potentialAction']['target'])->toContain('shipping/tracking/popup');
    });

    it('puts the markup before the closing head tag, and only once', function () {
        $html = '<html><head><title>Order</title></head><body>Body</body></html>';
        $injected = $this->actions->inject($this->variables, $html);

        expect(substr_count($injected, 'application/ld+json'))->toBe(1);

        $scriptAt = strpos($injected, 'application/ld+json');
        $headAt = strpos($injected, '</head>');

        expect($scriptAt)->toBeInt()->toBeLessThan($headAt);
    });

    it('lists every number and links the first carrier page of several tracks', function () {
        $withPage = new class extends Mage_Sales_Model_Order_Shipment_Track {
            public function getTrackingUrl(): ?string
            {
                return 'https://www.ups.com/track?tracknum=222';
            }
        };
        $withPage->setNumber('222');
        $this->variables['shipment']->fakeTracks = [
            Mage::getModel('sales/order_shipment_track')->setNumber('111'),
            $withPage,
        ];

        $payload = ($this->payload)($this->actions->getMarkup($this->variables));

        expect($payload['potentialAction']['target'])->toBe('https://www.ups.com/track?tracknum=222');
        expect($payload['description'])->toContain('111, 222');
    });

    it('finds the head tag in upper case', function () {
        $html = '<HTML><HEAD></HEAD><BODY>Body</BODY></HTML>';

        expect($this->actions->inject($this->variables, $html))->toContain('application/ld+json');
    });

    it('keeps a backslash and a dollar sign of a tracking number', function () {
        $this->variables['shipment']->fakeTracks = [
            Mage::getModel('sales/order_shipment_track')->setNumber('AB\\12$0'),
        ];
        $html = '<html><head></head><body>Body</body></html>';

        $payload = ($this->payload)($this->actions->inject($this->variables, $html));

        expect($payload['description'])->toContain('AB\\12$0');
    });

    it('keeps a tracking number with HTML inside the script tag', function () {
        $this->variables['shipment']->fakeTracks = [
            Mage::getModel('sales/order_shipment_track')->setNumber('1Z</script><img src=x onerror=alert(1)>'),
        ];

        $markup = $this->actions->getMarkup($this->variables);

        expect(substr_count($markup, '</script>'))->toBe(1);
        expect($markup)->not->toContain('<img');
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

    it('adds the markup to a rendered HTML email and keeps it after CSS inlining', function () {
        $template = Mage::getModel('core/email_template')
            ->setTemplateType(Mage_Core_Model_Template::TYPE_HTML)
            ->setTemplateText('{{inlinecss file="email-inline.css"}}<html><head></head><body><p>Body</p></body></html>');

        $html = $template->getProcessedTemplate($this->variables);

        expect($html)->toContain('<script type="application/ld+json">');
        expect(strpos($html, 'application/ld+json'))->toBeLessThan(stripos($html, '</head>'));
    });

    it('adds no markup to a plain text email', function () {
        $template = Mage::getModel('core/email_template')
            ->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT)
            ->setTemplateText('<html><head></head><body>Body</body></html>');

        expect($template->getProcessedTemplate($this->variables))->not->toContain('application/ld+json');
    });
});
