<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Safety_OutputSanitizer
{
    /**
     * PII patterns — detects but doesn't block.
     *
     * Phone is the trickiest: a permissive `[\d\s\-\(\)]{10,}` matches order
     * IDs, invoice numbers and SKUs and floods the log. The patterns below
     * require recognisable phone shapes (E.164, or grouped digits with at
     * least one delimiter) so prose with stray long digit strings doesn't trip.
     */
    private const PII_PATTERNS = [
        'email' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
        'phone' => '/(?:\+\d{1,3}[\s\-]?)?\(?\d{2,4}\)?[\s\-]\d{2,4}[\s\-]\d{2,4}(?:[\s\-]\d{2,4})?/',
        'ssn'   => '/\b\d{3}[-\s]?\d{2}[-\s]?\d{4}\b/',
        'cc'    => '/\b(?:\d{4}[-\s]?){3}\d{4}\b/',
    ];

    /**
     * Sanitize AI output for safe use
     */
    public function sanitize(string $output, bool $isHtml = false, array &$metadata = []): string
    {
        if ($isHtml && Mage::getStoreConfigFlag('maho_ai/safety/output_sanitize_html')) {
            $output = (string) Mage::helper('core/purifier')->purify($output);
        }

        if (Mage::getStoreConfigFlag('maho_ai/safety/pii_detection')) {
            $this->detectPii($output, $metadata);
        }

        return $output;
    }

    private function detectPii(string $text, array &$metadata): void
    {
        $foundTypes = [];
        foreach (self::PII_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $text)) {
                $foundTypes[] = $type;
            }
        }

        if ($foundTypes) {
            $metadata['pii_flagged'] = true;
            $metadata['pii_types'] = $foundTypes;
            Mage::log(
                sprintf('Maho AI: PII detected in output (%s)', implode(', ', $foundTypes)),
                Mage::LOG_WARNING,
                'maho_ai.log',
            );
        }
    }
}
