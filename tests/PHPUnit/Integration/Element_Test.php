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
use Smalot\PdfParser\Element\Element_Array;
use Smalot\PdfParser\Element\Element_Boolean;
use Smalot\PdfParser\Element\Element_Date;
use Smalot\PdfParser\Element\Element_Name;
use Smalot\PdfParser\Element\Element_Null;
use Smalot\PdfParser\Element\Element_Numeric;
use Smalot\PdfParser\Element\Element_String;
use Smalot\PdfParser\Element\Element_X_Ref;
use Smalot\PdfParser\Header;

class Element_Test extends Test_Case
{
    public function testParse(): void
    {
        $document = $this->get_document_instance();

        // Only_values = false.
        $content = '/NameType /FlateDecode
        /Contents[4 0 R 42]/Fonts<</F1 41/F2 43>>/NullType
        null/StringType(hello)/DateType(D:20130901235555+02\'00\')/XRefType 2 0 R
        /NumericType 8/HexaType<0020>/BooleanType false
        /Space#20Test(Templates)/Hyphen#2DTest(Templates)';
        $offset = 0;

        $elements = Element::parse($content, $document, $offset, false);

        $this->assertTrue(\array_key_exists('NameType', $elements));
        $this->assertTrue($elements['NameType'] instanceof Element_Name);
        $this->assertEquals('FlateDecode', $elements['NameType']->get_content());

        $this->assertTrue(\array_key_exists('Contents', $elements));
        $this->assertTrue($elements['Contents'] instanceof Element_Array);
        $this->assertTrue($elements['Contents']->contains(42));

        $this->assertTrue(\array_key_exists('Fonts', $elements));
        $this->assertTrue($elements['Fonts'] instanceof Header);

        $this->assertTrue(\array_key_exists('NullType', $elements));
        $this->assertTrue($elements['NullType'] instanceof Element_Null);
        $this->assertEquals('null', (string) $elements['NullType']);

        $this->assertTrue(\array_key_exists('StringType', $elements));
        $this->assertTrue($elements['StringType'] instanceof Element_String);
        $this->assertEquals('hello', $elements['StringType']->get_content());

        $this->assertTrue(\array_key_exists('DateType', $elements));
        $this->assertTrue($elements['DateType'] instanceof Element_Date);

        $this->assertTrue(\array_key_exists('XRefType', $elements));
        $this->assertTrue($elements['XRefType'] instanceof Element_X_Ref);
        $this->assertEquals('2_0', $elements['XRefType']->get_id());

        $this->assertTrue(\array_key_exists('NumericType', $elements));
        $this->assertTrue($elements['NumericType'] instanceof Element_Numeric);
        $this->assertEquals('8', (string) $elements['NumericType']);

        $this->assertTrue(\array_key_exists('HexaType', $elements));
        $this->assertTrue($elements['HexaType'] instanceof Element_String);
        $this->assertEquals(' ', (string) $elements['HexaType']);

        $this->assertTrue(\array_key_exists('BooleanType', $elements));
        $this->assertTrue($elements['BooleanType'] instanceof Element_Boolean);
        $this->assertFalse($elements['BooleanType']->get_content());

        $this->assertTrue(\array_key_exists('Space Test', $elements));

        $this->assertTrue(\array_key_exists('Hyphen-Test', $elements));

        // Only_values = true.
        $content = '/NameType /FlateDecode';
        $offset = 0;
        $elements = Element::parse($content, $document, $offset, true);
        $this->assertEquals(2, \count($elements));
        $this->assertEquals(22, $offset);

        // Test error.
        $content = '/NameType /FlateDecode $$$';
        $offset = 0;
        $elements = Element::parse($content, $document, $offset, false);
        $this->assertEquals(1, \count($elements));
        $this->assertEquals(22, $offset);
        $this->assertEquals('NameType', key($elements));
        $this->assertTrue(current($elements) instanceof Element_Name);

        $content = '/NameType $$$';
        $offset = 0;
        $elements = Element::parse($content, $document, $offset, false);
        $this->assertEquals(0, $offset);
        $this->assertEquals(0, \count($elements));
    }

    public function testGetContent(): void
    {
        $element = $this->get_element_instance(42);
        $content = $element->get_content();
        $this->assertEquals(42, $content);

        $element = $this->get_element_instance([4, 2]);
        $this->assertEquals(2, \count($element->get_content()));
    }

    public function testEquals(): void
    {
        $element = $this->get_element_instance(2);

        $this->assertTrue($element->equals(2));
    }

    public function testContains(): void
    {
        $element = $this->get_element_instance([$this->get_element_instance(4), $this->get_element_instance(2)]);

        $this->assertTrue($element->contains(2));
        $this->assertFalse($element->contains(8));
    }

    public function testToString(): void
    {
        $this->assertEquals((string) $this->get_element_instance('2'), '2');
    }
}
