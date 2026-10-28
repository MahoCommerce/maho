<?php

/**
 * Gmail go-to actions for transactional emails.
 *
 * Gmail reads schema.org EmailMessage markup from the document head and turns it into a button
 * next to the subject line. For a shipment email that button opens the order, where the customer
 * finds the tracking numbers.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Email_Gmail_Actions
{
    /**
     * The action is a link to the store, so ViewAction is the honest type: TrackAction expects a
     * parcel carrier URL, and building one here would mean asking the carrier API for the tracking
     * detail while the email is being rendered.
     */
    public const ACTION_TYPE = 'ViewAction';

    public const ACTION_LABEL = 'Track your order';

    /**
     * Add the markup to a rendered email, immediately before the closing head tag.
     *
     * Nothing is added when the email has no head (a plain fragment), when it already carries
     * schema.org markup, or when there is no shipment to link.
     *
     * @param array<string, mixed> $variables the variables the template was rendered with
     * @param string $html
     * @return string
     */
    public function inject(array $variables, $html)
    {
        $html = (string) $html;
        if (stripos($html, '</head>') === false || stripos($html, 'application/ld+json') !== false) {
            return $html;
        }

        $markup = $this->getMarkup($variables);
        if ($markup === '') {
            return $html;
        }

        return (string) preg_replace('/<\/head>/i', $markup . '</head>', $html, 1);
    }

    /**
     * The schema.org script for the shipment the email is about, or '' when there is none.
     *
     * @param array<string, mixed> $variables
     * @return string
     */
    public function getMarkup(array $variables)
    {
        $shipment = $variables['shipment'] ?? null;
        if (!$shipment instanceof Mage_Sales_Model_Order_Shipment) {
            return '';
        }

        $order = $variables['order'] ?? null;
        if (!$order instanceof Mage_Sales_Model_Order) {
            return '';
        }

        // The action says "track", so it needs at least one number to be worth showing.
        $numbers = [];
        foreach ($shipment->getAllTracks() as $track) {
            $number = trim((string) $track->getNumber());
            if ($number !== '') {
                $numbers[] = $number;
            }
        }
        if ($numbers === []) {
            return '';
        }

        $target = $this->getOrderUrl($order);
        if ($target === '') {
            return '';
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'EmailMessage',
            'name' => Mage::helper('core')->__(self::ACTION_LABEL),
            'description' => Mage::helper('core')->__('Tracking number %s', implode(', ', $numbers)),
            'potentialAction' => [
                '@type' => self::ACTION_TYPE,
                'name' => Mage::helper('core')->__(self::ACTION_LABEL),
                'target' => $target,
            ],
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
        if ($json === false) {
            return '';
        }

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * The order page in the store the email belongs to.
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    protected function getOrderUrl(Mage_Sales_Model_Order $order)
    {
        $storeId = (int) $order->getStoreId();
        $url = Mage::app()->getStore($storeId)->getUrl('sales/order/view', ['order_id' => (int) $order->getId()]);

        return $url === false ? '' : (string) $url;
    }
}
