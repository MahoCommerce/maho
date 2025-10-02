<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Email Automation Observer
 *
 * Handles segment membership changes to trigger email sequences
 */
class Maho_CustomerSegmentation_Model_Observer_EmailAutomation
{
    /**
     * Handle segment refresh completion
     * Triggered after segment customer membership is updated
     */
    public function onSegmentRefreshAfter(Varien_Event_Observer $observer): void
    {
        /** @var Maho_CustomerSegmentation_Model_Segment $segment */
        $segment = $observer->getEvent()->getSegment();
        $matchedCustomers = $observer->getEvent()->getMatchedCustomers();

        Mage::log(
            sprintf(
                'Observer fired: segment_id=%s, matched_customers=%s, has_automation=%s',
                $segment->getId(),
                implode(',', $matchedCustomers),
                $segment->hasEmailAutomation() ? 'YES' : 'NO',
            ),
            Mage::LOG_INFO,
            'customer_segmentation.log',
        );

        if (!$segment->hasEmailAutomation()) {
            return;
        }

        try {
            $this->processSegmentChanges($segment, $matchedCustomers);
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log(
                "Email automation failed for segment {$segment->getId()}: " . $e->getMessage(),
                Mage::LOG_ERROR,
                'customer_segmentation.log',
            );
        }
    }

    /**
     * Process segment membership changes and trigger appropriate sequences
     */
    protected function processSegmentChanges(
        Maho_CustomerSegmentation_Model_Segment $segment,
        array $currentMatchedCustomers,
    ): void {
        $segmentId = $segment->getId();
        $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');

        // Get previously active customers for this segment
        $previousCustomers = $resource->getActiveSequenceCustomers((int) $segmentId, 'enter');

        // Determine who entered and who exited
        $enteredCustomers = array_diff($currentMatchedCustomers, $previousCustomers);
        $exitedCustomers = array_diff($previousCustomers, $currentMatchedCustomers);

        // Handle customers entering the segment
        if (!empty($enteredCustomers) && $segment->getAutoEmailTrigger() === Maho_CustomerSegmentation_Model_Segment::EMAIL_TRIGGER_ENTER) {
            $this->handleCustomersEntering($segment, $enteredCustomers);
        }

        // Handle customers exiting the segment
        if (!empty($exitedCustomers)) {
            $this->handleCustomersExiting($segment, $exitedCustomers);
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
            $segment->startEmailSequence($customerId, Maho_CustomerSegmentation_Model_Segment::EMAIL_TRIGGER_ENTER);
        }

        Mage::log(
            'Started email sequences for ' . count($customerIds) . " customers entering segment {$segment->getId()}",
            Mage::LOG_INFO,
            'customer_segmentation.log',
        );
    }

    /**
     * Handle customers exiting a segment
     */
    protected function handleCustomersExiting(
        Maho_CustomerSegmentation_Model_Segment $segment,
        array $customerIds,
    ): void {
        $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');

        // Stop any scheduled enter sequences for exited customers
        $stoppedEnter = $resource->stopSequencesForCustomers(
            (int) $segment->getId(),
            $customerIds,
            Maho_CustomerSegmentation_Model_Segment::EMAIL_TRIGGER_ENTER,
        );

        // Start exit sequences if configured
        if ($segment->getAutoEmailTrigger() === Maho_CustomerSegmentation_Model_Segment::EMAIL_TRIGGER_EXIT) {
            $exitStarted = 0;
            foreach ($customerIds as $customerId) {
                $customerId = (int) $customerId;

                // Verify customer is subscribed to newsletter
                if (!$this->isCustomerSubscribed($customerId)) {
                    continue;
                }

                $segment->startEmailSequence($customerId, Maho_CustomerSegmentation_Model_Segment::EMAIL_TRIGGER_EXIT);
                $exitStarted++;
            }

            Mage::log(
                "Started {$exitStarted} exit email sequences for segment {$segment->getId()}",
                Mage::LOG_INFO,
                'customer_segmentation.log',
            );
        }

        if ($stoppedEnter > 0) {
            Mage::log(
                "Stopped {$stoppedEnter} enter email sequences for customers exiting segment {$segment->getId()}",
                Mage::LOG_INFO,
                'customer_segmentation.log',
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
    public function onNewsletterSubscriberSaveAfter(Varien_Event_Observer $observer): void
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
            ->addStatusFilter('scheduled');

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
                'customer_segmentation.log',
            );
        }
    }

    /**
     * Handle customer deletion
     * Clean up sequence progress when customer is deleted
     */
    public function onCustomerDeleteAfter(Varien_Event_Observer $observer): void
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
                'customer_segmentation.log',
            );
        }
    }

    /**
     * Process ready email sequences (called by cron)
     * This processes scheduled emails that are ready to send
     */
    public function processScheduledEmails(Varien_Event_Observer $observer): void
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
                    'customer_segmentation.log',
                );
            }

        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log(
                'Email automation cron failed: ' . $e->getMessage(),
                Mage::LOG_ERROR,
                'customer_segmentation.log',
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
        if (!$progress->getId() || $progress->getStatus() !== 'scheduled') {
            return; // Already processed or invalid
        }

        // Load customer
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            $progress->markAsSkipped();
            return;
        }

        // Verify customer is still subscribed
        if (!$this->isCustomerSubscribed($customerId)) {
            $progress->markAsSkipped();
            return;
        }

        // Load template
        $template = Mage::getModel('newsletter/template')->load($templateId);
        if (!$template->getId()) {
            $progress->markAsFailed();
            throw new Exception("Newsletter template {$templateId} not found");
        }

        // Generate variables for template
        $variables = $this->getTemplateVariables($customer, $sequenceData);

        // Generate coupon if needed
        if ($generateCoupon && !empty($sequenceData['coupon_sales_rule_id'])) {
            $couponCode = $this->generateSequenceCoupon($customerId, $sequenceData);
            if ($couponCode) {
                // Add coupon variables to template
                $couponHelper = Mage::helper('customersegmentation/coupon');
                $rule = Mage::getModel('salesrule/rule')->load($sequenceData['coupon_sales_rule_id']);
                $couponVars = $couponHelper->getCouponTemplateVariables($couponCode, null, $rule);
                $variables = array_merge($variables, $couponVars);
            }
        }

        // Create newsletter queue
        $queue = Mage::getModel('newsletter/queue');
        $queue->setTemplateId($templateId)
              ->setNewsletterType(Mage_Newsletter_Model_Template::TYPE_HTML)
              ->setNewsletterSubject($template->getTemplateSubject())
              ->setNewsletterSenderName($template->getTemplateSenderName())
              ->setNewsletterSenderEmail($template->getTemplateSenderEmail())
              ->setQueueStatus(Mage_Newsletter_Model_Queue::STATUS_SENDING)
              ->setQueueStartAt(Mage::getSingleton('core/date')->gmtDate())
              ->setAutomationSource('customer_segmentation')
              ->setAutomationSourceId($sequenceData['segment_id'])
              ->save();

        // Add subscriber to queue
        $subscriberCollection = Mage::getResourceModel('newsletter/subscriber_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

        foreach ($subscriberCollection as $subscriber) {
            $queue->addSubscribersToQueue([$subscriber->getId()]);
            break; // Should only be one
        }

        // Process template with variables
        $template->setTemplateFilter(Mage::getModel('core/email_template_filter'))
                 ->getTemplateFilter()
                 ->setVariables($variables);

        // Send the email
        $queue->sendPerSubscriber();

        // Mark as sent
        $progress->markAsSent($queue->getId());

        Mage::log(
            "Sent automation email to customer {$customerId}, template {$templateId}, queue {$queue->getId()}",
            Mage::LOG_INFO,
            'customer_segmentation.log',
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
