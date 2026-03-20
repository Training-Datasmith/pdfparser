<?php

declare (strict_types=1);
/**
 * This file is based on code of tecnickcom/TCPDF PDF library.
 *
 * Original author Nicola Asuni (info@tecnick.com) and
 * contributors (https://github.com/tecnickcom/TCPDF/graphs/contributors).
 *
 * @see https://github.com/tecnickcom/TCPDF
 *
 * Original code was licensed on the terms of the LGPL v3.
 *
 * ------------------------------------------------------------------------------
 *
 * @file This file is part of the PdfParser library.
 *
 * @author  Konrad Abicht <k.abicht@gmail.com>
 *
 * @date    2020-01-06
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
namespace Smalot\Pdf_Parser\Raw_Data;

use Smalot\Pdf_Parser\Config;
use Smalot\Pdf_Parser\Exception\Empty_Pdf_Exception;
use Smalot\Pdf_Parser\Exception\Missing_Pdf_Header_Exception;
class Raw_Data_Parser
{
    /**
     * @var Config
     */
    private $config;
    /**
     * Configuration array.
     *
     * @var array<string,bool>
     */
    protected $cfg = [
        // if `true` ignore filter decoding errors
        'ignore_filter_decoding_errors' => true,
        // if `true` ignore missing filter decoding errors
        'ignore_missing_filter_decoders' => true,
    ];
    protected $filter_helper;
    protected $objects;
    /**
     * @param array $cfg Configuration array, default is []
     */
    public function __construct($cfg = [], ?Config $config = null)
    {
        // merge given array with default values
        $this->cfg = array_merge($this->cfg, $cfg);
        $this->filter_helper = new Filter_Helper();
        $this->config = $config ?: new Config();
    }
    /**
     * Decode the specified stream.
     *
     * @param string $pdfData PDF data
     * @param array  $sdic    Stream's dictionary array
     * @param string $stream  Stream to decode
     *
     * @return array containing decoded stream data and remaining filters
     *
     * @throws \Exception
     */
    protected function decode_stream(string $pdf_data, array $xref, array $sdic, string $stream): array
    {
        // get stream length and filters
        $slength = \strlen($stream);
        if ($slength <= 0) {
            return ['', []];
        }
        $filters = [];
        foreach ($sdic as $k => $v) {
            if ('/' == $v[0]) {
                if ('Length' == $v[1] && isset($sdic[$k + 1]) && 'numeric' == $sdic[$k + 1][0]) {
                    // get declared stream length
                    $declength = (int) $sdic[$k + 1][1];
                    if ($declength < $slength) {
                        $stream = substr($stream, 0, $declength);
                        $slength = $declength;
                    }
                } elseif ('Filter' == $v[1] && isset($sdic[$k + 1])) {
                    // resolve indirect object
                    $objval = $this->get_object_val($pdf_data, $xref, $sdic[$k + 1]);
                    if ('/' == $objval[0]) {
                        // single filter
                        $filters[] = $objval[1];
                    } elseif ('[' == $objval[0]) {
                        // array of filters
                        foreach ($objval[1] as $flt) {
                            if ('/' == $flt[0]) {
                                $filters[] = $flt[1];
                            }
                        }
                    }
                }
            }
        }
        // decode the stream
        $remaining_filters = [];
        foreach ($filters as $filter) {
            if (\in_array($filter, $this->filter_helper->get_available_filters(), true)) {
                try {
                    $stream = $this->filter_helper->decode_filter($filter, $stream, $this->config->get_decode_memory_limit());
                } catch (\Exception $e) {
                    $emsg = $e->get_message();
                    if ('~' == $emsg[0] && !$this->cfg['ignore_missing_filter_decoders'] || '~' != $emsg[0] && !$this->cfg['ignore_filter_decoding_errors']) {
                        throw new \Exception($e->get_message());
                    }
                }
            } else {
                // add missing filter to array
                $remaining_filters[] = $filter;
            }
        }
        return [$stream, $remaining_filters];
    }
    /**
     * Decode the Cross-Reference section
     *
     * @param string     $pdfData        PDF data
     * @param int        $startxref      Offset at which the xref section starts (position of the 'xref' keyword)
     * @param array      $xref           Previous xref array (if any)
     * @param array<int> $visitedOffsets Array of visited offsets to prevent infinite loops
     *
     * @return array containing xref and trailer data
     *
     * @throws \Exception
     */
    protected function decode_xref(string $pdf_data, int $startxref, array $xref = [], array $visited_offsets = []): array
    {
        $startxref += 4;
        // 4 is the length of the word 'xref'
        // skip initial white space chars
        $offset = $startxref + strspn($pdf_data, $this->config->get_pdf_whitespaces(), $startxref);
        // initialize object number
        $obj_num = 0;
        // search for cross-reference entries or subsection
        while (preg_match('/([0-9]+)[\x20]([0-9]+)[\x20]?([nf]?)(\r\n|[\x20]?[\r\n])/', $pdf_data, $matches, \PREG_OFFSET_CAPTURE, $offset) > 0) {
            if ($matches[0][1] != $offset) {
                // we are on another section
                break;
            }
            $offset += \strlen($matches[0][0]);
            if ('n' == $matches[3][0]) {
                // create unique object index: [object number]_[generation number]
                $index = $obj_num . '_' . (int) $matches[2][0];
                // check if object already exist
                if (!isset($xref['xref'][$index])) {
                    // store object offset position
                    $xref['xref'][$index] = (int) $matches[1][0];
                }
                ++$obj_num;
            } elseif ('f' == $matches[3][0]) {
                ++$obj_num;
            } else {
                // object number (index)
                $obj_num = (int) $matches[1][0];
            }
        }
        // get trailer data
        if (preg_match('/trailer[\s]*<<(.*)>>/isU', $pdf_data, $matches, \PREG_OFFSET_CAPTURE, $offset) > 0) {
            $trailer_data = $matches[1][0];
            if (!isset($xref['trailer']) || empty($xref['trailer'])) {
                // get only the last updated version
                $xref['trailer'] = [];
                // parse trailer_data
                if (preg_match('/Size[\s]+([0-9]+)/i', $trailer_data, $matches) > 0) {
                    $xref['trailer']['size'] = (int) $matches[1];
                }
                if (preg_match('/Root[\s]+([0-9]+)[\s]+([0-9]+)[\s]+R/i', $trailer_data, $matches) > 0) {
                    $xref['trailer']['root'] = (int) $matches[1] . '_' . (int) $matches[2];
                }
                if (preg_match('/Encrypt[\s]+([0-9]+)[\s]+([0-9]+)[\s]+R/i', $trailer_data, $matches) > 0) {
                    $xref['trailer']['encrypt'] = (int) $matches[1] . '_' . (int) $matches[2];
                }
                if (preg_match('/Info[\s]+([0-9]+)[\s]+([0-9]+)[\s]+R/i', $trailer_data, $matches) > 0) {
                    $xref['trailer']['info'] = (int) $matches[1] . '_' . (int) $matches[2];
                }
                if (preg_match('/ID[\s]*[\[][\s]*[<]([^>]*)[>][\s]*[<]([^>]*)[>]/i', $trailer_data, $matches) > 0) {
                    $xref['trailer']['id'] = [];
                    $xref['trailer']['id'][0] = $matches[1];
                    $xref['trailer']['id'][1] = $matches[2];
                }
            }
            if (preg_match('/Prev[\s]+([0-9]+)/i', $trailer_data, $matches) > 0) {
                $offset = (int) $matches[1];
                if (0 != $offset) {
                    // get previous xref
                    $xref = $this->get_xref_data($pdf_data, $offset, $xref, $visited_offsets);
                }
            }
        } else {
            throw new \Exception('Unable to find trailer');
        }
        return $xref;
    }
    /**
     * Decode the Cross-Reference Stream section
     *
     * @param string     $pdfData        PDF data
     * @param int        $startxref      Offset at which the xref section starts
     * @param array      $xref           Previous xref array (if any)
     * @param array<int> $visitedOffsets Array of visited offsets to prevent infinite loops
     *
     * @return array containing xref and trailer data
     *
     * @throws \Exception if unknown PNG predictor detected
     */
    protected function decode_xref_stream(string $pdf_data, int $startxref, array $xref = [], array $visited_offsets = []): array
    {
        // try to read Cross-Reference Stream
        $xrefobj = $this->get_raw_object($pdf_data, $startxref);
        $xrefcrs = $this->get_indirect_object($pdf_data, $xref, $xrefobj[1], $startxref, true);
        if (!isset($xref['trailer']) || empty($xref['trailer'])) {
            // get only the last updated version
            $xref['trailer'] = [];
            $filltrailer = true;
        } else {
            $filltrailer = false;
        }
        if (!isset($xref['xref'])) {
            $xref['xref'] = [];
        }
        $valid_crs = false;
        $columns = 0;
        $predictor = null;
        $sarr = $xrefcrs[0][1];
        if (!\is_array($sarr)) {
            $sarr = [];
        }
        $wb = [];
        foreach ($sarr as $k => $v) {
            if ('/' == $v[0] && 'Type' == $v[1] && (isset($sarr[$k + 1]) && '/' == $sarr[$k + 1][0] && 'XRef' == $sarr[$k + 1][1])) {
                $valid_crs = true;
            } elseif ('/' == $v[0] && 'Index' == $v[1] && isset($sarr[$k + 1])) {
                // initialize list for: first object number in the subsection / number of objects
                $index_blocks = [];
                for ($m = 0; $m < \count($sarr[$k + 1][1]); $m += 2) {
                    $index_blocks[] = [$sarr[$k + 1][1][$m][1], $sarr[$k + 1][1][$m + 1][1]];
                }
            } elseif ('/' == $v[0] && 'Prev' == $v[1] && (isset($sarr[$k + 1]) && 'numeric' == $sarr[$k + 1][0])) {
                // get previous xref offset
                $prevxref = (int) $sarr[$k + 1][1];
            } elseif ('/' == $v[0] && 'W' == $v[1] && isset($sarr[$k + 1])) {
                // number of bytes (in the decoded stream) of the corresponding field
                $wb[0] = (int) $sarr[$k + 1][1][0][1];
                $wb[1] = (int) $sarr[$k + 1][1][1][1];
                $wb[2] = (int) $sarr[$k + 1][1][2][1];
            } elseif ('/' == $v[0] && 'DecodeParms' == $v[1] && isset($sarr[$k + 1][1])) {
                $decpar = $sarr[$k + 1][1];
                foreach ($decpar as $kdc => $vdc) {
                    if ('/' == $vdc[0] && 'Columns' == $vdc[1] && (isset($decpar[$kdc + 1]) && 'numeric' == $decpar[$kdc + 1][0])) {
                        $columns = (int) $decpar[$kdc + 1][1];
                    } elseif ('/' == $vdc[0] && 'Predictor' == $vdc[1] && (isset($decpar[$kdc + 1]) && 'numeric' == $decpar[$kdc + 1][0])) {
                        $predictor = (int) $decpar[$kdc + 1][1];
                    }
                }
            } elseif ($filltrailer) {
                if ('/' == $v[0] && 'Size' == $v[1] && (isset($sarr[$k + 1]) && 'numeric' == $sarr[$k + 1][0])) {
                    $xref['trailer']['size'] = $sarr[$k + 1][1];
                } elseif ('/' == $v[0] && 'Root' == $v[1] && (isset($sarr[$k + 1]) && 'objref' == $sarr[$k + 1][0])) {
                    $xref['trailer']['root'] = $sarr[$k + 1][1];
                } elseif ('/' == $v[0] && 'Info' == $v[1] && (isset($sarr[$k + 1]) && 'objref' == $sarr[$k + 1][0])) {
                    $xref['trailer']['info'] = $sarr[$k + 1][1];
                } elseif ('/' == $v[0] && 'Encrypt' == $v[1] && (isset($sarr[$k + 1]) && 'objref' == $sarr[$k + 1][0])) {
                    $xref['trailer']['encrypt'] = $sarr[$k + 1][1];
                } elseif ('/' == $v[0] && 'ID' == $v[1] && isset($sarr[$k + 1])) {
                    $xref['trailer']['id'] = [];
                    $xref['trailer']['id'][0] = $sarr[$k + 1][1][0][1];
                    $xref['trailer']['id'][1] = $sarr[$k + 1][1][1][1];
                }
            }
        }
        // decode data
        if ($valid_crs && isset($xrefcrs[1][3][0])) {
            if (null !== $predictor) {
                // number of bytes in a row
                $rowlen = $columns + 1;
                // convert the stream into an array of integers
                /** @var array<int> */
                $sdata = unpack('C*', $xrefcrs[1][3][0]);
                // TODO: Handle the case when unpack returns false
                // split the rows
                $sdata = array_chunk($sdata, $rowlen);
                // initialize decoded array
                $ddata = [];
                // initialize first row with zeros
                $prev_row = array_fill(0, $rowlen, 0);
                // for each row apply PNG unpredictor
                foreach ($sdata as $k => $row) {
                    // initialize new row
                    $ddata[$k] = [];
                    // get PNG predictor value
                    $predictor = 10 + $row[0];
                    // for each byte on the row
                    for ($i = 1; $i <= $columns; ++$i) {
                        // new index
                        $j = $i - 1;
                        $row_up = $prev_row[$j];
                        if (1 == $i) {
                            $row_left = 0;
                            $row_upleft = 0;
                        } else {
                            $row_left = $row[$i - 1];
                            $row_upleft = $prev_row[$j - 1];
                        }
                        switch ($predictor) {
                            case 10:
                                // PNG prediction (on encoding, PNG None on all rows)
                                $ddata[$k][$j] = $row[$i];
                                break;
                            case 11:
                                // PNG prediction (on encoding, PNG Sub on all rows)
                                $ddata[$k][$j] = $row[$i] + $row_left & 0xff;
                                break;
                            case 12:
                                // PNG prediction (on encoding, PNG Up on all rows)
                                $ddata[$k][$j] = $row[$i] + $row_up & 0xff;
                                break;
                            case 13:
                                // PNG prediction (on encoding, PNG Average on all rows)
                                $ddata[$k][$j] = $row[$i] + ($row_left + $row_up) / 2 & 0xff;
                                break;
                            case 14:
                                // PNG prediction (on encoding, PNG Paeth on all rows)
                                // initial estimate
                                $p = $row_left + $row_up - $row_upleft;
                                // distances
                                $pa = abs($p - $row_left);
                                $pb = abs($p - $row_up);
                                $pc = abs($p - $row_upleft);
                                $pmin = min($pa, $pb, $pc);
                                // return minimum distance
                                switch ($pmin) {
                                    case $pa:
                                        $ddata[$k][$j] = $row[$i] + $row_left & 0xff;
                                        break;
                                    case $pb:
                                        $ddata[$k][$j] = $row[$i] + $row_up & 0xff;
                                        break;
                                    case $pc:
                                        $ddata[$k][$j] = $row[$i] + $row_upleft & 0xff;
                                        break;
                                }
                                break;
                            default:
                                // PNG prediction (on encoding, PNG optimum)
                                throw new \Exception('Unknown PNG predictor: ' . $predictor);
                        }
                    }
                    $prev_row = $ddata[$k];
                }
                // end for each row
                // complete decoding
            } else {
                // number of bytes in a row
                $rowlen = array_sum($wb);
                if (0 < $rowlen) {
                    // convert the stream into an array of integers
                    $sdata = unpack('C*', $xrefcrs[1][3][0]);
                    // split the rows
                    $ddata = array_chunk($sdata, $rowlen);
                } else {
                    // if the row length is zero, $ddata should be an empty array as well
                    $ddata = [];
                }
            }
            $sdata = [];
            // for every row
            foreach ($ddata as $k => $row) {
                // initialize new row
                $sdata[$k] = [0, 0, 0];
                if (0 == $wb[0]) {
                    // default type field
                    $sdata[$k][0] = 1;
                }
                $i = 0;
                // count bytes in the row
                // for every column
                for ($c = 0; $c < 3; ++$c) {
                    // for every byte on the column
                    for ($b = 0; $b < $wb[$c]; ++$b) {
                        if (isset($row[$i])) {
                            $sdata[$k][$c] += $row[$i] << ($wb[$c] - 1 - $b) * 8;
                        }
                        ++$i;
                    }
                }
            }
            // fill xref
            if (isset($index_blocks)) {
                // load the first object number of the first /Index entry
                $obj_num = $index_blocks[0][0];
            } else {
                $obj_num = 0;
            }
            foreach ($sdata as $row) {
                switch ($row[0]) {
                    case 0:
                        // (f) linked list of free objects
                        break;
                    case 1:
                        // (n) objects that are in use but are not compressed
                        // create unique object index: [object number]_[generation number]
                        $index = $obj_num . '_' . $row[2];
                        // check if object already exist
                        if (!isset($xref['xref'][$index])) {
                            // store object offset position
                            $xref['xref'][$index] = $row[1];
                        }
                        break;
                    case 2:
                        // compressed objects
                        // $row[1] = object number of the object stream in which this object is stored
                        // $row[2] = index of this object within the object stream
                        $index = $row[1] . '_0_' . $row[2];
                        $xref['xref'][$index] = -1;
                        break;
                    default:
                        // null objects
                        break;
                }
                ++$obj_num;
                if (isset($index_blocks)) {
                    // reduce the number of remaining objects
                    --$index_blocks[0][1];
                    if (0 == $index_blocks[0][1]) {
                        // remove the actual used /Index entry
                        array_shift($index_blocks);
                        if (0 < \count($index_blocks)) {
                            // load the first object number of the following /Index entry
                            $obj_num = $index_blocks[0][0];
                        } else {
                            // if there are no more entries, remove $index_blocks to avoid actions on an empty array
                            unset($index_blocks);
                        }
                    }
                }
            }
        }
        // end decoding data
        if (isset($prevxref)) {
            // get previous xref
            return $this->get_xref_data($pdf_data, $prevxref, $xref, $visited_offsets);
        }
        return $xref;
    }
    protected function get_object_header_pattern(array $obj_refs): string
    {
        // consider all whitespace character (PDF specifications)
        return '/' . $obj_refs[0] . $this->config->get_pdf_whitespaces_regex() . $obj_refs[1] . $this->config->get_pdf_whitespaces_regex() . 'obj/';
    }
    protected function get_object_header_len(array $obj_refs): int
    {
        // "4 0 obj"
        // 2 whitespaces + strlen("obj") = 5
        return 5 + \strlen($obj_refs[0]) + \strlen($obj_refs[1]);
    }
    /**
     * Get content of indirect object.
     *
     * @param string $pdfData  PDF data
     * @param string $objRef   Object number and generation number separated by underscore character
     * @param int    $offset   Object offset
     * @param bool   $decoding If true decode streams
     *
     * @return array containing object data
     *
     * @throws \Exception if invalid object reference found
     */
    protected function get_indirect_object(string $pdf_data, array $xref, string $obj_ref, int $offset = 0, bool $decoding = true): array
    {
        /*
         * build indirect object header
         */
        // $objHeader = "[object number] [generation number] obj"
        $obj_ref_arr = explode('_', $obj_ref);
        if (2 !== \count($obj_ref_arr)) {
            throw new \Exception('Invalid object reference for $obj.');
        }
        $obj_header_len = $this->get_object_header_len($obj_ref_arr);
        /*
         * check if we are in position
         */
        // ignore whitespace characters at offset
        $offset += strspn($pdf_data, $this->config->get_pdf_whitespaces(), $offset);
        // ignore leading zeros for object number
        $offset += strspn($pdf_data, '0', $offset);
        if (0 == preg_match($this->get_object_header_pattern($obj_ref_arr), substr($pdf_data, $offset, $obj_header_len))) {
            // an indirect reference to an undefined object shall be considered a reference to the null object
            return ['null', 'null', $offset];
        }
        /*
         * get content
         */
        // starting position of object content
        $offset += $obj_header_len;
        $obj_content_arr = [];
        $i = 0;
        // object main index
        $header = null;
        do {
            $old_offset = $offset;
            // get element
            $element = $this->get_raw_object($pdf_data, $offset, null != $header ? $header[1] : null);
            $offset = $element[2];
            // decode stream using stream's dictionary information
            if ($decoding && 'stream' === $element[0] && null != $header) {
                $element[3] = $this->decode_stream($pdf_data, $xref, $header[1], $element[1]);
            }
            $obj_content_arr[$i] = $element;
            $header = isset($element[0]) && '<<' === $element[0] ? $element : null;
            ++$i;
        } while ('endobj' !== $element[0] && $offset !== $old_offset);
        // remove closing delimiter
        array_pop($obj_content_arr);
        /*
         * return raw object content
         */
        return $obj_content_arr;
    }
    /**
     * Get the content of object, resolving indirect object reference if necessary.
     *
     * @param string $pdfData PDF data
     * @param array  $obj     Object value
     *
     * @return array containing object data
     *
     * @throws \Exception
     */
    protected function get_object_val(string $pdf_data, array $xref, array $obj): array
    {
        if ('objref' != $obj[0]) {
            return $obj;
        }
        // reference to indirect object
        if (isset($this->objects[$obj[1]])) {
            // this object has been already parsed
            return $this->objects[$obj[1]];
        }
        if (isset($xref[$obj[1]])) {
            // parse new object
            $this->objects[$obj[1]] = $this->get_indirect_object($pdf_data, $xref, $obj[1], $xref[$obj[1]], false);
            return $this->objects[$obj[1]];
        }
        return $obj;
    }
    /**
     * Get object type, raw value and offset to next object
     *
     * @param int        $offset    Object offset
     * @param array|null $headerDic obj header's dictionary, parsed by getRawObject. Used for stream parsing optimization
     *
     * @return array containing object type, raw value and offset to next object
     */
    protected function get_raw_object(string $pdf_data, int $offset = 0, ?array $header_dic = null): array
    {
        $objtype = '';
        // object type to be returned
        $objval = '';
        // object value to be returned
        // skip initial white space chars
        $offset += strspn($pdf_data, $this->config->get_pdf_whitespaces(), $offset);
        // get first char
        $char = $pdf_data[$offset];
        // get object type
        switch ($char) {
            case '%':
                // \x25 PERCENT SIGN
                // skip comment and search for next token
                $next = strcspn($pdf_data, "\r\n", $offset);
                if ($next > 0) {
                    $offset += $next;
                    return $this->get_raw_object($pdf_data, $offset);
                }
                break;
            case '/':
                // \x2F SOLIDUS
                // name object
                $objtype = $char;
                ++$offset;
                $span = strcspn($pdf_data, "\x00\t\n\f\r \n\t\r\v\f()<>[]{}/%", $offset, 256);
                if ($span > 0) {
                    $objval = substr($pdf_data, $offset, $span);
                    // unescaped value
                    $offset += $span;
                }
                break;
            case '(':
            // \x28 LEFT PARENTHESIS
            case ')':
                // \x29 RIGHT PARENTHESIS
                // literal string object
                $objtype = $char;
                ++$offset;
                $strpos = $offset;
                if ('(' == $char) {
                    $open_bracket = 1;
                    while ($open_bracket > 0) {
                        if (!isset($pdf_data[$strpos])) {
                            break;
                        }
                        $ch = $pdf_data[$strpos];
                        switch ($ch) {
                            case '\\':
                                // REVERSE SOLIDUS (5Ch) (Backslash)
                                // skip next character
                                ++$strpos;
                                break;
                            case '(':
                                // LEFT PARENHESIS (28h)
                                ++$open_bracket;
                                break;
                            case ')':
                                // RIGHT PARENTHESIS (29h)
                                --$open_bracket;
                                break;
                        }
                        ++$strpos;
                    }
                    $objval = substr($pdf_data, $offset, $strpos - $offset - 1);
                    $offset = $strpos;
                }
                break;
            case '[':
            // \x5B LEFT SQUARE BRACKET
            case ']':
                // \x5D RIGHT SQUARE BRACKET
                // array object
                $objtype = $char;
                ++$offset;
                if ('[' == $char) {
                    // get array content
                    $objval = [];
                    do {
                        $old_offset = $offset;
                        // get element
                        $element = $this->get_raw_object($pdf_data, $offset);
                        $offset = $element[2];
                        $objval[] = $element;
                    } while (']' != $element[0] && $offset != $old_offset);
                    // remove closing delimiter
                    array_pop($objval);
                }
                break;
            case '<':
            // \x3C LESS-THAN SIGN
            case '>':
                // \x3E GREATER-THAN SIGN
                if (isset($pdf_data[$offset + 1]) && $pdf_data[$offset + 1] == $char) {
                    // dictionary object
                    $objtype = $char . $char;
                    $offset += 2;
                    if ('<' == $char) {
                        // get array content
                        $objval = [];
                        do {
                            $old_offset = $offset;
                            // get element
                            $element = $this->get_raw_object($pdf_data, $offset);
                            $offset = $element[2];
                            $objval[] = $element;
                        } while ('>>' != $element[0] && $offset != $old_offset);
                        // remove closing delimiter
                        array_pop($objval);
                    }
                } else {
                    // hexadecimal string object
                    $objtype = $char;
                    ++$offset;
                    $span = strspn($pdf_data, "0123456789abcdefABCDEF\t\n\f\r ", $offset);
                    $data_to_check = $pdf_data[$offset + $span] ?? null;
                    if ('<' == $char && $span > 0 && '>' == $data_to_check) {
                        // remove white space characters
                        $objval = strtr(substr($pdf_data, $offset, $span), $this->config->get_pdf_whitespaces(), '');
                        $offset += $span + 1;
                    } elseif (false !== $endpos = strpos($pdf_data, '>', $offset)) {
                        $offset = $endpos + 1;
                    }
                }
                break;
            default:
                if ('endobj' == substr($pdf_data, $offset, 6)) {
                    // indirect object
                    $objtype = 'endobj';
                    $offset += 6;
                } elseif ('null' == substr($pdf_data, $offset, 4)) {
                    // null object
                    $objtype = 'null';
                    $offset += 4;
                    $objval = 'null';
                } elseif ('true' == substr($pdf_data, $offset, 4)) {
                    // boolean true object
                    $objtype = 'boolean';
                    $offset += 4;
                    $objval = 'true';
                } elseif ('false' == substr($pdf_data, $offset, 5)) {
                    // boolean false object
                    $objtype = 'boolean';
                    $offset += 5;
                    $objval = 'false';
                } elseif ('stream' == substr($pdf_data, $offset, 6)) {
                    // start stream object
                    $objtype = 'stream';
                    $offset += 6;
                    if (1 == preg_match('/^( *[\r]?[\n])/isU', substr($pdf_data, $offset, 4), $matches)) {
                        $offset += \strlen($matches[0]);
                        // we get stream length here to later help preg_match test less data
                        $stream_len = (int) $this->get_header_value($header_dic, 'Length', 'numeric', 0);
                        $skip = false === $this->config->get_retain_image_content() && 'XObject' == $this->get_header_value($header_dic, 'Type', '/') && 'Image' == $this->get_header_value($header_dic, 'Subtype', '/');
                        $preg_result = preg_match('/(endstream)[\x09\x0a\x0c\x0d\x20]/isU', $pdf_data, $matches, \PREG_OFFSET_CAPTURE, $offset + $stream_len);
                        if (1 == $preg_result) {
                            $objval = $skip ? '' : substr($pdf_data, $offset, $matches[0][1] - $offset);
                            $offset = $matches[1][1];
                        }
                    }
                } elseif ('endstream' == substr($pdf_data, $offset, 9)) {
                    // end stream object
                    $objtype = 'endstream';
                    $offset += 9;
                } elseif (1 == preg_match('/^([0-9]+)[\s]+([0-9]+)[\s]+R/iU', substr($pdf_data, $offset, 33), $matches)) {
                    // indirect object reference
                    $objtype = 'objref';
                    $offset += \strlen($matches[0]);
                    $objval = (int) $matches[1] . '_' . (int) $matches[2];
                } elseif (1 == preg_match('/^([0-9]+)[\s]+([0-9]+)[\s]+obj/iU', substr($pdf_data, $offset, 33), $matches)) {
                    // object start
                    $objtype = 'obj';
                    $objval = (int) $matches[1] . '_' . (int) $matches[2];
                    $offset += \strlen($matches[0]);
                } elseif (($numlen = strspn($pdf_data, '+-.0123456789', $offset)) > 0) {
                    // numeric object
                    $objtype = 'numeric';
                    $objval = substr($pdf_data, $offset, $numlen);
                    $offset += $numlen;
                }
                break;
        }
        return [$objtype, $objval, $offset];
    }
    /**
     * Get value of an object header's section (obj << YYY >> part ).
     *
     * It is similar to Header::get('...')->getContent(), the only difference is it can be used during the parsing process,
     * when no Smalot\PdfParser\Header objects are created yet.
     *
     * @param string            $key     header's section name
     * @param string            $type    type of the section (i.e. 'numeric', '/', '<<', etc.)
     * @param string|array|null $default default value for header's section
     *
     * @return string|array|null value of obj header's section, or default value if none found, or its type doesn't match $type param
     */
    private function get_header_value(?array $header_dic, string $key, string $type, $default = '')
    {
        if (false === \is_array($header_dic)) {
            return $default;
        }
        /*
         * It recieves dictionary of header fields, as it is returned by RawDataParser::getRawObject,
         * iterates over it, searching for section of type '/' whith requested key.
         * If such a section is found, it tries to receive it's value (next object in dictionary),
         * returning it, if it matches requested type, or default value otherwise.
         */
        foreach ($header_dic as $i => $val) {
            $is_section_name = \is_array($val) && 3 == \count($val) && '/' == $val[0];
            if ($is_section_name && $val[1] == $key && isset($header_dic[$i + 1])) {
                $is_section_value = \is_array($header_dic[$i + 1]) && 1 < \count($header_dic[$i + 1]);
                return $is_section_value && $type == $header_dic[$i + 1][0] ? $header_dic[$i + 1][1] : $default;
            }
        }
        return $default;
    }
    /**
     * Get Cross-Reference (xref) table and trailer data from PDF document data.
     *
     * @param int        $offset        xref offset (if known)
     * @param array      $xref          previous xref array (if any)
     * @param array<int,true> $visitedOffsets hash-set of visited offsets (keys) to prevent infinite loops; O(1) lookup
     *
     * @return array containing xref and trailer data
     *
     * @throws \Exception if it was unable to find startxref
     * @throws \Exception if it was unable to find xref
     */
    protected function get_xref_data(string $pdf_data, int $offset = 0, array $xref = [], array $visited_offsets = []): array
    {
        // Use the array as a hash-set (keys) for O(1) membership tests instead of
        // in_array() which is O(n), avoiding O(n²) traversal on PDFs with many xref sections.
        if (isset($visited_offsets[$offset])) {
            // We've already processed this offset, skip to avoid infinite loop
            return $xref;
        }
        // Track this offset as visited (key-based for O(1) lookup)
        $visited_offsets[$offset] = true;
        // If the $offset is currently pointed at whitespace, bump it
        // forward until it isn't; affects loosely targetted offsets
        // for the 'xref' keyword
        // See: https://github.com/smalot/pdfparser/issues/673
        $bump_offset = $offset;
        while (preg_match('/\s/', substr($pdf_data, $bump_offset, 1))) {
            ++$bump_offset;
        }
        // Find all startxref tables from this $offset forward
        $startxref_preg = preg_match_all('/(?<=[\r\n])startxref[\s]*[\r\n]+([0-9]+)[\s]*[\r\n]+%%EOF/i', $pdf_data, $startxref_matches, \PREG_SET_ORDER, $offset);
        if (0 == $startxref_preg) {
            // No startxref tables were found
            throw new \Exception('Unable to find startxref');
        }
        if (0 == $offset) {
            // Use the last startxref in the document
            $startxref = (int) $startxref_matches[\count($startxref_matches) - 1][1];
        } elseif (strpos($pdf_data, 'xref', $bump_offset) == $bump_offset) {
            // Already pointing at the xref table
            $startxref = $bump_offset;
        } elseif (preg_match('/([0-9]+[\s][0-9]+[\s]obj)/i', $pdf_data, $matches, 0, $bump_offset)) {
            // Cross-Reference Stream object
            $startxref = $bump_offset;
        } else {
            // Use the next startxref from this $offset
            $startxref = (int) $startxref_matches[0][1];
        }
        if ($startxref > \strlen($pdf_data)) {
            throw new \Exception('Unable to find xref (PDF corrupted?)');
        }
        // check xref position
        if (strpos($pdf_data, 'xref', $startxref) == $startxref) {
            // Cross-Reference
            $xref = $this->decode_xref($pdf_data, $startxref, $xref, $visited_offsets);
        } else {
            // Check if the $pdfData might have the wrong line-endings
            $pdf_data_unix = str_replace("\r\n", "\n", $pdf_data);
            if ($startxref < \strlen($pdf_data_unix) && strpos($pdf_data_unix, 'xref', $startxref) == $startxref) {
                // Return Unix-line-ending flag
                $xref = ['Unix' => true];
            } else {
                // Cross-Reference Stream
                $xref = $this->decode_xref_stream($pdf_data, $startxref, $xref, $visited_offsets);
            }
        }
        if (empty($xref)) {
            throw new \Exception('Unable to find xref');
        }
        return $xref;
    }
    /**
     * Parses PDF data and returns extracted data as array.
     *
     * @param string $data PDF data to parse
     *
     * @return array array of parsed PDF document objects
     *
     * @throws EmptyPdfException if empty PDF data given
     * @throws MissingPdfHeaderException if PDF data missing `%PDF-` header
     */
    public function parse_data(string $data): array
    {
        if (empty($data)) {
            throw new Empty_Pdf_Exception('Empty PDF data given.');
        }
        // find the pdf header starting position
        if (false === $trimpos = strpos($data, '%PDF-')) {
            throw new Missing_Pdf_Header_Exception('Invalid PDF data: Missing `%PDF-` header.');
        }
        // get PDF content string
        $pdf_data = $trimpos > 0 ? substr($data, $trimpos) : $data;
        // get xref and trailer data
        $xref = $this->get_xref_data($pdf_data);
        // If we found Unix line-endings
        if (isset($xref['Unix'])) {
            $pdf_data = str_replace("\r\n", "\n", $pdf_data);
            $xref = $this->get_xref_data($pdf_data);
        }
        // parse all document objects
        $objects = [];
        foreach ($xref['xref'] as $obj => $offset) {
            if (!isset($objects[$obj]) && $offset > 0) {
                // decode objects with positive offset
                $objects[$obj] = $this->get_indirect_object($pdf_data, $xref, $obj, $offset, true);
            }
        }
        return [$xref, $objects];
    }
}