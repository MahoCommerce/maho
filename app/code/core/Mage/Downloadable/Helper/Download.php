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

    /**
     * Seconds that a signed URL of a remote downloadable mount stays valid. Any client can use the URL
     * until then, with no download limit, so the lifetime only gives the transfer time to start.
     */
    public const TEMPORARY_URL_LIFETIME = 60;

    #[\Override]
    protected $_moduleName = 'Mage_Downloadable';

    /**
     * Type of link
     *
     * @var string
     */
    protected $_linkType        = self::LINK_TYPE_FILE;

    /**
     * Resource file: a URL, or for a file link its path on the downloadable mount
     *
     * @var string
     */
    protected $_resourceFile    = null;

    /**
     * Resource open handle
     *
     * @var resource|null
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

    protected bool $resourceChecked = false;

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
                    $target = new \Maho\Security\OutboundUrl()
                        ->validate($this->_resourceFile, $this->getLinkUrlAllowedPrefixes());
                } catch (\Maho\Security\OutboundUrlException $e) {
                    Mage::log(sprintf('Refused download URL on host %s: %s', parse_url($this->_resourceFile, PHP_URL_HOST), $e->getMessage()), Mage::LOG_WARNING);
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
                try {
                    $this->_handle = $this->getMount()->readStream($this->_resourceFile);
                } catch (\League\Flysystem\FilesystemException) {
                    Mage::throwException(Mage::helper('downloadable')->__('The file does not exist.'));
                }
            } else {
                Mage::throwException(Mage::helper('downloadable')->__('Invalid download link type.'));
            }
        }
        return $this->_handle;
    }

    /**
     * Check that the resource exists without a transfer: a file link checks the mount, and a
     * URL link reads the response headers.
     */
    protected function _prepareResource(): void
    {
        if ($this->_linkType != self::LINK_TYPE_FILE) {
            $this->_getHandle();
            return;
        }
        if (!$this->_resourceFile) {
            Mage::throwException(Mage::helper('downloadable')->__('Please set resource file and link type.'));
        }
        if (!$this->resourceChecked) {
            if (!$this->getMount()->fileExists($this->_resourceFile)) {
                Mage::throwException(Mage::helper('downloadable')->__('The file does not exist.'));
            }
            $this->resourceChecked = true;
        }
    }

    /**
     * Retrieve file size in bytes
     */
    public function getFilesize()
    {
        $this->_prepareResource();
        if ($this->_linkType == self::LINK_TYPE_FILE) {
            return $this->getMount()->fileSize($this->_resourceFile);
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
        $this->_prepareResource();
        if ($this->_linkType == self::LINK_TYPE_FILE) {
            $extension = pathinfo($this->_resourceFile, PATHINFO_EXTENSION);

            // A type that global/mime/types declares for this extension is more exact than
            // content sniffing, which reads a container format as zip.
            if ($configured = Mage::helper('core')->getConfiguredMimeType($extension)) {
                return $configured;
            }
            try {
                return $this->getMount()->mimeType($this->_resourceFile);
            } catch (\League\Flysystem\FilesystemException) {
                return Mage::helper('downloadable/file')->getFileType($this->_resourceFile);
            }
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
        $this->_prepareResource();
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
     * A file link takes its path on the downloadable mount, such as files/links/m/a/manual.pdf.
     * An absolute path inside the local media/downloadable directory, as earlier releases passed, still works.
     *
     * @param string $resourceFile
     * @param string $linkType
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function setResource($resourceFile, $linkType = self::LINK_TYPE_FILE)
    {
        if (self::LINK_TYPE_FILE == $linkType) {
            $legacyDir = Mage::getBaseDir('media') . DS . 'downloadable';
            $localPath = \Maho\Io::getPathWithinDir($legacyDir, (string) $resourceFile);
            if ($localPath !== null && str_starts_with((string) $resourceFile, $legacyDir . DS)) {
                $resourceFile = substr($localPath, strlen($legacyDir) + 1);
            }
            $resourceFile = \Maho\Io::getPathWithinMount($this->getMount(), '', (string) $resourceFile);
            if ($resourceFile === null) {
                Mage::throwException(
                    Mage::helper('downloadable')->__('Invalid file path.'),
                );
            }
        }

        $this->_resourceFile    = $resourceFile;
        $this->_linkType        = $linkType;
        $this->_handle          = null;
        $this->_urlHeaders      = [];
        $this->resourceChecked  = false;

        return $this;
    }

    /**
     * A signed URL of the file on a remote downloadable mount that supports one, such as S3, so the
     * client downloads from the bucket and not through PHP. Null for a URL link or a local disk.
     */
    public function getTemporaryUrl(mixed $store = null): ?string
    {
        if ($this->_linkType != self::LINK_TYPE_FILE || !$this->getMount()->supportsTemporaryUrls()) {
            return null;
        }

        $contentType = (string) $this->getContentType();
        $disposition = $this->getContentDisposition($store);
        $disposition = $disposition ? $disposition . '; filename="' . addcslashes((string) $this->getFilename(), '"\\') . '"' : null;

        return $this->getMount()->temporaryUrl(
            $this->_resourceFile,
            new DateTimeImmutable('+' . self::TEMPORARY_URL_LIFETIME . ' seconds'),
            [
                'get_object_options' => array_filter([
                    'ResponseContentType' => $contentType,
                    'ResponseContentDisposition' => $disposition,
                ]),
                'gcp_signing_options' => array_filter([
                    'responseType' => $contentType,
                    'responseDisposition' => $disposition,
                ]),
            ],
        );
    }

    protected function getMount(): \Maho\Storage\Mount
    {
        return Mage::getStorage('downloadable');
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
            fpassthru($handle);
            fclose($handle);
            $this->_handle = null;
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
