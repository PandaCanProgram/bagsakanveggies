<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /**
     * @param  list<string>  $sizes
     */
    protected function makeProduct(string $name, array $sizes = ['10 kg bag', '1 kg']): Product
    {
        return Product::create([
            'name' => $name,
            'variants' => array_map(fn (string $size) => ['label' => $size, 'price' => 100], $sizes),
        ]);
    }

    /**
     * Place an order at the given Manila time, the way checkout saves it.
     *
     * @param  list<array{0: Product, 1: string, 2: int|float}>  $lines  [product, size, qty] per line.
     */
    protected function placeOrder(string $manilaTime, array $lines): Order
    {
        $this->travelTo(CarbonImmutable::parse($manilaTime, 'Asia/Manila'));

        $order = Order::create([
            'full_name' => 'Juan Dela Cruz',
            'contact_number' => '09171234567',
            'delivery_address' => 'Brgy 1',
            'payment_method' => 'GCash (via Messenger)',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total' => 0,
        ]);

        foreach ($lines as [$product, $size, $qty]) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_label' => $size,
                'unit_price' => 100,
                'qty' => $qty,
                'line_total' => 100 * $qty,
            ]);
        }

        $this->travelBack();

        return $order;
    }

    /**
     * The text of each row in the first sheet of an .xlsx file.
     *
     * @return list<list<string>>
     */
    protected function sheetRows(string $xlsx): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $xlsx);

        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = new SimpleXMLElement($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);

        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $cells[] = (string) $cell->is->t;
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    public function test_guests_are_sent_to_sign_in_instead_of_seeing_the_dashboard(): void
    {
        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    public function test_guests_cannot_download_the_order_summary(): void
    {
        $this->get(route('admin.order-summary.export'))->assertRedirect(route('admin.login'));
    }

    public function test_dashboard_adds_up_the_days_orders_per_product(): void
    {
        $sayote = $this->makeProduct('Sayote');
        $repolyo = $this->makeProduct('Repolyo');
        $broccoli = $this->makeProduct('Broccoli');
        $calamansi = $this->makeProduct('Calamansi');
        $whiteOnions = $this->makeProduct('White Onions');
        $redOnions = $this->makeProduct('Red Onions');
        $this->placeOrder('2026-10-01 08:15', [[$sayote, '1 kg', 1], [$repolyo, '1 kg', 1], [$calamansi, '1 kg', 1]]);
        $this->placeOrder('2026-10-01 11:40', [[$sayote, '1 kg', 1], [$broccoli, '1 kg', 1], [$whiteOnions, '1 kg', 2]]);
        $this->placeOrder('2026-10-01 19:05', [[$sayote, '1 kg', 1], [$calamansi, '1 kg', 1], [$redOnions, '1 kg', 1]]);

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeTextInOrder([
            'October 1, 2026',
            'Orders received: 3',
            'Broccoli', '1 kg',
            'Calamansi', '2 kg',
            'Red Onions', '1 kg',
            'Repolyo', '1 kg',
            'Sayote', '3 kg',
            'White Onions', '2 kg',
            'TOTAL', '10 kg',
        ]);
    }

    public function test_each_product_shows_its_veggie_or_fruit_emoji(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');
        $orange = $this->makeProduct('Orange', ['1 piece']);
        $this->placeOrder('2026-10-01 08:00', [[$carrots, '1 kg', 1], [$orange, '1 piece', 2]]);

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeTextInOrder(['🥕 Fresh Carrots', '1 kg', '🍊 Orange', '2 pcs']);
    }

    public function test_bags_and_gram_sizes_are_added_up_in_kilos(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');
        $siliLabuyo = $this->makeProduct('Fresh Sili Labuyo', ['10 kg bag', '1 kg', '100 grams']);
        $this->placeOrder('2026-10-01 08:00', [[$carrots, '10 kg bag', 4], [$carrots, '1 kg', 1.5]]);
        $this->placeOrder('2026-10-01 09:00', [[$carrots, '10 kg bag', 2], [$siliLabuyo, '100 grams', 3]]);

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeTextInOrder(['Fresh Carrots', '61.5 kg', 'Fresh Sili Labuyo', '0.3 kg', 'TOTAL', '61.8 kg']);
    }

    public function test_pieces_are_counted_apart_from_kilos(): void
    {
        $orange = $this->makeProduct('Orange', ['1 piece']);
        $fujiApples = $this->makeProduct('Fuji Apples', ['1 piece']);
        $carrots = $this->makeProduct('Fresh Carrots');
        $this->placeOrder('2026-10-01 08:00', [[$orange, '1 piece', 3], [$fujiApples, '1 piece', 1], [$carrots, '1 kg', 2]]);

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeTextInOrder(['Fresh Carrots', '2 kg', 'Fuji Apples', '1 pc', 'Orange', '3 pcs', 'TOTAL', '2 kg + 4 pcs']);
    }

    /**
     * Orders are saved in UTC: 7:00 AM Oct 1 Manila is still Sep 30 in UTC, and 12:30 AM Oct 2 is Oct 1.
     */
    public function test_a_day_runs_from_midnight_to_midnight_manila_time(): void
    {
        $okra = $this->makeProduct('Fresh Okra');
        $sayote = $this->makeProduct('Fresh Sayote');
        $this->placeOrder('2026-10-01 07:00', [[$okra, '1 kg', 1]]);
        $this->placeOrder('2026-10-02 00:30', [[$sayote, '1 kg', 1]]);

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeTextInOrder(['Orders received: 1', 'Fresh Okra'])
            ->assertDontSeeText('Fresh Sayote');
    }

    public function test_dashboard_shows_today_in_manila_when_no_day_is_picked(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');
        $this->placeOrder('2026-10-01 18:00', [[$carrots, '1 kg', 5]]);
        $this->placeOrder('2026-10-02 06:30', [[$carrots, '1 kg', 2]]);
        $this->travelTo(CarbonImmutable::parse('2026-10-02 07:00', 'Asia/Manila'));

        $response = $this->actingAs($this->admin)->get(route('admin.home'));

        $response->assertSeeTextInOrder(['October 2, 2026', 'Today', 'Orders received: 1', 'Fresh Carrots', '2 kg']);
    }

    public function test_a_product_renamed_during_the_day_stays_on_one_line(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');
        $this->placeOrder('2026-10-01 08:00', [[$carrots, '1 kg', 1]]);
        $carrots->update(['name' => 'Baguio Carrots']);
        $this->placeOrder('2026-10-01 09:00', [[$carrots, '1 kg', 2]]);

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeTextInOrder(['Baguio Carrots', '3 kg'])
            ->assertDontSeeText('Fresh Carrots');
    }

    public function test_deleted_products_still_show_under_the_name_they_were_ordered_by(): void
    {
        $okra = $this->makeProduct('Fresh Okra');
        $this->placeOrder('2026-10-01 08:00', [[$okra, '1 kg', 1.5]]);
        $okra->delete();

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeTextInOrder(['Fresh Okra', '1.5 kg']);
    }

    public function test_a_day_without_orders_says_so_and_offers_nothing_to_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSeeText('No orders on this day')
            ->assertDontSeeText('Download Excel');
    }

    public function test_product_names_are_escaped_on_the_dashboard(): void
    {
        $product = $this->makeProduct('Okra <script>alert(1)</script>');
        $this->placeOrder('2026-10-01 08:00', [[$product, '1 kg', 1]]);

        $response = $this->actingAs($this->admin)->get(route('admin.home', ['date' => '2026-10-01']));

        $response->assertSee('Okra &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_an_invalid_date_shows_a_message_instead_of_a_summary(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.home'))
            ->followingRedirects()
            ->get(route('admin.home', ['date' => '2026-02-30']));

        $response->assertSeeText('Pick a day from the calendar.');
    }

    #[RequiresPhpExtension('zip')]
    public function test_order_summary_downloads_as_an_excel_sheet_laid_out_like_the_dashboard(): void
    {
        $sayote = $this->makeProduct('Sayote');
        $carrots = $this->makeProduct('Fresh Carrots');
        $this->placeOrder('2026-10-01 08:00', [[$sayote, '1 kg', 2], [$carrots, '10 kg bag', 1]]);
        $this->placeOrder('2026-10-01 10:00', [[$sayote, '1 kg', 1]]);

        $response = $this->actingAs($this->admin)->get(route('admin.order-summary.export', ['date' => '2026-10-01']));

        $response->assertDownload('order-summary-2026-10-01.xlsx')
            ->assertHeader('Content-Type', SimpleXlsx::MIME_TYPE);
        $this->assertSame([
            ['DAILY ORDER SUMMARY'],
            ['October 1, 2026'],
            ['Orders received: 2'],
            [],
            ['Product', 'Total Qty Ordered'],
            ['🥕 Fresh Carrots', '10 kg'],
            ['🍐 Sayote', '3 kg'],
            ['TOTAL', '13 kg'],
        ], $this->sheetRows($response->streamedContent()));
    }
}
