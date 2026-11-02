<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Support\PurchaseMoney;
use App\Support\SimpleXlsx;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PurchaseOrderWorkbook
{
    /** @param Collection<int, PurchaseOrder> $orders */
    public static function build(Collection $orders, bool $perPurchaser, string $period): SimpleXlsx
    {
        if ($orders->isEmpty()) {
            return new SimpleXlsx('Purchases');
        }

        $orders = $perPurchaser ? $orders->sortBy('purchaser_id') : $orders;
        [$details, $purchases] = self::purchaseDetails($orders, $period);
        $matrix = [];
        $estimates = [];

        foreach ($purchases as $purchase) {
            $id = $purchase['id'];

            foreach ($purchase['lines'] as $line) {
                $key = json_encode([Str::lower(Str::squish($line['product'])), Str::lower(Str::squish($line['unit']))]);
                $matrix[$key] ??= ['product' => $line['product'], 'unit' => $line['unit'], 'quantities' => [], 'references' => []];
                $matrix[$key]['quantities'][$id] = ($matrix[$key]['quantities'][$id] ?? 0) + $line['thousandths'];
                $matrix[$key]['references'][$id][] = $line['quantityReference'];
                $priceKey = $key.'|'.$line['priceCents'];
                $estimates[$priceKey] ??= ['product' => $line['product'], 'unit' => $line['unit'], 'priceCents' => $line['priceCents'], 'thousandths' => 0, 'totalCents' => 0, 'quantityReferences' => [], 'totalReferences' => []];
                $estimates[$priceKey]['thousandths'] += $line['thousandths'];
                $estimates[$priceKey]['totalCents'] += $line['totalCents'];
                $estimates[$priceKey]['quantityReferences'][] = $line['quantityReference'];
                $estimates[$priceKey]['totalReferences'][] = $line['totalReference'];
            }
        }

        uasort($estimates, fn (array $a, array $b): int => strnatcasecmp($a['product'].' '.$a['unit'], $b['product'].' '.$b['unit']) ?: $a['priceCents'] <=> $b['priceCents']);
        uasort($matrix, fn (array $a, array $b): int => strnatcasecmp($a['product'].' '.$a['unit'], $b['product'].' '.$b['unit']));
        $singlePage = array_sum(array_map(self::lineHeight(...), $estimates)) <= 540;
        $picking = (new SimpleXlsx('Unified picking list', [29, 10, 15, 14, 16]))->forPrint(9, $singlePage, 4)
            ->addRow(['UNIFIED PICKING LIST'], 'template-title', 24)->merge('A1:E1')
            ->addRow([$period], 'bold', 22)->merge('A2:E2')
            ->addRow(['Admin estimated prices. Different unit prices stay on separate rows.'], 'default', 32)->merge('A3:E3')
            ->addRow(['ITEM', 'QTY', 'UNIT', 'Unit Price', 'Total Price'], 'template-header', 24);
        $summaries = [];
        $purchaseReferences = [];

        foreach (array_chunk($purchases, 4) as $batchIndex => $batch) {
            $name = 'Packing summary'.($batchIndex ? ' '.($batchIndex + 1) : '');
            $lastColumn = SimpleXlsx::columnName(3 + count($batch));
            $headerHeight = max(40, ...array_map(fn (array $purchase): int => (int) ceil(mb_strlen($purchase['name']) / 15) * 14, $batch));
            $sheet = (new SimpleXlsx($name, [27, 9, 15, ...array_fill(0, count($batch), 16)]))->forPrint(9, $singlePage, 4)
                ->addRow(['PACKING SUMMARY'], 'template-title', 24)->merge('A1:'.$lastColumn.'1')
                ->addRow([$period], 'bold', 22)->merge('A2:'.$lastColumn.'2')
                ->addRow()
                ->addRow(['ITEM', 'QTY', 'UNIT', ...array_column($batch, 'name')], 'template-header', $headerHeight);
            $batchIds = array_map(fn (array $purchase): int => $purchase['id'], $batch);

            foreach ($matrix as $line) {
                $quantities = array_intersect_key($line['quantities'], array_flip($batchIds));

                if ($quantities === []) {
                    continue;
                }

                $row = $sheet->rowCount() + 1;
                $total = array_sum($quantities) / 1000;
                $cells = [$line['product'], SimpleXlsx::formula('SUM(D'.$row.':'.$lastColumn.$row.')', $total), $line['unit']];

                foreach ($batch as $index => $purchase) {
                    $id = $purchase['id'];
                    $qty = $quantities[$id] ?? null;
                    $cells[] = $qty === null ? '' : SimpleXlsx::formula('SUM('.implode(',', $line['references'][$id]).')', $qty / 1000);

                    if ($qty !== null) {
                        $purchaseReferences[$id][] = [
                            'product' => $line['product'], 'unit' => $line['unit'], 'qty' => $qty / 1000,
                            'reference' => "'".$name."'!".SimpleXlsx::columnName($index + 4).$row,
                        ];
                    }
                }

                $sheet->addRow($cells, ['cell', 'quantity', 'cell', ...array_fill(0, count($batch), 'quantity')], self::lineHeight($line));
            }

            $summaries[] = $sheet;
        }

        foreach ($estimates as $line) {
            $picking->addRow([
                $line['product'], SimpleXlsx::formula('SUM('.implode(',', $line['quantityReferences']).')', $line['thousandths'] / 1000), $line['unit'], $line['priceCents'] / 100,
                SimpleXlsx::formula('SUM('.implode(',', $line['totalReferences']).')', $line['totalCents'] / 100),
            ], ['cell', 'quantity', 'cell', 'money', 'money'], self::lineHeight($line));
        }

        $last = $picking->rowCount();
        $picking->addRow(['TOTAL', '', '', '', $last >= 5
            ? SimpleXlsx::formula('SUM(E5:E'.$last.')', array_sum(array_column($estimates, 'totalCents')) / 100) : 0], ['total', 'total', 'total', 'total', 'money-total'], 24);

        foreach ($summaries as $summary) {
            $picking->addSheet($summary);
        }

        $packing = (new SimpleXlsx('Packing lists', [25, 9, 15, 3, 25, 9, 15]))->forPrint();

        foreach (array_chunk($purchases, 2) as $pair) {
            $lists = array_map(fn (array $purchase): array => $purchaseReferences[$purchase['id']] ?? [], $pair);
            $offsets = [0, 0];
            $part = 0;

            do {
                if ($packing->rowCount()) {
                    $packing->breakBeforeRow($packing->rowCount() + 1);
                }

                $part++;
                $top = $packing->rowCount() + 1;
                $nameHeight = max(40, ...array_map(fn (array $purchase): int => (int) ceil(mb_strlen($purchase['name']) / 40) * 14, $pair));
                $packing->addRow(['PACKING LIST', '', '', '', isset($pair[1]) ? 'PACKING LIST' : '', '', ''], 'template-title', 24)
                    ->merge('A'.$top.':C'.$top)->merge('E'.$top.':G'.$top)
                    ->addRow([$pair[0]['name'].($part > 1 ? ' (continued)' : ''), '', '', '', isset($pair[1]) ? $pair[1]['name'].($part > 1 ? ' (continued)' : '') : '', '', ''], 'bold', $nameHeight)
                    ->merge('A'.($top + 1).':C'.($top + 1))->merge('E'.($top + 1).':G'.($top + 1))
                    ->addRow([$pair[0]['date'], '', '', '', isset($pair[1]) ? $pair[1]['date'] : '', '', ''], 'default', 22)
                    ->merge('A'.($top + 2).':C'.($top + 2))->merge('E'.($top + 2).':G'.($top + 2))
                    ->addRow(['ITEM', 'QTY', 'UNIT', '', isset($pair[1]) ? 'ITEM' : '', isset($pair[1]) ? 'QTY' : '', isset($pair[1]) ? 'UNIT' : ''], ['template-header', 'template-header', 'template-header', 'default', 'template-header', 'template-header', 'template-header'], 24);
                $usedHeight = 0;

                while (isset($lists[0][$offsets[0]]) || isset($lists[1][$offsets[1]])) {
                    $left = $lists[0][$offsets[0]] ?? null;
                    $right = $lists[1][$offsets[1]] ?? null;
                    $height = max($left ? self::lineHeight($left) : 22, $right ? self::lineHeight($right) : 22);

                    if ($usedHeight > 0 && $usedHeight + $height > 460) {
                        break;
                    }

                    $cells = [];

                    foreach ([$left, $right] as $index => $line) {
                        if ($index) {
                            $cells[] = '';
                        }

                        array_push($cells, ...($line ? [$line['product'], SimpleXlsx::formula($line['reference'], $line['qty']), $line['unit']] : ['', '', '']));

                        if ($line) {
                            $offsets[$index]++;
                        }
                    }

                    $packing->addRow($cells, ['cell', 'quantity', 'cell', 'default', 'cell', 'quantity', 'cell'], $height);
                    $usedHeight += $height;
                }

                $padding = max(18, 460 - $usedHeight);

                while ($padding > 0) {
                    $height = min(300, $padding);
                    $packing->addRow([], 'default', $height);
                    $padding -= $height;
                }
            } while (isset($lists[0][$offsets[0]]) || isset($lists[1][$offsets[1]]));
        }

        return $picking->addSheet($packing)->addSheet($details);
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     * @return array{SimpleXlsx, list<array{id: int, name: string, date: string, lines: list<array{product: string, unit: string, thousandths: int, priceCents: int, totalCents: int, quantityReference: string, totalReference: string}>}>}
     */
    private static function purchaseDetails(Collection $orders, string $period): array
    {
        $sheet = (new SimpleXlsx('Purchase details', [32, 10, 16, 16, 17, 32]))->forPrint(9, false, 0, 'landscape')
            ->addRow(['PURCHASE ORDER DETAILS'], 'template-title', 26)->merge('A1:F1')
            ->addRow([$period], 'bold', 26)->merge('A2:F2')
            ->addRow(['Source purchase orders and admin estimates for the picking and packing forms.'], 'default', 24)->merge('A3:F3');
        $purchases = [];
        $totalRows = [];

        foreach ($orders as $order) {
            $name = $order->purchaser->name.' / '.$order->displayName();
            $purchase = ['id' => $order->id, 'name' => $name, 'date' => $order->purchase_date->format('M j, Y'), 'lines' => []];
            $row = $sheet->rowCount() + 2;
            $sheet->addRow()->addRow([$name.' - '.$purchase['date']], 'bold', 28)->merge('A'.$row.':F'.$row)
                ->addRow(['Particulars', 'Qty', 'Unit', 'Estimated unit price', 'Estimated total price', 'Remarks'], 'template-header', 32);
            $first = $sheet->rowCount() + 1;

            foreach ($order->items as $item) {
                $row = $sheet->rowCount() + 1;
                $sheet->addRow([
                    $item->particulars, (float) $item->quantity, $item->unit, (float) $item->estimated_unit_price,
                    SimpleXlsx::formula('ROUND(B'.$row.'*D'.$row.',2)', $item->estimatedTotalCents() / 100), $item->remarks ?? '',
                ], ['cell', 'quantity', 'cell', 'money', 'money', 'cell'], max(28, (int) ceil(mb_strlen($item->particulars) / 30) * 14, (int) ceil(mb_strlen($item->remarks ?? '') / 30) * 14));
                $purchase['lines'][] = [
                    'product' => $item->particulars, 'unit' => $item->unit,
                    'thousandths' => (int) round((float) $item->quantity * 1000),
                    'priceCents' => PurchaseMoney::cents($item->estimated_unit_price), 'totalCents' => $item->estimatedTotalCents(),
                    'quantityReference' => "'Purchase details'!B".$row, 'totalReference' => "'Purchase details'!E".$row,
                ];
            }

            $last = $sheet->rowCount();
            $totalRows[] = $last + 1;
            $sheet->addRow(['PURCHASE TOTAL', '', '', '', $last >= $first ? SimpleXlsx::formula('SUM(E'.$first.':E'.$last.')', $order->estimatedTotalCents() / 100) : 0, ''], ['total', 'total', 'total', 'total', 'money-total', 'total'], 26);
            $purchases[] = $purchase;
        }

        $sheet->addRow()->addRow(['TOTAL ESTIMATED COST', '', '', '', SimpleXlsx::formula('SUM('.implode(',', array_map(fn (int $row): string => 'E'.$row, $totalRows)).')', $orders->sum(fn (PurchaseOrder $order): int => $order->estimatedTotalCents()) / 100), ''], ['total', 'total', 'total', 'total', 'money-total', 'total'], 28);

        return [$sheet, $purchases];
    }

    /** @param array{product: string, unit: string} $line */
    private static function lineHeight(array $line): int
    {
        return max(24, (int) ceil(mb_strlen($line['product']) / 24) * 14, (int) ceil(mb_strlen($line['unit']) / 12) * 14);
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
