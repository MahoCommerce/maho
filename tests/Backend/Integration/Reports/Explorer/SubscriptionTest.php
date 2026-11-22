<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * An administrator gets a saved report by email each day, week or month, or only when a metric changes.
 */

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
    $this->owner = AdminRoleFixture::login(['report']);
    $this->other = AdminRoleFixture::login(['report']);
    $this->service = Mage::getService('reports/subscription');
    $this->access = Mage_Reports_Model_Explorer_Access::full();
    $this->admin = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $this->config = [];
    $this->setConfig = function (string $path, string $value): void {
        $this->config[$path] ??= $this->admin->getConfig($path);
        $this->admin->setConfig($path, $value);
    };
});

afterEach(function () {
    foreach ($this->config as $path => $value) {
        $this->admin->setConfig($path, $value);
    }
    AdminRoleFixture::delete($this->owner);
    AdminRoleFixture::delete($this->other);
});

/** @param array<string, mixed> $request */
function subscriptionReport(int $adminUserId, bool $shared, array $request = []): Mage_Reports_Model_SavedReport
{
    $report = Mage::getService('reports/savedReport')->newReport()
        ->setName('Weekly sales')
        ->setIsShared($shared)
        ->setRequestData($request + ['dataset' => 'sales_orders', 'metrics' => ['orders', 'net_sales'], 'dimensions' => ['period:day'], 'range' => 'last_7_days']);
    return Mage::getService('reports/savedReport')->save($report, $adminUserId, Mage_Reports_Model_Explorer_Access::full());
}

function subscriptionUtc(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time, new DateTimeZone('UTC'));
}

/**
 * Record the variables of each report email, and stop the send before it reaches a mail server.
 *
 * @return object{emails: list<array<string, mixed>>}
 */
function catchReportEmails(): object
{
    $observer = new class {
        /** @var list<array<string, mixed>> */
        public array $emails = [];

        public function record(Maho\Event\Observer $observer): never
        {
            $this->emails[] = $observer->getEvent()->getData('variables');
            throw new RuntimeException('Stop before the send');
        }
    };
    Mage::unregister('_singleton/subscription_test/observer');
    Mage::register('_singleton/subscription_test/observer', $observer);
    $node = 'global/events/email_template_dispatch_before/observers/subscription_test/';
    Mage::getConfig()->setNode($node . 'type', 'singleton');
    Mage::getConfig()->setNode($node . 'class', 'subscription_test/observer');
    Mage::getConfig()->setNode($node . 'method', 'record');
    return $observer;
}

describe('the sending times', function () {
    beforeEach(function () {
        ($this->setConfig)(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'Europe/Rome');
        ($this->setConfig)('general/locale/firstday', '1');
        ($this->setConfig)(Mage_Reports_Service_Subscription::XML_PATH_SEND_HOUR, '7');
    });

    it('sends at the configured hour of the default time zone', function (string $now, string $frequency, string $expected) {
        expect($this->service->lastSendingTime($frequency, subscriptionUtc($now))->format('Y-m-d H:i'))->toBe($expected);
    })->with([
        'daily, before the hour' => ['2026-11-22 05:00', 'daily', '2026-11-21 06:00'],
        'daily, after the hour' => ['2026-11-22 06:30', 'daily', '2026-11-22 06:00'],
        'weekly, on a Sunday' => ['2026-11-22 08:00', 'weekly', '2026-11-16 06:00'],
        'weekly, on Monday after the hour' => ['2026-11-23 06:30', 'weekly', '2026-11-23 06:00'],
        'monthly, before the hour of the first day' => ['2026-11-01 05:30', 'monthly', '2026-10-01 05:00'],
        'monthly, in summer time' => ['2026-07-01 05:30', 'monthly', '2026-07-01 05:00'],
    ]);

    it('is due once after each sending time', function () {
        $subscription = Mage::getModel('reports/subscription')->setFrequency('daily');
        expect($this->service->isDue($subscription, subscriptionUtc('2026-11-22 08:00')))->toBeTrue();

        $subscription->setCheckedAt('2026-11-22 06:05:00');
        expect($this->service->isDue($subscription, subscriptionUtc('2026-11-22 08:00')))->toBeFalse()
            ->and($this->service->isDue($subscription, subscriptionUtc('2026-11-23 06:00')))->toBeTrue();
    });
});

it('triggers an alert only when the change reaches the threshold in its direction', function (string $direction, ?float $percent, bool $expected) {
    $subscription = Mage::getModel('reports/subscription')
        ->setAlertMetric('net_sales')
        ->setAlertDirection($direction)
        ->setAlertThreshold(20.0);
    expect($this->service->alertTriggered($subscription, $percent))->toBe($expected);
})->with([
    ['down', -25.0, true],
    ['down', -20.0, true],
    ['down', -19.99, false],
    ['down', 30.0, false],
    ['up', 20.0, true],
    ['up', -30.0, false],
    ['any', -30.0, true],
    ['any', 5.0, false],
    ['any', null, false],
]);

describe('saving a subscription', function () {
    it('saves an email for a report that the administrator sees, and sends the first one at the next sending time', function () {
        $owner = (int) $this->owner->getId();
        $report = subscriptionReport($owner, false);
        $subscription = $this->service->getFor((int) $report->getId(), $owner)->setFrequency('weekly');

        $this->service->save($subscription, $owner, $this->access);

        $saved = $this->service->listFor($owner)[(int) $report->getId()] ?? null;
        expect($saved?->getFrequency())->toBe('weekly')
            ->and($saved?->getCheckedAt())->not->toBeNull()
            ->and($saved?->isAlert())->toBeFalse();
    });

    it('lets another administrator get a shared report, but not a private one', function () {
        $other = (int) $this->other->getId();
        $shared = subscriptionReport((int) $this->owner->getId(), true);
        $private = subscriptionReport((int) $this->owner->getId(), false);

        $this->service->save($this->service->getFor((int) $shared->getId(), $other)->setFrequency('daily'), $other, $this->access);
        expect($this->service->listFor($other))->toHaveKey((int) $shared->getId())
            ->and(fn() => $this->service->save($this->service->getFor((int) $private->getId(), $other)->setFrequency('daily'), $other, $this->access))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('refuses a frequency, a metric or a threshold that is not valid', function (array $fields, string $field) {
        $owner = (int) $this->owner->getId();
        $subscription = $this->service->getFor((int) subscriptionReport($owner, false)->getId(), $owner)
            ->setFrequency($fields['frequency'] ?? 'daily')
            ->setAlertMetric($fields['metric'] ?? null)
            ->setAlertDirection($fields['direction'] ?? 'down')
            ->setAlertThreshold($fields['threshold'] ?? 10.0);

        try {
            $this->service->save($subscription, $owner, $this->access);
            $this->fail('The service saved a subscription that is not valid');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toContain($field);
        }
    })->with([
        'frequency' => [['frequency' => 'hourly'], 'frequency'],
        'unknown metric' => [['metric' => 'no_such_metric'], 'alert_metric'],
        'direction' => [['metric' => 'net_sales', 'direction' => 'sideways'], 'alert_direction'],
        'threshold of 0' => [['metric' => 'net_sales', 'threshold' => 0.0], 'alert_threshold'],
        'threshold too high' => [['metric' => 'net_sales', 'threshold' => 5000.0], 'alert_threshold'],
    ]);

    it('refuses an alert on a report without a period', function () {
        $owner = (int) $this->owner->getId();
        $report = subscriptionReport($owner, false, ['dataset' => 'search_terms', 'metrics' => ['searches'], 'dimensions' => ['query_text'], 'range' => null]);
        $subscription = $this->service->getFor((int) $report->getId(), $owner)
            ->setFrequency('daily')->setAlertMetric('searches')->setAlertDirection('up')->setAlertThreshold(10.0);

        expect(fn() => $this->service->save($subscription, $owner, $this->access))->toThrow(Mage_Core_Exception_Input::class);
    });

    it('lets only its administrator change or stop an email', function () {
        $owner = (int) $this->owner->getId();
        $report = subscriptionReport($owner, true);
        $subscription = $this->service->save($this->service->getFor((int) $report->getId(), $owner)->setFrequency('daily'), $owner, $this->access);
        $other = (int) $this->other->getId();

        expect(fn() => $this->service->save($subscription->setFrequency('monthly'), $other, $this->access))->toThrow(Mage_Core_Exception_Forbidden::class)
            ->and(fn() => $this->service->delete($subscription, $other))->toThrow(Mage_Core_Exception_Forbidden::class);

        $this->service->delete($subscription, $owner);
        expect($this->service->listFor($owner))->toBe([]);
    });
});

describe('sending', function () {
    beforeEach(function () {
        \Maho\Queue\QueueManager::reset();
        clearQueueTable();
    });

    afterEach(function () {
        clearQueueTable();
        \Maho\Queue\QueueManager::reset();
    });

    it('queues each due subscription once', function () {
        $owner = (int) $this->owner->getId();
        $subscription = $this->service->save($this->service->getFor((int) subscriptionReport($owner, false)->getId(), $owner)->setFrequency('daily'), $owner, $this->access);
        $subscription->setCheckedAt('2000-01-01 00:00:00')->save();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        expect($this->service->queueDue($now))->toBe(1);
        $rows = fetchQueueRows();
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['message_class'])->toBe(Mage_Reports_Model_Queue_Subscription::class)
            ->and($this->service->queueDue($now))->toBe(0);
    });

    it('emails the report with the access of its administrator', function () {
        $owner = (int) $this->owner->getId();
        $report = subscriptionReport($owner, false);
        $subscription = $this->service->save($this->service->getFor((int) $report->getId(), $owner)->setFrequency('daily'), $owner, $this->access);
        $emails = catchReportEmails();

        expect(fn() => $this->service->send($subscription))->toThrow(RuntimeException::class, 'Stop before the send')
            ->and($emails->emails)->toHaveCount(1)
            ->and($emails->emails[0]['subject'])->toBe('Report: Weekly sales')
            ->and($emails->emails[0]['report_html'])->toContain('Net sales')
            ->and($emails->emails[0]['report_html'])->toContain('saved/' . $report->getId());
    });

    it('sends no email when an alert does not trigger', function () {
        $owner = (int) $this->owner->getId();
        $report = subscriptionReport($owner, false, ['range' => null, 'from' => '2001-01-01', 'to' => '2001-01-07']);
        $subscription = $this->service->save(
            $this->service->getFor((int) $report->getId(), $owner)
                ->setFrequency('daily')->setAlertMetric('net_sales')->setAlertDirection('any')->setAlertThreshold(1.0),
            $owner,
            $this->access,
        );
        $emails = catchReportEmails();

        expect($this->service->send($subscription))->toBeFalse()
            ->and($emails->emails)->toBe([])
            ->and($subscription->getSentAt())->toBeNull();
    });

    it('sends nothing when the administrator no longer sees the report', function () {
        $report = subscriptionReport((int) $this->owner->getId(), true);
        $other = (int) $this->other->getId();
        $subscription = $this->service->save($this->service->getFor((int) $report->getId(), $other)->setFrequency('daily'), $other, $this->access);
        $report->setIsShared(false)->save();
        $emails = catchReportEmails();

        expect($this->service->send($subscription))->toBeFalse()
            ->and($emails->emails)->toBe([]);
    });
});

it('escapes every value of the email, and names the change of an alert in the subject', function () {
    $report = Mage::getModel('reports/savedReport')->setId(7)->setName('<b>Sales</b>');
    $subscription = Mage::getModel('reports/subscription')->setAlertMetric('net_sales')->setAlertDirection('down')->setAlertThreshold(10.0);
    $result = [
        'currency' => 'USD',
        'columns' => [
            ['key' => 'country', 'label' => 'Country', 'kind' => 'dimension'],
            ['key' => 'net_sales', 'label' => 'Net sales', 'kind' => 'metric', 'format' => 'money'],
        ],
        'rows' => [['dimensions' => ['country' => 'XX'], 'labels' => ['country' => '<script>alert(1)</script>'], 'values' => ['net_sales' => 10.0]]],
        'totals' => ['values' => ['net_sales' => 75.0], 'compare' => ['net_sales' => 100.0], 'change' => ['net_sales' => ['absolute' => -25.0, 'percent' => -25.0]]],
        'totalRows' => 1,
        'period' => ['from' => '2026-11-15', 'to' => '2026-11-21', 'timezone' => 'UTC'],
        'comparePeriod' => ['from' => '2026-11-08', 'to' => '2026-11-14'],
    ];

    $email = $this->service->compose($report, $subscription, $result);

    expect($email['subject'])->toContain('<b>Sales</b>')
        ->and($email['subject'])->toContain('fell by 25')
        ->and($email['html'])->not->toContain('<script>')
        ->and($email['html'])->toContain('&lt;script&gt;')
        ->and($email['html'])->toContain('2026-11-08')
        ->and($email['html'])->toContain('saved/7');
});
