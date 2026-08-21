# SKIM: архитектурный аудит и стратегия горизонтального масштабирования

> Проверено по текущему working tree 21 августа 2026 года. Исходные документы: [`ARCHITECTURE_REVIEW.md`](ARCHITECTURE_REVIEW.md) и [`k8s_arhitecture.md`](k8s_arhitecture.md).

## Краткий вывод

Оба исходных аудита полезны как чек-листы, но смешивают четыре разных класса замечаний:

1. реальные дефекты реализации;
2. ограничения отдельных функций;
3. production-hardening контейнера и starter-проекта;
4. необязательные enterprise-требования.

HTTP-часть SKIM в целом подходит для горизонтального масштабирования: конфигурация читается из environment, сессии и cache ориентированы на Redis, request state очищается между запросами FrankenPHP worker mode, незавершённые DB-транзакции откатываются.

Однако текущий репозиторий пока нельзя честно называть production-deploy-ready. Основные причины — не отсутствие Helm, HPA или PodDisruptionBudget, а более приземлённые проблемы:

- официальный FrankenPHP image не собирается из текущего repository context;
- cache fallback заявлен, но фактически не срабатывает при недоступном Redis;
- очередь теряет job при падении worker и может дублировать delayed jobs;
- Redis session driver допускает adoption неизвестного session ID и не имеет locking;
- контейнерные логи по умолчанию пишутся в локальный файл;
- compiled config несовместим с generic image, в который runtime environment передаётся после build;
- WebSocket rooms работают только внутри одного PHP-процесса.

Для микрофреймворка не нужен Kubernetes-first подход. Практичная лестница выглядит так:

1. Railway template для one-click deployment;
2. один Hetzner VPS с Coolify или Dokploy;
3. два stateless app-сервера за Hetzner Load Balancer;
4. k3s только при появлении реальной потребности в Kubernetes orchestration.

---

# 1. Разбор `ARCHITECTURE_REVIEW.md`

## 1.1 Реальные проблемы

### C1. `emit_async()` вызывает отсутствующий класс

[`src/events/event.php:134-139`](src/events/event.php#L134-L139) создаёт `\skim\queue\internal\emit_event_job`, которого в репозитории нет.

Проверка `class_exists(\skim\queue\queue::class)` не защищает этот путь: сам класс `queue` входит в framework и существует. После проверки произойдёт `Error: Class not found`, а не документированный `RuntimeException`.

**Вердикт:** реальный функциональный баг.

**Что делать:** либо добавить внутренний job с тестом полного `emit_async() → queue → worker → emit()` flow, либо удалить публичный метод до появления реализации.

---

### C3. Session fixation возможна, но причина описана неточно

[`src/session/redis_session_driver.php:40-64`](src/session/redis_session_driver.php#L40-L64) принимает любой `PHPSESSID` из cookie, даже если такого session ID ещё нет в Redis. Если auth-код после login не вызовет `session::regenerate()`, authenticated state может быть записан под заранее навязанным ID.

Проблема не сводится к отсутствию HMAC-подписи. Непрозрачные server-side session IDs обычно не обязаны быть подписаны. Основные меры защиты:

- отклонять неизвестные и неинициализированные ID — аналог PHP `session.use_strict_mode`;
- валидировать формат и длину ID;
- менять ID после login, privilege change и logout;
- использовать HSTS и cookie с `__Host-` prefix;
- явно конфигурировать secure cookie при TLS termination на proxy.

[`src/session/session.php:121-129`](src/session/session.php#L121-L129) уже предоставляет `session::regenerate()`, но framework не может гарантировать, что authentication extension вызывает его в нужный момент.

**Вердикт:** риск реальный; HMAC из исходного аудита — лишь дополнительная защита, а не основное исправление.

#### Дополнительная проблема: lost updates

Redis driver хранит сессию одним JSON blob и не использует lock или optimistic version. Два параллельных запроса могут прочитать одинаковое состояние, изменить разные поля, после чего последний `SETEX` затрёт изменения первого.

Для простых flash/user-id sessions это может встречаться редко, но вероятность растёт при HTMX/Datastar, когда браузер отправляет несколько параллельных запросов.

---

### C4. CSRF отсутствует

В framework нет CSRF middleware и helpers. Для HTML-приложения с cookie-based authentication это реальный пробел безопасности.

Но отсутствие CSRF нельзя считать абсолютным production-блокером для всех проектов:

- stateless API с Bearer token не требует CSRF;
- backend service и webhook consumer не требуют CSRF;
- cookie-authenticated формы требуют CSRF.

**Лучший формат для микрофреймворка:** небольшой официальный CSRF module/middleware, включённый по умолчанию в web starter, но не обязательный для API-only applications.

---

### H2. `request::ip()` безусловно доверяет `X-Forwarded-For`

[`src/core/request.php:167-180`](src/core/request.php#L167-L180) всегда берёт первый IP из `X-Forwarded-For`. [`src/middleware/rate_limit.php:41-48`](src/middleware/rate_limit.php#L41-L48) использует это значение как ключ rate limit.

Если приложение доступно напрямую, клиент может менять XFF и обходить ограничение. На PaaS риск меньше, поскольку контейнер обычно доступен только через platform proxy, но framework не должен полагаться на это неявно.

**Вердикт:** реальная security boundary ошибка.

**Что делать:** добавить `trusted_proxies`; учитывать forwarded headers только когда `REMOTE_ADDR` принадлежит доверенному proxy. На Hetzner прямой доступ к app nodes должен дополнительно закрываться firewall.

---

### H4. Неизвестные validation rules молча проходят

[`src/validation/validate.php:182-185`](src/validation/validate.php#L182-L185) считает неизвестное правило успешным. Опечатка `emial` вместо `email` полностью отключит проверку поля.

Комментарий `prevents accidental lockouts` не оправдывает поведение: ошибка разработчика не должна молча ослаблять validation contract.

**Вердикт:** реальная API-ошибка, но не архитектурный кризис.

**Что делать:** бросать `InvalidArgumentException` всегда. Debug-only exception недостаточен: production не должен вести себя менее безопасно, чем development.

---

### M4. `cache::remember()` имеет race между `has()` и `get()`

[`src/cache/cache.php:41-50`](src/cache/cache.php#L41-L50) выполняет два backend reads. Если ключ истечёт после `has()` и до `get()`, callback не будет вызван: метод вернёт cache default, нарушив собственный контракт.

Исходный аудит описывает race неточно: callback в этом сценарии не успевает выполниться.

**Минимальное исправление:** один `get()` с уникальным sentinel.

Distributed lock нужен только если измерения покажут дорогой cache stampede; для обычного cache это не P0.

---

## 1.2 Условные риски и design trade-offs

### C2. Raw `order_by` и `group_by`

[`src/db/query_builder.php:56-63`](src/db/query_builder.php#L56-L63) вставляет эти значения как raw SQL fragments. Если application code передаст туда query parameter без whitelist, возможна SQL injection.

Однако это не удалённо эксплуатируемая уязвимость framework «из коробки». Structural SQL невозможно безопасно передать через PDO value placeholder. Аналогичные raw fragments уже принимаются в `%where%`, а имена колонок из `%set%` и `%values%` также формируют SQL structure.

Предложенный в исходном аудите regex слишком узкий: он сломает `users.created_at`, aliases, несколько колонок и SQL functions.

**Практичное правило:** пользователь выбирает только ключ из application whitelist:

```php
$sort = [
    'new'  => 'created_at DESC',
    'name' => 'name ASC',
][$req->get('sort')] ?? 'id DESC';
```

Дополнительно можно предоставить typed identifier/order helper, не запрещая raw SQL для разработчика.

---

### H1. `unserialize()` в cache

[`src/cache/redis_driver.php:40-47`](src/cache/redis_driver.php#L40-L47) и [`src/cache/file_driver.php:38-55`](src/cache/file_driver.php#L38-L55) разрешают восстановление любых классов.

Object injection возможна, если атакующий уже умеет писать в Redis или cache directory. Это серьёзный defense-in-depth риск, но не автоматическая critical RCE для любого приложения.

Для cache разумно хранить primitives/arrays и использовать `allowed_classes => false`. Если object caching считается частью API, он должен быть отдельным явным режимом с whitelist.

Для queue это решение напрямую не подходит: job обязан восстановиться как объект.

---

### H3. Rate limiter создаёт Redis connection на каждый запрос

[`src/middleware/rate_limit.php:72-84`](src/middleware/rate_limit.php#L72-L84) каждый раз вызывает `new Redis()` и `connect()`.

В PHP-FPM это обычная цена shared-nothing request lifecycle. В FrankenPHP worker mode это ненужный connect/disconnect и дополнительная нагрузка на Redis.

**Вердикт:** performance concern среднего приоритета, не security blocker.

---

### H5/F8. Production error response не учитывает `Accept`

[`src/core/app.php:709-719`](src/core/app.php#L709-L719) отдаёт bare `Internal Server Error`. Это неудобно для API, но не является эксплуатируемой уязвимостью.

Нужен единый error response formatter, который выбирает JSON или HTML. Это API consistency, а не проблема горизонтального масштабирования.

---

### M1/F1. `app.php` как god object

Большой central kernel — допустимый trade-off микрофреймворка. Разделять его только ради формального SRP сейчас не нужно.

Разумное ограничение: новые независимые подсистемы реализуются отдельными классами, а `app` только координирует их. Порог «разделить после 2000 строк» не имеет технического основания.

---

### M3. `detach()` с пустым массивом удаляет все связи

[`src/db/merry_model.php:108-137`](src/db/merry_model.php#L108-L137) явно документирует это поведение.

API опасен при использовании `$request->input('tags') ?? []`, но не сломан. Отдельный `detach_all()` был бы безопаснее, однако это не production blocker.

---

### M7. SSE не управляет timeout и replay

[`src/realtime/sse.php`](src/realtime/sse.php) уже умеет отправлять event ID и heartbeat через `ping()`.

Длительность stream, частота heartbeat, хранение событий и обработка `Last-Event-ID` зависят от приложения. Не стоит превращать маленький SSE formatter в event broker.

---

## 1.3 Надуманные, низкоприоритетные или уже исправленные пункты

- **M2, pipeline state:** [`worker_reset::apply()`](src/worker/worker_reset.php#L39-L55) автоматически вызывает `pipeline::reset_instance_cache()`.
- **M5, `addslashes` в debug SQL:** метод явно помечен как non-executable debug output. Это не SQL injection path.
- **L1, `config::set()`:** testing seam; он не связан с `app::freeze()`.
- **L2, duplicate route names:** debug warning полезен, но last-write-wins документирован.
- **L3, idempotent env loading:** нормальный lifecycle contract.
- **L4, leak detector:** plateau прекращает detection. Возможен false negative, но не заявленный false positive. См. [`src/worker/leak_detector.php:106-126`](src/worker/leak_detector.php#L106-L126).
- **F2, static facades:** нормальный API микрофреймворка. Важно отделять process state от request state, а не запрещать static API.
- **F6, extension priority range:** произвольный integer не создаёт vulnerability; одинаковый priority может быть допустим.
- **F7, malformed JSON → `[]`:** спорный API contract. Для строгих API полезен `json_or_throw()`, но текущая логика явно документирована и не является архитектурным дефектом.

---

# 2. Проблемы, пропущенные `ARCHITECTURE_REVIEW.md`

## 2.1 Cache fallback фактически не работает

[`cache::resolve_driver()`](src/cache/cache.php#L196-L213) ловит исключение только при создании driver. Конструктор [`redis_driver`](src/cache/redis_driver.php#L24-L30) не подключается к Redis; соединение открывается лениво при первой операции в [`redis_driver::redis()`](src/cache/redis_driver.php#L141-L158).

Поэтому реальная недоступность Redis проявляется уже после выхода из `resolve_driver()`, и file fallback не включается.

Кроме того, file fallback в multi-replica deployment вреден: каждый pod получит собственный несогласованный cache.

**Вывод:** заявления README и class docs о fail-soft Redis → file сейчас неверны. Нужна явная per-operation failure policy, а не тихое переключение на локальный диск.

---

## 2.2 Очередь не имеет production-reliable delivery semantics

Текущая очередь проста, но её нельзя приравнивать к надёжной distributed queue.

### Потеря job при падении worker

[`worker::work()`](src/queue/worker.php#L42-L68) получает job через `BRPOP`. Redis удаляет элемент до выполнения `handle()`. Если процесс, node или container умрёт после `BRPOP`, job потеряется.

### Дублирование delayed jobs

[`queue::promote_delayed()`](src/queue/queue.php#L66-L89) выполняет `ZRANGEBYSCORE → LPUSH → ZREM` отдельными командами. Несколько worker'ов могут одновременно увидеть один delayed payload и положить его в queue несколько раз.

### Нет DLQ

После исчерпания retries вызывается `job::failed()`, но исходный payload не сохраняется для просмотра или повторного запуска.

### Backoff назван неверно

[`src/queue/worker.php:104-110`](src/queue/worker.php#L104-L110) использует `attempts * 5`: 5, 10, 15 секунд. Это линейный, а не exponential backoff.

### Rolling deploy compatibility

Job хранится через PHP `serialize()`. Новая версия приложения должна уметь десериализовать job, созданный старой версией. Rename class или несовместимое изменение typed properties ломает очередь.

**Минимальный production путь:** Redis Streams consumer groups либо reserve/ack processing-list через `BRPOPLPUSH`, recovery зависших jobs, atomic delayed promotion, DLQ и versioned payload envelope.

---

## 2.3 DB liveness check выполняет дополнительный query слишком часто

[`db::pdo()`](src/db/db.php#L117-L148) делает `SELECT 1` перед каждым повторным использованием connection. Поскольку все DB operations вызывают `pdo()`, обычный application query получает дополнительный round trip.

При горизонтальном scale важнее не термин «connection pool», а connection budget:

```text
web replicas × FrankenPHP workers × named DB connections
+ queue workers
+ migration/CLI processes
```

SKIM хранит по одному PDO connection на имя внутри процесса; это process-local connection cache, не настоящий multiplexing pool.

Практичнее reconnect после transport error либо ping не чаще заданного интервала. При большом количестве replicas PostgreSQL стоит закрывать PgBouncer.

---

# 3. Разбор `k8s_arhitecture.md`

## 3.1 Что действительно готово к horizontal web scale

### Shared sessions

[`config/app.php:18-22`](config/app.php#L18-L22) выбирает Redis session driver. Любая web replica может обработать следующий запрос без sticky sessions.

Ограничение: driver hardcoded в PHP config и не читает `SESSION_DRIVER` environment, хотя исходный k8s audit рекомендует устанавливать эту переменную. Сейчас она ничего не изменит.

### Redis cache

[`config/cache.php:3-23`](config/cache.php#L3-L23) выбирает Redis по умолчанию и исключает APCu. Это правильная модель для нескольких PHP processes, но заявления о рабочем file fallback неверны.

### 12-factor configuration

DB и Redis configuration читаются из environment. Это совместимо с Railway variables, Docker secrets и Kubernetes Secret/ConfigMap.

### Worker request lifecycle

[`app::begin_request()` и `app::end_request()`](src/core/app.php#L660-L706), [`worker_reset`](src/worker/worker_reset.php) и lifecycle tests показывают, что request-scoped state действительно очищается между запросами.

### DB transaction safety

[`db::rollback_all()`](src/db/db.php#L102-L115) не позволяет следующему request унаследовать открытую транзакцию.

### Event boot snapshot

[`event::capture_boot_snapshot()`](src/events/event.php#L32-L53) сохраняет boot listeners и удаляет request-time listeners между запросами.

Это хороший фундамент. Но сравнение «SKIM значительно более k8s-ready, чем Laravel» преувеличено. Laravel также поддерживает Redis sessions/cache/queue, trusted proxies, CSRF и Octane. Преимущество SKIM — меньший runtime и собственный worker lifecycle, но не автоматическая production readiness.

---

## 3.2 Реальный feature-specific blocker: WebSocket rooms

[`src/websocket/connection.php:6-22`](src/websocket/connection.php#L6-L22) хранит room membership в process-local static array. [`broadcast()`](src/websocket/connection.php#L86-L105) видит только connections текущего PHP-процесса.

Это блокирует cross-process room broadcast, но не обычный HTTP scale.

Варианты по мере роста:

1. одна replica отдельного WebSocket service;
2. WebSocket service масштабируется отдельно от HTTP;
3. Redis pub/sub fanout между processes;
4. отдельный realtime gateway, если требования перерастут framework helper.

Sticky sessions не решают fanout: клиент остаётся на process A, но событие, возникшее на process B, всё равно нужно доставить в A.

---

## 3.3 FrankenPHP worker packaging сейчас сломан

[`docker/Dockerfile.frankenphp:36-43`](docker/Dockerfile.frankenphp#L36-L43) ожидает `Caddyfile` и `/app/public/worker.php`, которых в framework repository нет.

[`worker_install_command`](src/cli/commands/worker_install_command.php#L20-L47) тоже ищет отсутствующий source `public/worker.php` и завершится ошибкой.

Это реальный blocker обещанного one-click deployment.

При исправлении важны:

- реальный worker entrypoint и Caddy configuration;
- production `php.ini`;
- фиксированный FrankenPHP image tag или digest вместо `latest`;
- `.dockerignore`;
- reproducible dependencies;
- корректный non-root setup либо unprivileged port.

Multi-stage build полезен для размера и build cache, но не является архитектурным условием scale. Простое добавление `USER www-data` тоже недостаточно: Caddy нужны права на `/data`, `/config`, а для ports 80/443 — `CAP_NET_BIND_SERVICE` либо непривилегированный port.

Для library repository отсутствие tracked `composer.lock` нормально. Для deployable starter/image lock-файл должен быть committed.

---

## 3.4 Отсутствие manifests и Helm — не blocker

Framework library не обязан поставлять application-specific:

- Deployment;
- Service;
- Ingress;
- HPA;
- PDB;
- Secret templates;
- storage classes.

Эти artifacts зависят от приложения, платформы, database topology и observability stack. Их лучше поставлять как:

- отдельный SKIM starter;
- Railway template;
- deployment recipes;
- optional `deploy/k3s` example.

HPA с CPU 70% и RPS 1000 из исходного аудита — произвольные числа без измерений. Для SSE/WebSocket CPU и RPS могут плохо отражать saturation; важнее active connections, queue depth и свободные FrankenPHP threads.

PDB бесполезен при одной replica или одном физическом node.

---

## 3.5 Health endpoint нужен, но не должен перезапускать app из-за DB outage

Kubernetes не требует именно HTTP `/health`: доступны TCP и exec probes, а сами probes опциональны. Однако небольшой HTTP endpoint полезен для Railway, Render, Kamal и rolling deploy.

Нужно разделять смысл:

- `/up` или `/live` — локальный process способен отвечать; без DB и Redis;
- `/ready` — replica готова принимать traffic;
- dependency diagnostics — отдельная проверка для monitoring и alerting.

DB+Redis query в liveness — опасный anti-pattern: краткий outage dependency перезапустит все здоровые web containers и усилит аварию.

Readiness может учитывать критические dependencies, но тоже требует осторожности: общий DB outage не должен одномоментно удалить все replicas из load balancer, если приложение способно отдавать cached/read-only responses.

---

## 3.6 File state и logging

File sessions и file cache действительно несовместимы с round-robin replicas без shared filesystem. Shared PVC для них обычно хуже Redis: он добавляет latency, locking и отдельную failure domain.

Логи по умолчанию пишутся в локальный файл через [`file_handler`](src/log/file_handler.php#L50-L66). `LOG_CHANNEL=null` не решает проблему — он выбрасывает записи.

Для container deployment нужен встроенный stdout/stderr handler. Railway, Docker, Coolify, Dokploy и Kubernetes соберут stream logs автоматически.

После перехода на Redis sessions/cache и stderr logs runtime `storage/` может оставаться ephemeral. Общий writable volume web replicas не требуется.

---

## 3.7 Compiled config и runtime environment

[`cache:build`](src/cli/commands/cache_build_command.php#L35-L59) выполняет config PHP files и записывает уже разрешённые env values в `storage/config_cache/config.php`.

Если cache строится в generic Docker image, environment от Railway или Kubernetes появится позже и не изменит cached config values.

Правильные варианты:

- не использовать compiled config в generic image;
- строить его при container startup после injection runtime env;
- строить отдельный environment-specific image, если это сознательная release model.

Baking generic config cache на Docker build stage, как предлагает исходный k8s audit, неверно.

---

## 3.8 `REQUEST_TIME_FLOAT` — hardening, не P0

[`worker::check_restart_signal()`](src/queue/worker.php#L136-L144) использует implicit `$_SERVER['REQUEST_TIME_FLOAT']`.

Утверждение, что переменная является HTTP-only, неверно: типичные PHP CLI builds также задают request start time. При этом PHP не гарантирует наличие всех `$_SERVER` entries во всех SAPIs.

Явное `$started_at = microtime(true)` в worker property проще и надёжнее, но это небольшое hardening, а не blocker horizontal scale.

---

## 3.9 Rate limiter уже существует

Исходный k8s audit утверждает, что rate limiting отсутствует, но [`src/middleware/rate_limit.php`](src/middleware/rate_limit.php) уже содержит Redis-backed sliding window middleware.

Ему нужны trusted proxies, connection reuse и желательно atomic Lua/transaction implementation. Circuit breaker в core микрофреймворка не обязателен; его добавляют на конкретные outbound integrations или на infrastructure level.

---

# 4. Минимальная архитектура горизонтального scale

```text
Internet
   |
Platform LB / Hetzner LB / Traefik
   |
+----------+----------+
| web #1   | web #2   |    один immutable image
+----------+----------+
      |          |
      +----+-----+
           |
   +-------+---------+----------------+
   | PostgreSQL     Redis             S3-compatible storage
   | authoritative  sessions/cache    uploads/blobs
   | data            coordination
   +----------------------------------+

Отдельный service/process:
queue worker #1..N
```

## Обязательные правила

1. Web image immutable; deploy каждой replica использует одну и ту же версию.
2. Пользовательский state не хранится на локальном диске web container.
3. Sessions и cross-process coordination находятся в Redis.
4. Uploads и generated files находятся в S3-compatible object storage.
5. Logs отправляются в stdout/stderr.
6. Migrations выполняются one-off/pre-deploy command, а не каждой web replica при старте.
7. Queue workers масштабируются отдельно от web.
8. Graceful shutdown должен перестать принимать новую работу и завершить текущий request/job в пределах platform timeout.
9. DB connection limit рассчитывается до увеличения replicas.
10. Autoscaling добавляется после измерения saturation, а не заранее.

Размер микрофреймворка почти ничего здесь не меняет. Horizontal scale определяется state ownership, process lifecycle и внешними dependencies, а не количеством классов framework.

---

# 5. Практичная лестница deployment

## Уровень 1. Railway — официальный one-click путь

Для SKIM это наиболее подходящий первый commercial deployment channel:

- Git и Dockerfile deploy;
- manual horizontal replicas;
- automatic vertical scaling в пределах plan limits;
- private networking;
- PostgreSQL, MySQL и Redis templates;
- отдельные always-on worker services;
- WebSocket support;
- deploy-time healthcheck;
- официальный `Deploy on Railway` button.

### Ограничения

- Automatic horizontal autoscaling отсутствует; replica count меняется вручную или через собственную automation.
- Sticky sessions нет. Для SKIM это нормально при Redis sessions.
- Cross-replica WebSocket rooms требуют Redis fanout.
- SSE ограничен 15 минутами при передаче heartbeat и закрывается после 5 минут без data.
- Database templates являются platform-provisioned containers, а не полностью managed RDS. Persistence, AOF, backups и HA нужно настроить явно.

### Рекомендуемый template

```text
SKIM Web
SKIM Queue Worker — optional
PostgreSQL
Redis + persistent volume + AOF
```

Queue worker нельзя включать как «reliable production queue», пока не исправлены reserve/ack и delayed promotion semantics.

### Affiliate и partner program

Railway официально предлагает:

- affiliate: 15% invoices привлечённого пользователя первые 12 месяцев;
- новому пользователю — $20 credits;
- Open Source Partner templates: 15% usage;
- support bonus: ещё 10%, итого до 25%.

Источники:

- [Railway Affiliate Program](https://docs.railway.com/community/affiliate-program)
- [Railway Open Source & Technology Partners](https://docs.railway.com/templates/partners)
- [Railway Scaling](https://docs.railway.com/deployments/scaling)
- [Railway SSE vs WebSockets](https://docs.railway.com/guides/sse-vs-websockets)

**Вывод:** Railway стоит сделать официальным one-click и monetization channel SKIM.

---

## Уровень 2. Один Hetzner VPS + Coolify или Dokploy

Это должно появиться раньше k3s.

Hetzner предлагает Coolify как one-click app. Такой setup даёт:

- дешёвый VPS;
- Git deploy;
- Dockerfile/Compose;
- TLS;
- env/secrets;
- backups;
- web UI.

Один нормальный server способен обслужить значительный SKIM traffic, особенно в FrankenPHP worker mode. Сначала разумно масштабироваться вертикально: это дешевле и проще диагностики.

Недостаток очевиден: один server не даёт HA. Если node умер, недоступны и app, и локально размещённые DB/Redis.

### Монетизация

Hetzner официально сообщает, что старая referral-credit программа прекращена. Старые referral links нельзя считать рабочей стратегией: [Hetzner Cloud FAQ](https://docs.hetzner.com/cloud/general/faq/).

У Dokploy есть официальный partner program с 20% first-year revenue: [Dokploy Partners](https://dokploy.com/partners). Это комиссия за клиентов Dokploy, а не доля расходов пользователя в Hetzner.

Для Coolify отдельная официальная cash affiliate program не подтверждена.

---

## Уровень 3. Два app-сервера + Hetzner Load Balancer

Когда одного server действительно недостаточно:

```text
Hetzner Load Balancer
  ├── app-1: FrankenPHP
  └── app-2: FrankenPHP

Separate state:
  ├── PostgreSQL/MySQL
  ├── Redis
  └── Object Storage backups/uploads
```

Это можно построить через:

- Dokploy + Docker Swarm;
- Coolify multi-server;
- Kamal;
- обычный Docker Compose + CI.

Для двух–пяти servers такой setup проще k3s и решает большую часть практических задач.

Важно: две web replicas и одна database на отдельном, но единственном server не дают полной HA. Database остаётся single point of failure. Horizontal web scale и infrastructure HA — разные задачи.

---

## Уровень 4. k3s на Hetzner

k3s оправдан, если появилась хотя бы одна реальная причина:

- много приложений и environments;
- частые rolling deploys;
- нужен scheduling workloads между nodes;
- HPA/node autoscaling подтверждены нагрузкой;
- команда уже знает Kubernetes;
- нужен стандартный Kubernetes ecosystem.

k3s легче Kubernetes по потреблению ресурсов, но не отменяет:

- etcd/datastore operations;
- CNI;
- CSI volumes;
- ingress;
- certificates;
- secrets;
- upgrades;
- backups;
- cluster debugging.

Для настоящего HA control plane нужны минимум три server nodes с нечётным etcd quorum. Один control-plane и два agent nodes позволяют распределить application workloads, но control plane остаётся SPOF.

Наиболее практичный bootstrap — community Terraform/OpenTofu module [Kube-Hetzner](https://github.com/kube-hetzner/terraform-hcloud-kube-hetzner), который автоматизирует private networking, Hetzner CCM/CSI, ingress и autoscaler. Это полезный advanced path, но не первая кнопка для пользователя микрофреймворка.

Стартовать следует с fixed node pool и manual replica count. HPA, KEDA и Cluster Autoscaler добавляются только после появления понятной метрики saturation.

---

## Дополнительная one-click альтернатива: DigitalOcean

DigitalOcean App Platform поддерживает `Deploy to DigitalOcean` button и позволяет добавить referral code прямо в deployment URL.

Официальные условия:

- обычный referral: $25 credit после того, как новый пользователь потратит первые $25;
- отдельная affiliate program: 10% spend привлечённого пользователя в течение года.

Ограничение Deploy button: он поддерживает простой service и dev database, но не собирает полноценный multi-service production topology с managed database так удобно, как Railway template.

Источники:

- [Deploy to DigitalOcean Button](https://docs.digitalocean.com/products/app-platform/how-to/add-deploy-do-button/)
- [DigitalOcean Referral Program](https://www.digitalocean.com/referral-program)
- [DigitalOcean Affiliate Program](https://www.digitalocean.com/affiliates)

Поэтому DigitalOcean разумно оставить вторым one-click channel после Railway.

---

# 6. Рекомендуемый порядок работ

## P0. Честный production deployment

1. Исправить официальный FrankenPHP worker entrypoint, Caddy config и Docker build.
2. Добавить process-only `/up` endpoint в deployable starter.
3. Добавить stdout/stderr log handler.
4. Сделать runtime configuration совместимой с PaaS; не bake'ить environment-specific config в generic image.
5. Commit `composer.lock` в deployable starter.
6. Создать Railway template и проверить deployment с двумя web replicas.

## P1. Безопасность web starter

1. Strict acceptance session IDs и обязательный regeneration contract для auth.
2. Trusted proxies.
3. CSRF module для cookie-auth web applications.
4. Unknown validation rules должны бросать exception.
5. Явная secure cookie configuration за TLS-terminating proxy.
6. Решить session lost updates: lock, versioning или документированный ограниченный contract.

## P1. Если queue заявляется production-ready

1. Reserve/ack/recovery.
2. Atomic delayed promotion.
3. Dead-letter queue.
4. Реальный exponential backoff.
5. Versioned payload envelope и deployment compatibility policy.

## P2. Только по реальному спросу

- Redis WebSocket fanout;
- k3s manifests;
- Helm chart;
- HPA/KEDA;
- PodDisruptionBudget;
- circuit breakers для конкретных integrations.

---

# Итоговая позиция

SKIM уже имеет хороший фундамент для stateless web replicas, но horizontal scalability нельзя оценивать только по Redis sessions и worker reset. Надёжность очереди, container packaging, logging, session concurrency, DB connection budget и внешнее хранение state не менее важны.

Продуктовая стратегия должна быть простой:

1. **Railway** — официальный one-click template и основной affiliate/partner channel.
2. **Coolify или Dokploy на Hetzner** — дешёвый народный self-hosted путь.
3. **Несколько app nodes за Hetzner LB** — следующий шаг при измеренной нагрузке.
4. **k3s/Kube-Hetzner** — отдельный advanced guide, а не обязательная основа SKIM.

Начинать с k3s только потому, что framework умеет worker mode, — преждевременное усложнение. Сначала нужно сделать безупречным один immutable container и его deployment contract. После этого horizontal scale становится свойством платформы, а не специальной функцией framework.
