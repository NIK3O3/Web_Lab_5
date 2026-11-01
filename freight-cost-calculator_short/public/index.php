<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/repository.php';

$page = 'calculator'; $error = ''; $item = null; $quote = null;
$cargo = []; $routes = []; $quotes = []; $count = 0; $pages = 1; $number = 1;
$stats = ['cargo' => 0, 'routes' => 0, 'quotes' => 0];
try {
    $page = field($_GET, 'page') ?: 'calculator';
    if (!in_array($page, ['calculator','history','cargo','routes','cargo_edit','route_edit','quote'], true)) {
        http_response_code(404); $page = 'not_found';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], field($_POST, 'csrf'))) {
            http_response_code(403); throw new DomainException('Сесію форми не підтверджено. Оновіть сторінку та повторіть дію.');
        }
        $action = field($_POST, 'action');
        $target = match ($action) {
            'create_quote' => '?page=quote&id=' . create_quote($_POST),
            'save_cargo' => (save_cargo($_POST) ? '?page=cargo' : '?page=cargo'),
            'save_route' => (save_route($_POST) ? '?page=routes' : '?page=routes'),
            'delete' => (function (): string {
                $kind = field($_POST, 'kind');
                delete_record($kind, id_value($_POST));
                return '?page=' . match ($kind) { 'cargo' => 'cargo', 'route' => 'routes', 'quote' => 'history' };
            })(),
            default => throw new DomainException('Невідома дія форми.'),
        };
        $_SESSION['flash'] = 'Дані успішно збережено.';
        header('Location: ' . $target, true, 303); exit;
    }
} catch (DomainException $ex) {
    if (http_response_code() !== 403) http_response_code(422);
    $error = $ex->getMessage();
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        http_response_code(422);
        $error = 'Запис дублюється або вже використовується в історії. Змініть реквізити; використаний довідник видалити не можна.';
    } else {
        http_response_code(503); $error = 'База даних тимчасово недоступна. Перевірте запуск контейнерів.'; error_log((string)$ex);
    }
} catch (Throwable $ex) {
    http_response_code(500); $error = 'Не вдалося виконати дію. Повторіть спробу.'; error_log((string)$ex);
}
try {
    $cargo = run('SELECT * FROM cargo_types ORDER BY id')->fetchAll();
    $routes = run('SELECT * FROM routes ORDER BY id')->fetchAll();
    $stats = ['cargo' => count($cargo), 'routes' => count($routes), 'quotes' => (int)run('SELECT COUNT(*) FROM quotes')->fetchColumn()];
    if (in_array($page, ['cargo_edit','route_edit'], true) && field($_GET, 'id') !== '') {
        $table = $page === 'cargo_edit' ? 'cargo_types' : 'routes';
        $item = run("SELECT * FROM $table WHERE id=?", [id_value($_GET)])->fetch();
        if (!$item) { http_response_code(404); $page = 'not_found'; }
    }
    if ($page === 'quote') {
        $quote = run('SELECT * FROM quotes WHERE id=?', [id_value($_GET)])->fetch();
        if (!$quote) { http_response_code(404); $page = 'not_found'; }
    }
    if ($page === 'history') {
        $query = field($_GET, 'q');
        if (strlen($query) > 300) throw new DomainException('Пошуковий запит надто довгий.');
        $cargoFilter = field($_GET, 'cargo_id');
        $params = []; $where = [];
        if ($query !== '') { $where[] = '(cargo_name LIKE ? OR route_name LIKE ? OR note LIKE ?)'; $params = array_fill(0, 3, '%' . $query . '%'); }
        if ($cargoFilter !== '') { $where[] = 'cargo_id=?'; $params[] = id_value($_GET, 'cargo_id'); }
        $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = (int)run('SELECT COUNT(*) FROM quotes' . $condition, $params)->fetchColumn();
        $pages = max(1, (int)ceil($count / 10));
        $number = field($_GET, 'p') === '' ? 1 : id_value($_GET, 'p');
        $number = min($number, $pages); $offset = ($number - 1) * 10;
        $quotes = run('SELECT * FROM quotes' . $condition . " ORDER BY id DESC LIMIT 10 OFFSET $offset", $params)->fetchAll();
    }
} catch (DomainException $ex) { http_response_code(422); $error = $ex->getMessage();
} catch (PDOException $ex) { http_response_code(503); $error = 'База даних тимчасово недоступна. Перевірте запуск контейнерів.'; error_log((string)$ex); }
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
function old(string $key, string $fallback = ''): string {
    $value = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$key] ?? '') : $fallback;
    return e(is_string($value) ? trim($value) : '');
}
function selected(mixed $a, mixed $b): string { return is_scalar($a) && (string)$a === (string)$b ? ' selected' : ''; }
function query_text(string $key): string { return is_string($_GET[$key] ?? null) ? $_GET[$key] : ''; }
function delete_form(string $kind, int $id): void { ?>
<form method="post" class="inline" data-confirm="Видалити цей запис? Дію неможливо скасувати.">
    <?=csrf()?><input type="hidden" name="action" value="delete"><input type="hidden" name="kind" value="<?=e($kind)?>"><input type="hidden" name="id" value="<?=$id?>">
    <button class="text-button danger" type="submit">Видалити</button>
</form><?php }
?><!doctype html>
<html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Вартість перевезення · CargoCalc</title><meta name="description" content="Навчальний калькулятор вартості автомобільних перевезень">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script></head>
<body><header class="header"><div class="header-inner"><a class="brand" href="?page=calculator"><span class="brand-icon">↗</span> CargoCalc<span class="brand-note">автомобільні перевезення</span></a>
<nav aria-label="Головна навігація"><?php foreach (['calculator'=>'Калькулятор','history'=>'Історія','cargo'=>'Типи вантажів','routes'=>'Маршрути'] as $key=>$label): ?>
<a href="?page=<?=$key?>"<?=($page === $key || ($key === 'cargo' && $page === 'cargo_edit') || ($key === 'routes' && $page === 'route_edit')) ? ' aria-current="page"' : ''?>><?=$label?></a><?php endforeach ?></nav></div></header>
<main class="container">
<?php if ($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif ?>
<?php if ($flash): ?><div class="notice success" role="status"><?=e($flash)?></div><?php endif ?>
<?php if ($page === 'calculator'): ?>
<div class="page-heading"><div><p class="eyebrow">ПЛАНУВАННЯ ПЕРЕВЕЗЕНЬ</p><h1>Вартість маршруту<br><span>за кілька кроків</span></h1><p class="muted intro">Оберіть вантаж і напрямок — отримайте розрахунок з усіма складовими.</p></div><span class="tag">PHP + MySQL · Практична № 5</span></div>
<div class="stats"><div><span>Типи вантажів</span><strong><?=$stats['cargo']?></strong></div><div><span>Готові маршрути</span><strong><?=$stats['routes']?></strong></div><div><span>Збережені розрахунки</span><strong><?=$stats['quotes']?></strong></div></div>
<div class="calculator-grid"><section class="panel"><div class="panel-heading"><h2>Параметри перевезення</h2><span class="muted small">01 — 03</span></div>
<form method="post" class="form" id="calculator-form"><?=csrf()?><input type="hidden" name="action" value="create_quote">
<label for="cargo_id"><span class="step">01</span> Тип вантажу</label><select id="cargo_id" name="cargo_id" required><option value="">Оберіть тип вантажу</option>
<?php foreach ($cargo as $c): ?><option value="<?=$c['id']?>"<?=selected($_POST['cargo_id'] ?? $_GET['cargo_id'] ?? '', $c['id'])?>><?=e($c['name'])?> · <?=money($c['rate_cents'])?> грн/км</option><?php endforeach ?></select>
<label for="route_id"><span class="step">02</span> Маршрут перевезення</label><select id="route_id" name="route_id" required><option value="">Оберіть маршрут</option>
<?php foreach ($routes as $r): ?><option value="<?=$r['id']?>" data-distance="<?=decimal_string($r['distance10'],1)?>" data-factor="<?=decimal_string($r['factor_pct'],2)?>" data-fee="<?=money($r['fee_cents'])?>"<?=selected($_POST['route_id'] ?? $_GET['route_id'] ?? '', $r['id'])?>><?=e(route_label($r))?></option><?php endforeach ?></select>
<p class="field-help" id="route-info" aria-live="polite">Відстань і коефіцієнт підставляються з довідника маршрутів.</p>
<label for="custom_distance"><span class="step">03</span> Власна відстань <span class="optional">необов’язково</span></label><div class="input-unit"><input id="custom_distance" name="custom_distance" type="number" min="0.1" max="10000" step="0.1" placeholder="Відстань обраного маршруту" value="<?=old('custom_distance')?>"><span>км</span></div><p class="field-help">Залиште порожнім для відстані з довідника. Межі: 0,1–10 000 км.</p>
<label for="note">Примітка <span class="optional">необов’язково</span></label><input id="note" name="note" maxlength="200" placeholder="Наприклад, доставка обладнання" value="<?=old('note')?>">
<button class="button primary wide" type="submit"<?=!$cargo || !$routes ? ' disabled' : ''?>>Розрахувати та зберегти <span>↗</span></button>
<?php if (!$cargo || !$routes): ?><p>Спочатку додайте тип вантажу й маршрут у довідниках.</p><?php endif ?></form></section>
<aside class="explanation"><span class="eyebrow">ПРОЗОРИЙ РОЗРАХУНОК</span><h2>Кожна складова<br>має значення</h2><div class="formula">Відстань × тариф × коефіцієнт<br><span>+ маршрутний збір</span></div><ol class="explain-list"><li><strong>Тип вантажу</strong><p>Визначає тариф за кілометр і мінімальну вартість замовлення.</p></li><li><strong>Обраний шлях</strong><p>Задає відстань, коефіцієнт складності та додатковий збір.</p></li><li><strong>Підсумкова вартість</strong><p>Округлюється до копійки й не може бути нижчою за мінімальну.</p></li></ol><p class="demo-note">Навчальні тарифи й відстані. Вартість наведено без ПДВ; побудова маршруту за картою не виконується.</p></aside></div>
<?php elseif ($page === 'quote' && $quote): ?>
<div class="page-heading"><div><p class="eyebrow">ЗБЕРЕЖЕНИЙ РОЗРАХУНОК № <?=str_pad((string)$quote['id'],4,'0',STR_PAD_LEFT)?></p><h1>Перевезення розраховано</h1><p class="muted"><?=e(local_time($quote['created_at']))?> · час Києва</p></div><a class="button" href="?page=history">Усі розрахунки</a></div>
<div class="result-grid"><section class="panel result"><span class="tag">Вартість без ПДВ</span><p class="price"><?=money($quote['total_cents'])?> <span>грн</span></p><h2><?=e($quote['cargo_name'])?></h2><p class="route-title"><?=e($quote['route_name'])?></p><dl class="breakdown"><div><dt>Відстань<?=$quote['distance_custom'] ? ' · власна' : ' · за маршрутом'?></dt><dd><?=decimal_string($quote['distance10'],1)?> км</dd></div><div><dt>Тариф вантажу</dt><dd><?=money($quote['rate_cents'])?> грн/км</dd></div><div><dt>Коефіцієнт маршруту</dt><dd><?=decimal_string($quote['factor_pct'],2)?></dd></div><div><dt>За відстань з коефіцієнтом</dt><dd><?=money($quote['transport_cents'])?> грн</dd></div><div><dt>Маршрутний збір</dt><dd><?=money($quote['fee_cents'])?> грн</dd></div><div><dt>Доплата до мінімуму</dt><dd><?=money($quote['minimum_extra_cents'])?> грн</dd></div><div class="total"><dt>Разом</dt><dd><?=money($quote['total_cents'])?> грн</dd></div></dl>
<?php if ($quote['note'] !== ''): ?><p class="muted">Примітка: <?=e($quote['note'])?></p><?php endif ?></section><aside class="panel aside-panel"><h2>Як отримано суму</h2><p><?=decimal_string($quote['distance10'],1)?> км × <?=money($quote['rate_cents'])?> грн/км × <?=decimal_string($quote['factor_pct'],2)?> = <strong><?=money($quote['transport_cents'])?> грн</strong></p><p>До суми додано збір <?=money($quote['fee_cents'])?> грн. Мінімальна вартість для цього вантажу — <?=money($quote['minimum_cents'])?> грн.</p><p>Тарифи та назви збережені разом із розрахунком. Редагування довідників не змінить цю суму.</p><a class="button primary wide" href="?page=calculator">Новий розрахунок ↗</a><button class="button wide" id="print-button" type="button">Друкувати розрахунок</button><div class="delete-area"><?php delete_form('quote',(int)$quote['id']); ?></div></aside></div>
<?php elseif ($page === 'history'): ?>
<div class="page-heading"><div><p class="eyebrow">ОБЛІК ПЕРЕВЕЗЕНЬ</p><h1>Історія розрахунків</h1><p class="muted">Збережені параметри, тарифи та підсумкові суми.</p></div><a href="?page=calculator" class="button primary">+ Новий розрахунок</a></div>
<section class="panel"><form method="get" class="filters"><input type="hidden" name="page" value="history"><label class="sr-only" for="q">Пошук</label><input id="q" name="q" maxlength="100" value="<?=e(query_text('q'))?>" placeholder="Вантаж, маршрут або примітка"><label class="sr-only" for="filter-cargo">Тип вантажу</label><select id="filter-cargo" name="cargo_id"><option value="">Усі типи вантажів</option><?php foreach ($cargo as $c): ?><option value="<?=$c['id']?>"<?=selected($_GET['cargo_id'] ?? '',$c['id'])?>><?=e($c['name'])?></option><?php endforeach ?></select><button class="button" type="submit">Знайти</button><a href="?page=history" class="text-button">Скинути</a></form>
<div class="table-scroll"><table><thead><tr><th>№ / Дата</th><th>Вантаж і маршрут</th><th>Відстань</th><th>Вартість</th><th>Дії</th></tr></thead><tbody>
<?php foreach ($quotes as $q): ?><tr><td><strong>#<?=str_pad((string)$q['id'],4,'0',STR_PAD_LEFT)?></strong><small><?=e(local_time($q['created_at']))?></small></td><td><strong><?=e($q['cargo_name'])?></strong><small><?=e($q['route_name'])?></small><?php if ($q['note']): ?><small><?=e($q['note'])?></small><?php endif ?></td><td><?=decimal_string($q['distance10'],1)?> км</td><td class="amount"><?=money($q['total_cents'])?> грн</td><td><a href="?page=quote&id=<?=$q['id']?>">Деталі ↗</a></td></tr><?php endforeach ?>
<?php if (!$quotes): ?><tr><td colspan="5" class="empty"><h2>Розрахунків не знайдено</h2><p>Змініть фільтри або створіть перший розрахунок.</p></td></tr><?php endif ?></tbody></table></div>
<div class="pagination"><span>Записів: <?=$count?> · Сторінка <?=$number?> з <?=$pages?> · час Києва</span><div><?php foreach ([-1=>'← Назад',1=>'Далі →'] as $delta=>$label): if ($number+$delta>=1 && $number+$delta<=$pages): ?><a class="button" href="?<?=e(http_build_query(['page'=>'history','q'=>$_GET['q']??'','cargo_id'=>$_GET['cargo_id']??'','p'=>$number+$delta]))?>"><?=$label?></a><?php endif; endforeach ?></div></div></section>
<?php elseif ($page === 'cargo' || $page === 'routes'): $isCargo = $page === 'cargo'; ?>
<div class="page-heading"><div><p class="eyebrow">ДОВІДНИКИ</p><h1><?=$isCargo ? 'Типи вантажів' : 'Маршрути перевезень'?></h1><p class="muted"><?=$isCargo ? 'Тарифи за кілометр і мінімальна вартість.' : 'Напрямки, відстані та умови проїзду.'?></p></div><a class="button primary" href="?page=<?=$isCargo ? 'cargo_edit' : 'route_edit'?>">+ <?=$isCargo ? 'Додати тип' : 'Додати маршрут'?></a></div>
<div class="cards"><?php foreach ($isCargo ? $cargo : $routes as $row): ?><article class="panel record-card"><span class="tag"><?=$isCargo ? 'Тип вантажу' : 'Маршрут'?> #<?=$row['id']?></span><h2><?=e($isCargo ? $row['name'] : $row['origin'].' → '.$row['destination'])?></h2><p class="muted"><?=e($isCargo ? $row['description'] : $row['via'])?></p><div class="record-metrics"><div><strong><?=$isCargo ? money($row['rate_cents']) : decimal_string($row['distance10'],1)?></strong><span><?=$isCargo ? 'грн / км' : 'км за маршрутом'?></span></div><div><strong><?=$isCargo ? money($row['minimum_cents']) : decimal_string($row['factor_pct'],2)?></strong><span><?=$isCargo ? 'грн мінімум' : 'коефіцієнт'?></span></div></div><?php if (!$isCargo): ?><p class="small muted">Маршрутний збір: <?=money($row['fee_cents'])?> грн</p><?php endif ?><div class="card-actions"><a href="?page=<?=$isCargo ? 'cargo_edit' : 'route_edit'?>&id=<?=$row['id']?>">Редагувати</a><?php delete_form($isCargo ? 'cargo' : 'route',(int)$row['id']); ?></div></article><?php endforeach ?>
<?php if (!($isCargo ? $cargo : $routes)): ?><section class="panel empty"><h2>Довідник порожній</h2><p>Додайте перший запис, щоб користуватися калькулятором.</p></section><?php endif ?></div><p class="muted small">Використані в історії записи захищені від видалення. Нові тарифи застосовуються до нових розрахунків.</p>
<?php elseif ($page === 'cargo_edit' || $page === 'route_edit'): $isCargo = $page === 'cargo_edit'; ?>
<div class="page-heading"><div><p class="eyebrow">ДОВІДНИКИ</p><h1><?=$item ? 'Редагування' : 'Новий запис'?> · <?=$isCargo ? 'тип вантажу' : 'маршрут'?></h1><p class="muted">Усі числові значення перевіряються на сервері.</p></div></div><section class="panel editor"><form method="post" class="form"><?=csrf()?><input type="hidden" name="action" value="<?=$isCargo ? 'save_cargo' : 'save_route'?>"><input type="hidden" name="id" value="<?=e($item['id'] ?? '')?>">
<?php if ($isCargo): ?>
<label for="name">Назва типу вантажу</label><input id="name" name="name" required minlength="2" maxlength="80" value="<?=old('name',$item['name']??'')?>">
<div class="form-row"><div><label for="rate">Тариф, грн/км</label><input id="rate" name="rate" type="number" min="0.01" max="1000" step="0.01" required value="<?=old('rate',isset($item['rate_cents']) ? decimal_string($item['rate_cents'],2) : '28.00')?>"></div><div><label for="minimum">Мінімальна вартість, грн</label><input id="minimum" name="minimum" type="number" min="0" max="100000" step="0.01" required value="<?=old('minimum',isset($item['minimum_cents']) ? decimal_string($item['minimum_cents'],2) : '1000.00')?>"></div></div>
<label for="description">Опис</label><textarea id="description" name="description" maxlength="200" rows="3"><?=old('description',$item['description']??'')?></textarea>
<?php else: ?>
<div class="form-row"><div><label for="origin">Звідки</label><input id="origin" name="origin" required minlength="2" maxlength="80" value="<?=old('origin',$item['origin']??'')?>"></div><div><label for="destination">Куди</label><input id="destination" name="destination" required minlength="2" maxlength="80" value="<?=old('destination',$item['destination']??'')?>"></div></div>
<label for="via">Шлях через населені пункти</label><input id="via" name="via" required minlength="2" maxlength="120" value="<?=old('via',$item['via']??'')?>">
<div class="form-row"><div><label for="distance">Відстань, км</label><input id="distance" name="distance" type="number" min="0.1" max="10000" step="0.1" required value="<?=old('distance',isset($item['distance10']) ? decimal_string($item['distance10'],1) : '')?>"></div><div><label for="factor">Коефіцієнт, 0,50–3,00</label><input id="factor" name="factor" type="number" min="0.5" max="3" step="0.01" required value="<?=old('factor',isset($item['factor_pct']) ? decimal_string($item['factor_pct'],2) : '1.00')?>"></div></div>
<label for="fee">Маршрутний збір, грн</label><input id="fee" name="fee" type="number" min="0" max="100000" step="0.01" required value="<?=old('fee',isset($item['fee_cents']) ? decimal_string($item['fee_cents'],2) : '0.00')?>">
<?php endif ?><div class="form-actions"><a class="button" href="?page=<?=$isCargo?'cargo':'routes'?>">Скасувати</a><button class="button primary" type="submit">Зберегти</button></div></form></section>
<?php else: ?><section class="panel empty"><p class="eyebrow">404</p><h1>Сторінку не знайдено</h1><a class="button primary" href="?page=calculator">До калькулятора</a></section><?php endif ?>
</main><footer class="footer"><span>CargoCalc · Практична робота № 5</span><span>Навчальна модель · суми у гривнях без ПДВ</span></footer></body></html>
