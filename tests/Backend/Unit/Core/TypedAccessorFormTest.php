<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Data\Form;
use Maho\Data\Form\Element\Checkbox;
use Maho\Data\Form\Element\Checkboxes;
use Maho\Data\Form\Element\Date;
use Maho\Data\Form\Element\Multiline;
use Maho\Data\Form\Element\Select;
use Maho\Data\Form\Element\Text;

uses(Tests\MahoBackendTestCase::class);

/**
 * A form element takes most of its data from the config array of addField(),
 * which writes the data without a setter, so the getters must cast that input.
 */

it('returns typed values from the config array of a field', function () {
    $form = new Form();
    $text = $form->addField('name', 'text', ['label' => 'Name', 'required' => 1, 'no_span' => 1, 'class' => 'wide']);
    $lines = $form->addField('street', 'multiline', [])->setData('line_count', '3');
    $checkbox = $form->addField('agree', 'checkbox', ['checked' => 'checked']);
    $date = $form->addField('dob', 'date', ['time' => 1, 'format' => 'Y-m-d']);

    expect($text)->toBeInstanceOf(Text::class)
        ->and($text->getLabel())->toBe('Name')
        ->and($text->getRequired())->toBeTrue()
        ->and($text->getNoSpan())->toBeTrue()
        ->and($text->getClass())->toBe('wide')
        ->and($lines)->toBeInstanceOf(Multiline::class)
        ->and($lines->getLineCount())->toBe(3)
        ->and($checkbox)->toBeInstanceOf(Checkbox::class)
        ->and($checkbox->getChecked())->toBeTrue()
        ->and($date)->toBeInstanceOf(Date::class)
        ->and($date->getTime())->toBeTrue()
        ->and($date->getFormat())->toBe('Y-m-d');
});

it('returns null for a key the element does not hold', function () {
    $text = new Form()->addField('name', 'text', []);

    expect($text->getLabel())->toBeNull()
        ->and($text->getRequired())->toBeNull()
        ->and($text->getClass())->toBeNull()
        ->and($text->getValues())->toBeNull();
});

it('keeps the default line count of a multiline element', function () {
    expect(new Multiline()->getLineCount())->toBe(2);
});

it('keeps the options of a select and the option list of a checkbox list as given', function () {
    $form = new Form();
    $select = $form->addField('color', 'select', ['options' => ['1' => 'Red', '2' => 'Blue']]);
    $list = $form->addField('flags', 'checkboxes', ['values' => [['value' => 'one', 'label' => 'One']]]);

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->getOptions())->toBe(['1' => 'Red', '2' => 'Blue'])
        ->and($list)->toBeInstanceOf(Checkboxes::class)
        ->and($list->getValues())->toBe([['value' => 'one', 'label' => 'One']]);
});

it('builds the html id and the name from the typed form settings', function () {
    $form = new Form();
    $form->setHtmlIdPrefix('rule_')->setFieldNameSuffix('rule');
    $text = $form->addField('title', 'text', ['name' => 'title']);

    expect($form->getHtmlIdPrefix())->toBe('rule_')
        ->and($form->getHtmlIdSuffix())->toBeNull()
        ->and($text->getHtmlId())->toBe('rule_title')
        ->and($text->getName())->toBe('rule[title]');
});

it('renders a disabled element after a flag setter call', function () {
    $text = new Form()->addField('code', 'text', ['name' => 'code']);
    $text->setDisabled()->setRequired(false);

    expect($text->getElementHtml())->toContain('disabled="disabled"')
        ->and($text->getRequired())->toBeFalse();
});

it('holds the data object of a form', function () {
    $product = Mage::getModel('catalog/product');
    $form = new Form()->setDataObject($product)->setUseContainer();

    expect($form->getDataObject())->toBe($product)
        ->and($form->getUseContainer())->toBeTrue();
});

it('accepts an int element id and an int timestamp in the strict form classes', function () {
    $form = new Form();
    $form->addField('5', 'text', ['name' => 'five']);
    $form->addValues([5 => 'x']);
    $date = $form->addField('when', 'date', ['format' => 'Y-m-d']);
    $date->setValue(86400);

    expect($form->getElement('5')->getValue())->toBe('x')
        ->and($date->getValue())->toBe('1970-01-02');
});
