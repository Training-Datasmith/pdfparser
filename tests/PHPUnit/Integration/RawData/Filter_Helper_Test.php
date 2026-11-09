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
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\RawData\Filter_Helper;

class Filter_Helper_Test extends Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new Filter_Helper();
    }

    /*
     * Tests for filter ASCII85Decode
     */

    public function testDecodeFilterASCII85Decode(): void
    {
        $compressed = '6Z6g\Eb0<5ARlp)FE2)5B)'; // = Compressed string
        $result = $this->fixture->decode_filter('ASCII85Decode', $compressed);

        $this->assertEquals('Compressed string', $result);
    }

    public function testDecodeFilterASCII85DecodeInitSequence(): void
    {
        $compressed = '<~6Z6g\Eb0<5ARlp)FE2)5B)'; // = Compressed string
        $result = $this->fixture->decode_filter('ASCII85Decode', $compressed);

        $this->assertEquals('Compressed string', $result);
    }

    public function testDecodeFilterASCII85DecodeEndSequence(): void
    {
        $compressed = '6Z6g\Eb0<5ARlp)FE2)5B)~>'; // = Compressed string
        $result = $this->fixture->decode_filter('ASCII85Decode', $compressed);

        $this->assertEquals('Compressed string', $result);
    }

    public function testDecodeFilterASCII85DecodeSpecificEndSequence(): void
    {
        $compressed = '+^6b<~>'; // = 0x215B33C0 = "![3\xC0"
        $result = $this->fixture->decode_filter('ASCII85Decode', $compressed);

        $this->assertEquals("\x21\x5B\x33\xC0", $result);
    }

    /*
     * Tests for filter ASCIIHexDecode
     */

    public function testDecodeFilterASCIIHexDecode(): void
    {
        $compressed = '43 6f 6d 70 72 65 73 73 65 64 20 73 74 72 69 6e 67'; // = Compressed string
        $result = $this->fixture->decode_filter('ASCIIHexDecode', $compressed);

        $this->assertEquals('Compressed string', $result);
    }

    /*
     * Tests for filter FlateDecode
     */

    public function testDecodeFilterFlateDecode(): void
    {
        $compressed = gzcompress('Compress me', 9);
        $result = $this->fixture->decode_filter('FlateDecode', $compressed);

        $this->assertEquals('Compress me', $result);
    }

    /**
     * How does function behave if an empty string was given.
     */
    public function testDecodeFilterFlateDecodeEmptyString(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('decodeFilterFlateDecode: invalid data');

        $this->fixture->decode_filter('FlateDecode', '');
    }

    /**
     * How does function behave if an uncompressed string was given.
     */
    public function testDecodeFilterFlateDecodeUncompressedString(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('decodeFilterFlateDecode: invalid data');

        $this->fixture->decode_filter('FlateDecode', 'something');
    }

    /**
     * How does function behave if compression checksum is CRC32 instead of Adler-32.
     * See: https://github.com/smalot/pdfparser/issues/592
     */
    public function testDecodeFilterFlateDecodeCRC32Checksum(): void
    {
        $document = (new Parser())->parse_file($this->rootDir.'/samples/bugs/Issue592.pdf');

        self::assertStringContainsString('Two Westbrook Corporate Center Suite 500', $document->get_text());
    }

    /**
     * How does function behave if an unknown filter name was given.
     */
    public function testDecodeFilterUnknownFilter(): void
    {
        $result = $this->fixture->decode_filter('a string '.rand(), 'something');
        $this->assertEquals('something', $result);
    }

    /*
     * Test for filters not being implemented yet.
     */

    /**
     * CCITTFaxDecode
     */
    public function testDecodeFilterCCITTFaxDecode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decode CCITTFaxDecode not implemented yet.');

        $this->fixture->decode_filter('CCITTFaxDecode', '');
    }

    /**
     * Crypt
     */
    public function testDecodeFilterCrypt(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decode Crypt not implemented yet.');

        $this->fixture->decode_filter('Crypt', '');
    }

    /**
     * DCTDecode
     */
    public function testDecodeFilterDCTDecode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decode DCTDecode not implemented yet.');

        $this->fixture->decode_filter('DCTDecode', '');
    }

    /**
     * JBIG2Decode
     */
    public function testDecodeFilterJBIG2Decode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decode JBIG2Decode not implemented yet.');

        $this->fixture->decode_filter('JBIG2Decode', '');
    }

    /**
     * JPXDecode
     */
    public function testDecodeFilterJPXDecode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decode JPXDecode not implemented yet.');

        $this->fixture->decode_filter('JPXDecode', '');
    }
}
