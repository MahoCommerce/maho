<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RateLimiter
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? \Mage::getBaseDir('var') . '/cache/rate_limit';
    }

    public function check(string $key, int $maxAttempts, int $windowSeconds): void
    {
        $dir = $this->cacheDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $safeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
        $file = $dir . '/' . $safeKey . '.json';
        $now = time();

        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return; // Fail open if file can't be created
        }

        flock($fp, LOCK_EX);

        try {
            $data = stream_get_contents($fp);
            $attempts = $data ? (json_decode($data, true) ?? []) : [];
            $attempts = array_values(array_filter($attempts, fn(int $ts) => $ts > $now - $windowSeconds));

            if (count($attempts) >= $maxAttempts) {
                $retryAfter = $windowSeconds - ($now - $attempts[0]);
                throw new TooManyRequestsHttpException(
                    (string) max(1, $retryAfter),
                    'Too many requests. Please try again later.',
                );
            }

            $attempts[] = $now;

            fseek($fp, 0);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($attempts));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function cleanup(int $maxAge = 7200): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }
        $now = time();
        foreach (glob($this->cacheDir . '/*.json') as $file) {
            if ($now - filemtime($file) > $maxAge) {
                @unlink($file);
            }
        }
    }
}
