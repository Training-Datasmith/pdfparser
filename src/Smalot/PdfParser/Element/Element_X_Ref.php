<?php

declare (strict_types=1);
/**
 * @file
 *          This file is part of the PdfParser library.
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
namespace Smalot\Pdf_Parser\Element;

use Smalot\Pdf_Parser\Document;
use Smalot\Pdf_Parser\Element;
/**
 * Class ElementXRef
 */
class Element_X_Ref extends Element
{
    public function get_id(): string
    {
        return $this->get_content();
    }
    public function get_object()
    {
        return $this->document->get_object_by_id($this->get_id());
    }
    public function equals($value): bool
    {
        /**
         * In case $value is a number and $this->value is a string like 5_0
         *
         * Without this if-clause code like:
         *
         *      $element = new ElementXRef('5_0');
         *      $this->assertTrue($element->equals(5));
         *
         * would fail (= 5_0 and 5 are not equal in PHP 8.0+).
         */
        if (true === is_numeric($value) && true === \is_string($this->get_content()) && 1 === preg_match('/[0-9]+\_[0-9]+/', $this->get_content(), $matches)) {
            return (float) $this->get_content() == $value;
        }
        $id = $value instanceof self ? $value->get_id() : $value;
        return $this->get_id() == $id;
    }
    public function __toString(): string
    {
        return '#Obj#' . $this->get_id();
    }
    /**
     * @return bool|ElementXRef
     */
    public static function parse(string $content, ?Document $document = null, int &$offset = 0)
    {
        if (preg_match('/^\s*(?P<id>[0-9]+\s+[0-9]+\s+R)/s', $content, $match)) {
            $id = $match['id'];
            $offset += strpos($content, $id) + \strlen($id);
            $id = str_replace(' ', '_', rtrim($id, ' R'));
            return new self($id, $document);
        }
        return false;
    }
}