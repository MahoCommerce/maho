<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

function injectCronConfig(string $innerXml): void
{
    // The merged config caches the 'crontab' section separately
    // (Mage_Core_Model_Config::$_cacheSections), so getNode('crontab/jobs')
    // reads from the cached section file and ignores runtime extend() calls.
    // Disable the section cache so the live in-memory tree is used.
    $config = Mage::getConfig();
    (new ReflectionProperty($config, '_useCache'))->setValue($config, false);

    $extra = new Maho\Simplexml\Config();
    $extra->loadString('<?xml version="1.0"?><config>' . $innerXml . '</config>');
    $config->extend($extra);
}

describe('Provider_Cron attribute-based jobs', function () {
    it('returns compiled attribute cron jobs', function () {
        $jobs = Mage::getModel('intelligence/provider_cron')->getAllJobs();

        expect($jobs)->not->toBeEmpty();

        $attributeJobs = array_filter($jobs, fn($j) => ($j['source'] ?? null) === 'attribute');
        expect($attributeJobs)->not->toBeEmpty();
    });

    it('builds model as "{alias}::{method}" for attribute jobs', function () {
        $jobs = Mage::getModel('intelligence/provider_cron')->getAllJobs();
        $attributeJob = null;
        foreach ($jobs as $job) {
            if (($job['source'] ?? null) === 'attribute') {
                $attributeJob = $job;
                break;
            }
        }

        expect($attributeJob)->not->toBeNull();
        expect($attributeJob['model'])->toMatch('/^[a-z0-9_]+\/[a-z0-9_]+::[a-zA-Z0-9_]+$/');
    });

    it('exposes required shape for attribute jobs', function () {
        $jobs = Mage::getModel('intelligence/provider_cron')->getAllJobs();
        $attributeJob = null;
        foreach ($jobs as $job) {
            if (($job['source'] ?? null) === 'attribute') {
                $attributeJob = $job;
                break;
            }
        }

        expect($attributeJob)->toHaveKeys(['name', 'model', 'schedule', 'config_path', 'module', 'source']);
        expect($attributeJob['name'])->toBeString()->not->toBeEmpty();
    });
});

describe('Provider_Cron XML-defined jobs', function () {
    it('returns an injected XML cron job with source=xml', function () {
        injectCronConfig('
            <crontab>
                <jobs>
                    <test_xml_cron>
                        <schedule><cron_expr>*/15 * * * *</cron_expr></schedule>
                        <run><model>core/observer::cleanCache</model></run>
                    </test_xml_cron>
                </jobs>
            </crontab>
        ');

        $jobs = Mage::getModel('intelligence/provider_cron')->getAllJobs();

        expect($jobs)->toHaveKey('test_xml_cron');
        $job = $jobs['test_xml_cron'];
        expect($job['source'])->toBe('xml');
        expect($job['name'])->toBe('test_xml_cron');
        expect($job['model'])->toBe('core/observer::cleanCache');
        expect($job['schedule'])->toBe('*/15 * * * *');
        expect($job['config_path'])->toBeNull();
    });

    it('reads cron jobs from default/crontab/jobs', function () {
        injectCronConfig('
            <default>
                <crontab>
                    <jobs>
                        <test_default_cron>
                            <schedule><cron_expr>0 3 * * *</cron_expr></schedule>
                            <run><model>foo/bar::baz</model></run>
                        </test_default_cron>
                    </jobs>
                </crontab>
            </default>
        ');

        $jobs = Mage::getModel('intelligence/provider_cron')->getAllJobs();

        expect($jobs)->toHaveKey('test_default_cron');
        expect($jobs['test_default_cron']['schedule'])->toBe('0 3 * * *');
        expect($jobs['test_default_cron']['source'])->toBe('xml');
    });

    it('captures config_path when schedule uses a configurable path', function () {
        injectCronConfig('
            <default>
                <crontab>
                    <jobs>
                        <configurable_cron>
                            <schedule><config_path>cron_test/my_schedule</config_path></schedule>
                            <run><model>x/y::z</model></run>
                        </configurable_cron>
                    </jobs>
                </crontab>
            </default>
        ');

        $jobs = Mage::getModel('intelligence/provider_cron')->getAllJobs();

        expect($jobs)->toHaveKey('configurable_cron');
        expect($jobs['configurable_cron']['config_path'])->toBe('cron_test/my_schedule');
        expect($jobs['configurable_cron']['model'])->toBe('x/y::z');
    });

    it('returns jobs sorted naturally and case-insensitively', function () {
        injectCronConfig('
            <crontab>
                <jobs>
                    <zeta_cron>
                        <schedule><cron_expr>0 1 * * *</cron_expr></schedule>
                        <run><model>x/y::z</model></run>
                    </zeta_cron>
                    <alpha_cron>
                        <schedule><cron_expr>0 1 * * *</cron_expr></schedule>
                        <run><model>x/y::z</model></run>
                    </alpha_cron>
                </jobs>
            </crontab>
        ');

        $jobs = Mage::getModel('intelligence/provider_cron')->getAllJobs();
        $keys = array_keys($jobs);
        $alphaPos = array_search('alpha_cron', $keys, true);
        $zetaPos = array_search('zeta_cron', $keys, true);

        expect($alphaPos)->toBeLessThan($zetaPos);
    });
});
