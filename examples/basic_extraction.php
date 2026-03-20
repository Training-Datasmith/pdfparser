<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Config;

// --- Example 1: Extract text from a PDF file ---
$parser = new Parser();

// Replace with a real PDF path to test
// $pdf = $parser->parseFile('/path/to/document.pdf');

// For demonstration, parse from a string (a valid minimal PDF):
// $pdf = $parser->parseContent(file_get_contents('/path/to/document.pdf'));

echo "// To extract text from a PDF file:\n";
echo <<<'EXAMPLE'
$parser = new \Smalot\PdfParser\Parser();
$pdf    = $parser->parseFile('/path/to/document.pdf');

// Extract all text from the document
$text = $pdf->getText();
echo $text;

// Extract text page by page
$pages = $pdf->getPages();
foreach ($pages as $i => $page) {
    echo "=== Page " . ($i + 1) . " ===\n";
    echo $page->getText();
    echo "\n";
}
EXAMPLE;
echo "\n\n";

// --- Example 2: Extract metadata ---
echo "// To extract document metadata:\n";
echo <<<'EXAMPLE'
$pdf    = $parser->parseFile('/path/to/document.pdf');
$details = $pdf->getDetails();

echo "Title:    " . ($details['Title'] ?? 'n/a') . "\n";
echo "Author:   " . ($details['Author'] ?? 'n/a') . "\n";
echo "Pages:    " . ($details['Pages'] ?? 'n/a') . "\n";
echo "Producer: " . ($details['Producer'] ?? 'n/a') . "\n";
EXAMPLE;
echo "\n\n";

// --- Example 3: Custom config (adjust horizontal whitespace threshold) ---
echo "// Custom config to tune text extraction:\n";
echo <<<'EXAMPLE'
$config = new \Smalot\PdfParser\Config();
$config->setHorizontalOffset(0);  // merge characters with no horizontal gap

$parser = new \Smalot\PdfParser\Parser([], $config);
$pdf    = $parser->parseFile('/path/to/document.pdf');
echo $pdf->getText();
EXAMPLE;
echo "\n";
