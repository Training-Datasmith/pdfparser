<?php

declare (strict_types=1);
/**
 * @file
 *          This file is part of the PdfParser library.
 *
 * @author  Konrad Abicht <hi@inspirito.de>
 *
 * @date    2020-11-22
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

/**
 * This class contains configurations used in various classes. You can override them
 * manually, in case default values aren't working.
 *
 * @see https://github.com/smalot/pdfparser/issues/305
 */
class Config
{
    private $font_space_limit = -50;
    /**
     * @var string
     */
    private $horizontal_offset = ' ';
    /**
     * Represents: (NUL, HT, LF, FF, CR, SP)
     *
     * @var string
     */
    private $pdf_whitespaces = "\x00\t\n\f\r ";
    /**
     * Represents: (NUL, HT, LF, FF, CR, SP)
     *
     * @var string
     */
    private $pdf_whitespaces_regex = '[\0\t\n\f\r ]';
    /**
     * Whether to retain raw image data as content or discard it to save memory
     *
     * @var bool
     */
    private $retain_image_content = true;
    /**
     * Memory limit to use when de-compressing files, in bytes.
     *
     * @var int
     */
    private $decode_memory_limit = 0;
    /**
     * Whether to include font id and size in dataTm array
     *
     * @var bool
     */
    private $data_tm_font_info_has_to_be_included = false;
    /**
     * Whether to attempt to read PDFs even if they are marked as encrypted.
     *
     * @var bool
     */
    private $ignore_encryption = false;
    public function get_font_space_limit()
    {
        return $this->font_space_limit;
    }
    public function set_font_space_limit($value): void
    {
        $this->font_space_limit = $value;
    }
    public function get_horizontal_offset(): string
    {
        return $this->horizontal_offset;
    }
    public function set_horizontal_offset($value): void
    {
        $this->horizontal_offset = $value;
    }
    public function get_pdf_whitespaces(): string
    {
        return $this->pdf_whitespaces;
    }
    public function set_pdf_whitespaces(string $pdf_whitespaces): void
    {
        $this->pdf_whitespaces = $pdf_whitespaces;
    }
    public function get_pdf_whitespaces_regex(): string
    {
        return $this->pdf_whitespaces_regex;
    }
    public function set_pdf_whitespaces_regex(string $pdf_whitespaces_regex): void
    {
        $this->pdf_whitespaces_regex = $pdf_whitespaces_regex;
    }
    public function get_retain_image_content(): bool
    {
        return $this->retain_image_content;
    }
    public function set_retain_image_content(bool $retain_image_content): void
    {
        $this->retain_image_content = $retain_image_content;
    }
    public function get_decode_memory_limit(): int
    {
        return $this->decode_memory_limit;
    }
    public function set_decode_memory_limit(int $decode_memory_limit): void
    {
        $this->decode_memory_limit = $decode_memory_limit;
    }
    public function get_data_tm_font_info_has_to_be_included(): bool
    {
        return $this->data_tm_font_info_has_to_be_included;
    }
    public function set_data_tm_font_info_has_to_be_included(bool $data_tm_font_info_has_to_be_included): void
    {
        $this->data_tm_font_info_has_to_be_included = $data_tm_font_info_has_to_be_included;
    }
    public function get_ignore_encryption(): bool
    {
        return $this->ignore_encryption;
    }
    /**
     * @deprecated this is a temporary workaround, don't rely on it
     * @see https://github.com/smalot/pdfparser/pull/653
     */
    public function set_ignore_encryption(bool $ignore_encryption): void
    {
        $this->ignore_encryption = $ignore_encryption;
    }
}