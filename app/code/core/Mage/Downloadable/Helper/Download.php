<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_Helper_Download extends Mage_Core_Helper_Abstract
{
    public const LINK_TYPE_URL         = 'url';
    public const LINK_TYPE_FILE        = 'file';

    public const XML_PATH_CONTENT_DISPOSITION  = 'catalog/downloadable/content_disposition';
    public const XML_PATH_LINK_URL_ALLOWED_PREFIXES = 'catalog/downloadable/link_url_allowed_prefixes';

    protected $_moduleName = 'Mage_Downloadable';

    /**
     * Type of link
     *
     * @var string
     */
    protected $_linkType        = self::LINK_TYPE_FILE;

    /**
     * Resource file
     *
     * @var string
     */
    protected $_resourceFile    = null;

    /**
     * Resource open handle
     *
     * @var resource|\Maho\Io\File|null
     */
    protected $_handle          = null;

    /**
     * Remote server headers
     *
     * @var array
     */
    protected $_urlHeaders      = [];

    /**
     * MIME Content-type for a file
     *
     * @var string
     */
    protected $_contentType     = 'application/octet-stream';

    /**
     * File name
     *
     * @var string
     */
    protected $_fileName        = 'download';

    /**
     * Retrieve Resource file handle (socket, file pointer etc)
     *
     * @return resource
     */
    protected function _getHandle()
    {
        if (!$this->_resourceFile) {
            Mage::throwException(Mage::helper('downloadable')->__('Please set resource file and link type.'));
        }

        if (is_null($this->_handle)) {
            if ($this->_linkType == self::LINK_TYPE_URL) {
                try {
                    $target = (new \Maho\Security\OutboundUrl())
                        ->validate($this->_resourceFile, $this->getLinkUrlAllowedPrefixes());
                } catch (\Maho\Security\OutboundUrlException $e) {
                    Mage::log(sprintf('Refused download URL %s: %s', $this->_resourceFile, $e->getMessage()), Mage::LOG_WARNING);
                    Mage::throwException(Mage::helper('downloadable')->__('The download URL is not allowed.'));
                }

                $context = stream_context_create(['ssl' => $target->sslOptions()]);
                $this->_handle = @stream_socket_client($target->socketAddress(), $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

                if ($this->_handle === false) {
                    Mage::throwException(Mage::helper('downloadable')->__('Cannot connect to remote host, error: %s.', $errstr));
                }

                $headers = 'GET ' . $target->requestTarget() . ' HTTP/1.0' . "\r\n"
                    . 'Host: ' . $target->hostHeader() . "\r\n"
                    . 'User-Agent: Maho ver/' . Mage::getVersion() . "\r\n"
                    . 'Connection: close' . "\r\n"
                    . "\r\n";
                fwrite($this->_handle, $headers);

                while (!feof($this->_handle)) {
                    $str = fgets($this->_handle, 1024);
                    if ($str == "\r\n") {
                        break;
                    }
                    $match = [];
                    if (preg_match('#^([^:]+): (.*)\s+$#', $str, $match)) {
                        $k = strtolower($match[1]);
                        if ($k == 'set-cookie') {
                            continue;
                        }
                        $this->_urlHeaders[$k] = trim($match[2]);
                    } elseif (preg_match('#^HTTP/[0-9\.]+ (\d+) (.*)\s$#', $str, $match)) {
                        $this->_urlHeaders['code'] = $match[1];
                        $this->_urlHeaders['code-string'] = trim($match[2]);
                    }
                }

                if (!isset($this->_urlHeaders['code']) || $this->_urlHeaders['code'] != 200) {
                    Mage::throwException(Mage::helper('downloadable')->__('An error occurred while getting the requested content. Please contact the store owner.'));
                }
            } elseif ($this->_linkType == self::LINK_TYPE_FILE) {
                $this->_handle = new \Maho\Io\File();
                if (!is_file($this->_resourceFile)) {
                    Mage::throwException(Mage::helper('downloadable')->__('The file does not exist.'));
                }
                $this->_handle->open(['path' => Mage::getBaseDir('var')]);
                if (!$this->_handle->fileExists($this->_resourceFile, true)) {
                    Mage::throwException(Mage::helper('downloadable')->__('The file does not exist.'));
                }
                $this->_handle->streamOpen($this->_resourceFile, 'r');
            } else {
                Mage::throwException(Mage::helper('downloadable')->__('Invalid download link type.'));
            }
        }
        return $this->_handle;
    }

    /**
     * Retrieve file size in bytes
     */
    public function getFilesize()
    {
        $handle = $this->_getHandle();
        if ($this->_linkType == self::LINK_TYPE_FILE) {
            return $handle->streamStat('size');
        }
        if ($this->_linkType == self::LINK_TYPE_URL) {
            if (isset($this->_urlHeaders['content-length'])) {
                return $this->_urlHeaders['content-length'];
            }
        }
        return null;
    }

    /**
     * @return array|string
     * @throws Exception
     */
    public function getContentType()
    {
        $handle = $this->_getHandle();
        if ($this->_linkType == self::LINK_TYPE_FILE) {
            if ($contentType = mime_content_type($this->_resourceFile)) {
                return $contentType;
            }
            return Mage::helper('downloadable/file')->getFileType($this->_resourceFile);
        }
        if ($this->_linkType == self::LINK_TYPE_URL) {
            if (isset($this->_urlHeaders['content-type'])) {
                $contentType = explode('; ', $this->_urlHeaders['content-type']);
                return $contentType[0];
            }
        }
        return $this->_contentType;
    }

    /**
     * @return bool|mixed|string
     * @throws Exception
     */
    public function getFilename()
    {
        $handle = $this->_getHandle();
        if ($this->_linkType == self::LINK_TYPE_FILE) {
            return pathinfo($this->_resourceFile, PATHINFO_BASENAME);
        }
        if ($this->_linkType == self::LINK_TYPE_URL) {
            if (isset($this->_urlHeaders['content-disposition'])) {
                $contentDisposition = explode('; ', $this->_urlHeaders['content-disposition']);
                if (!empty($contentDisposition[1]) && str_contains($contentDisposition[1], 'filename=')) {
                    return substr($contentDisposition[1], 9);
                }
            }
            if ($fileName = @pathinfo($this->_resourceFile, PATHINFO_BASENAME)) {
                return $fileName;
            }
        }
        return $this->_fileName;
    }

    /**
     * Set resource file for download
     *
     * @param string $resourceFile
     * @param string $linkType
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function setResource($resourceFile, $linkType = self::LINK_TYPE_FILE)
    {
        if (self::LINK_TYPE_FILE == $linkType) {
            // Validate file path is within allowed media directory
            $mediaDir = Mage::getBaseDir('media');
            if (!\Maho\Io::allowedPath($resourceFile, $mediaDir)) {
                Mage::throwException(
                    Mage::helper('downloadable')->__('Invalid file path.'),
                );
            }
        }

        $this->_resourceFile    = $resourceFile;
        $this->_linkType        = $linkType;

        return $this;
    }

    /**
     * Retrieve Http Request Object
     *
     * @return Mage_Core_Controller_Request_Http
     */
    public function getHttpRequest()
    {
        return Mage::app()->getFrontController()->getRequest();
    }

    /**
     * Retrieve Http Response Object
     *
     * @return Mage_Core_Controller_Response_Http
     */
    public function getHttpResponse()
    {
        return Mage::app()->getFrontController()->getResponse();
    }

    public function output()
    {
        $handle = $this->_getHandle();
        if ($this->_linkType == self::LINK_TYPE_FILE) {
            while ($buffer = $handle->streamRead()) {
                print $buffer;
            }
        } elseif ($this->_linkType == self::LINK_TYPE_URL) {
            while (!feof($handle)) {
                print fgets($handle, 1024);
            }
        }
    }

    /**
     * URL prefixes that may point to non-public hosts, one per line in the store config.
     *
     * @return list<string>
     */
    public function getLinkUrlAllowedPrefixes(mixed $store = null): array
    {
        $prefixes = preg_split('/\R/', (string) Mage::getStoreConfig(self::XML_PATH_LINK_URL_ALLOWED_PREFIXES, $store)) ?: [];
        return array_values(array_filter(array_map(trim(...), $prefixes)));
    }

    /**
     * Use Content-Disposition: attachment
     *
     * @param mixed $store
     * @return bool
     */
    public function getContentDisposition($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_CONTENT_DISPOSITION, $store);
    }
}
