<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\DataObject;
use Maho\Filter\Template;
use Maho\Filter\Template\ExpressionObjectWrapper;
use Maho\Filter\Template\ExpressionSandbox;

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
        $wrapped = ExpressionSandbox::wrap($object);

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
            'vault_token' => 'tok-123',
            'api_key' => 'sk_live_abc',
            'firstname' => 'Bob',
        ]);
        $filter = (new Template())->setVariables(['customer' => $customer]);

        expect($filter->filter("{{if customer.getData('password') starts with '\$2y\$'}}Y{{else}}N{{/if}}"))->toBe('N');
        // the fragment rule closes the whole family, not just the exact field name
        expect($filter->filter("{{if customer.getData('vault_token') == 'tok-123'}}Y{{else}}N{{/if}}"))->toBe('N');
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

    it('refuses a secret key inside an array returned by an allowed getter', function () {
        // Subscripting an array is native PHP: it never reaches __call()/__get(), so a secret
        // sitting one hop past a legitimately allowed getter would otherwise be readable under
        // a key the method name cannot reveal.
        $payment = new class extends DataObject {
            /** @return array<string, mixed> */
            public function getGatewayProfile(): array
            {
                return [
                    'method_title' => 'Check / Money order',
                    'vault_token_id' => 'tok_live_secret123',
                    'gateway' => ['cc_number' => '4111111111111111'],
                ];
            }
        };
        $order = new class ($payment) extends DataObject {
            public function __construct(private DataObject $payment)
            {
                parent::__construct();
            }

            public function getPayment(): DataObject
            {
                return $this->payment;
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);

        $token = "order.getPayment().getGatewayProfile()['vault_token_id']";
        expect($filter->filter("{{if {$token} == 'tok_live_secret123'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter("{{if {$token} == null}}Y{{else}}N{{/if}}"))->toBe('Y');

        // The gate follows nested arrays too
        $nested = "order.getPayment().getGatewayProfile()['gateway']['cc_number']";
        expect($filter->filter("{{if {$nested} == '4111111111111111'}}Y{{else}}N{{/if}}"))->toBe('N');

        // A benign key in the very same array stays readable
        $title = "order.getPayment().getGatewayProfile()['method_title']";
        expect($filter->filter("{{if {$title} == 'Check / Money order'}}Y{{else}}N{{/if}}"))->toBe('Y');
    });

    it('renders the else branch when an intermediate hop is absent', function () {
        // {{if order.shipping_address.company}} on a virtual order: the hop past null makes
        // Symfony throw, which must degrade to a false condition the way the legacy resolver did
        $order = new class extends DataObject {
            public function getShippingAddress(): ?DataObject
            {
                return null;
            }
        };
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter('{{if order.shipping_address.company}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($filter->filter('{{depend order.shipping_address.company}}X{{/depend}}'))->toBe('');
    });
});

/**
 * Symfony resolves "customer.foo()" with is_callable([$wrapper, 'foo']), which is satisfied by
 * any real public method of the wrapper — static ones included — without ever reaching the
 * gated __call(). A public helper on the class handed to the expression engine is therefore
 * directly callable from a template, and unwrap() in particular returns the raw guarded model.
 */
describe('wrapper surface exposed to expressions', function () {
    it('exposes nothing but gated magic methods on the wrapper', function () {
        expect(get_class_methods(ExpressionObjectWrapper::class))
            ->toEqualCanonicalizing(['__call', '__get', '__isset', '__toString']);
    });

    it('refuses to hand the raw model back through unwrap()', function () {
        $customer = new class extends DataObject {
            public function getPassword(): string
            {
                return 'hunter2';
            }
        };
        $filter = (new Template())->setVariables(['customer' => $customer]);

        expect($filter->filter('[{{var customer.unwrap(customer).getPassword()}}]'))->toBe('[]');
        expect($filter->filter("{{if customer.unwrap(customer).getPassword() == 'hunter2'}}Y{{else}}N{{/if}}"))->toBe('N');
        expect($filter->filter('[{{var customer.wrap(customer)}}]'))->toBe('[]');
        expect($filter->filter('[{{var customer.__construct(customer)}}]'))->toBe('[]');
    });
});

/**
 * PHP compares two objects of the same class structurally — property by property, ignoring
 * visibility — and every value the expression engine sees is an ExpressionObjectWrapper. A
 * comparison never reaches __get()/__call(), so the field gate cannot run on it: if the wrapper
 * held the guarded model in a property, "secret > probe" would recurse into both models' _data
 * arrays and answer from the raw values, turning any refused field into a bisection oracle.
 */
describe('comparing two wrapped values', function () {
    it('does not order two wrappers by the guarded objects data', function () {
        // Same two objects throughout, so only the guarded *data* changes between the two
        // renders: a comparison that answers from it flips, one that cannot read it does not.
        $a = new DataObject(['password' => 'aaaaaaa']);
        $b = new DataObject(['password' => 'zzzzzzz']);
        $filter = (new Template())->setVariables(['a' => $a, 'b' => $b]);

        $before = $filter->filter('{{if a > b}}Y{{else}}N{{/if}}');
        $a->setPassword('zzzzzzz');
        $b->setPassword('aaaaaaa');

        expect($filter->filter('{{if a > b}}Y{{else}}N{{/if}}'))->toBe($before);
    });

    it('does not equate two wrappers by the guarded objects data', function () {
        $filter = (new Template())->setVariables([
            'secret' => new DataObject(['password' => 'hunter2']),
            'guess' => new DataObject(['password' => 'hunter2']),
            'wrong' => new DataObject(['password' => 'aaaaaaa']),
        ]);

        // A structural comparison would answer Y for the matching guess, confirming the secret.
        expect($filter->filter('{{if secret == guess}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($filter->filter('{{if secret == wrong}}Y{{else}}N{{/if}}'))->toBe('N');
    });

    it('does not leak the ordering through min() and max() either', function () {
        // ExpressionLanguage registers min()/max() as the real PHP functions, which reach the
        // same comparison primitive without going through an operator.
        $a = new DataObject(['password' => 'aaaaaaa']);
        $b = new DataObject(['password' => 'zzzzzzz']);
        $filter = (new Template())->setVariables(['a' => $a, 'b' => $b]);

        $before = $filter->filter('{{if max(a, b) == a}}Y{{else}}N{{/if}}');
        $a->setPassword('zzzzzzz');
        $b->setPassword('aaaaaaa');

        expect($filter->filter('{{if max(a, b) == a}}Y{{else}}N{{/if}}'))->toBe($before);
    });

    it('leaves an object-to-object comparison unanswerable, even for one and the same object', function () {
        // The flip side of the rule above: no comparison of two wrapped values can consult what
        // they guard, so a template has to compare a field of each instead.
        $address = new DataObject(['entity_id' => 7, 'city' => 'Torino']);
        $order = new DataObject(['billing_address' => $address, 'shipping_address' => $address]);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter('{{if order.billing_address == order.shipping_address}}Y{{else}}N{{/if}}'))->toBe('N');
        expect($filter->filter('{{if order.billing_address.entity_id == order.shipping_address.entity_id}}Y{{else}}N{{/if}}'))
            ->toBe('Y');
    });

    it('keeps comparing a wrapped value against a literal', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['grand_total' => 150])]);

        expect($filter->filter('{{if order.grand_total > 100}}Y{{else}}N{{/if}}'))->toBe('Y');
        expect($filter->filter('{{if order.grand_total > 200}}Y{{else}}N{{/if}}'))->toBe('N');
    });
});

describe('block rendering methods', function () {
    it('refuses toHtml() even though it matches the read-only "to" prefix', function () {
        // Mage_Core_Block_Abstract extends DataObject, so a block reaching a template variable
        // would otherwise let a condition run a full block render.
        $block = new class extends DataObject {
            public function toHtml(): string
            {
                return '<b>rendered</b>';
            }
        };
        $filter = (new Template())->setVariables(['block' => $block]);

        expect($filter->filter('[{{var block.toHtml()}}]'))->toBe('[]');
    });

    it('refuses the get-prefixed block render helpers that reach the same _toHtml() path', function () {
        // getChildHtml()/getChildChildHtml()/getBlockHtml() carry the read-only "get" prefix but
        // trigger a full child-block render (observers, cache writes) exactly like toHtml(), so
        // they must be denied for the same must-not-mutate reason.
        $block = new class extends DataObject {
            public function getChildHtml(): string
            {
                return '<b>child</b>';
            }

            public function getChildChildHtml(): string
            {
                return '<b>grandchild</b>';
            }

            public function getBlockHtml(): string
            {
                return '<b>named</b>';
            }
        };
        $filter = (new Template())->setVariables(['block' => $block]);

        expect($filter->filter('[{{var block.getChildHtml()}}]'))->toBe('[]');
        expect($filter->filter('[{{var block.getChildChildHtml()}}]'))->toBe('[]');
        expect($filter->filter('[{{var block.getBlockHtml()}}]'))->toBe('[]');
    });
});

describe('arguments carrying a path', function () {
    it('keeps route and config path arguments working', function () {
        // The legacy resolver passed method arguments through untouched, so
        // {{var this.getUrl('customer/account/')}} resolved. Refusing every "/" argument broke
        // that; the nested-data concern it addressed belongs to the keyed data accessors alone.
        $block = new class extends DataObject {
            public function getUrl(string $route): string
            {
                return 'https://example.test/' . $route;
            }
        };
        $filter = (new Template())->setVariables(['block' => $block]);

        expect($filter->filter("{{var block.getUrl('customer/account/')}}"))
            ->toBe('https://example.test/customer/account/');
    });

    it('still refuses a slash key on a keyed data accessor', function () {
        $order = new DataObject([
            'address' => new DataObject(['cc_cid' => '123']),
        ]);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter("{{if order.getData('address/cc_cid') == '123'}}Y{{else}}N{{/if}}"))->toBe('N');
    });

    it('still refuses a secret field name passed as an argument', function () {
        $block = new class extends DataObject {
            public function getUrl(string $route): string
            {
                return 'https://example.test/' . $route;
            }
        };
        $filter = (new Template())->setVariables(['block' => $block]);

        expect($filter->filter("[{{var block.getUrl('cc_number')}}]"))->toBe('[]');
    });
});

describe('password reset templates', function () {
    it('resolves the reset token the account mails build their link from', function () {
        // {{store url="customer/account/resetpassword/" _query_token=$customer.rp_token}} —
        // refusing rp_token silently drops the token from the link, leaving the recipient
        // unable to reset their password.
        $customer = new DataObject(['rp_token' => 'reset-token-123', 'rp_customer_id' => 42]);
        $filter = (new Template())->setVariables(['customer' => $customer]);

        expect($filter->filter('{{var customer.rp_token}}'))->toBe('reset-token-123');
        expect($filter->filter('{{var customer.getRpToken()}}'))->toBe('reset-token-123');
        expect($filter->filter("{{var customer.getData('rp_token')}}"))->toBe('reset-token-123');
    });

    it('keeps refusing other token fields', function () {
        $customer = new DataObject(['vault_token_id' => 'tok_live_abc', 'api_token' => 'sk_live_abc']);
        $filter = (new Template())->setVariables(['customer' => $customer]);

        expect($filter->filter('[{{var customer.vault_token_id}}]'))->toBe('[]');
        expect($filter->filter('[{{var customer.api_token}}]'))->toBe('[]');
    });
});

/**
 * Mage_Core_Model_Observer::secureVarProcessing() refuses any model save or delete while the
 * varProcessing flag is registered. It is the net behind the wrapper's read-only method gate,
 * for a getter that looks like a read but persists on the way out. {{var}} has always set the
 * flag; conditions used to inherit it because ifDirective() resolved through _getVariable(),
 * and must keep it now that they evaluate through the expression engine instead.
 */
describe('save guard during condition evaluation', function () {
    it('registers varProcessing while an expression condition is evaluated', function () {
        $probe = new class extends DataObject {
            public ?bool $guardedDuringIf = null;
            public ?bool $guardedDuringDepend = null;

            public function getIfProbe(): string
            {
                $this->guardedDuringIf = (bool) Mage::registry('varProcessing');
                return 'x';
            }

            public function getDependProbe(): string
            {
                $this->guardedDuringDepend = (bool) Mage::registry('varProcessing');
                return 'x';
            }
        };
        /** @var Mage_Core_Model_Email_Template_Filter $filter */
        $filter = Mage::getModel('core/email_template_filter');
        $filter->setVariables(['o' => $probe]);

        $filter->filter('{{if o.getIfProbe()}}Y{{else}}N{{/if}}');
        $filter->filter('{{depend o.getDependProbe()}}X{{/depend}}');

        expect($probe->guardedDuringIf)->toBeTrue();
        expect($probe->guardedDuringDepend)->toBeTrue();
        // and the flag is always released again, including when the condition throws
        $filter->filter('{{if o.getIfProbe() >}}Y{{else}}N{{/if}}');
        expect(Mage::registry('varProcessing'))->toBeNull();
    });

    it('joins the guard already in force instead of aborting a nested render', function () {
        // Mage::register() throws when the key is taken, so a getter that filters a template of
        // its own would abort the inner render — and abort it before its own try block, leaving
        // the flag to whichever frame unwinds first. Re-entering must simply join the guard.
        $probe = new class extends DataObject {
            public ?bool $guardedInside = null;

            public function getNested(): string
            {
                /** @var Mage_Core_Model_Email_Template_Filter $inner */
                $inner = Mage::getModel('core/email_template_filter');
                $inner->setVariables(['x' => new DataObject(['a' => 'INNER'])]);
                $rendered = $inner->filter('{{var x.a}}');
                // the outer frame's guard is still up after the inner one has finished
                $this->guardedInside = (bool) Mage::registry('varProcessing');
                return $rendered;
            }
        };
        /** @var Mage_Core_Model_Email_Template_Filter $filter */
        $filter = Mage::getModel('core/email_template_filter');
        $filter->setVariables(['o' => $probe]);

        expect($filter->filter('[{{var o.getNested()}}]'))->toBe('[INNER]');
        expect($probe->guardedInside)->toBeTrue();
        expect(Mage::registry('varProcessing'))->toBeNull();

        expect($filter->filter('{{if o.getNested()}}Y{{else}}N{{/if}}'))->toBe('Y');
        expect(Mage::registry('varProcessing'))->toBeNull();
    });
});

describe('encrypted config subtree', function () {
    // Store::getConfig() returns a scalar for a leaf path and the whole subtree (an array) for an
    // ancestor path. The exact encrypted leaf was already neutralized; an ancestor request plus a
    // native subscript reached the decrypted leaf around that check.
    function stubStore(string $leaf, string $sentinel): DataObject
    {
        return new class ($leaf, $sentinel) extends DataObject {
            /** @var array<mixed> */
            private array $tree;

            public function __construct(string $leaf, string $sentinel)
            {
                parent::__construct();
                [$section, $group, $field] = explode('/', $leaf);
                $this->tree = [
                    $section => [$group => [$field => $sentinel, 'plain_sibling' => 'PLAIN']],
                    'general' => ['store_information' => ['name' => 'MyStore']],
                ];
            }

            public function getConfig($path)
            {
                if (!is_string($path) || $path === '') {
                    return null;
                }
                $node = $this->tree;
                foreach (explode('/', $path) as $segment) {
                    if (!is_array($node) || !array_key_exists($segment, $node)) {
                        return null;
                    }
                    $node = $node[$segment];
                }
                return $node;
            }
        };
    }

    it('does not leak an encrypted leaf reached through an ancestor subtree subscript', function () {
        $paths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();
        expect($paths)->not->toBeEmpty();
        [$section, $group, $field] = explode('/', $paths[0]);
        $store = stubStore($paths[0], 'ENC_SENTINEL');
        $filter = (new Template())->setVariables(['store' => $store]);

        // exact leaf, ancestor subtree, top-level subtree, concat-built key and oracle all closed
        expect($filter->filter("[{{var store.getConfig('{$section}/{$group}/{$field}')}}]"))->toBe('[]');
        expect($filter->filter("[{{var store.getConfig('{$section}/{$group}')['{$field}']}}]"))->toBe('[]');
        expect($filter->filter("[{{var store.getConfig('{$section}')['{$group}']['{$field}']}}]"))->toBe('[]');
        expect($filter->filter("[{{if store.getConfig('{$section}/{$group}')['{$field}'] starts with 'ENC'}}LEAK{{else}}-{{/if}}]"))->toBe('[-]');
    });

    it('still resolves a non-encrypted config value', function () {
        $paths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();
        $store = stubStore($paths[0], 'ENC_SENTINEL');
        $filter = (new Template())->setVariables(['store' => $store]);

        expect($filter->filter("[{{var store.getConfig('general/store_information/name')}}]"))->toBe('[MyStore]');
        expect($filter->filter("[{{var store.getConfig('general/store_information')['name']}}]"))->toBe('[MyStore]');
    });
});

describe('expression length cap', function () {
    it('rejects an over-long expression instead of crashing the parser', function () {
        // Symfony's recursive-descent parser SIGSEGVs on a deeply nested expression; the byte cap
        // refuses it before the parser can run into it. This expression would evaluate true (an
        // even number of "not"), so rendering the else branch proves it was refused, not evaluated.
        $filter = (new Template())->setVariables(['x' => 1]);
        $overLong = '{{if ' . str_repeat('not ', 600) . 'x}}Y{{else}}N{{/if}}';
        expect(strlen($overLong))->toBeGreaterThan(2000);
        expect($filter->filter($overLong))->toBe('N');
    });

    it('leaves a normal-length expression untouched', function () {
        $filter = (new Template())->setVariables(['order' => new DataObject(['grand_total' => 120.5])]);
        expect($filter->filter('{{if order.grand_total > 100}}Y{{else}}N{{/if}}'))->toBe('Y');
    });
});
