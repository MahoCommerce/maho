<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Builds a synthetic axe result node for the runner's saveResults()
 */
function a11yNode(string $selector, ?array $box): array
{
    return [
        'cssSelector' => $selector,
        'html' => '<a class="x" href="#"></a>',
        'failureSummary' => 'Fix any of the following: element has no text',
        'boundingBox' => $box,
    ];
}

function a11yViolation(string $ruleId, string $impact, array $tags, array $nodes): array
{
    return [
        'ruleId' => $ruleId,
        'impact' => $impact,
        'wcagTags' => $tags,
        'description' => "Description of $ruleId",
        'helpUrl' => "https://dequeuniversity.com/rules/axe/$ruleId",
        'nodes' => $nodes,
    ];
}

describe('AccessibilityScan runner result saving', function () {
    it('deduplicates violations across viewports and keeps per-viewport geometry', function () {
        $scan = Mage::getModel('accessibilityscan/scan');
        $scan->setUrl('https://example.com/')
            ->setStoreId(1)
            ->setWcagLevel('AA')
            ->setTriggeredBy(Maho_AccessibilityScan_Model_Scan::TRIGGER_CLI)
            ->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_RUNNING)
            ->save();

        $results = [
            'desktop' => [
                'url' => 'https://example.com/',
                'title' => 'Home',
                'screenshotPath' => null,
                'pageWidth' => 1280,
                'pageHeight' => 2000,
                'rawHtml' => '',
                'incompleteCount' => 3,
                'violations' => [
                    // Present on both viewports - must merge into one row
                    a11yViolation('link-name', 'serious', ['wcag2a', 'wcag412'], [
                        a11yNode('.foo > a', ['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40]),
                    ]),
                    // Desktop only, no measurable geometry
                    a11yViolation('image-alt', 'critical', ['wcag2a', 'wcag111'], [
                        a11yNode('img.logo', null),
                    ]),
                ],
            ],
            'mobile' => [
                'url' => 'https://example.com/',
                'title' => 'Home',
                'screenshotPath' => null,
                'pageWidth' => 390,
                'pageHeight' => 3000,
                'rawHtml' => '',
                'incompleteCount' => 2,
                'violations' => [
                    a11yViolation('link-name', 'serious', ['wcag2a', 'wcag412'], [
                        a11yNode('.foo > a', ['x' => 5, 'y' => 15, 'width' => 25, 'height' => 35]),
                    ]),
                    // Mobile only
                    a11yViolation('target-size', 'serious', ['wcag22aa'], [
                        a11yNode('.nav button', ['x' => 1, 'y' => 2, 'width' => 3, 'height' => 4]),
                    ]),
                ],
            ],
        ];

        $runner = Mage::getModel('accessibilityscan/runner');
        (new ReflectionMethod($runner, 'saveResults'))->invoke($runner, $scan, $results);
        $scan->save();

        try {
            $violations = [];
            foreach ($scan->getViolationCollection() as $violation) {
                $violations[$violation->getAxeRuleId()] = $violation;
            }

            // 4 axe nodes across the two viewports collapse into 3 distinct issues
            expect($violations)->toHaveCount(3);

            $linkName = $violations['link-name'];
            expect($linkName->getViewports())->toBe(['desktop', 'mobile']);
            expect($linkName->getElementRect('desktop'))->toBe(['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40]);
            expect($linkName->getElementRect('mobile'))->toBe(['x' => 5, 'y' => 15, 'width' => 25, 'height' => 35]);
            expect($linkName->getWcagCriteria())->toBe('4.1.2');

            expect($violations['image-alt']->getViewports())->toBe(['desktop']);
            expect($violations['image-alt']->getElementRect('desktop'))->toBeNull();

            expect($violations['target-size']->getViewports())->toBe(['mobile']);
            expect($violations['target-size']->getElementRect('mobile'))->toBe(['x' => 1, 'y' => 2, 'width' => 3, 'height' => 4]);
            expect($violations['target-size']->getElementRect('desktop'))->toBeNull();

            // Scan counters count distinct issues, incomplete takes the max
            expect($scan->getTotalViolations())->toBe(3);
            expect($scan->getViolationCounts()['serious'])->toBe(2);
            expect($scan->getViolationCounts()['critical'])->toBe(1);
            expect($scan->getIncompleteCount())->toBe(3);

            // Page rows keep their own per-viewport occurrence counts
            $pageCounts = [];
            foreach ($scan->getPages() as $page) {
                $pageCounts[$page->getViewport()] = $page->getViolationCount();
            }
            expect($pageCounts)->toBe(['desktop' => 2, 'mobile' => 2]);
        } finally {
            $scan->deleteWithScreenshots();
        }
    });
});
