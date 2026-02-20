<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Cache\Marshaller\MarshallerInterface;

class Mage_Core_Model_Cache_Marshaller implements MarshallerInterface
{
    /**
     * @param-out array<int, int|string> $failed
     */
    #[\Override]
    public function marshall(array $values, ?array &$failed): array
    {
        $serialized = $failed = [];
        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = serialize($value);
            } catch (\Throwable $e) {
                $failed[] = $id;
            }
        }

        return $serialized;
    }

    #[\Override]
    public function unmarshall(string $value): mixed
    {
        if ('b:0;' === $value) {
            return false;
        }
        if ('N;' === $value) {
            return null;
        }

        if (false !== $value = unserialize($value, ['allowed_classes' => false])) {
            return $value;
        }

        throw new \Exception(error_get_last() ? error_get_last()['message'] : 'Failed to unserialize values.');
    }
}
