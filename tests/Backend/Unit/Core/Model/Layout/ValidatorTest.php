<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class LayoutValidatorTestCompleteBlock extends Mage_Install_Block_Complete {}

describe(Mage_Core_Model_Layout_Validator::class, function () {
    beforeEach(function () {
        $this->validator = Mage::getModel('core/layout_validator');
    });

    it('keeps a valid layout update valid', function (string $xml) {
        expect($this->validator->isValid($xml))->toBeTrue()
            ->and($this->validator->getMessages())->toBe([]);
    })->with([
        'action without helper' => ['<reference name="content"><action method="setText"><text>x</text></action></reference>'],
        'block by alias' => ['<reference name="content"><block type="core/text" name="t"/></reference>'],
        'block by class name' => ['<reference name="content"><block type="Mage_Core_Block_Text" name="t"/></reference>'],
        'method that only contains settemplate' => ['<reference name="content"><action method="unsetTemplateData"><t>x</t></action></reference>'],
        'setDataUsingMethod without template' => ['<reference name="content"><action method="setDataUsingMethod"><k>title</k><v>x</v></action></reference>'],
        'remove' => ['<remove name="right.poll"/>'],
        'renderer with an allowed block' => ['<reference name="product.info.options"><action method="addOptionRenderer"><a>text</a><b>catalog/product_view_options_type_text</b></action></reference>'],
    ]);

    it('refuses a namespaced element or attribute', function (string $xml) {
        expect($this->validator->isValid($xml))->toBeFalse()
            ->and($this->validator->getMessages())->toBe(['XML data is invalid.']);
    })->with([
        'default namespace on action with a helper argument' => ['<reference name="content"><action xmlns="urn:x" method="setText"><text helper="core/string/truncate"/></action></reference>'],
        'default namespace on reference with a helper argument' => ['<reference xmlns="urn:x" name="content"><action method="setText"><text helper="core/string/truncate"/></action></reference>'],
        'default namespace on reference' => ['<reference xmlns="urn:x" name="content"><action method="setText"><text>x</text></action></reference>'],
        'prefixed action' => ['<reference xmlns:n="urn:x" name="content"><n:action method="setText"><text helper="a/b"/></n:action></reference>'],
        'default namespace on a disallowed block' => ['<block xmlns="urn:x" type="Mage_Rss_Block_Order_New" name="z"/>'],
        'default namespace on block with a template' => ['<block xmlns="urn:x" type="core/template" name="t" template="../x.phtml"/>'],
        'default namespace on a template element' => ['<reference name="content"><action method="setDataUsingMethod"><template xmlns="urn:x">x</template></action></reference>'],
        'default namespace on setTemplate' => ['<reference name="content"><action xmlns="urn:x" method="setTemplate"><t>page/x.phtml</t></action></reference>'],
        'prefixed attribute' => ['<reference xmlns:n="urn:x" name="content" n:foo="bar"/>'],
    ]);

    it('refuses a helper argument', function () {
        expect($this->validator->isValid('<reference name="content"><action method="setText"><text helper="core/string/truncate"/></action></reference>'))->toBeFalse()
            ->and($this->validator->getMessages())->toBe(['Helper attributes should not be used in custom layout updates.']);
    });

    it('refuses a disallowed block in any form that the layout instantiates', function (string $xml) {
        expect($this->validator->isValid($xml))->toBeFalse()
            ->and($this->validator->getMessages())->toBe(['Disallowed block name for frontend.']);
    })->with([
        'class name' => ['<block type="Mage_Rss_Block_Order_New" name="z"/>'],
        'class name in lower case' => ['<block type="mage_rss_block_order_new" name="z"/>'],
        'alias' => ['<block type="rss/order_new" name="z"/>'],
        'alias with a capital letter' => ['<block type="Rss/order_new" name="z"/>'],
        'alias in upper case' => ['<block type="RSS/ORDER_NEW" name="z"/>'],
        'leading backslash' => ['<block type="\\Mage_Rss_Block_Order_New" name="z"/>'],
        'class attribute' => ['<block type="core/text" class="Mage_Rss_Block_Order_New" name="z"/>'],
        'install alias' => ['<block type="install/complete" name="z"/>'],
        'install class in the class attribute' => ['<block type="core/text" class="\\mage_install_block_complete" name="z"/>'],
        'subclass' => ['<block type="LayoutValidatorTestCompleteBlock" name="z"/>'],
        'subclass in the class attribute' => ['<block type="core/text" class="layoutvalidatortestcompleteblock" name="z"/>'],
        'action argument as alias' => ['<reference name="product.info.options"><action method="addOptionRenderer"><a>text</a><b>rss/order_new</b></action></reference>'],
        'action argument as class name' => ['<reference name="content"><action method="addItemRender"><a>simple</a><b>Mage_Rss_Block_Order_New</b></action></reference>'],
    ]);

    it('refuses setTemplate and setDataUsingMethod with a template in any case', function (string $xml) {
        expect($this->validator->isValid($xml))->toBeFalse()
            ->and($this->validator->getMessages())->toBe(['Invalid template path used in layout update.']);
    })->with([
        'setTemplate' => ['<reference name="content"><action method="setTemplate"><t>page/x.phtml</t></action></reference>'],
        'lower case' => ['<reference name="content"><action method="settemplate"><t>../x.phtml</t></action></reference>'],
        'upper case' => ['<reference name="content"><action method="SETTEMPLATE"><t>../x.phtml</t></action></reference>'],
        'Class::method' => ['<reference name="content"><action method="Mage_Core_Block_Template::setTemplate"><t>../x.phtml</t></action></reference>'],
        'Class::method in lower case' => ['<reference name="content"><action method="mage_core_block_template::settemplate"><t>../x.phtml</t></action></reference>'],
        'setDataUsingMethod in mixed case' => ['<reference name="content"><action method="SetDataUsingMethod"><k>Template</k><v>../x.phtml</v></action></reference>'],
        'setDataUsingMethod as Class::method' => ['<reference name="content"><action method="Mage_Core_Block_Abstract::setdatausingmethod"><k>TEMPLATE</k><v>../x.phtml</v></action></reference>'],
        'template attribute' => ['<reference name="content"><block type="core/template" name="t" template="page/x.phtml"/></reference>'],
        'setDataUsingMethod with an underscore' => ['<reference name="content"><action method="setDataUsingMethod"><k>tem_plate</k><v>../x.phtml</v></action></reference>'],
        'setDataUsingMethod with a space' => ['<reference name="content"><action method="setDataUsingMethod"><k>tem plate</k><v>../x.phtml</v></action></reference>'],
    ]);

    it('refuses the json attribute of an action', function (string $xml) {
        expect($this->validator->isValid($xml))->toBeFalse()
            ->and($this->validator->getMessages())->toBe(['Custom layout updates cannot use the json attribute.']);
    })->with([
        'block type in a json argument' => ['<reference name="product.info.options"><action method="addOptionRenderer" json="b"><a>text</a><b>"rss\/order_new"</b><c>x</c></action></reference>'],
        'template key in a json argument' => ['<reference name="content"><action method="setDataUsingMethod" json="k"><k>"\u0074emplate"</k><v>page/x.phtml</v></action></reference>'],
    ]);

    it('refuses invalid XML', function () {
        expect($this->validator->isValid('<reference name="content">'))->toBeFalse()
            ->and($this->validator->getMessages())->toBe(['XML data is invalid.']);
    });
});
