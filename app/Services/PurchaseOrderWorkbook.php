<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Support\SimpleXlsx;
use Illuminate\Support\Collection;

class PurchaseOrderWorkbook
{
    /** @param Collection<int, PurchaseOrder> $orders */
    public static function build(Collection $orders, bool $perPurchaser, string $period): SimpleXlsx
    {
        $groups = $perPurchaser ? $orders->groupBy('purchaser_id')->values() : collect([$orders]);
        $workbook = null;

        foreach ($groups as $index => $group) {
            $title = $perPurchaser ? $group->first()->purchaser->name : 'All purchases';
            $sheet = (new SimpleXlsx($title, [32, 10, 16, 16, 17, 32]))->forPrint(9, false, 0, 'landscape')
                ->addRow(['PURCHASE ORDER SUMMARY'], 'template-title', 26)->merge('A1:F1')
                ->addRow([$period.($perPurchaser ? "\nPurchaser: ".$title : '')], 'bold', 36)->merge('A2:F2')
                ->addRow(['Admin estimated prices. Each purchase keeps its own unit prices.'], 'default', 24)->merge('A3:F3');
            $totalRows = [];

            foreach ($group as $order) {
                $row = $sheet->rowCount() + 2;
                $sheet->addRow()->addRow([$order->number().' - '.$order->purchaser->name.' - '.$order->purchase_date->format('M j, Y')], 'bold', 28)->merge('A'.$row.':F'.$row)
                    ->addRow(['Particulars', 'Qty', 'Unit', 'Estimated unit price', 'Estimated total price', 'Remarks'], 'template-header', 32);
                $first = $sheet->rowCount() + 1;

                foreach ($order->items as $item) {
                    $row = $sheet->rowCount() + 1;
                    $sheet->addRow([
                        $item->particulars, (float) $item->quantity, $item->unit, (float) $item->estimated_unit_price,
                        SimpleXlsx::formula('ROUND(B'.$row.'*D'.$row.',2)', $item->estimatedTotalCents() / 100), $item->remarks ?? '',
                    ], ['cell', 'quantity', 'cell', 'money', 'money', 'cell'], max(28, (int) ceil(mb_strlen($item->particulars) / 30) * 14, (int) ceil(mb_strlen($item->remarks ?? '') / 30) * 14));
                }

                $last = $sheet->rowCount();
                $totalRows[] = $last + 1;
                $sheet->addRow(['PURCHASE TOTAL', '', '', '', $last >= $first ? SimpleXlsx::formula('SUM(E'.$first.':E'.$last.')', $order->estimatedTotalCents() / 100) : 0, ''], ['total', 'total', 'total', 'total', 'money-total', 'total'], 26);
            }

            $sheet->addRow()->addRow(['TOTAL ESTIMATED COST', '', '', '', SimpleXlsx::formula('SUM('.implode(',', array_map(fn (int $row): string => 'E'.$row, $totalRows)).')', $group->sum(fn (PurchaseOrder $order): int => $order->estimatedTotalCents()) / 100), ''], ['total', 'total', 'total', 'total', 'money-total', 'total'], 28);

            if ($workbook === null) {
                $workbook = $sheet;
            } else {
                $workbook->addSheet($sheet);
            }
        }

        return $workbook ?? new SimpleXlsx('Purchases');
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     * @return list<array{id: int, name: string, purchases: list<array{id: string, label: string}>}>
     */
    public static function selectionGroups(Collection $orders): array
    {
        $groups = [];
        $numbers = [];

        foreach ($orders->sortBy('id')->sortBy('purchase_date') as $order) {
            $id = $order->purchaser_id;
            $date = $order->purchase_date->toDateString();
            $numbers[$id][$date] = ($numbers[$id][$date] ?? 0) + 1;
            $groups[$id] ??= ['id' => $id, 'name' => $order->purchaser->name, 'purchases' => []];
            $groups[$id]['purchases'][] = ['id' => (string) $order->id, 'label' => 'Purchase '.$numbers[$id][$date].' - '.$order->number().' - '.$order->purchase_date->format('M j, Y')];
        }

        return array_values($groups);
    }
}
