<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Feed Generation Log model
 *
 * Error Handling Pattern:
 * - Getter methods (getFeed, getErrorsArray): Return null/empty array if not found, never throw
 * - Status methods (isRunning, isCompleted, isFailed): Return boolean, never throw
 * - Recording methods (addError, recordUploadSuccess): Append to internal arrays, save on demand
 * - Duration methods (getDuration, getDurationFormatted): Return 0 or formatted string on failure
 *
 * @method int getLogId()
 * @method int getFeedId()
 * @method $this setFeedId(int $feedId)
 * @method string getStartedAt()
 * @method $this setStartedAt(string $datetime)
 * @method string|null getCompletedAt()
 * @method $this setCompletedAt(string|null $datetime)
 * @method string getStatus()
 * @method $this setStatus(string $status)
 * @method int|null getProductCount()
 * @method $this setProductCount(int|null $count)
 * @method int getErrorCount()
 * @method $this setErrorCount(int $count)
 * @method string|null getErrors()
 * @method $this setErrors(string|null $errors)
 * @method string|null getFilePath()
 * @method $this setFilePath(string|null $path)
 * @method int|null getFileSize()
 * @method $this setFileSize(int|null $size)
 * @method string|null getUploadStatus()
 * @method $this setUploadStatus(string|null $status)
 * @method string|null getUploadedAt()
 * @method $this setUploadedAt(string|null $datetime)
 * @method string|null getUploadMessage()
 * @method $this setUploadMessage(string|null $message)
 * @method int|null getDestinationId()
 * @method $this setDestinationId(int|null $id)
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
                'time' => Mage_Core_Model_Locale::now(),
            ];
        } else {
            // Called with just message
            $errors[] = [
                'message' => $message,
                'time' => Mage_Core_Model_Locale::now(),
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

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
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
            ->setUploadedAt(Mage_Core_Model_Locale::now())
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
            ->setUploadedAt(Mage_Core_Model_Locale::now())
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
}
