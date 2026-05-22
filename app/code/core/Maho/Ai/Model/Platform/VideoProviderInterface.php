<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface Maho_Ai_Model_Platform_VideoProviderInterface
{
    /**
     * Start an async video generation job.
     *
     * @param array<string, mixed> $options  Supports: model, duration, aspect_ratio,
     *                                        resolution, imageUrl (for img2video),
     *                                        negative_prompt, seed
     * @return array{runId: string, status: string, cost: float}
     */
    public function generateVideo(string $prompt, array $options = []): array;

    /**
     * Poll for video generation status.
     *
     * @return array{status: string, videoUrl?: string, error?: string}
     *   status is one of: pending, in_progress, completed, failed
     */
    public function getVideoStatus(string $runId, string $model): array;

    /**
     * Get the platform code (e.g. 'nanogpt')
     */
    public function getVideoPlatformCode(): string;

    /**
     * Get the model used in the last generateVideo() call
     */
    public function getLastVideoModel(): string;
}
