"""HTTP checks. Python 3 standard library only; created records are removed."""
import http.cookiejar
import os
import re
import secrets
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get('APP_URL', 'http://127.0.0.1:8086')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
checks = 0

def request(path='', data=None):
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        response = client.open(BASE + '/' + path, payload, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.read().decode(), response.headers

def check(condition, label):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(label)
    print('PASS:', label)

status, html, _ = request()
check(status == 200 and 'CargoCalc' in html, 'Головна сторінка')
csrf = re.search(r'name="csrf" value="([a-f0-9]+)"', html).group(1)
suffix = 'HTTP-' + secrets.token_hex(5)
cargo_id = route_id = quote_id = None

def post(page, data):
    return request('?page=' + page, {'csrf': csrf, **data})

try:
    for page in ['history', 'cargo', 'routes', 'cargo_edit', 'route_edit']:
        check(request('?page=' + page)[0] == 200, 'GET ' + page)
    check(request('?page=unknown')[0] == 404, 'Невідома сторінка: 404')
    check(request('?page=quote&id=99999999')[0] == 404, 'Відсутній розрахунок: 404')
    check(request('', {'action': 'create_quote'})[0] == 403, 'POST без CSRF: 403')
    check(request('?page=history&q%5B%5D=x')[0] == 422, 'Некоректний масив у GET')
    cargo = dict(action='save_cargo', id='', name=suffix, rate='36', minimum='1500', description='<script>alert(1)</script>')
    check(post('cargo_edit', cargo)[0] == 303, 'Додавання типу: 303')
    _, html, _ = request('?page=cargo')
    card = next(x for x in re.findall(r'<article.*?</article>', html, re.S) if suffix in x)
    cargo_id = re.search(r'cargo_edit&id=(\d+)', card).group(1)
    check('&lt;script&gt;alert(1)&lt;/script&gt;' in card and '<script>alert(1)</script>' not in card, 'HTML введення екрановано')
    check(post('cargo_edit', cargo)[0] == 422, 'Дублікат назви: 422')
    check(post('cargo_edit', {**cargo, 'name': suffix + 'bad', 'rate': '-2'})[0] == 422, 'Від’ємний тариф: 422')
    check(post('cargo_edit', {**cargo, 'id': cargo_id, 'description': 'Оновлено'})[0] == 303, 'Редагування типу')
    route = dict(action='save_route', id='', origin=suffix, destination='Одеса', via='Тестовий шлях', distance='475', factor='1.05', fee='200')
    check(post('route_edit', route)[0] == 303, 'Додавання маршруту')
    _, html, _ = request('?page=routes')
    card = next(x for x in re.findall(r'<article.*?</article>', html, re.S) if suffix in x)
    route_id = re.search(r'route_edit&id=(\d+)', card).group(1)
    check(post('route_edit', {**route, 'id': route_id, 'via': 'Оновлено'})[0] == 303, 'Редагування маршруту')
    calc = dict(action='create_quote', cargo_id=cargo_id, route_id=route_id, custom_distance='', note=suffix)
    for invalid in ['0', '-1', '10000.1', '1.25', '1e3']:
        check(post('calculator', {**calc, 'custom_distance': invalid})[0] == 422, 'Відхилення відстані ' + invalid)
    check(post('calculator', {**calc, 'cargo_id': '99999999'})[0] == 422, 'Відсутній тип вантажу')
    status, _, headers = post('calculator', calc)
    check(status == 303 and 'page=quote' in headers['Location'], 'Розрахунок і перенаправлення')
    quote_id = re.search(r'id=(\d+)', headers['Location']).group(1)
    _, html, _ = request(headers['Location'])
    check('18 155,00' in html and '475.0' in html, 'Серверна сума 18155 грн')
    check(request(headers['Location'])[0] == 200, 'Оновлення результату без повторного POST')
    check(suffix in request('?page=history&q=' + suffix)[1], 'Пошук історії')
    check(suffix in request('?page=history&cargo_id=' + cargo_id)[1], 'Фільтр за типом вантажу')
    check(post('cargo', dict(action='delete',kind='cargo',id=cargo_id))[0] == 422, 'Захист використаного типу')
    check(post('routes', dict(action='delete',kind='route',id=route_id))[0] == 422, 'Захист використаного маршруту')
    check(post('history', dict(action='delete',kind='quote',id=quote_id))[0] == 303, 'Видалення розрахунку')
    quote_id = None
    check(post('routes', dict(action='delete',kind='route',id=route_id))[0] == 303, 'Видалення вільного маршруту')
    route_id = None
    check(post('cargo', dict(action='delete',kind='cargo',id=cargo_id))[0] == 303, 'Видалення вільного типу')
    cargo_id = None
    print(f'OK: {checks} перевірок')
finally:
    for kind, record_id in [('quote', quote_id), ('route', route_id), ('cargo', cargo_id)]:
        if record_id:
            post('history', dict(action='delete',kind=kind,id=record_id))
