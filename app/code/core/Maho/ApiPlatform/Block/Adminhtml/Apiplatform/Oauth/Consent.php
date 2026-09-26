<?php

/**
 * The approval screen an admin sees before a client is allowed to connect.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Oauth_Consent extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected $_template = 'apiplatform/oauth/consent.phtml';

    /**
     * @param array{client: Maho_ApiPlatform_Model_Oauth_Client, redirect_uri: string, scope: string, resource: string, code_challenge: string, state: string} $request
     */
    public function setAuthorizationRequest(array $request): self
    {
        $this->setData('authorization_request', $request);
        return $this;
    }

    public function getClient(): ?Maho_ApiPlatform_Model_Oauth_Client
    {
        $request = $this->getData('authorization_request');

        return is_array($request) ? $request['client'] : null;
    }

    public function getClientName(): string
    {
        return (string) $this->getClient()?->getData('client_name');
    }

    public function getRedirectUri(): string
    {
        return (string) ($this->getData('authorization_request')['redirect_uri'] ?? '');
    }

    public function getResource(): string
    {
        return (string) ($this->getData('authorization_request')['resource'] ?? '');
    }

    public function isUnverified(): bool
    {
        return $this->getClient()?->isTrusted() !== true;
    }

    /**
     * What the admin is being asked to hand over, in plain words. The token
     * carries the approving admin's own permissions, so that is what it says.
     *
     * @return array<string>
     */
    public function getGrantSummary(): array
    {
        return [
            (string) $this->__('Read and change store data through the API, using your own admin permissions.'),
            (string) $this->__('Act as you until you revoke this application, or your admin account is disabled.'),
        ];
    }

    public function getFormActionUrl(): string
    {
        return $this->getUrl('*/*/authorize');
    }

    /**
     * The approval binds the connection to this account, so the admin must see which one it is.
     */
    public function getAdminUsername(): string
    {
        return (string) Mage::getSingleton('admin/session')->getUser()?->getUsername();
    }

    public function getErrorMessage(): ?string
    {
        $value = $this->getData('error_message');
        return $value === null ? null : (string) $value;
    }

    public function setErrorMessage(?string $value): static
    {
        return $this->setData('error_message', $value);
    }
}
