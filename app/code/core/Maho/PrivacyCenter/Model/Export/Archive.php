<?php

/**
 * Writes the export of a privacy request as a ZIP file with data.json, for a program, and data.html, for a person.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Model_Export_Archive
{
    /**
     * Write $sections to a new file in the privacy_exports storage mount, and return the path of the file.
     *
     * @param array<string, array{label: string, data: array<array-key, mixed>}> $sections
     */
    public function write(Maho_PrivacyCenter_Model_Request $request, array $sections): string
    {
        $helper = Mage::helper('privacycenter');
        $generatedAt = Mage_Core_Model_Locale::nowUtc();
        $json = json_encode([
            'email' => $request->getEmail(),
            'generated_at' => $generatedAt,
            'sections' => array_map(fn(array $section): array => $section['data'], $sections),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $localFile = (string) tempnam(sys_get_temp_dir(), 'privacy');
        try {
            $zip = new ZipArchive();
            if ($zip->open($localFile, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot create the ZIP file of the export');
            }
            $zip->addFromString('data.json', $json);
            $zip->addFromString('data.html', $this->renderHtml($request, $sections, $generatedAt));
            $zip->close();

            $path = sprintf('%d/%s.zip', (int) $request->getId(), bin2hex(random_bytes(16)));
            $helper->getStorage()->copyFromLocalFile($localFile, $path);
            return $path;
        } finally {
            if (is_file($localFile)) {
                unlink($localFile);
            }
        }
    }

    /**
     * @param array<string, array{label: string, data: array<array-key, mixed>}> $sections
     */
    protected function renderHtml(Maho_PrivacyCenter_Model_Request $request, array $sections, string $generatedAt): string
    {
        $helper = Mage::helper('privacycenter');
        $title = $helper->__('Your Personal Data');
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . $this->escape($title) . '</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:2rem;color:#111}table{border-collapse:collapse;margin:.5rem 0 1.5rem}'
            . 'th,td{border:1px solid #ccc;padding:.25rem .5rem;text-align:left;vertical-align:top}th{background:#f4f4f4}</style>'
            . '</head><body><h1>' . $this->escape($title) . '</h1><p>'
            . $this->escape($helper->__('Email: %s', (string) $request->getEmail())) . '<br>'
            . $this->escape($helper->__('Generated at: %s UTC', $generatedAt)) . '</p>';
        foreach ($sections as $section) {
            $html .= '<h2>' . $this->escape($section['label']) . '</h2>';
            $html .= $section['data'] === []
                ? '<p>' . $this->escape($helper->__('No data.')) . '</p>'
                : $this->renderValue($section['data']);
        }
        return $html . '</body></html>';
    }

    protected function renderValue(mixed $value): string
    {
        if (!is_array($value)) {
            return $this->escape(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }
        if ($value === []) {
            return '';
        }
        if (array_is_list($value) && is_array($value[0])) {
            $html = '';
            foreach ($value as $item) {
                $html .= $this->renderValue($item);
            }
            return $html;
        }
        $html = '<table>';
        foreach ($value as $key => $item) {
            $html .= '<tr><th>' . $this->escape((string) $key) . '</th><td>' . $this->renderValue($item) . '</td></tr>';
        }
        return $html . '</table>';
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
