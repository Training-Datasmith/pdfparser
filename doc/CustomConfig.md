# Configuring the behavior of the parser

To change the behavior of the parser, create a `Config` object and pass it to the parser.
In this case, we're setting the font space limit.
Changing this value can be helpful when `get_text()` returns a text with too many spaces.

```php
$config = new \Smalot\PdfParser\Config();
$config->set_font_space_limit(-60);
$parser = new \Smalot\PdfParser\Parser([], $config);
$pdf = $parser->parse_file('document.pdf');
// output extracted text
// echo $pdf->get_text();
```

## Config options overview

The `Config` class has the following options:

| Option                                      | Type    | Default         | Description                                                                                                                                          |
|---------------------------------------------|---------|-----------------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| `set_decode_memory_limit`                   | Integer | `0`             | If parsing fails because of memory exhaustion, you can set a lower memory limit for decoding operations.                                             |
| `set_font_space_limit`                      | Integer | `-50`           | Changing font space limit can be helpful when `Parser::get_text()` returns a text with too many spaces.                                               |
| `set_ignore_encryption`                     | Boolean | `false`         | Read PDFs that are not encrypted but have the encryption flag set. This is a temporary workaround, don't rely on it.                                 |
| `set_horizontal_offset`                     | String  | ` `             | When words are broken up or when the structure of a table is not preserved, you may get better results when adapting `set_horizontal_offset`.          |
| `set_pdf_whitespaces`                       | String  | `\0\t\n\f\r `   |                                                                                                                                                      |
| `set_pdf_whitespaces_regex`                 | String  | `[\0\t\n\f\r ]` |                                                                                                                                                      |
| `set_retain_image_content`                  | Boolean | `true`          | If parsing fails due to memory exhaustion, you can set the value to `false`. This will reduce memory usage, although it will no longer retain image content. |


## option set_decode_memory_limit + set_retain_image_content (manage memory usage)

If parsing fails because of memory exhaustion, you can use the following options.

```php
$config = new \Smalot\PdfParser\Config();
// Whether to retain raw image data as content or discard it to save memory
$config->set_retain_image_content(false);
// Memory limit to use when de-compressing files, in bytes
$config->set_decode_memory_limit(1000000);
$parser = new \Smalot\PdfParser\Parser([], $config);
```

## option set_horizontal_offset

When words are broken up or when the structure of a table is not preserved, you can use `set_horizontal_offset`.

```php
$config = new \Smalot\PdfParser\Config();
// An empty string can prevent words from breaking up
$config->set_horizontal_offset('');
// A tab can help preserve the structure of your document
$config->set_horizontal_offset("\t");
$parser = new \Smalot\PdfParser\Parser([], $config);
```

## option set_font_space_limit

Changing font space limit can be helpful when `get_text()` returns a text with too many spaces.

```php
$config = new \Smalot\PdfParser\Config();
$config->set_font_space_limit(-60);
$parser = new \Smalot\PdfParser\Parser([], $config);
$pdf = $parser->parse_file('document.pdf');
```

## option set_ignore_encryption

In some cases PDF files may be internally marked as encrypted even though the content is not encrypted and can be read.
This can be caused by the PDF being created by a tool that does not properly set the encryption flag.
If you are sure that the PDF is not encrypted, you can ignore the encryption flag by setting the `ignoreEncryption` flag to `true` in a custom `Config` instance.

```php
$config = new \Smalot\PdfParser\Config();
$config->set_ignore_encryption(true);

$parser = new \Smalot\PdfParser\Parser([], $config);
$pdf = $parser->parse_file('document.pdf');
```
