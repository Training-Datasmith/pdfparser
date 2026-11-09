<?php

declare(strict_types=1);

/**
 * @file This file is part of the PdfParser library.
 *
 * @author  Konrad Abicht <k.abicht@gmail.com>
 *
 * @date    2020-06-02
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

namespace PHP_Unit_Tests\Integration\Element;

use PHP_Unit_Tests\Test_Case;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Element\Element_Array;
use Smalot\PdfParser\Element\Element_Numeric;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Page;

class Element_Array_Test extends Test_Case
{
    public function testParse(): void
    {
        $document = $this->get_document_instance();

        // Skipped.
        $offset = 0;
        $element = Element_Array::parse('ABC', $document, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_Array::parse(' / [ 4 2 ] ', $document, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_Array::parse(' 0 [ 4 2 ] ', $document, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_Array::parse(" 0 \n [ 4 2 ] ", $document, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        // Valid.
        $offset = 0;
        $element = Element_Array::parse(' [ 4 2 ] ', $document, $offset);
        $this->assertTrue($element->contains(2));
        $this->assertTrue($element->contains(4));
        $this->assertFalse($element->contains(8));
        $this->assertEquals(8, $offset);

        $offset = 0;
        $element = Element_Array::parse(' [ 4 2 ]', $document, $offset);
        $this->assertTrue($element->contains(2));
        $this->assertTrue($element->contains(4));
        $this->assertFalse($element->contains(8));
        $this->assertEquals(8, $offset);

        $offset = 0;
        $element = Element_Array::parse('[ 4 2 ]', $document, $offset);
        $this->assertTrue($element->contains(2));
        $this->assertTrue($element->contains(4));
        $this->assertFalse($element->contains(8));
        $this->assertEquals(7, $offset);

        $offset = 0;
        $element = Element_Array::parse(" \n [ 4 2 ] ", $document, $offset);
        $this->assertTrue($element->contains(2));
        $this->assertTrue($element->contains(4));
        $this->assertFalse($element->contains(8));
        $this->assertEquals(10, $offset);
    }

    public function testGetContent(): void
    {
        $val_4 = new Element_Numeric('4');
        $val_2 = new Element_Numeric('2');
        $element = new Element_Array([$val_4, $val_2]);

        $content = $element->get_content();
        $this->assertCount(2, $content);
    }

    public function testContains(): void
    {
        $val_4 = new Element_Numeric('4');
        $val_2 = new Element_Numeric('2');
        $element = new Element_Array([$val_4, $val_2]);

        $this->assertTrue($element->contains(2));
        $this->assertTrue($element->contains(4));

        $this->assertFalse($element->contains(8));
    }

    public function testResolveXRef(): void
    {
        // Document with text.
        $filename = $this->rootDir.'/samples/Document1_pdfcreator_nocompressed.pdf';
        $parser = $this->get_parser_instance();
        $document = $parser->parse_file($filename);
        $object = $document->get_object_by_id('3_0');
        $kids = $object->get('Kids');

        $this->assertTrue($kids instanceof Element_Array);
        $this->assertCount(1, $kids->get_content());

        $pages = $kids->get_content();
        $this->assertTrue(reset($pages) instanceof Page);
    }

    public function testGetDetails(): void
    {
        $document = $this->get_document_instance();
        $content = '<</Type/Page/Types[8]/Sizes[1 2 3 4 5 <</Subtype/XObject>> [8 [9 <</FontSize 10>>]]]>>';
        $details_reference = [
            'Type' => 'Page',
            'Types' => [
                8,
            ],
            'Sizes' => [
                1,
                2,
                3,
                4,
                5,
                [
                    'Subtype' => 'XObject',
                ],
                [
                    8,
                    [
                        9,
                        [
                            'FontSize' => 10,
                        ],
                    ],
                ],
            ],
        ];
        $header = Header::parse($content, $document);
        $details = $header->get_details();

        $this->assertCount(3, $details);
        $this->assertEquals($details_reference, $details);
    }

    public function testToString(): void
    {
        $val_4 = new Element_Numeric('4');
        $val_2 = new Element_Numeric('2');
        $element = new Element_Array([$val_4, $val_2]);
        $this->assertEquals('4,2', (string) $element);

        $document = $this->get_document_instance();
        $element = Element_Array::parse(' [ 4 2 ]', $document);
        $this->assertEquals('4,2', (string) $element);
    }
}
