<?php

/**
 * Wraps an object exposed to ExpressionLanguage template conditions.
 *
 * Forwards property reads and read-only method calls to the wrapped object, re-wrapping
 * every object and array element in the result so the guard follows the whole object graph.
 * Methods that are not obviously read-only (setters, save(), delete(), ...) are refused:
 * evaluating a rendering condition must not mutate anything. getConfig() calls against
 * encrypted configuration paths are neutralized (called with null instead), mirroring the
 * protection the legacy variable resolver applies, and any read that resolves to a secret
 * field (password, card data, tokens, ...) is refused outright.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Filter\Template;

use Maho\DataObject;

class ExpressionObjectWrapper implements \Stringable
{
    /**
     * Method name prefixes considered read-only and therefore callable from a condition.
     */
    private const READ_ONLY_PREFIXES = ['get', 'has', 'is', 'can', 'to', 'count', 'format'];

    /**
     * Methods refused even though they match a read-only prefix. getConfigData() reads
     * payment/carrier credentials under a relative path the encrypted-path guard cannot
     * check; toArray()/toJson()/toXml()/toString() each dump an object's whole internal
     * state in one call, defeating the per-name gate; getDataUsingMethod() re-derives and
     * calls an arbitrary getter, so it side-steps the name-based getConfig encrypted-path
     * guard in __call() (the same getter stays reachable directly — getConfig()/getFoo()/foo
     * — where the guard still applies); getDataSetDefault() writes to the object despite its
     * getter-shaped name.
     *
     * This list is deliberately small: it holds only method-level denials that a field-name
     * rule cannot express. Secret *fields* (password, card data, tokens, ...) are refused by
     * SENSITIVE_FIELD_FRAGMENTS instead, and the no-argument rule in isAllowedCall() already
     * refuses getDataByPath() (it walks an ungated a/b/c path into nested _data) and
     * isDeleted($flag)-style getter-shaped mutators, without listing each by name.
     */
    private const DENIED_METHODS = [
        'getconfigdata',
        'toarray',
        'tojson',
        'toxml',
        'tostring',
        'getdatausingmethod',
        'getdatasetdefault',
    ];

    /**
     * Field-name fragments that name a secret. A resolved field/key whose snake_case name
     * contains one of these (case-insensitively) is refused whether it is reached as a named
     * getter (getPassword() / .password), as a keyed data read (getData('password')), or as
     * the getter derived from a property. Matching a fragment rather than an exact name closes
     * the whole family in one rule — password_hash, rp_token, api_secret, cc_number — instead
     * of chasing each subclass getter (Mage_Customer_Model_Customer::getPassword() returns the
     * stored password and the customer model is handed to the changed-password email template,
     * so with the range/comparison operators a bare getter is a char-by-char exfil oracle).
     */
    private const SENSITIVE_FIELD_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'private_key',
        'cc_number',
        'cc_cid',
    ];

    /**
     * Accessors that take the data key as an argument instead of encoding it in the method
     * name, so the gate has to be applied to the key rather than to the method. Each reads the
     * raw _data/_orig_data array by key without dispatching to a getter, so unlike
     * getDataUsingMethod() they cannot reach a method that decrypts or is otherwise guarded.
     */
    private const KEYED_DATA_ACCESSORS = ['getdata', 'getdatabykey', 'getorigdata'];

    public function __construct(private readonly object $object) {}

    public static function wrap(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_object($value)) {
            return new self($value);
        }
        if (is_array($value)) {
            return array_map(self::wrap(...), $value);
        }
        return $value;
    }

    public function __call(string $method, array $args): mixed
    {
        // Only DataObject instances may be walked into. The legacy resolver chained solely
        // through DataObject (Template::_getVariable), so a getter returning an infrastructure
        // object — getResource() → resource model → getReadConnection() → DBAL connection →
        // getParams()['password'] — must dead-end here instead of exposing DB credentials.
        if (!$this->object instanceof DataObject || !self::isAllowedCall($method, $args)) {
            return null;
        }
        if (strcasecmp($method, 'getConfig') === 0 && $this->isEncryptedConfigPath($args)) {
            $args = [null];
        }
        return self::wrap($this->object->$method(...$args));
    }

    public function __get(string $name): mixed
    {
        // Property access, like method calls, only walks into DataObject instances so it can
        // never reach a non-DataObject's internals (see __call for the escape this closes).
        if (!$this->object instanceof DataObject) {
            return null;
        }
        // Same resolution order as the legacy variable resolver: a real getter method
        // wins over raw data access, so "order.status_label" keeps calling getStatusLabel()
        $getter = 'get' . uc_words($name, '');
        // Property syntax has to clear the same gate as the call syntax, otherwise
        // "payment.cc_number" reaches what "payment.getCcNumber()" refuses. The check runs
        // on the derived getter name so it also covers the getData() fallback below, where
        // there is no method name to inspect but the model still decrypts on read.
        if (!self::isAllowedCall($getter, [])) {
            return null;
        }
        if (method_exists($this->object, $getter)) {
            return self::wrap($this->object->{$getter}());
        }
        return self::wrap($this->object->getData($name));
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    /**
     * PHP stringifies a Stringable object whenever it is compared against a string or a
     * number, so an object without its own string form must fail loudly here: returning ''
     * would silently make "order.billing_address == ''" true for a populated address.
     * evaluateCondition() turns the exception into a logged warning and a false condition.
     */
    #[\Override]
    public function __toString(): string
    {
        if (!method_exists($this->object, '__toString')) {
            throw new \LogicException(sprintf('%s cannot be converted to a string', $this->object::class));
        }
        return (string) $this->object;
    }

    /**
     * @param array $args the arguments the call would be made with, empty for property syntax
     */
    private static function isAllowedCall(string $method, array $args): bool
    {
        if (!self::isReadOnlyMethod($method)) {
            return false;
        }
        $isKeyedAccessor = in_array(strtolower($method), self::KEYED_DATA_ACCESSORS, true);
        // A read-only getter is called with no arguments. The only calls that legitimately
        // carry one are the keyed data accessors (the key to read) and getConfig (the config
        // path, still neutralized on encrypted paths by __call). Refusing every other
        // argument-bearing call closes a whole class of escapes structurally rather than
        // name by name: a getter-shaped method that mutates when handed an argument
        // (isDeleted($flag)) or resolves an ungated nested path (getDataByPath('payment/cc_number'),
        // the un-gated twin of the keyed getData()) can never be reached.
        if ($args !== [] && !$isKeyedAccessor && strcasecmp($method, 'getConfig') !== 0) {
            return false;
        }
        if (!$isKeyedAccessor) {
            // A named getter resolves to a field; refuse it when that field is a secret, so a
            // subclass getter returning a stored credential (Customer::getPassword()) cannot
            // become an exfil oracle. Reached both directly (getPassword()) and via the getter
            // __get() derives for a property (.password -> getPassword).
            $field = self::fieldFromMethod($method);
            // A method that is exactly a bare prefix ("get") resolves to no field and, through
            // DataObject::__call, dumps the whole _data array (getData('') semantics), so it is
            // refused like the keyed accessors refuse a missing key.
            return $field !== '' && !self::isSensitiveField($field);
        }
        // getData('password') has to be refused exactly like getPassword(): the key names
        // what is being read, so it is the key that has to clear both the secret-field gate
        // and the read-only gate. Called without a key these accessors return the entire
        // internal _data array, which would hand out every field at once, so a missing or
        // non-string key is refused outright. A key carrying a "/" is refused too: getData()
        // forwards it to the same ungated getDataByPath() traversal, so only the top-level key
        // would clear the gate while the nested segment ("address/cc_number") is read unchecked.
        $key = $args[0] ?? null;
        return is_string($key) && $key !== '' && !str_contains($key, '/')
            && !self::isSensitiveField($key)
            && self::isReadOnlyMethod('get' . uc_words($key, ''));
    }

    private static function isReadOnlyMethod(string $method): bool
    {
        if (in_array(strtolower($method), self::DENIED_METHODS, true)) {
            return false;
        }
        // The prefix has to end on a camelCase boundary, otherwise a method that merely
        // starts with the same letters passes as read-only: "canShip" is a check,
        // "cancel" cancels the order. Only the prefix itself matches case-insensitively.
        return (bool) preg_match('/^(?i:' . implode('|', self::READ_ONLY_PREFIXES) . ')([^a-z]|$)/', $method);
    }

    /**
     * True when $field names a secret. A boolean-flag accessor (is_/has_/can_) exposes a
     * yes/no status rather than the value itself, so the shipped condition
     * {{if customer.is_change_password}} stays readable even though its name contains
     * "password". Otherwise the field is refused when it ends in Maho's "_enc" encrypted-column
     * suffix or contains a SENSITIVE_FIELD_FRAGMENTS entry.
     */
    private static function isSensitiveField(string $field): bool
    {
        $field = self::toSnakeCase($field);
        if (preg_match('/^(?:is|has|can)_/', $field)) {
            return false;
        }
        if (str_ends_with($field, '_enc')) {
            return true;
        }
        return array_any(self::SENSITIVE_FIELD_FRAGMENTS, fn($fragment) => str_contains($field, $fragment));
    }

    /**
     * Strip the leading read-only prefix off a method name and return the field it reads in
     * snake_case: getPassword -> password, getApiKey -> api_key, getCcNumber -> cc_number.
     */
    private static function fieldFromMethod(string $method): string
    {
        $remainder = preg_replace('/^(?i:' . implode('|', self::READ_ONLY_PREFIXES) . ')/', '', $method, 1);
        return self::toSnakeCase((string) $remainder);
    }

    private static function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/([A-Z])/', '_$1', lcfirst($name)));
    }

    private function isEncryptedConfigPath(array $args): bool
    {
        /** @var \Mage_Adminhtml_Model_Email_PathValidator $validator */
        $validator = \Mage::getModel('adminhtml/email_pathValidator');
        return $validator->isValid($args);
    }
}
