<?php
declare(strict_types=1);

function save_cargo(array $data): int
{
    $id = field($data, 'id') === '' ? null : id_value($data);
    $values = [text_value($data, 'name', 'Назва', 2, 80),
        decimal_units(field($data, 'rate'), 2, 1, 100000, 'Тариф'),
        decimal_units(field($data, 'minimum'), 2, 0, 10000000, 'Мінімальна вартість'),
        text_value($data, 'description', 'Опис', 0, 200)];
    if ($id) {
        if (!run('SELECT id FROM cargo_types WHERE id=?', [$id])->fetch()) throw new DomainException('Тип вантажу не знайдено.');
        run('UPDATE cargo_types SET name=?,rate_cents=?,minimum_cents=?,description=? WHERE id=?', [...$values, $id]);
    } else {
        run('INSERT INTO cargo_types (name,rate_cents,minimum_cents,description) VALUES (?,?,?,?)', $values);
        $id = (int)db()->lastInsertId();
    }
    return $id;
}
function save_route(array $data): int
{
    $id = field($data, 'id') === '' ? null : id_value($data);
    $origin = text_value($data, 'origin', 'Звідки', 2, 80);
    $destination = text_value($data, 'destination', 'Куди', 2, 80);
    if (preg_match('/^' . preg_quote($origin, '/') . '$/iu', $destination)) throw new DomainException('Пункти відправлення та призначення мають відрізнятися.');
    $values = [$origin, $destination, text_value($data, 'via', 'Шлях', 2, 120),
        decimal_units(field($data, 'distance'), 1, 1, 100000, 'Відстань'),
        decimal_units(field($data, 'factor'), 2, 50, 300, 'Коефіцієнт'),
        decimal_units(field($data, 'fee'), 2, 0, 10000000, 'Збір')];
    if ($id) {
        if (!run('SELECT id FROM routes WHERE id=?', [$id])->fetch()) throw new DomainException('Маршрут не знайдено.');
        run('UPDATE routes SET origin=?,destination=?,via=?,distance10=?,factor_pct=?,fee_cents=? WHERE id=?', [...$values, $id]);
    } else {
        run('INSERT INTO routes (origin,destination,via,distance10,factor_pct,fee_cents) VALUES (?,?,?,?,?,?)', $values);
        $id = (int)db()->lastInsertId();
    }
    return $id;
}
function create_quote(array $data): int
{
    $cargoId = id_value($data, 'cargo_id');
    $routeId = id_value($data, 'route_id');
    $note = text_value($data, 'note', 'Примітка', 0, 200);
    $custom = field($data, 'custom_distance');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cargo = run('SELECT * FROM cargo_types WHERE id=? FOR SHARE', [$cargoId])->fetch();
        $route = run('SELECT * FROM routes WHERE id=? FOR SHARE', [$routeId])->fetch();
        if (!$cargo || !$route) throw new DomainException('Тип вантажу або маршрут не знайдено.');
        $distance = $custom === '' ? (int)$route['distance10']
            : decimal_units($custom, 1, 1, 100000, 'Відстань');
        $cost = calculate_cost($distance, (int)$cargo['rate_cents'], (int)$route['factor_pct'],
            (int)$route['fee_cents'], (int)$cargo['minimum_cents']);
        $sql = 'INSERT INTO quotes (cargo_id,route_id,cargo_name,route_name,distance10,
            distance_custom,rate_cents,factor_pct,fee_cents,minimum_cents,
            transport_cents,minimum_extra_cents,total_cents,note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        run($sql, [$cargoId, $routeId, $cargo['name'], route_label($route), $distance,
            (int)($custom !== ''), $cargo['rate_cents'], $route['factor_pct'], $route['fee_cents'],
            $cargo['minimum_cents'], $cost['transport_cents'], $cost['minimum_extra_cents'],
            $cost['total_cents'], $note]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
function delete_record(string $kind, int $id): void
{
    $table = match ($kind) { 'cargo' => 'cargo_types', 'route' => 'routes', 'quote' => 'quotes',
        default => throw new DomainException('Невідомий тип запису.') };
    // Table names come exclusively from the allowlist above.
    if (run("DELETE FROM $table WHERE id=?", [$id])->rowCount() === 0) throw new DomainException('Запис не знайдено.');
}
