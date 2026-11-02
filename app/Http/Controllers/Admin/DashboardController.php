<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DailyOrderSummary;
use App\Services\OrderSummaryWorkbook;
use App\Services\PerPersonOrderSummary;
use App\Support\Format;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * The day's orders, either added up per product (the default) or person by person (?view=people).
     */
    public function index(Request $request): View
    {
        $day = $this->requestedDay($request);
        $perPerson = $request->query('view') === 'people';

        return view('admin.dashboard.index', [
            'perPerson' => $perPerson,
            'summary' => $perPerson ? PerPersonOrderSummary::for($day) : DailyOrderSummary::for($day),
            'today' => DailyOrderSummary::today(),
            'exportSummary' => $perPerson ? null : PerPersonOrderSummary::for($day),
        ]);
    }

    /**
     * Download the selected clients' picking list, packing summary and packing lists.
     */
    public function export(Request $request): StreamedResponse
    {
        $summary = PerPersonOrderSummary::for($this->requestedDay($request));
        $people = $this->selectedPeople($request, $summary);
        abort_if($people === [], 422, 'There are no client orders to download for this date.');
        $sheet = OrderSummaryWorkbook::build($summary->day, $people);

        return $this->download($sheet, 'order-summary-'.$summary->day->toDateString().'.xlsx');
    }

    /**
     * Download the day's orders per person as an Excel file: one block per person, like the dashboard.
     */
    public function exportPerPerson(Request $request): StreamedResponse
    {
        $summary = PerPersonOrderSummary::for($this->requestedDay($request));
        $people = $this->selectedPeople($request, $summary);
        $personCount = count($people);
        $orderCount = array_sum(array_map(fn (array $person): int => count($person['orders']), $people));
        $total = array_sum(array_column($people, 'total'));
        $totalLabel = $personCount < count($summary->people) ? 'TOTAL FOR SELECTED PEOPLE' : 'TOTAL FOR THE DAY';

        $sheet = (new SimpleXlsx('Orders per person', [36, 16, 8, 14]))
            ->addRow(['ORDERS PER PERSON'], 'title')
            ->addRow([$summary->day->format('F j, Y')])
            ->addRow(['Orders received: '.$orderCount.' from '.$personCount.' '.Str::plural('person', $personCount)]);

        foreach ($people as $number => $person) {
            $sheet->addRow()
                ->addRow([($number + 1).'. '.$person['name']], 'bold')
                ->addRow(['CP or Viber: '.$person['contact_number']])
                ->addRow(['Address: '.implode(' | ', $person['addresses'])])
                ->addRow([Str::plural('Order', count($person['orders'])).': '.collect($person['orders'])->map(fn (array $order) => '#'.$order['id'].' ('.$order['time'].')')->implode(', ')])
                ->addRow(['Item', 'Size', 'Qty', 'Amount'], ['header', 'header', 'header-right', 'header-right']);

            foreach ($person['lines'] as $line) {
                $sheet->addRow([$line['product'], $line['size'], Format::qty($line['qty']), Format::peso($line['amount'])], ['cell', 'cell', 'cell-right', 'cell-right']);
            }

            if ($person['delivery_fee'] > 0) {
                $sheet->addRow(['Delivery fee', '', '', Format::peso($person['delivery_fee'])], ['cell', 'cell', 'cell', 'cell-right']);
            }

            $sheet->addRow(['TOTAL', '', '', Format::peso($person['total'])], ['total', 'total', 'total', 'total-right']);
        }

        $sheet->addRow()
            ->addRow([$totalLabel, '', '', Format::peso($total)], ['total', 'total', 'total', 'total-right']);

        return $this->download($sheet, 'orders-per-person-'.$summary->day->toDateString().'.xlsx');
    }

    /** @return list<array<string, mixed>> */
    private function selectedPeople(Request $request, PerPersonOrderSummary $summary): array
    {
        $personIds = array_map(fn (array $person): int => $person['orders'][0]['id'], $summary->people);
        $selection = $request->validate([
            'selection' => ['sometimes', Rule::in(['1'])],
            'people' => ['required_if:selection,1', 'array', 'min:1'],
            'people.*' => ['required', 'integer', 'distinct', Rule::in($personIds)],
        ], [
            'people.required_if' => 'Select at least one person to download.',
            'people.min' => 'Select at least one person to download.',
            'people.*.in' => 'A selected person is not on this day. Reload the dashboard and try again.',
        ]);
        $selectedIds = isset($selection['people']) ? array_map('intval', $selection['people']) : $personIds;

        return array_values(array_filter($summary->people, fn (array $person): bool => in_array($person['orders'][0]['id'], $selectedIds, true)));
    }

    protected function download(SimpleXlsx $sheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheet) {
            echo $sheet->toString();
        }, $filename, ['Content-Type' => SimpleXlsx::MIME_TYPE]);
    }

    /**
     * The day picked on the dashboard (?date=2026-10-01), or today.
     */
    protected function requestedDay(Request $request): CarbonImmutable
    {
        $date = $request->validate(
            ['date' => ['nullable', 'date_format:Y-m-d']],
            ['date.date_format' => 'Pick a day from the calendar.'],
        )['date'] ?? null;

        return $date ? CarbonImmutable::parse($date, DailyOrderSummary::TIMEZONE) : DailyOrderSummary::today();
    }
}
