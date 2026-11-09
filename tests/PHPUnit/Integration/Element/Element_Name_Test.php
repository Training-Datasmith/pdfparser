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
use Smalot\PdfParser\Element\Element_Name;

class Element_Name_Test extends Test_Case
{
    public function testParse(): void
    {
        // Skipped.
        $offset = 0;
        $element = Element_Name::parse('ABC', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);
        $offset = 0;
        $element = Element_Name::parse(' [ /ABC 5 6 ]', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);
        $offset = 0;
        $element = Element_Name::parse(' << invalid >>', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);
        $offset = 0;
        $element = Element_Name::parse(' / FlateDecode ', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);
        $offset = 0;
        $element = Element_Name::parse(' 0 /FlateDecode ', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);
        $offset = 0;
        $element = Element_Name::parse(" 0 \n /FlateDecode ", null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        // Valid.
        $offset = 0;
        $element = Element_Name::parse(' /FlateDecode ', null, $offset);
        $this->assertEquals('FlateDecode', $element->get_content());
        $this->assertEquals(13, $offset);

        $offset = 0;
        $element = Element_Name::parse(' /FlateDecode', null, $offset);
        $this->assertEquals('FlateDecode', $element->get_content());
        $this->assertEquals(13, $offset);

        $offset = 0;
        $element = Element_Name::parse('/FlateDecode', null, $offset);
        $this->assertEquals('FlateDecode', $element->get_content());
        $this->assertEquals(12, $offset);

        $offset = 0;
        $element = Element_Name::parse(" \n /FlateDecode ", null, $offset);
        $this->assertEquals('FlateDecode', $element->get_content());
        $this->assertEquals(15, $offset);

        $offset = 0;
        $element = Element_Name::parse('/FlateDecode2', null, $offset);
        $this->assertEquals('FlateDecode2', $element->get_content());
        $this->assertEquals(13, $offset);

        $offset = 0;
        $element = Element_Name::parse('/Flate-Decode2', null, $offset);
        $this->assertEquals('Flate-Decode2', $element->get_content());
        $this->assertEquals(14, $offset);

        $offset = 0;
        $element = Element_Name::parse('/OJHCYD+Cambria', null, $offset);
        $this->assertEquals('OJHCYD+Cambria', $element->get_content());
        $this->assertEquals(15, $offset);

        $offset = 0;
        $element = Element_Name::parse('/OJHCYD+Cambria,Bold', null, $offset);
        $this->assertEquals('OJHCYD+Cambria,Bold', $element->get_content());
        $this->assertEquals(20, $offset);

        $offset = 0;
        $element = Element_Name::parse('/Flate_Decode2', null, $offset);
        $this->assertEquals('Flate', $element->get_content());
        $this->assertEquals(6, $offset);

        $offset = 0;
        $element = Element_Name::parse('/Flate.Decode2', null, $offset);
        $this->assertEquals('Flate.Decode2', $element->get_content());
        $this->assertEquals(14, $offset);
    }

    public function testGetContent(): void
    {
        $element = new Element_Name('FlateDecode');
        $this->assertEquals('FlateDecode', $element->get_content());
    }

    public function testEquals(): void
    {
        $element = new Element_Name('FlateDecode');
        $this->assertTrue($element->equals('FlateDecode'));
        $this->assertFalse($element->equals('Flatedecode'));

        $element = new Element_Name('FlateDecode2');
        $this->assertTrue($element->equals('FlateDecode2'));
        $this->assertFalse($element->equals('FlateDecode3'));

        $element = new Element_Name('Flate-Decode2');
        $this->assertTrue($element->equals('Flate-Decode2'));
        $this->assertFalse($element->equals('Flate-Decode3'));
    }

    public function testContains(): void
    {
        $element = new Element_Name('FlateDecode');
        $this->assertTrue($element->contains('FlateDecode'));
        $this->assertFalse($element->contains('Flatedecode'));

        $element = new Element_Name('FlateDecode2');
        $this->assertTrue($element->contains('FlateDecode2'));
        $this->assertFalse($element->contains('FlateDecode3'));

        $element = new Element_Name('Flate-Decode2');
        $this->assertTrue($element->contains('Flate-Decode2'));
        $this->assertFalse($element->contains('Flate-Decode3'));
    }

    public function testToString(): void
    {
        $element = new Element_Name('FlateDecode');
        $this->assertEquals('FlateDecode', (string) $element);
    }
}
