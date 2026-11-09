<?php

declare(strict_types=1);

namespace PHP_Unit_Tests\Unit;

use PHP_Unit_Tests\Test_Case;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Element;
use Smalot\PdfParser\Element\Element_Array;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Pdf_Object;
use Smalot\PdfParser\XObject\Form;
use Smalot\PdfParser\XObject\Image;

class PDF_Object_Test extends Test_Case
{
    public function testGetTextOnNullPage(): void
    {
        static::assertSame(' ', (new Pdf_Object(new Document()))->get_text());
    }

    public function testGetTextOnPageWithoutContent(): void
    {
        $document = new Document();

        static::assertSame(' ', (new Pdf_Object($document, null, null))->get_text(new Page($document)));
    }

    public function testTextArrayObjects(): void
    {
        $document = new Document();
        $document->init();

        $config = new Config();
        $image = new Image($document);
        $formNoText = new Form($document);
        $formWithText = new Form($document, null, 'BT /F1 12 Tf 10 10 Td (Form text) Tj ET', $config);
        $xObject = new Pdf_Object($document);

        $header1 = new Header([
            'Resources' => new Header([
                'XObject' => new Header([
                    'Im0' => $image,
                ]),
            ]),
            'Contents' => new Element_Array([new Element('/Im0 Do', $document)], $document),
        ]);
        $page1 = new Page($document, $header1);

        $header2 = new Header([
            'Resources' => new Header([
                'XObject' => new Header([
                    'Fr0' => $formNoText,
                ]),
            ]),
            'Contents' => new Element_Array([new Element('/Fr0 Do', $document)], $document),
        ]);
        $page2 = new Page($document, $header2);

        $header3 = new Header([
            'Resources' => new Header([
                'XObject' => new Header([
                    'Fr0' => $formWithText,
                ]),
            ]),
            'Contents' => new Element_Array([new Element('/Fr0 Do', $document)], $document),
        ]);
        $page3 = new Page($document, $header3);

        $header4 = new Header([
            'Resources' => new Header([
                'XObject' => new Header([
                    'Ps0' => $xObject,
                ]),
            ]),
            'Contents' => new Element_Array([new Element('/Ps0 Do', $document)], $document),
        ]);
        $page4 = new Page($document, $header4);

        // Page 1 contains an image, which should not appear in the text array.
        self::assertSame([], $page1->get_text_array());

        // Page 2 contains a form that contains no text, which should not appear
        // in the text array.
        self::assertSame([], $page2->get_text_array());

        // Page 3 contains a form that contains text, which should appear in the
        // text array.
        self::assertSame(['Form text '], $page3->get_text_array());

        // Page 4 contains a non-image object, which should appear in the text
        // array.
        self::assertSame([' '], $page4->get_text_array());
    }
}
