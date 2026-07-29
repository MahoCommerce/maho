<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\SolveChallengeOptions;

uses(Tests\MahoBackendTestCase::class);

/**
 * A solved payload is reusable by the visitor who solved it until its challenge expires, and
 * by nobody else. One page posts the same protected form more than once (the checkout re-saves
 * the billing step), which used to be rejected as a replay.
 */

/** Helper with a settable owner, standing in for two different visitors. */
class CaptchaPayloadOwnerHelper extends Maho_Captcha_Helper_Data
{
    public string $owner = 'session:first';

    #[\Override]
    protected function getPayloadOwner(): string
    {
        return $this->owner;
    }
}

/** Solve a challenge the way the browser widget does, and encode the payload it would post. */
function solvedCaptchaPayload(Maho_Captcha_Helper_Data $helper): string
{
    $challenge = $helper->createChallenge();
    $solution = (new Altcha(hmacSignatureSecret: $helper->getHmacKey()))
        ->solveChallenge(new SolveChallengeOptions($challenge, new Pbkdf2()));

    expect($solution)->not->toBeNull();

    return base64_encode((string) json_encode([
        'challenge' => $challenge->toArray(),
        'solution' => $solution->toArray(),
    ]));
}

/** verify() short-circuits on repeat calls within one request; tests need the real path. */
function forgetCaptchaRequestCache(): void
{
    (new ReflectionProperty(Maho_Captcha_Helper_Data::class, '_payloadVerificationCache'))->setValue(null, []);
}

beforeEach(function () {
    $this->helper = new CaptchaPayloadOwnerHelper();
    forgetCaptchaRequestCache();
});

it('accepts a solved payload again from the visitor who solved it', function () {
    $payload = solvedCaptchaPayload($this->helper);

    expect($this->helper->verify($payload))->toBeTrue();

    forgetCaptchaRequestCache();
    expect($this->helper->verify($payload))->toBeTrue();
});

it('rejects a solved payload posted by anyone else', function () {
    $payload = solvedCaptchaPayload($this->helper);

    expect($this->helper->verify($payload))->toBeTrue();

    forgetCaptchaRequestCache();
    $this->helper->owner = 'session:second';
    expect($this->helper->verify($payload))->toBeFalse();
});

it('rejects an unsolved or malformed payload', function () {
    expect($this->helper->verify(''))->toBeFalse();

    forgetCaptchaRequestCache();
    expect($this->helper->verify(base64_encode('{"challenge":{},"solution":{}}')))->toBeFalse();
});
