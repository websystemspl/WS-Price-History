<?php

namespace WsPriceHistory\App;

/**
 * Pure price-history logic (no WordPress calls), so it can be unit tested.
 *
 * Polish Act on informing about prices of goods and services (Dz.U. 2023 poz. 168), art. 4:
 *  - ust. 2: next to a reduced price show the lowest price that applied in the 30 days
 *            BEFORE the reduction was introduced,
 *  - ust. 3: product offered for less than 30 days: lowest price from the start of the offer
 *            until the reduction,
 *  - ust. 4: perishable goods: the price from before the first reduction.
 *
 * A history row is an array:
 *  - time    int        unix timestamp (UTC) from which this state applied,
 *  - price   float      effective (charged) price,
 *  - regular float|null regular price (null for rows written by plugin <= 1.0.3),
 *  - on_sale bool|null  whether the row was a reduced price (null = unknown, legacy row).
 *
 * Each row applies from its time until the time of the next row.
 */
class LowestPriceCalculator
{
    const SOURCE_HISTORY = 'history';
    const SOURCE_FALLBACK_REGULAR = 'fallback_regular';
    const SOURCE_PERISHABLE = 'perishable';

    /**
     * Normalises a raw state into a row.
     */
    public static function makeRow($time, $price, $regular = null, $onSale = null)
    {
        return [
            'time' => (int) $time,
            'price' => (float) $price,
            'regular' => ($regular === null || $regular === '') ? null : (float) $regular,
            'on_sale' => $onSale === null ? null : (bool) $onSale,
        ];
    }

    public static function samePrice($a, $b)
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        return abs((float) $a - (float) $b) < 0.00005;
    }

    /**
     * Should a new snapshot be written, given the latest stored row (or null)?
     */
    public static function shouldRecord($lastRow, array $newRow)
    {
        if ($lastRow === null) {
            return true;
        }
        if (!self::samePrice($lastRow['price'], $newRow['price'])) {
            return true;
        }
        if ($lastRow['on_sale'] === null || $lastRow['regular'] === null) {
            // Legacy row: upgrade it with a full snapshot once.
            return true;
        }
        if ($lastRow['on_sale'] !== $newRow['on_sale']) {
            return true;
        }
        return !self::samePrice($lastRow['regular'], $newRow['regular']);
    }

    /**
     * When did the new state start to apply? For a scheduled sale that started (or ended)
     * between the last stored row and now, use the schedule date instead of "now", because
     * WooCommerce applies scheduled prices by a daily cron that may run late.
     *
     * @param int|null $lastTime   time of the latest stored row
     * @param bool     $onSale     new state is a reduced price
     * @param int|null $saleFrom   date_on_sale_from timestamp
     * @param int|null $saleTo     date_on_sale_to timestamp
     */
    public static function effectiveTime($now, $lastTime, $onSale, $saleFrom, $saleTo)
    {
        $candidate = null;
        if ($onSale && $saleFrom) {
            $candidate = (int) $saleFrom;
        } elseif (!$onSale && $saleTo) {
            $candidate = (int) $saleTo;
        }
        if ($candidate === null || $candidate > $now) {
            return (int) $now;
        }
        if ($lastTime !== null && $candidate <= $lastTime) {
            return (int) $now;
        }
        return $candidate;
    }

    /**
     * Lowest price required by art. 4 for the current reduced price.
     *
     * @param array $rows    stored history rows (any order)
     * @param array $current current state: price, regular, on_sale, sale_from (int|null)
     * @param int   $now
     * @param array $options days (30), perishable (false), first_reduction (false: reference is the
     *                       most recent price cut, i.e. each further cut has its own 30-day window;
     *                       true: reference is the first cut of an uninterrupted promotion)
     * @return array|null ['price' => float, 'reference' => int, 'source' => string] or null when the
     *                    current price is not a reduction
     */
    public static function lowestPrice(array $rows, array $current, $now, array $options = [])
    {
        $days = isset($options['days']) ? (int) $options['days'] : 30;
        $perishable = !empty($options['perishable']);
        $firstReduction = !empty($options['first_reduction']);

        if (empty($current['on_sale'])) {
            return null;
        }
        $currentRow = self::makeRow($now, $current['price'], isset($current['regular']) ? $current['regular'] : null, true);
        if ($currentRow['regular'] !== null && $currentRow['price'] >= $currentRow['regular']) {
            return null;
        }

        usort($rows, function ($a, $b) {
            return $a['time'] - $b['time'];
        });
        $rows = array_values(array_filter($rows, function ($r) use ($now) {
            return $r['time'] <= $now;
        }));

        // Make sure the current reduced state is the last row (e.g. cron has not run yet).
        $last = empty($rows) ? null : $rows[count($rows) - 1];
        if ($last === null || !self::samePrice($last['price'], $currentRow['price']) || $last['on_sale'] === false) {
            $saleFrom = isset($current['sale_from']) ? $current['sale_from'] : null;
            $currentRow['time'] = self::effectiveTime($now, $last === null ? null : $last['time'], true, $saleFrom, null);
            if ($last !== null && $currentRow['time'] < $last['time']) {
                $currentRow['time'] = $last['time'];
            }
            $rows[] = $currentRow;
        } else {
            $rows[count($rows) - 1]['on_sale'] = true;
        }

        $rows = self::inferLegacyOnSale($rows, $currentRow['regular']);
        $n = count($rows);

        // Start of the uninterrupted reduction that is running now.
        $start = $n - 1;
        while ($start > 0 && $rows[$start - 1]['on_sale'] === true) {
            $start--;
        }

        if ($perishable) {
            if ($start === 0) {
                return self::fallback($rows[0], $currentRow, $rows[0]['time']);
            }
            return [
                'price' => $rows[$start - 1]['price'],
                'reference' => $rows[$start]['time'],
                'source' => self::SOURCE_PERISHABLE,
            ];
        }

        $ref = $start;
        if (!$firstReduction) {
            for ($j = $n - 1; $j > $start; $j--) {
                if ($rows[$j]['price'] < $rows[$j - 1]['price'] - 0.00005) {
                    $ref = $j;
                    break;
                }
            }
        }
        $refTime = $rows[$ref]['time'];
        $windowStart = $refTime - $days * 86400;

        $lowest = null;
        for ($k = 0; $k < $ref; $k++) {
            $validTo = $rows[$k + 1]['time'];
            if ($validTo <= $windowStart || $validTo <= $rows[$k]['time']) {
                continue; // ended before the window or applied for zero time
            }
            if ($lowest === null || $rows[$k]['price'] < $lowest) {
                $lowest = $rows[$k]['price'];
            }
        }

        if ($lowest === null) {
            return self::fallback($rows[$ref], $currentRow, $refTime);
        }

        return ['price' => $lowest, 'reference' => $refTime, 'source' => self::SOURCE_HISTORY];
    }

    /**
     * No price is known from before the reduction (product added already reduced, or history
     * started after the reduction). The regular price is the best available reference.
     */
    private static function fallback(array $refRow, array $currentRow, $refTime)
    {
        $regular = $refRow['regular'] !== null ? $refRow['regular'] : $currentRow['regular'];
        if ($regular === null) {
            return null;
        }
        return ['price' => $regular, 'reference' => $refTime, 'source' => self::SOURCE_FALLBACK_REGULAR];
    }

    /**
     * Rows written by plugin <= 1.0.3 have no regular price / sale flag. Treat such a row as a
     * reduction when its price is below the nearest known later regular price.
     */
    private static function inferLegacyOnSale(array $rows, $currentRegular)
    {
        $nextRegular = $currentRegular;
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            if ($rows[$i]['regular'] !== null) {
                $nextRegular = $rows[$i]['regular'];
            }
            if ($rows[$i]['on_sale'] === null) {
                $rows[$i]['on_sale'] = $nextRegular !== null && $rows[$i]['price'] < $nextRegular - 0.00005;
            }
        }
        return $rows;
    }
}
