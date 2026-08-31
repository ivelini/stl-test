# План: пагинация доступности + remaining в базе

## Цель

`GET /slots/availability` получает пагинацию `?page=` с envelope `{data, meta}` (per_page=100), остаток вычисляется в SQL (без гидратации Eloquent-моделей). Кэш — вариант A: полная карта остатков под одним ключом, страница — срез после кэша (ADR 0006 не меняется).

## Контракт

`GET /slots/availability?page=1`

```json
{
  "data": [{ "slot_id": 1, "capacity": 10, "remaining": 9 }, ...],
  "meta": { "current_page": 1, "per_page": 100, "total": 250, "last_page": 3 }
}
```

- `page`: integer ≥ 1, дефолт 1; 422 при нарушении.
- `per_page` — константа 100 (домен Витрины: `AvailabilityReader::PER_PAGE`).
- Страница за пределами → `data: []`, meta честный (Laravel-канон).
- Плоский массив из ТЗ меняется на envelope — согласовано пользователем.

## Решения (согласованы)

1. Форма — `?page=`, envelope `{data, meta}`.
2. Кэш — вариант A: полная карта в одном ключе, срез в представлении.
3. per_page = 100.
4. Валидация — отдельный `SlotAvailabilityRequest` (`page`).
5. Проверки remaining переносятся из feature в unit `Slots/AvailabilityReaderTest`.

## Схема

```
GET /slots/availability?page=1
  → [SlotAvailabilityRequest] page: integer|min:1 (422)
  → SlotController@index
      → [FlexibleCache] handle(CACHE_KEY, CACHE_WINDOW, fn → reader->handle())
      │    └─ SQL: slots LEFT JOIN (count confirmed GROUP BY slot_id)
      │         SELECT slots.id, slots.capacity,
      │                slots.capacity - COALESCE(c.confirmed_count, 0) AS remaining
      │         ORDER BY slots.id
      → срез array_slice(map, (page-1)*100, 100)
  → 200: {data: [...], meta: {current_page, per_page, total, last_page}}
```

Инвалидация не меняется: `SlotChanged` → `CacheInvalidator` → один ключ (ADR 0006).

## Тест-лист

### `tests/Unit/Services/SlotService/Slots/AvailabilityReaderTest.php`

**Тест 1** — remaining вычисляется в базе: capacity − confirmed
Вход: capacity=3, confirmed=1
Ожидание: карта[slot_id].remaining == 2
Имя: `test_remaining_computed_in_database`

**Тест 2** — held не влияют на остаток (ADR 0001)
Вход: capacity=1, held=5, confirmed=0
Ожидание: remaining == 1
Имя: `test_remaining_ignores_holds`

### `tests/Feature/AvailabilityApiTest.php`

**Тест 3** — форма ответа: data + meta{current_page, per_page, total, last_page}; пагинация 250 слотов
Вход: 250 слотов, page=1 → data 100, total=250, last_page=3, per_page=100; page=3 → data 50
Ожидание: срезы по 100, meta корректен
Имя: `test_availability_paginated_with_meta`

**Тест 4** — 422 на невалидный page
Вход: page=0, page=-1, page=abc
Ожидание: 422
Имя: `test_availability_invalid_page_422`

**Тест 5** — без параметра page → текущая страница 1
Вход: запрос без page
Ожидание: meta.current_page == 1, data — первая страница
Имя: `test_availability_default_page`

**Тест 6** — страница за пределами → data пустой, meta честный
Вход: 250 слотов, page=999
Ожидание: data == [], meta.current_page == 999, total == 250
Имя: `test_availability_page_beyond_last_returns_empty`

## Прогоны

**Красный:** 4 feature-теста пагинации падают (форма `{data, meta}` и валидация `page` не реализованы); unit-тесты remaining зелёные как страж-тесты контракта.

**Зелёный:** 26 passed (74 assertions); phpstan level 6 — 0 ошибок; pint — PASS.

**Заминка в прогоне:** TypeError `stdClass` в неймспейсе (не было `use stdClass;` в `AvailabilityReader`) — исправлено импортом.
