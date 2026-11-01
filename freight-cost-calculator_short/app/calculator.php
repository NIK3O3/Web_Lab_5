<?php
declare(strict_types=1);

/** Decimal input to integer units, without binary floating point. */
function decimal_units(string $value, int $places, int $min, int $max, string $label): int
{
    $value = str_replace(',', '.', trim($value));
    if (!preg_match('/^\d{1,8}(?:\.\d{1,' . $places . '})?$/D', $value)) {
        throw new DomainException("$label: введіть додатне число, до $places знаків після коми.");
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $units = (int)$whole * (10 ** $places) + (int)str_pad($fraction, $places, '0');
    if ($units < $min || $units > $max) {
        throw new DomainException("$label: значення поза дозволеними межами.");
    }
    return $units;
}

/** Distance in tenths of km; money in kopecks; route coefficient in percent. */
function calculate_cost(int $distance10, int $rate, int $factor, int $fee, int $minimum): array
{
    if ($distance10 < 1 || $distance10 > 100000 || $rate < 1 || $rate > 100000
        || $factor < 50 || $factor > 300 || $fee < 0 || $fee > 10000000
        || $minimum < 0 || $minimum > 10000000) {
        throw new DomainException('Некоректні параметри розрахунку.');
    }
    // Half-up rounding to one kopeck, using only 64-bit integers.
    $transport = intdiv($distance10 * $rate * $factor + 500, 1000);
    $beforeMinimum = $transport + $fee;
    return [
        'transport_cents' => $transport,
        'minimum_extra_cents' => max(0, $minimum - $beforeMinimum),
        'total_cents' => max($minimum, $beforeMinimum),
    ];
}
