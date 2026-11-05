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
        if ($orders->isEmpty()) {
            return new SimpleXlsx('Purchases');
        }

        $orders = $perPurchaser ? $orders->sortBy('purchaser_id') : $orders;

        return self::purchaseDetails($orders, $period);
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     */
    private static function purchaseDetails(Collection $orders, string $period): SimpleXlsx
    {
        $sheet = (new SimpleXlsx('Purchase details', [32, 10, 16, 16, 17, 32]))->forPrint(9, false, 0, 'landscape')
            ->addRow(['PURCHASE ORDER DETAILS'], 'template-title', 26)->merge('A1:F1')
            ->addRow([$period], 'bold', 26)->merge('A2:F2')
            ->addRow(['Purchase orders and admin estimates. Picking and packing lists are available on the Dashboard.'], 'default', 24)->merge('A3:F3');
        $totalRows = [];

        foreach ($orders as $order) {
            $name = $order->purchaser->name.' / '.$order->displayName();
            $row = $sheet->rowCount() + 2;
            $sheet->addRow()->addRow([$name.' - '.$order->purchase_date->format('M j, Y')], 'bold', 28)->merge('A'.$row.':F'.$row)
                ->addRow(['Particulars', 'Qty', 'Unit', 'Estimated unit price', 'Estimated total price', 'Remarks'], 'template-header', 32);
            $first = $sheet->rowCount() + 1;

            foreach ($order->items as $item) {
                $row = $sheet->rowCount() + 1;
                $sheet->addRow([
                    $item->particulars, (float) $item->quantity, $item->unit, (float) $item->estimated_unit_price,
                    SimpleXlsx::formula('ROUND(B'.$row.'*D'.$row.',2)', $item->estimatedTotalCents() / 100), $item->remarks ?? '',
                ], ['cell', 'quantity', 'unit', 'money', 'money', 'cell'], max(28, (int) ceil(mb_strlen($item->particulars) / 30) * 14, (int) ceil(mb_strlen($item->remarks ?? '') / 30) * 14));
            }

            $last = $sheet->rowCount();
            $totalRows[] = $last + 1;
            $sheet->addRow(['PURCHASE TOTAL', '', '', '', $last >= $first ? SimpleXlsx::formula('SUM(E'.$first.':E'.$last.')', $order->estimatedTotalCents() / 100) : 0, ''], ['total', 'total', 'total', 'total', 'money-total', 'total'], 26);
        }

        $sheet->addRow()->addRow(['TOTAL ESTIMATED COST', '', '', '', SimpleXlsx::formula('SUM('.implode(',', array_map(fn (int $row): string => 'E'.$row, $totalRows)).')', $orders->sum(fn (PurchaseOrder $order): int => $order->estimatedTotalCents()) / 100), ''], ['total', 'total', 'total', 'total', 'money-total', 'total'], 28);

        return $sheet;
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     * @return list<array{id: int, name: string, purchases: list<array{id: string, label: string}>}>
     */
    public static function selectionGroups(Collection $orders): array
    {
        $groups = [];

        foreach ($orders->sortBy('id')->sortBy('purchase_date') as $order) {
            $id = $order->purchaser_id;
            $groups[$id] ??= ['id' => $id, 'name' => $order->purchaser->name, 'purchases' => []];
            $groups[$id]['purchases'][] = ['id' => (string) $order->id, 'label' => $order->displayName().' - '.$order->purchase_date->format('M j, Y')];
        }

        return array_values($groups);
    }
}
