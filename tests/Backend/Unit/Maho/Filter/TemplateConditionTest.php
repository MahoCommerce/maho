<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Filter
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
});
