<?php

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;

class CacheMarshaller implements MarshallerInterface
{
    public function marshall(array $values, ?array &$failed): array // @phpstan-ignore parameterByRef.unusedType
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
