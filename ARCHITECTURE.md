# Architecture: pdfparser (smalot/pdfparser)

## Purpose

A PHP library for parsing PDF files and extracting text, metadata, and structure. Supports compressed streams, various font encodings, and common PDF object types.

## Directory Structure

```
src/Smalot/PdfParser/
  Parser.php              — Entry point: parses a PDF file/string into a Document
  Document.php            — Represents a parsed PDF; provides access to pages, metadata, objects
  Page.php                — Represents a single PDF page with extractable text
  Pages.php               — Container for all pages in a document
  PDF_Object.php          — Base for all PDF objects (dictionaries, streams, etc.)
  Element.php             — Base class for PDF data elements
  Header.php              — PDF object header (dictionary of key/value pairs)
  Font.php                — Font object; handles character-to-unicode mapping
  Element/                — Concrete element types: Array, Boolean, Date, Hex, Numeric, String, etc.
  Font/                   — Font subtypes: Type0, Type1, Type3, TrueType, CIDFont
  Encoding/               — Character encoding tables (ISO-Latin1, WinAnsi, MacRoman, etc.)
  RawData/
    Raw_Data_Parser.php   — Low-level binary PDF structure parser (cross-reference tables, object streams)
    Filter_Helper.php     — Decompresses and decodes PDF streams (FlateDecode, LZW, ASCII85, etc.)
  XObject/
    Form.php              — XObject Form (reusable content stream)
    Image.php             — XObject Image
  Exception/              — Domain-specific exceptions
  Config.php              — Parser configuration (horizontal whitespace, horizontal offset, etc.)
```

## Key Design Decisions

- **Two-pass parsing**: `RawDataParser` first extracts the binary structure (object offsets, xref tables), then `Parser` builds the semantic object graph
- **Lazy stream decoding**: Stream content is decoded only when accessed, avoiding decompressing the entire PDF upfront
- **Font-aware text extraction**: Each page maps character codes through its font's encoding table, handling multi-byte CID fonts and custom ToUnicode CMaps
- **Config-driven whitespace**: `Config` controls thresholds for merging or splitting text fragments, allowing tuning for different PDF layouts

## Extension Points

- Pass a custom `Config` to `Parser` to adjust text-extraction behaviour
- Implement additional stream filter decoders in `Filter_Helper` for exotic PDFs

## Dependency Flow

```
Parser::parseFile($path) / parseContent($data)
  → RawDataParser::parse()       — binary structure, cross-reference
  → Document (object graph)
  → Document::getPages()
  → Page::getText()
      → Font::decodeText()       — encoding + ToUnicode mapping
```
