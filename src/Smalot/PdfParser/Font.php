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

use Smalot\Pdf_Parser\Encoding\Win_Ansi_Encoding;
use Smalot\Pdf_Parser\Exception\Encoding_Not_Found_Exception;
/**
 * Class Font
 */
class Font extends Pdf_Object
{
    public const MISSING = '?';
    /**
     * @var array
     */
    protected $table;
    /**
     * @var array
     */
    protected $table_sizes;
    /**
     * Caches results from uchr.
     *
     * @var array
     */
    private static $uchr_cache = [];
    /**
     * In some PDF-files encoding could be referenced by object id but object itself does not contain
     * `/Type /Encoding` in its dictionary. These objects wouldn't be initialized as Encoding in
     * \Smalot\PdfParser\PDFObject::factory() during file parsing (they would be just PDFObject).
     *
     * Therefore, we create an instance of Encoding from them during decoding and cache this value in this property.
     *
     * @var Encoding
     *
     * @see https://github.com/smalot/pdfparser/pull/500
     */
    private $initialized_encoding_by_pdf_object;
    public function init(): void
    {
        // Load translate table.
        $this->load_translate_table();
    }
    public function get_name(): string
    {
        return $this->has('BaseFont') ? (string) $this->get('BaseFont') : '[Unknown]';
    }
    public function get_type(): string
    {
        return (string) $this->header->get('Subtype');
    }
    public function get_details(bool $deep = true): array
    {
        $details = [];
        $details['Name'] = $this->get_name();
        $details['Type'] = $this->get_type();
        $details['Encoding'] = $this->has('Encoding') ? (string) $this->get('Encoding') : 'Ansi';
        return $details + parent::get_details($deep);
    }
    /**
     * @return string|bool
     */
    public function translate_char(string $char, bool $use_default = true)
    {
        $dec = hexdec(bin2hex($char));
        if (\array_key_exists($dec, $this->table)) {
            return $this->table[$dec];
        }
        // fallback for decoding single-byte ANSI characters that are not in the lookup table
        $fallback_decoded = $char;
        if (\strlen($char) < 2 && $this->has('Encoding') && $this->get('Encoding') instanceof Encoding) {
            try {
                if (Win_Ansi_Encoding::class === $this->get('Encoding')->__toString()) {
                    $fallback_decoded = self::uchr($dec);
                }
            } catch (Encoding_Not_Found_Exception $e) {
                // Encoding->getEncodingClass() throws EncodingNotFoundException when BaseEncoding doesn't exists
                // See table 5.11 on PDF 1.5 specs for more info
            }
        }
        return $use_default ? self::MISSING : $fallback_decoded;
    }
    /**
     * Convert unicode character code to "utf-8" encoded string.
     *
     * @param int|float $code Unicode character code. Will be casted to int internally!
     */
    public static function uchr($code): string
    {
        // note:
        // $code was typed as int before, but changed in https://github.com/smalot/pdfparser/pull/623
        // because in some cases uchr was called with a float instead of an integer.
        $code = (int) $code;
        if (!isset(self::$uchr_cache[$code])) {
            // html_entity_decode() will not work with UTF-16 or UTF-32 char entities,
            // therefore, we use mb_convert_encoding() instead
            self::$uchr_cache[$code] = mb_convert_encoding("&#{$code};", 'UTF-8', 'HTML-ENTITIES');
        }
        return self::$uchr_cache[$code];
    }
    /**
     * Init internal chars translation table by ToUnicode CMap.
     */
    public function load_translate_table(): array
    {
        if (null !== $this->table) {
            return $this->table;
        }
        $this->table = [];
        $this->table_sizes = ['from' => 1, 'to' => 1];
        if ($this->has('ToUnicode')) {
            $content = $this->get('ToUnicode')->get_content();
            $matches = [];
            // Support for multiple spacerange sections
            if (preg_match_all('/begincodespacerange(?P<sections>.*?)endcodespacerange/s', $content, $matches)) {
                foreach ($matches['sections'] as $section) {
                    $regexp = '/<(?P<from>[0-9A-F]+)> *<(?P<to>[0-9A-F]+)>[ \r\n]+/is';
                    preg_match_all($regexp, $section, $matches);
                    $this->table_sizes = ['from' => max(1, \strlen(current($matches['from'])) / 2), 'to' => max(1, \strlen(current($matches['to'])) / 2)];
                    break;
                }
            }
            // Support for multiple bfchar sections
            if (preg_match_all('/beginbfchar(?P<sections>.*?)endbfchar/s', $content, $matches)) {
                foreach ($matches['sections'] as $section) {
                    $regexp = '/<(?P<from>[0-9A-F]+)> *<(?P<to>[0-9A-F]+)>[ \r\n]+/is';
                    preg_match_all($regexp, $section, $matches);
                    $this->table_sizes['from'] = max(1, \strlen(current($matches['from'])) / 2);
                    foreach ($matches['from'] as $key => $from) {
                        $parts = preg_split('/([0-9A-F]{4})/i', $matches['to'][$key], 0, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
                        $text = '';
                        foreach ($parts as $part) {
                            $text .= self::uchr(hexdec($part));
                        }
                        $this->table[hexdec($from)] = $text;
                    }
                }
            }
            // Support for multiple bfrange sections
            if (preg_match_all('/beginbfrange(?P<sections>.*?)endbfrange/s', $content, $matches)) {
                foreach ($matches['sections'] as $section) {
                    /**
                     * Regexp to capture <from>, <to>, and either <offset> or [...] items.
                     * - (?P<from>...) Source range's start
                     * - (?P<to>...)   Source range's end
                     * - (?P<dest>...) Destination range's offset or each char code
                     *                 Some PDF file has 2-byte Unicode values on new lines > added \r\n
                     */
                    $regexp = '/<(?P<from>[0-9A-F]+)> *<(?P<to>[0-9A-F]+)> *(?P<dest><[0-9A-F]+>|\[[\r\n<>0-9A-F ]+\])[ \r\n]+/is';
                    preg_match_all($regexp, $section, $matches);
                    foreach ($matches['from'] as $key => $from) {
                        $char_from = hexdec($from);
                        $char_to = hexdec($matches['to'][$key]);
                        $dest = $matches['dest'][$key];
                        if (1 === preg_match('/^<(?P<offset>[0-9A-F]+)>$/i', $dest, $offset_matches)) {
                            // Support for : <srcCode1> <srcCode2> <dstString>
                            $offset = hexdec($offset_matches['offset']);
                            for ($char = $char_from; $char <= $char_to; ++$char) {
                                $this->table[$char] = self::uchr($char - $char_from + $offset);
                            }
                        } else {
                            // Support for : <srcCode1> <srcCodeN> [<dstString1> <dstString2> ... <dstStringN>]
                            $strings = [];
                            $matched = preg_match_all('/<(?P<string>[0-9A-F]+)> */is', $dest, $strings);
                            if (false === $matched) {
                                continue;
                            }
                            if (0 === $matched) {
                                continue;
                            }
                            foreach ($strings['string'] as $position => $string) {
                                $parts = preg_split('/([0-9A-F]{4})/i', $string, 0, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
                                if (false === $parts) {
                                    continue;
                                }
                                $text = '';
                                foreach ($parts as $part) {
                                    $text .= self::uchr(hexdec($part));
                                }
                                $this->table[$char_from + $position] = $text;
                            }
                        }
                    }
                }
            }
        }
        return $this->table;
    }
    /**
     * Set custom char translation table where:
     * - key - integer character code;
     * - value - "utf-8" encoded value;
     */
    public function set_table(array $table): void
    {
        $this->table = $table;
    }
    /**
     * Calculate text width with data from header 'Widths'. If width of character is not found then character is added to missing array.
     */
    public function calculate_text_width(string $text, ?array &$missing = null): ?float
    {
        $index_map = array_flip($this->table);
        $details = $this->get_details();
        // Usually, Widths key is set in $details array, but if it isn't use an empty array instead.
        $widths = $details['Widths'] ?? [];
        /*
         * Widths array is zero indexed but table is not. We must map them based on FirstChar and LastChar
         *
         * Note: Without the change you would see warnings in PHP 8.4 because the values of FirstChar or LastChar
         *       can be null sometimes.
         */
        $width_map = array_flip(range((int) $details['FirstChar'], (int) $details['LastChar']));
        $width = null;
        $missing = [];
        $text_length = mb_strlen($text);
        for ($i = 0; $i < $text_length; ++$i) {
            $char = mb_substr($text, $i, 1);
            if (!\array_key_exists($char, $index_map) || !\array_key_exists($index_map[$char], $width_map) || !\array_key_exists($width_map[$index_map[$char]], $widths)) {
                $missing[] = $char;
                continue;
            }
            $width_index = $width_map[$index_map[$char]];
            $width += $widths[$width_index];
        }
        return $width;
    }
    /**
     * Decode hexadecimal encoded string. If $add_braces is true result value would be wrapped by parentheses.
     */
    public static function decode_hexadecimal(string $hexa, bool $add_braces = false): string
    {
        // Special shortcut for XML content.
        if (false !== stripos($hexa, '<?xml')) {
            return $hexa;
        }
        $text = '';
        $parts = preg_split('/(<[a-f0-9\s]+>)/si', $hexa, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $part) {
            if (preg_match('/^<[a-f0-9\s]+>$/si', $part)) {
                // strip whitespace
                $part = preg_replace("/\\s/", '', $part);
                $part = trim($part, '<>');
                if ($add_braces) {
                    $text .= '(';
                }
                $part = pack('H*', $part);
                $text .= $add_braces ? preg_replace('/\\\\/s', '\\\\\\', $part) : $part;
                if ($add_braces) {
                    $text .= ')';
                }
            } else {
                $text .= $part;
            }
        }
        return $text;
    }
    /**
     * Decode string with octal-decoded chunks.
     */
    public static function decode_octal(string $text): string
    {
        // Replace all double backslashes \\ with a special string
        $text = strtr($text, ['\\\\' => '[**pdfparserdblslsh**]']);
        // Now we can replace all octal codes without worrying about
        // escaped backslashes
        $text = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m): string {
            return \chr(octdec($m[1]));
        }, $text);
        // Unescape any parentheses
        $text = str_replace(['\(', '\)'], ['(', ')'], $text);
        // Replace instances of the special string with a single backslash
        return str_replace('[**pdfparserdblslsh**]', '\\', $text);
    }
    /**
     * Decode string with html entity encoded chars.
     */
    public static function decode_entities(string $text): string
    {
        return preg_replace_callback('/#([0-9a-f]{2})/i', function ($m): string {
            return \chr(hexdec($m[1]));
        }, $text);
    }
    /**
     * Check if given string is Unicode text (by BOM);
     * If true - decode to "utf-8" encoded string.
     * Otherwise - return text as is.
     *
     * @todo Rename in next major release to make the name correspond to reality (for ex. decodeIfUnicode())
     */
    public static function decode_unicode(string $text): string
    {
        if ("\xfe\xff" === substr($text, 0, 2)) {
            // Strip U+FEFF byte order marker.
            $decode = substr($text, 2);
            $text = '';
            $length = \strlen($decode);
            for ($i = 0; $i < $length; $i += 2) {
                $text .= self::uchr(hexdec(bin2hex(substr($decode, $i, 2))));
            }
        }
        return $text;
    }
    /**
     * @todo Deprecated, use $this->config->getFontSpaceLimit() instead.
     */
    protected function get_font_space_limit(): int
    {
        return $this->config->get_font_space_limit();
    }
    /**
     * Decode text by commands array.
     */
    public function decode_text(array $commands, float $font_factor = 4): string
    {
        $word_position = 0;
        $words = [];
        $font_space = $this->get_font_space_limit() * abs($font_factor) / 4;
        foreach ($commands as $command) {
            switch ($command[Pdf_Object::TYPE]) {
                case 'n':
                    $offset = (float) trim($command[Pdf_Object::COMMAND]);
                    if ($offset - (float) $font_space < 0) {
                        $word_position = \count($words);
                    }
                    continue 2;
                case '<':
                    // Decode hexadecimal.
                    $text = self::decode_hexadecimal('<' . $command[Pdf_Object::COMMAND] . '>');
                    break;
                default:
                    // Decode octal (if necessary).
                    $text = self::decode_octal($command[Pdf_Object::COMMAND]);
            }
            // replace escaped chars
            $text = str_replace(['\\\\', '\(', '\)', '\n', '\r', '\t', '\f', '\ ', '\b'], [\chr(92), \chr(40), \chr(41), \chr(10), \chr(13), \chr(9), \chr(12), \chr(32), \chr(8)], $text);
            // add content to result string
            if (isset($words[$word_position])) {
                $words[$word_position] .= $text;
            } else {
                $words[$word_position] = $text;
            }
        }
        foreach ($words as &$word) {
            $word = $this->decode_content($word);
            $word = str_replace("\t", ' ', $word);
        }
        // Remove internal "words" that are just spaces, but leave them
        // if they are at either end of the array of words. This fixes,
        // for   example,   lines   that   are   justified   to   fill
        // a whole row.
        for ($x = \count($words) - 2; $x >= 1; --$x) {
            if ('' === trim($words[$x], ' ')) {
                unset($words[$x]);
            }
        }
        $words = array_values($words);
        // Cut down on the number of unnecessary internal spaces by
        // imploding the string on the null byte, and checking if the
        // text includes extra spaces on either side. If so, merge
        // where appropriate.
        $words = implode("\x00\x00", $words);
        return str_replace([" \x00\x00 ", "\x00\x00 ", " \x00\x00", "\x00\x00"], ['  ', ' ', ' ', ' '], $words);
    }
    /**
     * Decode given $text to "utf-8" encoded string.
     *
     * @param bool $unicode This parameter is deprecated and might be removed in a future release
     */
    public function decode_content(string $text, ?bool &$unicode = null): string
    {
        // If this string begins with a UTF-16BE BOM, then decode it
        // directly as Unicode
        if ("\xfe\xff" === substr($text, 0, 2)) {
            return static::decode_unicode($text);
        }
        if ($this->has('ToUnicode')) {
            return $this->decode_content_by_to_unicode_c_map_or_descendant_fonts($text);
        }
        if ($this->has('Encoding')) {
            $result = $this->decode_content_by_encoding($text);
            if (null !== $result) {
                return $result;
            }
        }
        return $this->decode_content_by_autodetect_if_necessary($text);
    }
    /**
     * First try to decode $text by ToUnicode CMap.
     * If char translation not found in ToUnicode CMap tries:
     *  - If DescendantFonts exists tries to decode char by one of that fonts.
     *      - If have no success to decode by DescendantFonts interpret $text as a string with "Windows-1252" encoding.
     *  - If DescendantFonts does not exist just return "?" as decoded char.
     *
     * @todo Seems this is invalid algorithm that do not follow pdf-format specification. Must be rewritten.
     */
    private function decode_content_by_to_unicode_c_map_or_descendant_fonts(string $text): string
    {
        $bytes = $this->table_sizes['from'];
        if ($bytes) {
            $result = '';
            $length = \strlen($text);
            for ($i = 0; $i < $length; $i += $bytes) {
                $char = substr($text, $i, $bytes);
                if (false !== $decoded = $this->translate_char($char, false)) {
                    $char = $decoded;
                } elseif ($this->has('DescendantFonts')) {
                    if ($this->get('DescendantFonts') instanceof Pdf_Object) {
                        $fonts = $this->get('DescendantFonts')->get_header()->get_elements();
                    } else {
                        $fonts = $this->get('DescendantFonts')->get_content();
                    }
                    $decoded = false;
                    foreach ($fonts as $font) {
                        if (!$font instanceof self) {
                            continue;
                        }
                        if (false === $decoded = $font->translate_char($char, false)) {
                            continue;
                        }
                        $decoded = mb_convert_encoding($decoded, 'UTF-8', 'Windows-1252');
                        break;
                    }
                    if (false !== $decoded) {
                        $char = $decoded;
                    } else {
                        $char = mb_convert_encoding($char, 'UTF-8', 'Windows-1252');
                    }
                } else {
                    $char = self::MISSING;
                }
                $result .= $char;
            }
            $text = $result;
        }
        return $text;
    }
    /**
     * Decode content by any type of Encoding (dictionary's item) instance.
     */
    private function decode_content_by_encoding(string $text): ?string
    {
        $encoding = $this->get('Encoding');
        // When Encoding referenced by object id (/Encoding 520 0 R) but object itself does not contain `/Type /Encoding` in it's dictionary.
        if ($encoding instanceof Pdf_Object) {
            $encoding = $this->get_initialized_encoding_by_pdf_object($encoding);
        }
        // When Encoding referenced by object id (/Encoding 520 0 R) but object itself contains `/Type /Encoding` in it's dictionary.
        if ($encoding instanceof Encoding) {
            return $this->decode_content_by_encoding_encoding($text, $encoding);
        }
        // When Encoding is just string (/Encoding /WinAnsiEncoding)
        if ($encoding instanceof Element) {
            // todo: ElementString class must by used?
            return $this->decode_content_by_encoding_element($text, $encoding);
        }
        // don't double-encode strings already in UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        return $text;
    }
    /**
     * Returns already created or create a new one if not created before Encoding instance by PDFObject instance.
     */
    private function get_initialized_encoding_by_pdf_object(Pdf_Object $pdf_object): Encoding
    {
        if (!$this->initialized_encoding_by_pdf_object) {
            $this->initialized_encoding_by_pdf_object = $this->create_initialized_encoding_by_pdf_object($pdf_object);
        }
        return $this->initialized_encoding_by_pdf_object;
    }
    /**
     * Decode content when $encoding (given by $this->get('Encoding')) is instance of Encoding.
     */
    private function decode_content_by_encoding_encoding(string $text, Encoding $encoding): string
    {
        $result = '';
        $length = \strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $dec_av = hexdec(bin2hex($text[$i]));
            $dec_ap = $encoding->translate_char($dec_av);
            $result .= self::uchr($dec_ap ?? $dec_av);
        }
        return $result;
    }
    /**
     * Decode content when $encoding (given by $this->get('Encoding')) is instance of Element.
     */
    private function decode_content_by_encoding_element(string $text, Element $encoding): ?string
    {
        $pdf_encoding_name = $encoding->get_content();
        // mb_convert_encoding does not support MacRoman/macintosh,
        // so we use iconv() here
        $iconv_encoding_name = $this->get_iconv_encoding_name_or_null_by_pdf_encoding_name($pdf_encoding_name);
        return $iconv_encoding_name ? iconv($iconv_encoding_name, 'UTF-8//TRANSLIT//IGNORE', $text) : null;
    }
    /**
     * Convert PDF encoding name to iconv-known encoding name.
     */
    private function get_iconv_encoding_name_or_null_by_pdf_encoding_name(string $pdf_encoding_name): ?string
    {
        $pdf_to_iconv_encoding_name_map = ['StandardEncoding' => 'ISO-8859-1', 'MacRomanEncoding' => 'MACINTOSH', 'WinAnsiEncoding' => 'CP1252'];
        return \array_key_exists($pdf_encoding_name, $pdf_to_iconv_encoding_name_map) ? $pdf_to_iconv_encoding_name_map[$pdf_encoding_name] : null;
    }
    /**
     * If string seems like "utf-8" encoded string do nothing and just return given string as is.
     * Otherwise, interpret string as "Window-1252" encoded string.
     *
     * @return string|false
     */
    private function decode_content_by_autodetect_if_necessary(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        // todo: Why exactly `Windows-1252` used?
    }
    /**
     * Create Encoding instance by PDFObject instance and init it.
     */
    private function create_initialized_encoding_by_pdf_object(Pdf_Object $pdf_object): Encoding
    {
        $encoding = $this->create_encoding_by_pdf_object($pdf_object);
        $encoding->init();
        return $encoding;
    }
    /**
     * Create Encoding instance by PDFObject instance (without init).
     */
    private function create_encoding_by_pdf_object(Pdf_Object $pdf_object): Encoding
    {
        $document = $pdf_object->get_document();
        $header = $pdf_object->get_header();
        $content = $pdf_object->get_content();
        $config = $pdf_object->get_config();
        return new Encoding($document, $header, $content, $config);
    }
}