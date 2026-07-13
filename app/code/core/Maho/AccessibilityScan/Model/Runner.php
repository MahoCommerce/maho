<?php

/**
 * Scan orchestrator: installs Playwright on demand, spawns the Node.js scanner
 * and persists the axe-core results with template source mapping.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_Runner
{
    public const COOKIE_NAME = 'maho_a11y_scan';
    public const CACHE_KEY_PREFIX = 'accessibilityscan_token_';

    /** Seconds a scan token stays valid after the scan starts */
    protected const TOKEN_LIFETIME = 600;

    /** Seconds allowed for npm install / browser download */
    protected const INSTALL_TIMEOUT = 900;

    protected Maho_AccessibilityScan_Helper_Data $helper;

    public function __construct()
    {
        $this->helper = Mage::helper('accessibilityscan');
    }

    /**
     * Run a full scan for the given (already saved) scan entity
     */
    public function run(Maho_AccessibilityScan_Model_Scan $scan, bool $reinstallPlaywright = false): Maho_AccessibilityScan_Model_Scan
    {
        $locale = Mage::app()->getLocale();
        $scan->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_RUNNING)
            ->setStartedAt($locale->formatDateForDb('now'))
            ->save();

        try {
            $this->installPlaywright($reinstallPlaywright);
            $results = [];
            foreach ($this->helper->getViewports() as $device => $viewport) {
                $results[$device] = $this->executeScanner($scan, $device, $viewport);
            }
            $this->saveResults($scan, $results);
            $scan->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_COMPLETE);
        } catch (Throwable $e) {
            Mage::logException($e);
            $scan->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_FAILED)
                ->setErrorMessage($e->getMessage());
        }

        $scan->setCompletedAt($locale->formatDateForDb('now'))->save();
        return $scan;
    }

    /**
     * Lazily install Playwright and Chromium into var/accessibility-scan/playwright.
     * Skipped when node_modules is already populated, unless $force is set.
     */
    public function installPlaywright(bool $force = false): void
    {
        $dir = $this->helper->getPlaywrightDir();

        // The working directory is shared; serialize install/update across
        // concurrent scans so npm install and the scanner copy cannot race
        $lock = fopen($dir . DS . '.install.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            Mage::throwException($this->helper->__('Unable to acquire the scanner install lock in %s', $dir));
        }

        try {
            $packageJson = $dir . DS . 'package.json';
            if ($force || !is_file($packageJson)) {
                file_put_contents($packageJson, Mage::helper('core')->jsonEncode([
                    'name' => 'maho-accessibility-scanner',
                    'private' => true,
                    'type' => 'module',
                    'dependencies' => [
                        'playwright' => '^1',
                        '@axe-core/playwright' => '^4',
                    ],
                ]));
            }

            $this->copyScannerScript($dir);

            if (!$force && is_file($dir . DS . 'node_modules' . DS . '.package-lock.json')) {
                return;
            }

            $this->execProcess(
                [$this->helper->getNpmPath(), 'install', '--no-audit', '--no-fund'],
                $dir,
                self::INSTALL_TIMEOUT,
            );
            $this->execProcess(
                [$this->helper->getNodePath(), $dir . DS . 'node_modules' . DS . 'playwright' . DS . 'cli.js', 'install', 'chromium'],
                $dir,
                self::INSTALL_TIMEOUT,
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Copy the scanner next to node_modules (so its imports resolve) when
     * missing or outdated. The write is atomic (temp file + rename) so a
     * concurrent scan never sees a partially written script.
     */
    protected function copyScannerScript(string $dir): void
    {
        $source = __DIR__ . DS . '..' . DS . 'scanner' . DS . 'scan.mjs';
        $target = $dir . DS . 'scan.mjs';
        if (is_file($target) && hash_file('xxh128', $target) === hash_file('xxh128', $source)) {
            return;
        }

        $tmp = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (!copy($source, $tmp) || !rename($tmp, $target)) {
            @unlink($tmp);
            Mage::throwException($this->helper->__('Unable to copy the scanner script to %s', $dir));
        }
    }

    /**
     * Spawn the Node.js scanner for one viewport and return its decoded JSON result
     *
     * @param array{width: int, height: int, mobile: bool} $viewport
     * @return array<string, mixed>
     */
    protected function executeScanner(Maho_AccessibilityScan_Model_Scan $scan, string $device, array $viewport): array
    {
        $dir = $this->helper->getPlaywrightDir();
        $script = $dir . DS . 'scan.mjs';

        // One-time token; the frontend observer force-enables template hints for it
        $token = bin2hex(random_bytes(16));
        Mage::app()->saveCache('1', self::CACHE_KEY_PREFIX . $token, [], self::TOKEN_LIFETIME);

        $timeout = $this->helper->getScanTimeout();
        $inputFile = $this->helper->getBaseDir() . DS . 'input-' . $scan->getId() . '-' . $device . '.json';
        file_put_contents($inputFile, Mage::helper('core')->jsonEncode([
            'url' => $scan->getUrl(),
            'wcagTags' => $this->helper->getWcagTags($scan->getWcagLevel()),
            'scanCookie' => ['name' => self::COOKIE_NAME, 'value' => $token],
            'screenshotDir' => $this->helper->getScreenshotDir(),
            'screenshotName' => 'scan-' . $scan->getId() . '-' . $device . '.png',
            'timeout' => $timeout * 1000,
            'viewport' => $viewport,
        ]));

        try {
            $stdout = $this->execProcess(
                [$this->helper->getNodePath(), $script, $inputFile],
                $dir,
                $timeout + 60,
            );
        } finally {
            Mage::app()->removeCache(self::CACHE_KEY_PREFIX . $token);
            @unlink($inputFile);
        }

        $result = Mage::helper('core')->jsonDecode($stdout);
        if (!is_array($result)) {
            Mage::throwException($this->helper->__('The scanner returned an unexpected result'));
        }
        return $result;
    }

    /**
     * Persist one page row per scanned viewport with its violations, and
     * update the scan counters with the totals across all viewports
     *
     * @param array<string, array<string, mixed>> $results scanner results keyed by device name
     */
    protected function saveResults(Maho_AccessibilityScan_Model_Scan $scan, array $results): void
    {
        $locale = Mage::app()->getLocale();

        $counts = array_fill_keys(Maho_AccessibilityScan_Model_Violation::IMPACT_LEVELS, 0);
        $total = 0;
        $incomplete = 0;

        foreach ($results as $device => $result) {
            $page = Mage::getModel('accessibilityscan/page');
            $page->setScanId((int) $scan->getId())
                ->setViewport((string) $device)
                ->setUrl((string) ($result['url'] ?? $scan->getUrl()))
                ->setPageTitle(isset($result['title']) ? mb_substr((string) $result['title'], 0, 255) : null)
                ->setStatus('complete')
                ->setScreenshotPath(isset($result['screenshotPath']) ? (string) $result['screenshotPath'] : null)
                ->setPageWidth(isset($result['pageWidth']) ? (int) $result['pageWidth'] : null)
                ->setPageHeight(isset($result['pageHeight']) ? (int) $result['pageHeight'] : null)
                ->setScannedAt($locale->formatDateForDb('now'))
                ->save();

            $mapper = new Maho_AccessibilityScan_Model_TemplateMapper((string) ($result['rawHtml'] ?? ''));
            $pageTotal = 0;

            $violations = $result['violations'] ?? [];
            foreach (is_array($violations) ? $violations : [] as $violation) {
                if (!is_array($violation)) {
                    continue;
                }
                $wcagTags = is_array($violation['wcagTags'] ?? null) ? $violation['wcagTags'] : [];
                $impact = (string) ($violation['impact'] ?? '');
                if (!in_array($impact, Maho_AccessibilityScan_Model_Violation::IMPACT_LEVELS, true)) {
                    $impact = Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR;
                }

                $nodes = $violation['nodes'] ?? [];
                foreach (is_array($nodes) ? $nodes : [] as $node) {
                    if (!is_array($node)) {
                        continue;
                    }
                    $snippet = isset($node['html']) ? (string) $node['html'] : null;
                    [$templateFile, $templateLine] = $mapper->mapSnippet($snippet);
                    $box = is_array($node['boundingBox'] ?? null) ? $node['boundingBox'] : null;

                    Mage::getModel('accessibilityscan/violation')
                        ->setPageId((int) $page->getId())
                        ->setScanId((int) $scan->getId())
                        ->setAxeRuleId(mb_substr((string) ($violation['ruleId'] ?? ''), 0, 64))
                        ->setImpact($impact)
                        ->setWcagLevel($this->wcagLevelFromTags($wcagTags))
                        ->setWcagCriteria($this->wcagCriteriaFromTags($wcagTags))
                        ->setDescription(isset($violation['description']) ? (string) $violation['description'] : null)
                        ->setHelpUrl(isset($violation['helpUrl']) ? mb_substr((string) $violation['helpUrl'], 0, 512) : null)
                        ->setHtmlSnippet($snippet)
                        ->setCssSelector(isset($node['cssSelector']) ? (string) $node['cssSelector'] : null)
                        ->setFailureSummary(isset($node['failureSummary']) ? (string) $node['failureSummary'] : null)
                        ->setTemplateFile($templateFile)
                        ->setTemplateLine($templateLine)
                        ->setElementX($box !== null ? (int) ($box['x'] ?? 0) : null)
                        ->setElementY($box !== null ? (int) ($box['y'] ?? 0) : null)
                        ->setElementWidth($box !== null ? (int) ($box['width'] ?? 0) : null)
                        ->setElementHeight($box !== null ? (int) ($box['height'] ?? 0) : null)
                        ->save();

                    $counts[$impact]++;
                    $total++;
                    $pageTotal++;
                }
            }

            $page->setViolationCount($pageTotal)->save();
            $incomplete += max((int) ($result['incompleteCount'] ?? 0), 0);
        }

        $scan->setTotalViolations($total)
            ->setIncompleteCount($incomplete)
            ->setViolationsCritical($counts[Maho_AccessibilityScan_Model_Violation::IMPACT_CRITICAL])
            ->setViolationsSerious($counts[Maho_AccessibilityScan_Model_Violation::IMPACT_SERIOUS])
            ->setViolationsModerate($counts[Maho_AccessibilityScan_Model_Violation::IMPACT_MODERATE])
            ->setViolationsMinor($counts[Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR]);
    }

    /**
     * Derive the strictest WCAG conformance level from axe tags
     *
     * @param list<mixed> $tags
     */
    protected function wcagLevelFromTags(array $tags): ?string
    {
        $tags = array_map('strval', $tags);
        foreach (['aaa' => 'AAA', 'aa' => 'AA', 'a' => 'A'] as $suffix => $level) {
            foreach ($tags as $tag) {
                if (preg_match('/^wcag2\d?' . $suffix . '$/', $tag)) {
                    return $level;
                }
            }
        }
        return null;
    }

    /**
     * Extract success criteria references (e.g. "1.4.3") from axe tags
     *
     * @param list<mixed> $tags
     */
    protected function wcagCriteriaFromTags(array $tags): ?string
    {
        $criteria = [];
        foreach ($tags as $tag) {
            if (preg_match('/^wcag(\d)(\d)(\d+)$/', (string) $tag, $m)) {
                $criteria[] = "{$m[1]}.{$m[2]}.{$m[3]}";
            }
        }
        return $criteria === [] ? null : implode(', ', array_unique($criteria));
    }

    /**
     * Run an external command, enforcing a wall-clock timeout, and return its stdout
     *
     * @param list<string> $command
     */
    protected function execProcess(array $command, string $cwd, int $timeout): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = getenv();
        $env['PLAYWRIGHT_BROWSERS_PATH'] = $this->helper->getBrowsersDir();
        // Web-server PHP often runs with a minimal PATH; append the usual
        // node/npm install locations so the default binary names resolve
        $env['PATH'] = implode(':', array_unique(array_filter([
            ...explode(':', $env['PATH'] ?? ''),
            '/usr/local/bin',
            '/opt/homebrew/bin',
            '/usr/bin',
            '/bin',
        ])));

        // proc_open() resolves a bare binary name against the parent process
        // PATH, not the child $env, so resolve it ourselves
        $command[0] = $this->resolveBinary($command[0], $env['PATH']);

        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);
        if (!is_resource($process)) {
            Mage::throwException($this->helper->__('Unable to start process: %s', implode(' ', $command)));
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $timeout;

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 200000) > 0) {
                foreach ($read as $stream) {
                    $chunk = (string) fread($stream, 65536);
                    if ($stream === $pipes[1]) {
                        $stdout .= $chunk;
                    } else {
                        $stderr .= $chunk;
                    }
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                proc_close($process);
                Mage::throwException($this->helper->__(
                    'Command timed out after %s seconds: %s',
                    $timeout,
                    implode(' ', $command),
                ));
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($status['exitcode'] !== 0) {
            Mage::throwException($this->helper->__(
                'Command failed (exit code %s): %s',
                $status['exitcode'],
                trim(mb_substr($stderr !== '' ? $stderr : $stdout, -2000)),
            ));
        }

        return $stdout;
    }

    /**
     * Resolve a bare binary name to an absolute path using the given PATH
     */
    protected function resolveBinary(string $binary, string $path): string
    {
        if (str_contains($binary, '/')) {
            return $binary;
        }
        foreach (explode(':', $path) as $dir) {
            $candidate = $dir . '/' . $binary;
            if ($dir !== '' && is_executable($candidate)) {
                return $candidate;
            }
        }
        return $binary;
    }
}
