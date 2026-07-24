<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\DataObject;
use Maho\Filter\Template;

uses(Tests\MahoBackendTestCase::class);

/**
 * The legacy {{var}} object-path resolver historically called any existing method
 * (method_exists() was a free pass), so a template could invoke a state-changing method
 * on a passed-in model. Resolution is now limited to read-only accessors.
 */
describe('{{var}} method gate', function () {
    function spy(): DataObject
    {
        return new class extends DataObject {
            public bool $deleted = false;
            public bool $saved = false;
            public bool $flagged = false;

            public function delete(): string
            {
                $this->deleted = true;
                return 'DELETED';
            }

            public function save(): string
            {
                $this->saved = true;
                return 'SAVED';
            }

            public function cancel(): string
            {
                return 'CANCELLED';
            }

            public function isDeleted($flag = null): string
            {
                if ($flag !== null) {
                    $this->flagged = (bool) $flag;
                }
                return 'is-deleted';
            }

            public function getName(): string
            {
                return 'Bob';
            }

            public function format(string $type): string
            {
                return "formatted-{$type}";
            }
        };
    }

    it('does not execute a state-changing method', function () {
        $o = spy();
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.delete()}}]'))->toBe('[]');
        expect($o->deleted)->toBeFalse();

        expect($filter->filter('[{{var o.save()}}]'))->toBe('[]');
        expect($o->saved)->toBeFalse();

        expect($filter->filter('[{{var o.cancel()}}]'))->toBe('[]');
    });

    it('refuses an argument to an is/can predicate so a getter-shaped mutator cannot toggle state', function () {
        $o = spy();
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.isDeleted(1)}}]'))->toBe('[]');
        expect($o->flagged)->toBeFalse();
    });

    it('still resolves read-only accessors, including arg-bearing formatters', function () {
        $o = spy();
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.getName()}}]'))->toBe('[Bob]');
        expect($filter->filter('[{{var o.format(\'html\')}}]'))->toBe('[formatted-html]');
    });

    it('resolves a property path, an arg-bearing getter, and defaults an absent variable', function () {
        $o = new class extends DataObject {
            public function getAttributeText(string $code): string
            {
                return "label-{$code}";
            }
        };
        $o->setData('increment_id', '100000042');
        $filter = (new Template())->setVariables(['o' => $o]);

        // property path resolves through the getter/getData chain
        expect($filter->filter('[{{var o.increment_id}}]'))->toBe('[100000042]');
        // an argument to a read getter is allowed (the {{if}}/{{var}} arg policy)
        expect($filter->filter('[{{var o.getAttributeText(\'color\')}}]'))->toBe('[label-color]');
        // an absent variable renders empty rather than failing the parse
        expect($filter->filter('[{{var missing}}]'))->toBe('[]');
    });

    it('does not dump the whole object through a bare accessor', function () {
        $o = new DataObject(['secret_note' => 'x', 'firstname' => 'Bob']);
        $filter = (new Template())->setVariables(['o' => $o]);

        // getData()/get() with no key would hand back the entire _data array
        expect($filter->filter('[{{var o.getData()}}]'))->toBe('[]');
        // a keyed read of a benign field still works
        expect($filter->filter('[{{var o.getData(\'firstname\')}}]'))->toBe('[Bob]');
    });

    it('does not print a secret key from an array returned by an allowed getter', function () {
        // Array subscripting bypasses the wrapper's method/property gate entirely, so the
        // field-name rule has to be applied to the array's keys when the array is wrapped
        $o = new class extends DataObject {
            /** @return array<string, mixed> */
            public function getPayload(): array
            {
                return ['method_title' => 'Check / Money order', 'vault_token_id' => 'tok_live_secret123'];
            }
        };
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter("[{{var o.getPayload()['vault_token_id']}}]"))->toBe('[]');
        expect($filter->filter("[{{var o.getPayload()['method_title']}}]"))->toBe('[Check / Money order]');
    });

    it('resolves a method under the casing the class declares, not the casing the template spells', function () {
        // PHP dispatches method names case-insensitively, so "canCel" reaches cancel() and
        // "getPassWord" reaches getPassword(). Both gates read camelCase word boundaries out of
        // the name, so a faked boundary would turn cancel() into a "can" predicate and password
        // into the unlisted field "pass_word".
        $o = new class extends DataObject {
            public bool $cancelled = false;

            public function cancel(): string
            {
                $this->cancelled = true;
                return 'CANCELLED';
            }

            public function getPassword(): string
            {
                return 'hunter2';
            }
        };
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.canCel()}}]'))->toBe('[]');
        expect($o->cancelled)->toBeFalse();
        expect($filter->filter('[{{var o.getPassWord()}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.PassWord}}]'))->toBe('[]');
        expect($filter->filter('[{{if o.getPassWord() > \'a\'}}LEAK{{else}}-{{/if}}]'))->toBe('[-]');
    });

    it('refuses the free-form gateway bags on a payment info object', function () {
        $o = new class extends DataObject {
            /** @return array<string, mixed> */
            public function getAdditionalInformation(): array
            {
                return ['method_title' => 'PayPal', 'cvv_code' => 'M', 'avs_code' => 'Y', 'payer_email' => 'a@b.c'];
            }

            public function getAdditionalData(): string
            {
                return 'serialized-gateway-blob';
            }
        };
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter("[{{var o.getAdditionalInformation()['cvv_code']}}]"))->toBe('[]');
        expect($filter->filter("[{{var o.getAdditionalInformation()['method_title']}}]"))->toBe('[]');
        expect($filter->filter('[{{var o.getAdditionalInformation()}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.additional_information}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.getAdditionalData()}}]'))->toBe('[]');
    });

    it('refuses cardholder data but keeps the masked display fields readable', function () {
        $o = new DataObject([
            'cc_owner' => 'Ada Lovelace',
            'cc_exp_month' => '11',
            'cc_exp_year' => '2031',
            'cc_ss_issue' => '3',
            'cc_number' => '4111111111111111',
            'cc_cid' => '737',
            'cc_type' => 'VI',
            'cc_last4' => '1111',
        ]);
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.cc_owner}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.getCcOwner()}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.getCcExpMonth()}}/{{var o.getCcExpYear()}}]'))->toBe('[/]');
        expect($filter->filter('[{{var o.getCcSsIssue()}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.getData(\'cc_owner\')}}]'))->toBe('[]');
        // the two fields Magento stores unencrypted precisely so a template can print them
        expect($filter->filter('[{{var o.cc_type}} ****{{var o.cc_last4}}]'))->toBe('[VI ****1111]');
    });

    it('renders an object with no string form as empty instead of raising a native string-cast error', function () {
        $o = new DataObject(['child' => new DataObject(['company' => 'ACME'])]);
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.child}}]'))->toBe('[]');
    });

    it('refuses a secret whose getter spells the field as an acronym', function () {
        // A gateway module declaring getCVVCode()/getCCNumber() must not read as the fields
        // "c_v_v_code"/"c_c_number", which no gate names: the acronym has to normalize to
        // cvv_code/cc_number so the fragment and cc_* prefix rules still see it. The property
        // form matters as much as the call form — __get() canonicalizes "cvv_code" to the
        // declared getCVVCode() before gating, so a per-capital split would open both.
        $o = new class extends DataObject {
            public function getCVVCode(): string
            {
                return '737';
            }

            public function getCCNumber(): string
            {
                return '4111111111111111';
            }

            public function getAPIKey(): string
            {
                return 'sk_live_secret';
            }

            public function getOPCheckoutUrl(): string
            {
                return 'https://example.test/checkout';
            }
        };
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.getCVVCode()}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.cvv_code}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.getCCNumber()}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.cc_number}}]'))->toBe('[]');
        expect($filter->filter('[{{var o.getAPIKey()}}]'))->toBe('[]');
        expect($filter->filter('[{{if o.getCVVCode() starts with \'7\'}}LEAK{{else}}-{{/if}}]'))->toBe('[-]');
        // an acronym getter that names nothing sensitive keeps working
        expect($filter->filter('[{{var o.getOPCheckoutUrl()}}]'))->toBe('[https://example.test/checkout]');
    });

    it('refuses a nested a/b/c path smuggled past the keyed-accessor gate as a second argument', function () {
        // DataObject::getData($key, $index) forwards $index to the nested object's own getData(),
        // which resolves a "/" key through getDataByPath(). The field gate only ever sees the
        // whole path string, whose anchored rules (the cc_ prefix, the additional_* bags) then
        // never fire — so the traversal getDataByPath() is denied for has to be closed here too.
        $payment = new DataObject([
            'additional_information' => ['cards' => [['cc_number' => '4111111111111111']], 'payer_email' => 'a@b.c'],
            'method' => 'checkmo',
        ]);
        $order = new DataObject(['payment' => $payment, 'increment_id' => '100000042']);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter("[{{var order.getData('payment','additional_information/cards/0/cc_number')}}]"))->toBe('[]');
        expect($filter->filter("[{{var order.getData('payment','additional_information/payer_email')}}]"))->toBe('[]');
        expect($filter->filter("[{{var order.getOrigData('payment','additional_information/payer_email')}}]"))->toBe('[]');
        // only the "/" traversal is closed: a slash-free index is an ordinary keyed read of the
        // nested object, gated on both names exactly as the equivalent order.payment.method is
        expect($filter->filter("[{{var order.getData('payment','method')}}]"))->toBe('[checkmo]');
        // and the path form is no oracle either
        expect($filter->filter("[{{if order.getData('payment','additional_information/payer_email') ends with 'b.c'}}LEAK{{else}}-{{/if}}]"))->toBe('[-]');
        // a plain keyed read of a benign field still works
        expect($filter->filter("[{{var order.getData('increment_id')}}]"))->toBe('[100000042]');
    });

    it('refuses a nested a/b/c path smuggled through a magic getter argument', function () {
        // DataObject::__call() rewrites an undeclared getFoo($x) to getData('foo', $x), so a
        // magic getter reaches the same traversal without naming a keyed accessor at all.
        $payment = new DataObject(['additional_information' => ['vault' => ['cc_number' => '4111111111111111']]]);
        $order = new DataObject(['payment' => $payment]);
        $filter = (new Template())->setVariables(['order' => $order]);

        expect($filter->filter("[{{var order.getPayment('additional_information/vault/cc_number')}}]"))->toBe('[]');
        expect($filter->filter("[{{depend order.getPayment('additional_information/vault/cc_number')}}LEAK{{/depend}}]"))->toBe('[]');
    });

    it('keeps a "/" argument working for a declared method, where it is a route or config path', function () {
        // The "/" refusal is scoped to calls that land in DataObject::getData(): every route and
        // config path is spelled with slashes, so a real method must keep taking them.
        $o = new class extends DataObject {
            public function getUrl(string $route): string
            {
                return "https://example.test/{$route}";
            }
        };
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter("[{{var o.getUrl('customer/account/')}}]"))->toBe('[https://example.test/customer/account/]');
    });

    it('renders an array-valued path as empty instead of the literal text "Array"', function () {
        $o = new DataObject(['sizes' => ['S', 'M', 'L']]);
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.sizes}}]'))->toBe('[]');
    });

    it('degrades a self-referential array instead of exhausting memory', function () {
        $cyclic = ['label' => 'ok'];
        $cyclic['self'] = &$cyclic;
        $o = new DataObject(['bag' => $cyclic]);
        $filter = (new Template())->setVariables(['o' => $o]);

        expect($filter->filter('[{{var o.bag[\'label\']}}]'))->toBe('[ok]');
    });
});

/**
 * The cc_* card prefix already closes cardholder data; the eCheck/ACH bank family
 * (echeck_routing_number, echeck_account_name, ...) is equally sensitive and lives on the
 * payment object handed to order emails, so the "echeck" fragment closes it the same way.
 */
describe('eCheck bank data gate', function () {
    it('refuses the echeck bank family by property, getter and getData', function () {
        $payment = new DataObject([
            'echeck_routing_number' => 'SECRET_ROUTING',
            'echeck_account_name' => 'SECRET_ACCT',
            'echeck_bank_name' => 'SECRET_BANK',
        ]);
        $filter = (new Template())->setVariables(['payment' => $payment]);

        expect($filter->filter('[{{var payment.echeck_routing_number}}]'))->toBe('[]');
        expect($filter->filter('[{{var payment.getEcheckRoutingNumber()}}]'))->toBe('[]');
        expect($filter->filter("[{{var payment.getData('echeck_bank_name')}}]"))->toBe('[]');
    });

    it('does not let the echeck family become a bisection oracle', function () {
        $payment = new DataObject(['echeck_routing_number' => 'SECRET_ROUTING']);
        $filter = (new Template())->setVariables(['payment' => $payment]);

        expect($filter->filter('[{{if payment.echeck_routing_number starts with "SEC"}}LEAK{{else}}-{{/if}}]'))->toBe('[-]');
    });
});

/**
 * A boolean is/has/can predicate answers a yes/no status, never the underlying value, so a
 * SENSITIVE_FIELD_FRAGMENTS hit in its name must not refuse it — the property path already
 * exempts an is_/has_/can_ field, and the method path has to agree.
 */
describe('boolean predicate exemption', function () {
    it('allows an is/has/can predicate whose name contains a sensitive fragment', function () {
        $model = new class extends DataObject {
            public function isMagicLinkTokenExpired(): bool
            {
                return true;
            }
        };
        $filter = (new Template())->setVariables(['customer' => $model]);

        expect($filter->filter('{{if customer.isMagicLinkTokenExpired()}}HIT{{else}}MISS{{/if}}'))->toBe('HIT');
    });

    it('still refuses a get-prefixed secret getter and an argument-bearing predicate', function () {
        $model = new class extends DataObject {
            public function getPassword(): string
            {
                return 'SECRET_PW';
            }

            public bool $toggled = false;

            public function isDeleted($flag = null): bool
            {
                if ($flag !== null) {
                    $this->toggled = (bool) $flag;
                }
                return false;
            }
        };
        $filter = (new Template())->setVariables(['customer' => $model]);

        expect($filter->filter('[{{var customer.getPassword()}}]'))->toBe('[]');
        // an argument to a predicate is a state toggle and stays refused; the flag never flips
        expect($filter->filter('[{{if customer.isDeleted(1)}}X{{else}}Y{{/if}}]'))->toBe('[Y]');
        expect($model->toggled)->toBeFalse();
    });
});
