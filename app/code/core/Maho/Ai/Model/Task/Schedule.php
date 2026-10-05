<?php

/**
 * An instruction that the assistant runs on a schedule, without a browser.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);


/**
 * Each run is an agent task in a new conversation of the administrator who created the
 * schedule, with that administrator's permissions. A run never has its writes approved in
 * advance: a write waits in the conversation until the administrator confirms it.
 */
class Maho_Ai_Model_Task_Schedule extends Mage_Core_Model_Abstract
{
    /** Only the administrator who created the schedule sees its notifications. */
    public const NOTIFY_SELF = 'self';
    /** Every administrator sees its notifications. */
    public const NOTIFY_EVERYONE = 'everyone';

    /** Schedules per administrator: each run costs model calls. */
    public const MAX_PER_ADMIN = 20;

    /** Characters of the previous answer that the next run reads. */
    private const PREVIOUS_ANSWER_CHARS = 2000;

    /** Days ahead that the next run is searched in: a yearly schedule still finds its day. */
    private const SEARCH_DAYS = 400;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/task_schedule');
    }

    public function getAdminUserId(): ?int
    {
        $value = $this->getData('admin_user_id');
        return $value === null ? null : (int) $value;
    }

    public function setAdminUserId(?int $value): static
    {
        return $this->setData('admin_user_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function getInstruction(): ?string
    {
        $value = $this->getData('instruction');
        return $value === null ? null : (string) $value;
    }

    public function setInstruction(?string $value): static
    {
        return $this->setData('instruction', $value);
    }

    public function getCronExpr(): ?string
    {
        $value = $this->getData('cron_expr');
        return $value === null ? null : (string) $value;
    }

    public function setCronExpr(?string $value): static
    {
        return $this->setData('cron_expr', $value);
    }

    public function getNotify(): ?string
    {
        $value = $this->getData('notify');
        return $value === null ? null : (string) $value;
    }

    public function setNotify(?string $value): static
    {
        return $this->setData('notify', $value);
    }

    public function getIsActive(): ?bool
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (bool) $value;
    }

    public function setIsActive(?bool $value = true): static
    {
        return $this->setData('is_active', $value);
    }

    public function getNextRunAt(): ?string
    {
        $value = $this->getData('next_run_at');
        return $value === null ? null : (string) $value;
    }

    public function setNextRunAt(?string $value): static
    {
        return $this->setData('next_run_at', $value);
    }

    public function getLastRunAt(): ?string
    {
        $value = $this->getData('last_run_at');
        return $value === null ? null : (string) $value;
    }

    public function setLastRunAt(?string $value): static
    {
        return $this->setData('last_run_at', $value);
    }

    public function getLastTaskId(): ?int
    {
        $value = $this->getData('last_task_id');
        return $value === null ? null : (int) $value;
    }

    public function setLastTaskId(?int $value): static
    {
        return $this->setData('last_task_id', $value);
    }

    /**
     * Five fields in the time zone of the store: minute, hour, day of the month, month, day of the week.
     *
     * @throws Mage_Core_Exception
     */
    public static function validateCronExpr(string $expr): string
    {
        $fields = preg_split('#\s+#', trim($expr), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($fields) !== 5) {
            throw new Mage_Core_Exception(Mage::helper('ai')->__('A schedule needs five cron fields: minute, hour, day of the month, month and day of the week.'));
        }
        $cron = Mage::getModel('cron/schedule');
        $ranges = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 6]];
        foreach ($fields as $i => $field) {
            $matches = false;
            for ($n = $ranges[$i][0]; $n <= $ranges[$i][1]; $n++) {
                // Throws on a field it cannot read.
                $matches = $cron->matchCronExpression($field, $n) || $matches;
            }
            if (!$matches) {
                throw new Mage_Core_Exception(Mage::helper('ai')->__('The cron field "%s" never matches.', $field));
            }
        }

        return implode(' ', $fields);
    }

    /**
     * Check and normalize the fields before a save from the admin form or the API. A run
     * uses the permissions of its owner, so whoever saves the schedule becomes its owner:
     * nobody can write an instruction that runs with the permissions of someone else. The
     * audience sees what a run found, so a resource audience must be one the owner has.
     *
     * @throws Mage_Core_Exception
     */
    public function validateFor(Mage_Admin_Model_User $owner): static
    {
        $helper = Mage::helper('ai');
        $title = trim(mb_substr((string) $this->getTitle(), 0, 255));
        $instruction = trim((string) $this->getInstruction());
        if ($title === '' || $instruction === '') {
            throw new Mage_Core_Exception($helper->__('A scheduled task needs a title and an instruction.'));
        }
        if (!$this->getId() && Mage::getModel('ai/task_schedule')->getCollection()->addFieldToFilter('admin_user_id', (int) $owner->getId())->getSize() >= self::MAX_PER_ADMIN) {
            throw new Mage_Core_Exception($helper->__('An administrator can have at most %d scheduled tasks.', self::MAX_PER_ADMIN));
        }
        $cron = self::validateCronExpr((string) $this->getCronExpr());
        // One fixed minute: a run every few minutes would cost a model call each time.
        if (!preg_match('/^\d{1,2}$/', explode(' ', $cron)[0])) {
            throw new Mage_Core_Exception($helper->__('A scheduled task runs at most once an hour: give one fixed minute, such as "0 * * * *" or "15 8 * * *".'));
        }
        $notify = trim((string) $this->getNotify());
        $notify = $notify === '' ? self::NOTIFY_SELF : $notify;
        if (!in_array($notify, [self::NOTIFY_SELF, self::NOTIFY_EVERYONE], true)) {
            $notify = Mage_AdminNotification_Model_Inbox::normalizeAclResource($notify);
            if (!self::isAllowedFor($owner, $notify)) {
                throw new Mage_Core_Exception($helper->__('"%s" is not an ACL resource this administrator is allowed. Use "self", "everyone" or a resource such as "sales/order".', $notify));
            }
        }

        return $this->setTitle($title)
            ->setInstruction($instruction)
            ->setCronExpr($cron)
            ->setNotify($notify)
            ->setAdminUserId((int) $owner->getId());
    }

    /** The admin session check, for any administrator: a resource the ACL does not know is not allowed. */
    public static function isAllowedFor(Mage_Admin_Model_User $admin, string $resource): bool
    {
        $session = Mage::getSingleton('admin/session');
        $acl = (int) $session->getUser()?->getId() === (int) $admin->getId() ? $session->getAcl() : null;
        $acl ??= Mage::getResourceModel('admin/acl')->loadAcl();
        $resource = 'admin/' . Mage_AdminNotification_Model_Inbox::normalizeAclResource($resource);
        try {
            return $acl->hasResource($resource) && $acl->isAllowed($admin->getAclRole(), $resource);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * The audiences an administrator can pick: only the owner, everyone, or the
     * administrators of an ACL resource the administrator has, as path => indented title.
     *
     * @return array<string, string>
     */
    public static function audienceOptions(Mage_Admin_Model_User $admin): array
    {
        $helper = Mage::helper('ai');
        $options = [self::NOTIFY_SELF => $helper->__('Only the owner'), self::NOTIFY_EVERYONE => $helper->__('Every administrator')];
        foreach (Mage::getModel('admin/roles')->getResourcesList() as $path => $resource) {
            if (!str_starts_with((string) $path, 'admin/') || !self::isAllowedFor($admin, (string) $path)) {
                continue;
            }
            $options[substr((string) $path, 6)] = str_repeat("\u{00A0}\u{00A0}", max(0, (int) $resource['level'] - 2)) . $helper->__('Allowed: %s', (string) $resource['name']);
        }

        return $options;
    }

    /**
     * The first minute after $afterUtc that the cron expression matches in the time zone of
     * the store, as a UTC database date, or null when no day in the next year matches.
     */
    public function computeNextRun(?string $afterUtc = null): ?string
    {
        $fields = preg_split('#\s+#', (string) $this->getCronExpr(), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($fields) !== 5) {
            return null;
        }
        [$minutes, $hours, $days, $months, $weekdays] = $fields;
        $cron = Mage::getModel('cron/schedule');
        $locale = Mage::app()->getLocale();
        $after = $locale->utcToStore($this->getStoreId(), $afterUtc ?? $locale->formatDateForDb('now'));
        $start = $after->setTime((int) $after->format('G'), (int) $after->format('i'))->modify('+1 minute');
        $day = $start->setTime(0, 0);
        for ($d = 0; $d < self::SEARCH_DAYS; $d++, $day = $day->modify('+1 day')) {
            if (!$cron->matchCronExpression($days, (int) $day->format('j'))
                || !$cron->matchCronExpression($months, (int) $day->format('n'))
                || !$cron->matchCronExpression($weekdays, (int) $day->format('w'))) {
                continue;
            }
            for ($h = 0; $h < 24; $h++) {
                if (!$cron->matchCronExpression($hours, $h)) {
                    continue;
                }
                for ($m = 0; $m < 60; $m++) {
                    if (!$cron->matchCronExpression($minutes, $m)) {
                        continue;
                    }
                    $candidate = $day->setTime($h, $m);
                    if ($candidate >= $start) {
                        return $candidate->setTimezone(new DateTimeZone('UTC'))->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Start one run, unless the previous run still runs or still waits for a confirmation:
     * a second run would only find or propose the same thing again.
     */
    public function run(): ?Maho_Ai_Model_Task
    {
        $previous = $this->getLastTaskId() ? Mage::getModel('ai/task')->load($this->getLastTaskId()) : null;
        if ($previous?->getId()) {
            if ($previous->isPending() || $previous->isProcessing()) {
                return null;
            }
            $conversation = Mage::getModel('ai/conversation')->load((int) $previous->getConversationId());
            if ($conversation->getId() && $conversation->getPendingWrites() !== []) {
                return null;
            }
        }

        $instruction = (string) $this->getInstruction();
        $answer = trim((string) $previous?->getData('response'));
        if ($answer !== '') {
            $instruction .= "\n\nThe previous run of this task, on " . (string) $previous->getData('completed_at') . " UTC, answered:\n"
                . mb_substr($answer, 0, self::PREVIOUS_ANSWER_CHARS);
        }

        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation');
        $conversation->setAdminUserId((int) $this->getAdminUserId());
        $conversation->setStoreId((int) $this->getStoreId());
        $conversation->setTitle(mb_substr(sprintf('%s: %s', (string) $this->getTitle(), Mage::app()->getLocale()->utcToStore($this->getStoreId())->format('Y-m-d H:i')), 0, 255));
        $task = Maho_Ai_Model_Chat_AgentRunner::queue(
            $conversation,
            (int) $this->getAdminUserId(),
            $instruction,
            Maho_Ai_Model_Chat_RunMode::Schedule,
            ['schedule_id' => (int) $this->getId()],
        );

        $this->setLastRunAt(Mage::app()->getLocale()->formatDateForDb('now'));
        $this->setLastTaskId((int) $task->getId());
        $this->save();

        return $task;
    }

    /** The administrator who sees the notifications, when only one does. */
    public function notifyAdminUserId(): ?int
    {
        return $this->getNotify() === self::NOTIFY_SELF || (string) $this->getNotify() === '' ? $this->getAdminUserId() : null;
    }

    /** The ACL resource whose administrators see the notifications, when the audience is one. */
    public function notifyAclResource(): ?string
    {
        $notify = (string) $this->getNotify();

        return in_array($notify, ['', self::NOTIFY_SELF, self::NOTIFY_EVERYONE], true) ? null : $notify;
    }

    #[\Override]
    protected function _beforeSave(): static
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if (!$this->getId()) {
            $this->setData('created_at', $now);
        }
        $this->setData('updated_at', $now);
        // A new or changed schedule, or one switched back on, runs at its next minute, never to catch up.
        if ($this->getNextRunAt() === null || $this->dataHasChangedFor('cron_expr') || ($this->dataHasChangedFor('is_active') && $this->getIsActive())) {
            $this->setNextRunAt($this->computeNextRun($now));
        }

        return parent::_beforeSave();
    }
}
