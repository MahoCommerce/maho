<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Email Automation Observer
 *
 * Handles segment membership changes to trigger email sequences
 *
 * Error Handling Pattern:
 * - Operational failures (customer ineligible, already processed): Mark as skipped/failed, log, continue
 * - Critical errors (database issues, missing templates): Log exception, mark failed, re-throw
 * - Expected conditions (unsubscribed, no email): Mark as skipped, log info, continue silently
 * - All exceptions are logged to customer_segmentation.log for debugging
 */
class Maho_CustomerSegmentation_Model_Observer_EmailAutomation
{
    /**
     * Handle segment refresh completion
     * Triggered after segment customer membership is updated
     */
    public function onSegmentRefreshAfter(\Maho\Event\Observer $observer): void
    {
        /** @var Maho_CustomerSegmentation_Model_Segment $segment */
        $segment = $observer->getEvent()->getSegment();
        $matchedCustomers = $observer->getEvent()->getMatchedCustomers();
        $previousCustomers = $observer->getEvent()->getPreviousCustomers();

        Mage::log(
            sprintf(
                'Observer fired: segment_id=%s, matched_customers=%s, previous_customers=%s, has_automation=%s',
                $segment->getId(),
                implode(',', $matchedCustomers),
                implode(',', $previousCustomers),
                $segment->hasEmailAutomation() ? 'YES' : 'NO',
            ),
            Mage::LOG_INFO,
        );

        if (!$segment->hasEmailAutomation()) {
            return;
        }

        try {
            $this->processSegmentChanges($segment, $matchedCustomers, $previousCustomers);
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log(
                "Email automation failed for segment {$segment->getId()}: " . $e->getMessage(),
                Mage::LOG_ERROR,
            );
        }
    }

    /**
     * Process segment membership changes and trigger appropriate sequences
     */
    protected function processSegmentChanges(
        Maho_CustomerSegmentation_Model_Segment $segment,
        array $currentMatchedCustomers,
        array $previousCustomers,
    ): void {
        $segmentId = $segment->getId();

        // Determine who entered and who exited
        $enteredCustomers = array_diff($currentMatchedCustomers, $previousCustomers);
        $exitedCustomers = array_diff($previousCustomers, $currentMatchedCustomers);

        // Check if segment has enter sequences
        $hasEnterSequences = Mage::getResourceModel('customersegmentation/emailSequence_collection')
            ->addFieldToFilter('segment_id', $segmentId)
            ->addFieldToFilter('trigger_event', Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER)
            ->addFieldToFilter('is_active', 1)
            ->getSize() > 0;

        // Check if segment has exit sequences
        $hasExitSequences = Mage::getResourceModel('customersegmentation/emailSequence_collection')
            ->addFieldToFilter('segment_id', $segmentId)
            ->addFieldToFilter('trigger_event', Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_EXIT)
            ->addFieldToFilter('is_active', 1)
            ->getSize() > 0;

        // Handle customers entering the segment
        if (!empty($enteredCustomers) && $hasEnterSequences) {
            $this->handleCustomersEntering($segment, $enteredCustomers);
        }

        // Handle customers exiting the segment
        if (!empty($exitedCustomers)) {
            $this->handleCustomersExiting($segment, $exitedCustomers, $hasExitSequences);
        }
    }

    /**
     * Handle customers entering a segment
     */
    protected function handleCustomersEntering(
        Maho_CustomerSegmentation_Model_Segment $segment,
        array $customerIds,
    ): void {
        foreach ($customerIds as $customerId) {
            $customerId = (int) $customerId;

            // Verify customer is subscribed to newsletter
            if (!$this->isCustomerSubscribed($customerId)) {
                continue;
            }

            // Start email sequence for entering customer
            $segment->startEmailSequence($customerId, Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER);
        }

        Mage::log(
            'Started email sequences for ' . count($customerIds) . " customers entering segment {$segment->getId()}",
            Mage::LOG_INFO,
        );
    }

    /**
     * Handle customers exiting a segment
     */
    protected function handleCustomersExiting(
        Maho_CustomerSegmentation_Model_Segment $segment,
        array $customerIds,
        bool $hasExitSequences,
    ): void {
        $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');

        // Stop any scheduled enter sequences for exited customers
        $stoppedEnter = $resource->stopSequencesForCustomers(
            (int) $segment->getId(),
            $customerIds,
            Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER,
        );

        // Start exit sequences if configured
        if ($hasExitSequences) {
            $exitStarted = 0;
            foreach ($customerIds as $customerId) {
                $customerId = (int) $customerId;

                // Verify customer is subscribed to newsletter
                if (!$this->isCustomerSubscribed($customerId)) {
                    continue;
                }

                $segment->startEmailSequence($customerId, Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_EXIT);
                $exitStarted++;
            }

            Mage::log(
                "Started {$exitStarted} exit email sequences for segment {$segment->getId()}",
                Mage::LOG_INFO,
            );
        }

        if ($stoppedEnter > 0) {
            Mage::log(
                "Stopped {$stoppedEnter} enter email sequences for customers exiting segment {$segment->getId()}",
                Mage::LOG_INFO,
            );
        }
    }

    /**
     * Check if customer is subscribed to newsletter
     */
    protected function isCustomerSubscribed(int $customerId): bool
    {
        $collection = Mage::getResourceModel('newsletter/subscriber_collection');
        $collection->addFieldToFilter('customer_id', $customerId);
        $subscriber = $collection->getFirstItem();

        return $subscriber->getId() &&
               (int) $subscriber->getSubscriberStatus() === Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
    }

    /**
     * Handle newsletter subscription changes
     * Stop email sequences when customer unsubscribes
     */
    public function onNewsletterSubscriberSaveAfter(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Newsletter_Model_Subscriber $subscriber */
        $subscriber = $observer->getEvent()->getSubscriber();

        // If customer unsubscribed, stop all their scheduled sequences
        if ((int) $subscriber->getSubscriberStatus() === Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED &&
            $subscriber->getCustomerId()) {

            $this->stopAllCustomerSequences((int) $subscriber->getCustomerId());
        }
    }

    /**
     * Stop all scheduled sequences for a customer
     */
    protected function stopAllCustomerSequences(int $customerId): void
    {
        $collection = Mage::getResourceModel('customersegmentation/sequenceProgress_collection')
            ->addCustomerFilter($customerId)
            ->addStatusFilter(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED);

        $stopped = 0;
        foreach ($collection as $progress) {
            /** @var Maho_CustomerSegmentation_Model_SequenceProgress $progress */
            $progress->markAsSkipped();
            $stopped++;
        }

        if ($stopped > 0) {
            Mage::log(
                "Stopped {$stopped} email sequences for unsubscribed customer {$customerId}",
                Mage::LOG_INFO,
            );
        }
    }

    /**
     * Handle customer deletion
     * Clean up sequence progress when customer is deleted
     */
    public function onCustomerDeleteAfter(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getEvent()->getCustomer();

        if ($customer->getId()) {
            $this->cleanupCustomerSequences((int) $customer->getId());
        }
    }

    /**
     * Clean up all sequence data for deleted customer
     */
    protected function cleanupCustomerSequences(int $customerId): void
    {
        $collection = Mage::getResourceModel('customersegmentation/sequenceProgress_collection')
            ->addCustomerFilter($customerId);

        $deleted = 0;
        foreach ($collection as $progress) {
            /** @var Maho_CustomerSegmentation_Model_SequenceProgress $progress */
            $progress->delete();
            $deleted++;
        }

        if ($deleted > 0) {
            Mage::log(
                "Cleaned up {$deleted} sequence records for deleted customer {$customerId}",
                Mage::LOG_INFO,
            );
        }
    }

    /**
     * Process ready email sequences (called by cron)
     * This processes scheduled emails that are ready to send
     */
    public function processScheduledEmails(\Maho\Event\Observer $observer): void
    {
        $startTime = microtime(true);
        $processed = 0;
        $failed = 0;

        try {
            $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');
            $readySequences = $resource->getReadyToSendSequences(100); // Process 100 at a time

            foreach ($readySequences as $sequenceData) {
                try {
                    $this->sendSequenceEmail($sequenceData);
                    $processed++;
                } catch (Exception $e) {
                    $failed++;
                    Mage::logException($e);
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            if ($processed > 0 || $failed > 0) {
                Mage::log(
                    "Email automation cron: processed {$processed}, failed {$failed} emails in {$duration}s",
                    Mage::LOG_INFO,
                );
            }

        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log(
                'Email automation cron failed: ' . $e->getMessage(),
                Mage::LOG_ERROR,
            );
        }
    }

    /**
     * Send individual sequence email
     */
    protected function sendSequenceEmail(array $sequenceData): void
    {
        $progressId = $sequenceData['progress_id'];
        $customerId = $sequenceData['customer_id'];
        $templateId = $sequenceData['template_id'];
        $generateCoupon = (bool) $sequenceData['generate_coupon'];

        // Load progress record
        $progress = Mage::getModel('customersegmentation/sequenceProgress')->load($progressId);
        if (!$progress->getId() || $progress->getStatus() !== Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED) {
            return; // Already processed or invalid
        }

        // Validate customer email from pre-loaded data
        if (empty($sequenceData['customer_email'])) {
            Mage::log(
                "Skipping sequence email for customer {$customerId}: No email address",
                Mage::LOG_INFO,
            );
            $progress->markAsSkipped();
            return;
        }

        // Load customer model to get all attributes (EAV)
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            Mage::log(
                "Skipping sequence email for customer {$customerId}: Customer not found",
                Mage::LOG_INFO,
            );
            $progress->markAsSkipped();
            return;
        }

        // Load template
        $template = Mage::getModel('newsletter/template')->load($templateId);
        if (!$template->getId()) {
            $progress->markAsFailed();
            throw new Exception("Newsletter template {$templateId} not found");
        }

        // Load subscriber
        $subscriberCollection = Mage::getResourceModel('newsletter/subscriber_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

        $subscriber = $subscriberCollection->getFirstItem();
        if (!$subscriber->getId()) {
            Mage::log(
                "Skipping sequence email for customer {$customerId}: Subscriber not found",
                Mage::LOG_INFO,
            );
            $progress->markAsSkipped();
            return;
        }

        // Generate variables for template
        $variables = $this->getTemplateVariables($customer, $sequenceData);
        $variables['subscriber'] = $subscriber; // Add subscriber for unsubscribe link

        // Generate coupon if needed
        if ($generateCoupon && !empty($sequenceData['coupon_sales_rule_id'])) {
            $couponCode = $this->generateSequenceCoupon($customerId, $sequenceData);
            if ($couponCode) {
                // Load the coupon to get expiration date
                $coupon = Mage::getModel('salesrule/coupon')->loadByCode($couponCode);
                $expirationDateRaw = $coupon->getExpirationDate();

                // Convert DateTime to string if needed
                $expirationDate = null;
                if ($expirationDateRaw instanceof DateTime) {
                    $expirationDate = $expirationDateRaw->format('Y-m-d H:i:s');
                } elseif (is_string($expirationDateRaw) && !empty($expirationDateRaw)) {
                    $expirationDate = $expirationDateRaw;
                }

                // Add coupon variables to template
                $couponHelper = Mage::helper('customersegmentation/coupon');
                $rule = Mage::getModel('salesrule/rule')->load($sequenceData['coupon_sales_rule_id']);
                $couponVars = $couponHelper->getCouponTemplateVariables($couponCode, $expirationDate, $rule);
                $variables = array_merge($variables, $couponVars);
            }
        }

        // Process template with variables to get the HTML content
        $processedText = $template->getProcessedTemplate($variables);
        $processedSubject = $template->getProcessedTemplateSubject($variables);

        // Create newsletter queue with processed content
        $queue = Mage::getModel('newsletter/queue');
        $queue->setTemplateId($templateId)
              ->setNewsletterType(Mage_Newsletter_Model_Template::TYPE_HTML)
              ->setNewsletterText($processedText)
              ->setNewsletterStyles($template->getTemplateStyles())
              ->setNewsletterSubject($processedSubject)
              ->setNewsletterSenderName($template->getTemplateSenderName())
              ->setNewsletterSenderEmail($template->getTemplateSenderEmail())
              ->setQueueStatus(Mage_Newsletter_Model_Queue::STATUS_SENDING)
              ->setQueueStartAt(Mage::getSingleton('core/date')->gmtDate())
              ->setAutomationSource('customer_segmentation')
              ->setAutomationSourceId($sequenceData['segment_id'])
              ->save();

        // Add subscriber to queue
        $queue->addSubscribersToQueue([$subscriber->getId()]);

        // Send the email
        $queue->sendPerSubscriber(1);

        // Mark as sent
        $progress->markAsSent((int) $queue->getId());

        Mage::log(
            "Sent automation email to customer {$customerId}, template {$templateId}, queue {$queue->getId()}",
            Mage::LOG_INFO,
        );
    }

    /**
     * Get template variables for email
     */
    protected function getTemplateVariables(Mage_Customer_Model_Customer $customer, array $sequenceData): array
    {
        return [
            'customer' => $customer,
            'customer_name' => $customer->getName(),
            'customer_firstname' => $customer->getFirstname(),
            'customer_lastname' => $customer->getLastname(),
            'customer_email' => $customer->getEmail(),
            'segment_name' => $sequenceData['segment_name'],
            'step_number' => $sequenceData['step_number'],
            'store' => Mage::app()->getStore($customer->getStoreId()),
        ];
    }

    /**
     * Generate coupon for sequence email
     */
    protected function generateSequenceCoupon(int $customerId, array $sequenceData): ?string
    {
        $helper = Mage::helper('customersegmentation/coupon');

        $prefix = $sequenceData['coupon_prefix'] ?: 'SEQ';
        $expireDays = (int) ($sequenceData['coupon_expires_days'] ?: 30);

        return $helper->generateCustomerCoupon(
            $customerId,
            $sequenceData['coupon_sales_rule_id'],
            $prefix,
            $expireDays,
        );
    }
}
