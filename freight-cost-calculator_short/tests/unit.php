<?php
declare(strict_types=1);
require __DIR__ . '/../app/calculator.php';
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks; $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo "PASS: $label\n";
}
function rejects(callable $fn, string $label): void {
    try { $fn(); } catch (DomainException) { check(true, $label); return; }
    check(false, $label);
}
check(calculate_cost(5400,2800,100,0,100000)['total_cents'] === 1512000, 'Київ — Львів: 15120 грн');
check(calculate_cost(4750,3600,105,20000,150000)['total_cents'] === 1815500, 'Крихкий, Одеса: 18155 грн');
check(calculate_cost(2200,4200,110,10000,200000)['total_cents'] === 1026400, 'Охолоджений, Харків: 10264 грн');
check(calculate_cost(5750,2800,95,0,100000)['total_cents'] === 1529500, 'Альтернативний шлях: 15295 грн');
check(calculate_cost(100,2800,100,0,100000)['total_cents'] === 100000, 'Мінімальна вартість');
check(calculate_cost(100,2800,100,0,100000)['minimum_extra_cents'] === 72000, 'Доплата до мінімуму');
check(calculate_cost(1,1,50,0,0)['total_cents'] === 0, 'Округлення менш ніж пів копійки');
check(calculate_cost(1,1,50,1,0)['total_cents'] === 1, 'Фіксований збір');
check(calculate_cost(1,10,50,0,0)['total_cents'] === 1, 'Пів копійки округлюється вгору');
check(calculate_cost(100000,100000,300,10000000,0)['total_cents'] === 3010000000, 'Максимальні межі, BIGINT');
check(decimal_units('28,50',2,1,100000,'Тариф') === 2850, 'Десяткова кома');
check(decimal_units('0.1',1,1,100000,'Відстань') === 1, 'Десята кілометра');
check(decimal_units('0',2,0,10000000,'Збір') === 0, 'Нульовий збір');
foreach (['-1','0','10000.1','1.25','abc','1e3','NaN'] as $value) {
    rejects(fn()=>decimal_units($value,1,1,100000,'Відстань'), 'Відхилення відстані ' . $value);
}
rejects(fn()=>calculate_cost(100,2800,49,0,0), 'Коефіцієнт нижче межі');
rejects(fn()=>calculate_cost(100,2800,301,0,0), 'Коефіцієнт вище межі');
rejects(fn()=>calculate_cost(100,0,100,0,0), 'Нульовий тариф');
rejects(fn()=>calculate_cost(100,2800,100,-1,0), 'Від’ємний збір');
echo "OK: $checks перевірки\n";
