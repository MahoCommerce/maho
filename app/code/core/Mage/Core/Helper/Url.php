<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Helper_Url extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Core';

    /**
     * Retrieve current url
     *
     * @return string
     */
    public function getCurrentUrl()
    {
        $request = Mage::app()->getRequest();
        $port = $request->getServer('SERVER_PORT');
        if ($port) {
            $defaultPorts = [
                Mage_Core_Controller_Request_Http::DEFAULT_HTTP_PORT,
                Mage_Core_Controller_Request_Http::DEFAULT_HTTPS_PORT,
            ];
            $port = (in_array($port, $defaultPorts)) ? '' : ':' . $port;
        }
        $url = $request->getScheme() . '://' . $request->getHttpHost() . $port . $request->getServer('REQUEST_URI');
        return $this->escapeUrl($url);
    }

    /**
     * Retrieve current url in base64 encoding
     *
     * @return string
     */
    public function getCurrentBase64Url()
    {
        return $this->urlEncode($this->getCurrentUrl());
    }

    /**
     * Return encoded url
     *
     * @param null|string $url
     * @return string
     */
    public function getEncodedUrl($url = null)
    {
        if (!$url) {
            $url = $this->getCurrentUrl();
        }
        return $this->urlEncode($url);
    }

    /**
     * Retrieve homepage url
     *
     * @return string
     */
    public function getHomeUrl()
    {
        return Mage::getBaseUrl();
    }

    /**
     * Formatting string
     *
     * @param string $string
     * @return string
     */
    protected function _prepareString($string)
    {
        $string = preg_replace('#[^0-9a-z]+#i', '-', $string);
        $string = strtolower($string);
        $string = trim($string, '-');

        return $string;
    }

    /**
     * Build a URL from the result of PHP's parse_url()
     */
    public function buildUrl(array $parts): string
    {
        return
            (isset($parts['scheme']) ? $parts['scheme'] . '://' : '') .
            ($parts['user'] ?? '') .
            (isset($parts['pass']) ? ':' . $parts['pass'] : '') .
            ((isset($parts['user']) || isset($parts['pass'])) ? '@' : '') .
            ($parts['host'] ?? '') .
            (isset($parts['port']) ? ':' . $parts['port'] : '') .
            ($parts['path'] ?? '/') .
            (isset($parts['query']) ? '?' . $parts['query'] : '') .
            (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    /**
     * Add, update, or remove multiple varien route params in URL
     */
    public function setRouteParams(string $url, array $params = []): string
    {
        $parts = parse_url($url);
        $parts['path'] ??= '/';

        $noTrailingSlash = !str_ends_with($parts['path'], '/');
        if ($noTrailingSlash) {
            $parts['path'] .= '/';
        }

        foreach ($params as $key => $val) {
            $regex = "/\/{$key}\/(.*?)\//";
            if ($val === null || $val === false) {
                $parts['path'] = preg_replace($regex, '/', $parts['path']);
            } elseif (preg_match($regex, $parts['path'])) {
                $parts['path'] = preg_replace($regex, "/{$key}/{$val}/", $parts['path']);
            } else {
                $parts['path'] .= "{$key}/{$val}/";
            }
        }

        if ($noTrailingSlash && $parts['path'] !== '/') {
            $parts['path'] = rtrim($parts['path'], '/');
        }

        return $this->buildUrl($parts);
    }

    /**
     * Add or update varien route param in URL
     */
    public function addRouteParam(string $url, string $paramKey, mixed $value): string
    {
        return $this->setRouteParams($url, [$paramKey => $value]);
    }

    /**
     * Remove varien route param from URL
     */
    public function removeRouteParam(string $url, string $paramKey): string
    {
        return $this->setRouteParams($url, [$paramKey => null]);
    }

    /**
     * Add query parameter into url
     *
     * @param string $url
     * @param array $param ( 'key' => value )
     * @return string
     */
    public function addRequestParam($url, $param)
    {
        $startDelimiter = (str_contains($url, '?')) ? '&' : '?';

        $arrQueryParams = [];
        foreach ($param as $key => $value) {
            if (is_numeric($key) || is_object($value)) {
                continue;
            }

            if (is_array($value)) {
                $arrQueryParams[] = $key . '[]=' . implode('&' . $key . '[]=', $value);
            } elseif (is_null($value)) {
                $arrQueryParams[] = $key;
            } else {
                $arrQueryParams[] = $key . '=' . $value;
            }
        }
        $url .= $startDelimiter . implode('&', $arrQueryParams);

        return $url;
    }

    /**
     * Remove query parameter from url
     *
     * @param string $url
     * @param string $paramKey
     * @param bool $caseSensitive
     * @return string
     */
    public function removeRequestParam($url, $paramKey, $caseSensitive = false)
    {
        if (!str_contains($url, '?')) {
            return $url;
        }

        [$baseUrl, $query] = explode('?', $url, 2);
        parse_str($query, $params);

        if (!$caseSensitive) {
            $paramsLower = array_change_key_case($params);
            $paramKeyLower = strtolower($paramKey);

            if (array_key_exists($paramKeyLower, $paramsLower)) {
                $params[$paramKey] = $paramsLower[$paramKeyLower];
            }
        }

        if (array_key_exists($paramKey, $params)) {
            unset($params[$paramKey]);
        }

        return $baseUrl . ($params === [] ? '' : '?' . http_build_query($params));
    }

    /**
     * Add trailing slash to URL
     */
    public function addTrailingSlash(string $url): string
    {
        // Parse URL and remove all trailing slashes from path
        $parts = parse_url($url);
        $parts['path'] = rtrim($parts['path'] ?? '', '/');

        // Only add trailing slashes for pages without an extension
        if (pathinfo($parts['path'], PATHINFO_EXTENSION) === '') {
            $parts['path'] .= '/';
        }

        return $this->buildUrl($parts);
    }

    /**
     * Remove trailing slash from URL
     */
    public function removeTrailingSlash(string $url): string
    {
        // Parse URL and remove all trailing slashes from path
        $parts = parse_url($url);
        $parts['path'] = rtrim($parts['path'] ?? '', '/');

        // Add a trailing slash to the root domain
        if ($parts['path'] === '') {
            $parts['path'] .= '/';
        }

        return $this->buildUrl($parts);
    }

    /**
     * Add or remove trailing slash from URL based on store config
     */
    public function addOrRemoveTrailingSlash(string $url): string
    {
        if (!Mage::isInstalled()) {
            return $url;
        }

        if (Mage::helper('adminhtml')->isAdminFrontNameMatched($url)) {
            return $url;
        }

        $mode = Mage::getStoreConfig('web/url/trailing_slash_behavior');
        if ($mode === Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash::REMOVE_TRAILING_SLASH) {
            return $this->removeTrailingSlash($url);
        }

        if ($mode === Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash::ADD_TRAILING_SLASH) {
            return $this->addTrailingSlash($url);
        }
        return $url;
    }

    /**
     * Retrieve encoding domain name in punycode
     *
     * @param string $url encode url to Punycode
     * @return string
     */
    public function encodePunycode($url)
    {
        $parsedUrl = parse_url($url);
        if (!$this->_isPunycode($parsedUrl['host'])) {
            $host = idn_to_ascii($parsedUrl['host']);
            return str_replace($parsedUrl['host'], $host, $url);
        }

        return $url;
    }

    /**
     * Retrieve decoding domain name from punycode
     *
     * @param string $url decode url from Punycode
     * @return string
     * @throws Exception
     */
    public function decodePunycode($url)
    {
        $parsedUrl = parse_url($url);
        if ($this->_isPunycode($parsedUrl['host'])) {
            $host = idn_to_utf8($parsedUrl['host']);
            return str_replace($parsedUrl['host'], $host, $url);
        }

        return $url;
    }

    /**
     * Check domain name for IDN using ACE prefix http://tools.ietf.org/html/rfc3490#section-5
     *
     * @param string $host domain name
     * @return bool
     */
    private function _isPunycode(string $host)
    {
        if (str_starts_with($host, 'xn--') || str_contains($host, '.xn--')
            || str_starts_with($host, 'XN--') || str_contains($host, '.XN--')
        ) {
            return true;
        }
        return false;
    }
}
