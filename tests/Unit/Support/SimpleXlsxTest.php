<?php

namespace Tests\Unit\Support;

use App\Support\SimpleXlsx;
use DOMDocument;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[RequiresPhpExtension('zip')]
class SimpleXlsxTest extends TestCase
{
    /**
     * The files inside an .xlsx, keyed by path, read with PHP's own zip reader.
     *
     * @return array<string, string>
     */
    protected function unzip(string $xlsx): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $xlsx);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CHECKCONS), 'The file is not a valid zip archive.');
        $parts = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $parts[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
        }

        $zip->close();
        unlink($path);

        return $parts;
    }

    public function test_builds_a_valid_zip_of_the_well_formed_parts_excel_needs(): void
    {
        $sheet = (new SimpleXlsx('Order summary', [36, 22]))
            ->addRow(['DAILY ORDER SUMMARY'], 'title')
            ->addRow(['Product', 'Total Qty Ordered'], ['header', 'header-right']);

        $parts = $this->unzip($sheet->toString());

        $this->assertSame([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ], array_keys($parts));

        foreach ($parts as $path => $xml) {
            $this->assertTrue((new DOMDocument)->loadXML($xml), "{$path} is not well-formed XML.");
        }
    }

    public function test_escapes_markup_and_drops_characters_xml_cannot_hold(): void
    {
        $sheet = (new SimpleXlsx)->addRow(['Okra & <Sili> "Labuyo"', "Tab\tstays, bell\x07 goes"]);

        $worksheet = simplexml_load_string($this->unzip($sheet->toString())['xl/worksheets/sheet1.xml']);

        $this->assertSame('Okra & <Sili> "Labuyo"', (string) $worksheet->sheetData->row->c[0]->is->t);
        $this->assertSame("Tab\tstays, bell goes", (string) $worksheet->sheetData->row->c[1]->is->t);
    }

    public function test_sheet_name_drops_characters_excel_forbids_and_keeps_31_at_most(): void
    {
        $sheet = new SimpleXlsx('Orders: 10/01 [draft] for the whole week ahead');

        $workbook = simplexml_load_string($this->unzip($sheet->toString())['xl/workbook.xml']);

        $this->assertSame('Orders 1001 draft for the whole', (string) $workbook->sheets->sheet['name']);
    }

    public function test_quantities_keep_native_numeric_precision_and_use_general_format_without_a_trailing_decimal_point(): void
    {
        $sheet = (new SimpleXlsx)->addRow([5, 3.997, 0.5, 0, SimpleXlsx::formula('SUM(A1:B1)', 8.997)], 'quantity');
        $parts = $this->unzip($sheet->toString());
        $worksheet = simplexml_load_string($parts['xl/worksheets/sheet1.xml']);
        $styles = simplexml_load_string($parts['xl/styles.xml']);
        $values = [];

        foreach ($worksheet->sheetData->row->c as $cell) {
            $values[] = (string) $cell->v;
            $format = $styles->cellXfs->xf[(int) $cell['s']];
            $this->assertSame('0', (string) $format['numFmtId']);
            $this->assertSame('center', (string) $format->alignment['horizontal']);
            $this->assertNotSame('inlineStr', (string) $cell['t']);
        }

        $this->assertSame(['5', '3.997', '0.5', '0', '8.997'], $values);
    }

    public function test_money_and_totals_use_peso_currency_while_units_have_space_from_quantities(): void
    {
        $sheet = (new SimpleXlsx)->addRow([400, SimpleXlsx::formula('A1*2', 800), 'kg'], ['money', 'money-total', 'unit']);
        $parts = $this->unzip($sheet->toString());
        $worksheet = simplexml_load_string($parts['xl/worksheets/sheet1.xml']);
        $styles = simplexml_load_string($parts['xl/styles.xml']);
        $numberFormats = [];

        foreach ($styles->numFmts->numFmt as $format) {
            $numberFormats[(int) $format['numFmtId']] = (string) $format['formatCode'];
        }

        foreach ([0, 1] as $index) {
            $cell = $worksheet->sheetData->row->c[$index];
            $format = $styles->cellXfs->xf[(int) $cell['s']];
            $this->assertSame('"'."\u{20B1}".'"#,##0.00', $numberFormats[(int) $format['numFmtId']]);
            $this->assertSame('right', (string) $format->alignment['horizontal']);
            $this->assertNotSame('inlineStr', (string) $cell['t']);
        }

        $unit = $styles->cellXfs->xf[(int) $worksheet->sheetData->row->c[2]['s']];
        $this->assertSame('left', (string) $unit->alignment['horizontal']);
        $this->assertSame('1', (string) $unit->alignment['indent']);
    }
}
