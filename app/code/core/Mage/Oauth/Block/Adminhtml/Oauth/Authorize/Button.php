<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

declare(strict_types=1);

class Mage_Oauth_Block_Adminhtml_Oauth_Authorize_Button extends Mage_Oauth_Block_Authorize_ButtonBaseAbstract
{
    /**
     * Build confirm/reject urls through the admin url model so they carry the per-action secret
     * key. This button renders inside the logged-in admin session, so the state-changing confirm
     * and reject actions can validate that key instead of being exempted as public.
     */
    #[\Override]
    protected function _getUrlModelClass()
    {
        return 'adminhtml/url';
    }

    /**
     * Retrieve confirm authorization url path
     *
     * @return string
     */
    #[\Override]
    public function getConfirmUrlPath()
    {
        return 'adminhtml/oauth_authorize/confirm';
    }

    /**
     * Retrieve reject authorization url path
     *
     * @return string
     */
    #[\Override]
    public function getRejectUrlPath()
    {
        return 'adminhtml/oauth_authorize/reject';
    }
}
