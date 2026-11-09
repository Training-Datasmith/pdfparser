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
use Smalot\PdfParser\Element\Element_Numeric;

class Element_Numeric_Test extends Test_Case
{
    public function testParse(): void
    {
        // Skipped.
        $offset = 0;
        $element = Element_Numeric::parse('ABC', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(' [ 2 ]', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(' /2', null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(" /2 \n 2", null, $offset);
        $this->assertFalse($element);
        $this->assertEquals(0, $offset);

        // Valid.
        $offset = 0;
        $element = Element_Numeric::parse(' -2', null, $offset);
        $this->assertEquals(-2.0, $element->get_content());
        $this->assertEquals(3, $offset);

        $offset = 0;
        $element = Element_Numeric::parse('2BC', null, $offset);
        $this->assertEquals(2.0, $element->get_content());
        $this->assertEquals(1, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(' 2BC', null, $offset);
        $this->assertEquals(2.0, $element->get_content());
        $this->assertEquals(2, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(' -2BC', null, $offset);
        $this->assertEquals(-2.0, $element->get_content());
        $this->assertEquals(3, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(' -2', null, $offset);
        $this->assertEquals(-2.0, $element->get_content());
        $this->assertEquals(3, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(' 2 0 obj', null, $offset);
        $this->assertEquals(2.0, $element->get_content());
        $this->assertEquals(2, $offset);

        $offset = 0;
        $element = Element_Numeric::parse(" \n -2 ", null, $offset);
        $this->assertEquals(-2.0, $element->get_content());
        $this->assertEquals(5, $offset);
    }

    public function testGetContent(): void
    {
        $element = new Element_Numeric('B');
        $this->assertEquals(0.0, $element->get_content());

        $element = new Element_Numeric('-2.5');
        $this->assertEquals(-2.5, $element->get_content());

        $element = new Element_Numeric('-2');
        $this->assertEquals(-2.0, $element->get_content());

        $element = new Element_Numeric(' -2');
        $this->assertEquals(-2.0, $element->get_content());

        $element = new Element_Numeric('2.5');
        $this->assertEquals(2.5, $element->get_content());

        $element = new Element_Numeric('2');
        $this->assertEquals(2.0, $element->get_content());
    }

    public function testEquals(): void
    {
        $element = new Element_Numeric('1');
        $this->assertFalse($element->equals('B'));
        $element = new Element_Numeric('1.5');
        $this->assertFalse($element->equals('B'));

        $element = new Element_Numeric('2');
        $this->assertTrue($element->equals('2'));
        $element = new Element_Numeric('2');
        $this->assertFalse($element->equals('3'));

        $element = new Element_Numeric('-2');
        $this->assertTrue($element->equals('-2'));
        $element = new Element_Numeric('-2');
        $this->assertFalse($element->equals('-3'));

        $element = new Element_Numeric('2.5');
        $this->assertTrue($element->equals('2.5'));
        $element = new Element_Numeric('2.5');
        $this->assertFalse($element->equals('3.5'));

        $element = new Element_Numeric('-2.5');
        $this->assertTrue($element->equals('-2.5'));
        $element = new Element_Numeric('-2.5');
        $this->assertFalse($element->equals('-3.5'));
    }

    public function testContains(): void
    {
        $element = new Element_Numeric('1');
        $this->assertFalse($element->contains('B'));
        $element = new Element_Numeric('1.5');
        $this->assertFalse($element->contains('B'));

        $element = new Element_Numeric('2');
        $this->assertTrue($element->contains('2'));
        $element = new Element_Numeric('2');
        $this->assertFalse($element->contains('3'));

        $element = new Element_Numeric('-2');
        $this->assertTrue($element->contains('-2'));
        $element = new Element_Numeric('-2');
        $this->assertFalse($element->contains('-3'));

        $element = new Element_Numeric('2.5');
        $this->assertTrue($element->contains('2.5'));
        $element = new Element_Numeric('2.5');
        $this->assertFalse($element->contains('3.5'));

        $element = new Element_Numeric('-2.5');
        $this->assertTrue($element->contains('-2.5'));
        $element = new Element_Numeric('-2.5');
        $this->assertFalse($element->contains('-3.5'));
    }

    public function testToString(): void
    {
        $element = new Element_Numeric('B');
        $this->assertEquals('0', (string) $element);
        $element = new Element_Numeric('1B');
        $this->assertEquals('1', (string) $element);

        $element = new Element_Numeric('2');
        $this->assertEquals('2', (string) $element);

        $element = new Element_Numeric('-2');
        $this->assertEquals('-2', (string) $element);

        $element = new Element_Numeric('2.5');
        $this->assertEquals('2.5', (string) $element);

        $element = new Element_Numeric('-2.5');
        $this->assertEquals('-2.5', (string) $element);
    }
}
