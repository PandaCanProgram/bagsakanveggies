<?php

namespace Tests\Unit;

use App\Services\OrderSummaryWorkbook;
use Carbon\CarbonImmutable;
use DOMDocument;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use ZipArchive;

#[RequiresPhpExtension('zip')]
class OrderSummaryWorkbookTest extends TestCase
{
    public function test_template_contains_three_printable_forms_and_reconciles_bags_and_kilos(): void
    {
        $parts = $this->parts([
            $this->client(1, 'Barrio Fiesta', [['product' => 'Ampalaya', 'size' => '1 kg', 'qty' => 2.0], ['product' => 'Ampalaya', 'size' => 'bag', 'qty' => 1.0]]),
            $this->client(2, "Mama Lou's", [['product' => 'Ampalaya', 'size' => '1 kg', 'qty' => 3.0], ['product' => 'Ampalaya', 'size' => 'bag', 'qty' => 2.0]]),
            $this->client(3, 'St. Ali', [['product' => 'Ampalaya', 'size' => 'bag', 'qty' => 1.0]]),
        ]);
        $workbook = new SimpleXMLElement($parts['xl/workbook.xml']);
        $names = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $names[] = (string) $sheet['name'];
        }
        $this->assertSame(['Unified picking list', 'Packing summary', 'Packing lists'], $names);
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $summary = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet3.xml']);
        $rows = [];

        foreach ($summary->sheetData->row as $row) {
            if (isset($row->c[0]) && (string) $row->c[0]->is->t === 'Ampalaya') {
                $rows[(string) $row->c[2]->is->t] = (float) $row->c[1]->v;
                $this->assertSame('SUM(D'.$row['r'].':F'.$row['r'].')', (string) $row->c[1]->f);
            }
        }

        $this->assertSame(['bag' => 4.0, 'kg' => 5.0], $rows);
        $this->assertSame('9', (string) $picking->pageSetup['paperSize']);
        $this->assertSame('1', (string) $picking->pageSetup['fitToWidth']);
        $this->assertCount(1, $packing->rowBreaks->brk);
        $this->assertStringContainsString('Barrio Fiesta', $parts['xl/worksheets/sheet3.xml']);
        $this->assertStringContainsString('Mama Lou', $parts['xl/worksheets/sheet3.xml']);
        $this->assertStringContainsString('St. Ali', $parts['xl/worksheets/sheet3.xml']);
        $this->assertStringContainsString('&apos;Packing summary&apos;!', $parts['xl/worksheets/sheet3.xml']);
        $this->assertStringContainsString('ROUND(B', $parts['xl/worksheets/sheet1.xml']);

        foreach ($parts as $path => $xml) {
            $this->assertTrue((new DOMDocument)->loadXML($xml), $path);
        }
    }

    public function test_buying_prices_stay_blank_and_item_names_are_safe_text_without_added_icons(): void
    {
        $parts = $this->parts([$this->client(1, '=unsafe & carrots', [['product' => '=HYPERLINK("bad")', 'size' => '1 kg', 'qty' => 0.5]])]);
        $sheet = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $itemRow = $sheet->sheetData->row[4];
        $this->assertSame('inlineStr', (string) $itemRow->c[0]['t']);
        $this->assertSame('=HYPERLINK("bad")', (string) $itemRow->c[0]->is->t);
        $this->assertSame('', (string) $itemRow->c[3]->is->t);
        $this->assertSame('', (string) $itemRow->c[4]->v);
        $this->assertStringContainsString('IF(COUNT(D5:D5)&lt;COUNTA(A5:A5)', $parts['xl/worksheets/sheet1.xml']);
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}]/u', implode('', $parts));
    }

    public function test_many_clients_are_split_into_readable_summary_sheets_and_paired_packing_pages(): void
    {
        $clients = [];
        for ($id = 1; $id <= 6; $id++) {
            $clients[] = $this->client($id, 'Client '.$id, [['product' => 'Carrots', 'size' => '1 kg', 'qty' => (float) $id]]);
        }
        $parts = $this->parts($clients);
        $workbook = new SimpleXMLElement($parts['xl/workbook.xml']);
        $this->assertCount(4, $workbook->sheets->sheet);
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $this->assertSame(21.0, (float) $picking->sheetData->row[4]->c[1]->v);
        $this->assertStringContainsString('Packing summary 2', (string) $picking->sheetData->row[4]->c[1]->f);
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet4.xml']);
        $this->assertCount(2, $packing->rowBreaks->brk);
        foreach ($packing->sheetData->row as $row) {
            $this->assertLessThanOrEqual(409.5, (float) $row['ht']);
        }
    }

    public function test_long_packing_lists_continue_without_losing_items_or_shrinking_the_picking_page(): void
    {
        $lines = [];
        for ($index = 0; $index < 35; $index++) {
            $lines[] = ['product' => 'Item '.$index.' '.str_repeat('long name ', 8), 'size' => '1 kg', 'qty' => 1.0];
        }
        $parts = $this->parts([$this->client(1, 'Long client list', $lines)]);
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet3.xml']);
        $this->assertSame('0', (string) $picking->pageSetup['fitToHeight']);
        $this->assertGreaterThan(0, count($packing->rowBreaks->brk));
        $items = 0;
        foreach ($packing->sheetData->row as $row) {
            if (isset($row->c[0]) && str_starts_with((string) $row->c[0]->is->t, 'Item ')) {
                $items++;
            }
        }
        $this->assertSame(35, $items);
    }

    /** @param list<array<string, mixed>> $people @return array<string, string> */
    private function parts(array $people): array
    {
        $bytes = OrderSummaryWorkbook::build(CarbonImmutable::parse('2026-11-02', 'Asia/Manila'), $people)->toString();
        $path = tempnam(sys_get_temp_dir(), 'summary-xlsx');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CHECKCONS));
        $parts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $parts[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
        }
        $zip->close();
        unlink($path);

        return $parts;
    }

    /** @param list<array{product: string, size: string, qty: float}> $lines @return array<string, mixed> */
    private function client(int $id, string $name, array $lines): array
    {
        return ['name' => $name, 'orders' => [['id' => $id, 'time' => '8:00 AM']], 'lines' => $lines];
    }
}
