<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Records cookie writes instead of emitting them, so a test can assert which
 * code paths touch the client. Cookie::delete() routes through set() in
 * production, so deletes are recorded separately to keep the two apart.
 */
class RecordingCookie extends \Mage_Core_Model_Cookie
{
    /** @var list<string> */
    public array $writes = [];

    /** @var list<string> */
    public array $deletes = [];

    #[\Override]
    public function set($name, $value, $period = null, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        $this->writes[] = (string) $name;
        return $this;
    }

    #[\Override]
    public function delete($name, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        $this->deletes[] = (string) $name;
        return $this;
    }
}
