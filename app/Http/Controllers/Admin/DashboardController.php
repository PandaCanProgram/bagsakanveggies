<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DailyOrderSummary;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.dashboard', [
            'summary' => DailyOrderSummary::for($this->requestedDay($request)),
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

        return response()->streamDownload(function () use ($sheet) {
            echo $sheet->toString();
        }, 'order-summary-'.$summary->day->toDateString().'.xlsx', ['Content-Type' => SimpleXlsx::MIME_TYPE]);
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
