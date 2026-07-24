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
     * Method name prefixes considered read-only and therefore callable from a template.
     * Template::_getVariable() and Template::_evaluateCondition() both resolve through this
     * wrapper, so {{var}} and {{if}}/{{depend}} draw their read-only surface from one place.
     */
    private const READ_ONLY_PREFIXES = ['get', 'has', 'is', 'can', 'to', 'count', 'format'];

    /**
     * Methods refused even though they match a read-only prefix. getConfigData() reads
     * payment/carrier credentials under a relative path the encrypted-path guard cannot
     * check; toArray()/toJson()/toXml()/toString() each dump an object's whole internal
     * state in one call, defeating the per-name gate; toHtml() matches the same "to" prefix but
     * is not a read at all — Mage_Core_Block_Abstract extends DataObject, so a block reaching a
     * template variable would let a condition run a whole block render (queries, observers,
     * cache writes) against the "must not mutate anything" rule above; getChildHtml()/
     * getChildChildHtml()/getBlockHtml() reach the identical _toHtml() render path but carry the
     * read-only "get" prefix, so they are denied alongside toHtml() for the same reason;
     * getDataUsingMethod() re-derives and
     * calls an arbitrary getter, so it side-steps the name-based getConfig encrypted-path
     * guard in __call() (the same getter stays reachable directly — getConfig()/getFoo()/foo
     * — where the guard still applies); getDataSetDefault() writes to the object despite its
     * getter-shaped name; getDataByPath() walks an ungated a/b/c path into nested _data, so it
     * is closed whole rather than filtered by key; getAdditionalInformation()/getAdditionalData()
     * are the payment bags whose *keys* are defined by whichever gateway module is installed
     * (core alone stores cvv_code, avs_code, payer_email, payer_id, three_d_secure, dispute_*,
     * paypal_*_id, vault_token_id and a raw gateway-response blob there), so no field-name
     * vocabulary can ever be complete for them and they are closed whole. No shipped template
     * reads them — order emails render payment through the payment_html block.
     *
     * This list is deliberately small: it holds only method-level denials that a field-name
     * rule cannot express. Secret *fields* (password, card data, tokens, ...) are refused by
     * SENSITIVE_FIELD_FRAGMENTS instead, and isAllowedCall() refuses an argument to an
     * is/can predicate so isDeleted($flag)-style getter-shaped mutators cannot toggle state.
     */
    private const DENIED_METHODS = [
        'getconfigdata',
        'toarray',
        'tojson',
        'toxml',
        'tostring',
        'tohtml',
        'getchildhtml',
        'getchildchildhtml',
        'getblockhtml',
        'getdatausingmethod',
        'getdatasetdefault',
        'getdatabypath',
        'getadditionalinformation',
        'getadditionaldata',
    ];

    /**
     * Field-name fragments that name a secret. A resolved field/key whose snake_case name
     * contains one of these (case-insensitively) is refused whether it is reached as a named
     * getter (getPassword() / .password), as a keyed data read (getData('password')), or as
     * the getter derived from a property. Matching a fragment rather than an exact name closes
     * the whole family in one rule — password_hash, rp_token, api_secret, cvv_code — instead
     * of chasing each subclass getter (Mage_Customer_Model_Customer::getPassword() returns the
     * stored password and the customer model is handed to the changed-password email template,
     * so with the range/comparison operators a bare getter is a char-by-char exfil oracle).
     * "echeck" closes the eCheck/ACH bank family a payment info object carries
     * (echeck_routing_number, echeck_account_name, echeck_bank_name, echeck_account_type) —
     * bank data every bit as sensitive as the cc_* card fields, and the payment object is handed
     * to order emails.
     */
    private const SENSITIVE_FIELD_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'private_key',
        'cvv',
        'echeck',
    ];

    /**
     * Cardholder data is the one field family where the denylist is inverted: everything a
     * payment info object carries under cc_* is card data (cc_number, cc_cid, cc_owner, the
     * expiry pair, the Switch/Solo cc_ss_* set), so naming the sensitive members one by one
     * leaves whatever field the next gateway adds readable by default. The prefix is refused
     * wholesale and only the two fields Magento deliberately keeps in the clear so a template
     * can print "VI ****1111" are let back through.
     */
    private const CARD_FIELD_PREFIX = 'cc_';
    private const ALLOWED_CARD_FIELDS = ['cc_type', 'cc_last4'];

    /**
     * Fields a SENSITIVE_FIELD_FRAGMENTS entry would otherwise refuse, but which a shipped
     * template has to read to do its job. rp_token is the password-reset / magic-link token:
     * the account_new_password_link, account_password_reset_confirmation, account_magic_link and
     * admin_password_reset_confirmation mails build their action URL from it
     * ({{store url="customer/account/resetpassword/" _query_token=$customer.rp_token}}), so
     * refusing it silently drops the token from the link and leaves the recipient unable to
     * reset their password. Reading it is not a disclosure — the token's whole purpose is to be
     * printed into the mail being rendered, which is addressed to the account that owns it.
     */
    private const ALLOWED_TOKEN_FIELDS = ['rp_token'];

    /**
     * Accessors that take the data key as an argument instead of encoding it in the method
     * name, so the gate has to be applied to the key rather than to the method. Each reads the
     * raw _data/_orig_data array by key without dispatching to a getter, so unlike
     * getDataUsingMethod() they cannot reach a method that decrypts or is otherwise guarded.
     */
    private const KEYED_DATA_ACCESSORS = ['getdata', 'getdatabykey', 'getorigdata'];

    /**
     * Bounds on the eager copy wrapArray() makes of every array handed to the engine.
     *
     * Subscripting is native PHP, so an array has to be walked and filtered on the way in rather
     * than gated at access time — which means a self-referential array ($a['self'] = &$a) recurses
     * until the process dies of memory exhaustion. That is a fatal error, not an exception, so it
     * escapes the handler that turns a bad expression into an empty render and takes the whole
     * request with it. Past either bound the remaining entries are dropped, degrading the value the
     * same way a refused field does. Neither bound is reachable by real template data: a model's
     * data array is a few levels deep and orders of magnitude smaller.
     */
    private const MAX_ARRAY_DEPTH = 16;
    private const MAX_ARRAY_ELEMENTS = 10000;

    /** @var array<class-string, array<string, string>> lowercased method name => declared spelling */
    private static array $_declaredMethods = [];

    /**
     * The guarded object, held inside a closure rather than in a property of its own.
     *
     * PHP compares two objects of the same class structurally — property by property, blind to
     * visibility — and every value handed to the expression engine is an ExpressionObjectWrapper.
     * A comparison never reaches __call()/__get(), so no gate can run on it: with the guarded
     * object sitting in a property here, "{{if secret > probe}}" would recurse straight into both
     * models' _data arrays and answer from the raw values, turning any refused field into a
     * bisection oracle ({{if secret == guess}} confirms a password outright). That also covers
     * min()/max(), which ExpressionLanguage registers as the real PHP functions and which reach
     * the same comparison primitive without going through an operator.
     *
     * A closure is opaque to that comparison: PHP compares two distinct Closure instances as
     * unequal and unordered whatever they capture, so comparing two wrapped values now answers
     * the same way regardless of what they guard. The consequence is that an object-to-object
     * comparison is simply not answerable in a condition — {{if a == b}} is always false for two
     * wrapped values, and a template that needs to compare them has to compare a field of each
     * ({{if a.entity_id == b.entity_id}}), which goes through the gate as it should.
     */
    private readonly \Closure $_object;

    protected function __construct(object $object)
    {
        $this->_object = static fn(): object => $object;
    }

    private function _object(): object
    {
        return ($this->_object)();
    }

    /**
     * Wrap a template variable so every hop through it is gated. Published by ExpressionSandbox.
     *
     * Deliberately not public: an instance of this class is handed straight to
     * ExpressionLanguage, and Symfony's GetAttrNode dispatches a method call as
     * is_callable([$obj, $name]) — which is satisfied by any *real* public method, including a
     * static one, without ever reaching __call(). A public wrap()/unwrap() would therefore be
     * callable from a template as "customer.unwrap(customer)", handing the raw model back into
     * the expression and voiding every gate below it. Everything non-magic on this class stays
     * private or protected so that such a call falls through to the gated __call() instead;
     * ExpressionSandbox borrows this scope to publish the two entry points Template needs.
     */
    protected static function _wrap(mixed $value): mixed
    {
        $budget = self::MAX_ARRAY_ELEMENTS;
        return self::wrapValue($value, 0, $budget);
    }

    private static function wrapValue(mixed $value, int $depth, int &$budget): mixed
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_object($value)) {
            return new self($value);
        }
        if (is_array($value)) {
            return $depth >= self::MAX_ARRAY_DEPTH ? null : self::wrapArray($value, $depth + 1, $budget);
        }
        return $value;
    }

    /**
     * Wrap an array element by element, nulling out every entry whose key names a secret.
     *
     * Subscripting an array in an expression ("customer.getCredentials()['api_token']") is native
     * PHP: it never reaches __call()/__get(), so the field-name gate cannot run at access time and
     * has to be applied here, when the array is handed to the expression. Without this a secret
     * sitting inside an array an allowed getter legitimately returns would be readable even though
     * the identically named getter/property is refused.
     *
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function wrapArray(array $value, int $depth, int &$budget): array
    {
        $wrapped = [];
        foreach ($value as $key => $item) {
            if (--$budget < 0) {
                break;
            }
            $wrapped[$key] = is_string($key) && self::isSensitiveField($key)
                ? null
                : self::wrapValue($item, $depth, $budget);
        }
        return $wrapped;
    }

    /**
     * Reverse wrap(): unwrap a value returned from an expression back to the raw object it
     * guards, so {{var}} can format a DateTimeInterface or string-cast a scalar. The guard only
     * governs how a value is *reached* during evaluation; the resolved value itself is returned
     * plain. Not public, for the reason spelled out on _wrap(): it returns the guarded object, so
     * reaching it from inside an expression would be a complete escape.
     */
    protected static function _unwrap(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->_object();
        }
        if (is_array($value)) {
            return array_map(self::_unwrap(...), $value);
        }
        return $value;
    }

    public function __call(string $method, array $args): mixed
    {
        $object = $this->_object();
        $method = self::_canonicalMethodName($object, $method);
        // Only DataObject instances may be walked into. The legacy resolver chained solely
        // through DataObject (Template::_getVariable), so a getter returning an infrastructure
        // object — getResource() → resource model → getReadConnection() → DBAL connection →
        // getParams()['password'] — must dead-end here instead of exposing DB credentials.
        if (!$object instanceof DataObject || !self::isAllowedCall($method, $args, method_exists($object, $method))) {
            return null;
        }
        if (strcasecmp($method, 'getConfig') === 0 && $this->isEncryptedConfigPath($args)) {
            $args = [null];
        }
        return self::_wrap($object->$method(...$args));
    }

    public function __get(string $name): mixed
    {
        $object = $this->_object();
        // Property access, like method calls, only walks into DataObject instances so it can
        // never reach a non-DataObject's internals (see __call for the escape this closes).
        if (!$object instanceof DataObject) {
            return null;
        }
        // Same resolution order as the legacy variable resolver: a real getter method
        // wins over raw data access, so "order.status_label" keeps calling getStatusLabel()
        $getter = self::_canonicalMethodName($object, 'get' . uc_words($name, ''));
        // Property syntax has to clear the same gate as the call syntax, otherwise
        // "payment.cc_number" reaches what "payment.getCcNumber()" refuses. The check runs
        // on the derived getter name so it also covers the getData() fallback below, where
        // there is no method name to inspect but the model still decrypts on read.
        $declared = method_exists($object, $getter);
        if (!self::isAllowedCall($getter, [], $declared)) {
            return null;
        }
        if ($declared) {
            return self::_wrap($object->{$getter}());
        }
        return self::_wrap($object->getData($name));
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
        $object = $this->_object();
        if (!method_exists($object, '__toString')) {
            throw new \LogicException(sprintf('%s cannot be converted to a string', $object::class));
        }
        return (string) $object;
    }

    /**
     * @param array $args the arguments the call would be made with, empty for property syntax
     * @param bool $declared whether the class really declares $method, i.e. the call does not
     *                       fall through to DataObject::__call() (see the "/" rule below)
     */
    private static function isAllowedCall(string $method, array $args, bool $declared): bool
    {
        if (!self::isReadOnlyMethod($method)) {
            return false;
        }
        // A named getter resolves to a field; refuse it when that field is a secret, so a
        // subclass getter returning a stored credential (Customer::getPassword(), reached
        // directly or as the getter __get() derives for a property) cannot become an oracle.
        // A boolean predicate is exempt for the same reason isSensitiveField() exempts an
        // is_/has_/can_ field: it answers a yes/no status, not the value (see isBooleanPredicate).
        if (!self::isBooleanPredicate($method) && self::isSensitiveField(self::fieldFromMethod($method))) {
            return false;
        }
        // A bare "get" resolves to getData('') and dumps the whole _data array. (format()/
        // count() also strip to an empty field but read, not dump, so they stay allowed.)
        if (strcasecmp($method, 'get') === 0) {
            return false;
        }
        if (in_array(strtolower($method), self::KEYED_DATA_ACCESSORS, true)) {
            // A keyed data accessor needs a non-empty string key; without one it returns the
            // whole internal _data array, handing out every field at once.
            $key = $args[0] ?? null;
            if (!is_string($key) || $key === '') {
                return false;
            }
        }
        if ($args === []) {
            return true;
        }
        // An argument to an is/can predicate is not a read but a state toggle (isDeleted($flag)).
        if (preg_match('/^(?i:is|can)([^a-z]|$)/', $method)) {
            return false;
        }
        // Every argument of a call that lands in DataObject::getData() is a data key, and a key
        // holding "/" is a nested-path traversal: getData() forwards it to getDataByPath(), which
        // walks into nested _data and reaches getData('payment', 'additional_information/cc_cid')
        // around the field-name gate — that gate only ever sees the whole path string, whose
        // anchored rules (the cc_ prefix, the additional_* bags) then never fire. getDataByPath()
        // is denied outright for the same reason, so it must not be reachable indirectly either.
        // Both the *key* and the *index* argument reach it, and so does an undeclared getFoo($x),
        // which DataObject::__call() rewrites to getData('foo', $x). Declared methods keep taking
        // "/" arguments — it is how every route and config path is spelled (getUrl('customer/
        // account/'), getConfig('web/unsecure/base_url')) — and getConfig()'s encrypted paths are
        // neutralized in __call().
        if (!$declared || in_array(strtolower($method), self::KEYED_DATA_ACCESSORS, true)) {
            if (array_any($args, fn($arg) => is_string($arg) && str_contains($arg, '/'))) {
                return false;
            }
        }
        // Otherwise arguments are allowed as the legacy resolver allowed them — getData('sku'),
        // getAttributeText('color'), format('html') — but a string argument must not name a
        // secret field, because a keyed read answers with the raw value under that name.
        return !array_any(
            $args,
            fn($arg) => is_string($arg) && self::isSensitiveField($arg),
        );
    }

    /**
     * Return $method spelled the way the class declares it.
     *
     * PHP dispatches method names case-insensitively, but both gates below read camelCase word
     * boundaries out of the name the template author typed: isReadOnlyMethod() requires the
     * read-only prefix to end on a boundary and fieldFromMethod() splits the remainder on
     * capitals. Spelling a method to fake a boundary that the declaration does not have therefore
     * changes what the gates see while PHP still reaches the same method — "canCel" reads as a
     * "can" predicate but runs cancel(), "getPassWord" resolves to the unlisted field "pass_word"
     * but runs getPassword(). Normalizing first makes the gates inspect the symbol that will
     * actually be called. A name no declared method matches is a magic getter, where
     * DataObject::_underscore() derives the data key from that same typed string, so leaving it
     * as typed keeps the gate and the lookup symmetric.
     */
    private static function _canonicalMethodName(object $object, string $method): string
    {
        $class = $object::class;
        if (!isset(self::$_declaredMethods[$class])) {
            self::$_declaredMethods[$class] = array_column(
                array_map(fn(string $name): array => [strtolower($name), $name], get_class_methods($object)),
                1,
                0,
            );
        }
        return self::$_declaredMethods[$class][strtolower($method)] ?? $method;
    }

    /**
     * True when $method is an is/has/can boolean predicate (isMagicLinkTokenExpired(),
     * hasCustomOptions()). It answers a yes/no status, never the underlying value, so — exactly
     * like the is_/has_/can_ *property* exemption in isSensitiveField() — a SENSITIVE_FIELD_FRAGMENTS
     * hit in its name ("token" in isMagicLinkTokenExpired) must not refuse it. Without this the two
     * access paths disagree: __get() prepends "get" before gating, so the marker survives
     * fieldFromMethod() for a property, but a direct call strips the is/has/can itself and the
     * exemption could never fire. This only exempts the method *name* from the field-name gate;
     * an argument to a predicate is a state toggle (isDeleted($flag)) and is refused separately in
     * isAllowedCall(), and a sensitive string argument is still refused there too.
     */
    private static function isBooleanPredicate(string $method): bool
    {
        return (bool) preg_match('/^(?i:is|has|can)([^a-z]|$)/', $method);
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
     * "password", and ALLOWED_TOKEN_FIELDS lets the reset-link templates through.
     * Otherwise the field is refused when it names one of the free-form payment bags,
     * sits under the cc_* cardholder prefix without being one of the two masked display fields,
     * ends in Maho's "_enc" encrypted-column suffix, or contains a SENSITIVE_FIELD_FRAGMENTS entry.
     */
    private static function isSensitiveField(string $field): bool
    {
        $field = self::toSnakeCase($field);
        if (preg_match('/^(?:is|has|can)_/', $field)) {
            return false;
        }
        if (in_array($field, self::ALLOWED_TOKEN_FIELDS, true)) {
            return false;
        }
        // The bags DENIED_METHODS closes, refused again as data keys so getData('additional_data')
        // cannot fetch what getAdditionalData() will not.
        if (in_array($field, ['additional_information', 'additional_data'], true)) {
            return true;
        }
        if (str_starts_with($field, self::CARD_FIELD_PREFIX)) {
            return !in_array($field, self::ALLOWED_CARD_FIELDS, true);
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

    /**
     * Split $name on camelCase boundaries, keeping a run of capitals together as one word:
     * CcNumber -> cc_number, CVVCode -> cvv_code, APIKey -> api_key, CCNumber -> cc_number.
     *
     * Splitting on every capital instead would spell those c_v_v_code / a_p_i_key / c_c_number,
     * which no rule below names — a gateway module declaring getCVVCode() or getCCNumber() would
     * sail past both the "cvv" fragment and the cc_* cardholder prefix. The property form leaks
     * with it: __get() canonicalizes the typed "cvv_code" to the declared getCVVCode() before
     * gating, so the gate would see the mis-split name there too.
     */
    private static function toSnakeCase(string $name): string
    {
        $name = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name);
        $name = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name);
        return strtolower($name);
    }

    /**
     * True when a getConfig() argument would surface an encrypted configuration value, so __call()
     * can neutralize it by calling getConfig(null) instead.
     *
     * The exact encrypted leaf (system/smtp/password) is refused, but so is any *ancestor* path:
     * Store::getConfig('system/smtp') returns the whole subtree as an array, and a native subscript
     * (store.getConfig('system/smtp')['password'], or ['pass'~'word'] to dodge a literal-argument
     * check) then reaches the decrypted leaf around a check that only ever saw the ancestor path.
     * The encrypted-node list is the authoritative record of what a template must never print —
     * gateway/SMTP/carrier credentials — so a request for any path that would carry one of them out
     * in its subtree is closed. Legitimate scalar reads (general/store_information/name) and
     * subtrees with no encrypted descendant are untouched.
     */
    private function isEncryptedConfigPath(array $args): bool
    {
        $path = $args[0] ?? null;
        if (!is_string($path) || $path === '') {
            return false;
        }
        /** @var \Mage_Adminhtml_Model_Config $config */
        $config = \Mage::getSingleton('adminhtml/config');
        return array_any(
            $config->getEncryptedNodeEntriesPaths(),
            fn($encrypted): bool => $path === $encrypted || str_starts_with((string) $encrypted, $path . '/'),
        );
    }
}
