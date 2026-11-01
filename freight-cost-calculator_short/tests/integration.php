<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/repository.php';
$checks = 0; $cargoId = null; $routeId = null; $quoteId = null;
function check(bool $condition, string $label): void {
    global $checks; $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo "PASS: $label\n";
}
$suffix = bin2hex(random_bytes(5));
try {
    $cargoData = ['name'=>'TEST-'.$suffix,'rate'=>'28.00','minimum'=>'1000','description'=>'<b>Тест</b>'];
    $cargoId = save_cargo($cargoData);
    check($cargoId > 0, 'INSERT типу вантажу');
    $cargoData['id'] = (string)$cargoId; $cargoData['rate'] = '36.00';
    save_cargo($cargoData);
    check((int)run('SELECT rate_cents FROM cargo_types WHERE id=?',[$cargoId])->fetchColumn() === 3600, 'UPDATE тарифу');
    $routeData = ['origin'=>'Тест '.$suffix,'destination'=>'Київ','via'=>'Тестовий шлях','distance'=>'475','factor'=>'1.05','fee'=>'200'];
    $routeId = save_route($routeData);
    check($routeId > 0, 'INSERT маршруту');
    $routeData['id'] = (string)$routeId; $routeData['via'] = 'Оновлений шлях'; save_route($routeData);
    check(run('SELECT via FROM routes WHERE id=?',[$routeId])->fetchColumn() === 'Оновлений шлях', 'UPDATE маршруту');
    $quoteData = ['cargo_id'=>(string)$cargoId,'route_id'=>(string)$routeId,'note'=>'Тест історії','custom_distance'=>''];
    $quoteId = create_quote($quoteData);
    $q = run('SELECT * FROM quotes WHERE id=?',[$quoteId])->fetch();
    check((int)$q['total_cents'] === 1815500, 'Збереження результату 18155 грн');
    $cargoData['rate'] = '99.00'; save_cargo($cargoData);
    check((int)run('SELECT rate_cents FROM quotes WHERE id=?',[$quoteId])->fetchColumn() === 3600, 'Знімок тарифу не змінюється');
    $routeData['distance'] = '100'; save_route($routeData);
    check((int)run('SELECT distance10 FROM quotes WHERE id=?',[$quoteId])->fetchColumn() === 4750, 'Знімок відстані не змінюється');
    foreach (['cargo'=>$cargoId,'route'=>$routeId] as $kind=>$id) {
        try { delete_record($kind,$id); check(false,'Захист FK '.$kind); }
        catch (PDOException $ex) { check($ex->getCode()==='23000','Захист FK '.$kind); }
    }
    try { save_cargo($cargoData + []); check(true,'Повторний UPDATE'); } catch (Throwable) { check(false,'Повторний UPDATE'); }
    try { save_cargo(array_diff_key($cargoData,['id'=>1])); check(false,'Унікальність назви'); }
    catch (PDOException $ex) { check($ex->getCode()==='23000','Унікальність назви'); }
    $before = (int)run('SELECT COUNT(*) FROM quotes')->fetchColumn();
    try { create_quote([...$quoteData,'custom_distance'=>'-1']); check(false,'Некоректна відстань'); }
    catch (DomainException) { check((int)run('SELECT COUNT(*) FROM quotes')->fetchColumn()===$before && !db()->inTransaction(),'Відкат невдалого розрахунку'); }
    check(e('<script>') === '&lt;script&gt;', 'HTML екранування');
    delete_record('quote',$quoteId); $quoteId = null;
    check(!run('SELECT id FROM quotes WHERE id=?',[$q['id']])->fetch(),'DELETE розрахунку');
    delete_record('route',$routeId); $routeId = null;
    delete_record('cargo',$cargoId); $cargoId = null;
    check(true,'DELETE вільних довідників');
    echo "OK: $checks перевірок\n";
} finally {
    if (db()->inTransaction()) db()->rollBack();
    if ($quoteId) run('DELETE FROM quotes WHERE id=?',[$quoteId]);
    if ($routeId) run('DELETE FROM routes WHERE id=?',[$routeId]);
    if ($cargoId) run('DELETE FROM cargo_types WHERE id=?',[$cargoId]);
}
