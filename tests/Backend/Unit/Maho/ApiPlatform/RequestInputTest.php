<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

uses(Tests\MahoBackendTestCase::class);

describe('RequestInput', function (): void {

    it('takes the first key that is set and not null, and null when there is none', function (): void {
        $input = RequestInput::of(['a' => null, 'b' => 'second', 'c' => 'third']);

        expect($input->string('a', 'b', 'c'))->toBe('second');
        expect($input->string('missing'))->toBeNull();
        expect(RequestInput::of([])->int('a'))->toBeNull();
    });

    it('reads strings, and numbers as their strings', function (): void {
        $input = RequestInput::of(['s' => 'abc', 'e' => '', 'i' => 12345, 'f' => 1.5]);

        expect($input->string('s'))->toBe('abc');
        expect($input->string('e'))->toBe('');
        expect($input->string('i'))->toBe('12345');
        expect($input->string('f'))->toBe('1.5');
    });

    it('reads integers and numbers, and strings of them', function (): void {
        $input = RequestInput::of(['i' => 7, 's' => '7', 'n' => '-3', 'w' => 4.0, 'f' => '2.5']);

        expect($input->int('i'))->toBe(7);
        expect($input->int('s'))->toBe(7);
        expect($input->int('n'))->toBe(-3);
        expect($input->int('w'))->toBe(4);
        expect($input->float('i'))->toBe(7.0);
        expect($input->float('f'))->toBe(2.5);
    });

    it('reads booleans as InputBag does', function (mixed $value, bool $expected): void {
        expect(RequestInput::of(['v' => $value])->bool('v'))->toBe($expected);
        expect((new InputBag(['v' => $value]))->getBoolean('v'))->toBe($expected);
    })->with([
        [true, true], [false, false],
        [1, true], [0, false],
        ['1', true], ['0', false],
        ['true', true], ['false', false],
        ['yes', true], ['no', false],
    ]);

    it('reads lists and rows', function (): void {
        $input = RequestInput::of([
            'mixed' => [1, 'a'],
            'strings' => ['a', 2],
            'ints' => [1, '2'],
            'object' => ['k' => 1],
            'rows' => [['qty' => 1], ['qty' => '2']],
        ]);

        expect($input->list('mixed'))->toBe([1, 'a']);
        expect($input->stringList('strings'))->toBe(['a', '2']);
        expect($input->intList('ints'))->toBe([1, 2]);
        expect($input->array('object'))->toBe(['k' => 1]);
        expect(array_map(fn(RequestInput $row): ?int => $row->int('qty'), $input->rows('rows')))->toBe([1, 2]);
    });

    it('decodes the JSON content whatever the content type says', function (): void {
        $json = Request::create('/', 'PUT', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"value":"1"}');
        $form = Request::create('/', 'PUT', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], '{"value":"1"}');

        expect(RequestInput::decode($json))->toBe(['value' => '1']);
        expect(RequestInput::decode($form))->toBe(['value' => '1']);
        expect(RequestInput::decode(Request::create('/', 'PUT', [], [], [], [], '[1,2]')))->toBe([1, 2]);
        expect(RequestInput::decode(Request::create('/', 'PUT')))->toBe([]);
        expect(RequestInput::decode(null))->toBe([]);
    });

    it('answers 400 for a body that is not a JSON object or array, or null when asked to try', function (string $content): void {
        $request = Request::create('/', 'PUT', [], [], [], [], $content);

        expect(fn() => RequestInput::decode($request))->toThrow(BadRequestHttpException::class, 'Invalid JSON in request body');
        expect(RequestInput::tryDecode($request))->toBeNull();
    })->with(['not JSON' => ['{oops'], 'a string' => ['"abc"'], 'a number' => ['12'], 'null' => ['null']]);

    it('converts like the shared readers', function (): void {
        expect(RequestInput::toString('a'))->toBe('a');
        expect(RequestInput::toString(5))->toBe('5');
        expect(RequestInput::toString(true))->toBeNull();
        expect(RequestInput::toString(['a']))->toBeNull();
        expect(RequestInput::toInt('12'))->toBe(12);
        expect(RequestInput::toInt(true))->toBeNull();
        expect(RequestInput::toInt('1.5'))->toBeNull();
        expect(RequestInput::toInt([1]))->toBeNull();
        expect(RequestInput::toFloat('1.5'))->toBe(1.5);
        expect(RequestInput::toFloat(false))->toBeNull();
    });

    it('throws the error of the serializer for a value of the wrong type', function (string $method, mixed $value, string $type, string $given): void {
        try {
            RequestInput::of(['first' => null, 'theKey' => $value])->$method('first', 'theKey');
            $this->fail('No exception');
        } catch (NotNormalizableValueException $e) {
            expect($e->getMessage())->toBe("The type of the \"theKey\" attribute must be \"{$type}\", \"{$given}\" given.");
            expect($e->getPath())->toBe('theKey');
            expect($e->canUseMessageForUser())->toBeTrue();
        }
    })->with([
        'a string as an array' => ['string', ['a'], 'string', 'array'],
        'a string as a boolean' => ['string', true, 'string', 'boolean'],
        'an integer as a word' => ['int', 'abc', 'int', 'string'],
        'an integer as a fraction' => ['int', 1.5, 'int', 'double'],
        'an integer as a boolean' => ['int', true, 'int', 'boolean'],
        'a number as a word' => ['float', 'abc', 'float', 'string'],
        'a boolean as a word' => ['bool', 'maybe', 'bool', 'string'],
        'a boolean as 2' => ['bool', 2, 'bool', 'integer'],
        'a list as a string' => ['list', 'a', 'list', 'string'],
        'a list as an object' => ['list', ['k' => 1], 'list', 'array'],
        'an object as a string' => ['array', 'a', 'array', 'string'],
    ]);

    it('names the place of a wrong entry in the request', function (): void {
        $input = RequestInput::of([
            'ids' => [1, 'abc'],
            'rows' => [['qty' => 1], ['qty' => 'abc']],
            'notRows' => ['x'],
        ]);

        expect(fn() => $input->intList('ids'))->toThrow(NotNormalizableValueException::class, 'The type of the "ids[1]" attribute');
        expect(fn() => RequestInput::of(['ids' => [1, null]])->intList('ids'))
            ->toThrow(NotNormalizableValueException::class, 'The type of the "ids[1]" attribute must be "int", "NULL" given.');
        expect(fn() => RequestInput::of(['roles' => [null, 'image']])->stringList('roles'))
            ->toThrow(NotNormalizableValueException::class, 'The type of the "roles[0]" attribute must be "string", "NULL" given.');

        $rows = $input->rows('rows');
        expect(fn() => $rows[1]->int('qty'))->toThrow(NotNormalizableValueException::class, 'The type of the "rows[1].qty" attribute');
        expect(fn() => $input->rows('notRows'))->toThrow(NotNormalizableValueException::class, 'The type of the "notRows[0]" attribute');

        $row = RequestInput::of(['price' => 'abc'], '[2]');
        expect(fn() => $row->float('price'))->toThrow(NotNormalizableValueException::class, 'The type of the "[2].price" attribute');
    });

});
