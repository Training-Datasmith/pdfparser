<?php

declare(strict_types=1);

/**
 * @file This file is part of the PdfParser library.
 *
 * @author  Konrad Abicht <k.abicht@gmail.com>
 *
 * @date    2020-06-01
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

namespace PHP_Unit_Tests\Integration;

use PHP_Unit_Tests\Test_Case;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Pages;
use Smalot\PdfParser\Pdf_Object;

/**
 * General Document related tests.
 */
class Document_Test extends Test_Case
{
    protected function get_document_instance(): Document
    {
        return new Document();
    }

    protected function get_pdf_object_instance(Document $document, ?Header $header = null): Pdf_Object
    {
        return new Pdf_Object($document, $header);
    }

    protected function get_page_instance(Document $document, Header $header): Pdf_Object
    {
        return new Page($document, $header);
    }

    protected function get_pages_instance(Document $document, Header $header): Pdf_Object
    {
        return new Pages($document, $header);
    }

    public function testSetObjects(): void
    {
        $document = $this->get_document_instance();
        $object = $this->get_pdf_object_instance($document);

        // Obj #1 is missing
        $this->assertNull($document->get_object_by_id(1));
        $document->set_objects([1 => $object]);

        // Obj #1 exists
        $this->assertTrue($document->get_object_by_id(1) instanceof Pdf_Object);

        $content = '<</Type/Page>>';
        $header = Header::parse($content, $document);
        $object = $this->get_pdf_object_instance($document, $header);
        $document->set_objects([2 => $object]);

        // Obj #1 is missing
        $this->assertNull($document->get_object_by_id(1));

        // Obj #2 exists
        $this->assertTrue($document->get_object_by_id(2) instanceof Pdf_Object);
    }

    public function testGetObjects(): void
    {
        $document = $this->get_document_instance();
        $object1 = $this->get_pdf_object_instance($document);
        $content = '<</Type/Page>>unparsed content';
        $header = Header::parse($content, $document);

        $object2 = $this->get_page_instance($document, $header);
        $document->set_objects([1 => $object1, 2 => $object2]);

        $objects = $document->get_objects();
        $this->assertEquals(2, \count($objects));
        $this->assertTrue($objects[1] instanceof Pdf_Object);
        $this->assertTrue($objects[2] instanceof Pdf_Object);
        $this->assertTrue($objects[2] instanceof Page);
    }

    public function testDictionary(): void
    {
        $document = $this->get_document_instance();
        $objects = $document->get_dictionary();
        $this->assertEquals(0, \count($objects));
        $object1 = $this->get_pdf_object_instance($document);

        $content = '<</Type/Page>>';
        $header = Header::parse($content, $document);
        $object2 = $this->get_page_instance($document, $header);
        $document->set_objects([1 => $object1, 2 => $object2]);

        $objects = $document->get_dictionary();
        $this->assertEquals(1, \count($objects));
        $this->assertEquals(1, \count($objects['Page']['all']));
        $this->assertEquals($object2, $objects['Page']['all'][2]);
    }

    public function testGetObjectsByType(): void
    {
        $document = $this->get_document_instance();
        $object1 = $this->get_pdf_object_instance($document);
        $content = '<</Type/Page>>';
        $header = Header::parse($content, $document);
        $object2 = $this->get_page_instance($document, $header);
        $document->set_objects([1 => $object1, 2 => $object2]);

        $objects = $document->get_objects_by_type('Page');
        $this->assertEquals(1, \count($objects));
        $this->assertTrue($objects[2] instanceof Pdf_Object);
        $this->assertTrue($objects[2] instanceof Page);
    }

    public function testGetPages(): void
    {
        $document = $this->get_document_instance();

        // Listing pages from type Page
        $content = '<</Type/Page>>';
        $header = Header::parse($content, $document);
        $object1 = $this->get_page_instance($document, $header);
        $header = Header::parse($content, $document);
        $object2 = $this->get_page_instance($document, $header);
        $document->set_objects([1 => $object1, 2 => $object2]);
        $pages = $document->get_pages();

        $this->assertEquals(2, \count($pages));
        $this->assertTrue($pages[0] instanceof Page);
        $this->assertTrue($pages[1] instanceof Page);

        // Listing pages from type Pages (kids)
        $content = '<</Type/Page>>';
        $header = Header::parse($content, $document);
        $object1 = $this->get_page_instance($document, $header);
        $header = Header::parse($content, $document);
        $object2 = $this->get_page_instance($document, $header);
        $header = Header::parse($content, $document);
        $object3 = $this->get_page_instance($document, $header);

        $content = '<</Type/Pages/Kids[1 0 R 2 0 R]>>';
        $header = Header::parse($content, $document);
        $object4 = $this->get_pages_instance($document, $header);

        $content = '<</Type/Pages/Kids[3 0 R]>>';
        $header = Header::parse($content, $document);
        $object5 = $this->get_pages_instance($document, $header);

        $document->set_objects([
            '1_0' => $object1,
            '2_0' => $object2,
            '3_0' => $object3,
            '4_0' => $object4,
            '5_0' => $object5,
        ]);
        $pages = $document->get_pages();

        $this->assertEquals(3, \count($pages));
        $this->assertTrue($pages[0] instanceof Page);
        $this->assertTrue($pages[1] instanceof Page);
        $this->assertTrue($pages[2] instanceof Page);

        // Listing pages from type Catalog
        $content = '<</Type/Page>>';
        $header = Header::parse($content, $document);
        $object1 = $this->get_page_instance($document, $header);
        $header = Header::parse($content, $document);
        $object2 = $this->get_page_instance($document, $header);
        $header = Header::parse($content, $document);
        $object3 = $this->get_page_instance($document, $header);
        $content = '<</Type/Pages/Kids[1 0 R 2 0 R]>>';
        $header = Header::parse($content, $document);
        $object4 = $this->get_pages_instance($document, $header);
        $content = '<</Type/Pages/Kids[4 0 R 3 0 R]>>';
        $header = Header::parse($content, $document);
        $object5 = $this->get_pages_instance($document, $header);
        $content = '<</Type/Catalog/Pages 5 0 R >>';
        $header = Header::parse($content, $document);
        $object6 = $this->get_pages_instance($document, $header);
        $document->set_objects(
            [
                '1_0' => $object1,
                '2_0' => $object2,
                '3_0' => $object3,
                '4_0' => $object4,
                '5_0' => $object5,
                '6_0' => $object6,
            ]
        );
        $pages = $document->get_pages();
        $this->assertEquals(3, \count($pages));
        $this->assertTrue($pages[0] instanceof Page);
        $this->assertTrue($pages[1] instanceof Page);
        $this->assertTrue($pages[2] instanceof Page);
    }

    public function testGetPagesMissingCatalog(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing catalog.');

        // Missing catalog
        $document = $this->get_document_instance();
        $document->get_pages();
    }

    /**
     * @see https://github.com/smalot/pdfparser/issues/721
     */
    public function testExtractXMPMetadataIssue721(): void
    {
        $document = $this->get_document_instance();

        // Check that XMP metadata is parsed even if missing a dc:format tag
        $content = '<?xpacket begin="ï»¿" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="Adobe XMP Core 5.6-c015 84.159810, 2016/09/10-02:41:30">
   <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
      <rdf:Description>
         <dc:creator>
            <rdf:Seq>
               <rdf:li>PdfParser</rdf:li>
            </rdf:Seq>
         </dc:creator>
         <xmp:CreateDate>2018-02-07T11:51:44-05:00</xmp:CreateDate>
         <xmp:ModifyDate>2019-10-23T09:56:01-04:00</xmp:ModifyDate>
      </rdf:Description>
   </rdf:RDF>
</x:xmpmeta>';

        $document->extract_xmp_metadata($content);
        $document->init();
        $details = $document->get_details();

        $this->assertEquals(4, \count($details));
        $this->assertEquals('PdfParser', $details['dc:creator']);
        $this->assertEquals('2019-10-23T09:56:01-04:00', $details['xmp:modifydate']);
    }
}
