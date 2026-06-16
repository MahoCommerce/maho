<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

/**
 * JWT token blacklist for logout/revocation, backed by a durable DB table.
 *
 * A DB store (not the Mage cache) is used deliberately: a routine cache flush
 * must not resurrect a revoked token. Expired rows are pruned opportunistically.
 */
class TokenBlacklist
{
    private const TABLE = 'maho_api_revoked_tokens';

    public function revoke(string $jti, int $expiresAt): void
    {
        if ($expiresAt - time() <= 0) {
            return;
        }

        $jti = self::normalizeJti($jti);
        if ($jti === null) {
            return;
        }

        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName(self::TABLE);

        // INSERT ... ON DUPLICATE so re-revoking a jti just refreshes expiry.
        $write->insertOnDuplicate(
            $table,
            ['jti' => $jti, 'expires_at' => $expiresAt],
            ['expires_at'],
        );
    }

    public function isRevoked(string $jti): bool
    {
        $jti = self::normalizeJti($jti);
        if ($jti === null) {
            // Reject malformed JTIs by treating them as revoked rather than
            // querying with a value that can never have been issued.
            return true;
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $table = $resource->getTableName(self::TABLE);

        $expiresAt = $read->fetchOne(
            $read->select()
                ->from($table, ['expires_at'])
                ->where('jti = ?', $jti),
        );

        if ($expiresAt === false || $expiresAt === null) {
            return false;
        }

        // A row past its expiry no longer needs to block the token; clean it up.
        if ((int) $expiresAt <= time()) {
            $this->purgeExpired();
            return false;
        }

        return true;
    }

    /**
     * Delete revocation rows whose tokens have already expired.
     */
    public function purgeExpired(): void
    {
        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName(self::TABLE);
        $write->delete($table, ['expires_at <= ?' => time()]);
    }

    /**
     * Normalize a JTI, or null when malformed. JwtService issues hex JTIs
     * (`bin2hex(random_bytes(16))`), so anything else is rejected.
     */
    private static function normalizeJti(string $jti): ?string
    {
        if ($jti === '' || !preg_match('/^[a-f0-9]+$/i', $jti)) {
            return null;
        }
        return strtolower($jti);
    }
}
