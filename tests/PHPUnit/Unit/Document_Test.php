<?php

declare(strict_types=1);

namespace PHP_Unit_Tests\Unit;

use PHP_Unit_Tests\Test_Case;
use Smalot\PdfParser\Document;

class Document_Test extends Test_Case
{
    public function testGetFirstFontWhenNoFontAvailable(): void
    {
        $document = new Document();

        $this->assertNull($document->get_first_font());
    }
}
