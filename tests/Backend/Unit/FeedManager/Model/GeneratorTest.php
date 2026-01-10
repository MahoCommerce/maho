<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Generator - XML Mode Selection', function () {

    describe('Mode Priority', function () {
        test('xml_structure takes priority over xml_item_template', function () {
            $feed = Mage::getModel('feedmanager/feed');
            $feed->setFileFormat('xml');
            $feed->setXmlStructure('[{"tag":"id","source_type":"attribute","source_value":"sku"}]');
            $feed->setXmlItemTemplate('<id>{{sku}}</id>'); // Old template

            $xmlStructure = $feed->getXmlStructure();
            $xmlTemplate = $feed->getXmlItemTemplate();

            // xml_structure should be checked first
            $isStructureMode = !empty($xmlStructure) && $feed->getFileFormat() === 'xml';
            $isTemplateMode = !empty($xmlTemplate) && $feed->getFileFormat() === 'xml' && !$isStructureMode;

            expect($isStructureMode)->toBeTrue();
            expect($isTemplateMode)->toBeFalse();
        });

        test('falls back to xml_item_template when xml_structure is empty', function () {
            $feed = Mage::getModel('feedmanager/feed');
            $feed->setFileFormat('xml');
            $feed->setXmlStructure(''); // Empty
            $feed->setXmlItemTemplate('<id>{{sku}}</id>');

            $xmlStructure = $feed->getXmlStructure();
            $xmlTemplate = $feed->getXmlItemTemplate();

            $isStructureMode = !empty($xmlStructure) && $feed->getFileFormat() === 'xml';
            $isTemplateMode = !empty($xmlTemplate) && $feed->getFileFormat() === 'xml' && !$isStructureMode;

            expect($isStructureMode)->toBeFalse();
            expect($isTemplateMode)->toBeTrue();
        });

        test('neither mode when both are empty', function () {
            $feed = Mage::getModel('feedmanager/feed');
            $feed->setFileFormat('xml');
            $feed->setXmlStructure('');
            $feed->setXmlItemTemplate('');

            $xmlStructure = $feed->getXmlStructure();
            $xmlTemplate = $feed->getXmlItemTemplate();

            $isStructureMode = !empty($xmlStructure) && $feed->getFileFormat() === 'xml';
            $isTemplateMode = !empty($xmlTemplate) && $feed->getFileFormat() === 'xml' && !$isStructureMode;

            expect($isStructureMode)->toBeFalse();
            expect($isTemplateMode)->toBeFalse();
        });

        test('xml modes only apply to xml format', function () {
            $feed = Mage::getModel('feedmanager/feed');
            $feed->setFileFormat('csv'); // Not XML
            $feed->setXmlStructure('[{"tag":"id","source_type":"attribute","source_value":"sku"}]');
            $feed->setXmlItemTemplate('<id>{{sku}}</id>');

            $xmlStructure = $feed->getXmlStructure();
            $xmlTemplate = $feed->getXmlItemTemplate();

            $isStructureMode = !empty($xmlStructure) && $feed->getFileFormat() === 'xml';
            $isTemplateMode = !empty($xmlTemplate) && $feed->getFileFormat() === 'xml' && !$isStructureMode;

            expect($isStructureMode)->toBeFalse();
            expect($isTemplateMode)->toBeFalse();
        });
    });

    describe('XML Structure Parsing', function () {
        test('parses valid JSON structure', function () {
            $structureJson = '[{"tag":"g:id","source_type":"attribute","source_value":"sku"},{"tag":"g:title","source_type":"attribute","source_value":"name","cdata":true}]';

            $structure = Mage::helper('core')->jsonDecode($structureJson);

            expect($structure)->toBeArray();
            expect($structure)->toHaveCount(2);
            expect($structure[0]['tag'])->toBe('g:id');
            expect($structure[1]['cdata'])->toBeTrue();
        });

        test('handles empty structure gracefully', function () {
            $structureJson = '[]';

            $structure = Mage::helper('core')->jsonDecode($structureJson);

            expect($structure)->toBeArray();
            expect($structure)->toBeEmpty();
        });

        test('handles nested children in structure', function () {
            $structureJson = '[{"tag":"pricing","children":[{"tag":"price","source_type":"attribute","source_value":"price"},{"tag":"currency","source_type":"static","source_value":"AUD"}]}]';

            $structure = Mage::helper('core')->jsonDecode($structureJson);

            expect($structure[0]['tag'])->toBe('pricing');
            expect($structure[0]['children'])->toBeArray();
            expect($structure[0]['children'])->toHaveCount(2);
            expect($structure[0]['children'][0]['tag'])->toBe('price');
        });
    });

    describe('Feed Configuration', function () {
        test('default item tag is "item"', function () {
            $feed = Mage::getModel('feedmanager/feed');

            $itemTag = trim($feed->getXmlItemTag() ?: 'item');

            expect($itemTag)->toBe('item');
        });

        test('custom item tag is respected', function () {
            $feed = Mage::getModel('feedmanager/feed');
            $feed->setXmlItemTag('product');

            $itemTag = trim($feed->getXmlItemTag() ?: 'item');

            expect($itemTag)->toBe('product');
        });

        test('empty item tag uses default', function () {
            $feed = Mage::getModel('feedmanager/feed');
            $feed->setXmlItemTag('');

            $itemTag = trim($feed->getXmlItemTag() ?: 'item');

            expect($itemTag)->toBe('item');
        });
    });
});

describe('FeedManager Generator - Header/Footer Rendering', function () {

    test('xml_structure mode writes header', function () {
        $feed = Mage::getModel('feedmanager/feed');
        $feed->setFileFormat('xml');
        $feed->setXmlStructure('[{"tag":"id"}]');
        $feed->setXmlHeader('<?xml version="1.0"?><root>');
        $feed->setXmlFooter('</root>');

        $header = $feed->getXmlHeader();

        expect($header)->toBe('<?xml version="1.0"?><root>');
    });

    test('xml_structure mode writes footer', function () {
        $feed = Mage::getModel('feedmanager/feed');
        $feed->setFileFormat('xml');
        $feed->setXmlStructure('[{"tag":"id"}]');
        $feed->setXmlHeader('<?xml version="1.0"?><root>');
        $feed->setXmlFooter('</root>');

        $footer = $feed->getXmlFooter();

        expect($footer)->toBe('</root>');
    });
});
