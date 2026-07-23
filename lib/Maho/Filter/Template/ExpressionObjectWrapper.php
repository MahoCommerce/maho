<?php

/**
 * Wraps a DataObject exposed to ExpressionLanguage template conditions.
 *
 * Forwards property reads and method calls to the wrapped object, re-wrapping DataObject
 * results so the guard applies to the whole object graph. getConfig() calls against
 * encrypted configuration paths are neutralized (called with null instead), mirroring the
 * protection the legacy variable resolver applies.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Filter\Template;

use Maho\DataObject;

class ExpressionObjectWrapper
{
    public function __construct(private readonly DataObject $object) {}

    public static function wrap(mixed $value): mixed
    {
        return $value instanceof DataObject ? new self($value) : $value;
    }

    public function __call(string $method, array $args): mixed
    {
        if (strcasecmp($method, 'getConfig') === 0 && $this->isEncryptedConfigPath($args)) {
            $args = [null];
        }
        return self::wrap($this->object->$method(...$args));
    }

    public function __get(string $name): mixed
    {
        // Same resolution order as the legacy variable resolver: a real getter method
        // wins over raw data access, so "order.status_label" keeps calling getStatusLabel()
        $getter = 'get' . uc_words($name, '');
        if (method_exists($this->object, $getter)) {
            return self::wrap($this->object->{$getter}());
        }
        return self::wrap($this->object->getData($name));
    }

    private function isEncryptedConfigPath(array $args): bool
    {
        /** @var \Mage_Adminhtml_Model_Email_PathValidator $validator */
        $validator = \Mage::getModel('adminhtml/email_pathValidator');
        return $validator->isValid($args);
    }
}
