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
use Smalot\Pdf_Parser\Element\Element_Missing;
use Smalot\Pdf_Parser\Element\Element_Null;
use Smalot\Pdf_Parser\Element\Element_X_Ref;
class Page extends Pdf_Object
{
    /**
     * @var Font[]
     */
    protected $fonts;
    /**
     * @var PDFObject[]
     */
    protected $xobjects;
    /**
     * @var array
     */
    protected $data_tm;
    /**
     * @param array<\Smalot\PdfParser\Font> $fonts
     *
     * @internal
     */
    public function set_fonts($fonts): void
    {
        if (empty($this->fonts)) {
            $this->fonts = $fonts;
        }
    }
    /**
     * @return Font[]
     */
    public function get_fonts()
    {
        if (null !== $this->fonts) {
            return $this->fonts;
        }
        $resources = $this->get('Resources');
        if (method_exists($resources, 'has') && $resources->has('Font')) {
            if ($resources->get('Font') instanceof Element_Missing) {
                return [];
            }
            if ($resources->get('Font') instanceof Header) {
                $fonts = $resources->get('Font')->get_elements();
            } else {
                $fonts = $resources->get('Font')->get_header()->get_elements();
            }
            $table = [];
            foreach ($fonts as $id => $font) {
                if ($font instanceof Font) {
                    $table[$id] = $font;
                    // Store too on cleaned id value (only numeric)
                    $id = preg_replace('/[^0-9\.\-_]/', '', $id);
                    if ('' != $id) {
                        $table[$id] = $font;
                    }
                }
            }
            return $this->fonts = $table;
        }
        return [];
    }
    public function get_font(string $id): ?Font
    {
        $fonts = $this->get_fonts();
        if (isset($fonts[$id])) {
            return $fonts[$id];
        }
        // According to the PDF specs (https://www.adobe.com/content/dam/acom/en/devnet/pdf/pdfs/PDF32000_2008.pdf, page 238)
        // "The font resource name presented to the Tf operator is arbitrary, as are the names for all kinds of resources"
        // Instead, we search for the unfiltered name first and then do this cleaning as a fallback, so all tests still pass.
        if (isset($fonts[$id])) {
            return $fonts[$id];
        }
        $id = preg_replace('/[^0-9\.\-_]/', '', $id);
        return $fonts[$id] ?? null;
    }
    /**
     * Support for XObject
     *
     * @return PDFObject[]
     */
    public function get_x_objects()
    {
        if (null !== $this->xobjects) {
            return $this->xobjects;
        }
        $resources = $this->get('Resources');
        if (method_exists($resources, 'has') && $resources->has('XObject')) {
            if ($resources->get('XObject') instanceof Header) {
                $xobjects = $resources->get('XObject')->get_elements();
            } else {
                $xobjects = $resources->get('XObject')->get_header()->get_elements();
            }
            $table = [];
            foreach ($xobjects as $id => $xobject) {
                $table[$id] = $xobject;
                // Store too on cleaned id value (only numeric)
                $id = preg_replace('/[^0-9\.\-_]/', '', $id);
                if ('' != $id) {
                    $table[$id] = $xobject;
                }
            }
            return $this->xobjects = $table;
        }
        return [];
    }
    public function get_x_object(string $id): ?Pdf_Object
    {
        $xobjects = $this->get_x_objects();
        return $xobjects[$id] ?? null;
        /*$id = preg_replace('/[^0-9\.\-_]/', '', $id);
        
                if (isset($xobjects[$id])) {
                    return $xobjects[$id];
                } else {
                    return null;
                }*/
    }
    public function get_text(?self $page = null): string
    {
        if ($contents = $this->get('Contents')) {
            if ($contents instanceof Element_Missing) {
                return '';
            }
            if ($contents instanceof Element_Null) {
                return '';
            }
            if ($contents instanceof Pdf_Object) {
                $elements = $contents->get_header()->get_elements();
                if (is_numeric(key($elements))) {
                    $new_content = '';
                    foreach ($elements as $element) {
                        if ($element instanceof Element_X_Ref) {
                            $new_content .= $element->get_object()->get_content();
                        } else {
                            $new_content .= $element->get_content();
                        }
                    }
                    $header = new Header([], $this->document);
                    $contents = new Pdf_Object($this->document, $header, $new_content, $this->config);
                }
            } elseif ($contents instanceof Element_Array) {
                // Create a virtual global content.
                $new_content = '';
                foreach ($contents->get_content() as $content) {
                    $new_content .= $content->get_content() . "\n";
                }
                $header = new Header([], $this->document);
                $contents = new Pdf_Object($this->document, $header, $new_content, $this->config);
            }
            /*
             * Elements referencing each other on the same page can cause endless loops during text parsing.
             * To combat this we keep a recursionStack containing already parsed elements on the page.
             * The stack is only emptied here after getting text from a page.
             */
            $contents_text = $contents->get_text($this);
            Pdf_Object::$recursion_stack = [];
            return $contents_text;
        }
        return '';
    }
    /**
     * Return true if the current page is a (setasign\Fpdi\Fpdi) FPDI/FPDF document
     *
     * The metadata 'Producer' should have the value of "FPDF" . FPDF_VERSION if the
     * pdf file was generated by FPDF/Fpfi.
     *
     * @return bool true is the current page is a FPDI/FPDF document
     */
    public function is_fpdf(): bool
    {
        if (\array_key_exists('Producer', $this->document->get_details()) && \is_string($this->document->get_details()['Producer']) && 0 === strncmp($this->document->get_details()['Producer'], 'FPDF', 4)) {
            return true;
        }
        return false;
    }
    /**
     * Return the page number of the PDF document of the page object
     *
     * @return int the page number
     */
    public function get_page_number(): int
    {
        $pages = $this->document->get_pages();
        $num_of_pages = \count($pages);
        for ($page_num = 0; $page_num < $num_of_pages; ++$page_num) {
            if ($pages[$page_num] === $this) {
                break;
            }
        }
        return $page_num;
    }
    /**
     * Return the Object of the page if the document is a FPDF/FPDI document
     *
     * If the document was generated by FPDF/FPDI it returns the
     * PDFObject of the given page
     *
     * @return PDFObject The PDFObject for the page
     */
    public function get_pdf_object_for_fpdf(): Pdf_Object
    {
        $page_num = $this->get_page_number();
        $x_objects = $this->get_x_objects();
        return $x_objects[$page_num];
    }
    /**
     * Return a new PDFObject of the document created with FPDF/FPDI
     *
     * For a document generated by FPDF/FPDI, it generates a
     * new PDFObject for that document
     *
     * @return PDFObject The PDFObject
     */
    public function create_pdf_object_for_fpdf(): Pdf_Object
    {
        $pdf_object = $this->get_pdf_object_for_fpdf();
        $new_content = $pdf_object->get_content();
        $header = $pdf_object->get_header();
        $config = $pdf_object->config;
        return new Pdf_Object($pdf_object->document, $header, $new_content, $config);
    }
    /**
     * Return page if document is a FPDF/FPDI document
     *
     * @return Page The page
     */
    public function create_page_for_fpdf(): self
    {
        $pdf_object = $this->get_pdf_object_for_fpdf();
        $new_content = $pdf_object->get_content();
        $header = $pdf_object->get_header();
        $config = $pdf_object->config;
        return new self($pdf_object->document, $header, $new_content, $config);
    }
    public function get_text_array(?self $page = null): array
    {
        if ($this->is_fpdf()) {
            $pdf_object = $this->get_pdf_object_for_fpdf();
            $new_pdf_object = $this->create_pdf_object_for_fpdf();
            return $new_pdf_object->get_text_array($pdf_object);
        }
        if ($contents = $this->get('Contents')) {
            if ($contents instanceof Element_Missing) {
                return [];
            }
            if ($contents instanceof Element_Null) {
                return [];
            }
            if ($contents instanceof Pdf_Object) {
                $elements = $contents->get_header()->get_elements();
                if (is_numeric(key($elements))) {
                    $new_content = '';
                    /** @var PDFObject $element */
                    foreach ($elements as $element) {
                        if ($element instanceof Element_X_Ref) {
                            $new_content .= $element->get_object()->get_content();
                        } else {
                            $new_content .= $element->get_content();
                        }
                    }
                    $header = new Header([], $this->document);
                    $contents = new Pdf_Object($this->document, $header, $new_content, $this->config);
                } else {
                    try {
                        $contents->get_text_array($this);
                    } catch (\Throwable $e) {
                        return $contents->get_text_array();
                    }
                }
            } elseif ($contents instanceof Element_Array) {
                // Create a virtual global content.
                $new_content = '';
                /** @var PDFObject $content */
                foreach ($contents->get_content() as $content) {
                    $new_content .= $content->get_content() . "\n";
                }
                $header = new Header([], $this->document);
                $contents = new Pdf_Object($this->document, $header, $new_content, $this->config);
            }
            return $contents->get_text_array($this);
        }
        return [];
    }
    /**
     * Gets all the text data with its internal representation of the page.
     *
     * Returns an array with the data and the internal representation
     */
    public function extract_raw_data(): array
    {
        /*
         * Now you can get the complete content of the object with the text on it
         */
        $extracted_data = [];
        $content = $this->get('Contents');
        $values = $content->get_content();
        if (isset($values) && \is_array($values)) {
            $text = '';
            foreach ($values as $section) {
                $text .= $section->get_content();
            }
            $sections_text = $this->get_sections_text($text);
            foreach ($sections_text as $section_text) {
                $commands_text = $this->get_commands_text($section_text);
                foreach ($commands_text as $command) {
                    $extracted_data[] = $command;
                }
            }
        } else {
            if ($this->is_fpdf()) {
                $content = $this->get_pdf_object_for_fpdf();
            }
            $sections_text = $content->get_sections_text($content->get_content());
            foreach ($sections_text as $section_text) {
                $commands_text = $content->get_commands_text($section_text);
                foreach ($commands_text as $command) {
                    $extracted_data[] = $command;
                }
            }
        }
        return $extracted_data;
    }
    /**
     * Gets all the decoded text data with it internal representation from a page.
     *
     * @param array $extractedRawData the extracted data return by extractRawData or
     *                                null if extractRawData should be called
     *
     * @return array An array with the data and the internal representation
     */
    public function extract_decoded_raw_data(?array $extracted_raw_data = null): array
    {
        if (!isset($extracted_raw_data) || !$extracted_raw_data) {
            $extracted_raw_data = $this->extract_raw_data();
        }
        $current_font = null;
        /** @var Font $currentFont */
        $clipped_font = null;
        $fpdf_page = null;
        if ($this->is_fpdf()) {
            $fpdf_page = $this->create_page_for_fpdf();
        }
        foreach ($extracted_raw_data as &$command) {
            if ('Tj' == $command['o'] || 'TJ' == $command['o']) {
                $data = $command['c'];
                if (!\is_array($data)) {
                    $tmp_text = '';
                    if (isset($current_font)) {
                        $tmp_text = $current_font->decode_octal($data);
                        // $tmpText = $currentFont->decodeHexadecimal($tmpText, false);
                    }
                    $tmp_text = str_replace(['\\\\', '\(', '\)', '\n', '\r', '\t', '\ '], ['\\', '(', ')', "\n", "\r", "\t", ' '], $tmp_text);
                    $tmp_text = mb_convert_encoding($tmp_text, 'UTF-8', 'ISO-8859-1');
                    if (isset($current_font)) {
                        $tmp_text = $current_font->decode_content($tmp_text);
                    }
                    $command['c'] = $tmp_text;
                    continue;
                }
                $num_text = \count($data);
                for ($i = 0; $i < $num_text; ++$i) {
                    if (0 != $i % 2) {
                        continue;
                    }
                    $tmp_text = $data[$i]['c'];
                    $decoded_text = isset($current_font) ? $current_font->decode_octal($tmp_text) : $tmp_text;
                    $decoded_text = str_replace(['\\\\', '\(', '\)', '\n', '\r', '\t', '\ '], ['\\', '(', ')', "\n", "\r", "\t", ' '], $decoded_text);
                    $decoded_text = mb_convert_encoding($decoded_text, 'UTF-8', 'ISO-8859-1');
                    if (isset($current_font)) {
                        $decoded_text = $current_font->decode_content($decoded_text);
                    }
                    $command['c'][$i]['c'] = $decoded_text;
                }
            } elseif ('Tf' == $command['o'] || 'TF' == $command['o']) {
                $font_id = explode(' ', $command['c'])[0];
                // If document is a FPDI/FPDF the $page has the correct font
                $current_font = isset($fpdf_page) ? $fpdf_page->get_font($font_id) : $this->get_font($font_id);
                continue;
            } elseif ('Q' == $command['o']) {
                $current_font = $clipped_font;
            } elseif ('q' == $command['o']) {
                $clipped_font = $current_font;
            }
        }
        return $extracted_raw_data;
    }
    /**
     * Gets just the Text commands that are involved in text positions and
     * Text Matrix (Tm)
     *
     * It extract just the PDF commands that are involved with text positions, and
     * the Text Matrix (Tm). These are: BT, ET, TL, Td, TD, Tm, T*, Tj, ', ", and TJ
     *
     * @param array $extractedDecodedRawData The data extracted by extractDecodeRawData.
     *                                       If it is null, the method extractDecodeRawData is called.
     *
     * @return array An array with the text command of the page
     */
    public function get_data_commands(?array $extracted_decoded_raw_data = null): array
    {
        if (!isset($extracted_decoded_raw_data) || !$extracted_decoded_raw_data) {
            $extracted_decoded_raw_data = $this->extract_decoded_raw_data();
        }
        $extracted_data = [];
        foreach ($extracted_decoded_raw_data as $command) {
            switch ($command['o']) {
                /*
                 * BT
                 * Begin a text object, inicializind the Tm and Tlm to identity matrix
                 */
                case 'BT':
                /*
                 * cm
                 * Concatenation Matrix that will transform all following Tm
                 */
                case 'cm':
                /*
                 * ET
                 * End a text object, discarding the text matrix
                 */
                case 'ET':
                /*
                 * leading TL
                 * Set the text leading, Tl, to leading. Tl is used by the T*, ' and " operators.
                 * Initial value: 0
                 */
                case 'TL':
                /*
                 * tx ty Td
                 * Move to the start of the next line, offset form the start of the
                 * current line by tx, ty.
                 */
                case 'Td':
                /*
                 * tx ty TD
                 * Move to the start of the next line, offset form the start of the
                 * current line by tx, ty. As a side effect, this operator set the leading
                 * parameter in the text state. This operator has the same effect as the
                 * code:
                 * -ty TL
                 * tx ty Td
                 */
                case 'TD':
                /*
                 * a b c d e f Tm
                 * Set the text matrix, Tm, and the text line matrix, Tlm. The operands are
                 * all numbers, and the initial value for Tm and Tlm is the identity matrix
                 * [1 0 0 1 0 0]
                 */
                case 'Tm':
                /*
                 * T*
                 * Move to the start of the next line. This operator has the same effect
                 * as the code:
                 * 0 Tl Td
                 * Where Tl is the current leading parameter in the text state.
                 */
                case 'T*':
                /*
                 * string Tj
                 * Show a Text String
                 */
                case 'Tj':
                /*
                 * string '
                 * Move to the next line and show a text string. This operator has the
                 * same effect as the code:
                 * T*
                 * string Tj
                 */
                case "'":
                /*
                 * aw ac string "
                 * Move to the next lkine and show a text string, using aw as the word
                 * spacing and ac as the character spacing. This operator has the same
                 * effect as the code:
                 * aw Tw
                 * ac Tc
                 * string '
                 * Tw set the word spacing, Tw, to wordSpace.
                 * Tc Set the character spacing, Tc, to charsSpace.
                 */
                case '"':
                case 'Tf':
                case 'TF':
                /*
                 * array TJ
                 * Show one or more text strings allow individual glyph positioning.
                 * Each lement of array con be a string or a number. If the element is
                 * a string, this operator shows the string. If it is a number, the
                 * operator adjust the text position by that amount; that is, it translates
                 * the text matrix, Tm. This amount is substracted form the current
                 * horizontal or vertical coordinate, depending on the writing mode.
                 * in the default coordinate system, a positive adjustment has the effect
                 * of moving the next glyph painted either to the left or down by the given
                 * amount.
                 */
                case 'TJ':
                /*
                 * q
                 * Save current graphics state to stack
                 */
                case 'q':
                /*
                 * Q
                 * Load last saved graphics state from stack
                 */
                case 'Q':
                    $extracted_data[] = $command;
                    break;
                default:
            }
        }
        return $extracted_data;
    }
    /**
     * Gets the Text Matrix of the text in the page
     *
     * Return an array where every item is an array where the first item is the
     * Text Matrix (Tm) and the second is a string with the text data.  The Text matrix
     * is an array of 6 numbers. The last 2 numbers are the coordinates X and Y of the
     * text. The first 4 numbers has to be with Scalation, Rotation and Skew of the text.
     *
     * @param array $dataCommands the data extracted by getDataCommands
     *                            if null getDataCommands is called
     *
     * @return array an array with the data of the page including the Tm information
     *               of any text in the page
     */
    public function get_data_tm(?array $data_commands = null): array
    {
        if (!isset($data_commands) || !$data_commands) {
            $data_commands = $this->get_data_commands();
        }
        /*
         * At the beginning of a text object Tm is the identity matrix
         */
        $default_tm = ['1', '0', '0', '1', '0', '0'];
        $concat_tm = ['1', '0', '0', '1', '0', '0'];
        $graphics_states_stack = [];
        /*
         *  Set the text leading used by T*, ' and " operators
         */
        $default_tl = 0;
        /*
         *  Set default values for font data
         */
        $default_font_id = -1;
        $default_font_size = 1;
        /*
         * Indexes of horizontal/vertical scaling and X,Y-coordinates in the matrix (Tm)
         */
        $h_sc = 0;
        // horizontal scaling
        /**
         * index of vertical scaling in the array that encodes the text matrix.
         * for more information: https://github.com/smalot/pdfparser/pull/559#discussion_r1053415500
         */
        $v_sc = 3;
        $x = 4;
        $y = 5;
        /*
         * x,y-coordinates of text space origin in user units
         *
         * These will be assigned the value of the currently printed string
         */
        $Tx = 0;
        $Ty = 0;
        $Tm = $default_tm;
        $Tl = $default_tl;
        $font_id = $default_font_id;
        $font_size = $default_font_size;
        // reflects fontSize set by Tf or Tfs
        $extracted_texts = $this->get_text_array();
        $extracted_data = [];
        foreach ($data_commands as $command) {
            // If we've used up all the texts from getTextArray(), exit
            // so we aren't accessing non-existent array indices
            // Fixes 'undefined array key' errors in Issues #575, #576
            if (\count($extracted_texts) <= \count($extracted_data)) {
                break;
            }
            $current_text = $extracted_texts[\count($extracted_data)];
            switch ($command['o']) {
                /*
                 * BT
                 * Begin a text object, initializing the Tm and Tlm to identity matrix
                 */
                case 'BT':
                    $Tm = $default_tm;
                    $Tl = $default_tl;
                    $Tx = 0;
                    $Ty = 0;
                    break;
                case 'cm':
                    $new_concat_tm = explode(' ', $command['c']);
                    $temp_matrix = [];
                    // Multiply with previous concatTm
                    $temp_matrix[0] = (float) $concat_tm[0] * (float) $new_concat_tm[0] + (float) $concat_tm[1] * (float) $new_concat_tm[2];
                    $temp_matrix[1] = (float) $concat_tm[0] * (float) $new_concat_tm[1] + (float) $concat_tm[1] * (float) $new_concat_tm[3];
                    $temp_matrix[2] = (float) $concat_tm[2] * (float) $new_concat_tm[0] + (float) $concat_tm[3] * (float) $new_concat_tm[2];
                    $temp_matrix[3] = (float) $concat_tm[2] * (float) $new_concat_tm[1] + (float) $concat_tm[3] * (float) $new_concat_tm[3];
                    $temp_matrix[4] = (float) $concat_tm[4] * (float) $new_concat_tm[0] + (float) $concat_tm[5] * (float) $new_concat_tm[2] + (float) $new_concat_tm[4];
                    $temp_matrix[5] = (float) $concat_tm[4] * (float) $new_concat_tm[1] + (float) $concat_tm[5] * (float) $new_concat_tm[3] + (float) $new_concat_tm[5];
                    $concat_tm = $temp_matrix;
                    break;
                /*
                 * ET
                 * End a text object
                 */
                case 'ET':
                    break;
                /*
                 * text leading TL
                 * Set the text leading, Tl, to leading. Tl is used by the T*, ' and " operators.
                 * Initial value: 0
                 */
                case 'TL':
                    // scaled text leading
                    $Tl = (float) $command['c'] * (float) $Tm[$v_sc];
                    break;
                /*
                 * tx ty Td
                 * Move to the start of the next line, offset from the start of the
                 * current line by tx, ty.
                 */
                case 'Td':
                    $coord = explode(' ', $command['c']);
                    $Tx += (float) $coord[0] * (float) $Tm[$h_sc];
                    $Ty += (float) $coord[1] * (float) $Tm[$v_sc];
                    $Tm[$x] = (string) $Tx;
                    $Tm[$y] = (string) $Ty;
                    break;
                /*
                 * tx ty TD
                 * Move to the start of the next line, offset form the start of the
                 * current line by tx, ty. As a side effect, this operator set the leading
                 * parameter in the text state. This operator has the same effect as the
                 * code:
                 * -ty TL
                 * tx ty Td
                 */
                case 'TD':
                    $coord = explode(' ', $command['c']);
                    $Tl = -((float) $coord[1] * (float) $Tm[$v_sc]);
                    $Tx += (float) $coord[0] * (float) $Tm[$h_sc];
                    $Ty += (float) $coord[1] * (float) $Tm[$v_sc];
                    $Tm[$x] = (string) $Tx;
                    $Tm[$y] = (string) $Ty;
                    break;
                /*
                 * a b c d e f Tm
                 * Set the text matrix, Tm, and the text line matrix, Tlm. The operands are
                 * all numbers, and the initial value for Tm and Tlm is the identity matrix
                 * [1 0 0 1 0 0]
                 */
                case 'Tm':
                    $Tm = explode(' ', $command['c']);
                    $temp_matrix = [];
                    $temp_matrix[0] = (float) $Tm[0] * (float) $concat_tm[0] + (float) $Tm[1] * (float) $concat_tm[2];
                    $temp_matrix[1] = (float) $Tm[0] * (float) $concat_tm[1] + (float) $Tm[1] * (float) $concat_tm[3];
                    $temp_matrix[2] = (float) $Tm[2] * (float) $concat_tm[0] + (float) $Tm[3] * (float) $concat_tm[2];
                    $temp_matrix[3] = (float) $Tm[2] * (float) $concat_tm[1] + (float) $Tm[3] * (float) $concat_tm[3];
                    $temp_matrix[4] = (float) $Tm[4] * (float) $concat_tm[0] + (float) $Tm[5] * (float) $concat_tm[2] + (float) $concat_tm[4];
                    $temp_matrix[5] = (float) $Tm[4] * (float) $concat_tm[1] + (float) $Tm[5] * (float) $concat_tm[3] + (float) $concat_tm[5];
                    $Tm = $temp_matrix;
                    $Tx = $Tm[$x];
                    $Ty = $Tm[$y];
                    break;
                /*
                 * T*
                 * Move to the start of the next line. This operator has the same effect
                 * as the code:
                 * 0 Tl Td
                 * Where Tl is the current leading parameter in the text state.
                 */
                case 'T*':
                    $Ty -= $Tl;
                    $Tm[$y] = (string) $Ty;
                    break;
                /*
                 * string Tj
                 * Show a Text String
                 */
                case 'Tj':
                /*
                 * array TJ
                 * Show one or more text strings allow individual glyph positioning.
                 * Each lement of array con be a string or a number. If the element is
                 * a string, this operator shows the string. If it is a number, the
                 * operator adjust the text position by that amount; that is, it translates
                 * the text matrix, Tm. This amount is substracted form the current
                 * horizontal or vertical coordinate, depending on the writing mode.
                 * in the default coordinate system, a positive adjustment has the effect
                 * of moving the next glyph painted either to the left or down by the given
                 * amount.
                 */
                case 'TJ':
                    $data = [$Tm, $current_text];
                    if ($this->config->get_data_tm_font_info_has_to_be_included()) {
                        $data[] = $font_id;
                        $data[] = $font_size;
                    }
                    $extracted_data[] = $data;
                    break;
                /*
                 * string '
                 * Move to the next line and show a text string. This operator has the
                 * same effect as the code:
                 * T*
                 * string Tj
                 */
                case "'":
                    $Ty -= $Tl;
                    $Tm[$y] = (string) $Ty;
                    $extracted_data[] = [$Tm, $current_text];
                    break;
                /*
                 * aw ac string "
                 * Move to the next line and show a text string, using aw as the word
                 * spacing and ac as the character spacing. This operator has the same
                 * effect as the code:
                 * aw Tw
                 * ac Tc
                 * string '
                 * Tw set the word spacing, Tw, to wordSpace.
                 * Tc Set the character spacing, Tc, to charsSpace.
                 */
                case '"':
                    $data = explode(' ', $current_text);
                    $Ty -= $Tl;
                    $Tm[$y] = (string) $Ty;
                    $extracted_data[] = [$Tm, $data[2]];
                    // Verify
                    break;
                case 'Tf':
                    /*
                     * From PDF 1.0 specification, page 106:
                     *     fontname size Tf Set font and size
                     *     Sets the text font and text size in the graphics state. There is no default value for
                     *     either fontname or size; they must be selected using Tf before drawing any text.
                     *     fontname is a resource name. size is a number expressed in text space units.
                     *
                     * Source: https://ia902503.us.archive.org/10/items/pdfy-0vt8s-egqFwDl7L2/PDF%20Reference%201.0.pdf
                     * Introduced with https://github.com/smalot/pdfparser/pull/516
                     */
                    [$font_id, $font_size] = explode(' ', $command['c'], 2);
                    break;
                /*
                 * q
                 * Save current graphics state to stack
                 */
                case 'q':
                    $graphics_states_stack[] = $concat_tm;
                    break;
                /*
                 * Q
                 * Load last saved graphics state from stack
                 */
                case 'Q':
                    $concat_tm = array_pop($graphics_states_stack);
                    break;
                default:
            }
        }
        $this->data_tm = $extracted_data;
        return $extracted_data;
    }
    /**
     * Gets text data that are around the given coordinates (X,Y)
     *
     * If the text is in near the given coordinates (X,Y) (or the TM info),
     * the text is returned.  The extractedData return by getDataTm, could be use to see
     * where is the coordinates of a given text, using the TM info for it.
     *
     * @param float $x      The X value of the coordinate to search for. if null
     *                      just the Y value is considered (same Row)
     * @param float $y      The Y value of the coordinate to search for
     *                      just the X value is considered (same column)
     * @param float $xError The value less or more to consider an X to be "near"
     * @param float $yError The value less or more to consider an Y to be "near"
     *
     * @return array An array of text that are near the given coordinates. If no text
     *               "near" the x,y coordinate, an empty array is returned. If Both, x
     *               and y coordinates are null, null is returned.
     */
    public function get_text_xy(?float $x = null, ?float $y = null, float $x_error = 0, float $y_error = 0): array
    {
        if (!isset($this->data_tm) || !$this->data_tm) {
            $this->get_data_tm();
        }
        if (null === $x && null === $y) {
            return [];
        }
        $extracted_data = [];
        foreach ($this->data_tm as $item) {
            $tm = $item[0];
            $x_tm = (float) $tm[4];
            $y_tm = (float) $tm[5];
            $text = $item[1];
            if (null === $y) {
                if ($x_tm >= $x - $x_error && $x_tm <= $x + $x_error) {
                    $extracted_data[] = [$tm, $text];
                    continue;
                }
            }
            if (null === $x) {
                if ($y_tm >= $y - $y_error && $y_tm <= $y + $y_error) {
                    $extracted_data[] = [$tm, $text];
                    continue;
                }
            }
            if ($x_tm >= $x - $x_error && $x_tm <= $x + $x_error && $y_tm >= $y - $y_error && $y_tm <= $y + $y_error) {
                $extracted_data[] = [$tm, $text];
                continue;
            }
        }
        return $extracted_data;
    }
}