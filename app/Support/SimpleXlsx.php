<?php

namespace App\Support;

/**
 * A one-sheet Excel (.xlsx) file of text cells with a few built-in styles, ready to print.
 *
 * The file is zipped by hand (uncompressed), so downloads work on any host, even one without PHP's zip extension.
 */
class SimpleXlsx
{
    public const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Cell styles by name: [font, fill, border, horizontal alignment], pointing into the lists in styles.xml.
     *
     * @var array<string, array{0: int, 1: int, 2: int, 3: string|null}>
     */
    protected const STYLES = [
        'default' => [0, 0, 0, null],
        'title' => [2, 0, 0, null],
        'header' => [1, 2, 1, null],
        'header-right' => [1, 2, 1, 'right'],
        'cell' => [0, 0, 1, null],
        'cell-right' => [0, 0, 1, 'right'],
        'total' => [1, 0, 1, null],
        'total-right' => [1, 0, 1, 'right'],
    ];

    protected const XML_DECLARATION = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    protected const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    protected const PACKAGE_RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    protected const DOCUMENT_RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @var list<list<array{text: string, style: string}>>
     */
    protected array $rows = [];

    /**
     * @param  list<int|float>  $columnWidths  Widths in characters, starting at column A.
     */
    public function __construct(
        protected string $sheetName = 'Sheet1',
        protected array $columnWidths = [],
    ) {}

    /**
     * Add a row of text, with one style for every cell or a style per cell.
     *
     * @param  list<string>  $texts
     * @param  string|list<string>  $styles  Names from STYLES.
     */
    public function addRow(array $texts = [], string|array $styles = 'default'): static
    {
        $cells = [];

        foreach (array_values($texts) as $index => $text) {
            $cells[] = ['text' => $text, 'style' => is_array($styles) ? ($styles[$index] ?? 'default') : $styles];
        }

        $this->rows[] = $cells;

        return $this;
    }

    /**
     * The contents of the .xlsx file.
     */
    public function toString(): string
    {
        return $this->zip([
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relationshipsXml(['officeDocument' => 'xl/workbook.xml']),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->relationshipsXml(['worksheet' => 'worksheets/sheet1.xml', 'styles' => 'styles.xml']),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->worksheetXml(),
        ]);
    }

    protected function contentTypesXml(): string
    {
        $spreadsheetType = 'application/vnd.openxmlformats-officedocument.spreadsheetml';

        return self::XML_DECLARATION
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="'.$spreadsheetType.'.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="'.$spreadsheetType.'.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="'.$spreadsheetType.'.styles+xml"/>'
            .'</Types>';
    }

    /**
     * @param  array<string, string>  $targets  Part paths keyed by relationship type, numbered rId1, rId2…
     */
    protected function relationshipsXml(array $targets): string
    {
        $relationships = '';
        $id = 1;

        foreach ($targets as $type => $target) {
            $relationships .= '<Relationship Id="rId'.$id++.'" Type="'.self::DOCUMENT_RELATIONSHIPS_NAMESPACE.'/'.$type.'" Target="'.$target.'"/>';
        }

        return self::XML_DECLARATION.'<Relationships xmlns="'.self::PACKAGE_RELATIONSHIPS_NAMESPACE.'">'.$relationships.'</Relationships>';
    }

    protected function workbookXml(): string
    {
        // Excel sheet names are at most 31 characters, without \ / ? * [ ] or :
        $sheetName = mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $this->sheetName), 0, 31);

        return self::XML_DECLARATION
            .'<workbook xmlns="'.self::SPREADSHEET_NAMESPACE.'" xmlns:r="'.self::DOCUMENT_RELATIONSHIPS_NAMESPACE.'">'
            .'<sheets><sheet name="'.$this->escape($sheetName ?: 'Sheet1').'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    protected function stylesXml(): string
    {
        $edge = fn (string $side) => '<'.$side.' style="thin"><color rgb="FF8C9A8F"/></'.$side.'>';
        $cellFormats = '';

        foreach (self::STYLES as [$font, $fill, $border, $alignment]) {
            $cellFormats .= '<xf numFmtId="0" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1"'
                .($alignment ? ' applyAlignment="1"><alignment horizontal="'.$alignment.'"/></xf>' : '/>');
        }

        return self::XML_DECLARATION
            .'<styleSheet xmlns="'.self::SPREADSHEET_NAMESPACE.'">'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE3F3E1"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'.$edge('left').$edge('right').$edge('top').$edge('bottom').'<diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count(self::STYLES).'">'.$cellFormats.'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    protected function worksheetXml(): string
    {
        $columns = '';

        foreach ($this->columnWidths as $index => $width) {
            $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $styleIndexes = array_flip(array_keys(self::STYLES));
        $rows = '';

        foreach ($this->rows as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $rows .= '<row r="'.$rowNumber.'">';

            foreach ($cells as $columnIndex => $cell) {
                // Columns A to Z, plenty for these sheets.
                $rows .= '<c r="'.chr(65 + $columnIndex).$rowNumber.'" s="'.$styleIndexes[$cell['style']].'" t="inlineStr">'
                    .'<is><t xml:space="preserve">'.$this->escape($cell['text']).'</t></is></c>';
            }

            $rows .= '</row>';
        }

        return self::XML_DECLARATION
            .'<worksheet xmlns="'.self::SPREADSHEET_NAMESPACE.'">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .($columns === '' ? '' : '<cols>'.$columns.'</cols>')
            .'<sheetData>'.$rows.'</sheetData>'
            .'<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="portrait" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    /**
     * Escape text for XML, dropping control characters an XML file can't contain.
     */
    protected function escape(string $text): string
    {
        return htmlspecialchars(
            preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text),
            ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }

    /**
     * Pack files into an uncompressed zip archive, the container an .xlsx file is.
     *
     * @param  array<string, string>  $files  File contents keyed by path inside the archive.
     */
    protected function zip(array $files): string
    {
        $entries = '';
        $directory = '';

        foreach ($files as $path => $contents) {
            // Zip 2.0, no flags, stored as is, dated 1980-01-01 00:00, then checksum, sizes and name length.
            $header = pack('vvvvvVVVvv', 20, 0, 0, 0, 33, crc32($contents), strlen($contents), strlen($contents), strlen($path), 0);

            $directory .= pack('Vv', 0x02014B50, 20).$header.pack('vvvVV', 0, 0, 0, 0, strlen($entries)).$path;
            $entries .= pack('V', 0x04034B50).$header.$path.$contents;
        }

        return $entries.$directory.pack('VvvvvVVv', 0x06054B50, 0, 0, count($files), count($files), strlen($directory), strlen($entries), 0);
    }
}
