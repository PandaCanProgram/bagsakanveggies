<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DailyOrderSummary;
use Carbon\CarbonImmutable;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

#[RequiresPhpExtension('zip')]
class CustomerOrderExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_exports_use_only_selected_customers_and_include_the_three_fulfillment_forms(): void
    {
        $carrots = $this->product('Carrots');
        $okra = $this->product('Okra');
        $first = $this->order('Juan', '09171234567', [[$carrots, 'kg', 1, 100]]);
        $first->update(['delivery_fee' => 50, 'total' => 150]);
        $this->order('Juan', '0917-123-4567', [[$carrots, 'kg', 2, 100]]);
        $this->order('Maria excluded', '09281234567', [[$okra, 'kg', 4, 100]]);
        $this->order('Tomorrow excluded', '09991234567', [[$okra, 'kg', 10, 100]], '2026-11-06 00:01');
        PurchaseOrder::factory()->hasItems(1, ['particulars' => 'PO item excluded'])->create(['purchase_date' => '2026-11-05']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        foreach (['admin.order-summary.export', 'admin.orders-per-person.export'] as $route) {
            $response = $this->get(route($route, ['date' => '2026-11-05', 'selection' => 1, 'people' => [$first->id]]))->assertOk();
            $parts = $this->parts($response->streamedContent());
            $book = new SimpleXMLElement($parts['xl/workbook.xml']);
            $names = array_map(fn ($sheet): string => (string) $sheet['name'], iterator_to_array($book->sheets->sheet, false));
            $this->assertSame([$route === 'admin.order-summary.export' ? 'Order summary' : 'Orders per person', 'Unified picking list', 'Packing summary', 'Packing lists'], $names);
            foreach (['Maria excluded', 'Tomorrow excluded', 'Okra', 'PO item excluded', 'Purchase details'] as $excluded) {
                $this->assertStringNotContainsString($excluded, implode('', $parts));
            }
            $picking = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
            $this->assertSame(3.0, (float) $picking->sheetData->row[4]->c[1]->v);
            $this->assertSame(300.0, (float) $picking->sheetData->row[5]->c[4]->v);
            $matrix = new SimpleXMLElement($parts['xl/worksheets/sheet3.xml']);
            $this->assertSame('Juan', (string) $matrix->sheetData->row[3]->c[3]->is->t);
            $this->assertSame(3.0, (float) $matrix->sheetData->row[4]->c[3]->v);
            $this->assertStringContainsString('Juan', $parts['xl/worksheets/sheet4.xml']);
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}]/u', implode('', $parts));
        }
    }

    public function test_customers_sharing_a_phone_can_be_selected_independently_in_every_export_sheet(): void
    {
        $pandaItem = $this->product('Panda item');
        $anthonyItem = $this->product('Anthony item');
        $panda = $this->order('Engr. Panda', '09171234567', [[$pandaItem, 'kg', 1, 100]]);
        $anthony = $this->order('anthony', '09171234567', [[$anthonyItem, 'kg', 2, 100]]);
        $this->order(' ANTHONY ', '0917-123-4567', [[$anthonyItem, 'kg', 3, 100]]);
        $this->order('anthony', '09991234567', [[$this->product('Other phone item'), 'kg', 10, 100]]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        foreach (['admin.order-summary.export', 'admin.orders-per-person.export'] as $route) {
            foreach ([[$panda, 'Panda item', 'Anthony item', 1.0], [$anthony, 'Anthony item', 'Panda item', 5.0]] as [$selected, $included, $excluded, $qty]) {
                $response = $this->get(route($route, ['date' => '2026-11-05', 'selection' => 1, 'people' => [$selected->id]]))->assertOk();
                $parts = $this->parts($response->streamedContent());
                foreach ([2, 3, 4] as $sheet) {
                    $this->assertStringContainsString($included, $parts['xl/worksheets/sheet'.$sheet.'.xml']);
                }
                $this->assertStringNotContainsString($excluded, implode('', $parts));
                $this->assertStringNotContainsString('Other phone item', implode('', $parts));
                $picking = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
                $this->assertSame($qty, (float) $picking->sheetData->row[4]->c[1]->v);
                $this->assertSame($qty * 100, (float) $picking->sheetData->row[5]->c[4]->v);
            }
        }
    }

    public function test_all_customers_need_no_po_and_the_export_respects_the_store_day(): void
    {
        $product = $this->product('Sayote');
        $this->order('Yesterday', '09000000001', [[$product, 'kg', 50, 100]], '2026-11-04 23:59');
        $this->order('Early customer', '09000000002', [[$product, 'kg', 1, 100]], '2026-11-05 00:01');
        $this->order('Late customer', '09000000003', [[$product, 'kg', 2, 100]], '2026-11-05 23:59');
        $this->order('Tomorrow', '09000000004', [[$product, 'kg', 50, 100]], '2026-11-06 00:01');
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.order-summary.export', ['date' => '2026-11-05']))->assertDownload('order-summary-2026-11-05.xlsx');
        $parts = $this->parts($response->streamedContent());
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
        $this->assertSame(3.0, (float) $picking->sheetData->row[4]->c[1]->v);
        $this->assertStringContainsString('Early customer', $parts['xl/worksheets/sheet3.xml']);
        $this->assertStringContainsString('Late customer', $parts['xl/worksheets/sheet3.xml']);
        $this->assertStringNotContainsString('Yesterday', implode('', $parts));
        $this->assertStringNotContainsString('Tomorrow', implode('', $parts));
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_cancelled_orders_disappear_from_all_fulfillment_sheets_and_stale_selection_is_rejected(): void
    {
        $cancelled = $this->order('Cancelled customer', '09000000001', [[$this->product('Cancelled item'), 'kg', 2, 100]]);
        $this->order('Remaining customer', '09000000002', [[$this->product('Remaining item'), 'kg', 1, 100]]);
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->delete(route('admin.orders.destroy', $cancelled))->assertRedirect();
        $parts = $this->parts($this->get(route('admin.order-summary.export', ['date' => '2026-11-05']))->assertOk()->streamedContent());
        $this->assertStringNotContainsString('Cancelled customer', implode('', $parts));
        $this->assertStringNotContainsString('Cancelled item', implode('', $parts));
        foreach ([2, 3, 4] as $sheet) {
            $this->assertStringContainsString('Remaining item', $parts['xl/worksheets/sheet'.$sheet.'.xml']);
        }
        $this->getJson(route('admin.order-summary.export', ['date' => '2026-11-05', 'selection' => 1, 'people' => [$cancelled->id]]))
            ->assertUnprocessable();
    }

    public function test_prices_and_sizes_stay_separate_and_packing_combines_repeat_orders(): void
    {
        $product = $this->product('Carrots');
        $this->order('Juan', '09171234567', [[$product, '1 kg', 1.5, 100]]);
        $this->order('Juan', '09171234567', [[$product, '1 kg', 2, 120], [$product, '10 kg bag', 1, 700]]);
        $product->update(['name' => 'Baguio Carrots', 'variants' => [['label' => '1 kg', 'price' => 999]]]);
        $parts = $this->exportParts();
        $sheet = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
        $prices = [];
        foreach ($sheet->sheetData->row as $row) {
            if ((string) ($row->c[0]->is->t ?? '') === 'Baguio Carrots') {
                $prices[(string) $row->c[2]->is->t][] = [(float) $row->c[1]->v, (float) $row->c[3]->v, (float) $row->c[4]->v];
            }
        }
        $this->assertSame([[1.5, 100.0, 150.0], [2.0, 120.0, 240.0]], $prices['1 kg']);
        $this->assertSame([[1.0, 700.0, 700.0]], $prices['10 kg bag']);
        $this->assertSame(1090.0, (float) $sheet->sheetData->row[count($sheet->sheetData->row) - 1]->c[4]->v);
        $matrix = new SimpleXMLElement($parts['xl/worksheets/sheet3.xml']);
        $quantities = [];
        foreach ($matrix->sheetData->row as $row) {
            if ((string) ($row->c[0]->is->t ?? '') === 'Baguio Carrots') {
                $quantities[(string) $row->c[2]->is->t] = (float) $row->c[3]->v;
            }
        }
        $this->assertSame(['1 kg' => 3.5, '10 kg bag' => 1.0], $quantities);
    }

    public function test_only_per_person_opens_customer_selection_and_invalid_export_choices_are_rejected(): void
    {
        $product = $this->product('Sayote');
        $first = $this->order('Juan', '09171234567', [[$product, 'kg', 1, 100]]);
        $repeat = $this->order('Juan', '09171234567', [[$product, 'kg', 2, 100]]);
        $otherDay = $this->order('Tomorrow', '09991234567', [[$product, 'kg', 10, 100]], '2026-11-06 00:01');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['' => 'admin.order-summary.export', 'people' => 'admin.orders-per-person.export'] as $view => $route) {
            $response = $this->get(route('admin.home', ['date' => '2026-11-05', 'view' => $view]))->assertOk();
            if ($view === 'people') {
                $response->assertSee('action="'.route($route).'"', false)
                    ->assertSee('name="people[]" value="'.$first->id.'"', false)
                    ->assertDontSee('name="people[]" value="'.$repeat->id.'"', false)->assertDontSeeText('Tomorrow');
            } else {
                $response->assertSee('href="'.e(route($route, ['date' => '2026-11-05'])).'"', false)
                    ->assertDontSee('id="person-export-dialog"', false)
                    ->assertDontSee('name="people[]"', false);
            }
            foreach ([[], ['people' => []], ['people' => [$first->id, $first->id]], ['people' => [$repeat->id]], ['people' => [$otherDay->id]], ['people' => 'all']] as $selection) {
                $this->getJson(route($route, ['date' => '2026-11-05', 'selection' => 1, ...$selection]))->assertUnprocessable();
            }
        }
    }

    public function test_all_orders_downloads_every_customer_with_separate_packing_columns_and_lists(): void
    {
        $this->order('Engr. Panda', '09171234567', [[$this->product('Carrots'), 'kg', 1, 100]]);
        $this->order('anthony', '09171234567', [[$this->product('Okra'), 'kg', 2, 100]]);
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.order-summary.export', ['date' => '2026-11-05']))
            ->assertDownload('order-summary-2026-11-05.xlsx');
        $parts = $this->parts($response->streamedContent());
        $matrix = new SimpleXMLElement($parts['xl/worksheets/sheet3.xml']);
        $this->assertSame('Engr. Panda', (string) $matrix->sheetData->row[3]->c[3]->is->t);
        $this->assertSame('anthony', (string) $matrix->sheetData->row[3]->c[4]->is->t);
        $this->assertSame(1.0, (float) $matrix->sheetData->row[4]->c[3]->v);
        $this->assertSame(2.0, (float) $matrix->sheetData->row[5]->c[4]->v);
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet4.xml']);
        $this->assertSame('Engr. Panda', (string) $packing->sheetData->row[1]->c[0]->is->t);
        $this->assertSame('anthony', (string) $packing->sheetData->row[1]->c[4]->is->t);
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
        $this->assertSame(300.0, (float) $picking->sheetData->row[6]->c[4]->v);
    }

    public function test_many_customers_have_readable_summary_sheets_and_two_packing_lists_per_page(): void
    {
        $product = $this->product('Carrots');
        for ($index = 0; $index < 6; $index++) {
            $this->order('Customer '.$index, '0900000000'.$index, [[$product, 'kg', 2, 100]]);
        }
        $parts = $this->exportParts();
        $book = new SimpleXMLElement($parts['xl/workbook.xml']);
        $this->assertCount(5, $book->sheets->sheet);
        $this->assertSame('Packing summary 2', (string) $book->sheets->sheet[3]['name']);
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet5.xml']);
        $this->assertCount(2, $packing->rowBreaks->brk);
        $this->assertSame('0', (string) $packing->sheetPr->pageSetUpPr['fitToPage']);
        $this->assertSame('90', (string) $packing->pageSetup['scale']);
        foreach ($packing->sheetData->row as $row) {
            if (count($row->c) === 0) {
                $this->assertLessThanOrEqual(12, (float) $row['ht']);
            }
        }
    }

    public function test_odd_packing_lists_have_no_empty_table_and_preserve_number_and_money_formats(): void
    {
        $carrots = $this->product('Carrots');
        $okra = $this->product('Okra');
        $this->order('First', '09000000001', [[$carrots, 'kg', 3.997, 100], [$okra, 'bag', 1, 100]]);
        $this->order('Second', '09000000002', [[$carrots, 'kg', 1, 100]]);
        $this->order('Third', '09000000003', [[$okra, 'bag', 2, 100]]);
        $okra->delete();
        $parts = $this->exportParts();
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet4.xml']);
        $styles = new SimpleXMLElement($parts['xl/styles.xml']);
        $this->assertCount(1, $packing->rowBreaks->brk);
        $lastPage = (int) $packing->rowBreaks->brk['id'] + 1;
        foreach ($packing->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                if ((int) $row['r'] >= $lastPage) {
                    $this->assertMatchesRegularExpression('/^[ABC]\d+$/', (string) $cell['r']);
                }
                if (isset($cell->v)) {
                    $style = $styles->cellXfs->xf[(int) $cell['s']];
                    $this->assertSame('center', (string) $style->alignment['horizontal']);
                    $this->assertSame('0', (string) $style['numFmtId']);
                }
            }
        }
        foreach ($packing->mergeCells->mergeCell as $merge) {
            if (preg_match('/^E(\d+):/', (string) $merge['ref'], $match)) {
                $this->assertLessThan($lastPage, (int) $match[1]);
            }
        }
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
        $this->assertSame('165', (string) $styles->cellXfs->xf[(int) $picking->sheetData->row[4]->c[3]['s']]['numFmtId']);
        $this->assertStringContainsString('₱', (string) $styles->numFmts->numFmt['formatCode']);
        $this->assertStringContainsString('<v>3.997</v>', $parts['xl/worksheets/sheet4.xml']);
        $this->assertStringContainsString('Okra', $parts['xl/worksheets/sheet2.xml']);
    }

    public function test_a_right_hand_continuation_moves_left_and_shares_the_next_page_with_the_next_customer(): void
    {
        $short = $this->product('Short item');
        $this->order('First hotel', '09000000001', [[$short, 'kg', 1, 100]]);
        $lines = [];
        for ($index = 0; $index < 35; $index++) {
            $lines[] = [$this->product('Hotel item '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)), 'kg', 1, 100];
        }
        $this->order('Long hotel', '09000000002', $lines);
        $this->order('Next hotel', '09000000003', [[$short, 'kg', 2, 100]]);
        $parts = $this->exportParts();
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet4.xml']);
        $this->assertCount(1, $packing->rowBreaks->brk);
        $secondPage = (int) $packing->rowBreaks->brk[0]['id'];
        $this->assertSame('PACKING LIST', (string) $packing->sheetData->row[$secondPage]->c[0]->is->t);
        $this->assertSame('Long hotel (continued)', (string) $packing->sheetData->row[$secondPage + 1]->c[0]->is->t);
        $this->assertSame('Next hotel', (string) $packing->sheetData->row[$secondPage + 1]->c[4]->is->t);
        $items = [];
        foreach ($packing->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $value = (string) ($cell->is->t ?? '');
                if (str_starts_with($value, 'Hotel item ')) {
                    $items[] = $value;
                }
            }
        }
        $this->assertCount(35, $items);
        $this->assertCount(35, array_unique($items));
    }

    public function test_both_long_lists_keep_their_items_when_one_finishes_before_the_other(): void
    {
        foreach ([35, 40, 1] as $customer => $count) {
            $lines = [];
            for ($index = 0; $index < $count; $index++) {
                $lines[] = [$this->product('C'.$customer.' item '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)), 'kg', 1, 100];
            }
            $this->order('Hotel '.$customer, '0900000000'.$customer, $lines);
        }
        $parts = $this->exportParts();
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet4.xml']);
        $this->assertCount(2, $packing->rowBreaks->brk);
        $thirdPage = (int) $packing->rowBreaks->brk[1]['id'];
        $this->assertSame('Hotel 1 (continued)', (string) $packing->sheetData->row[$thirdPage + 1]->c[0]->is->t);
        $this->assertSame('Hotel 2', (string) $packing->sheetData->row[$thirdPage + 1]->c[4]->is->t);
        $items = [];
        foreach ($packing->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $value = (string) ($cell->is->t ?? '');
                if (preg_match('/^C\d item /', $value)) {
                    $items[] = $value;
                }
            }
        }
        $this->assertCount(76, $items);
        $this->assertCount(76, array_unique($items));
    }

    public function test_long_customer_lists_continue_without_losing_items_and_keep_text_safe(): void
    {
        $lines = [];
        for ($index = 0; $index < 35; $index++) {
            $lines[] = [$this->product('Item '.$index.' '.str_repeat('long name ', 8)), 'kg', 1, 100];
        }
        $this->order('=Customer & One', '09000000001', $lines);
        $parts = $this->exportParts();
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet4.xml']);
        $this->assertGreaterThan(0, count($packing->rowBreaks->brk));
        $items = 0;
        foreach ($packing->sheetData->row as $row) {
            if (str_starts_with((string) ($row->c[0]->is->t ?? ''), 'Item ')) {
                $items++;
            }
            foreach ($row->c as $cell) {
                $this->assertMatchesRegularExpression('/^[ABC]\d+$/', (string) $cell['r']);
            }
        }
        $this->assertSame(35, $items);
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
        $this->assertSame('0', (string) $picking->pageSetup['fitToHeight']);
        $this->assertSame('inlineStr', (string) $packing->sheetData->row[1]->c[0]['t']);
        foreach ($parts as $part) {
            $this->assertTrue((new DOMDocument)->loadXML($part));
        }
    }

    /** @return array<string, string> */
    private function exportParts(): array
    {
        return $this->parts($this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.orders-per-person.export', ['date' => '2026-11-05']))->assertOk()->streamedContent());
    }

    private function product(string $name): Product
    {
        return Product::create(['name' => $name, 'variants' => [['label' => '1 kg', 'price' => 100]]]);
    }

    /** @param list<array{Product, string, float|int, int}> $lines */
    private function order(string $name, string $phone, array $lines, string $time = '2026-11-05 09:00'): Order
    {
        $total = array_sum(array_map(fn (array $line): float => round($line[2] * $line[3], 2), $lines));
        $createdAt = CarbonImmutable::parse($time, DailyOrderSummary::TIMEZONE)->setTimezone(config('app.timezone'));
        $order = Order::create([
            'full_name' => $name, 'contact_number' => $phone, 'delivery_address' => 'Test address',
            'payment_method' => 'GCash', 'subtotal' => $total, 'delivery_fee' => 0, 'total' => $total,
        ]);
        $order->forceFill(['created_at' => $createdAt])->save();
        foreach ($lines as [$product, $size, $quantity, $price]) {
            $order->items()->create([
                'product_id' => $product->id, 'product_name' => $product->name, 'variant_label' => $size,
                'qty' => $quantity, 'unit_price' => $price, 'line_total' => round($quantity * $price, 2),
            ]);
        }

        return $order;
    }

    /** @return array<string, string> */
    private function parts(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-export');
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
}
