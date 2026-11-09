<?php

declare(strict_types=1);

namespace PHP_Unit_Tests\Unit;

use PHP_Unit_Tests\Test_Case;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Encoding;

class Encoding_Test extends Test_Case
{
    public function testTranslateCharOnUnknownGlyph(): void
    {
        $encoding = new Encoding(new Document());

        $this->assertNull($encoding->translate_char('foo'));
    }
}
