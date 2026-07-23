<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\DataObject;
use Maho\Filter\Template;
use Maho\Filter\Template\ExpressionObjectWrapper;

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests the {{if}} / {{depend}} template directives, evaluated through Symfony
 * ExpressionLanguage. The legacy single-variable syntax is a subset of the expression
 * syntax and must keep working unchanged.
 */
describe('legacy single-variable conditions', function () {
    it('renders the true branch for a non-empty variable', function () {
        $filter = (new Template())->setVariables(['customer_name' => 'Bob']);
        expect($filter->filter('{{if customer_name}}Hi{{/if}}'))->toBe('Hi');
    });

    it('renders the else branch for an empty variable', function () {
        $filter = (new Template())->setVariables(['foo' => '']);
        expect($filter->filter('{{if foo}}A{{else}}B{{/if}}'))->toBe('B');
    });

    it('resolves DataObject property paths', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['increment_id' => '100000001'])]);
        expect($filter->filter('{{if order.increment_id}}Y{{else}}N{{/if}}'))->toBe('Y');
    });

    it('resolves DataObject getter method calls', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['increment_id' => '100000001'])]);
        expect($filter->filter('{{if order.getIncrementId()}}Y{{else}}N{{/if}}'))->toBe('Y');
    });

    it('handles the depend directive', function () {
        $filter = (new Template())->setVariables(['foo' => 'bar', 'empty' => '']);
        expect($filter->filter('{{depend foo}}X{{/depend}}'))->toBe('X');
        expect($filter->filter('{{depend empty}}X{{/depend}}'))->toBe('');
        expect($filter->filter('{{depend missing}}X{{/depend}}'))->toBe('');
    });

    it('prefers a real getter method over raw data access for property paths', function () {
        $order = new class extends DataObject {
            public function getStatusLabel(): string
            {
                return 'Processing';
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);
        expect($filter->filter("{{if order.status_label == 'Processing'}}Y{{else}}N{{/if}}"))->toBe('Y');
    });

    it('resolves property paths through computed getters returning objects', function () {
        // getData('billing_address') is empty; only the real getter can resolve the path,
        // and its DataObject result must be re-wrapped for the .company access to work
        $order = new class extends DataObject {
            public function getBillingAddress(): DataObject
            {
                return new DataObject(['company' => 'ACME']);
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);
        expect($filter->filter('{{if order.billing_address.company}}Y{{else}}N{{/if}}'))->toBe('Y');
        expect($filter->filter("{{if order.billing_address.company == 'ACME'}}Y{{else}}N{{/if}}"))->toBe('Y');
    });

    it('treats absent variables as empty without failing', function () {
        $filter = (new Template())->setVariables(['foo' => 'bar']);
        expect($filter->filter('{{if comment}}A{{else}}B{{/if}}'))->toBe('B');
        expect($filter->filter('{{depend comment}}X{{/depend}}'))->toBe('');
    });

    it('treats zero as false', function () {
        $filter = (new Template())->setVariables(['qty' => 0, 'qty_string' => '0']);
        expect($filter->filter('{{if qty}}A{{else}}B{{/if}}'))->toBe('B');
        expect($filter->filter('{{if qty_string}}A{{else}}B{{/if}}'))->toBe('B');
    });

    it('leaves directives untouched during preprocessing (no variables set)', function () {
        $filter = new Template();
        $template = '{{if order.total > 100}}A{{else}}B{{/if}}';
        expect($filter->filter($template))->toBe($template);
    });
});

describe('expression conditions', function () {
    it('evaluates numeric comparisons on DataObject data', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['grand_total' => 150.0])]);
        expect($filter->filter('{{if order.grand_total > 100}}BIG{{else}}SMALL{{/if}}'))->toBe('BIG');

        $filter = (new Template())->setVariables(['order' => new DataObject(['grand_total' => 50.0])]);
        expect($filter->filter('{{if order.grand_total > 100}}BIG{{else}}SMALL{{/if}}'))->toBe('SMALL');
    });

    it('evaluates boolean logic across multiple variables', function () {
        $filter = (new Template())->setVariables([
            'order' => new DataObject(['grand_total' => 150.0]),
            'customer' => new DataObject(['group_id' => 2]),
        ]);
        expect($filter->filter('{{if order.grand_total > 100 && customer.group_id == 2}}Y{{else}}N{{/if}}'))->toBe('Y');
        expect($filter->filter('{{if order.grand_total > 500 || customer.group_id == 2}}Y{{else}}N{{/if}}'))->toBe('Y');
        expect($filter->filter('{{if order.grand_total > 500 && customer.group_id == 2}}Y{{else}}N{{/if}}'))->toBe('N');
    });

    it('supports method calls in expressions', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['grand_total' => 150.0])]);
        expect($filter->filter('{{if order.getGrandTotal() > 100}}Y{{else}}N{{/if}}'))->toBe('Y');
    });

    it('supports string comparisons and the in operator', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['status' => 'complete'])]);
        expect($filter->filter("{{if order.status == 'complete'}}Y{{else}}N{{/if}}"))->toBe('Y');
        expect($filter->filter("{{if order.status in ['complete', 'closed']}}Y{{else}}N{{/if}}"))->toBe('Y');
        expect($filter->filter("{{if order.status in ['pending', 'holded']}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('traverses nested DataObjects', function () {
        $filter = (new Template())->setVariables([
            'order' => new DataObject(['payment' => new DataObject(['method' => 'checkmo'])]),
        ]);
        expect($filter->filter("{{if order.payment.method == 'checkmo'}}Y{{else}}N{{/if}}"))->toBe('Y');
        expect($filter->filter("{{if order.getPayment().getMethod() == 'checkmo'}}Y{{else}}N{{/if}}"))->toBe('Y');
    });

    it('works in the depend directive too', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['grand_total' => 150.0])]);
        expect($filter->filter('{{depend order.grand_total > 100}}X{{/depend}}'))->toBe('X');
        expect($filter->filter('{{depend order.grand_total > 500}}X{{/depend}}'))->toBe('');
    });

    it('renders the else branch for an invalid expression instead of throwing', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['grand_total' => 150.0])]);
        expect($filter->filter('{{if order.grand_total >}}A{{else}}B{{/if}}'))->toBe('B');
    });

    it('renders the else branch when an expression references an unknown variable', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject()]);
        expect($filter->filter('{{if warehouse.stock > 0}}A{{else}}B{{/if}}'))->toBe('B');
    });

    it('does not expose the constant() function', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject()]);
        expect($filter->filter("{{if constant('PHP_VERSION')}}A{{else}}B{{/if}}"))->toBe('B');
    });
});

describe('expression object wrapper', function () {
    it('neutralizes getConfig calls against encrypted configuration paths', function () {
        $encryptedPaths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();
        if (count($encryptedPaths) === 0) {
            $this->markTestSkipped('No encrypted configuration paths available in this environment');
        }

        $object = new class extends DataObject {
            public function getConfig(?string $path = null): ?string
            {
                return $path;
            }
        };
        $wrapped = ExpressionObjectWrapper::wrap($object);

        expect($wrapped->getConfig('general/locale/code'))->toBe('general/locale/code');
        expect($wrapped->getConfig((string) $encryptedPaths[0]))->toBeNull();
    });

    it('applies the encrypted-path guard to getConfig calls made inside template expressions', function () {
        $encryptedPaths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();
        if (count($encryptedPaths) === 0) {
            $this->markTestSkipped('No encrypted configuration paths available in this environment');
        }

        $store = new class extends DataObject {
            public function getConfig(?string $path = null): ?string
            {
                return $path === null ? null : 'secret-value';
            }
        };
        $filter = (new Template())->setVariables(['store' => $store]);

        // Plain path reaches getConfig and returns a value; encrypted path is called with null
        expect($filter->filter("{{if store.getConfig('general/locale/code') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('Y');
        $encrypted = (string) $encryptedPaths[0];
        expect($filter->filter("{{if store.getConfig('{$encrypted}') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('keeps the encrypted-path guard after an array hop', function () {
        $encryptedPaths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();
        if (count($encryptedPaths) === 0) {
            $this->markTestSkipped('No encrypted configuration paths available in this environment');
        }

        $store = new class extends DataObject {
            public function getConfig(?string $path = null): ?string
            {
                return $path === null ? null : 'secret-value';
            }
        };
        $container = new DataObject(['stores' => [$store]]);
        $filter = (new Template())->setVariables(['container' => $container]);

        $encrypted = (string) $encryptedPaths[0];
        expect($filter->filter("{{if container.stores[0].getConfig('general/locale/code') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('Y');
        expect($filter->filter("{{if container.stores[0].getConfig('{$encrypted}') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses method calls that are not read-only', function () {
        $order = new DataObject(['status' => 'pending']);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter("{{if order.setStatus('canceled')}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter('{{if order.unsetData()}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($order->getStatus())->toBe('pending');
    });

    it('refuses methods that merely start with the letters of a read-only prefix', function () {
        // "can" must not open the door to cancel(), nor "has" to hash(), nor "to" to toggle()
        $order = new class extends DataObject {
            public bool $cancelled = false;

            public function cancel(): bool
            {
                $this->cancelled = true;
                return true;
            }

            public function canShip(): bool
            {
                return true;
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter('{{if order.cancel()}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($order->cancelled)->toBeFalse();
        expect($filter->filter('{{if order.canShip()}}Y{{else}}N{{/if}}'))->toBe('Y');
    });

    it('refuses getters that decrypt secrets on read', function () {
        $payment = new class extends DataObject {
            public function getCcNumber(): string
            {
                return '4111111111111111';
            }

            public function getConfigData(string $field): string
            {
                return 'gateway-' . $field;
            }
        };
        $filter = (new Template())->setVariables(['payment' => $payment]);

        expect($filter->filter("{{if payment.getCcNumber() == '4111111111111111'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if payment.getConfigData('password') == 'gateway-password'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses secret data keys read through the generic getData() accessor', function () {
        // Mage_Payment_Model_Info::getData() decrypts cc_number/cc_cid on read, so the key
        // has to clear the same gate the equivalent named getter does
        $payment = new DataObject(['cc_number' => '4111111111111111', 'cc_cid' => '123', 'method' => 'checkmo']);
        $filter = (new Template())->setVariables(['payment' => $payment]);

        expect($filter->filter("{{if payment.getData('cc_number') == '4111111111111111'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if payment.getDataByKey('cc_cid') == '123'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if payment.getDataUsingMethod('cc_number') == '4111111111111111'}}Y{{else}}N{{/if}}"))->toBe('N');
        // a harmless key still resolves, so the gate filters keys rather than banning getData()
        expect($filter->filter("{{if payment.getData('method') == 'checkmo'}}Y{{else}}N{{/if}}"))->toBe('Y');
    });

    it('refuses accessors that dump the whole object state in one call', function () {
        $order = new DataObject(['increment_id' => '100000001', 'customer_email' => 'a@example.com']);
        $filter = (new Template())->setVariables(['order' => $order]);

        // no key at all returns the entire internal _data array
        expect($filter->filter('{{if order.getData()}}Y{{else}}N{{/if}}'))->toBe('N');
        // bare get() dumps the same _data array via DataObject::__call (getData('') semantics)
        expect($filter->filter('{{if order.get()}}Y{{else}}N{{/if}}'))->toBe('N');
        // toArray()/toJson()/toXml()/toString() all serialize the whole _data array
        expect($filter->filter('{{if order.toArray()}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($filter->filter("{{if order.toJson() contains '100000001'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if order.toXml() contains '100000001'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if order.toString() contains 'a@example.com'}}Y{{else}}N{{/if}}"))->toBe('N');
        // "order.data" resolves to the same argument-less getData() call
        expect($filter->filter('{{if order.data}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($filter->filter("{{if order.increment_id == '100000001'}}Y{{else}}N{{/if}}"))->toBe('Y');
    });

    it('refuses getDataUsingMethod(), which dispatches past the getConfig encrypted-path guard', function () {
        // getDataUsingMethod('config', $path) re-derives getConfig() and calls it, so it would
        // reach the decrypt-on-read config with the raw path had it not been denied outright.
        $encryptedPaths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();
        if (count($encryptedPaths) === 0) {
            $this->markTestSkipped('No encrypted configuration paths available in this environment');
        }

        $store = new class extends DataObject {
            public function getConfig(?string $path = null): ?string
            {
                return $path === null ? null : 'secret-value';
            }
        };
        $filter = (new Template())->setVariables(['store' => $store]);

        $encrypted = (string) $encryptedPaths[0];
        // Even a plain path is refused because the whole dispatcher is denied
        expect($filter->filter("{{if store.getDataUsingMethod('config', 'general/locale/code') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if store.getDataUsingMethod('config', '{$encrypted}') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('N');
        // The direct getConfig() call still works and stays guarded
        expect($filter->filter("{{if store.getConfig('general/locale/code') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('Y');
        expect($filter->filter("{{if store.getConfig('{$encrypted}') == 'secret-value'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses a subclass getter that returns a stored secret field', function () {
        // Mage_Customer_Model_Customer::getPassword() returns the stored password and the
        // customer model is handed to the changed-password email template, so a bare getter
        // would be a char-by-char exfil oracle with the comparison operators.
        $customer = new class extends DataObject {
            public function getPassword(): string
            {
                return '$2y$10$abcdefghijklmnopqrstuv';
            }
        };
        $filter = (new Template())->setVariables(['customer' => $customer]);

        expect($filter->filter("{{if customer.getPassword() starts with '\$2y\$'}}Y{{else}}N{{/if}}"))->toBe('N');
        // property syntax resolves to the same getPassword() and is refused too
        expect($filter->filter("{{if customer.password starts with '\$2y\$'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses secret fields read through the generic getData() accessor', function () {
        $customer = new DataObject([
            'password' => '$2y$10$abcdefghijklmnopqrstuv',
            'rp_token' => 'reset-token-123',
            'api_key' => 'sk_live_abc',
            'firstname' => 'Bob',
        ]);
        $filter = (new Template())->setVariables(['customer' => $customer]);

        expect($filter->filter("{{if customer.getData('password') starts with '\$2y\$'}}Y{{else}}N{{/if}}"))->toBe('N');
        // the fragment rule closes the whole family, not just the exact field name
        expect($filter->filter("{{if customer.getData('rp_token') == 'reset-token-123'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if customer.api_key == 'sk_live_abc'}}Y{{else}}N{{/if}}"))->toBe('N');
        // positive control: a benign field still resolves
        expect($filter->filter("{{if customer.firstname == 'Bob'}}Y{{else}}N{{/if}}"))->toBe('Y');
        expect($filter->filter("{{if customer.getData('firstname') == 'Bob'}}Y{{else}}N{{/if}}"))->toBe('Y');
    });

    it('still reads a boolean flag whose name contains a secret fragment', function () {
        // {{if customer.is_change_password}} ships in the changed-password email template.
        // is_/has_/can_ accessors expose a yes/no status, not the secret value, so the
        // secret-fragment rule must not swallow them.
        $customer = new DataObject([
            'is_change_password' => 1,
            'has_secret_question' => 0,
        ]);
        $filter = (new Template())->setVariables(['customer' => $customer]);

        expect($filter->filter('{{if customer.is_change_password}}Y{{else}}N{{/if}}'))->toBe('Y');
        expect($filter->filter("{{if customer.getData('is_change_password')}}Y{{else}}N{{/if}}"))->toBe('Y');
        expect($filter->filter('{{depend customer.has_secret_question}}X{{/depend}}'))->toBe('');
    });

    it('refuses any field carrying the _enc encrypted-column suffix', function () {
        // Maho stores encrypted-at-rest columns with an "_enc" suffix; a custom one whose base
        // name is in no fragment list must still be refused.
        $model = new DataObject(['gateway_credential_enc' => 'cipher-text']);
        $filter = (new Template())->setVariables(['model' => $model]);

        expect($filter->filter("{{if model.gateway_credential_enc == 'cipher-text'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if model.getData('gateway_credential_enc') == 'cipher-text'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses getDataByPath(), the ungated twin of the keyed getData() accessor', function () {
        // getDataByPath() walks an a/b/c path straight into the raw _data array with no
        // per-segment gate, so it must not become a back door to a key getData() refuses.
        $payment = new DataObject([
            'cc_number' => '4111111111111111',
            'method' => 'checkmo',
            'address' => new DataObject(['cc_cid' => '123']),
        ]);
        $filter = (new Template())->setVariables(['payment' => $payment]);

        expect($filter->filter("{{if payment.getDataByPath('cc_number') == '4111111111111111'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if payment.getDataByPath('address/cc_cid') == '123'}}Y{{else}}N{{/if}}"))->toBe('N');
        // even a harmless key is refused: the whole path accessor is closed, not filtered
        expect($filter->filter("{{if payment.getDataByPath('method') == 'checkmo'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses a slash path smuggled through the keyed getData() accessor', function () {
        // getData('address/cc_cid') would clear the gate on the top-level key and then forward
        // to the same ungated getDataByPath() traversal, reaching the nested secret segment.
        $order = new DataObject([
            'address' => new DataObject(['cc_cid' => '123']),
            'increment_id' => '100000001',
        ]);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter("{{if order.getData('address/cc_cid') == '123'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses a getter-shaped mutator invoked with an argument', function () {
        // isDeleted() reads a flag, but isDeleted(true) writes it — and Mage_Core_Model_Abstract
        // ::save() routes a "deleted" object to delete() instead of persisting. A read-only
        // condition must never carry an argument into such a method.
        $order = new DataObject(['increment_id' => '100000001']);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter('{{if order.isDeleted(true)}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($order->isDeleted())->toBeFalse();
    });

    it('refuses getDataSetDefault(), which writes despite its getter-shaped name', function () {
        $order = new DataObject(['increment_id' => '100000001']);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter("{{if order.getDataSetDefault('status', 'new') == 'new'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($order->hasData('status'))->toBeFalse();
    });

    it('refuses those getters through the property syntax too', function () {
        // Mage_Payment_Model_Info decrypts on getData('cc_number'), so the dotted form must
        // be refused whether or not the model declares a real getter for it
        $payment = new class extends DataObject {
            public function getCcNumber(): string
            {
                return '4111111111111111';
            }
        };
        $magic = new DataObject(['cc_number' => '4111111111111111', 'cc_cid' => '123']);
        $filter = (new Template())->setVariables(['payment' => $payment, 'magic' => $magic]);

        expect($filter->filter("{{if payment.cc_number == '4111111111111111'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if magic.cc_number == '4111111111111111'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if magic.cc_cid == '123'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('refuses a mutating method reached through the property syntax', function () {
        $order = new class extends DataObject {
            public bool $cancelled = false;

            public function cancel(): bool
            {
                $this->cancelled = true;
                return true;
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter('{{if order.cancel}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($order->cancelled)->toBeFalse();
    });

    it('refuses to walk into a non-DataObject returned by a getter', function () {
        // Mirrors order.getResource().getReadConnection()...: a getter that returns an
        // infrastructure object must dead-end, so a template can never chain from a model
        // into a DB adapter/connection and read credentials via a boolean oracle.
        $connection = new class {
            /** @return array<string, string> */
            public function getParams(): array
            {
                return ['password' => 'super-secret'];
            }
        };
        $order = new class ($connection) extends DataObject {
            public function __construct(private object $connection)
            {
                parent::__construct();
            }

            public function getResource(): object
            {
                return $this->connection;
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);

        // The first hop is allowed (getResource is a read-only getter) but returns a wrapped
        // non-DataObject, so the second hop returns null and the oracle never fires.
        expect($filter->filter("{{if order.getResource().getParams()['password'] == 'super-secret'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter('{{if order.getResource().getParams() == null}}Y{{else}}N{{/if}}'))->toBe('Y');
    });

    it('refuses the matches and range operators', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['status' => 'complete'])]);

        expect($filter->filter("{{if order.status matches '/^complete$/'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter('{{if 1 in 0..10}}Y{{else}}N{{/if}}'))->toBe('N');
    });

    it('does not silently compare an object without a string form against a string', function () {
        // Stringifying to '' would make a populated address equal to the empty string
        $order = new class extends DataObject {
            public function getBillingAddress(): DataObject
            {
                return new DataObject(['company' => 'ACME']);
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter("{{if order.billing_address == ''}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('does not expose the enum() function', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject()]);
        expect($filter->filter("{{if enum('Maho\\\\Filter\\\\Template::CONSTRUCTION_IF_PATTERN')}}A{{else}}B{{/if}}"))->toBe('B');
    });
});
