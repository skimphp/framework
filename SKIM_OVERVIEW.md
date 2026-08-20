# SKIM — PHP 8.5+ Framework

## Что это

SKIM — современный PHP 8.5+ фреймворк с нулевыми внешними зависимостями (кроме fast-route и pest для тестов), строгой типизацией и полным стеком: HTTP routing с middleware, Active Record ORM, query builder, cache (Redis/file/array), session, validation с pipe-синтаксисом, события, Redis-очередь, CLI команды, DI-контейнер с 3 скоупами, Vite-интеграция для ассетов, логирование, i18n, тулзы для разработки (profiler, debug toolbar) и система расширений через Composer.

Все классы и методы используют snake_case конвенцию, шаблоны — чистый PHP без Twig/Blade, а архитектура строится вокруг компонентного подхода с namespace `skim\component` для фреймворка и `app\*` для пользовательского кода.

## Чем лучше других

1. **Нулевые зависимости** — собственные реализации вместо тяжеловесных библиотек (нет Symfony, Monolog, Twig), что снижает сложность и размер проекта

2. **AI-first подход** — встроенная система документации с @ai-contract аннотациями и MCP tools для AI агентов, что упрощает интеграцию с LLM

3. **Полный стек в одном пакете** — ORM, cache, queue, validation, events, CLI, assets, testing без необходимости собирать из десятков пакетов

4. **Современный PHP 8.5+** — строгая типизация и использование современных фич языка

5. **Система расширений** — Composer-based с декларацией capabilities и обнаружением конфликтов

6. **Vite integration из коробки** — HMR в dev режиме и hashed filenames в production

7. **Единая конвенция** — snake_case везде (классы, методы, файлы), что упрощает навигацию и предсказуемость кода

## Производительность

### Бенчмарки

Есть 5 официальных бенчмарков в `benchmarks/`:

| Бенчмарк | Результат | Цель | Статус |
|----------|-----------|------|--------|
| boot_isolation | 1.78ms | <1ms на bare metal | ⚠️ |
| cache_hit | 0.0004ms per call | <0.05ms | ✅ |
| http_hello | 0.597ms avg, 1517.6 RPS | <2ms | ✅ |
| http_json | 0.665ms avg, 1383.2 RPS | <7ms | ✅ |
| http_warm | 0.64ms avg, 1456 RPS | — | — |

**Запуск:** `docker compose exec app php benchmarks/bench_run.php [requests] [concurrency]`

Результаты измерены в Docker с 8 worker-ами. На bare metal с php-fpm будет еще быстрее.

### Worker Mode — "один раз запустил и используешь"

Это FrankenPHP-style режим где приложение загружается один раз и обрабатывает запросы в цикле:

- Отдельный бенчмарк `benchmarks/worker_mode_benchmark.php`
- Поднимает TCP сервер
- Загружает app один раз
- В цикле делает `app::begin_request() → dispatch() → send() → end_request()`
- Измеряет RPS в worker mode

Также есть **soak test** (`benchmarks/soak_test.php`) — прогоняет 1000 запросов через worker loop с leak detector для проверки отсутствия утечек памяти.

## Quickstart

### Docker
```bash
docker compose up -d --build
# http://localhost:8080
```

### CLI
```bash
php skim serve
```

## Runtime

- PHP 8.5+
- `strict_types=1`
- Zero external deps (except fast-route and pest for tests)