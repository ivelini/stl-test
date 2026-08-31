# План: вынос хардкода значений в config/availability.php

## Цель

Все магические значения — в один конфиг-файл `config/availability.php` (без env, чтение напрямую через `config()`). Поведение не меняется — меняется источник значений.

## Значения

| Ключ | Значение | Потребитель |
|---|---|---|
| `availability.cache_key` | `slots:availability` | `SlotController`, `InvalidateCacheSlotListener` |
| `availability.cache_window` | `[5, 15]` | `SlotController` |
| `availability.per_page` | `100` | `SlotController` |
| `availability.expires_minutes` | `5` | `CreateHold` |
| `availability.cache_lock_seconds` | `10` | `FlexibleCache` |
| `availability.cache_wait_seconds` | `5` | `FlexibleCache` |

## Изменения

1. Новый `config/availability.php` (6 ключей, плоская структура, комментарии о принадлежности).
2. `AvailabilityReader` — удалить константы `CACHE_KEY`, `CACHE_WINDOW`, `PER_PAGE` (остаётся чистая SQL-логика).
3. `SlotController` — `config('availability.cache_key'|'cache_window'|'per_page')`.
4. `InvalidateCacheSlotListener` — `config('availability.cache_key')`.
5. `CreateHold` — `config('availability.expires_minutes')` вместо `EXPIRES_IN_MINUTES`.
6. `FlexibleCache` — `config('availability.cache_lock_seconds'|'cache_wait_seconds')`.
7. `HoldApiTest` — `AvailabilityReader::CACHE_KEY` → `config('availability.cache_key')`.

## Тест-лист

Поведение не меняется — все 26 тестов остаются. Единственная правка теста: чтение ключа кэша в `HoldApiTest::test_confirm_and_cancel_invalidate_cache` из конфига. Страж-тест контракта: create-тест по-прежнему ожидает expires_at ≈ +5 мин (теперь из конфига) — упадёт, если конфиг собьётся.

## Прогоны

**Красный:** `test_confirm_and_cancel_invalidate_cache` падает — `config('availability.cache_key')` возвращает null (конфига ещё нет), кэш не инвалидируется.

**Зелёный:** 26 passed (74 assertions); phpstan level 6 — 0 ошибок; pint — PASS (62 файла).
