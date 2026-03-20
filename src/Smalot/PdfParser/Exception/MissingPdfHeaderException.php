<?php

declare (strict_types=1);
namespace Smalot\Pdf_Parser\Exception;

/**
 * This Exception is thrown when the %PDF- header is missing.
 */
class Missing_Pdf_Header_Exception extends \Exception
{
}