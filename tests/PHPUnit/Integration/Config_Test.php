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
use Smalot\PdfParser\Config;

class Config_Test extends Test_Case
{
    public function testHorizontalOffset()
    {
        $filename = $this->rootDir.'/samples/bugs/Issue494.pdf';

        $config = new Config();
        $config->set_horizontal_offset('');

        $parser = $this->get_parser_instance($config);
        $document = $parser->parse_file($filename);
        $text = $document->get_text();

        $reference = '11 ADET DERGİ İÇİN 3 KALEM HİZMET ALIMI İHALE EDİLECEKTİR ';
        $firstLine = explode("\n", $text)[0];
        $this->assertEquals($reference, $firstLine);
    }
}
