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

use Smalot\Pdf_Parser\Element\Element_Numeric;
use Smalot\Pdf_Parser\Encoding\Encoding_Locator;
use Smalot\Pdf_Parser\Encoding\Post_Script_Glyphs;
use Smalot\Pdf_Parser\Exception\Encoding_Not_Found_Exception;
/**
 * Class Encoding
 */
class Encoding extends Pdf_Object
{
    /**
     * @var array
     */
    protected $encoding;
    /**
     * @var array
     */
    protected $differences;
    /**
     * @var array
     */
    protected $mapping;
    public function init(): void
    {
        $this->mapping = [];
        $this->differences = [];
        $this->encoding = [];
        if ($this->has('BaseEncoding')) {
            $this->encoding = Encoding_Locator::get_encoding($this->get_encoding_class())->get_translations();
            // Build table including differences.
            $differences = $this->get('Differences')->get_content();
            $code = 0;
            if (!\is_array($differences)) {
                return;
            }
            foreach ($differences as $difference) {
                /** @var ElementNumeric $difference */
                if ($difference instanceof Element_Numeric) {
                    $code = $difference->get_content();
                    continue;
                }
                // ElementName
                $this->differences[$code] = $difference;
                if (\is_object($difference)) {
                    $this->differences[$code] = $difference->get_content();
                }
                // For the next char.
                ++$code;
            }
            $this->mapping = $this->encoding;
            foreach ($this->differences as $code => $difference) {
                /* @var string $difference */
                $this->mapping[$code] = $difference;
            }
        }
    }
    public function get_details(bool $deep = true): array
    {
        $details = [];
        $details['BaseEncoding'] = $this->has('BaseEncoding') ? (string) $this->get('BaseEncoding') : 'Ansi';
        $details['Differences'] = $this->has('Differences') ? (string) $this->get('Differences') : '';
        return $details + parent::get_details($deep);
    }
    public function translate_char($dec): ?int
    {
        if (isset($this->mapping[$dec])) {
            $dec = $this->mapping[$dec];
        }
        return Post_Script_Glyphs::get_code_point($dec);
    }
    /**
     * Returns encoding class name if available or empty string (only prior PHP 7.4).
     *
     * @throws \Exception On PHP 7.4+ an exception is thrown if encoding class doesn't exist.
     */
    public function __toString(): string
    {
        try {
            return $this->get_encoding_class();
        } catch (\Exception $e) {
            // prior to PHP 7.4 toString has to return an empty string.
            if (version_compare(\PHP_VERSION, '7.4.0', '<')) {
                return '';
            }
            throw $e;
        }
    }
    /**
     * @throws EncodingNotFoundException
     */
    protected function get_encoding_class(): string
    {
        // Load reference table charset.
        $base_encoding = preg_replace('/[^A-Z0-9]/is', '', $this->get('BaseEncoding')->get_content());
        // Check for empty BaseEncoding field value
        if (!\is_string($base_encoding) || 0 == \strlen($base_encoding)) {
            $base_encoding = 'StandardEncoding';
        }
        $class_name = '\Smalot\PdfParser\Encoding\\' . $base_encoding;
        if (!class_exists($class_name)) {
            throw new Encoding_Not_Found_Exception('Missing encoding data for: "' . $base_encoding . '".');
        }
        return $class_name;
    }
}