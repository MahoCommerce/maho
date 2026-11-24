<?php

/**
 * The saved reports that administrators get by email: each day, week or month, or as an alert when a metric changes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Comparison;
use Maho\Report\Request;

class Mage_Reports_Service_Subscription
{
    public const string XML_PATH_SEND_HOUR = 'reports/explorer/email_hour';
    public const string XML_PATH_EMAIL_TEMPLATE = 'reports/explorer/email_template';
    public const string XML_PATH_EMAIL_IDENTITY = 'reports/explorer/email_identity';

    /** The rows of the report in the email. The link opens the whole report. */
    public const int EMAIL_ROWS = 10;

    /** The groupings, and the values of each grouping, that the email of an alert shows as causes of the change. */
    public const int EMAIL_CAUSES = 3;

    public function newSubscription(): Mage_Reports_Model_Subscription
    {
        return Mage::getModel('reports/subscription');
    }

    /** The subscription of $adminUserId to a saved report, or a new one for that report. */
    public function getFor(int $savedReportId, int $adminUserId): Mage_Reports_Model_Subscription
    {
        $subscription = Mage::getResourceModel('reports/subscription_collection')
            ->addFieldToFilter('saved_report_id', $savedReportId)
            ->addFieldToFilter('admin_user_id', $adminUserId)
            ->getFirstItem();
        return $subscription instanceof Mage_Reports_Model_Subscription && $subscription->getId() !== null
            ? $subscription
            : $this->newSubscription()->setSavedReportId($savedReportId)->setAdminUserId($adminUserId);
    }

    /**
     * The subscriptions of $adminUserId, by saved report ID.
     *
     * @return array<int, Mage_Reports_Model_Subscription>
     */
    public function listFor(int $adminUserId): array
    {
        $list = [];
        foreach (Mage::getResourceModel('reports/subscription_collection')->addFieldToFilter('admin_user_id', $adminUserId) as $subscription) {
            if ($subscription instanceof Mage_Reports_Model_Subscription) {
                $list[(int) $subscription->getSavedReportId()] = $subscription;
            }
        }
        return $list;
    }

    /**
     * Check and save the subscription of $adminUserId to a saved report that the administrator sees. The alert metric
     * must be a metric of the dataset of the report, and the dataset must have a period to compare.
     *
     * @throws Mage_Core_Exception_Forbidden
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function save(Mage_Reports_Model_Subscription $subscription, int $adminUserId, Mage_Reports_Model_Explorer_Access $access): Mage_Reports_Model_Subscription
    {
        $helper = Mage::helper('reports');
        if ($subscription->getId() !== null && $subscription->getAdminUserId() !== $adminUserId) {
            throw new Mage_Core_Exception_Forbidden($helper->__('Only the administrator who gets the email can change it.'));
        }
        $report = Mage::getService('reports/savedReport')->getById((int) $subscription->getSavedReportId(), $adminUserId);
        $dataset = Mage::getSingleton('reports/explorer_registry')->getDataset((string) ($report->getRequestData()['dataset'] ?? ''));
        if ($dataset === null || !$access->allowsDataset($dataset)) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('The saved report with ID %s does not exist.', $report->getId()));
        }

        $errors = new Mage_Core_Exception_Input($helper->__('The email of the report is not valid.'))
            ->addValidationResult($subscription->validate(), 'frequency');
        $metric = $subscription->getAlertMetric();
        if ($metric !== null && !$dataset->hasMetric($metric)) {
            $errors->addError('alert_metric', $helper->__('The metric "%s" does not exist in this report.', $metric));
        } elseif ($metric !== null && !$dataset->isDated()) {
            $errors->addError('alert_metric', $helper->__('This report has no period to compare, so it cannot have an alert.'));
        }
        $errors->throwIfErrors();

        if (!$subscription->isAlert()) {
            $subscription->setAlertDirection(null)->setAlertThreshold(null);
        }
        if ($subscription->getId() === null) {
            // The first email goes at the next sending time, not at once
            $subscription->setAdminUserId($adminUserId)->setCheckedAt(Mage_Core_Model_Locale::nowUtc());
        }
        $subscription->save();
        return $subscription;
    }

    /** @throws Mage_Core_Exception_Forbidden */
    public function delete(Mage_Reports_Model_Subscription $subscription, int $adminUserId): void
    {
        if ($subscription->getAdminUserId() !== $adminUserId) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('reports')->__('Only the administrator who gets the email can change it.'));
        }
        $subscription->delete();
    }

    /**
     * The last sending time of a frequency at or before $now, in UTC. The store sends at the configured hour of the
     * default time zone: each day, on the first day of the week, or on the first day of the month.
     */
    public function lastSendingTime(string $frequency, DateTimeImmutable $now): DateTimeImmutable
    {
        $local = $now->setTimezone(new DateTimeZone(Mage_Reports_Model_Explorer_Runner::timezone()));
        $hour = max(0, min(23, Mage::getStoreConfigAsInt(self::XML_PATH_SEND_HOUR, Mage_Core_Model_App::ADMIN_STORE_ID)));
        $time = match ($frequency) {
            Mage_Reports_Model_Subscription::FREQUENCY_WEEKLY => $local->setTime($hour, 0)
                ->modify(sprintf('-%d days', ((int) $local->format('w') - Mage_Reports_Model_Explorer_Runner::firstDayOfWeek() + 7) % 7)),
            Mage_Reports_Model_Subscription::FREQUENCY_MONTHLY => $local->modify('first day of this month')->setTime($hour, 0),
            default => $local->setTime($hour, 0),
        };
        if ($time > $local) {
            $time = match ($frequency) {
                Mage_Reports_Model_Subscription::FREQUENCY_WEEKLY => $time->modify('-7 days'),
                Mage_Reports_Model_Subscription::FREQUENCY_MONTHLY => $local->modify('first day of last month')->setTime($hour, 0),
                default => $time->modify('-1 day'),
            };
        }
        return $time->setTimezone(new DateTimeZone('UTC'));
    }

    /** Whether a sending time passed since the last check of the subscription. */
    public function isDue(Mage_Reports_Model_Subscription $subscription, DateTimeImmutable $now): bool
    {
        $checked = $subscription->getCheckedAt();
        return $checked === null
            || new DateTimeImmutable($checked, new DateTimeZone('UTC')) < $this->lastSendingTime((string) $subscription->getFrequency(), $now);
    }

    /**
     * Queue the check of each subscription that is due. A subscription is marked as checked when it is queued, so a
     * failed check does not send the email twice.
     *
     * @return int the number of queued subscriptions
     */
    public function queueDue(DateTimeImmutable $now): int
    {
        $count = 0;
        foreach (Mage::getResourceModel('reports/subscription_collection') as $subscription) {
            if (!$subscription instanceof Mage_Reports_Model_Subscription || !$this->isDue($subscription, $now)) {
                continue;
            }
            $subscription->setCheckedAt($now->setTimezone(new DateTimeZone('UTC'))->format(Mage_Core_Model_Locale::DATETIME_FORMAT))->save();
            \Maho\Queue\QueueManager::dispatch(
                new Mage_Reports_Model_Queue_Subscription((int) $subscription->getId()),
                queue: Mage_Reports_Model_Queue_Subscription::QUEUE_NAME,
            );
            $count++;
        }
        return $count;
    }

    /**
     * Run the report of a subscription with the access of its administrator, and email it to the administrator. An
     * alert sends the email only when its metric changed by the threshold.
     *
     * @return bool whether the email was sent
     */
    public function send(Mage_Reports_Model_Subscription $subscription): bool
    {
        $user = Mage::getModel('admin/user')->load((int) $subscription->getAdminUserId());
        if ($user->getId() === null || !$user->getIsActive()) {
            return false;
        }
        $adminUserId = (int) $user->getId();
        try {
            $report = Mage::getService('reports/savedReport')->getById((int) $subscription->getSavedReportId(), $adminUserId);
        } catch (Mage_Core_Exception_NoSuchEntity) {
            // The owner deleted the report or stopped sharing it
            return false;
        }
        $access = Mage_Reports_Model_Explorer_Access::forAdminUser($user);
        $request = Request::fromArray($report->getRequestData());
        $dataset = Mage::getSingleton('reports/explorer_registry')->getDataset($request->dataset);
        if ($dataset === null || !$access->allowsDataset($dataset)) {
            return false;
        }

        $metric = $subscription->getAlertMetric();
        if ($metric !== null && $request->compare === Comparison::None) {
            $request = $request->with(['compare' => Comparison::PreviousPeriod->value]);
        }
        $explorer = Mage::getService('reports/explorer');
        $result = $explorer->run($request->with(['limit' => self::EMAIL_ROWS, 'offset' => 0]), $access);

        $causes = null;
        if ($metric !== null) {
            if (!$this->alertTriggered($subscription, $result['totals']['change'][$metric]['percent'] ?? null)) {
                return false;
            }
            try {
                $causes = $explorer->explain($request, $access, $metric, null, self::EMAIL_CAUSES);
            } catch (Mage_Core_Exception $e) {
                Mage::logException($e);
            }
        }

        $email = $this->compose($report, $subscription, $result, $causes);
        $mailer = Mage::getModel('core/email_template_mailer');
        $info = Mage::getModel('core/email_info');
        $info->addTo((string) $user->getEmail(), $user->getName());
        $mailer->addEmailInfo($info)
            ->setSender(Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY, Mage_Core_Model_App::ADMIN_STORE_ID))
            ->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->setTemplateId(Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE, Mage_Core_Model_App::ADMIN_STORE_ID))
            ->setTemplateParams(['subject' => $email['subject'], 'report_html' => $email['html']])
            ->send();

        $subscription->setSentAt(Mage_Core_Model_Locale::nowUtc())->save();
        return true;
    }

    /** Whether the change of the alert metric, in percent, reaches the threshold of the alert in its direction. */
    public function alertTriggered(Mage_Reports_Model_Subscription $subscription, int|float|null $percent): bool
    {
        $threshold = $subscription->getAlertThreshold();
        if ($percent === null || $threshold === null) {
            return false;
        }
        return match ($subscription->getAlertDirection()) {
            Mage_Reports_Model_Subscription::DIRECTION_DOWN => $percent <= -$threshold,
            Mage_Reports_Model_Subscription::DIRECTION_UP => $percent >= $threshold,
            default => abs($percent) >= $threshold,
        };
    }

    /**
     * The subject and the HTML body of the email of a report: the period, the totals, the first rows, the causes of
     * an alert, and a link to the report. Every value is escaped, because the template prints the HTML as it is.
     *
     * @param array<string, mixed> $result the result of Mage_Reports_Service_Explorer::run()
     * @param array<string, mixed>|null $causes the result of Mage_Reports_Service_Explorer::explain()
     * @return array{subject: string, html: string}
     */
    public function compose(Mage_Reports_Model_SavedReport $report, Mage_Reports_Model_Subscription $subscription, array $result, ?array $causes = null): array
    {
        $helper = Mage::helper('reports');
        $currency = (string) $result['currency'];
        $name = (string) $report->getName();
        $metrics = array_values(array_filter($result['columns'], static fn(array $column): bool => $column['kind'] === 'metric'));
        $dimensions = array_values(array_filter($result['columns'], static fn(array $column): bool => $column['kind'] === 'dimension'));
        $totals = $result['totals'] ?? ['values' => [], 'compare' => null, 'change' => null];

        $subject = $helper->__('Report: %s', $name);
        $html = '';
        $alertMetric = $subscription->getAlertMetric();
        if ($alertMetric !== null) {
            $column = array_find($metrics, static fn(array $c): bool => $c['key'] === $alertMetric) ?? ['key' => $alertMetric, 'label' => $alertMetric, 'format' => 'number'];
            $percent = (float) ($totals['change'][$alertMetric]['percent'] ?? 0);
            $sentence = $percent < 0
                ? $helper->__('%1$s fell by %2$s %%.', $column['label'], $this->number(abs($percent), 2))
                : $helper->__('%1$s rose by %2$s %%.', $column['label'], $this->number(abs($percent), 2));
            $subject = $helper->__('Alert: %1$s: %2$s', $name, $sentence);
        }

        $period = $result['period'] ?? null;
        if (is_array($period)) {
            $text = $helper->__('Period: %1$s to %2$s', $period['from'], $period['to']);
            $compare = $result['comparePeriod'] ?? null;
            if (is_array($compare)) {
                $text .= ' ' . $helper->__('(compared with %1$s to %2$s)', $compare['from'], $compare['to']);
            }
            $html .= '<p>' . $helper->escapeHtml($text) . '</p>';
        }

        $cell = static fn(string $text, bool $number = false): string => '<td style="padding:4px 8px;border-bottom:1px solid #e5e5e5;' . ($number ? 'text-align:right;' : '') . '">' . $text . '</td>';
        $head = static fn(string $text, bool $number = false): string => '<th style="padding:4px 8px;border-bottom:2px solid #ccc;' . ($number ? 'text-align:right;' : 'text-align:left;') . '">' . $text . '</th>';

        $html .= '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:12px 0"><tr>'
            . $head($helper->escapeHtml($helper->__('Metric'))) . $head($helper->escapeHtml($helper->__('Total')), true);
        if ($totals['compare'] !== null) {
            $html .= $head($helper->escapeHtml($helper->__('Earlier')), true) . $head($helper->escapeHtml($helper->__('Change')), true);
        }
        $html .= '</tr>';
        foreach ($metrics as $column) {
            $key = $column['key'];
            $html .= '<tr>' . $cell($helper->escapeHtml($column['label'])) . $cell($helper->escapeHtml($this->format($column, $totals['values'][$key] ?? null, $currency)), true);
            if ($totals['compare'] !== null) {
                $change = $totals['change'][$key]['percent'] ?? null;
                $html .= $cell($helper->escapeHtml($this->format($column, $totals['compare'][$key] ?? null, $currency)), true)
                    . $cell($change === null ? '' : $helper->escapeHtml(($change > 0 ? '+' : '') . $this->number($change, 2) . ' %'), true);
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        if (is_array($causes) && $causes['dimensions'] !== []) {
            $html .= '<p><strong>' . $helper->escapeHtml($helper->__('What changed the most')) . '</strong></p><ul>';
            $column = ['format' => $causes['metric']['format']];
            foreach (array_slice($causes['dimensions'], 0, self::EMAIL_CAUSES) as $dimension) {
                foreach ($dimension['drivers'] as $driver) {
                    $line = $dimension['label'] . ': ' . $driver['label'] . ' ' . ($driver['change'] > 0 ? '+' : '-')
                        . $this->format($column, abs((float) $driver['change']), $currency)
                        . ($driver['percent'] === null ? '' : ' (' . ($driver['percent'] > 0 ? '+' : '') . $this->number((float) $driver['percent'], 2) . ' %)');
                    $html .= '<li>' . $helper->escapeHtml($line) . '</li>';
                }
            }
            $html .= '</ul>';
        }

        if ($dimensions !== [] && $result['rows'] !== []) {
            $html .= '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:12px 0"><tr>';
            foreach ($dimensions as $column) {
                $html .= $head($helper->escapeHtml($column['label']));
            }
            foreach ($metrics as $column) {
                $html .= $head($helper->escapeHtml($column['label']), true);
            }
            $html .= '</tr>';
            foreach (array_slice($result['rows'], 0, self::EMAIL_ROWS) as $row) {
                $html .= '<tr>';
                foreach ($dimensions as $column) {
                    $html .= $cell($helper->escapeHtml((string) ($row['labels'][$column['key']] ?? $row['dimensions'][$column['key']] ?? '')));
                }
                foreach ($metrics as $column) {
                    $html .= $cell($helper->escapeHtml($this->format($column, $row['values'][$column['key']] ?? null, $currency)), true);
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
            if ((int) $result['totalRows'] > self::EMAIL_ROWS) {
                $html .= '<p>' . $helper->escapeHtml($helper->__('The first %1$d rows of %2$d.', self::EMAIL_ROWS, (int) $result['totalRows'])) . '</p>';
            }
        }

        $url = Mage::helper('adminhtml')->getUrl('adminhtml/report_explorer/index', ['saved' => (int) $report->getId(), '_nosecret' => true]);
        $html .= '<p><a href="' . $helper->escapeUrl($url) . '">' . $helper->escapeHtml($helper->__('Open the report')) . '</a></p>';

        return ['subject' => $subject, 'html' => $html];
    }

    /** @param array{format?: string} $column */
    private function format(array $column, mixed $value, string $currency): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        return match ($column['format'] ?? 'number') {
            'money' => Mage::app()->getLocale()->formatCurrency((float) $value, $currency),
            'percent' => $this->number((float) $value, 2) . ' %',
            'decimal' => $this->number((float) $value, 2),
            default => $this->number((float) $value, floor((float) $value) == (float) $value ? 0 : 2),
        };
    }

    private function number(float $value, int $decimals): string
    {
        $formatter = new NumberFormatter(Mage::app()->getLocale()->getLocaleCode(), NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        return (string) $formatter->format($value);
    }
}
