<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

class TokenBlacklist
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? \Mage::getBaseDir('var') . '/cache/token_blacklist';
    }

    public function revoke(string $jti, int $expiresAt): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }

        $file = $this->dir . '/' . preg_replace('/[^a-f0-9]/', '', $jti);
        $tmpFile = $file . '.' . getmypid() . '.tmp';
        file_put_contents($tmpFile, (string) $expiresAt);
        rename($tmpFile, $file);
    }

    public function isRevoked(string $jti): bool
    {
        $file = $this->dir . '/' . preg_replace('/[^a-f0-9]/', '', $jti);
        if (!file_exists($file)) {
            return false;
        }

        $expiresAt = (int) @file_get_contents($file);
        if ($expiresAt > 0 && $expiresAt < time()) {
            @unlink($file);
            return false;
        }

        return true;
    }

    public function cleanup(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        $now = time();
        foreach (glob($this->dir . '/*') as $file) {
            if (is_file($file)) {
                $expiresAt = (int) @file_get_contents($file);
                if ($expiresAt > 0 && $expiresAt < $now) {
                    @unlink($file);
                }
            }
        }
    }
}
