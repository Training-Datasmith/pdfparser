<?php

declare(strict_types=1);

namespace Smalot\PdfParser\Encoding;

abstract class AbstractEncoding
{
    abstract public function getTranslations(): array;
}
