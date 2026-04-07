<?php
/**
 * Extract fee information from KIU_Programmes in usd.pdf
 */

$pdfFile = __DIR__ . '/KIU_Programmes in usd.pdf';

if (!file_exists($pdfFile)) {
    die("PDF file not found: $pdfFile");
}

// Read PDF file as binary
$pdfContent = file_get_contents($pdfFile);

// Simple extraction: Try to extract text between common delimiters
// PDFs store text in streams, often between "BT" and "ET" markers or in content streams

// Method 1: Try to extract readable strings from PDF
$extractedText = '';

// Look for text in streams (basic approach)
if (preg_match_all('/\(([^)]+)\)/s', $pdfContent, $matches)) {
    foreach ($matches[1] as $match) {
        $decoded = preg_replace('/[^\x20-\x7E\n]/', ' ', $match);
        if (strlen(trim($decoded)) > 2) {
            $extractedText .= $decoded . "\n";
        }
    }
}

// Clean up and output
$extractedText = preg_replace('/\s+/', ' ', $extractedText);
$lines = array_filter(array_map('trim', explode("\n", $extractedText)));

// Display extracted content
echo "=== EXTRACTED PDF CONTENT ===\n";
echo implode("\n", array_slice($lines, 0, 200));
echo "\n\n=== FULL TEXT (for analysis) ===\n";
echo implode("\n", $lines);
