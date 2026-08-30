<?php

/**
 * The OAuth consent screen, and the grid of clients an admin has approved.
 *
 * Consent lives in the admin area so it inherits admin login, two-factor
 * authentication, lockout and login logging. Building a second login form for
 * OAuth would bypass all four.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Adminhtml_Apiplatform_OauthController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/api/oauth_clients';

    /**
     * The consent screen is reached from an external client, which cannot know
     * the per-action secret key. The approval itself is a POST and is protected
     * by the admin form key.
     *
     * @var array
     */
    protected $_publicActions = ['authorize'];

    /**
     * Render the approval screen, or complete an approval.
     *
     * The request itself arrives in the session, parked by
     * OAuthController::authorize. It is not in the URL on purpose:
     * Mage_Admin_Model_Redirectpolicy sends a visitor to the dashboard when the
     * URL they logged in at carries any parameter, so a parameter-free consent
     * URL is what survives the login round trip.
     */
    #[Maho\Config\Route('/admin/apiplatform_oauth/authorize', methods: ['GET', 'POST'])]
    public function authorizeAction(): void
    {
        if (!$this->helper()->isAuthorizationServerEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $decision = (string) $this->getRequest()->getPost('decision', '');
        $secret = (string) $this->getRequest()->getCookie(Maho_ApiPlatform_Model_Oauth_Server::PENDING_REQUEST_COOKIE, '');

        // Consume once the decision is in: an approval or a denial ends the request.
        $request = $this->server()->readPendingRequest($secret, consume: $decision !== '');
        if ($request === null) {
            $this->renderConsentError(
                new Maho_ApiPlatform_Model_Oauth_Exception(
                    Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REQUEST,
                    (string) $this->__('This authorization request expired or is no longer valid. Start again from the application.'),
                    redirectable: false,
                ),
                '',
                '',
            );
            return;
        }

        // An admin who may not approve connections must not be able to mint a
        // token, even one limited to their own ACL.
        if (!$this->_isAllowed()) {
            $this->denyAuthorization($request['redirect_uri'], $request['state'], (string) $this->__('Your admin role may not approve API connections.'));
            return;
        }

        $adminId = (int) Mage::getSingleton('admin/session')->getUser()->getId();

        if ($decision !== '') {
            if (!$this->_validateFormKey()) {
                $this->renderConsentError(
                    new Maho_ApiPlatform_Model_Oauth_Exception(
                        Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REQUEST,
                        (string) $this->__('Invalid Form Key. Please refresh the page.'),
                        redirectable: false,
                    ),
                    $request['redirect_uri'],
                    $request['state'],
                );
                return;
            }

            if ($decision === 'approve') {
                $this->approve($request, $adminId);
                return;
            }

            $this->denyAuthorization($request['redirect_uri'], $request['state'], (string) $this->__('You denied the connection.'));
            return;
        }

        // A client the admin already approved for this exact scope and resource
        // does not ask again. A change in either brings the screen back.
        $consentId = $this->server()->findExistingConsentId(
            (string) $request['client']->getData('client_id'),
            $adminId,
            $request['scope'],
            $request['resource'],
        );

        if ($consentId !== null) {
            $this->server()->readPendingRequest($secret, consume: true);
            $this->approve($request, $adminId);
            return;
        }

        $this->_title($this->__('Authorize Application'));
        $this->loadLayout();

        /** @var Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Oauth_Consent $block */
        $block = $this->getLayout()->getBlock('apiplatform.oauth.consent');
        $block?->setAuthorizationRequest($request);

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/apiplatform_oauth/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Connected Applications'));
        $this->loadLayout()
            ->_setActiveMenu('system/api/oauth_clients')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('Connected Applications'), $this->__('Connected Applications'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/apiplatform_oauth/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Revoke every grant the selected clients hold. The next call with one of
     * their tokens fails, and the next authorization attempt asks for consent
     * again.
     */
    #[Maho\Config\Route('/admin/apiplatform_oauth/revoke', methods: ['POST'])]
    public function revokeAction(): void
    {
        $clientIds = $this->getRequest()->getPost('client_ids', $this->getRequest()->getPost('client_id', []));
        $clientIds = array_filter(array_map(strval(...), (array) $clientIds), fn(string $id): bool => $id !== '');

        if ($clientIds === []) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select an application.'));
            $this->_redirect('*/*/');
            return;
        }

        $revoked = 0;
        foreach ($clientIds as $clientId) {
            $revoked += $this->tokenResource()->revokeClientGrants($clientId);
        }

        Mage::getSingleton('adminhtml/session')->addSuccess(
            $this->__('Revoked %d grant(s). Access tokens already issued stop working when they expire.', $revoked),
        );

        $this->_redirect('*/*/');
    }

    /**
     * @param array{client: Maho_ApiPlatform_Model_Oauth_Client, redirect_uri: string, scope: string, resource: string, code_challenge: string, state: string} $request
     */
    protected function approve(array $request, int $adminId): void
    {
        try {
            $code = $this->server()->issueAuthorizationCode($request, $adminId);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->renderConsentError(
                new Maho_ApiPlatform_Model_Oauth_Exception(
                    Maho_ApiPlatform_Model_Oauth_Exception::ERROR_SERVER_ERROR,
                    (string) $this->__('The authorization could not be completed.'),
                ),
                $request['redirect_uri'],
                $request['state'],
            );
            return;
        }

        $query = ['code' => $code->getPlainToken()];
        if ($request['state'] !== '') {
            $query['state'] = $request['state'];
        }

        $this->redirectToClient($request['redirect_uri'], $query);
    }

    protected function denyAuthorization(string $redirectUri, string $state, string $message): void
    {
        $query = [
            'error' => Maho_ApiPlatform_Model_Oauth_Exception::ERROR_ACCESS_DENIED,
            'error_description' => $message,
        ];
        if ($state !== '') {
            $query['state'] = $state;
        }

        $this->redirectToClient($redirectUri, $query);
    }

    /**
     * An error only travels to a redirect URI that already matched the client's
     * registered list. Before that it is rendered, because sending anything to
     * an unverified URI is an open redirect.
     */
    protected function renderConsentError(Maho_ApiPlatform_Model_Oauth_Exception $e, string $redirectUri, string $state): void
    {
        if ($e->isRedirectable() && $redirectUri !== '') {
            $this->denyAuthorization($redirectUri, $state, $e->getDescription());
            return;
        }

        $this->getResponse()
            ->setHttpResponseCode($e->getHttpStatus())
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8', true)
            ->setBody($e->getError() . ': ' . $e->getDescription());
    }

    /**
     * @param array<string, string> $query
     */
    protected function redirectToClient(string $redirectUri, array $query): void
    {
        // The hand-off is over either way, so the cookie must not outlive it.
        Mage::getSingleton('core/cookie')->delete(Maho_ApiPlatform_Model_Oauth_Server::PENDING_REQUEST_COOKIE);

        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        $this->getResponse()->setRedirect($redirectUri . $separator . http_build_query($query));
    }

    protected function server(): Maho_ApiPlatform_Model_Oauth_Server
    {
        /** @var Maho_ApiPlatform_Model_Oauth_Server $server */
        $server = Mage::getSingleton('apiplatform/oauth_server');
        return $server;
    }

    protected function tokenResource(): Maho_ApiPlatform_Model_Resource_Oauth_Token
    {
        /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $resource */
        $resource = Mage::getResourceSingleton('apiplatform/oauth_token');
        return $resource;
    }

    protected function helper(): Maho_ApiPlatform_Helper_Data
    {
        /** @var Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('apiplatform');
        return $helper;
    }
}
