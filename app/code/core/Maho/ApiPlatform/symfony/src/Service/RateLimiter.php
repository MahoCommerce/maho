<?php

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
        $attempts = [];

        if (file_exists($file)) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $attempts = $decoded;
                }
            }
        }

        $attempts = array_values(array_filter($attempts, fn(int $ts) => $ts > $now - $windowSeconds));

        if (count($attempts) >= $maxAttempts) {
            $retryAfter = $windowSeconds - ($now - $attempts[0]);
            throw new TooManyRequestsHttpException(
                (string) max(1, $retryAfter),
                'Too many requests. Please try again later.',
            );
        }

        $attempts[] = $now;

        $tmpFile = $file . '.' . getmypid() . '.tmp';
        file_put_contents($tmpFile, json_encode($attempts));
        rename($tmpFile, $file);
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
