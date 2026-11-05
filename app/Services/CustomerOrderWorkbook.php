<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Support\SimpleXlsx;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerOrderWorkbook
{
    /**
     * @param  list<array{name: string, orders: list<array{id: int, time: string}>}>  $people
     */
    public static function appendTo(SimpleXlsx $workbook, array $people, string $period): SimpleXlsx
    {
        if ($people === []) {
            return $workbook;
        }

        $customers = self::customers($people, $period);
        $matrix = [];
        $pricedLines = [];

        foreach ($customers as $customer) {
            $id = $customer['id'];

            foreach ($customer['lines'] as $line) {
                $key = json_encode([$line['productKey'], Str::lower(Str::squish($line['unit']))]);
                $matrix[$key] ??= ['product' => $line['product'], 'unit' => $line['unit'], 'quantities' => []];
                $matrix[$key]['quantities'][$id] = ($matrix[$key]['quantities'][$id] ?? 0) + $line['thousandths'];
                $priceKey = $key.'|'.$line['priceCents'];
                $pricedLines[$priceKey] ??= ['product' => $line['product'], 'unit' => $line['unit'], 'priceCents' => $line['priceCents'], 'thousandths' => 0, 'totalCents' => 0];
                $pricedLines[$priceKey]['thousandths'] += $line['thousandths'];
                $pricedLines[$priceKey]['totalCents'] += $line['totalCents'];
            }
        }

        uasort($pricedLines, fn (array $a, array $b): int => strnatcasecmp($a['product'].' '.$a['unit'], $b['product'].' '.$b['unit']) ?: $a['priceCents'] <=> $b['priceCents']);
        uasort($matrix, fn (array $a, array $b): int => strnatcasecmp($a['product'].' '.$a['unit'], $b['product'].' '.$b['unit']));
        $singlePage = array_sum(array_map(self::lineHeight(...), $pricedLines)) <= 540;
        $picking = (new SimpleXlsx('Unified picking list', [29, 10, 15, 14, 16]))->forPrint(9, $singlePage, 4)
            ->addRow(['UNIFIED PICKING LIST'], 'template-title', 24)->merge('A1:E1')
            ->addRow([$period], 'bold', 22)->merge('A2:E2')
            ->addRow(['Customer order prices. Different sizes and unit prices stay on separate rows.'], 'default', 32)->merge('A3:E3')
            ->addRow(['ITEM', 'QTY', 'UNIT', 'Unit Price', 'Total Price'], 'template-header', 24);
        $summaries = [];
        $customerReferences = [];

        foreach (array_chunk($customers, 4) as $batchIndex => $batch) {
            $name = 'Packing summary'.($batchIndex ? ' '.($batchIndex + 1) : '');
            $lastColumn = SimpleXlsx::columnName(3 + count($batch));
            $headerHeight = max(40, ...array_map(fn (array $customer): int => (int) ceil(mb_strlen($customer['name']) / 15) * 14, $batch));
            $sheet = (new SimpleXlsx($name, [27, 9, 15, ...array_fill(0, count($batch), 16)]))->forPrint(9, $singlePage, 4)
                ->addRow(['PACKING SUMMARY'], 'template-title', 24)->merge('A1:'.$lastColumn.'1')
                ->addRow([$period], 'bold', 22)->merge('A2:'.$lastColumn.'2')
                ->addRow()
                ->addRow(['ITEM', 'QTY', 'UNIT', ...array_column($batch, 'name')], 'template-header', $headerHeight);
            $batchIds = array_map(fn (array $customer): int => $customer['id'], $batch);

            foreach ($matrix as $line) {
                $quantities = array_intersect_key($line['quantities'], array_flip($batchIds));

                if ($quantities === []) {
                    continue;
                }

                $row = $sheet->rowCount() + 1;
                $total = array_sum($quantities) / 1000;
                $cells = [$line['product'], SimpleXlsx::formula('SUM(D'.$row.':'.$lastColumn.$row.')', $total), $line['unit']];

                foreach ($batch as $index => $customer) {
                    $id = $customer['id'];
                    $qty = $quantities[$id] ?? null;
                    $cells[] = $qty === null ? '' : $qty / 1000;

                    if ($qty !== null) {
                        $customerReferences[$id][] = [
                            'product' => $line['product'], 'unit' => $line['unit'], 'qty' => $qty / 1000,
                            'reference' => "'".$name."'!".SimpleXlsx::columnName($index + 4).$row,
                        ];
                    }
                }

                $sheet->addRow($cells, ['cell', 'quantity', 'unit', ...array_fill(0, count($batch), 'quantity')], self::lineHeight($line));
            }

            $summaries[] = $sheet;
        }

        foreach ($pricedLines as $line) {
            $picking->addRow([
                $line['product'], $line['thousandths'] / 1000, $line['unit'], $line['priceCents'] / 100,
                $line['totalCents'] / 100,
            ], ['cell', 'quantity', 'unit', 'money', 'money'], self::lineHeight($line));
        }

        $last = $picking->rowCount();
        $picking->addRow(['TOTAL', '', '', '', $last >= 5
            ? SimpleXlsx::formula('SUM(E5:E'.$last.')', array_sum(array_column($pricedLines, 'totalCents')) / 100) : 0], ['total', 'total', 'total', 'total', 'money-total'], 24);

        $workbook->addSheet($picking);

        foreach ($summaries as $summary) {
            $workbook->addSheet($summary);
        }

        $packing = (new SimpleXlsx('Packing lists', [25, 9, 15, 3, 25, 9, 15]))->forPrint(scale: 90);

        foreach (array_chunk($customers, 2) as $pair) {
            $lists = array_map(fn (array $customer): array => $customerReferences[$customer['id']] ?? [], $pair);
            $offsets = [0, 0];
            $part = 0;

            do {
                $active = [isset($lists[0][$offsets[0]]), isset($lists[1][$offsets[1]])];

                if (! $active[0] && ! $active[1]) {
                    break;
                }

                if ($packing->rowCount()) {
                    $packing->addRow([], 'default', 12);
                    $packing->breakBeforeRow($packing->rowCount() + 1);
                }

                $part++;
                self::packingHeadings($packing, $pair, $active, $part);
                $usedHeight = 0;

                while (isset($lists[0][$offsets[0]]) || isset($lists[1][$offsets[1]])) {
                    $left = $lists[0][$offsets[0]] ?? null;
                    $right = $lists[1][$offsets[1]] ?? null;
                    $height = max($left ? self::lineHeight($left) : 22, $right ? self::lineHeight($right) : 22);

                    if ($usedHeight > 0 && $usedHeight + $height > 460) {
                        break;
                    }

                    $cells = [];
                    $styles = [];

                    foreach ([$left, $right] as $index => $line) {
                        if ($index && ! $active[1]) {
                            break;
                        }

                        if ($index) {
                            $cells[] = '';
                            $styles[] = 'default';
                        }

                        array_push($cells, ...($line ? [$line['product'], SimpleXlsx::formula($line['reference'], $line['qty']), $line['unit']] : ['', '', '']));
                        array_push($styles, ...($line ? ['cell', 'quantity', 'unit'] : ['default', 'default', 'default']));

                        if ($line) {
                            $offsets[$index]++;
                        }
                    }

                    $packing->addRow($cells, $styles, $height);
                    $usedHeight += $height;
                }

            } while (isset($lists[0][$offsets[0]]) || isset($lists[1][$offsets[1]]));
        }

        return $workbook->addSheet($packing);
    }

    /**
     * @param  list<array{name: string, date: string}>  $pair
     * @param  array{bool, bool}  $active
     */
    private static function packingHeadings(SimpleXlsx $sheet, array $pair, array $active, int $part): void
    {
        $nameHeight = max(40, ...array_map(fn (array $customer): int => (int) ceil(mb_strlen($customer['name']) / 40) * 14, $pair));
        $heights = [24, $nameHeight, 22, 24];
        $rowStyles = ['template-title', 'bold', 'default', 'template-header'];

        foreach ($heights as $index => $height) {
            $row = $sheet->rowCount() + 1;
            $cells = [];
            $styles = [];

            foreach ($active as $side => $hasItems) {
                if ($side && ! $hasItems) {
                    break;
                }

                if ($side) {
                    $cells[] = '';
                    $styles[] = 'default';
                }

                $values = $hasItems ? match ($index) {
                    0 => ['PACKING LIST', '', ''],
                    1 => [$pair[$side]['name'].($part > 1 ? ' (continued)' : ''), '', ''],
                    2 => [$pair[$side]['date'], '', ''],
                    3 => ['ITEM', 'QTY', 'UNIT'],
                } : ['', '', ''];
                array_push($cells, ...$values);
                array_push($styles, ...array_fill(0, 3, $hasItems ? $rowStyles[$index] : 'default'));

                if ($hasItems && $index < 3) {
                    $sheet->merge(($side ? 'E' : 'A').$row.':'.($side ? 'G' : 'C').$row);
                }
            }

            $sheet->addRow($cells, $styles, $height);
        }
    }

    /**
     * @param  list<array{name: string, orders: list<array{id: int, time: string}>}>  $people
     * @return list<array{id: int, name: string, date: string, lines: list<array{productKey: int|string, product: string, unit: string, thousandths: int, priceCents: int, totalCents: int}>}>
     */
    private static function customers(array $people, string $period): array
    {
        $orderIds = collect($people)->flatMap(fn (array $person): array => array_column($person['orders'], 'id'))->all();
        $items = OrderItem::with('product:id,name')->whereIn('order_id', $orderIds)->get()->groupBy('order_id');

        return array_map(function (array $person) use ($items, $period): array {
            $lines = collect($person['orders'])->flatMap(fn (array $order): Collection => $items->get($order['id'], collect()))
                ->map(fn (OrderItem $item): array => [
                    'productKey' => $item->product_id ?? 'deleted: '.$item->product_name,
                    'product' => $item->product?->name ?? $item->product_name,
                    'unit' => $item->variant_label,
                    'thousandths' => (int) round($item->qty * 1000),
                    'priceCents' => (int) round((float) $item->unit_price * 100),
                    'totalCents' => (int) round($item->line_total * 100),
                ])->all();

            return ['id' => $person['orders'][0]['id'], 'name' => $person['name'], 'date' => $period, 'lines' => $lines];
        }, $people);
    }

    /** @param array{product: string, unit: string} $line */
    private static function lineHeight(array $line): int
    {
        return max(24, (int) ceil(mb_strlen($line['product']) / 24) * 14, (int) ceil(mb_strlen($line['unit']) / 12) * 14);
    }
}
