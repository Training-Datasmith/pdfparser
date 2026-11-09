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

namespace Performance_Tests\Test;

use Performance_Tests\Abstract_Performance_Test;
use Smalot\PdfParser\Parser;

/**
 * This test checks does a performance test with certain PDF files that extensively use
 * the getFirstFont() method of Document.php. If Document.php correctly uses a dictionary
 * to cache the objects inside the PDF file, then the parsing should be quick.
 * If it does not, the parsing can be extensively slow or even crash.
 */
class Document_Dictionary_Cache_Test extends Abstract_Performance_Test
{
    /**
     * @var Parser
     */
    protected $parser;
    protected $data;

    public function init(): void
    {
        $this->parser = new Parser();

        // load PDF file content
        $this->data = file_get_contents(__DIR__.'/../../../samples/DocumentWithLotsOfObjects.pdf');
    }

    public function run(): void
    {
        // give PDF content to function and parse it
        $pdf = $this->parser->parse_content($this->data);

        $pages = $pdf->get_pages();

        foreach ($pages as $i => $page) { /* @var $page Page */
            if ($i < 77) {
                continue;
            }
            if ($i > 78) {
                continue;
            }

            $page->get_text(); // Test this method
        }
    }

    public function get_max_estimated_time(): int
    {
        return 20;
    }
}
