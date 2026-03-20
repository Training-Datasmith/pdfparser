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
use Smalot\Pdf_Parser\Header;
use Smalot\Pdf_Parser\Pdf_Object;
/**
 * Class ElementArray
 */
class Element_Array extends Element
{
    public function get_content()
    {
        foreach ($this->value as $name => $element) {
            $this->resolve_x_ref($name);
        }
        return parent::get_content();
    }
    public function get_raw_content(): array
    {
        return $this->value;
    }
    public function get_details(bool $deep = true): array
    {
        $values = [];
        $elements = $this->get_content();
        foreach ($elements as $key => $element) {
            if ($element instanceof Header && $deep) {
                $values[$key] = $element->get_details($deep);
            } elseif ($element instanceof Pdf_Object && $deep) {
                $values[$key] = $element->get_details(false);
            } elseif ($element instanceof self) {
                if ($deep) {
                    $values[$key] = $element->get_details();
                }
            } elseif ($element instanceof Element && !$element instanceof self) {
                $values[$key] = $element->get_content();
            }
        }
        return $values;
    }
    public function __toString(): string
    {
        return implode(',', $this->value);
    }
    /**
     * @return Element|PDFObject
     */
    protected function resolve_x_ref(string $name)
    {
        if (($obj = $this->value[$name]) instanceof Element_X_Ref) {
            /** @var ElementXRef $obj */
            $obj = $this->document->get_object_by_id($obj->get_id());
            $this->value[$name] = $obj;
        }
        return $this->value[$name];
    }
    /**
     * @todo: These methods return mixed and mismatched types throughout the hierarchy
     *
     * @return bool|ElementArray
     */
    public static function parse(string $content, ?Document $document = null, int &$offset = 0)
    {
        if (preg_match('/^\s*\[(?P<array>.*)/is', $content, $match)) {
            preg_match_all('/(.*?)(\[|\])/s', trim($content), $matches);
            $level = 0;
            $sub = '';
            foreach ($matches[0] as $part) {
                $sub .= $part;
                $level += false !== strpos($part, '[') ? 1 : -1;
                if ($level <= 0) {
                    break;
                }
            }
            // Removes 1 level [ and ].
            $sub = substr(trim($sub), 1, -1);
            $sub_offset = 0;
            $values = Element::parse($sub, $document, $sub_offset, true);
            $offset += strpos($content, '[') + 1;
            // Find next ']' position
            $offset += \strlen($sub) + 1;
            return new self($values, $document);
        }
        return false;
    }
}