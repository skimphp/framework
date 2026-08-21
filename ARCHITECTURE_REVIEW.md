# SKIM Framework — архитектурный обзор

> Анализ выполнен прямым чтением исходного кода (не только метаданных AGENT.md).
> Все находки подтверждены конкретными file:line.

---

## Хорошие решения

### 1. query_gen `%placeholder%` система — `src/db/query_builder.php:19-77`
Неиспользуемые плейсхолдеры молча удаляются → динамические запросы без условного кода.
Порядок обработки фиксирован (set→values→where→order→group→limit→offset), что исключает
конфликты ключей между `%set%` и `%where%`. Значения идут через PDO `:named` параметры —
реальная защита от SQL-инъекций для данных.

### 2. Dirty tracking в Active Record — `src/db/model.php:348-419`
`__set` через `set_attribute()` фиксирует изменённые колонки; `do_update()` обновляет
только dirty-колонки. Частичный UPDATE — эффективно и безопасно. `null_marker`
(`src/db/null_marker.php`) как сентинел для явного `SET col = NULL` vs `null` = пропустить
колонку — элегантное различие.

### 3. Scoped store (sys/app/user) — `src/core/app.php:130-180`
Три области изоляции: `sys.*` (write-once в проде), `app.*` (config, fallback на
`config::get`), `user.*` (per-request). `sys.*` бросает `LogicException` при повторной
записи в проде — защита от случайной мутации внутренних состояний.

### 4. Freeze/mutation guard — `src/core/app.php:613`
Необратимая заморозка после boot. `bind()`, `decorate()`, `use()`, регистрация роутов —
все бросают после `freeze()`. Структурный запрет на мутацию конфигурации в рантайме.

### 5. Eager loading с IN-запросами — `src/db/merry_model.php:173-261`
`with()` с dot-notation, один IN-запрос на уровень вложенности → нет N+1. Параметры через
`?` placeholders, не интерполяция.

### 6. Schema caching — `src/db/model.php:229-254`
DESCRIBE/information_schema/PRAGMA → кэш на 1 час с ключом `schema:{table}:{connection}`.
Flush через `cache::flush('schema:')`. Дешёвая повторная загрузка.

### 7. Worker-mode lifecycle — `src/core/app.php` begin_request/end_request
`begin_request()`/`end_request()` разделены из `run()` — worker вызывает их per-request
без полного re-boot. `end_request()` чистит user scope + request-scoped DI bindings +
`worker_reset::apply()`. `make_transient()` (`app.php:443`) для контроллеров в worker-режиме
— без кэша синглтонов, без утечек состояния.

### 8. env без зависимости — `src/core/env.php:28-56`
~30 строк парсинга `.env`, приоритет `$_SERVER → $_ENV → cache → default`, без `putenv()`.
Compiled cache для OPcache. Минимализм оправдан.

### 9. Migrator с advisory locks — `src/db/migrator.php:394-410`
MySQL/PostgreSQL advisory locks предотвращают параллельный запуск миграций. Batch tracking
+ checksum drift detection (`verify_checksums()`).

### 10. AGENT.md per-module — контракт для каждого модуля
Структурированные метаданные (`#AI:` аннотации) — машиночитаемая документация для
LLM-агентов. Нетипично для PHP-фреймворков, но прагматично.

---

## Проблемы (текущие)

### Критические

#### C1. `emit_async()` ссылается на несуществующий класс — `src/events/event.php:138`
```php
\skim\queue\queue::push(new \skim\queue\internal\emit_event_job($payload, $data));
```
Каталога `src/queue/internal/` нет (glob подтвердил: только `base_job.php, job.php,
queue.php, worker.php`). Класс `emit_event_job` не существует → `RuntimeException` при
первом вызове `emit_async()`. Функция задокументирована как рабочая, но мертва.

**Решение:** создать `src/queue/internal/emit_event_job.php`, реализующий `job` интерфейс
— `handle()` вызывает `event::emit()`, `failed()` логирует. Или удалить `emit_async()` и
документировать как unimplemented.

#### C2. SQL-инъекция в `order_by`/`group_by` — `src/db/query_builder.php:55-62`
```php
$sql = str_replace('%order_by%', 'ORDER BY ' . $params['order_by'], $sql);
$sql = str_replace('%group_by%', 'GROUP BY ' . $params['group_by'], $sql);
```
Значения интерполируются напрямую в SQL без валидации идентификаторов. Если `order_by`
приходит из пользовательского ввода (например, `?sort=name;DROP TABLE users`), это инъекция.
`find_by()` валидирует колонки regex'ом (`model.php:62`), но `order_by`/`group_by` — нет.

**Решение:** валидировать через whitelist или regex
`^[a-zA-Z_][a-zA-Z0-9_]*( (ASC|DESC))?$` перед интерполяцией. Или принимать массив
`['col' => 'ASC']` и собирать безопасно.

#### C3. Session fixation — cookie без подписи — `src/session/redis_session_driver.php:45`
```php
$this->id = $_COOKIE['PHPSESSID'] ?? $this->generate_id();
```
Принимается любой `PHPSESSID` из cookie без HMAC-подписи. Атакующий может установить
известный ID (fixation), затем использовать его после логина. `regenerate()` существует,
но должен вызываться вручную — нет автоматической защиты. `file_session_driver` полагается
на нативную PHP session, что несколько безопаснее, но Redis-драйвер полностью ручной.

**Решение:** подписывать cookie HMAC-SHA256 с `APP_KEY`: `cookie = id.hmac`. При чтении
проверять подпись, отбраковывать неподписанные. Принудительный `regenerate()` после
аутентификации — задокументировать как обязательный, либо встроить в middleware.

#### C4. Нет CSRF-защиты — отсутствует класс
`grep` по всему `src/` нашёл только `'csrf' => 'CSRF protection'` в
`capability_vocabulary.php:24` (словарь возможностей расширений). Ни `csrf_token()`, ни
`csrf_field()`, ни middleware не существует. Для фреймворка с сессиями и формами это дыра
— любой POST без external защиты уязвим.

**Решение:** `csrf_middleware` — проверяет `_token` на POST/PUT/PATCH/DELETE, токен в
сессии, `csrf_token()`/`csrf_field()` хелперы для шаблонов. ~80 строк.

### Высокие

#### H1. `@unserialize()` без валидации — object injection
- `src/cache/redis_driver.php:47`
- `src/cache/file_driver.php:49`

`@unserialize()` на данных из Redis/файла без `allowed_classes`. Если кэш tampered (доступ
к Redis или файловой системе), атакующий может инстанцировать произвольные классы с
`__wakeup`/`__destruct` → RCE в худшем случае.

**Решение:** `unserialize($data, ['allowed_classes' => false])` или явно перечислить
разрешённые. Лучше — `json_encode`/`json_decode` вместо serialize для кэша (медленнее, но
безопаснее).

#### H2. `ip()` доверяет `X-Forwarded-For` без trusted proxy — `src/core/request.php:172-181`
```php
$forwarded = $this->header('X-Forwarded-For');
if ($forwarded !== null) {
    return trim(explode(',', $forwarded)[0]);
}
```
Заголовок клиентский → rate limiter (`rate_limit.php:33` использует `$req->ip()`)
обходится подделкой заголовка. Документировано ("do not use as a security boundary"), но
rate limiter именно security boundary.

**Решение:** конфиг `app.trusted_proxies` — `X-Forwarded-For` учитывается только если
`REMOTE_ADDR` в списке. Иначе — `REMOTE_ADDR`.

#### H3. `rate_limit` создаёт Redis-соединение per request — `src/middleware/rate_limit.php:68-77`
`$this->redis()` делает `new \Redis()` + `connect()` на каждый запрос. Под нагрузкой —
исчерпание соединений, latency. Не использует пул `db::pdo()`.

**Решение:** статический кэш `Redis` экземпляра с проверкой liveness, как в `db.php`. Или
инжекция через DI.

#### H4. Unknown validation rules молча проходят — `src/validation/validate.php:162`
```php
if (!isset($this->custom_rules[$rule])) {
    return null;   // unknown rules silently pass — prevents accidental lockouts
}
```
Опечатка `'emial'` вместо `'email'` → правило проходит, поле невалидируется. Security
vulnerability: невалидированные данные попадают в БД. Комментарий объясняет intent
(anti-lockout), но цена — тихая дыра.

**Решение:** в debug-режиме бросать `InvalidArgumentException` на unknown rule. В проде —
логировать warning. Не молчать.

#### H5. `handle_exception()` в проде возвращает bare string — `src/core/app.php:712-718`
500-я ошибка в production → `'Internal Server Error'` без JSON/content-type. API-клиенты
получат text/plain вместо ожидаемого JSON. Несовместимо с `is_json()` запросами.

**Решение:** проверять `Accept: application/json` → `{"error":"Internal Server Error"}` с
proper content-type. HTML → минимальная HTML-страница.

### Средние

#### M1. God-object `app` (1367 строк) — `src/core/app.php`
Контейнер + kernel + store + router + pipeline + extension context в одном классе. 20+
приватных свойств. Документировано как intentional ("keeps framework surface area small"),
но: тестирование изолированных подсистем затруднено, изменение DI затрагивает kernel, SRP
нарушен. `dispatch()` временно подменяет `self::$instance` (`app.php:1217`) — хрупко.

**Решение сейчас:** не рефакторить (работает), но остановить рост. Новые подсистемы —
отдельные классы, инжектируемые через DI. Замерить: если `app.php` превысит 2000 строк —
разделить container/kernel.

#### M2. `pipeline::$instance_cache` — process-global static — `src/core/pipeline.php:18`
Статический кэш middleware-инстансов разделяется между запросами в worker-режиме.
`reset_instance_cache()` должен вызываться вручную. Если забыть — middleware с состоянием
утекает между запросами.

**Решение:** инжектировать через `app::make()` вместо статического кэша. Или автоматически
сбрасывать в `end_request()`.

#### M3. `detach()` с пустым массивом удаляет все pivot-записи — `src/db/merry_model.php:94-117`
`detach($relation)` без `$ids` → `DELETE FROM pivot WHERE fk = :fk` — удаляет все связи.
Документировано WARNING, но легко вызвать случайно:
`detach('tags', $request->input('tags') ?? [])` — если `tags` отсутствует, пустой массив =
wipe.

**Решение:** требовать явный флаг `detach($relation, $ids, $all = false)` или отдельный
метод `detach_all()`.

#### M4. `remember()` — TOCTOU между `has()` и `get()` — `src/cache/cache.php:30-38`
```php
if (self::has($key)) { return self::get($key); }
$value = $default(); self::set($key, $value, $ttl);
```
Два round-trip к Redis. Между `has()` и `set()` ключ может истечь → `get()` вернёт default,
а `default()` уже выполнен. Не atomic.

**Решение:** Redis: `GET` → если miss, вычислить → `SET` с NX. Или один `GET`, проверка на
`null`/default.

#### M5. `interpolate()` использует `addslashes` — `src/db/query_builder.php:168`
```php
default => "'" . addslashes((string) $val) . "'",
```
Для debug-вывода приемлемо (не исполняется), но `addslashes` не экранирует правильно для
SQL. Если кто-то случайно выполнит debug-вывод — инъекция. Предупреждение в коде есть, но
риск человеческой ошибки.

**Решение:** добавить watermark `/* DEBUG — DO NOT EXECUTE */` в начало вывода. Или
отдельный класс `debug_sql` с `__toString()`, который бросает при попытке выполнить.

#### M6. Cache fallback без логирования — `src/cache/cache.php:165-175`
При падении Redis → молча переключается на file. В проде это означает: кэш работает, но
медленнее, и никто не знает. Деградация невидима.

**Решение:** `log::warning('Cache primary driver failed, falling back', ['driver' => $name,
'error' => $e])`.

#### M7. SSE без timeout — `src/realtime/sse.php`
Соединение может висеть бесконечно. `connection_aborted()` не детектирует silent TCP drops.
Нет `Last-Event-ID` для resume.

**Решение:** heartbeat ping каждые N секунд, max execution time, `Last-Event-ID` header
support.

### Низкие

#### L1. `config::set()` может мутировать замороженный конфиг — `src/core/config.php:89-107`
`set()` помечен "testing only", но не проверяет `frozen`. В проде случайно вызванный —
мутация конфига.

#### L2. `route_entry::name()` молча перезаписывает дубликаты — `src/core/route_entry.php:42`
Два роута с одним `name()` — последний выигрывает, `route()` helper указывает на него.
Тихая ошибка маршрутизации.

**Решение:** бросать при дубликате в debug.

#### L3. `env::load()` idempotent — второй путь игнорируется — `src/core/env.php:31-32`
`load('/custom/.env')` после `load('.env')` — no-op. Документировано, но неочевидно для
пользователя.

#### L4. `leak_detector` требует строгий monotonic growth — `src/worker/leak_detector.php:109-127`
Плато в потреблении памяти после warmup → false positive. Warmup 10 запросов может быть
недостаточен.

---

## Потенциальные проблемы в будущем и решения сейчас

### F1. Рост `app.php` → неподдерживаемость
Сейчас 1367 строк, god-object. Каждая новая фича (новый lifetime, новый scope, новый hook)
добавляет методы. Через год — 3000 строк, невозможно тестировать изолированно.

**Сейчас:** ввести правило — новые подсистемы как отдельные классы с DI-инжекцией. `app`
делегирует, не реализует. Контейнер, kernel, store — кандидаты на разделение, но не срочно.

### F2. Статические фасады (`db`, `cache`, `session`, `event`, `log`) → негибкость
Все подсистемы — static facades. Это удобно для micro-framework, но: невозможен per-request
override, mock injection только через `set_driver()`, глобальное состояние в worker-режиме
требует ручного `reset()`. При росте до mid-size приложения — боль.

**Сейчас:** сохранить фасады как primary API, но убедиться что каждый фасад делегирует
инстансу, который можно получить через `app::make()`. `cache::set_driver()` — правильный
паттерн, распространить на все. Не ломать API.

### F3. Нет CSRF + слабая session security → невозможно использовать в проде без доп. работы
Фреймворк позиционируется как production-ready (Docker, OPcache, worker mode), но базовая
web-безопасность отсутствует. Первый реальный пользователь упрётся в это.

**Сейчас:** реализовать CSRF middleware + signed session cookies (C3, C4). ~150 строк
суммарно. Это блокер для adoption.

### F4. `serialize()` в queue/cache → проблемы при эволюции классов
`queue::serialize_job()` (`queue.php:127`) и cache drivers используют PHP `serialize`. При
переименовании класса, удалении свойства или изменении типа — `unserialize()` падает или
даёт некорректные объекты. В очереди могут лежать старые версии job'ов после деплоя.

**Сейчас:** для queue — JSON-сериализация с `class` полем + `unserialize` через
`new $class($data)`. Для cache — `allowed_classes => false` (минимум) или JSON.
Документировать: job классы должны быть immutable value objects.

### F5. Нет dead-letter queue — `src/queue/worker.php:107-115`
После исчерпания retry job теряется. Backoff линейный (`attempts * 5`), не экспоненциальный.
Job не знает свой attempt count → не может реализовать per-attempt behavior.

**Сейчас:** добавить `failed_jobs` Redis list, `queue:failed` команда для просмотра,
`queue:retry <id>` для повторного запуска. Exponential backoff:
`min(2^attempts * base, max)`. Передавать attempt count в `job::handle()`.

### F6. Extension priority без валидации диапазона — `src/ext/extension_priority.php`
Константы `CORE=10, OFFICIAL=50, PAID=80, USER=100`, но расширения могут объявить любой int.
Конфликты приоритетов не детектируются структурно.

**Сейчас:** валидировать в `extension_manager::register()` — бросать если priority вне
диапазона или если два расширения с одинаковым priority претендуют на одну capability.

### F7. `json()` в request молча возвращает `[]` при ошибке — `src/core/request.php:93-104`
Malformed JSON → пустой массив вместо ошибки. Контроллер не отличит "пустой JSON" от "битый
JSON". API-клиент получает 200 с пустым ответом вместо 400.

**Сейчас:** добавить `json(strict: true)` опцию или `json_or_throw()`. Минимум — логировать
parse error в debug.

### F8. Нет content negotiation в error responses
`handle_exception()` (H5), `dispatch()` 404/405 — все возвращают text или JSON без учёта
`Accept`. При добавлении API-роутов — несогласованные форматы ошибок.

**Сейчас:** centralized `error_response(int $status, string $message, request $req):
response` — проверяет `is_json()`, возвращает соответствующий формат. Использовать во всех
error paths.

---

## Резюме приоритетов

| Приоритет | Проблема | Усилие |
|-----------|---------|--------|
| Блокер | C4: CSRF middleware | ~80 строк |
| Блокер | C3: Signed session cookies | ~60 строк |
| Блокер | C1: `emit_event_job` класс | ~30 строк |
| Высокий | C2: `order_by`/`group_by` валидация | ~20 строк |
| Высокий | H1: `allowed_classes` в unserialize | ~5 строк |
| Высокий | H2: Trusted proxy для `ip()` | ~15 строк |
| Высокий | H4: Unknown validation rule → throw в debug | ~5 строк |
| Средний | H3: Redis connection pool для rate_limit | ~30 строк |
| Средний | M6: Логировать cache fallback | ~3 строки |

---

## Общий вывод

Фреймворк архитектурно зрелый для micro-framework: scoped store, freeze model, query_gen,
dirty tracking, eager loading, worker lifecycle — продуманные решения. Основной долг —
web-безопасность (CSRF, session signing, SQL injection в order_by) и мёртвый код
(`emit_async`). Это не архитектурные проблемы, а недоработки реализации, которые
закрываются точечно без рефакторинга.
