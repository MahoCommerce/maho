<?php

declare(strict_types=1);

/**
 * Routing micro-benchmark.
 *
 * Measures per-request cost of the URL matching layer, from raw path to
 * matched-route-or-miss decision — the step we actually replaced in the
 * Symfony migration. Dispatch and rendering are excluded because they
 * didn't change.
 *
 * Runs in two modes:
 *   NEW — Symfony CompiledUrlMatcher (post-migration, this branch)
 *   OLD — legacy router chain iteration (pre-migration, main branch)
 *
 * The script auto-detects which mode to run based on whether the post-
 * migration classes are present.
 *
 * Usage:
 *   php bench_routing.php                    # default: 2000 iterations
 *   php bench_routing.php 10000              # custom iteration count
 */

require __DIR__ . '/vendor/autoload.php';

// OLD-mode's Router_Standard calls $front->getResponse() which tries to set
// a Content-Type header; in CLI that throws if any output has gone out. So
// buffer all output until the end and flush after the bench completes.
ob_start();

Mage::app();

$iterations = (int) ($argv[1] ?? 2000);

$isNew = class_exists('Maho\Routing\RouteCollectionBuilder');

// URL set: paths that exercise the match pipeline without triggering
// controller action dispatch. The old router couples match-and-dispatch,
// so we use nonexistent action names on valid modules — the match runs
// to completion (parse path, resolve module, instantiate controller,
// check hasAction) then bails without executing the action.
$urls = [
    '/catalog/product/xFakeAct',    // valid frontend module, fake action
    '/catalog/category/xFakeAct',
    '/checkout/cart/xFakeAct',
    '/customer/account/xFakeAct',
    '/cms/page/xFakeAct',
    '/admin/index/xFakeAct',        // valid admin module, fake action
    '/zzzfake/foo/bar',             // miss — fake module
    '/another/deep/miss/chain',     // miss — no such route
];

$mode = $isNew ? 'NEW (Symfony CompiledUrlMatcher)' : 'OLD (legacy router chain)';
$opcache = function_exists('opcache_get_status') && opcache_get_status(false) !== false ? 'on' : 'off';

// Prepare matcher closure per mode.
// NEW: creates a RequestContext + matcher per request (matches production).
// OLD: runs front controller init once, then iterates through routers (Default excluded — it would
//      set a no-route target and cause dispatch on a second iteration).
if ($isNew) {
    $matchFn = function (string $path): void {
        $sfRequest = \Symfony\Component\HttpFoundation\Request::create($path);
        $context = new \Symfony\Component\Routing\RequestContext();
        $context->fromRequest($sfRequest);
        $matcher = \Maho\Routing\RouteCollectionBuilder::createMatcher($context);
        try {
            $matcher->match($path);
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
            // Miss — counted, no action.
        } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException) {
            // Method miss — also counted.
        }
    };
} else {
    $front = Mage::app()->getFrontController();
    $front->init();
    $routers = [];
    foreach ($front->getRouters() as $name => $router) {
        if ($name === 'default') {
            continue; // Skip default router to avoid triggering no-route dispatch chain.
        }
        $routers[] = $router;
    }
    $matchFn = function (string $path) use ($routers): void {
        $sfRequest = \Symfony\Component\HttpFoundation\Request::create($path);
        $request = new Mage_Core_Controller_Request_Http($sfRequest);
        $request->setPathInfo($path);
        foreach ($routers as $router) {
            if ($router->match($request)) {
                break;
            }
        }
    };
}

// Warmup: populates caches, triggers autoload, primes opcache.
for ($i = 0; $i < 50; $i++) {
    foreach ($urls as $u) {
        $matchFn($u);
    }
}

// Timed loop.
$perCallTimes = [];
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($urls as $u) {
        $t0 = hrtime(true);
        $matchFn($u);
        $perCallTimes[] = hrtime(true) - $t0;
    }
}
$elapsed = microtime(true) - $start;

sort($perCallTimes);
$totalMatches = count($perCallTimes);
$totalNs = array_sum($perCallTimes);
$meanNs = $totalNs / $totalMatches;
$medianNs = $perCallTimes[(int) ($totalMatches * 0.50)];
$p95Ns = $perCallTimes[(int) ($totalMatches * 0.95)];
$p99Ns = $perCallTimes[(int) ($totalMatches * 0.99)];

ob_end_clean();

echo "Mode: {$mode}\n";
echo "URLs: " . count($urls) . ", iterations: {$iterations}, total matches: {$totalMatches}\n";
echo "OPcache: {$opcache}\n";
echo str_repeat('-', 60) . "\n";
printf("Wall total:  %.3f s\n", $elapsed);
printf("Per-match:   mean %6.2f μs | median %6.2f μs | p95 %6.2f μs | p99 %6.2f μs\n",
    $meanNs / 1e3, $medianNs / 1e3, $p95Ns / 1e3, $p99Ns / 1e3);
