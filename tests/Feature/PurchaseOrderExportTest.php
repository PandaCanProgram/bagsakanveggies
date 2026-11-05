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
    public function test_purchase_export_contains_only_details_for_selected_purchases(): void
    {
        $buyer = User::factory()->purchaser()->create(['name' => 'Buyer One']);
        $first = $this->purchase($buyer, 'Carrots first trip', '100.00');
        $second = $this->purchase($buyer, 'Carrots second trip', '120.00');
        $excluded = $this->purchase(User::factory()->purchaser()->create(), 'Excluded purchase', '999.00');
        $buyer->delete();
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')
            ->get(route('admin.purchase-orders.export', [
                'date' => '2026-11-02', 'mode' => 'purchasers', 'selection' => 1, 'purchases' => [$first->id, $second->id],
            ]))->assertDownload('purchases-per-purchaser-2026-11-02.xlsx');
        $parts = $this->parts($response->streamedContent());
        $book = new SimpleXMLElement($parts['xl/workbook.xml']);
        $this->assertCount(1, $book->sheets->sheet);
        $this->assertSame('Purchase details', (string) $book->sheets->sheet[0]['name']);
        $xml = $parts['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('Buyer One', $xml);
        $this->assertStringContainsString('Carrots first trip', $xml);
        $this->assertStringContainsString('Carrots second trip', $xml);
        $this->assertStringNotContainsString($excluded->number(), $xml);
        $this->assertStringNotContainsString('<v>777', $xml);
        $sheet = new SimpleXMLElement($xml);
        $rows = $sheet->sheetData->row;
        $this->assertSame(440.0, (float) $rows[count($rows) - 1]->c[4]->v);
        $this->assertSame('landscape', (string) $sheet->pageSetup['orientation']);
    }

    #[RequiresPhpExtension('zip')]
    public function test_all_purchase_export_filters_dates_and_preserves_estimates_and_remarks(): void
    {
        $first = $this->purchase(User::factory()->purchaser()->create(), '=HYPERLINK("bad")', '100.00');
        $first->items()->update(['remarks' => 'Keep chilled & covered', 'unit' => 'bag']);
        $second = $this->purchase(User::factory()->purchaser()->create(), 'Same item', '150.00');
        $old = $this->purchase($first->purchaser, 'Old item', '90.00', '2026-11-01');
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')
            ->get(route('admin.purchase-orders.export', ['date' => '2026-11-02', 'mode' => 'all']))
            ->assertDownload('all-purchases-2026-11-02.xlsx')->assertHeader('Content-Type', SimpleXlsx::MIME_TYPE);
        $parts = $this->parts($response->streamedContent());
        $xml = $parts['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString($first->number(), $xml);
        $this->assertStringContainsString($second->number(), $xml);
        $this->assertStringNotContainsString($old->number(), $xml);
        $this->assertStringContainsString('Keep chilled &amp; covered', $xml);
        $this->assertStringContainsString('bag', $xml);
        $sheet = new SimpleXMLElement($xml);
        $this->assertSame('inlineStr', (string) $sheet->sheetData->row[6]->c[0]['t']);
        $this->assertSame('=HYPERLINK("bad")', (string) $sheet->sheetData->row[6]->c[0]->is->t);
        $this->assertSame(500.0, (float) $sheet->sheetData->row[count($sheet->sheetData->row) - 1]->c[4]->v);
        foreach ($parts as $part) {
            $this->assertTrue((new DOMDocument)->loadXML($part));
        }
        $this->assertDoesNotMatchRegularExpression('/[\\x{1F300}-\\x{1FAFF}]/u', implode('', $parts));
    }

    #[RequiresPhpExtension('zip')]
    public function test_purchase_totals_preserve_individually_rounded_line_costs(): void
    {
        $buyer = User::factory()->purchaser()->create();
        $first = $this->purchase($buyer, 'Small item', '0.01');
        $second = $this->purchase($buyer, 'Small item', '0.01');
        $first->items()->update(['quantity' => '0.500']);
        $second->items()->update(['quantity' => '0.500']);
        $parts = $this->parts(PurchaseOrderWorkbook::build(PurchaseOrder::with(['purchaser', 'items'])->get(), false, 'November 2, 2026')->toString());
        $sheet = new SimpleXMLElement($parts['xl/worksheets/sheet1.xml']);
        $this->assertSame(0.02, (float) $sheet->sheetData->row[count($sheet->sheetData->row) - 1]->c[4]->v);
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
