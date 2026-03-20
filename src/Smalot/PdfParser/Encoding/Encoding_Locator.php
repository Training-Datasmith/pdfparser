<?php

declare (strict_types=1);
namespace Smalot\Pdf_Parser\Encoding;

class Encoding_Locator
{
    protected static $encodings;
    public static function get_encoding(string $encoding_class_name): Abstract_Encoding
    {
        if (!isset(self::$encodings[$encoding_class_name])) {
            self::$encodings[$encoding_class_name] = new $encoding_class_name();
        }
        return self::$encodings[$encoding_class_name];
    }
}