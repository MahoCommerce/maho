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
 * a rate limit or a honeypot belongs to the service, so that no transport can forget it. A transport
 * does not save or delete a model itself, and a service does not know which transport called it.
 *
 * Each rule has two lists. The allowlist holds the debt: the files that broke the rule when it came in,
 * with the number of matches in each file. It only shrinks: a pull request that fixes a file lowers or
 * removes its entry, and the test fails until it does. The permanent list holds the matches that stay,
 * each with its count and its reason. A file can have entries in both lists. When the count of a file
 * goes down, the test fails until its entries go down too. Comments and docblocks are not scanned.
 *
 * The legacy API (Mage_Api, Mage_Api2 and the Api.php and Api2 models of each module) is deprecated,
 * so no rule scans it.
 */

/** The files of the legacy API. */
const LAYERING_LEGACY_API = '#^app/code/core/Mage/Api2?/|/Model/(?:.+/)?Api\.php$|/Model/Api2/#';

/** The API classes, as transports. */
const LAYERING_API_TRANSPORT = '#^app/code/core/(Mage|Maho)/[A-Za-z]+/Api/|^app/code/core/Maho/ApiPlatform/symfony/#';

/**
 * Files that create an exception with a status code outside Maho_ApiPlatform, and must stop. The
 * exception is a Symfony HttpException, or an ApiException of Maho_ApiPlatform or one of its subclasses.
 */
const LAYERING_HTTP_EXCEPTION_ALLOWLIST = [];

/** Files that create an exception with a status code outside Maho_ApiPlatform for a reason that stays. */
const LAYERING_HTTP_EXCEPTION_PERMANENT = [
    'app/code/core/Maho/Ai/Api/Chat/ChatController.php' => [17, 'the admin assistant chat, which only this controller starts'],
    'app/code/core/Maho/SocialLogin/Api/SocialAuthProcessor.php' => [1, 'the 503 of the token endpoint when the provider does not answer'],
];

/** Files in a Service folder that read the request, the superglobals or the session, and must stop. */
const LAYERING_SERVICE_INPUT_ALLOWLIST = [];

/** Files in a Service folder that read the request, the superglobals or the session for a reason that stays. */
const LAYERING_SERVICE_INPUT_PERMANENT = [];

/** Files in a Service folder that use the API module, and must stop. */
const LAYERING_SERVICE_API_ALLOWLIST = [];

/** Files in a Service folder that use the API module for a reason that stays. */
const LAYERING_SERVICE_API_PERMANENT = [];

/** Files in a Service folder that ask a store for the admin area, and must stop. */
const LAYERING_SERVICE_AREA_ALLOWLIST = [];

/** Files in a Service folder that ask a store for the admin area for a reason that stays. */
const LAYERING_SERVICE_AREA_PERMANENT = [];

/** Files in a Service folder that read a Channel enum, and must stop. */
const LAYERING_SERVICE_CHANNEL_ALLOWLIST = [];

/** Files in a Service folder that read a Channel enum for a reason that stays. */
const LAYERING_SERVICE_CHANNEL_PERMANENT = [];

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

/** API classes that save or delete a model themselves, and must stop. */
const LAYERING_API_WRITE_ALLOWLIST = [
    'app/code/core/Maho/ApiPlatform/symfony/Processor.php' => 2,
];

/** API classes that save or delete a model themselves for a reason that stays. */
const LAYERING_API_WRITE_PERMANENT = [
    'app/code/core/Maho/Ai/Api/Agent/MemoryTool.php' => [2, 'the notes of the admin assistant, which only this tool writes'],
    'app/code/core/Maho/Ai/Api/Chat/Assistant.php' => [10, 'the conversation of the admin assistant, which only the chat starts'],
    'app/code/core/Maho/Ai/Api/Chat/ChatController.php' => [1, 'the conversation of the admin assistant, which only this controller starts'],
];

/** CLI commands that save or delete a model themselves, and must stop. */
const LAYERING_CLI_WRITE_ALLOWLIST = [];

/** CLI commands that save or delete a model themselves for a reason that stays. */
const LAYERING_CLI_WRITE_PERMANENT = [];

/** Controllers that save or delete a model themselves, and must stop. */
const LAYERING_CONTROLLER_WRITE_ALLOWLIST = [
    'app/code/core/Mage/Adminhtml/controllers/Api/RoleController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/Api/UserController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/Catalog/SearchController.php' => 3,
    'app/code/core/Mage/Adminhtml/controllers/Checkout/AgreementController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/Newsletter/QueueController.php' => 5,
    'app/code/core/Mage/Adminhtml/controllers/Newsletter/TemplateController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/NotificationController.php' => 4,
    'app/code/core/Mage/Adminhtml/controllers/Permissions/BlockController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/Permissions/RoleController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/Permissions/VariableController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/Sales/Billing/AgreementController.php' => 1,
    'app/code/core/Mage/Adminhtml/controllers/Sales/Order/CreditmemoController.php' => 1,
    'app/code/core/Mage/Adminhtml/controllers/Sales/Order/ShipmentController.php' => 1,
    'app/code/core/Mage/Adminhtml/controllers/Sales/Order/StatusController.php' => 1,
    'app/code/core/Mage/Adminhtml/controllers/Sales/OrderController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/Sales/TransactionsController.php' => 1,
    'app/code/core/Mage/Adminhtml/controllers/SitemapController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/System/Convert/ProfileController.php' => 3,
    'app/code/core/Mage/Adminhtml/controllers/System/Email/LogController.php' => 1,
    'app/code/core/Mage/Adminhtml/controllers/System/VariableController.php' => 2,
    'app/code/core/Mage/Adminhtml/controllers/TagController.php' => 4,
    'app/code/core/Mage/Catalog/controllers/Product/CompareController.php' => 1,
    'app/code/core/Mage/Checkout/controllers/OnepageController.php' => 1,
    'app/code/core/Mage/Directory/controllers/Adminhtml/Directory/CountryController.php' => 3,
    'app/code/core/Mage/Oauth/controllers/Adminhtml/Oauth/ConsumerController.php' => 2,
    'app/code/core/Mage/Payment/controllers/Adminhtml/Payment/RestrictionController.php' => 4,
    'app/code/core/Mage/Tag/controllers/IndexController.php' => 1,
    'app/code/core/Mage/Widget/controllers/Adminhtml/Widget/InstanceController.php' => 2,
    'app/code/core/Maho/Ai/controllers/Adminhtml/Ai/ChatController.php' => 3,
    'app/code/core/Maho/Ai/controllers/Adminhtml/Ai/ScheduleController.php' => 5,
    'app/code/core/Maho/ApiPlatform/controllers/Adminhtml/Apiplatform/UserController.php' => 2,
    'app/code/core/Maho/CatalogLinkRule/controllers/Adminhtml/Cataloglinkrule/RuleController.php' => 4,
    'app/code/core/Maho/CustomerSegmentation/controllers/Adminhtml/CustomerSegmentation_IndexController.php' => 2,
    'app/code/core/Maho/FeedManager/controllers/Adminhtml/Feedmanager/CategoryController.php' => 3,
    'app/code/core/Maho/FeedManager/controllers/Adminhtml/Feedmanager/DestinationController.php' => 4,
    'app/code/core/Maho/FeedManager/controllers/Adminhtml/Feedmanager/DynamicruleController.php' => 4,
    'app/code/core/Maho/FeedManager/controllers/Adminhtml/Feedmanager/FeedController.php' => 8,
    'app/code/core/Maho/MediaCleaner/controllers/Adminhtml/MediacleanerController.php' => 2,
    'app/code/core/Maho/Paypal/controllers/CheckoutController.php' => 3,
    'app/code/core/Maho/Paypal/controllers/VaultController.php' => 2,
];

/**
 * Controllers that call save() or delete() for a reason that stays. The rule cannot tell the cart
 * facade from a model, so the calls on the cart facade are here.
 */
const LAYERING_CONTROLLER_WRITE_PERMANENT = [
    'app/code/core/Mage/Checkout/controllers/CartController.php' => [9, 'the cart facade, which calls the cart service'],
    'app/code/core/Mage/Wishlist/controllers/IndexController.php' => [2, 'the cart facade, which calls the cart service'],
    'app/code/core/Mage/Wishlist/controllers/SharedController.php' => [1, 'the cart facade, which calls the cart service'],
];

/**
 * The number of files and the number of matches of each permanent list. A new permanent entry is a
 * decision of a maintainer, so a pull request that changes a permanent list changes this constant too.
 */
const LAYERING_PERMANENT_TOTALS = [
    'LAYERING_API_WRITE_PERMANENT' => [3, 13],
    'LAYERING_CLI_WRITE_PERMANENT' => [0, 0],
    'LAYERING_CONTROLLER_WRITE_PERMANENT' => [3, 12],
    'LAYERING_HTTP_EXCEPTION_PERMANENT' => [2, 18],
    'LAYERING_SERVICE_API_PERMANENT' => [0, 0],
    'LAYERING_SERVICE_AREA_PERMANENT' => [0, 0],
    'LAYERING_SERVICE_CHANNEL_PERMANENT' => [0, 0],
    'LAYERING_SERVICE_INPUT_PERMANENT' => [0, 0],
    'LAYERING_TRANSPORT_GATE_PERMANENT' => [6, 13],
];

/**
 * @return array<string, string> the code of each PHP file under app/code/core and lib/MahoCLI, by
 *                               its path from the Maho root. The legacy API is left out
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
            $path = substr($file->getPathname(), strlen($root));
            if (str_ends_with($path, '.php') && !preg_match(LAYERING_LEGACY_API, $path)) {
                $sources[$path] = (string) file_get_contents($file->getPathname());
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

/**
 * Count the matches of the rule $rule in each file.
 *
 * @return array<string, int> the count of each file that has a match, by path
 */
function layeringFound(string $rule): array
{
    $service = '#^app/code/core/(?!Maho/ApiPlatform/).*/Service/#';
    $write = '#->\s*(?:save|delete)\s*\(\s*\)#';

    return match ($rule) {
        // A transport detects a malformed request, so an Api/ class (a GraphQL handler too) may send a
        // 400, and a 422 for a required field that is not in the body.
        'http exception' => layeringMatches(
            '#^(?!app/code/core/Maho/ApiPlatform/)#',
            '#\bnew\s+\\\\?(?:[A-Za-z_]\w*\\\\)*(\w*HttpException|(?:Api|NotFound|Validation|Authentication)Exception)\s*\('
                . '|\b(?:Api|NotFound|Validation|Authentication)Exception::(\w+)\s*\(#',
            fn(string $path, array $match): bool => !(preg_match(LAYERING_API_TRANSPORT, $path)
                && ($match[1] === 'BadRequestHttpException' || str_starts_with($match[0], 'ValidationException::requiredField'))),
        ),
        'service input' => layeringMatches(
            $service,
            '#\bgetRequest\s*\(|\bgetParams?\s*\(|\$_(?:FILES|POST|GET|REQUEST)\b'
                . '|Mage::get(?:Singleton|Model)\(\s*[\'"][a-z0-9_]+/session(?:_[a-z0-9_]+)?[\'"]'
                . '|\b(?:Mage|Maho)_[A-Za-z]+_Model_Session\w*#',
        ),
        'service api' => layeringMatches(
            $service,
            '#\bMaho\\\\ApiPlatform\\\\|\bMaho_ApiPlatform_|Mage::helper\(\s*[\'"]apiplatform(?:/\w+)?[\'"]#',
        ),
        'service area' => layeringMatches($service, '#->\s*isAdmin\s*\(#'),
        'service channel' => layeringMatches($service, '#\b(?:\w+_)?Channel::#'),
        // checkRateLimit() and checkRateLimitByIp() of RateLimitTrait call the rate limiter for the API classes.
        'transport gate' => layeringMatches(
            '#/controllers/|^app/code/core/(Mage|Maho)/[A-Za-z]+/Api/|^app/code/core/Maho/ApiPlatform/|^lib/MahoCLI/Commands/#',
            '#(?:->|::)\s*(?:rateLimiter|rateLimiterBy|ipRateLimiter|tooManyAttempts|isHoneypotTriggered'
                . '|checkRateLimit|checkRateLimitByIp)\s*\(#',
        ),
        // A call with an argument is a call of a service or of the database adapter, so it does not count.
        'api write' => layeringMatches(LAYERING_API_TRANSPORT, $write),
        'cli write' => layeringMatches('#^lib/MahoCLI/Commands/#', $write),
        'controller write' => layeringMatches('#/controllers/#', $write),
    };
}

it('creates an exception with a status code only at the API edge', function () {
    $messages = layeringRatchet(
        layeringFound('http exception'),
        LAYERING_HTTP_EXCEPTION_ALLOWLIST,
        LAYERING_HTTP_EXCEPTION_PERMANENT,
        'Throw a Mage_Core_Exception subclass: ApiExceptionListener maps it to the status code.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('keeps the request and the session out of every service', function () {
    $messages = layeringRatchet(
        layeringFound('service input'),
        LAYERING_SERVICE_INPUT_ALLOWLIST,
        LAYERING_SERVICE_INPUT_PERMANENT,
        'Read the input in the transport and write it into the model that the service takes.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('keeps the classes and the helpers of the API module out of every service', function () {
    $messages = layeringRatchet(
        layeringFound('service api'),
        LAYERING_SERVICE_API_ALLOWLIST,
        LAYERING_SERVICE_API_PERMANENT,
        'Move the shared code to the module that owns it or to lib/Maho.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('keeps the area of the request out of every service', function () {
    $messages = layeringRatchet(
        layeringFound('service area'),
        LAYERING_SERVICE_AREA_ALLOWLIST,
        LAYERING_SERVICE_AREA_PERMANENT,
        'A service decides by the role of the caller: let the transport pass the role.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('keeps the transport that called out of every service', function () {
    $messages = layeringRatchet(
        layeringFound('service channel'),
        LAYERING_SERVICE_CHANNEL_ALLOWLIST,
        LAYERING_SERVICE_CHANNEL_PERMANENT,
        'A service does not know its transport: let the transport pass the value that differs.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('leaves rate limits and honeypot checks to the services', function () {
    $messages = layeringRatchet(
        layeringFound('transport gate'),
        LAYERING_TRANSPORT_GATE_ALLOWLIST,
        LAYERING_TRANSPORT_GATE_PERMANENT,
        'Move the rate limit or the honeypot check into the service of the flow.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
});

it('leaves the save and the delete of a model to the services', function (string $rule, array $allowed, array $permanent) {
    $messages = layeringRatchet(
        layeringFound($rule),
        $allowed,
        $permanent,
        'Call the service of the flow. A flow that only this transport starts can stay: add the file to the list.',
    );

    expect($messages)->toBe([], implode("\n", $messages));
})->with([
    'an API class' => ['api write', LAYERING_API_WRITE_ALLOWLIST, LAYERING_API_WRITE_PERMANENT],
    'a CLI command' => ['cli write', LAYERING_CLI_WRITE_ALLOWLIST, LAYERING_CLI_WRITE_PERMANENT],
    'a controller' => ['controller write', LAYERING_CONTROLLER_WRITE_ALLOWLIST, LAYERING_CONTROLLER_WRITE_PERMANENT],
]);

it('adds a permanent entry only with a decision of a maintainer', function () {
    $totals = [];
    foreach (array_keys(LAYERING_PERMANENT_TOTALS) as $name) {
        $list = constant($name);
        $totals[$name] = [count($list), array_sum(array_column($list, 0))];
    }
    $lists = array_filter(array_keys(get_defined_constants(true)['user']), fn(string $name): bool => (bool) preg_match('#^LAYERING_\w+_PERMANENT$#', $name));
    sort($lists);

    expect(array_keys(LAYERING_PERMANENT_TOTALS))->toBe($lists, 'Give each permanent list its entry in LAYERING_PERMANENT_TOTALS, sorted by name.')
        ->and($totals)->toBe(
            LAYERING_PERMANENT_TOTALS,
            'A permanent list changed. A new permanent entry needs a decision of a maintainer in the pull request. '
                . 'With that decision, or after a fix that removes an entry, update LAYERING_PERMANENT_TOTALS.',
        );
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
