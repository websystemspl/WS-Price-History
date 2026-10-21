<?php

/**
 * Unit tests for the pure price-history logic. No WordPress or PHPUnit needed:
 *
 *   php tests/LowestPriceCalculatorTest.php
 *
 * Exit code 0 = all passed.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__ . '/../App/LowestPriceCalculator.php';

use WsPriceHistory\App\LowestPriceCalculator as C;

const DAY = 86400;
$now = 1790000000; // fixed "now"
$failures = 0;
$count = 0;

function row($daysAgo, $price, $regular = null, $onSale = null)
{
    global $now;
    return C::makeRow($now - (int) round($daysAgo * DAY), $price, $regular, $onSale);
}

function check($name, $expected, $actual)
{
    global $failures, $count;
    $count++;
    if ($expected === $actual || (is_float($expected) && is_float($actual) && abs($expected - $actual) < 0.0001)) {
        echo "ok   $name\n";
        return;
    }
    $failures++;
    echo "FAIL $name: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
}

function lowest(array $rows, array $current, array $options = [])
{
    global $now;
    $r = C::lowestPrice($rows, $current, $now, $options);
    return $r === null ? null : $r['price'];
}

function source(array $rows, array $current, array $options = [])
{
    global $now;
    $r = C::lowestPrice($rows, $current, $now, $options);
    return $r === null ? null : $r['source'];
}

$sale80 = ['price' => 80.0, 'regular' => 100.0, 'on_sale' => true];

// 1. The core bug of 1.0.x: the sale price itself must not be the "lowest price".
check(
    'sale price is excluded, price before the reduction is shown',
    100.0,
    lowest([row(60, 100, 100, false), row(0, 80, 100, true)], $sale80)
);
check(
    'reduction started 10 days ago: window is 30 days before the reduction, not before today',
    100.0,
    lowest([row(60, 100, 100, false), row(10, 80, 100, true)], $sale80)
);

// 2. Lower regular price within 30 days before the reduction.
check(
    'regular price lowered 20 days before the reduction',
    90.0,
    lowest([row(90, 120, 120, false), row(20, 90, 90, false), row(0, 80, 90, true)], ['price' => 80.0, 'regular' => 90.0, 'on_sale' => true])
);

// 3. Window edges: a price that applied at the start of the window counts, an older one does not.
check(
    'price that ended before the window is ignored',
    100.0,
    lowest([row(60, 50, 50, false), row(40, 100, 100, false), row(0, 80, 100, true)], $sale80)
);
check(
    'price that was still valid inside the window counts',
    50.0,
    lowest([row(60, 50, 50, false), row(25, 100, 100, false), row(0, 80, 100, true)], $sale80)
);
check(
    'price valid until exactly the window start is ignored',
    100.0,
    lowest([row(60, 50, 50, false), row(30, 100, 100, false), row(0, 80, 100, true)], $sale80)
);
check(
    'price valid until one second after the window start counts',
    50.0,
    lowest([row(60, 50, 50, false), C::makeRow($now - 30 * DAY + 1, 100, 100, false), row(0, 80, 100, true)], $sale80)
);

// 4. An earlier promotion inside the 30 days counts.
check(
    'previous promotion within 30 days counts',
    70.0,
    lowest([row(90, 100, 100, false), row(20, 70, 100, true), row(10, 100, 100, false), row(0, 80, 100, true)], $sale80)
);
check(
    'previous promotion older than 30 days before the reduction is ignored',
    100.0,
    lowest([row(90, 100, 100, false), row(50, 70, 100, true), row(40, 100, 100, false), row(0, 80, 100, true)], $sale80)
);

// 5. Product offered for less than 30 days (art. 4 ust. 3).
check(
    'new product: lowest price since it was first offered',
    120.0,
    lowest([row(10, 120, 120, false), row(0, 100, 120, true)], ['price' => 100.0, 'regular' => 120.0, 'on_sale' => true])
);
check(
    'new product with a regular price change',
    110.0,
    lowest([row(10, 120, 120, false), row(5, 110, 110, false), row(0, 100, 110, true)], ['price' => 100.0, 'regular' => 110.0, 'on_sale' => true])
);

// 6. Scheduled sale applied by cron: no row yet for the running sale.
check(
    'running scheduled sale without a stored row uses sale_from as reference',
    100.0,
    lowest([row(60, 100, 100, false)], $sale80 + ['sale_from' => $now - 6 * 3600])
);
$r = C::lowestPrice([row(60, 100, 100, false)], $sale80 + ['sale_from' => $now - 6 * 3600], $now);
check('reference time is the scheduled start', $now - 6 * 3600, $r['reference']);
check(
    'regular price changed 2 days before a scheduled sale counts',
    90.0,
    lowest([row(60, 100, 100, false), row(2, 90, 100, false)], $sale80 + ['sale_from' => $now - 3600])
);

// 7. Stepped reductions.
$stepped = [row(60, 100, 100, false), row(10, 90, 100, true), row(0, 80, 100, true)];
check('second price cut: 30 days before the latest cut (includes the first sale price)', 90.0, lowest($stepped, $sale80));
check('second price cut with first_reduction option: price before the promotion', 100.0, lowest($stepped, $sale80, ['first_reduction' => true]));
check(
    'sale price raised during a promotion: reference stays at the last cut',
    100.0,
    lowest([row(60, 100, 100, false), row(10, 70, 100, true), row(2, 85, 100, true)], ['price' => 85.0, 'regular' => 100.0, 'on_sale' => true])
);

// 8. Not a reduction.
check('not on sale: nothing to show', null, lowest([row(60, 100, 100, false)], ['price' => 100.0, 'regular' => 100.0, 'on_sale' => false]));
check('sale price not below regular: nothing to show', null, lowest([row(60, 100, 100, false)], ['price' => 100.0, 'regular' => 100.0, 'on_sale' => true]));

// 9. Perishable goods (art. 4 ust. 4): price before the first reduction, no 30-day limit.
check('perishable: price before the first reduction', 100.0, lowest($stepped, $sale80, ['perishable' => true]));
check('perishable: source', C::SOURCE_PERISHABLE, source($stepped, $sale80, ['perishable' => true]));
check(
    'perishable: price older than 30 days still used',
    100.0,
    lowest([row(200, 100, 100, false), row(45, 80, 100, true)], $sale80, ['perishable' => true])
);

// 10. No history from before the reduction.
check('added already on sale: regular price fallback', 100.0, lowest([row(5, 80, 100, true)], $sale80));
check('added already on sale: source is fallback', C::SOURCE_FALLBACK_REGULAR, source([row(5, 80, 100, true)], $sale80));
check('empty history: regular price fallback', 100.0, lowest([], $sale80));
check('empty history and no regular price: nothing', null, lowest([], ['price' => 80.0, 'regular' => null, 'on_sale' => true]));

// 11. Rows written by 1.0.x (no regular price, no sale flag; date only).
check(
    'legacy rows: sale price recorded by 1.0.x is detected and excluded',
    100.0,
    lowest([row(40, 100), row(5, 80)], $sale80)
);
check(
    'legacy rows mixed with new rows',
    90.0,
    lowest([row(40, 100), row(20, 90), row(5, 80, 100, true)], $sale80)
);

// 12. Robustness.
check('rows in any order', 100.0, lowest([row(0, 80, 100, true), row(60, 100, 100, false)], $sale80));
check('rows from the future are ignored', 100.0, lowest([row(60, 100, 100, false), C::makeRow($now + DAY, 10, 10, false)], $sale80));
check(
    'zero-length state (two rows at the same time) is ignored',
    100.0,
    lowest([C::makeRow($now - 10 * DAY, 50, 50, false), C::makeRow($now - 10 * DAY, 100, 100, false), row(0, 80, 100, true)], $sale80)
);
check('custom window length (days option)', 50.0, lowest([row(60, 50, 50, false), row(40, 100, 100, false), row(0, 80, 100, true)], $sale80, ['days' => 90]));

// shouldRecord
check('shouldRecord: first row', true, C::shouldRecord(null, C::makeRow(0, 100, 100, false)));
check('shouldRecord: same state', false, C::shouldRecord(row(5, 100, 100, false), C::makeRow(0, 100, 100, false)));
check('shouldRecord: price change', true, C::shouldRecord(row(5, 100, 100, false), C::makeRow(0, 80, 100, true)));
check('shouldRecord: regular change only (still on sale)', true, C::shouldRecord(row(5, 80, 100, true), C::makeRow(0, 80, 120, true)));
check('shouldRecord: legacy row is completed once', true, C::shouldRecord(row(5, 100), C::makeRow(0, 100, 100, false)));

// effectiveTime
check('effectiveTime: manual save uses now', $now, C::effectiveTime($now, $now - DAY, true, null, null));
check('effectiveTime: cron lag uses sale_from', $now - 3600, C::effectiveTime($now, $now - DAY, true, $now - 3600, null));
check('effectiveTime: sale end uses sale_to', $now - 3600, C::effectiveTime($now, $now - DAY, false, null, $now - 3600));
check('effectiveTime: future sale_from uses now', $now, C::effectiveTime($now, $now - DAY, true, $now + 3600, null));
check('effectiveTime: sale_from before last row uses now', $now, C::effectiveTime($now, $now - 3600, true, $now - DAY, null));

echo "\n$count tests, $failures failed\n";
exit($failures === 0 ? 0 : 1);
