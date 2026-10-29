<?php

/**
 * Gmail go-to actions for transactional emails.
 *
 * Gmail reads schema.org EmailMessage markup from the document head and turns it into a button
 * next to the subject line. For a shipment email that button opens the public tracking page of
 * the carrier when there is one, and the order in the store otherwise.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Email_Gmail_Actions
{
    /**
     * The carrier page of the shipment, when the carrier publishes one and its URL can be composed
     * from configuration: the track accessor never asks the carrier while the email is rendered.
     */
    public const TRACK_ACTION_TYPE = 'TrackAction';

    /**
     * The action is a link to the store, so ViewAction is the honest type: it is used whenever no
     * carrier page is available, and TrackAction has nothing to point at.
     */
    public const ACTION_TYPE = 'ViewAction';

    public const ACTION_LABEL = 'Track your order';

    public const TRACK_ACTION_LABEL = 'Track your shipment';

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

        $target = $this->getTrackingUrl($shipment);
        $actionType = self::TRACK_ACTION_TYPE;
        $actionLabel = self::TRACK_ACTION_LABEL;
        if ($target === '') {
            $target = $this->getOrderUrl($order);
            $actionType = self::ACTION_TYPE;
            $actionLabel = self::ACTION_LABEL;
        }
        if ($target === '') {
            return '';
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'EmailMessage',
            'name' => Mage::helper('core')->__($actionLabel),
            'description' => Mage::helper('core')->__('Tracking number %s', implode(', ', $numbers)),
            'potentialAction' => [
                '@type' => $actionType,
                'name' => Mage::helper('core')->__($actionLabel),
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
     * The public tracking page of the first track that has one, or '' when none has.
     *
     * The guard on the method keeps this class working on an installation where the carrier
     * tracking URL contract is not available yet: the action then falls back to the order page.
     *
     * @return string
     */
    protected function getTrackingUrl(Mage_Sales_Model_Order_Shipment $shipment)
    {
        foreach ($shipment->getAllTracks() as $track) {
            if (!is_object($track) || !method_exists($track, 'getTrackingUrl')) {
                continue;
            }

            $url = trim((string) $track->getTrackingUrl());
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * The order page in the store the email belongs to.
     *
     * @return string
     */
    protected function getOrderUrl(Mage_Sales_Model_Order $order)
    {
        $storeId = (int) $order->getStoreId();
        $url = Mage::app()->getStore($storeId)->getUrl('sales/order/view', ['order_id' => (int) $order->getId()]);

        return $url === false ? '' : (string) $url;
    }
}
