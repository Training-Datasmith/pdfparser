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
/**
 * Class Pages
 */
class Pages extends Pdf_Object
{
    /**
     * @var array<\Smalot\PdfParser\Font>|null
     */
    protected $fonts;
    /**
     * @todo Objects other than Pages or Page might need to be treated specifically
     *       in order to get Page objects out of them.
     *
     * @see https://github.com/smalot/pdfparser/issues/331
     */
    public function get_pages(bool $deep = false): array
    {
        if (!$this->has('Kids')) {
            return [];
        }
        /** @var ElementArray $kidsElement */
        $kids_element = $this->get('Kids');
        if (!$deep) {
            return $kids_element->get_content();
        }
        // Prepare to apply the Pages' object's fonts to each page
        if (false === \is_array($this->fonts)) {
            $this->setup_fonts();
        }
        $fonts_available = 0 < \count($this->fonts);
        $kids = $kids_element->get_content();
        $pages = [];
        foreach ($kids as $kid) {
            if ($kid instanceof self) {
                $pages = array_merge($pages, $kid->get_pages(true));
            } elseif ($kid instanceof Page) {
                if ($fonts_available) {
                    $kid->set_fonts($this->fonts);
                }
                $pages[] = $kid;
            }
        }
        return $pages;
    }
    /**
     * Gathers information about fonts and collects them in a list.
     *
     * @return void
     *
     * @internal
     */
    protected function setup_fonts()
    {
        $resources = $this->get('Resources');
        if (method_exists($resources, 'has') && $resources->has('Font')) {
            // no fonts available, therefore stop here
            if ($resources->get('Font') instanceof Element\Element_Missing) {
                return;
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
            $this->fonts = $table;
        } else {
            $this->fonts = [];
        }
    }
}