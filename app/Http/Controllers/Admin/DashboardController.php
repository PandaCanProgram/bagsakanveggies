<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DailyOrderSummary;
use App\Services\PerPersonOrderSummary;
use App\Support\Format;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        ]);
    }

    /**
     * Download the day's order summary as an Excel file laid out like the dashboard.
     */
    public function export(Request $request): StreamedResponse
    {
        $summary = DailyOrderSummary::for($this->requestedDay($request));

        $sheet = (new SimpleXlsx('Order summary', [36, 22]))
            ->addRow(['DAILY ORDER SUMMARY'], 'title')
            ->addRow([$summary->day->format('F j, Y')])
            ->addRow(['Orders received: '.$summary->orderCount])
            ->addRow()
            ->addRow(['Product', 'Total Qty Ordered'], ['header', 'header-right']);

        foreach ($summary->lines as $line) {
            $sheet->addRow([$line['emoji'].' '.$line['product'], $line['quantity']], ['cell', 'cell-right']);
        }

        $sheet->addRow(['TOTAL', $summary->total], ['total', 'total-right']);

        return $this->download($sheet, 'order-summary-'.$summary->day->toDateString().'.xlsx');
    }

    /**
     * Download the day's orders per person as an Excel file: one block per person, like the dashboard.
     */
    public function exportPerPerson(Request $request): StreamedResponse
    {
        $summary = PerPersonOrderSummary::for($this->requestedDay($request));
        $people = count($summary->people);

        $sheet = (new SimpleXlsx('Orders per person', [36, 16, 8, 14]))
            ->addRow(['ORDERS PER PERSON'], 'title')
            ->addRow([$summary->day->format('F j, Y')])
            ->addRow(['Orders received: '.$summary->orderCount.' from '.$people.' '.Str::plural('person', $people)]);

        foreach ($summary->people as $number => $person) {
            $sheet->addRow()
                ->addRow([($number + 1).'. '.$person['name']], 'bold')
                ->addRow(['CP or Viber: '.$person['contact_number']])
                ->addRow(['Address: '.implode(' | ', $person['addresses'])])
                ->addRow([Str::plural('Order', count($person['orders'])).': '.collect($person['orders'])->map(fn (array $order) => '#'.$order['id'].' ('.$order['time'].')')->implode(', ')])
                ->addRow(['Item', 'Size', 'Qty', 'Amount'], ['header', 'header', 'header-right', 'header-right']);

            foreach ($person['lines'] as $line) {
                $sheet->addRow([$line['emoji'].' '.$line['product'], $line['size'], Format::qty($line['qty']), Format::peso($line['amount'])], ['cell', 'cell', 'cell-right', 'cell-right']);
            }

            if ($person['delivery_fee'] > 0) {
                $sheet->addRow(['Delivery fee', '', '', Format::peso($person['delivery_fee'])], ['cell', 'cell', 'cell', 'cell-right']);
            }

            $sheet->addRow(['TOTAL', '', '', Format::peso($person['total'])], ['total', 'total', 'total', 'total-right']);
        }

        $sheet->addRow()
            ->addRow(['TOTAL FOR THE DAY', '', '', Format::peso($summary->total)], ['total', 'total', 'total', 'total-right']);

        return $this->download($sheet, 'orders-per-person-'.$summary->day->toDateString().'.xlsx');
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
