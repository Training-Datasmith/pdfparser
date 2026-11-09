# Usage

First create a parser object and point it to a file.

```php
$parser = new \Smalot\PdfParser\Parser();

$pdf = $parser->parse_file('document.pdf');
// .. or ...
$pdf = $parser->parse_content(file_get_contents('document.pdf'))
 ```

## Extract text

A common scenario is to extract text.

```php
// extract text of the whole PDF
$text = $pdf->get_text();

// or extract the text of a specific page (in this case the first page)
$text = $pdf->get_pages()[0]->get_text();

// you can also extract text of a limited amount of pages. here, it will only use the first five pages.
$text = $pdf->get_text(5);
```

## Extract text positions

You can extract transformation matrix (indexes 0-3) and x,y position of text objects (indexes 4,5).

```php
$data = $pdf->get_pages()[0]->get_data_tm();

Array
(
    [0] => Array
        (
            [0] => Array
                (
                    [0] => 0.999429
                    [1] => 0
                    [2] => 0
                    [3] => 1
                    [4] => 201.96
                    [5] => 720.68
                )

            [1] => Document title
        )

    [1] => Array
        (
            [0] => Array
                (
                    [0] => 0.999402
                    [1] => 0
                    [2] => 0
                    [3] => 1
                    [4] => 70.8
                    [5] => 673.64
                )

            [1] => Calibri : Lorem ipsum dolor sit amet, consectetur a
        )
)
```

When activated via Config setting (`Config::set_data_tm_font_info_has_to_be_included(true)`) font identifier (index 2) and font size (index 3) are added to dataTm.

```php
// create config
$config = new Smalot\PdfParser\Config();
$config->set_data_tm_font_info_has_to_be_included(true);

// use config and parse file
$parser = new Smalot\PdfParser\Parser([], $config);
$pdf = $parser->parse_file('document.pdf');
$firstpage = $pdf->get_pages()[0];
$data = $firstpage->get_data_tm();

Array
(
    [0] => Array
        (
            [0] => Array
                (
                    [0] => 0.999429
                    [1] => 0
                    [2] => 0
                    [3] => 1
                    [4] => 201.96
                    [5] => 720.68
                )

            [1] => Document title
            [2] => R7
            [3] => 27.96
        )

    [1] => Array
        (
            [0] => Array
                (
                    [0] => 0.999402
                    [1] => 0
                    [2] => 0
                    [3] => 1
                    [4] => 70.8
                    [5] => 673.64
                )

            [1] => Calibri : Lorem ipsum dolor sit amet, consectetur a
            [2] => R9
            [3] => 11.04
        )
)
```

Text width should be calculated on text from dataTm to make sure all character widths are available.
In next example we are using data from above.

```php
$font_id = $data[0][2]; //R7
$font = $firstpage->get_font($font_id);
$text = $data[0][1];
$width = $font->calculate_text_width($text, $missing);
```

## Extract metadata

You can also extract metadata. The available data varies from PDF to PDF.

```php
$metaData = $pdf->get_details();

Array
(
    [Producer] => Adobe Acrobat
    [CreatedOn] => 2022-01-28T16:36:11+00:00
    [Pages] => 35
    ...
)
```

If the PDF contains Extensible Metadata Platform (XMP) XML metadata, their values, including the XMP namespace, will be appended to the data returned by `get_details()`. You can read more about what values and namespaces are commonly used in the [XMP Specifications](https://github.com/adobe/XMP-Toolkit-SDK/tree/main/docs).

```php
Array
(
    ...
    [Pages] => 35
    [dc:creator] => My Name
    [pdf:producer] => Adobe Acrobat
    [dc:title] => My Document Title
    ...
)
```

Some XMP metadata values may have multiple values, or even named children with their own values. In these cases, the value will be an array. The XMP metadata will follow the structure of the XML so it is possible to have multiple levels of nested values.

```php
Array
(
    ...
    [dc:title] => My Document Title
    [xmptpg:maxpagesize] => Array
    (
        [stdim:w] => 21.500000
        [stdim:h] => 6.222222
        [stdim:unit] => Inches
    )
    [xmptpg:platenames] => Array
    (
        [0] => Cyan
        [1] => Magenta
        [2] => Yellow
        [3] => Black
    )
    ...
)
```


## Read Base64 encoded PDFs

If working with [Base64](https://en.wikipedia.org/wiki/Base64) encoded PDFs, you might want to parse the PDF without saving the file to disk.
This sample will parse the Base64 encoded PDF and extract text from each page.

```php
<?php
// Parse Base64 encoded PDF string and build necessary objects.
$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parse_content(base64_decode($base64PDF));

$text = $pdf->get_text();
echo $text;
```

## Calculate text width

Try to calculate text width for given font.
Characters without width are added to `$missing` array in second parameter.

```php
$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parse_file('document.pdf');
$fonts = $pdf->get_fonts();
// get first font (we assume here there is at least one)
$font = reset($fonts);
// get width
$width = $font->calculate_text_width('Some text', $missing);
```

## Get pages width and height

Ref: [#472](https://github.com/smalot/pdfparser/issues/427#issuecomment-973416786)

```php
$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parse_file('document.pdf');
$pages = $pdf->get_pages();
// this variable will contain the height and width of each page of the given PDF
$mediaBox = [];
foreach ($pages as $page) {
    $details = $page->get_details();
    // If Mediabox is not set in details of current $page instance, get details from the header instead
    if (!isset($details['MediaBox'])) {
        $pages = $pdf->get_objects_by_type('Pages');
        $details = reset($pages)->get_header()->get_details();
    }
    $mediaBox[] = [
        'width' => $details['MediaBox'][2],
        'height' => $details['MediaBox'][3]
    ];
}
```

## PDF encryption

This library cannot currently read encrypted PDF files, i.e. those with
a read password.  Attempting to do so produces this error:
```
Exception: Secured pdf file are currently not supported.
```

See `setIgnoreEncryption` option in [CustomConfig.md](CustomConfig.md)
for how to override the check in specific cases.
