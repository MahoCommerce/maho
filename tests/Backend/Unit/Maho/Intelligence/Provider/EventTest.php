<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function injectEventsConfig(string $innerXml): void
{
    $extra = new Maho\Simplexml\Config();
    $extra->loadString('<?xml version="1.0"?><config>' . $innerXml . '</config>');
    Mage::getConfig()->extend($extra);
}

describe('Provider_Event attribute-based observers', function () {
    it('surfaces compiled attribute observers across all known areas', function () {
        $events = Mage::getModel('intelligence/provider_event')->getAllEvents();

        expect($events)->toHaveKey('global');
        expect($events)->toHaveKey('adminhtml');
        expect($events)->toHaveKey('frontend');

        $anyAttribute = false;
        foreach ($events as $areaEvents) {
            foreach ($areaEvents as $event) {
                foreach ($event['observers'] as $observer) {
                    if (($observer['source'] ?? null) === 'attribute') {
                        $anyAttribute = true;
                        break 3;
                    }
                }
            }
        }
        expect($anyAttribute)->toBeTrue();
    });

    it('tags attribute observers with module and alias metadata', function () {
        $events = Mage::getModel('intelligence/provider_event')->getAllEvents();

        $sample = null;
        foreach ($events as $areaEvents) {
            foreach ($areaEvents as $event) {
                foreach ($event['observers'] as $observer) {
                    if (($observer['source'] ?? null) === 'attribute') {
                        $sample = $observer;
                        break 3;
                    }
                }
            }
        }

        expect($sample)->not->toBeNull();
        expect($sample)->toHaveKeys(['name', 'class', 'method', 'type', 'module', 'alias', 'source']);
        expect($sample['class'])->toBeString()->not->toBeEmpty();
        expect($sample['method'])->toBeString()->not->toBeEmpty();
    });
});

describe('Provider_Event XML-defined observers', function () {
    it('surfaces an injected XML observer with source=xml', function () {
        injectEventsConfig('
            <global>
                <events>
                    <test_xml_event>
                        <observers>
                            <my_listener>
                                <class>Mage_Core_Model_Observer</class>
                                <method>handleTest</method>
                                <type>singleton</type>
                            </my_listener>
                        </observers>
                    </test_xml_event>
                </events>
            </global>
        ');

        $events = Mage::getModel('intelligence/provider_event')->getAllEvents();

        expect($events['global'])->toHaveKey('test_xml_event');
        $observers = $events['global']['test_xml_event']['observers'];
        expect($observers)->toHaveCount(1);

        $observer = $observers[0];
        expect($observer['source'])->toBe('xml');
        expect($observer['name'])->toBe('my_listener');
        expect($observer['class'])->toBe('Mage_Core_Model_Observer');
        expect($observer['method'])->toBe('handleTest');
        expect($observer['type'])->toBe('singleton');
        expect($observer['module'])->toBeNull();
        expect($observer['alias'])->toBeNull();
    });

    it('defaults observer type to singleton when XML omits it', function () {
        injectEventsConfig('
            <frontend>
                <events>
                    <test_default_type>
                        <observers>
                            <listener><class>Foo_Bar</class><method>baz</method></listener>
                        </observers>
                    </test_default_type>
                </events>
            </frontend>
        ');

        $events = Mage::getModel('intelligence/provider_event')->getAllEvents();
        expect($events['frontend']['test_default_type']['observers'][0]['type'])->toBe('singleton');
    });

    it('merges XML and attribute observers on the same event', function () {
        injectEventsConfig('
            <global>
                <events>
                    <controller_action_predispatch>
                        <observers>
                            <custom_xml_listener>
                                <class>Custom_Class</class>
                                <method>customMethod</method>
                            </custom_xml_listener>
                        </observers>
                    </controller_action_predispatch>
                </events>
            </global>
        ');

        $events = Mage::getModel('intelligence/provider_event')->getAllEvents();
        $observers = $events['global']['controller_action_predispatch']['observers'] ?? [];

        $sources = array_column($observers, 'source');
        expect($sources)->toContain('xml');
        expect($sources)->toContain('attribute');
    });
});

describe('Provider_Event::getObserversForEvent', function () {
    it('returns observers grouped by area for an XML-defined event', function () {
        injectEventsConfig('
            <adminhtml>
                <events>
                    <my_shared_event>
                        <observers>
                            <xml_observer><class>Foo</class><method>bar</method></xml_observer>
                        </observers>
                    </my_shared_event>
                </events>
            </adminhtml>
        ');

        $provider = Mage::getModel('intelligence/provider_event');
        $result = $provider->getObserversForEvent('my_shared_event');

        expect($result)->toHaveKey('adminhtml');
        expect($result['adminhtml'])->toHaveCount(1);
        expect($result['adminhtml'][0]['name'])->toBe('xml_observer');
    });

    it('returns an empty array for an unknown event name', function () {
        $provider = Mage::getModel('intelligence/provider_event');
        expect($provider->getObserversForEvent('this_event_definitely_does_not_exist_xyz'))->toBe([]);
    });

    it('is case-insensitive on event names', function () {
        injectEventsConfig('
            <global>
                <events>
                    <CaseSensitiveEvent>
                        <observers>
                            <o><class>X</class><method>y</method></o>
                        </observers>
                    </CaseSensitiveEvent>
                </events>
            </global>
        ');

        $provider = Mage::getModel('intelligence/provider_event');
        expect($provider->getObserversForEvent('casesensitiveevent'))->toHaveKey('global');
    });
});

describe('Provider_Event caching', function () {
    it('returns the same cached result on repeated calls', function () {
        $provider = Mage::getModel('intelligence/provider_event');
        $first = $provider->getAllEvents();
        $second = $provider->getAllEvents();

        expect($second)->toBe($first);
    });
});
