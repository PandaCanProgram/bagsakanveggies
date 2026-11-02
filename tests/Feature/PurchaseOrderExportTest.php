<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\PurchaseOrderWorkbook;
use App\Support\SimpleXlsx;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class PurchaseOrderExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchaser_view_has_purchase_checkboxes_for_the_date_across_all_pages(): void
    {
        $purchaser = User::factory()->purchaser()->create();
        $orders = PurchaseOrder::factory()->count(16)->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-11-02']);
        $otherDay = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-11-03']);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')
            ->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'view' => 'purchasers']))
            ->assertOk()->assertSee('Choose purchases to export')->assertSee('All purchases')->assertSee('Per purchaser')
            ->assertSee('name="purchases[]" value="'.$orders->first()->id.'"', false)
            ->assertSee('name="purchases[]" value="'.$orders->last()->id.'"', false)
            ->assertDontSee('name="purchases[]" value="'.$otherDay->id.'"', false)
            ->assertSee($orders->first()->number())->assertSee($orders->get(1)->number())
            ->assertSee('name="view" value="purchasers"', false);
    }

    #[RequiresPhpExtension('zip')]
    public function test_selected_purchases_keep_each_admin_estimate_and_exclude_unselected_or_other_dates(): void
    {
        $purchaser = User::factory()->purchaser()->create();
        $first = $this->purchase($purchaser, 'Carrots first trip', '100.00');
        $second = $this->purchase($purchaser, 'Carrots second trip', '120.00');
        $excluded = $this->purchase(User::factory()->purchaser()->create(), 'Excluded purchase', '999.00');
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $response = $this->get(route('admin.purchase-orders.export', [
            'date' => '2026-11-02', 'mode' => 'purchasers', 'selection' => 1, 'purchases' => [$first->id, $second->id],
        ]))->assertDownload('purchases-per-purchaser-2026-11-02.xlsx')->assertHeader('Content-Type', SimpleXlsx::MIME_TYPE);
        $parts = $this->parts($response->streamedContent());
        $sheet = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('Carrots first trip', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('Carrots second trip', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringNotContainsString($excluded->number(), implode('', $parts));
        $prices = [];
        foreach ($sheet->sheetData->row as $row) {
            if (isset($row->c[3]->v) && ! isset($row->c[3]->f)) {
                $prices[] = (float) $row->c[3]->v;
            }
        }
        $this->assertSame([100.0, 120.0], $prices);
        $lastRow = $sheet->sheetData->row[count($sheet->sheetData->row) - 1];
        $this->assertSame(440.0, (float) $lastRow->c[4]->v);
        $this->assertSame('portrait', (string) $sheet->pageSetup['orientation']);
    }

    #[RequiresPhpExtension('zip')]
    public function test_all_purchase_export_includes_only_the_filtered_date_and_preserves_different_prices(): void
    {
        $first = $this->purchase(User::factory()->purchaser()->create(), 'Same item', '100.00');
        $second = $this->purchase(User::factory()->purchaser()->create(), 'Same item', '150.00');
        $old = $this->purchase($first->purchaser, 'Other day only', '999.00', '2026-11-01');
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $response = $this->get(route('admin.purchase-orders.export', ['date' => '2026-11-02', 'mode' => 'all']))
            ->assertDownload('all-purchases-2026-11-02.xlsx');
        $parts = $this->parts($response->streamedContent());
        $this->assertStringContainsString($first->number(), implode('', $parts));
        $this->assertStringContainsString($second->number(), implode('', $parts));
        $this->assertStringNotContainsString($old->number(), implode('', $parts));
        $this->assertStringContainsString('<v>100</v>', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('<v>150</v>', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('<v>500</v>', $parts['xl/worksheets/sheet1.xml']);
        $summary = new SimpleXMLElement($parts['xl/worksheets/sheet2.xml']);
        $this->assertSame(4.0, (float) $summary->sheetData->row[4]->c[1]->v);
        $this->assertCount(5, $summary->sheetData->row);
    }

    #[RequiresPhpExtension('zip')]
    public function test_per_purchaser_export_contains_all_three_forms_and_keeps_deleted_account_history(): void
    {
        $first = $this->purchase(User::factory()->purchaser()->create(['name' => 'Buyer One']), 'Carrots', '100.00');
        $second = $this->purchase(User::factory()->purchaser()->create(['name' => 'Buyer Two']), 'Cabbage', '80.00');
        $second->purchaser->delete();
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')->get(route('admin.purchase-orders.export', [
            'all_dates' => 1, 'mode' => 'purchasers', 'selection' => 1, 'purchases' => [$first->id, $second->id],
        ]))->assertOk();
        $parts = $this->parts($response->streamedContent());
        $book = new SimpleXMLElement($parts['xl/workbook.xml']);
        $this->assertSame(['Unified picking list', 'Packing summary', 'Packing lists', 'Purchase details'], array_map(fn ($sheet) => (string) $sheet['name'], iterator_to_array($book->sheets->sheet, false)));
        foreach ([2, 3, 4] as $number) {
            $this->assertStringContainsString('Buyer One / '.$first->number(), $parts['xl/worksheets/sheet'.$number.'.xml']);
            $this->assertStringContainsString('Buyer Two / '.$second->number(), $parts['xl/worksheets/sheet'.$number.'.xml']);
        }
    }

    public function test_invalid_empty_duplicate_and_other_date_selections_are_rejected(): void
    {
        $mine = $this->purchase(User::factory()->purchaser()->create(), 'Carrots', '100.00');
        $otherDate = $this->purchase($mine->purchaser, 'Old carrots', '90.00', '2026-11-01');
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        foreach ([[], ['purchases' => []], ['purchases' => [$mine->id, $mine->id]], ['purchases' => [$otherDate->id]], ['purchases' => ['bad']], ['purchases' => 'all']] as $selection) {
            $this->getJson(route('admin.purchase-orders.export', [
                'date' => '2026-11-02', 'mode' => 'purchasers', 'selection' => 1, ...$selection,
            ]))->assertUnprocessable();
        }
        $this->getJson(route('admin.purchase-orders.export', ['date' => '2026-02-30', 'mode' => 'all']))->assertUnprocessable();
        $this->getJson(route('admin.purchase-orders.export', ['date' => '2026-10-01', 'mode' => 'all']))->assertUnprocessable();
    }

    public function test_guests_and_purchasers_cannot_export_admin_purchase_data(): void
    {
        $route = route('admin.purchase-orders.export', ['all_dates' => 1, 'mode' => 'all']);
        $this->get($route)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->purchaser()->create(), 'purchaser')->get($route)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create(), 'web')->get($route)->assertForbidden();
    }

    public function test_unnamed_purchases_keep_their_reference_and_date_in_the_checklist(): void
    {
        $buyer = User::factory()->purchaser()->create();
        $first = $this->purchase($buyer, 'First', '100');
        $second = $this->purchase($buyer, 'Second', '100');
        $tomorrow = $this->purchase($buyer, 'Tomorrow', '100', '2026-11-03');
        $groups = PurchaseOrderWorkbook::selectionGroups(PurchaseOrder::with('purchaser')->get());
        $this->assertStringStartsWith($first->number(), $groups[0]['purchases'][0]['label']);
        $this->assertStringStartsWith($second->number(), $groups[0]['purchases'][1]['label']);
        $this->assertStringStartsWith($tomorrow->number(), $groups[0]['purchases'][2]['label']);
    }

    #[RequiresPhpExtension('zip')]
    public function test_picking_totals_preserve_the_admins_individually_rounded_line_costs(): void
    {
        $buyer = User::factory()->purchaser()->create();
        $first = $this->purchase($buyer, 'Small item', '0.01');
        $second = $this->purchase($buyer, 'Small item', '0.01');
        $first->items()->update(['quantity' => '0.500']);
        $second->items()->update(['quantity' => '0.500']);
        $parts = $this->parts(PurchaseOrderWorkbook::build(PurchaseOrder::with(['purchaser', 'items'])->get(), false, 'November 2, 2026')->toString());
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $this->assertSame(1.0, (float) $picking->sheetData->row[4]->c[1]->v);
        $this->assertSame(0.01, (float) $picking->sheetData->row[4]->c[3]->v);
        $this->assertSame(0.02, (float) $picking->sheetData->row[4]->c[4]->v);
        $this->assertSame(0.02, (float) $picking->sheetData->row[5]->c[4]->v);
        $this->assertStringContainsString('Purchase details', (string) $picking->sheetData->row[4]->c[4]->f);
    }

    #[RequiresPhpExtension('zip')]
    public function test_template_keeps_bags_separate_from_kilos_and_preserves_remarks_and_safe_item_text(): void
    {
        $order = $this->purchase(User::factory()->purchaser()->create(['name' => '=Buyer & One']), '=HYPERLINK("bad")', '400.00');
        $order->items()->update(['unit' => 'bag', 'remarks' => 'Keep chilled & covered']);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'particulars' => '=HYPERLINK("bad")', 'unit' => 'kg', 'quantity' => '1.000', 'estimated_unit_price' => '200.00']);
        $parts = $this->parts(PurchaseOrderWorkbook::build(PurchaseOrder::with(['purchaser', 'items'])->get(), false, 'November 2, 2026')->toString());
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $this->assertSame('inlineStr', (string) $picking->sheetData->row[4]->c[0]['t']);
        $this->assertSame('=HYPERLINK("bad")', (string) $picking->sheetData->row[4]->c[0]->is->t);
        $this->assertSame('bag', (string) $picking->sheetData->row[4]->c[2]->is->t);
        $this->assertSame('kg', (string) $picking->sheetData->row[5]->c[2]->is->t);
        $this->assertSame(1000.0, (float) $picking->sheetData->row[6]->c[4]->v);
        $this->assertSame('9', (string) $picking->pageSetup['paperSize']);
        $this->assertStringContainsString('Keep chilled &amp; covered', $parts['xl/worksheets/sheet4.xml']);
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}]/u', implode('', $parts));
        foreach ($parts as $xml) {
            $this->assertTrue((new DOMDocument)->loadXML($xml));
        }
    }

    #[RequiresPhpExtension('zip')]
    public function test_many_purchases_use_readable_summary_sheets_and_two_packing_lists_per_page(): void
    {
        $buyer = User::factory()->purchaser()->create();
        for ($index = 0; $index < 6; $index++) {
            $this->purchase($buyer, 'Carrots', '100.00');
        }
        $parts = $this->parts(PurchaseOrderWorkbook::build(PurchaseOrder::with(['purchaser', 'items'])->get(), true, 'November 2, 2026')->toString());
        $book = new SimpleXMLElement($parts['xl/workbook.xml']);
        $this->assertCount(5, $book->sheets->sheet);
        $this->assertSame('Packing summary 2', (string) $book->sheets->sheet[2]['name']);
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $this->assertSame(12.0, (float) $picking->sheetData->row[4]->c[1]->v);
        $this->assertSame(1200.0, (float) $picking->sheetData->row[5]->c[4]->v);
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet4.xml']);
        $this->assertCount(2, $packing->rowBreaks->brk);
        foreach ($packing->sheetData->row as $row) {
            $this->assertLessThanOrEqual(409.5, (float) $row['ht']);
        }
    }

    #[RequiresPhpExtension('zip')]
    public function test_long_purchase_lists_continue_without_losing_items(): void
    {
        $order = PurchaseOrder::factory()->create();
        for ($index = 0; $index < 35; $index++) {
            PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'particulars' => 'Item '.$index.' '.str_repeat('long name ', 8)]);
        }
        $parts = $this->parts(PurchaseOrderWorkbook::build(PurchaseOrder::with(['purchaser', 'items'])->get(), false, 'All purchase dates')->toString());
        $picking = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $this->assertSame('0', (string) $picking->pageSetup['fitToHeight']);
        $packing = new SimpleXMLElement($parts['xl/worksheets/sheet3.xml']);
        $this->assertGreaterThan(0, count($packing->rowBreaks->brk));
        $items = 0;
        foreach ($packing->sheetData->row as $row) {
            if (isset($row->c[0]) && str_starts_with((string) $row->c[0]->is->t, 'Item ')) {
                $items++;
            }
        }
        $this->assertSame(35, $items);
    }

    private function purchase(User $buyer, string $name, string $price, string $date = '2026-11-02'): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->create(['purchaser_id' => $buyer->id, 'purchase_date' => $date]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'particulars' => $name, 'quantity' => '2.000', 'estimated_unit_price' => $price, 'actual_unit_price' => '777.00']);

        return $order;
    }

    /** @return array<string, string> */
    private function parts(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'purchase-export');
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
