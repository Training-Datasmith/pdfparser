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

namespace PHP_Unit_Tests\Integration;

use PHP_Unit_Tests\Test_Case;
use Smalot\PdfParser\Element;
use Smalot\PdfParser\Element\Element_Missing;
use Smalot\PdfParser\Element\Element_Name;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Pdf_Object;

/**
 * Class Header
 */
class Header_Test extends Test_Case
{
    /**
     * Checks that init function is called for each element.
     */
    public function testInitHappyPath(): void
    {
        $element = $this->createMock(Element::class);
        $element->expects($this->exactly(1))->method('init');

        $fixture = new Header([$element]);
        $fixture->init();
    }

    /**
     * Checks buggy behavior if an element was given which is not of type Element.
     *
     * Problem was, it always called $element::init(), even if its not an object at all.
     *
     * @see https://github.com/smalot/pdfparser/issues/367
     *
     * @doesNotPerformAssertions
     */
    public function testInitInvalidElement(): void
    {
        $element = false;

        $fixture = new Header([$element]);
        $fixture->init();
    }

    public function testParse(): void
    {
        $document = $this->get_document_instance();

        $content = '<</Type/Page/SubType/Text>>foo';
        $position = 0;
        $header = Header::parse($content, $document, $position);

        $this->assertTrue($header instanceof Header);
        $this->assertEquals(27, $position);
        $this->assertEquals(2, \count($header->get_elements()));

        // No header to parse
        $this->assertEquals('Page', (string) $header->get('Type'));
        $content = 'foo';
        $position = 0;
        $header = Header::parse($content, $document, $position);

        $this->assertTrue($header instanceof Header);
        $this->assertEquals(0, $position);
        $this->assertEquals(0, \count($header->get_elements()));

        $position = 0;
        $content = "<</CreationDate(D:20100309184803+01'00')/Author(Utilisateur)/Creator(PScript5.dll Version 5.2.2)/Producer(Acrobat Distiller 7.0.5 \(Windows\))/ModDate(D:20100310104810+01'00')/Title(Microsoft Word - CLEMI.docx)>>";
        Header::parse($content, $document, $position);
        $this->assertEquals(212, $position);

        $position = 0;
        $content = '[5 0 R ] foo';
        $header = Header::parse($content, $document, $position);
        $this->assertEquals(8, $position);
        $this->assertEquals(1, \count($header->get_elements()));
    }

    public function testGetElements(): void
    {
        $document = $this->get_document_instance();

        $content = '<</Type/Page/Subtype/Text>>foo';
        $position = 0;
        $header = Header::parse($content, $document, $position);

        $elements = $header->get_elements();
        $this->assertEquals(2, \count($elements));
        $this->assertTrue(current($elements) instanceof Element_Name);

        $types = $header->get_element_types();
        $this->assertTrue(\is_array($types));
        $this->assertEquals(Element_Name::class, $types['Type']);
        $this->assertEquals(Element_Name::class, $types['Subtype']);
    }

    public function testHas(): void
    {
        $document = $this->get_document_instance();

        $content = '<</Type/Page/SubType/Text/Font 5 0 R>>foo';
        $position = 0;
        $header = Header::parse($content, $document, $position);

        $this->assertTrue($header->has('Type'));
        $this->assertTrue($header->has('SubType'));
        $this->assertTrue($header->has('Font'));
        $this->assertFalse($header->has('Text'));
    }

    public function testGet(): void
    {
        $document = $this->get_document_instance();

        $content = '<</Type/Page/SubType/Text/Font 5 0 R/Resources 8 0 R>>foo';
        $position = 0;
        $header = Header::parse($content, $document, $position);
        $object = new Page($document, $header);
        $document->set_objects(['5_0' => $object]);

        $this->assertTrue($header->get('Type') instanceof Element_Name);
        $this->assertTrue($header->get('SubType') instanceof Element_Name);
        $this->assertTrue($header->get('Font') instanceof Page);
        $this->assertTrue($header->get('Image') instanceof Element_Missing);
        $this->assertTrue($header->get('Resources') instanceof Element_Missing);

        /**
         * A double forward slash in the header's content results in a falsy element
         * that should be parsed to Element_Missing instead.
         *
         * @see https://github.com/smalot/pdfparser/pull/525
         */
        $content = '<</Type/Page/SubType//Text>>foo';
        $position = 0;
        $header = Header::parse($content, $document, $position);

        $this->assertTrue($header->get('SubType') instanceof Element_Missing);
    }

    public function testResolveXRef(): void
    {
        $document = $this->get_document_instance();
        $content = '<</Type/Page/SubType/Text/Font 5 0 R/Resources 8 0 R>>foo';
        $position = 0;
        $header = Header::parse($content, $document, $position);
        $object = new Page($document, $header);
        $document->set_objects(['5_0' => $object]);

        $this->assertTrue($header->get('Font') instanceof Pdf_Object);

        $header = $header->get('Resources');
        $this->assertTrue($header instanceof Element_Missing);
    }
}
