<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class PurchaseOrderNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_highlights_the_required_name_field(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.purchase-orders.create'))->assertOk()
            ->assertSee('po-name-field')->assertSee('name="name"', false)
            ->assertSee('aria-required="true"', false)->assertSee('Required')
            ->assertSee('lists, download checkboxes and Excel forms');
    }

    public function test_missing_blank_and_overlong_names_are_rejected_without_saving_an_order(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $payload = $this->payload(User::factory()->purchaser()->create());
        foreach ([null, '', '   ', str_repeat('a', 121)] as $name) {
            $this->from(route('admin.purchase-orders.create'))->post(route('admin.purchase-orders.store'), [...$payload, 'name' => $name])
                ->assertRedirect(route('admin.purchase-orders.create'))->assertSessionHasErrors('name')
                ->assertSessionHasInput('items.0.particulars', 'Carrots');
        }
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_name_is_saved_and_used_in_both_panels_and_the_download_checklist(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $buyer = User::factory()->purchaser()->create();
        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [...$this->payload($buyer), 'name' => '  Barrio Fiesta  '])
            ->assertSessionHasNoErrors()->assertRedirect();
        $order = PurchaseOrder::sole();
        $this->assertSame('Barrio Fiesta', $order->name);
        $this->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'view' => 'purchasers']))
            ->assertOk()->assertSee('Barrio Fiesta - Nov 2, 2026')->assertDontSee('Purchase 1 -');
        $this->get(route('admin.purchase-orders.show', $order))->assertOk()
            ->assertSee('<h1 class="a-page-title">Barrio Fiesta</h1>', false);
        $this->actingAs($buyer, 'purchaser')->get(route('purchaser.home', ['date' => '2026-11-02']))
            ->assertOk()->assertSee('Barrio Fiesta');
        $this->get(route('purchaser.purchase-orders.show', $order))->assertOk()
            ->assertSee('<h1 class="a-page-title">Barrio Fiesta</h1>', false);
    }

    #[RequiresPhpExtension('zip')]
    public function test_the_named_purchase_is_used_in_excel_details_with_safe_text_and_no_po_number_as_its_heading(): void
    {
        $order = PurchaseOrder::factory()->hasItems(1)->create(['name' => 'Barrio Fiesta & Co.', 'purchase_date' => '2026-11-02']);
        $response = $this->actingAs($order->creator, 'web')->get(route('admin.purchase-orders.export', [
            'date' => '2026-11-02', 'mode' => 'purchasers', 'selection' => 1, 'purchases' => [$order->id],
        ]))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'named-po');
        file_put_contents($path, $response->streamedContent());
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Barrio Fiesta &amp; Co.', $xml);
        $this->assertStringNotContainsString($order->number(), $xml);
        $this->assertInstanceOf(SimpleXMLElement::class, simplexml_load_string($xml));
        $zip->close();
        unlink($path);
    }

    public function test_older_unnamed_orders_remain_accessible_with_their_reference_number(): void
    {
        $order = PurchaseOrder::factory()->hasItems(1)->create();
        $this->assertNull($order->name);
        $this->assertSame($order->number(), $order->displayName());
        $this->actingAs($order->creator)->get(route('admin.purchase-orders.index', ['all_dates' => 1, 'view' => 'purchasers']))
            ->assertOk()->assertSee($order->number());
        $this->get(route('admin.purchase-orders.show', $order))->assertOk()->assertSee($order->number());
    }

    /** @return array{name: string, purchaser_id: int, purchase_date: string, items: list<array{particulars: string, quantity: string, unit: string, estimated_unit_price: string}>} */
    private function payload(User $buyer): array
    {
        return [
            'name' => 'Barrio Fiesta', 'purchaser_id' => $buyer->id, 'purchase_date' => '2026-11-02',
            'items' => [['particulars' => 'Carrots', 'quantity' => '2', 'unit' => 'kg', 'estimated_unit_price' => '100.00']],
        ];
    }
}
