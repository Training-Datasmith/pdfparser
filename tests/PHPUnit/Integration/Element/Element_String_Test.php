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
use Smalot\PdfParser\Element\Element_String;

class Element_String_Test extends Test_Case
{
    public function testParse(): void
    {
        // Skipped.
        $offset = 0;
        $element = Element_String::parse('ABC', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_String::parse(' [ (ABC) 5 6 ]', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_String::parse(' << (invalid) >>', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_String::parse(' / (FlateDecode) ', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_String::parse(' 0 (FlateDecode) ', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_String::parse(" 0 \n (FlateDecode) ", null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        // Valid.
        $offset = 0;
        $element = Element_String::parse(' (Copyright) ', null, $offset);
        $this->assertEquals('Copyright', $element->get_content());
        $this->assertEquals(12, $offset);

        $offset = 0;
        $element = Element_String::parse(' (Copyright) ', null, $offset);
        $this->assertEquals('Copyright', $element->get_content());
        $this->assertEquals(12, $offset);

        $offset = 0;
        $element = Element_String::parse(' (Copyright)', null, $offset);
        $this->assertEquals('Copyright', $element->get_content());
        $this->assertEquals(12, $offset);

        $offset = 0;
        $element = Element_String::parse('(Copyright)', null, $offset);
        $this->assertEquals('Copyright', $element->get_content());
        $this->assertEquals(11, $offset);

        $offset = 0;
        $element = Element_String::parse('(Copy-right2)', null, $offset);
        $this->assertEquals('Copy-right2', $element->get_content());
        $this->assertEquals(13, $offset);

        $offset = 0;
        $element = Element_String::parse(" \n (Copyright) ", null, $offset);
        $this->assertEquals('Copyright', $element->get_content());
        $this->assertEquals(14, $offset);

        $offset = 0;
        $element = Element_String::parse('()', null, $offset);
        $this->assertEquals('', $element->get_content());
        $this->assertEquals(2, $offset);

        /*
         * Complex study case : Unicode + octal.
         */
        $offset = 0;
        $element = Element_String::parse('(ABC\\))', null, $offset);
        $this->assertEquals('ABC)', $element->get_content());
        $this->assertEquals(7, $offset);

        $offset = 0;
        $element = Element_String::parse("(\xFE\xFF\\000M)", null, $offset);
        $this->assertEquals('M', $element->get_content());
        $this->assertEquals(9, $offset);

        $offset = 0;
        $element = Element_String::parse('(<20>)', null, $offset);
        $this->assertEquals(' ', $element->get_content());
        $this->assertEquals(6, $offset);

        $offset = 0;
        $element = Element_String::parse('(Gutter\\ console\\ assembly)', null, $offset);
        $this->assertEquals('Gutter console assembly', $element->get_content());
        $this->assertEquals(27, $offset);
    }

    public function testGetContent(): void
    {
        $element = new Element_String('Copyright');
        $this->assertEquals('Copyright', $element->get_content());
    }

    public function testEquals(): void
    {
        $element = new Element_String('CopyRight');
        $this->assertTrue($element->equals('CopyRight'));
        $this->assertFalse($element->equals('Flatedecode'));

        $element = new Element_String('CopyRight2');
        $this->assertTrue($element->equals('CopyRight2'));
        $this->assertFalse($element->equals('CopyRight3'));

        $element = new Element_String('Flate-Decode2');
        $this->assertTrue($element->equals('Flate-Decode2'));
        $this->assertFalse($element->equals('Flate-Decode3'));
    }

    public function testContains(): void
    {
        $element = new Element_String('CopyRight');
        $this->assertTrue($element->contains('CopyRight'));
        $this->assertFalse($element->contains('Copyright'));

        $element = new Element_String('CopyRight2');
        $this->assertTrue($element->contains('CopyRight2'));
        $this->assertFalse($element->contains('CopyRight3'));
    }

    public function testToString(): void
    {
        $element = new Element_String('CopyRight');
        $this->assertEquals('CopyRight', (string) $element);
    }
}
