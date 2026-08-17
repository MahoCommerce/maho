<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Feed Generation Log model
 *
 * Error Handling Pattern:
 * - Getter methods (getFeed, getErrorsArray): Return null/empty array if not found, never throw
 * - Status methods (isRunning, isCompleted, isFailed): Return boolean, never throw
 * - Recording methods (addError, recordUploadSuccess): Append to internal arrays, save on demand
 * - Duration methods (getDuration, getDurationFormatted): Return 0 or formatted string on failure
 *
 * @method Maho_FeedManager_Model_Resource_Log getResource()
 * @method Maho_FeedManager_Model_Resource_Log _getResource()
 */
class Maho_FeedManager_Model_Log extends Mage_Core_Model_Abstract
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const UPLOAD_STATUS_PENDING = 'pending';
    public const UPLOAD_STATUS_SUCCESS = 'success';
    public const UPLOAD_STATUS_FAILED = 'failed';
    public const UPLOAD_STATUS_SKIPPED = 'skipped';

    protected $_eventPrefix = 'feedmanager_log';
    protected $_eventObject = 'log';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/log');
    }

    /**
     * Get errors as array
     */
    public function getErrorsArray(): array
    {
        $errors = $this->getErrors();
        if (empty($errors)) {
            return [];
        }
        return Mage::helper('core')->jsonDecode($errors) ?: [];
    }

    /**
     * Set errors from array
     */
    public function setErrorsArray(array $errors): self
    {
        $this->setErrors(Mage::helper('core')->jsonEncode($errors));
        return $this;
    }

    /**
     * Add error to log
     *
     * @param string $message Error message (or SKU if second param provided)
     * @param string|null $detail Additional detail (message if first param is SKU)
     */
    public function addError(string $message, ?string $detail = null): self
    {
        $errors = $this->getErrorsArray();
        if ($detail !== null) {
            // Called with SKU and message
            $errors[] = [
                'sku' => $message,
                'message' => $detail,
                'time' => Mage::app()->getLocale()->nowUtc(),
            ];
        } else {
            // Called with just message
            $errors[] = [
                'message' => $message,
                'time' => Mage::app()->getLocale()->nowUtc(),
            ];
        }
        $this->setErrorsArray($errors);
        $this->setErrorCount(count($errors));
        return $this;
    }

    /**
     * Get error messages as simple array of strings
     */
    public function getErrorMessagesArray(): array
    {
        $errors = $this->getErrorsArray();
        $messages = [];
        foreach ($errors as $error) {
            if (isset($error['sku'])) {
                $messages[] = "{$error['sku']}: {$error['message']}";
            } else {
                $messages[] = $error['message'] ?? '';
            }
        }
        return $messages;
    }

    /**
     * Get execution time in seconds
     */
    public function getExecutionTime(): ?float
    {
        if (!$this->getStartedAt() || !$this->getCompletedAt()) {
            return null;
        }

        $start = strtotime($this->getStartedAt());
        $end = strtotime($this->getCompletedAt());

        return $end - $start;
    }

    /**
     * Get formatted execution time
     */
    public function getFormattedExecutionTime(): string
    {
        $seconds = $this->getExecutionTime();
        if ($seconds === null) {
            return 'N/A';
        }

        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $seconds %= 60;
        return $minutes . 'm ' . round($seconds, 0) . 's';
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSize(): string
    {
        $bytes = $this->getFileSize();
        if (!$bytes) {
            return 'N/A';
        }

        return Mage::helper('core')->formatFileSize((int) $bytes);
    }

    /**
     * Check if log is running
     */
    public function isRunning(): bool
    {
        return $this->getStatus() === self::STATUS_RUNNING;
    }

    /**
     * Check if log completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->getStatus() === self::STATUS_COMPLETED;
    }

    /**
     * Check if log failed
     */
    public function isFailed(): bool
    {
        return $this->getStatus() === self::STATUS_FAILED;
    }

    /**
     * Get associated feed
     */
    public function getFeed(): ?Maho_FeedManager_Model_Feed
    {
        if (!$this->getFeedId()) {
            return null;
        }
        return Mage::getModel('feedmanager/feed')->load($this->getFeedId());
    }

    /**
     * Get status options
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_RUNNING => 'Running',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
        ];
    }

    /**
     * Get upload status options
     */
    public static function getUploadStatusOptions(): array
    {
        return [
            self::UPLOAD_STATUS_PENDING => 'Pending',
            self::UPLOAD_STATUS_SUCCESS => 'Success',
            self::UPLOAD_STATUS_FAILED => 'Failed',
            self::UPLOAD_STATUS_SKIPPED => 'Skipped',
        ];
    }

    /**
     * Record upload success
     */
    public function recordUploadSuccess(int $destinationId, string $message = 'Upload successful'): self
    {
        $this->setUploadStatus(self::UPLOAD_STATUS_SUCCESS)
            ->setUploadedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setUploadMessage($message)
            ->setDestinationId($destinationId)
            ->save();
        return $this;
    }

    /**
     * Record upload failure
     */
    public function recordUploadFailure(int $destinationId, string $message): self
    {
        $this->setUploadStatus(self::UPLOAD_STATUS_FAILED)
            ->setUploadedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setUploadMessage($message)
            ->setDestinationId($destinationId)
            ->save();
        return $this;
    }

    /**
     * Record upload skipped (no destination configured)
     */
    public function recordUploadSkipped(string $reason = 'No destination configured'): self
    {
        $this->setUploadStatus(self::UPLOAD_STATUS_SKIPPED)
            ->setUploadMessage($reason)
            ->save();
        return $this;
    }

    /**
     * Check if upload was successful
     */
    public function isUploadSuccessful(): bool
    {
        return $this->getUploadStatus() === self::UPLOAD_STATUS_SUCCESS;
    }

    /**
     * Get formatted upload status for display
     */
    public function getFormattedUploadStatus(): string
    {
        $status = $this->getUploadStatus();
        if (!$status) {
            return '-';
        }

        $options = self::getUploadStatusOptions();
        $label = $options[$status] ?? ucfirst($status);
        $message = $this->getUploadMessage();

        if ($message && $status !== self::UPLOAD_STATUS_SUCCESS) {
            return "{$label}: {$message}";
        }

        return $label;
    }

    /**
     * Get destination name (if uploaded)
     */
    public function getDestinationName(): ?string
    {
        if (!$this->getDestinationId()) {
            return null;
        }
        $destination = Mage::getModel('feedmanager/destination')->load($this->getDestinationId());
        return $destination->getId() ? $destination->getName() : null;
    }

    public function getLogId(): ?int
    {
        $value = $this->getData('log_id');
        return $value === null ? null : (int) $value;
    }

    public function getFeedId(): ?int
    {
        $value = $this->getData('feed_id');
        return $value === null ? null : (int) $value;
    }

    public function setFeedId(?int $value): static
    {
        return $this->setData('feed_id', $value);
    }

    public function getStartedAt(): ?string
    {
        $value = $this->getData('started_at');
        return $value === null ? null : (string) $value;
    }

    public function setStartedAt(?string $value): static
    {
        return $this->setData('started_at', $value);
    }

    public function getCompletedAt(): ?string
    {
        $value = $this->getData('completed_at');
        return $value === null ? null : (string) $value;
    }

    public function setCompletedAt(?string $value): static
    {
        return $this->setData('completed_at', $value);
    }

    public function getStatus(): ?string
    {
        $value = $this->getData('status');
        return $value === null ? null : (string) $value;
    }

    public function setStatus(?string $value): static
    {
        return $this->setData('status', $value);
    }

    public function getProductCount(): ?int
    {
        $value = $this->getData('product_count');
        return $value === null ? null : (int) $value;
    }

    public function setProductCount(?int $value): static
    {
        return $this->setData('product_count', $value);
    }

    public function getErrorCount(): ?int
    {
        $value = $this->getData('error_count');
        return $value === null ? null : (int) $value;
    }

    public function setErrorCount(?int $value): static
    {
        return $this->setData('error_count', $value);
    }

    public function getErrors(): ?string
    {
        $value = $this->getData('errors');
        return $value === null ? null : (string) $value;
    }

    public function setErrors(?string $value): static
    {
        return $this->setData('errors', $value);
    }

    public function getFilePath(): ?string
    {
        $value = $this->getData('file_path');
        return $value === null ? null : (string) $value;
    }

    public function setFilePath(?string $value): static
    {
        return $this->setData('file_path', $value);
    }

    public function getFileSize(): ?int
    {
        $value = $this->getData('file_size');
        return $value === null ? null : (int) $value;
    }

    public function setFileSize(?int $value): static
    {
        return $this->setData('file_size', $value);
    }

    public function getUploadStatus(): ?string
    {
        $value = $this->getData('upload_status');
        return $value === null ? null : (string) $value;
    }

    public function setUploadStatus(?string $value): static
    {
        return $this->setData('upload_status', $value);
    }

    public function getUploadedAt(): ?string
    {
        $value = $this->getData('uploaded_at');
        return $value === null ? null : (string) $value;
    }

    public function setUploadedAt(?string $value): static
    {
        return $this->setData('uploaded_at', $value);
    }

    public function getUploadMessage(): ?string
    {
        $value = $this->getData('upload_message');
        return $value === null ? null : (string) $value;
    }

    public function setUploadMessage(?string $value): static
    {
        return $this->setData('upload_message', $value);
    }

    public function getDestinationId(): ?int
    {
        $value = $this->getData('destination_id');
        return $value === null ? null : (int) $value;
    }

    public function setDestinationId(?int $value): static
    {
        return $this->setData('destination_id', $value);
    }
}
