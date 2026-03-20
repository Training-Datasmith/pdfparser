<?php

declare (strict_types=1);
namespace Smalot\Pdf_Parser\Encoding;

abstract class Abstract_Encoding
{
    abstract public function get_translations(): array;
}