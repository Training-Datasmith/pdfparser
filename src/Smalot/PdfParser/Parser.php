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
namespace Smalot\Pdf_Parser;

use Smalot\Pdf_Parser\Element\Element_Array;
use Smalot\Pdf_Parser\Element\Element_Boolean;
use Smalot\Pdf_Parser\Element\Element_Date;
use Smalot\Pdf_Parser\Element\Element_Hexa;
use Smalot\Pdf_Parser\Element\Element_Name;
use Smalot\Pdf_Parser\Element\Element_Null;
use Smalot\Pdf_Parser\Element\Element_Numeric;
use Smalot\Pdf_Parser\Element\Element_String;
use Smalot\Pdf_Parser\Element\Element_X_Ref;
use Smalot\Pdf_Parser\Raw_Data\Raw_Data_Parser;
/**
 * Class Parser
 */
class Parser
{
    /**
     * @var Config
     */
    private $config;
    /**
     * @var PDFObject[]
     */
    protected $objects = [];
    protected $raw_data_parser;
    public function __construct($cfg = [], ?Config $config = null)
    {
        $this->config = $config ?: new Config();
        $this->raw_data_parser = new Raw_Data_Parser($cfg, $this->config);
    }
    public function get_config(): Config
    {
        return $this->config;
    }
    /**
     * @throws \Exception
     */
    public function parse_file(string $filename): Document
    {
        $content = file_get_contents($filename);
        /*
         * 2018/06/20 @doganoo as multiple times a
         * users have complained that the parseFile()
         * method dies silently, it is an better option
         * to remove the error control operator (@) and
         * let the users know that the method throws an exception
         * by adding @throws tag to PHPDoc.
         *
         * See here for an example: https://github.com/smalot/pdfparser/issues/204
         */
        return $this->parse_content($content);
    }
    /**
     * @param string $content PDF content to parse
     *
     * @throws \Exception if secured PDF file was detected
     * @throws \Exception if no object list was found
     */
    public function parse_content(string $content): Document
    {
        // Create structure from raw data.
        [$xref, $data] = $this->raw_data_parser->parse_data($content);
        if (isset($xref['trailer']['encrypt']) && false === $this->config->get_ignore_encryption()) {
            throw new \Exception('Secured pdf file are currently not supported.');
        }
        if (empty($data)) {
            throw new \Exception('Object list not found. Possible secured file.');
        }
        // Create destination object.
        $document = new Document();
        $this->objects = [];
        foreach ($data as $id => $structure) {
            $this->parse_object($id, $structure, $document);
            unset($data[$id]);
        }
        $document->set_trailer($this->parse_trailer($xref['trailer'], $document));
        $document->set_objects($this->objects);
        return $document;
    }
    protected function parse_trailer(array $structure, ?Document $document): \Smalot\Pdf_Parser\Header
    {
        $trailer = [];
        foreach ($structure as $name => $values) {
            $name = ucfirst($name);
            if (is_numeric($values)) {
                $trailer[$name] = new Element_Numeric($values);
            } elseif (\is_array($values)) {
                $value = $this->parse_trailer($values, null);
                $trailer[$name] = new Element_Array($value);
            } elseif (false !== strpos($values, '_')) {
                $trailer[$name] = new Element_X_Ref($values, $document);
            } else {
                $trailer[$name] = $this->parse_header_element('(', $values, $document);
            }
        }
        return new Header($trailer, $document);
    }
    protected function parse_object(string $id, array $structure, ?Document $document)
    {
        $header = new Header([], $document);
        $content = '';
        foreach ($structure as $position => $part) {
            if (\is_int($part)) {
                $part = [null, null];
            }
            switch ($part[0]) {
                case '[':
                    $elements = [];
                    foreach ($part[1] as $sub_element) {
                        $sub_type = $sub_element[0];
                        $sub_value = $sub_element[1];
                        $elements[] = $this->parse_header_element($sub_type, $sub_value, $document);
                    }
                    $header = new Header($elements, $document);
                    break;
                case '<<':
                    $header = $this->parse_header($part[1], $document);
                    break;
                case 'stream':
                    $content = $part[3][0] ?? $part[1];
                    if ($header->get('Type')->equals('ObjStm')) {
                        $match = [];
                        // Split xrefs and contents.
                        preg_match('/^((\d+\s+\d+\s*)*)(.*)$/s', $content, $match);
                        $content = $match[3];
                        // Extract xrefs.
                        $xrefs = preg_split('/(\d+\s+\d+\s*)/s', $match[1], -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
                        $table = [];
                        foreach ($xrefs as $xref) {
                            [$id, $position] = preg_split("/\\s+/", trim($xref));
                            $table[$position] = $id;
                        }
                        ksort($table);
                        $ids = array_values($table);
                        $positions = array_keys($table);
                        foreach ($positions as $index => $position) {
                            $id = $ids[$index] . '_0';
                            $next_position = $positions[$index + 1] ?? \strlen($content);
                            $sub_content = substr($content, $position, (int) $next_position - (int) $position);
                            $sub_header = Header::parse($sub_content, $document);
                            $object = Pdf_Object::factory($document, $sub_header, '', $this->config);
                            $this->objects[$id] = $object;
                        }
                        // It is not necessary to store this content.
                        return;
                    }
                    if ($header->get('Type')->equals('Metadata')) {
                        // Attempt to parse XMP XML Metadata
                        $document->extract_xmp_metadata($content);
                    }
                    break;
                default:
                    if ('null' != $part) {
                        $element = $this->parse_header_element($part[0], $part[1], $document);
                        if ($element) {
                            $header = new Header([$element], $document);
                        }
                    }
                    break;
            }
        }
        if (!isset($this->objects[$id])) {
            $this->objects[$id] = Pdf_Object::factory($document, $header, $content, $this->config);
        }
    }
    /**
     * @throws \Exception
     */
    protected function parse_header(array $structure, ?Document $document): Header
    {
        $elements = [];
        $count = \count($structure);
        for ($position = 0; $position < $count; $position += 2) {
            $name = $structure[$position][1];
            $type = $structure[$position + 1][0];
            $value = $structure[$position + 1][1];
            $elements[$name] = $this->parse_header_element($type, $value, $document);
        }
        return new Header($elements, $document);
    }
    /**
     * @param string|array $value
     *
     * @return Element|Header|null
     *
     * @throws \Exception
     */
    protected function parse_header_element(?string $type, $value, ?Document $document)
    {
        $value_is_empty = null == $value || '' == $value || false == $value;
        if (('<<' === $type || '>>' === $type) && $value_is_empty) {
            $value = [];
        }
        switch ($type) {
            case '<<':
            case '>>':
                $header = $this->parse_header($value, $document);
                Pdf_Object::factory($document, $header, null, $this->config);
                return $header;
            case 'numeric':
                return new Element_Numeric($value);
            case 'boolean':
                return new Element_Boolean($value);
            case 'null':
                return new Element_Null();
            case '(':
                if ($date = Element_Date::parse('(' . $value . ')', $document)) {
                    return $date;
                }
                return Element_String::parse('(' . $value . ')', $document);
            case '<':
                return $this->parse_header_element('(', Element_Hexa::decode($value), $document);
            case '/':
                return Element_Name::parse('/' . $value, $document);
            case 'ojbref':
            // old mistake in tcpdf parser
            case 'objref':
                return new Element_X_Ref($value, $document);
            case '[':
                $values = [];
                if (\is_array($value)) {
                    foreach ($value as $sub_element) {
                        $sub_type = $sub_element[0];
                        $sub_value = $sub_element[1];
                        $values[] = $this->parse_header_element($sub_type, $sub_value, $document);
                    }
                }
                return new Element_Array($values, $document);
            case 'endstream':
            case 'obj':
            // I don't know what it means but got my project fixed.
            case '':
                // Nothing to do with.
                return null;
            default:
                throw new \Exception('Invalid type: "' . $type . '".');
        }
    }
}