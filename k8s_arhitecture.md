Готово. Ниже — полная карта готовности SKIM к горизонтальному масштабированию через k8s / Docker Swarm / k3s, собранная для передачи другому агенту. Каждый пункт привязан к конкретному файлу и строкам в коде.

---

# SKIM Framework — Scaling Readiness Audit

## TL;DR

SKIM **архитектурно готов** к горизонтальному масштабированию «из коробки» в значительно большей степени, чем Laravel без Octane. Конфигурация по умолчанию уже stateless-friendly (Redis-сессии, Redis-кеш, Redis-очередь, env-driven конфиг, worker-mode с per-request reset). Однако есть **несколько блокеров и потенциальных проблем**, которые нужно решить перед production-деплоем в k8s.

---

## 1. Что сделано правильно (Green)

### 1.1 Stateless-сессии по умолчанию
`config/app.php` указывает Redis-драйвер сессций по умолчанию:
```php
'session' => ['driver' => 'redis', 'lifetime' => 7200, 'prefix' => 'sess_'],
```
<ref_snippet file="/Users/liquan/Documents/web/skim/framework/config/app.php" lines="18-22" />

`redis_session_driver` хранит данные как JSON-blob в Redis, ключ — `prefix + session_id`, читается из `PHPSESSID` cookie. <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/session/redis_session_driver.php" lines="20-65" />

**Значение для k8s:** любой pod может обслужить любой запрос — сессия не привязана к локальной файловой системе. Load balancer может использовать round-robin без sticky sessions.

### 1.2 Redis-first кеш с fail-soft fallback
`config/cache.php` явно исключает APCu (per-process state, не консистентен между worker'ами) и ставит Redis дефолтом:
```php
'driver' => env('CACHE_DRIVER', 'redis'),
'fallback' => 'file',
```
<ref_snippet file="/Users/liquan/Documents/web/skim/framework/config/cache.php" lines="3-23" />

**Значение:** кеш-состояние shared между всеми pod'ами. File-fallback — только для dev/одиночного инстанса.

### 1.3 Redis-очередь с BRPOP
`queue` — статический фасад над Redis-списками (`LPUSH`/`BRPOP`) и sorted set для отложенных задач. <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/queue/queue.php" lines="22-46" />

`worker` — отдельный long-lived CLI-процесс с graceful shutdown через `SIGTERM`/`SIGINT` и Redis-based restart-сигналом (`skim:queue:restart`). <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/queue/worker.php" lines="42-68" /> <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/queue/worker.php" lines="126-144" />

**Значение для k8s:**
- Web-pod'ы и worker-pod'ы разделены (web только `push`, worker только `BRPOP`)
- `queue:restart` через Redis позволяет rolling-deploy без убийства mid-job — k8s шлёт SIGTERM, worker дообрабатывает текущую задачу и выходит
- Несколько worker-pod'ов безопасны — BRPOP атомарно выдаёт задачу одному consumer'у

### 1.4 12-Factor конфигурация
Все конфиги читаются из env-переменных через `env()`: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/config/db.php" lines="3-12" /> <ref_snippet file="/Users/liquan/Documents/web/skim/framework/config/cache.php" lines="6-15" />

`env::get()` приоритет: `$_SERVER → $_ENV → .env cache → default`. <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/core/env.php" lines="81-100" />

**Значение для k8s:** envvars инжектируются через ConfigMap/Secret — стандартный паттерн. `.env` файл не нужен в контейнере.

### 1.5 Worker-mode (FrankenPHP) с per-request reset
Это ключевая архитектурная фишка. `worker.php` entry point: <ref_snippet file="/Users/liquan/Documents/web/skim/workspace/research/skim_worker/public/worker.php" lines="1-79" />

- `boot()` + `freeze()` + `event::capture_boot_snapshot()` — **один раз** при старте процесса
- Цикл: `begin_request() → dispatch() → end_request()` — per-request
- `worker_reset::apply()` между запросами сбрасывает: output buffers, DB-транзакции, все `resettable` facade'ы, profiler, request_trace, pipeline cache <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/worker/worker_reset.php" lines="39-55" />
- `leak_detector` — runtime-проверка на утечку state между запросами (hard invariants + soft growth trends) <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/worker/leak_detector.php" lines="44-104" />
- `WORKER_MAX_REQUESTS` envvar — recycle процесса после N запросов (memory leak protection) <ref_snippet file="/Users/liquan/Documents/web/skim/workspace/research/skim_worker/public/worker.php" lines="68-77" />
- `make_transient()` для контроллеров — fresh instance per request, no cross-request leakage <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/core/app.php" lines="478-498" />

**Значение для k8s:** каждый pod — это долгоживущий PHP-процесс с N worker'ов (Caddyfile `num 4`), обрабатывающий тысячи запросов без re-boot. Ресайкл по `WORKER_MAX_REQUESTS` = аналог `pm.max_requests` в PHP-FPM. Это даёт ~1600-1800 RPS на один worker (по бенчмаркам).

### 1.6 DB connection pool с liveness check
`db::pdo()` проверяет соединение `SELECT 1` перед переиспользованием; при MySQL-ошибках 2006/2013/HY000 — reconnect: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/db/db.php" lines="128-148" />

`db::rollback_all()` — safety net в worker-mode, откатывает незакрытые транзакции между запросами: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/db/db.php" lines="102-115" />

**Значение для k8s:** при rolling-deploy DB или network blip — worker сам восстановит соединение, не упадёт.

### 1.7 Docker-артефакты
- `docker/Dockerfile` — PHP 8.5-cli-alpine, dev-ориентированный <ref_snippet file="/Users/liquan/Documents/web/skim/framework/docker/Dockerfile" />
- `docker/Dockerfile.frankenphp` — production-образ на базе `dunglas/frankenphp:latest`, `--no-dev` install, Caddyfile <ref_snippet file="/Users/liquan/Documents/web/skim/framework/docker/Dockerfile.frankenphp" />
- `docker-compose.yml` — dev-окружение с MySQL, PostgreSQL, Redis, healthchecks <ref_snippet file="/Users/liquan/Documents/web/skim/framework/docker-compose.yml" />

### 1.8 Event bus с boot-snapshot
`event::capture_boot_snapshot()` — снимок listener'ов после boot; `reset_request()` восстанавливает snapshot между запросами, предотвращая накопление: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/events/event.php" lines="33-53" />

**Значение:** в worker-mode listener'ы зарегистрированные в контроллерах не утекают между запросами.

### 1.9 Graceful shutdown
- Worker-mode: `SIGTERM`/`SIGINT` → `$shutdown_requested = true`, цикл выходит после текущего запроса <ref_snippet file="/Users/liquan/Documents/web/skim/workspace/research/skim_worker/public/worker.php" lines="36-44" />
- Queue worker: `pcntl_signal(SIGTERM/SIGINT)` → `$this->stop()` после текущей задачи <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/queue/worker.php" lines="126-134" />
- `app::shutdown()` — закрывает DB-пул, сбрасывает cache <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/core/app.php" lines="927-934" />

**Значение для k8s:** `terminationGracePeriodSeconds` работает корректно — pod завершает активные запросы перед смертью.

---

## 2. Блокеры (Red) — нужно решить перед k8s

### 2.1 BLOCKER: WebSocket rooms — process-local, не масштабируются
`connection::$rooms` — статический in-memory массив. Документация прямо говорит:
> Rooms are process-scoped static arrays; broadcast() skips the excluded connection ID; **Use Redis pub/sub for multi-process room fanout**

<ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/websocket/connection.php" lines="21-22" /> <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/websocket/connection.php" lines="98-105" />

**Проблема:** при 2+ pod'ах с WebSocket — клиент на pod A не получит broadcast от pod B. Комнаты не синхронизированы между процессами.

**Решение:** нужен Redis pub/sub adapter (не реализован в framework — только задокументирован как "layer on top"). Для k8s с WebSocket — обязательно.

### 2.2 BLOCKER: Нет готового production Dockerfile с worker-mode в основном репо
`docker/Dockerfile.frankenphp` существует, но:
- `COPY . /app` копирует ВСЁ (включая tests, benchmarks, .git) — образ раздут
- Нет multi-stage build
- Нет `USER` директивы (работает от root)
- `Caddyfile` не включён в образ (COPY Caddyfile, но файл лежит в репо research, не в framework) <ref_snippet file="/Users/liquan/Documents/web/skim/framework/docker/Dockerfile.frankenphp" lines="37-42" />
- Нет `.dockerignore` (нужно проверить)

**Решение:** нужен production-ready Dockerfile с multi-stage, non-root user, HEALTHCHECK, правильным Caddyfile.

### 2.3 BLOCKER: Нет k8s manifests / Helm chart
В репо нет:
- `Deployment.yaml`, `Service.yaml`, `Ingress.yaml`
- `HorizontalPodAutoscaler`
- `ConfigMap`/`Secret` templates
- `PodDisruptionBudget`
- readiness/liveness probes

**Решение:** нужно создать. Для readiness — HTTP endpoint `/health` (не найден в коде, нужно добавить route).

### 2.4 BLOCKER: Нет health check endpoint
В коде не найден route типа `/health` или `/ready`. k8s требует HTTP-пробу.

**Решение:** добавить route в skeleton, проверяющий DB + Redis connectivity.

---

## 3. Потенциальные проблемы при X-масштабировании (Yellow)

### 3.1 File-based fallback драйверы — не работают в k8s
- `file_session_driver` — пишет в `storage_path('sessions')` <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/session/file_session_driver.php" lines="34-56" />
- `file_driver` (cache) — пишет в `storage_path('cache')` <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/cache/file_driver.php" lines="22-27" />
- `file_handler` (log) — пишет в `storage_path('logs/app.log')` <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/log/file_handler.php" lines="28-38" />

**Риск:** если `CACHE_DRIVER=file` или `app.session.driver=file` в проде — кеш/сессии локальны для pod'а. При scale-up данные не переносятся.

**Mitigation:** в k8s-конфиге всегда явно ставить `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`. File-драйверы — только dev.

### 3.2 Логи в локальный файл — не агрегируются
`file_handler` пишет в `storage/logs/app-YYYY-MM-DD.log` с `LOCK_EX`. <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/log/file_handler.php" lines="50-66" />

**Риск:** при N pod'ов логи разбросаны по контейнерам, теряются при restart. `LOCK_EX` создаёт contention на shared volume.

**Mitigation:**
- Вариант A: `LOG_CHANNEL=null` + собирать логи через stdout (но framework пишет в файл, не в stdout)
- Вариант B: `log::set_handler()` с custom handler → stderr/stdout (нужно реализовать `stderr_handler`)
- Вариант C: PVC с shared volume (но `LOCK_EX` + N writers = bottleneck)

### 3.3 `storage/` директория — требует writable volume
Dockerfile: `RUN mkdir -p /app/storage /app/storage/pid /app/storage/logs && chmod -R 777 /app/storage` <ref_snippet file="/Users/liquan/Documents/web/skim/framework/docker/Dockerfile" lines="42-43" />

В worker-mode `storage/` используется для:
- `config_cache/` — compiled config/env cache (генерируется через `php skim cache:build`)
- `sessions/` — если file driver
- `cache/` — если file driver
- `logs/` — если file handler
- `pid/` — PID files

**Риск:** если использовать Redis для всего — `storage/` нужен только для `config_cache/` (опционально, можно отключить). Но `chmod 777` — плохая практика для production.

**Mitigation:** `config_cache` можно bake в образ при build time (`RUN php skim cache:build`). Тогда `storage/` не нужен в runtime (кроме логов, которые лучше перенаправить в stdout).

### 3.4 Config cache — stale при rolling deploy
`config::load_compiled_cache()` читает `storage/config_cache/config.php`, проверяет freshness только в debug-режиме: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/core/config.php" lines="134-154" />

**Риск:** в production (`APP_DEBUG=false`) — cache не инвалидируется автоматически при изменении env-переменных. Если envvars меняются через ConfigMap без пересборки образа — config cache отдаёт устаревшие значения.

**Mitigation:**
- Не использовать config_cache в k8s (удалить файл или всегда пересобирать образ при изменении ConfigMap)
- Или: `cache:build` при build stage, env-переменные читаются runtime через `env::get()` (env не кешируется, только config-файлы)

### 3.5 Static facades — process-global state
Множество static свойств: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/db/db.php" lines="28" /> (DB pool), <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/cache/cache.php" lines="25" /> (cache driver), <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/queue/queue.php" lines="23-25" /> (Redis conn), <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/events/event.php" lines="24" /> (listeners), etc.

**Риск:** это **нормально** для PHP — каждый pod/процесс имеет свой набор. Но в worker-mode (FrankenPHP) эти statics переживают запросы. `worker_reset` сбрасывает только `resettable`-классы. Если сторонний extension добавляет static state без `resettable` — утечка.

**Mitigation:** `leak_detector` в strict-режиме (CI) ловит это. В prod — `WORKER_MAX_REQUESTS` ограничивает время жизни процесса.

### 3.6 `app::instance()` — process-wide singleton
`app::$instance` — singleton на весь процесс: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/core/app.php" lines="27" /> <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/core/app.php" lines="126-134" />

**Риск:** в worker-mode — это намеренно (один app-instance на процесс, per-request reset через `begin_request/end_request`). Но если кто-то вызовет `app::instance()` в тестах или в queue-worker — получит тот же singleton.

**Mitigation:** для queue-worker'а — отдельный процесс, свой `app::instance()`. Не проблема для k8s.

### 3.7 SSE streaming — long-lived connections
`sse` class держит соединение открытым, flush после каждого event: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/realtime/sse.php" lines="38-54" />

**Риск:** при масштабировании — load balancer должен поддерживать long-polling (proxy_read_timeout в nginx, idle timeout в ALB). k8s Service с ClusterIP — OK, но Ingress controller может рвать соединения по таймауту.

**Mitigation:** настроить Ingress annotations для SSE endpoints (`nginx.ingress.kubernetes.io/proxy-read-timeout: "3600"`).

### 3.8 `$_SERVER['REQUEST_TIME_FLOAT']` в queue restart check
`worker::check_restart_signal()` сравнивает Redis-timestamp с `$_SERVER['REQUEST_TIME_FLOAT']`: <ref_snippet file="/Users/liquan/Documents/web/skim/framework/src/queue/worker.php" lines="139-144" />

**Риск:** `REQUEST_TIME_FLOAT` — это время старта HTTP-запроса, но queue-worker — CLI-процесс, где `REQUEST_TIME_FLOAT` может быть не определён или равен времени запуска скрипта. Restart-сигнал может не сработать.

**Mitigation:** нужно проверить, что `REQUEST_TIME_FLOAT` определён в CLI SAPI. Если нет — использовать `getmypid()` + `proc_get_status()['start_time']` или зафиксировать время старта в property.

### 3.9 Нет rate limiting / circuit breaker
В коде нет встроенного rate limiter (Redis-based token bucket и т.п.). При spike-трафике и autoscaling — новые pod'ы могут быть перегружены до того, как HPA отреагирует.

**Mitigation:** rate limiting на уровне Ingress controller (nginx `limit_req`) или API gateway.

---

## 4. Что нужно создать для k8s-деплоя (Action Items)

### 4.1 Production Dockerfile (multi-stage)
```dockerfile
# Stage 1: build
FROM composer:2 AS builder
COPY . /app
RUN composer install --no-dev --optimize-autoloader
RUN php bin/skim cache:build  # bake config cache

# Stage 2: runtime
FROM dunglas/frankenphp:latest
COPY --from=builder /app /app
COPY Caddyfile /etc/frankenphp/Caddyfile
ENV FRANKENPHP_CONFIG="worker /app/public/worker.php"
USER www-data
HEALTHCHECK --interval=10s CMD curl -f http://localhost/health || exit 1
EXPOSE 80
```

### 4.2 k8s manifests (минимум)
- `Deployment` (web, replicas: 3+, `WORKER_MAX_REQUESTS=1000`)
- `Deployment` (queue-worker, separate)
- `Service` (ClusterIP)
- `Ingress` (с SSE-таймаутами)
- `HPA` (CPU 70%, RPS 1000)
- `ConfigMap` (envvars: `DB_HOST`, `REDIS_HOST`, etc.)
- `Secret` (DB password, Redis password, APP_KEY)
- `PodDisruptionBudget` (minAvailable: 2)
- `Probes`: readiness → `/health`, liveness → `/health` с разными timeouts

### 4.3 Health endpoint
```php
$app->router->get('/health', function() {
    db::val('SELECT 1');  // throws on DB failure
    cache::has('__health__');  // throws on Redis failure
    return response()->json(['status' => 'ok']);
});
```

### 4.4 Redis pub/sub adapter для WebSocket
Реализовать `redis_room_adapter` — subscribe на `ws:room:{name}` channel, broadcast локально + publish в Redis. Каждый pod подписан на Redis-каналы → cross-pod broadcast.

### 4.5 Stderr log handler
```php
final class stderr_handler implements log_handler {
    public function write(string $level, string $message, array $context): void {
        fwrite(STDERR, json_encode(['level' => $level, 'message' => $message, 'context' => $context] + ['ts' => date('c')]) . "\n");
    }
}
```
+ `LOG_CHANNEL=stderr` в config.

---

## 5. Сравнение с Laravel Scale (из вашего контекста)

| Требование | Laravel (без Octane) | SKIM |
|---|---|---|
| Containerization | Нужна Docker-обёртка | `Dockerfile.frankenphp` готов (нужен production-polish) |
| Web + worker separation | Queue worker отдельно | `queue:work` — отдельный процесс, уже разделено |
| Stateless sessions | `SESSION_DRIVER=redis` | `app.session.driver=redis` — **дефолт** |
| External cache/queues/storage | Redis | Redis — **дефолт** для cache, queue, sessions |
| Runtime like Octane | Octane (FrankenPHP/Swoole/RoadRunner) | FrankenPHP worker-mode — **встроен**, с per-request reset |
| Horizontal autoscaling | Laravel Scale package | Нужны k8s manifests (нет из коробки) |
| k8s/Docker Swarm ready | Через Laravel Scale | Архитектурно готов, нужны manifests + polish |

**Вывод:** SKIM ближе к "k8s-ready out of the box" чем Laravel без Octane, потому что stateless-by-default (Redis-сессии, Redis-кеш, env-config). Но Laravel Scale даёт готовый autoscaling-setup, а SKIM требует ручного создания k8s-артефактов. Архитектурных блокеров для k8s нет — только WebSocket rooms (нужен Redis adapter) и отсутствие manifests/health-checks.

---

## 6. Приоритеты для другого агента

1. **P0 (блокер):** Redis pub/sub adapter для WebSocket rooms — без этого multi-pod WebSocket не работает
2. **P0 (блокер):** Production Dockerfile (multi-stage, non-root, healthcheck, .dockerignore)
3. **P0 (блокер):** k8s manifests (Deployment, Service, HPA, ConfigMap, Secret, PDB, probes)
4. **P0 (блокер):** `/health` endpoint в skeleton
5. **P1:** Stderr log handler + `LOG_CHANNEL=stderr` для log aggregation
6. **P1:** Проверить `REQUEST_TIME_FLOAT` в CLI SAPI для queue restart signal
7. **P2:** `.dockerignore` (исключить tests, benchmarks, .git, docs)
8. **P2:** Ingress annotations для SSE endpoints (proxy-read-timeout)
9. **P3:** Rate limiting (Ingress-level или Redis token bucket в framework)

Анализ завершён. Все выводы выше подкреплены ссылками на конкретные файлы и строки кода — другой агент может использовать их как отправную точку для реализации P0-задач.