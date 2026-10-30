<?php

/**
 * Gmail go-to actions for transactional emails.
 *
 * Gmail reads schema.org EmailMessage markup from the document head and turns it into a button
 * next to the subject line. For a shipment email that button opens the public tracking page of
 * the carrier when there is one, and the tracking page of the store otherwise.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Email_Gmail_Actions
{
    /**
     * Add the markup to a rendered email, immediately before the closing head tag.
     *
     * Nothing is added when the email has no head (a plain fragment), when it already carries
     * schema.org markup, or when there is no shipment to link.
     *
     * @param array<string, mixed> $variables the variables the template was rendered with
     */
    public function inject(array $variables, string $html): string
    {
        $headAt = stripos($html, '</head>');
        if ($headAt === false || stripos($html, 'application/ld+json') !== false) {
            return $html;
        }

        $markup = $this->getMarkup($variables);
        if ($markup === '') {
            return $html;
        }

        return substr_replace($html, $markup, $headAt, 0);
    }

    /**
     * The schema.org script for the shipment the email is about, or '' when there is none.
     *
     * @param array<string, mixed> $variables
     */
    public function getMarkup(array $variables): string
    {
        $shipment = $variables['shipment'] ?? null;
        if (!$shipment instanceof Mage_Sales_Model_Order_Shipment) {
            return '';
        }

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
        if ($target === '') {
            $target = Mage::helper('shipping')->getTrackingPopupUrlBySalesModel($shipment);
        }

        $label = Mage::helper('core')->__('Track your shipment');
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'EmailMessage',
            'name' => $label,
            'description' => Mage::helper('core')->__('Tracking number %s', implode(', ', $numbers)),
            'potentialAction' => [
                '@type' => 'TrackAction',
                'name' => $label,
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
     * The carrier page of the first track that has one, or '' when none has.
     */
    protected function getTrackingUrl(Mage_Sales_Model_Order_Shipment $shipment): string
    {
        foreach ($shipment->getAllTracks() as $track) {
            if (!method_exists($track, 'getTrackingUrl')) {
                continue;
            }

            $url = trim((string) $track->getTrackingUrl());
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }
}
