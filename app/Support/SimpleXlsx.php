<?php

namespace App\Support;

/**
 * Excel (.xlsx) worksheets with text, numbers, formulas and print layouts.
 *
 * The file is zipped by hand (uncompressed), so downloads work on any host, even one without PHP's zip extension.
 */
class SimpleXlsx
{
    public const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Cell styles by name: [font, fill, border, horizontal alignment], pointing into the lists in styles.xml.
     *
     * @var array<string, array{0: int, 1: int, 2: int, 3: string|null, 4?: int}>
     */
    protected const STYLES = [
        'default' => [0, 0, 0, null],
        'title' => [2, 0, 0, null],
        'bold' => [1, 0, 0, null],
        'header' => [1, 2, 1, null],
        'header-right' => [1, 2, 1, 'right'],
        'cell' => [0, 0, 1, null],
        'cell-right' => [0, 0, 1, 'right'],
        'total' => [1, 0, 1, null],
        'total-right' => [1, 0, 1, 'right'],
        'template-title' => [2, 0, 0, 'center'],
        'template-header' => [1, 3, 1, 'center'],
        'quantity' => [0, 0, 1, 'center'],
        'money' => [0, 0, 1, 'right', 165],
        'money-total' => [1, 0, 1, 'right', 165],
        'unit' => [0, 0, 1, 'left'],
    ];

    protected const XML_DECLARATION = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    protected const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    protected const PACKAGE_RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    protected const DOCUMENT_RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @var list<list<array{value: string|int|float|null, style: string, formula: string|null}>>
     */
    protected array $rows = [];

    /** @var list<self> */
    protected array $sheets = [];

    /** @var list<string> */
    protected array $merges = [];

    /** @var array<int, float> */
    protected array $rowHeights = [];

    /** @var list<int> */
    protected array $pageBreaks = [];

    protected ?int $paperSize = null;

    protected bool $singlePage = false;

    protected int $repeatRows = 0;

    protected string $orientation = 'portrait';

    protected ?int $printScale = null;

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
     * @param  list<string|int|float|null|array{formula: string, value: string|int|float|null}>  $texts
     * @param  string|list<string>  $styles  Names from STYLES.
     */
    public function addRow(array $texts = [], string|array $styles = 'default', ?float $height = null): static
    {
        $cells = [];

        foreach (array_values($texts) as $index => $text) {
            $cells[] = [
                'value' => is_array($text) ? $text['value'] : $text,
                'formula' => is_array($text) ? $text['formula'] : null,
                'style' => is_array($styles) ? ($styles[$index] ?? 'default') : $styles,
            ];
        }

        $this->rows[] = $cells;

        if ($height !== null) {
            $this->rowHeights[count($this->rows)] = $height;
        }

        return $this;
    }

    /** @return array{formula: string, value: string|int|float|null} */
    public static function formula(string $formula, string|int|float|null $value = null): array
    {
        return ['formula' => ltrim($formula, '='), 'value' => $value];
    }

    public function addSheet(self $sheet): static
    {
        if ($sheet === $this) {
            throw new \InvalidArgumentException('A worksheet cannot contain itself.');
        }

        $this->sheets[] = $sheet;

        return $this;
    }

    public function merge(string $range): static
    {
        $this->merges[] = $range;

        return $this;
    }

    public function forPrint(int $paperSize = 9, bool $singlePage = false, int $repeatRows = 0, string $orientation = 'portrait', ?int $scale = null): static
    {
        $this->paperSize = $paperSize;
        $this->singlePage = $singlePage;
        $this->repeatRows = $repeatRows;
        $this->orientation = $orientation;
        $this->printScale = $scale;

        return $this;
    }

    public function breakBeforeRow(int $row): static
    {
        $this->pageBreaks[] = $row - 1;

        return $this;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public static function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + $number % 26).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /**
     * The contents of the .xlsx file.
     */
    public function toString(): string
    {
        $sheets = [$this, ...$this->sheets];
        $relationships = '';

        foreach ($sheets as $index => $sheet) {
            $relationships .= '<Relationship Id="rId'.($index + 1).'" Type="'.self::DOCUMENT_RELATIONSHIPS_NAMESPACE.'/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        $relationships .= '<Relationship Id="rId'.(count($sheets) + 1).'" Type="'.self::DOCUMENT_RELATIONSHIPS_NAMESPACE.'/styles" Target="styles.xml"/>';
        $files = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relationshipsXml(['officeDocument' => 'xl/workbook.xml']),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => self::XML_DECLARATION.'<Relationships xmlns="'.self::PACKAGE_RELATIONSHIPS_NAMESPACE.'">'.$relationships.'</Relationships>',
            'xl/styles.xml' => $this->stylesXml(),
        ];

        foreach ($sheets as $index => $sheet) {
            $files['xl/worksheets/sheet'.($index + 1).'.xml'] = $sheet->worksheetXml();
        }

        return $this->zip($files);
    }

    protected function contentTypesXml(): string
    {
        $spreadsheetType = 'application/vnd.openxmlformats-officedocument.spreadsheetml';
        $worksheets = '';

        foreach ([$this, ...$this->sheets] as $index => $sheet) {
            $worksheets .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="'.$spreadsheetType.'.worksheet+xml"/>';
        }

        return self::XML_DECLARATION
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="'.$spreadsheetType.'.sheet.main+xml"/>'
            .$worksheets
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
        $sheetXml = '';
        $definedNames = '';
        $usedNames = [];

        foreach ([$this, ...$this->sheets] as $index => $sheet) {
            $baseName = trim(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $sheet->sheetName), 0, 31), "'") ?: 'Sheet';
            $sheetName = $baseName;
            $suffix = 2;

            while (in_array(mb_strtolower($sheetName), $usedNames, true)) {
                $ending = ' '.$suffix++;
                $sheetName = mb_substr($baseName, 0, 31 - mb_strlen($ending)).$ending;
            }

            $usedNames[] = mb_strtolower($sheetName);
            $sheetXml .= '<sheet name="'.$this->escape($sheetName).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
            $quotedName = "'".str_replace("'", "''", $sheetName)."'";

            if ($sheet->paperSize !== null) {
                $columns = max(1, count($sheet->columnWidths), ...array_map('count', $sheet->rows));
                $definedNames .= '<definedName name="_xlnm.Print_Area" localSheetId="'.$index.'">'.$this->escape($quotedName.'!$A$1:$'.self::columnName($columns).'$'.max(1, count($sheet->rows))).'</definedName>';
            }

            if ($sheet->repeatRows > 0) {
                $definedNames .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.$index.'">'.$this->escape($quotedName.'!$1:$'.$sheet->repeatRows).'</definedName>';
            }
        }

        return self::XML_DECLARATION
            .'<workbook xmlns="'.self::SPREADSHEET_NAMESPACE.'" xmlns:r="'.self::DOCUMENT_RELATIONSHIPS_NAMESPACE.'">'
            .'<sheets>'.$sheetXml.'</sheets>'
            .($definedNames ? '<definedNames>'.$definedNames.'</definedNames>' : '')
            .'<calcPr calcId="191029" fullCalcOnLoad="1"/>'
            .'</workbook>';
    }

    protected function stylesXml(): string
    {
        $edge = fn (string $side) => '<'.$side.' style="thin"><color rgb="FF8C9A8F"/></'.$side.'>';
        $cellFormats = '';

        foreach (self::STYLES as $name => $style) {
            [$font, $fill, $border, $alignment] = $style;
            $cellFormats .= '<xf numFmtId="'.($style[4] ?? 0).'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
                .'<alignment vertical="center" wrapText="1"'.($alignment ? ' horizontal="'.$alignment.'"' : '').($name === 'unit' ? ' indent="1"' : '').'/></xf>';
        }

        return self::XML_DECLARATION
            .'<styleSheet xmlns="'.self::SPREADSHEET_NAMESPACE.'">'
            .'<numFmts count="1"><numFmt numFmtId="165" formatCode="&quot;&#8369;&quot;#,##0.00"/></numFmts>'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE3F3E1"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE7E6E6"/></patternFill></fill>'
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
            $rows .= '<row r="'.$rowNumber.'"'.(isset($this->rowHeights[$rowNumber]) ? ' ht="'.$this->rowHeights[$rowNumber].'" customHeight="1"' : '').'>';

            foreach ($cells as $columnIndex => $cell) {
                $reference = self::columnName($columnIndex + 1).$rowNumber;
                $prefix = '<c r="'.$reference.'" s="'.$styleIndexes[$cell['style']].'"';

                if ($cell['formula'] !== null) {
                    $rows .= $prefix.(is_string($cell['value']) ? ' t="str"' : '').'><f>'.$this->escape($cell['formula']).'</f>'
                        .($cell['value'] !== null ? '<v>'.$this->escape((string) $cell['value']).'</v>' : '').'</c>';
                } elseif (is_int($cell['value']) || is_float($cell['value'])) {
                    $rows .= $prefix.'><v>'.$cell['value'].'</v></c>';
                } else {
                    $rows .= $prefix.' t="inlineStr"><is><t xml:space="preserve">'.$this->escape($cell['value'] ?? '').'</t></is></c>';
                }
            }

            $rows .= '</row>';
        }

        $merges = implode('', array_map(fn (string $range): string => '<mergeCell ref="'.$this->escape($range).'"/>', $this->merges));
        $breaks = implode('', array_map(fn (int $row): string => '<brk id="'.$row.'" min="0" max="16383" man="1"/>', $this->pageBreaks));

        return self::XML_DECLARATION
            .'<worksheet xmlns="'.self::SPREADSHEET_NAMESPACE.'">'
            .'<sheetPr><pageSetUpPr fitToPage="'.($this->printScale === null ? 1 : 0).'"/></sheetPr>'
            .($columns === '' ? '' : '<cols>'.$columns.'</cols>')
            .'<sheetData>'.$rows.'</sheetData>'
            .($merges ? '<mergeCells count="'.count($this->merges).'">'.$merges.'</mergeCells>' : '')
            .'<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="'.$this->escape($this->orientation).'"'
            .($this->printScale === null ? ' fitToWidth="1" fitToHeight="'.($this->singlePage ? 1 : 0).'"' : ' scale="'.$this->printScale.'"')
            .($this->paperSize !== null ? ' paperSize="'.$this->paperSize.'"' : '').'/>'
            .($breaks ? '<rowBreaks count="'.count($this->pageBreaks).'" manualBreakCount="'.count($this->pageBreaks).'">'.$breaks.'</rowBreaks>' : '')
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
