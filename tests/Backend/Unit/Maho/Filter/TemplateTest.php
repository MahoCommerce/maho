<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe(\Maho\Filter\Template::class, function () {
    beforeEach(function () {
        $this->filter = new \Maho\Filter\Template();
        $this->filter->setVariables(['name' => 'Ada', 'empty' => '']);
    });

    test('an if block renders its body when the variable has a value', function () {
        expect($this->filter->filter('{{if name}}Hi {{var name}}{{else}}Hi guest{{/if}}'))->toBe('Hi Ada');
    });

    test('an if block renders its else body when the variable is empty', function () {
        expect($this->filter->filter('{{if empty}}Hi {{var name}}{{else}}Hi guest{{/if}}'))->toBe('Hi guest');
    });

    test('a depend block renders only when the variable has a value', function () {
        expect($this->filter->filter('[{{depend name}}{{var name}}{{/depend}}][{{depend empty}}x{{/depend}}]'))->toBe('[Ada][]');
    });

    test('each occurrence of a repeated directive renders', function () {
        expect($this->filter->filter('{{var name}} and {{var name}}'))->toBe('Ada and Ada');
    });

    test('an unknown directive stays as literal text', function () {
        expect($this->filter->filter('a {{unknown x}} b'))->toBe('a {{unknown x}} b');
    });

    test('a null value renders as an empty string', function () {
        expect($this->filter->filter(null))->toBe('');
    });
});
