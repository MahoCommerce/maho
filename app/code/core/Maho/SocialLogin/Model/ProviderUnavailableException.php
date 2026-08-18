<?php

/**
 * Transient provider failure (JWKS or Graph API unreachable), distinct from an
 * invalid credential so callers can answer with a retryable status.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_ProviderUnavailableException extends Mage_Core_Exception {}
