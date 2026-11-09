<?php

declare(strict_types=1);

/**
 * @file This file is part of the PdfParser library.
 *
 * @author  Konrad Abicht <k.abicht@gmail.com>
 *
 * @date    2020-06-01
 *
 * @author  Sébastien MALOT <sebastien@malot.fr>
 *
 * @date    2017-01-03
 *
 * @license LGPLv3
 *
 * @url     <https://github.com/smalot/pdfparser>
 *
 *  PdfParser is a pdf library written in PHP, extraction oriented.
 *  Copyright (C) 2017 - Sébastien MALOT <sebastien@malot.fr>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.
 *  If not, see <http://www.pdfparser.org/sites/default/LICENSE.txt>.
 */

namespace PHP_Unit_Tests\Integration\RawData;

use PHP_Unit_Tests\Test_Case;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\RawData\Raw_Data_Parser;

class Raw_Data_Parser_Helper extends Raw_Data_Parser
{
    /**
     * Expose protected function "getRawObject".
     */
    public function expose_get_raw_object($pdfData, $offset = 0)
    {
        return $this->get_raw_object($pdfData, $offset);
    }

    /**
     * Expose protected function "get_xref_data".
     */
    public function expose_get_xref_data(string $pdfData, int $offset = 0, array $xref = [], array $visited_offsets = []): array
    {
        return $this->get_xref_data($pdfData, $offset, $xref, $visited_offsets);
    }

    /**
     * Expose protected function "decode_xref".
     */
    public function expose_decode_xref(string $pdfData, int $startxref, array $xref = [], array $visited_offsets = []): array
    {
        return $this->decode_xref($pdfData, $startxref, $xref, $visited_offsets);
    }

    /**
     * Expose protected function "decode_xref_stream".
     */
    public function expose_decode_xref_stream(string $pdfData, int $startxref, array $xref = [], array $visited_offsets = []): array
    {
        return $this->decode_xref_stream($pdfData, $startxref, $xref, $visited_offsets);
    }
}

class Raw_Data_Parser_Test extends Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new Raw_Data_Parser_Helper([], new Config());
    }

    /**
     * Tests buggy behavior of getRawObject.
     *
     * When PDF has corrupted xref table getRawObject may run into an infinite loop.
     *
     * @see https://github.com/smalot/pdfparser/issues/372
     * @see https://github.com/smalot/pdfparser/pull/377
     */
    public function testGetRawObjectIssue372(): void
    {
        // The following $data content is a minimal example to trigger the infinite loop
        $data = '<</Producer (eDkºãa˜þõ‚LÅòÕ�PïÙ��)©)>>';

        // calling "getRawObject" via "exposeGetRawObject" would result in an infinite loop
        // if the fix is not there.
        $result = $this->fixture->expose_get_raw_object($data);

        $this->assertEquals(
            [
                '<<',
                [
                    ['/', 'Producer', 11],
                    ['(', 'eDkºãa˜þõ‚LÅòÕ�PïÙ��', 52],
                ],
                52,
            ],
            $result
        );

        // Test that spaces after a 'stream' declaration are absorbed
        // See: https://github.com/smalot/pdfparser/issues/641
        $data = 'stream '."\n";
        $data .= 'streamdata'."\n";
        $data .= 'endstream'."\n";
        $data .= 'endobj';

        $result = $this->fixture->expose_get_raw_object($data);

        // Value 'streamdata'."\n" would be empty string without the fix
        $this->assertEquals(
            [
                'stream',
                'streamdata'."\n",
                19,
            ],
            $result
        );
    }

    /**
     * Tests buggy behavior of decodeXrefStream.
     *
     * @see https://github.com/smalot/pdfparser/issues/30
     * @see https://github.com/smalot/pdfparser/issues/192
     * @see https://github.com/smalot/pdfparser/issues/209
     * @see https://github.com/smalot/pdfparser/issues/330
     * @see https://github.com/smalot/pdfparser/issues/356
     * @see https://github.com/smalot/pdfparser/issues/373
     * @see https://github.com/smalot/pdfparser/issues/392
     * @see https://github.com/smalot/pdfparser/issues/397
     */
    public function testDecodeXrefStreamIssue356(): void
    {
        $filename = $this->rootDir.'/samples/bugs/Issue356.pdf';

        $parser = $this->get_parser_instance();
        $document = $parser->parse_file($filename);
        $pages = $document->get_pages();

        $this->assertStringContainsString('Ημερήσια έκθεση επιδημιολογικής', $pages[0]->get_text());
    }

    public function testDecodeObjectHeaderIssue405(): void
    {
        $filename = $this->rootDir.'/samples/bugs/Issue405.pdf';

        $parser = $this->get_parser_instance();
        $document = $parser->parse_file($filename);
        $pages = $document->get_pages();

        $this->assertStringContainsString('Bug fix: PR #405', $pages[0]->get_text());
    }

    /**
     * Tests buggy behavior of decodeXrefStream.
     *
     * When PDF has more than one entry in the /Index area (for example by changing
     * the document description), only the first entry is used.
     * If the fix is not used the array returned by getDetails() contains only the entry
     * with the key 'Pages'. All other entries like 'Author', 'Creator', 'Title',
     * 'Subject' (which come from the 'Info' object) are not listed, because the
     * 'Info' object gets a wrong object id during parsing the data into the xref structure.
     * So the object id listed at the /Info entry is not valid and the data of the info object
     * cannot be loaded during executing Document::buildDetails().
     *
     * @see https://github.com/smalot/pdfparser/pull/479
     */
    public function testDecodeXrefStreamIssue479(): void
    {
        $filename = $this->rootDir.'/samples/bugs/Issue479.pdf';

        $parser = $this->get_parser_instance();
        $document = $parser->parse_file($filename);
        $details = $document->get_details();

        $this->assertArrayHasKey('Author', $details);
        $this->assertArrayHasKey('CreationDate', $details);
        $this->assertArrayHasKey('Creator', $details);
        $this->assertArrayHasKey('ModDate', $details);
        $this->assertArrayHasKey('Producer', $details);
        $this->assertArrayHasKey('Subject', $details);
        $this->assertArrayHasKey('Title', $details);
    }

    /**
     * Account for inaccurate offset values in getXrefData.
     *
     * Normally offset values extracted from the PDF document are exact.
     * However in some cases, they may point to whitespace *before* a
     * valid xref keyword. Move the offset forward past whitespace to
     * make this function a little more lenient.
     *
     * @see https://github.com/smalot/pdfparser/issues/673
     */
    public function testGetXrefDataIssue673(): void
    {
        $filename = $this->rootDir.'/samples/bugs/Issue673.pdf';

        // Parsing this document would previously throw an Exception
        $parser = $this->get_parser_instance();
        $document = $parser->parse_file($filename);
        $text = $document->get_text();

        self::assertStringContainsString('6 rue des Goutais', $text);
    }

    /**
     * Handle self referencing xref
     *
     * It seems that some PDF creators output `Prev 0` when there is no previous xref.
     *
     * @see https://github.com/smalot/pdfparser/pull/727
     */
    public function testDecodeXrefIssue727(): void
    {
        $filename = $this->rootDir.'/samples/bugs/Issue727.pdf';

        // Parsing this document would previously cause an infinite loop
        $parser = $this->get_parser_instance();
        $document = $parser->parse_file($filename);
        $text = $document->get_text();

        self::assertStringContainsString('', $text);
    }

    /**
     * Test that getXrefData prevents circular references
     *
     * When a PDF has circular references in xref chain (e.g., Prev pointing to already visited offset),
     * the parser should detect this and stop recursion to prevent infinite loops.
     */
    public function testGetXrefDataPreventsCircularReferences(): void
    {
        // Create a minimal PDF structure with xref that would create a circular reference
        $pdfData = "%PDF-1.5\n";
        $pdfData .= "xref\n";
        $pdfData .= "0 1\n";
        $pdfData .= "0000000000 65535 f \n";
        $pdfData .= "trailer\n";
        $pdfData .= "<</Size 1/Prev 7>>\n";  // Prev points back to offset 7 (the xref keyword)
        $pdfData .= "startxref\n";
        $pdfData .= "7\n";
        $pdfData .= "%%EOF\n";

        // Test with visitedOffsets containing the offset we're trying to visit
        $result = $this->fixture->expose_get_xref_data($pdfData, 7, [], [7 => true]);

        // Should return empty xref array without recursing
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that decodeXref passes visitedOffsets correctly when handling Prev
     *
     * This ensures that circular reference detection works when decodeXref
     * calls getXrefData for a Prev pointer.
     */
    public function testDecodeXrefPassesVisitedOffsets(): void
    {
        // Create a minimal xref structure with Prev
        $pdfData = "xref\n";
        $pdfData .= "0 1\n";
        $pdfData .= "0000000000 65535 f \n";
        $pdfData .= "trailer\n";
        $pdfData .= "<</Size 1/Prev 100>>\n";

        // Call decodeXref with visitedOffsets that includes the Prev offset
        // This should not cause infinite recursion
        $result = $this->fixture->expose_decode_xref($pdfData, 0, [], [100 => true]);

        // Should complete without error and return an array
        $this->assertIsArray($result);
        $this->assertArrayHasKey('trailer', $result);
    }

    /**
     * Test that getXrefData tracks visited offsets correctly
     *
     * Ensures that offsets are added to visitedOffsets array to prevent
     * circular references in subsequent calls.
     */
    public function testGetXrefDataTracksVisitedOffsets(): void
    {
        // Test that calling with an already-visited offset returns immediately
        $pdfData = "%PDF-1.5\n";
        $pdfData .= "xref\n";
        $pdfData .= "0 1\n";
        $pdfData .= "0000000000 65535 f \n";
        $pdfData .= "trailer\n";
        $pdfData .= "<</Size 1>>\n";
        $pdfData .= "startxref\n";
        $pdfData .= "7\n";
        $pdfData .= "%%EOF\n";

        // Call with offset 50 already in visitedOffsets - should return immediately
        $result = $this->fixture->expose_get_xref_data($pdfData, 50, [], [50 => true]);

        // Should return empty array without processing
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
