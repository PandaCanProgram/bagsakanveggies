<?php

namespace App\Services;

use App\Models\Product;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class OrderSummaryWorkbook
{
    /**
     * @param  list<array{name: string, orders: list<array{id: int, time: string}>, lines: list<array{product: string, size: string, qty: float}>}>  $people
     */
    public static function build(CarbonImmutable $day, array $people): SimpleXlsx
    {
        $matrix = [];

        foreach ($people as $person) {
            $id = $person['orders'][0]['id'];

            foreach ($person['lines'] as $line) {
                [$quantity, $unit] = self::quantityAndUnit($line['qty'], $line['size']);
                $key = Str::lower(Str::squish($line['product'])).'|'.$unit;
                $matrix[$key] ??= ['product' => $line['product'], 'unit' => $unit, 'quantities' => []];
                $matrix[$key]['quantities'][$id] = ($matrix[$key]['quantities'][$id] ?? 0) + (int) round($quantity * 1000);
            }
        }

        uasort($matrix, fn (array $a, array $b): int => strnatcasecmp($a['product'].' '.$a['unit'], $b['product'].' '.$b['unit']));
        $singlePage = array_sum(array_map(self::lineHeight(...), $matrix)) <= 540;
        $picking = (new SimpleXlsx('Unified picking list', [29, 10, 15, 14, 16]))->forPrint(9, $singlePage, 4)
            ->addRow(['UNIFIED PICKING LIST'], 'template-title', 24)->merge('A1:E1')
            ->addRow([$day->format('F j, Y')], 'bold', 22)->merge('A2:E2')
            ->addRow(['Enter buying estimates in Unit Price. The total appears when every item is priced.'], 'default', 32)->merge('A3:E3')
            ->addRow(['ITEM', 'QTY', 'UNIT', 'Unit Price', 'Total Price'], 'template-header', 24);
        $summaries = [];
        $pickingReferences = [];
        $clientReferences = [];

        foreach (array_chunk($people, 4) as $batchIndex => $batch) {
            $name = 'Packing summary'.($batchIndex ? ' '.($batchIndex + 1) : '');
            $lastColumn = SimpleXlsx::columnName(3 + count($batch));
            $headerHeight = max(40, ...array_map(fn (array $person): int => (int) ceil(mb_strlen($person['name']) / 15) * 14, $batch));
            $sheet = (new SimpleXlsx($name, [27, 9, 15, ...array_fill(0, count($batch), 16)]))->forPrint(9, $singlePage, 4)
                ->addRow(['PACKING SUMMARY'], 'template-title', 24)->merge('A1:'.$lastColumn.'1')
                ->addRow([$day->format('F j, Y')], 'bold', 22)->merge('A2:'.$lastColumn.'2')
                ->addRow()
                ->addRow(['ITEM', 'QTY', 'UNIT', ...array_column($batch, 'name')], 'template-header', $headerHeight);
            $batchIds = array_map(fn (array $person): int => $person['orders'][0]['id'], $batch);

            foreach ($matrix as $key => $line) {
                $quantities = array_intersect_key($line['quantities'], array_flip($batchIds));

                if ($quantities === []) {
                    continue;
                }

                $row = $sheet->rowCount() + 1;
                $total = array_sum($quantities) / 1000;
                $cells = [$line['product'], SimpleXlsx::formula('SUM(D'.$row.':'.$lastColumn.$row.')', $total), $line['unit']];

                foreach ($batch as $index => $person) {
                    $id = $person['orders'][0]['id'];
                    $qty = $quantities[$id] ?? null;
                    $cells[] = $qty === null ? '' : $qty / 1000;

                    if ($qty !== null) {
                        $clientReferences[$id][] = [
                            'product' => $line['product'], 'unit' => $line['unit'], 'qty' => $qty / 1000,
                            'reference' => "'".$name."'!".SimpleXlsx::columnName($index + 4).$row,
                        ];
                    }
                }

                $pickingReferences[$key][] = "'".$name."'!B".$row;
                $sheet->addRow($cells, ['cell', 'quantity', 'cell', ...array_fill(0, count($batch), 'quantity')], self::lineHeight($line));
            }

            $summaries[] = $sheet;
        }

        foreach ($matrix as $key => $line) {
            $row = $picking->rowCount() + 1;
            $picking->addRow([
                $line['product'], SimpleXlsx::formula('SUM('.implode(',', $pickingReferences[$key]).')', array_sum($line['quantities']) / 1000), $line['unit'], '',
                SimpleXlsx::formula('IF(D'.$row.'="","",ROUND(B'.$row.'*D'.$row.',2))', ''),
            ], ['cell', 'quantity', 'cell', 'money', 'money'], self::lineHeight($line));
        }

        $last = $picking->rowCount();
        $picking->addRow(['TOTAL', '', '', '', $last >= 5
            ? SimpleXlsx::formula('IF(COUNT(D5:D'.$last.')<COUNTA(A5:A'.$last.'),"",SUM(E5:E'.$last.'))', '') : ''], ['total', 'total', 'total', 'total', 'money-total'], 24);

        foreach ($summaries as $summary) {
            $picking->addSheet($summary);
        }

        $packing = (new SimpleXlsx('Packing lists', [25, 9, 15, 3, 25, 9, 15]))->forPrint();

        foreach (array_chunk($people, 2) as $pair) {
            $lists = array_map(fn (array $person): array => $clientReferences[$person['orders'][0]['id']] ?? [], $pair);
            $offsets = [0, 0];
            $part = 0;

            do {
                if ($packing->rowCount()) {
                    $packing->breakBeforeRow($packing->rowCount() + 1);
                }

                $part++;
                $top = $packing->rowCount() + 1;
                $nameHeight = max(40, ...array_map(fn (array $person): int => (int) ceil(mb_strlen($person['name']) / 40) * 14, $pair));
                $packing->addRow(['PACKING LIST', '', '', '', isset($pair[1]) ? 'PACKING LIST' : '', '', ''], 'template-title', 24)
                    ->merge('A'.$top.':C'.$top)->merge('E'.$top.':G'.$top)
                    ->addRow([$pair[0]['name'].($part > 1 ? ' (continued)' : ''), '', '', '', isset($pair[1]) ? $pair[1]['name'].($part > 1 ? ' (continued)' : '') : '', '', ''], 'bold', $nameHeight)
                    ->merge('A'.($top + 1).':C'.($top + 1))->merge('E'.($top + 1).':G'.($top + 1))
                    ->addRow([$day->format('F j, Y'), '', '', '', isset($pair[1]) ? $day->format('F j, Y') : '', '', ''], 'default', 22)
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

        return $picking->addSheet($packing);
    }

    /** @return array{float, string} */
    private static function quantityAndUnit(float $quantity, string $size): array
    {
        $size = Str::lower(Str::squish($size));

        if (preg_match('/\bbags?\b/', $size)) {
            return [$quantity, preg_replace('/\bbags\b/', 'bag', $size)];
        }

        if (($kilos = Product::kilosPerUnit($size)) !== null) {
            return [round($quantity * $kilos, 3), 'kg'];
        }

        if (preg_match('/(?:(\d+)\s*)?(?<![a-z])(?:pcs?|pieces?)\b/', $size, $matches)) {
            return [$quantity * max(1, (int) ($matches[1] ?? 1)), 'pcs'];
        }

        return [$quantity, $size];
    }

    /** @param array{product: string, unit: string} $line */
    private static function lineHeight(array $line): int
    {
        return max(24, (int) ceil(mb_strlen($line['product']) / 24) * 14, (int) ceil(mb_strlen($line['unit']) / 12) * 14);
    }
}
