<?php
/**
 * Extract readable content from KIU_Programmes PDF
 */

$pdfFile = __DIR__ . '/KIU_Programmes in usd.pdf';

// Read the PDF file
$pdfContent = file_get_contents($pdfFile);

// Method: Extract text between stream delimiters, decompress if needed
preg_match_all('/stream\s*\n(.*?)\n*endstream/s', $pdfContent, $streams, PREG_PATTERN_ORDER);

$output = '';
foreach ($streams[1] as $index => $stream) {
    // Check if stream is compressed
    if (strpos($pdfContent, 'FlateDecode') !== false) {
        // Try to decompress gzip compressed data
        $decompressed = @gzuncompress($stream);
        if ($decompressed === false) {
            // Try without header
            $decompressed = @gzinflate($stream);
        }
        if ($decompressed) {
            $stream = $decompressed;
        }
    }
    
    // Extract printable characters and newlines
    $cleaned = preg_replace('/[^\x20-\x7E\n\r\t]/s', '', $stream);
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    
    if (strlen(trim($cleaned)) > 10) {
        $output .= $cleaned . "\n";
    }
}

// Look for common patterns like program names and fees
$lines = explode("\n", $output);
$programData = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (strlen($line) > 5) {
        echo $line . "\n";
    }
}
