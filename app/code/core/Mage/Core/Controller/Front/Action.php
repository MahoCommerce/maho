<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Controller_Front_Action extends Mage_Core_Controller_Varien_Action
{
    /**
     * Session constants to refer in other places
     *
     * Max lifetime is set to 400 days, as chromium based browsers will not allow anything higher
     * https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-cookie-lifetime-limits
     */
    public const SESSION_NAMESPACE = 'maho_session';
    public const SESSION_LEGACY_NAMESPACES = ['om_frontend', 'frontend'];
    public const SESSION_MIN_LIFETIME = 60 * 60;
    public const SESSION_MAX_LIFETIME = 60 * 60 * 24 * 400;

    /**
     * Actions of this controller that run without a form key check.
     *
     * Put an action here only when a third party must reach it: a payment webhook, an OAuth
     * endpoint, an unsubscribe link. Every other action that changes state keeps the check.
     *
     * @var string[]
     */
    protected $_publicActions = [];

    /**
     * Currently used area
     *
     * @var string
     */
    #[\Override]
    protected $_currentArea = Mage_Core_Model_App_Area::AREA_FRONTEND;

    /**
     * Namespace for session.
     *
     * @var string
     */
    #[\Override]
    protected $_sessionNamespace = self::SESSION_NAMESPACE;

    /**
     * Predispatch: should set layout area
     *
     * @return $this
     */
    #[\Override]
    public function preDispatch()
    {
        $this->getLayout()->setArea($this->_currentArea);

        parent::preDispatch();

        if ($this->getRequest()->isDispatched()
            && !$this->getFlag('', self::FLAG_NO_DISPATCH)
            && $this->_isFormKeyRequired()
            && !$this->_validateFormKey()
        ) {
            $this->_rejectInvalidFormKey();
        }

        return $this;
    }

    /**
     * Tell if the current request must carry a valid form key.
     */
    protected function _isFormKeyRequired(): bool
    {
        // A safe method needs no form key, unless the route of the action refuses that method
        $request = $this->getRequest();
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $route = \Maho\Routing\RouteCollectionBuilder::resolveRoute(
                (string) $request->getModuleName(),
                (string) $request->getControllerName(),
                (string) $request->getActionName(),
            );
            $method = $request->getMethod() === 'HEAD' ? 'GET' : $request->getMethod();
            if ($route === null || $route['methods'] === [] || !$this instanceof $route['class']
                || in_array($method, $route['methods'], true)
            ) {
                return false;
            }
        }

        // Without a session there is no form key to compare with
        if ($this->getFlag('', self::FLAG_NO_START_SESSION)) {
            return false;
        }

        return !in_array($this->getRequest()->getActionName(), $this->_publicActions, true);
    }

    /**
     * Stop the action and answer that the form key is wrong.
     */
    protected function _rejectInvalidFormKey(): void
    {
        $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);

        $message = Mage::helper('core')->__('Invalid form key. Please refresh the page.');

        if ($this->getRequest()->isAjax()) {
            $this->getResponse()
                ->setHttpResponseCode(403)
                ->setBodyJson(['error' => true, 'message' => $message]);
            return;
        }

        Mage::getSingleton('core/session')->addError($message);

        // Only the header, never a referer request parameter: an attacker controls the body
        $refererUrl = (string) $this->getRequest()->getServer('HTTP_REFERER');
        if ($refererUrl === '' || !$this->_isUrlInternal($refererUrl)) {
            $refererUrl = Mage::getBaseUrl();
        }
        $this->getResponse()->setRedirect($refererUrl);
    }

    /**
     * Postdispatch: should set last visited url
     *
     * @return $this
     */
    #[\Override]
    public function postDispatch()
    {
        parent::postDispatch();
        if (!$this->getFlag('', self::FLAG_NO_START_SESSION)) {
            Mage::getSingleton('core/session')->setLastUrl(Mage::getUrl('*/*/*', ['_current' => true]));
        }
        return $this;
    }

    /**
     * Translate a phrase
     *
     * @return string
     */
    public function __()
    {
        $args = func_get_args();
        $expr = new Mage_Core_Model_Translate_Expr(array_shift($args), $this->_getRealModuleName());
        array_unshift($args, $expr);
        return Mage::app()->getTranslator()->translate($args);
    }

    /**
     * Declare headers and content file in response for file download
     *
     * @param string $fileName
     * @param string|array $content set to null to avoid starting output, $contentLength should be set explicitly in
     *                              that case
     * @param string $contentType
     * @param int $contentLength    explicit content length, if strlen($content) isn't applicable
     * @return $this
     */
    #[\Override]
    protected function _prepareDownloadResponse(
        $fileName,
        $content,
        $contentType = 'application/octet-stream',
        $contentLength = null,
    ) {
        $session = Mage::getSingleton('admin/session');
        if ($session->isFirstPageAfterLogin()) {
            $this->_redirect($session->getUser()->getStartupPageUrl());
            return $this;
        }

        $isFile = false;
        $file   = null;
        if (is_array($content)) {
            if (!isset($content['type']) || !isset($content['value'])) {
                return $this;
            }
            if ($content['type'] == 'filename') {
                $isFile         = true;
                $file           = $content['value'];
                $contentLength  = filesize($file);
            }
        }

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', $contentType, true)
            ->setHeader('Content-Length', (string) ($contentLength ?? strlen($content)))
            ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->setHeader('Last-Modified', date('r'));

        if (!is_null($content)) {
            if ($isFile) {
                $this->getResponse()->clearBody();
                $this->getResponse()->sendHeaders();

                $ioAdapter = new \Maho\Io\File();
                if (!$ioAdapter->fileExists($file)) {
                    Mage::throwException(Mage::helper('core')->__('File not found'));
                }
                $ioAdapter->open(['path' => $ioAdapter->dirname($file)]);
                $ioAdapter->streamOpen($file, 'r');
                while ($buffer = $ioAdapter->streamRead()) {
                    print $buffer;
                }
                $ioAdapter->streamClose();
                if (!empty($content['rm'])) {
                    $ioAdapter->rm($file);
                }

                exit(0);
            }
            $this->getResponse()->setBody($content);
        }
        return $this;
    }

    /**
     * Check if form key validation is enabled.
     *
     * @return bool
     */
    #[\Deprecated(message: 'since 25.5.0')]
    protected function _isFormKeyEnabled()
    {
        return true;
    }
}
