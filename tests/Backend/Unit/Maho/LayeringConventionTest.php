<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

/**
 * Tripwire for the layering of #1348: a transport translates, a service decides. A transport is a
 * controller, an API class or a CLI command. It reads the request, calls one service and renders the
 * result. A service never reads the request or the session, a status code belongs to the API edge, and
 * a rate limit or a honeypot belongs to the service, so that no transport can forget it.
 *
 * Each rule has two lists. The allowlist holds the debt: the files that broke the rule when it came in,
 * with the number of matches in each file. It only shrinks: a pull request that fixes a file lowers or
 * removes its entry, and the test fails until it does. The permanent list holds the matches that stay,
 * each with its count and its reason. A file can have entries in both lists. When the count of a file
 * goes down, the test fails until its entries go down too. Comments and docblocks are not scanned.
 */

/** Files that create a Symfony HttpException outside Maho_ApiPlatform, and must stop. */
const LAYERING_HTTP_EXCEPTION_ALLOWLIST = [];

/** Files that create a Symfony HttpException outside Maho_ApiPlatform for a reason that stays. */
const LAYERING_HTTP_EXCEPTION_PERMANENT = [
    'app/code/core/Mage/Catalog/Api/CategoryProvider.php' => [1, '403 for scope=all without a backend token'],
    'app/code/core/Mage/Core/Api/ConfigSettingProcessor.php' => [1, '403 for a scope that the API token cannot access'],
    'app/code/core/Mage/Core/Api/ConfigSettingProvider.php' => [2, '403 for a store or a config section that the API token cannot access'],
    'app/code/core/Mage/Core/Api/StoreProcessor.php' => [1, '403 for a store that the API token cannot access'],
    'app/code/core/Mage/Customer/Api/CustomerProcessor.php' => [1, '401 of the GraphQL login for a wrong email or password'],
    'app/code/core/Mage/Newsletter/Api/NewsletterProcessor.php' => [2, '403 for a store that the API token cannot access, and for an unsubscribe without a customer token'],
    'app/code/core/Mage/Reports/Api/ReportQuery.php' => [2, '403 for a store or a website that the API token cannot access'],
    'app/code/core/Mage/Reports/Api/ReportStatisticsProcessor.php' => [1, '403 for a refresh with an API token that has a store restriction'],
    'app/code/core/Maho/Ai/Api/Chat/ChatController.php' => [17, 'the admin assistant chat, which only this controller starts'],
    'app/code/core/Maho/SocialLogin/Api/SocialAuthProcessor.php' => [3, 'the answers of a token endpoint (RFC 6749)'],
];

/** Files in a Service folder that read the request, the superglobals or the session, and must stop. */
const LAYERING_SERVICE_INPUT_ALLOWLIST = [];

/** Files in a Service folder that read the request, the superglobals or the session for a reason that stays. */
const LAYERING_SERVICE_INPUT_PERMANENT = [];

/** Transport files that call a rate limiter or a honeypot check themselves, and must stop. */
const LAYERING_TRANSPORT_GATE_ALLOWLIST = [];

/** Transport files that call a rate limiter or a honeypot check themselves for a reason that stays. */
const LAYERING_TRANSPORT_GATE_PERMANENT = [
    'app/code/core/Mage/Adminhtml/controllers/IndexController.php' => [2, 'the admin password reset link, which only the admin login page checks'],
    'app/code/core/Mage/Downloadable/controllers/DownloadController.php' => [2, 'the download link of the storefront, which only this controller serves'],
    'app/code/core/Mage/Shipping/controllers/TrackingController.php' => [2, 'the tracking popup of the storefront, which only this controller serves'],
    'app/code/core/Maho/ApiPlatform/Api/AuthTokenProcessor.php' => [4, 'the limits of the token endpoint and of the grants that only the API has'],
    'app/code/core/Maho/ApiPlatform/symfony/Controller/OAuthController.php' => [1, 'the limit of the OAuth endpoints, which only the API has'],
    'app/code/core/Maho/ApiPlatform/symfony/Trait/RateLimitTrait.php' => [2, 'the shared limiter of the token endpoints'],
];

/**
 * @return array<string, string> the code of each PHP file under app/code/core and lib/MahoCLI, by
 *                               its path from the Maho root
 */
function layeringSources(): array
{
    static $sources = null;
    if ($sources !== null) {
        return $sources;
    }

    $root = dirname(__DIR__, 4) . '/';
    $sources = [];
    foreach (['app/code/core', 'lib/MahoCLI'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (str_ends_with($file->getPathname(), '.php')) {
                $sources[substr($file->getPathname(), strlen($root))] = (string) file_get_contents($file->getPathname());
            }
        }
    }
    ksort($sources);
    return $sources;
}

/**
 * Remove the comments and the docblocks from $code, so that a comment that names a forbidden call
 * does not count.
 */
function layeringStripComments(string $code): string
{
    $stripped = '';
    foreach (token_get_all($code) as $token) {
        if (is_string($token)) {
            $stripped .= $token;
        } elseif ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
            $stripped .= $token[1];
        }
    }
    return $stripped;
}

/**
 * Count the matches of $codePattern in each file whose path matches $pathPattern. $accept gets the
 * path and the match, and returns false for a match that the rule allows.
 *
 * @param (callable(string, array<int|string, string>): bool)|null $accept
 * @return array<string, int> the count of each file that has a match, by path
 */
function layeringMatches(string $pathPattern, string $codePattern, ?callable $accept = null): array
{
    $found = [];
    foreach (layeringSources() as $path => $code) {
        if (!preg_match($pathPattern, $path) || !preg_match($codePattern, $code)) {
            continue;
        }
        preg_match_all($codePattern, layeringStripComments($code), $matches, PREG_SET_ORDER);
        $count = count(array_filter($matches, fn(array $match): bool => $accept === null || $accept($path, $match)));
        if ($count > 0) {
            $found[$path] = $count;
        }
    }
    return $found;
}

/**
 * Compare the matches of a rule with its allowlist $allowed and its permanent list $permanent. $fix
 * tells the developer how to fix a new match.
 *
 * @param array<string, int> $found
 * @param array<string, int> $allowed
 * @param array<string, array{int, string}> $permanent
 * @return list<string> one message for each difference
 */
function layeringRatchet(array $found, array $allowed, array $permanent, string $fix): array
{
    $messages = [];
    foreach (['allowlist' => $allowed, 'permanent list' => $permanent] as $name => $list) {
        $sorted = $list;
        ksort($sorted);
        if (array_keys($sorted) !== array_keys($list)) {
            $messages[] = "Sort the $name by path.";
        }
    }
    foreach ($permanent as $path => [$limit, $reason]) {
        if (trim($reason) === '') {
            $messages[] = "$path: give the reason of its permanent entry.";
        }
    }

    foreach ($found as $path => $count) {
        $limit = ($allowed[$path] ?? 0) + ($permanent[$path][0] ?? 0);
        if ($count > $limit) {
            $messages[] = "$path: $count matches, the lists permit $limit. $fix";
        }
    }
    foreach (array_keys($allowed + $permanent) as $path) {
        $debt = $allowed[$path] ?? 0;
        $kept = $permanent[$path][0] ?? 0;
        $count = $found[$path] ?? 0;
        if ($count >= $debt + $kept) {
            continue;
        }
        $changes = [];
        if ($debt > 0) {
            $changes[] = $count > $kept ? 'lower its allowlist entry to ' . ($count - $kept) : 'remove its allowlist entry';
        }
        if ($kept > 0 && $count < $kept) {
            $changes[] = $count === 0 ? 'remove its permanent entry' : "lower its permanent entry to $count";
        }
        $messages[] = "$path: $count matches, the lists permit " . ($debt + $kept) . '. ' . ucfirst(implode(' and ', $changes)) . '.';
    }
    return $messages;
}

it('creates a Symfony HttpException only at the API edge', function () {
    // A transport detects a malformed request, so an Api/ class (a GraphQL handler too) may send a 400.
    $found = layeringMatches(
        '#^(?!app/code/core/Maho/ApiPlatform/)#',
        '#\bnew\s+\\\\?(?:[A-Za-z_]\w*\\\\)*(\w*HttpException)\s*\(#',
        fn(string $path, array $match): bool => !($match[1] === 'BadRequestHttpException'
            && preg_match('#^app/code/core/(Mage|Maho)/[A-Za-z]+/Api/#', $path)),
    );
    $messages = layeringRatchet(
        $found,
        LAYERING_HTTP_EXCEPTION_ALLOWLIST,
        LAYERING_HTTP_EXCEPTION_PERMANENT,
        'Throw a Mage_Core_Exception subclass: ApiExceptionListener maps it to the status code.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('keeps the request and the session out of every service', function () {
    $found = layeringMatches(
        '#^app/code/core/(?!Maho/ApiPlatform/).*/Service/#',
        '#\bgetRequest\s*\(|\bgetParams?\s*\(|\$_(?:FILES|POST|GET|REQUEST)\b'
            . '|Mage::get(?:Singleton|Model)\(\s*[\'"][a-z0-9_]+/session(?:_[a-z0-9_]+)?[\'"]'
            . '|\b(?:Mage|Maho)_[A-Za-z]+_Model_Session\w*#',
    );
    $messages = layeringRatchet(
        $found,
        LAYERING_SERVICE_INPUT_ALLOWLIST,
        LAYERING_SERVICE_INPUT_PERMANENT,
        'Read the input in the transport and write it into the model that the service takes.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('keeps the classes of the API module out of every service', function () {
    $found = layeringMatches(
        '#^app/code/core/(?!Maho/ApiPlatform/).*/Service/#',
        '#\bMaho\\\\ApiPlatform\\\\#',
    );
    $messages = layeringRatchet($found, [], [], 'Move the shared code to the module that owns it or to lib/Maho.');

    expect($messages)->toBe([], implode("\n", $messages));
});

it('leaves rate limits and honeypot checks to the services', function () {
    // checkRateLimit() and checkRateLimitByIp() of RateLimitTrait call the rate limiter for the API classes.
    $found = layeringMatches(
        '#/controllers/|^app/code/core/(Mage|Maho)/[A-Za-z]+/Api/|^app/code/core/Maho/ApiPlatform/|^lib/MahoCLI/Commands/#',
        '#(?:->|::)\s*(?:rateLimiter|rateLimiterBy|ipRateLimiter|tooManyAttempts|isHoneypotTriggered'
            . '|checkRateLimit|checkRateLimitByIp)\s*\(#',
    );
    $messages = layeringRatchet(
        $found,
        LAYERING_TRANSPORT_GATE_ALLOWLIST,
        LAYERING_TRANSPORT_GATE_PERMANENT,
        'Move the rate limit or the honeypot check into the service of the flow.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('fails on a new match and on an entry that is higher than the count', function () {
    $permanent = ['b.php' => [2, 'a reason']];

    expect(layeringRatchet(['a.php' => 1, 'b.php' => 2], [], $permanent, 'Fix it.'))
        ->toBe(['a.php: 1 matches, the lists permit 0. Fix it.'])
        ->and(layeringRatchet(['b.php' => 1], [], $permanent, 'Fix it.'))
        ->toBe(['b.php: 1 matches, the lists permit 2. Lower its permanent entry to 1.'])
        ->and(layeringRatchet([], [], $permanent, 'Fix it.'))
        ->toBe(['b.php: 0 matches, the lists permit 2. Remove its permanent entry.'])
        ->and(layeringRatchet(['b.php' => 3], ['b.php' => 2], $permanent, 'Fix it.'))
        ->toBe(['b.php: 3 matches, the lists permit 4. Lower its allowlist entry to 1.'])
        ->and(layeringRatchet(['b.php' => 2], ['b.php' => 2], $permanent, 'Fix it.'))
        ->toBe(['b.php: 2 matches, the lists permit 4. Remove its allowlist entry.'])
        ->and(layeringRatchet(['b.php' => 2], [], ['b.php' => [2, ' ']], 'Fix it.'))
        ->toBe(['b.php: give the reason of its permanent entry.']);
});
