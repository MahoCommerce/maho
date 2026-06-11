<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Transformer_UrlEncode extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'url_encode';
    protected string $_name = 'URL Encode';
    protected string $_description = 'Encode value for safe use in URLs';

    protected array $_optionDefinitions = [
        'encode_type' => [
            'label' => 'Encoding Type',
            'type' => 'select',
            'required' => false,
            'options' => [
                'path' => 'URL Path (rawurlencode)',
                'query' => 'Query String (urlencode)',
                'full' => 'Full URL (encode special chars only)',
            ],
            'note' => 'Type of URL encoding to apply',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $encodeType = (string) $this->_getOption($options, 'encode_type', 'path');

        return match ($encodeType) {
            'query' => urlencode($value),
            'full' => $this->_encodeFullUrl($value),
            default => rawurlencode($value),
        };
    }

    /**
     * Encode a full URL, preserving structure
     */
    protected function _encodeFullUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return rawurlencode($url);
        }

        $result = '';

        if (isset($parts['scheme'])) {
            $result .= $parts['scheme'] . '://';
        }

        if (isset($parts['host'])) {
            $result .= $parts['host'];
        }

        if (isset($parts['port'])) {
            $result .= ':' . $parts['port'];
        }

        if (isset($parts['path'])) {
            $result .= implode('/', array_map('rawurlencode', explode('/', $parts['path'])));
        }

        if (isset($parts['query'])) {
            $result .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $result .= '#' . rawurlencode($parts['fragment']);
        }

        return $result;
    }
}
