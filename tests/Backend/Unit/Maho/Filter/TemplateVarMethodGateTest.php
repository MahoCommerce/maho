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
});
