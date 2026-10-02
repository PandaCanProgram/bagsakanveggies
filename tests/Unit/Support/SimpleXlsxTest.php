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
}
