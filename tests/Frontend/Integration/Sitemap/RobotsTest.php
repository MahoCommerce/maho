<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * Coverage for the generated robots.txt.
 *
 * Under RFC 9309 a crawler obeys exactly one group and inherits nothing from the wildcard group,
 * so adding "User-agent: GPTBot" to an otherwise correct file hands GPTBot every path the
 * wildcard group disallows unless those rules are repeated inside the new group.
 */
function robotsModel(): Mage_Sitemap_Model_Robots
{
    /** @var Mage_Sitemap_Model_Robots $model */
    $model = Mage::getSingleton('sitemap/robots');
    return $model;
}

function robotsParser(): Mage_Sitemap_Model_Robots_Parser
{
    /** @var Mage_Sitemap_Model_Robots_Parser $parser */
    $parser = Mage::getSingleton('sitemap/robots_parser');
    return $parser;
}

/**
 * @return list<string>
 */
function rulesForAgent(string $output, string $agent): array
{
    foreach (robotsParser()->parse($output)->getGroups() as $group) {
        if ($group->hasAgent($agent)) {
            return $group->getRules();
        }
    }
    return [];
}

function configureRobots(array $values = []): void
{
    $store = Mage::app()->getStore();
    $defaults = [
        Mage_Sitemap_Model_Robots::XML_PATH_ENABLED => '1',
        Mage_Sitemap_Model_Robots::XML_PATH_BASE_RULES => "Disallow: /checkout/\nDisallow: /customer/",
        Mage_Sitemap_Model_Robots::XML_PATH_BLOCKED_AGENTS => '',
        Mage_Sitemap_Model_Robots::XML_PATH_INCLUDE_SITEMAPS => '0',
        Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => '',
    ];
    foreach ([...$defaults, ...$values] as $path => $value) {
        $store->setConfig($path, $value);
    }
}

beforeEach(function () {
    configureRobots();
});

describe('parser', function () {
    test('consecutive user-agent lines form a single group', function () {
        $document = robotsParser()->parse("User-agent: A\nUser-agent: B\nDisallow: /x/");

        expect($document->getGroups())->toHaveCount(1);
        expect($document->getGroups()[0]->getAgents())->toBe(['A', 'B']);
        expect($document->getGroups()[0]->getRules())->toBe(['Disallow: /x/']);
    });

    test('a user-agent line after a rule starts a new group', function () {
        $document = robotsParser()->parse("User-agent: A\nDisallow: /x/\nUser-agent: B\nDisallow: /y/");

        expect($document->getGroups())->toHaveCount(2);
        expect($document->getGroups()[1]->getAgents())->toBe(['B']);
    });

    test('comments and blank lines are ignored', function () {
        $document = robotsParser()->parse("# a comment\n\nUser-agent: A # trailing\nDisallow: /x/ # why\n");

        expect($document->getGroups()[0]->getAgents())->toBe(['A']);
        expect($document->getGroups()[0]->getRules())->toBe(['Disallow: /x/']);
    });

    test('field names are matched without case and re-emitted canonically', function () {
        $document = robotsParser()->parse("USER-AGENT: A\ndisallow: /x/");

        expect($document->getGroups()[0]->getAgents())->toBe(['A']);
        expect($document->getGroups()[0]->getRules())->toBe(['Disallow: /x/']);
    });

    test('sitemap is a non-group field', function () {
        $document = robotsParser()->parse("Sitemap: https://example.com/sitemap.xml\nUser-agent: A\nDisallow: /x/");

        expect($document->getGlobalLines())->toBe(['Sitemap: https://example.com/sitemap.xml']);
        expect($document->getGroups()[0]->getRules())->toBe(['Disallow: /x/']);
    });

    test('rules written before any user-agent line are kept as orphans', function () {
        $document = robotsParser()->parse("Disallow: /x/\nUser-agent: A\nDisallow: /y/");

        expect($document->getOrphanRules())->toBe(['Disallow: /x/']);
        expect($document->getGroups()[0]->getRules())->toBe(['Disallow: /y/']);
    });
});

describe('generated file', function () {
    test('the wildcard group carries the base rules', function () {
        expect(rulesForAgent(robotsModel()->generate(), '*'))
            ->toBe(['Disallow: /checkout/', 'Disallow: /customer/']);
    });

    test('a blocked agent gets its own group with a full disallow', function () {
        configureRobots([Mage_Sitemap_Model_Robots::XML_PATH_BLOCKED_AGENTS => 'GPTBot,CCBot']);
        $output = robotsModel()->generate();

        expect(rulesForAgent($output, 'GPTBot'))->toBe(['Disallow: /']);
        expect(rulesForAgent($output, 'CCBot'))->toBe(['Disallow: /']);
    });

    test('a custom group receives the base rules it would not inherit', function () {
        configureRobots([
            Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => "User-agent: GPTBot\nCrawl-delay: 10",
        ]);

        expect(rulesForAgent(robotsModel()->generate(), 'GPTBot'))
            ->toBe(['Disallow: /checkout/', 'Disallow: /customer/', 'Crawl-delay: 10']);
    });

    test('a base rule already present in a custom group is not repeated', function () {
        configureRobots([
            Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => "User-agent: GPTBot\nDisallow: /customer/",
        ]);

        expect(rulesForAgent(robotsModel()->generate(), 'GPTBot'))
            ->toBe(['Disallow: /checkout/', 'Disallow: /customer/']);
    });

    test('a custom group blocking everything is left alone', function () {
        configureRobots([
            Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => "User-agent: GPTBot\nDisallow: /",
        ]);

        expect(rulesForAgent(robotsModel()->generate(), 'GPTBot'))->toBe(['Disallow: /']);
    });

    test('a hand-written group wins over the blocked list', function () {
        configureRobots([
            Mage_Sitemap_Model_Robots::XML_PATH_BLOCKED_AGENTS => 'GPTBot',
            Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => "User-agent: GPTBot\nDisallow: /private/",
        ]);
        $output = robotsModel()->generate();

        expect(substr_count($output, 'User-agent: GPTBot'))->toBe(1);
        expect(rulesForAgent($output, 'GPTBot'))->toContain('Disallow: /private/');
    });

    test('custom rules without a user-agent line join the wildcard group', function () {
        configureRobots([Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => 'Disallow: /private/']);

        expect(rulesForAgent(robotsModel()->generate(), '*'))->toContain('Disallow: /private/');
    });

    test('the wildcard group is never left without a rule', function () {
        configureRobots([Mage_Sitemap_Model_Robots::XML_PATH_BASE_RULES => '']);

        expect(rulesForAgent(robotsModel()->generate(), '*'))->toBe(['Disallow:']);
    });

    test('the admin path is never published', function () {
        $frontName = \Maho\Routing\RouteCollectionBuilder::getAdminFrontName();
        expect($frontName)->not->toBe('');

        configureRobots([
            Mage_Sitemap_Model_Robots::XML_PATH_BASE_RULES => "Disallow: /{$frontName}/\nDisallow: /checkout/",
            Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => "User-agent: GPTBot\nDisallow: /{$frontName}",
        ]);

        expect(robotsModel()->generate())->not->toContain($frontName);
    });

    test('sitemap lines are absolute and scoped to the store', function () {
        configureRobots([Mage_Sitemap_Model_Robots::XML_PATH_INCLUDE_SITEMAPS => '1']);
        $store = Mage::app()->getStore();

        $sitemap = Mage::getModel('sitemap/sitemap');
        $sitemap->setSitemapPath('/')
            ->setSitemapFilename('robots_test_sitemap.xml')
            ->setStoreId($store->getId())
            ->save();

        try {
            expect(robotsModel()->generate())->toContain(
                'Sitemap: ' . $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'robots_test_sitemap.xml',
            );
        } finally {
            $sitemap->delete();
        }
    });

    test('a sitemap the merchant declared by hand is not duplicated', function () {
        configureRobots([
            Mage_Sitemap_Model_Robots::XML_PATH_CUSTOM => 'Sitemap: https://example.com/a.xml',
        ]);

        expect(substr_count(robotsModel()->generate(), 'Sitemap: https://example.com/a.xml'))->toBe(1);
    });
});

describe('controller', function () {
    test('serves the file as plain text', function () {
        $response = new Mage_Core_Controller_Response_Http();
        (new Mage_Sitemap_RobotsController(Mage::app()->getRequest(), $response))->indexAction();

        expect($response->getHttpResponseCode())->toBe(200);
        expect($response->getBody())->toContain('User-agent: *');
        expect($response->getBody())->not->toContain('<html');

        $contentType = null;
        foreach ($response->getHeaders() as $header) {
            if (strcasecmp($header['name'], 'Content-Type') === 0) {
                $contentType = $header['value'];
            }
        }
        expect($contentType)->toBe('text/plain; charset=UTF-8');
    });

    test('answers 404 when generation is turned off', function () {
        configureRobots([Mage_Sitemap_Model_Robots::XML_PATH_ENABLED => '0']);

        $response = new Mage_Core_Controller_Response_Http();
        (new Mage_Sitemap_RobotsController(Mage::app()->getRequest(), $response))->indexAction();

        expect($response->getHttpResponseCode())->toBe(404);
        expect($response->getBody())->toBe('');
    });
});

describe('web server configuration', function () {
    test('the Apache deny rule leaves the generated files reachable', function () {
        $htaccess = file_get_contents(Mage::getBaseDir('public') . DS . '.htaccess');
        expect($htaccess)->toBeString();

        preg_match('/<FilesMatch "([^"]+)">/', (string) $htaccess, $matches);
        expect($matches)->toHaveCount(2);

        $pattern = '/' . str_replace('/', '\/', $matches[1]) . '/';
        expect(preg_match($pattern, 'robots.txt'))->toBe(0);
        expect(preg_match($pattern, 'llms.txt'))->toBe(0);
        // The rule still has to do its job for everything else.
        expect(preg_match($pattern, 'composer.json'))->toBe(1);
        expect(preg_match($pattern, 'README.md'))->toBe(1);
        expect(preg_match($pattern, 'notes.txt'))->toBe(1);
    });
});
