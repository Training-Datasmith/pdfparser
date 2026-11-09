<?php

declare(strict_types=1);

/**
 * @file This file is part of the PdfParser library.
 *
 * @author  Konrad Abicht <k.abicht@gmail.com>
 *
 * @date    2021-02-09
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
if (!extension_loaded('mbstring')) {
    $vendor_autoload = __DIR__.'/../../vendor/autoload.php';
    if (!is_readable($vendor_autoload)) {
        fwrite(STDERR, "alt-autoload-check: vendor/autoload.php is required when ext-mbstring is not loaded (polyfill bootstrap).\n");
        exit(1);
    }
    require $vendor_autoload;
}

require __DIR__.'/../../alt_autoload.php-dist';

$parser = new Smalot\PdfParser\Parser();

$filename = __DIR__.'/../../samples/InternationalChars.pdf';
$document = $parser->parse_file($filename);

$needle = 'Лорем ипсум долор сит амет, еу сед либрис долорем инцоррупте.';
if (false === strpos($document->get_text(), $needle)) {
    throw new \Exception('Something went wrong. Alt-Autoload is not working.');
}

fwrite(STDOUT, "alt-autoload-check: OK\n");
exit(0);
