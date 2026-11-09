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
namespace Smalot\PdfParser;

use Smalot\PdfParser\Element\Element_Array;
use Smalot\PdfParser\Element\Element_Boolean;
use Smalot\PdfParser\Element\Element_Date;
use Smalot\PdfParser\Element\Element_Hexa;
use Smalot\PdfParser\Element\Element_Name;
use Smalot\PdfParser\Element\Element_Null;
use Smalot\PdfParser\Element\Element_Numeric;
use Smalot\PdfParser\Element\Element_String;
use Smalot\PdfParser\Element\Element_Struct;
use Smalot\PdfParser\Element\Element_X_Ref;
/**
 * Class Element
 */
class Element
{
    /**
     * @var Document|null
     */
    protected $document;
    protected $value;
    public function __construct($value, ?Document $document = null)
    {
        $this->value = $value;
        $this->document = $document;
    }
    public function init()
    {
    }
    public function equals($value): bool
    {
        return $value == $this->value;
    }
    public function contains($value): bool
    {
        if (\is_array($this->value)) {
            /** @var Element $val */
            foreach ($this->value as $val) {
                if ($val->equals($value)) {
                    return true;
                }
            }
            return false;
        }
        return $this->equals($value);
    }
    public function get_content()
    {
        return $this->value;
    }
    public function __toString(): string
    {
        return (string) $this->value;
    }
    /**
     * @return mixed[]
     */
    public static function parse(string $content, ?Document $document = null, int &$offset = 0)
    {
        $args = \func_get_args();
        $only_values = $args[3] ?? false;
        $content = trim($content);
        $values = [];
        do {
            $old_offset = $offset;
            if (!$only_values) {
                if (!preg_match('/\G\s*(?P<name>\/[A-Z#0-9\._]+)(?P<value>.*)/si', $content, $match, 0, $offset)) {
                    break;
                } else {
                    $name = preg_replace_callback('/#([0-9a-f]{2})/i', function ($m): string {
                        return \chr((int) base_convert($m[1], 16, 10));
                    }, ltrim($match['name'], '/'));
                    $value = $match['value'];
                    $offset = strpos($content, $value, $offset + \strlen($match['name']));
                }
            } else {
                $name = \count($values);
                $value = substr($content, $offset);
            }
            if ($element = Element_Name::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_X_Ref::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_Numeric::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_Struct::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_Boolean::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_Null::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_Date::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_String::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_Hexa::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } elseif ($element = Element_Array::parse($value, $document, $offset)) {
                $values[$name] = $element;
            } else {
                $offset = $old_offset;
                break;
            }
        } while ($offset < \strlen($content));
        return $values;
    }
}