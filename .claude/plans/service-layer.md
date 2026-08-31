# План: сервисный слой и API-сценарии (ADR 0005)

## Цель

Реализовать четыре логических компонента из шага 2 архитектуры отдельными классами в `app/Services` (ADR 0005): `AvailabilityReader`, `HoldCreator`, `CapacityArbiter`, `HoldLifecycle`. Зарегистрировать маршруты hold/confirm/cancel, оживить инвалидацию кэша. Реализация строго по ADR 0001–0007 и тест-листу ниже.

## Ожидаемый результат

- Полный API по ТЗ: `GET /slots/availability`, `POST /slots/{id}/hold`, `POST /holds/{id}/confirm`, `DELETE /holds/{id}`.
- Кэш доступности 5–15 с (ADR 0007), инвалидация событием после коммита (ADR 0006).
- Тесты: unit (чистая логика) + feature (сценарии, идемпотентность, инвалидация) — зелёные; phpstan level 6 и pint — чистые.

## Задачи

1. Миграция: `UNIQUE(slot_id, idempotency_key)` на holds.
2. `app/Services/AvailabilityReader` — чтение остатков с кэшем.
3. `app/Services/CapacityArbiter` — атомарный переход, хранитель правила «не больше вместимости».
4. `app/Services/HoldCreator` — создание удержания, идемпотентность, срок годности.
5. `app/Services/HoldLifecycle` — confirm/cancel, машина состояний, публикация `SlotChanged` после коммита.
6. Маршруты + контроллеры (замена стабов) + Requests (валидация uuid ключа) + Resources (remaining).
7. `InvalidateCacheSlotListener` — сброс кэша.
8. Тесты по тест-листу (красный прогон ДО реализации, зелёный ПОСЛЕ).
9. Команда `holds:stress {slot_id} {requests=200}` — проверка характеристики 1 на живом стеке: N удержаний через API, параллельные confirm через curl, сводка 200/409 + сверка confirmed с БД (вариант В). Сценарий попадёт в `documentations/operations.md`.

## Контракт API

| Метод | Idempotency-Key | Успех | Ошибки |
|---|---|---|---|
| `GET /slots/availability` | — | 200 `[{slot_id, capacity, remaining}]` | — |
| `POST /slots/{id}/hold` | обязателен (UUID), в `Idempotency-Key` | 201 (создан) / 200 (повтор ключа) | 409 исчерпание, 422 невалидный ключ |
| `POST /holds/{id}/confirm` | **не требуется** (решено) | 200 | 409 исчерпание, 422 просрочен (решено) |
| `DELETE /holds/{id}` | не требуется (решено ранее) | 204 (в т.ч. повтор) | — |

## Схема

```
┌─ ТРИГГЕР ───────────────────────────────────────────────────────────────┐
│  HTTP: GET /slots/availability                                          │
│        POST /slots/{id}/hold      (Idempotency-Key, 422 без него)      │
│        POST /holds/{id}/confirm   (без ключа)                          │
│        DELETE /holds/{id}          (без ключа, идемпотентна по природе)│
└─────────────────────────────┬───────────────────────────────────────────┘
                              │
                              ▼
┌─ ПРЕДСТАВЛЕНИЕ ─────────────────────────────────────────────────────────┐
│  SlotController@index → SlotResource (slot_id, capacity, remaining)     │
│  SlotHoldController::__invoke (hold)                                   │
│  HoldConfirmController::__invoke (confirm)                             │
│  HoldController@destroy (cancel)                                       │
│  Requests: валидация uuid ключа в заголовке (hold), 422                │
└──────────────┬──────────────────────────────────────────────────────────┘
               │
               ▼
┌─ РАБОЧИЙ ПРОЦЕСС (app/Services) ────────────────────────────────────────┐
│  [AvailabilityReader]  read(): flexible([5,15]) + lock-наполнение      │
│       │ remaining = capacity − count(confirmed)                        │
│       ▼                                                                 │
│  [HoldCreator]  create(slot, key): идемпотентно, expires_at=+5мин      │
│       │ проверка места → CapacityArbiter (409 при исчерпании)          │
│       ▼                                                                 │
│  [CapacityArbiter]  reserve(slot, hold):                               │
│       │ UPDATE holds SET status='confirmed' WHERE id=? AND status='held'│
│       │  (affected=0 → разбор состояния: confirmed → replay 200,       │
│       │   cancelled → отказ, иначе → отказ)                            │
│       ▼                                                                 │
│  [HoldLifecycle]  confirm(hold) / cancel(hold) — машина состояний       │
│       └─ после коммита: SlotChanged(slot_id) →                          │
│           [InvalidateCacheSlotListener] → Cache::forget(availability)   │
└──────────────┬──────────────────────────────────────────────────────────┘
               │
               ▼
┌─ ДАННЫЕ ────────────────────────────────────────────────────────────────┐
│  MySQL: slots(capacity) · holds(slot_id, status, idempotency_key,       │
│         expires_at; UNIQUE(slot_id, idempotency_key))                   │
│  Redis: slots:availability (только путь чтения) + Cache::lock           │
└──────────────────────────────────────────────────────────────────────────┘
```

## Механики (решено)

1. **AvailabilityReader** — `Cache::flexible(key, [5,15], fn)`; внутри промаха — `Cache::lock` на наполнение (ADR 0007): при естественном истечении лавины нет (отдаётся старое), при инвалидации первое наполнение — под блокировкой.
2. **HoldCreator** — поиск по `(slot_id, idempotency_key)`: найден → вернуть как есть; не найден → проверка места → insert с `expires_at`. Гонку дублей ловит UNIQUE-индекс (решено: добавить).
3. **CapacityArbiter** — атомарный переход `UPDATE ... SET status='confirmed' WHERE id=? AND status='held'`; affected=0 → разбор: уже confirmed → replay (200), cancelled/иное → отказ. Счётчика нет, остаток вычисляется (ADR 0002). Отказ по ключу **не хранится** (решено: вариант A) — повтор 409 арбитрируется заново.
4. **HoldLifecycle** — статусы `held → confirmed | cancelled`; повторные переходы идемпотентны; просрочка — производная от `expires_at` (статуса `expired` нет): confirm протухшего → ошибка **422** (решено). После коммита — `SlotChanged` (ADR 0006).
5. **Фоновая очистка просроченных** — не делается (остаток от held не зависит, статуса expired нет; записи безвредны).
6. **cancel** — запись остаётся со статусом `cancelled` (не удаляется); отмена разрешена из held и confirmed (решено: да — «возврат места» автоматический, остаток вычисляется).
7. **Статус-коды hold** — 201 при создании, 200 при повторе ключа (решено: да).

## Тест-лист

### `tests/Unit/Services/HoldLifecycleTest.php`

**Тест 1** — переход held→confirmed разрешён, только если арбитраж разрешил; реализация с подтверждением без спроса арбитража этот тест завалит
Вход: hold(held), арбитраж разрешает
Ожидание: статус confirmed, вызван SlotChanged
Имя: `test_confirm_sets_confirmed_and_dispatches_event`

**Тест 2** — повторный confirm уже confirmed → идемпотентно, второй SlotChanged НЕ публикуется (страж-тест)
Вход: hold(confirmed), confirm повторно
Ожидание: без исключения, без второго события
Имя: `test_confirm_twice_is_idempotent_no_second_event`

**Тест 3** — cancel разрешён из held и из confirmed
Вход: hold(held), hold(confirmed)
Ожидание: оба → cancelled, SlotChanged опубликован
Имя: `test_cancel_from_held_and_confirmed`

**Тест 4** — повторный DELETE → no-op без исключения (идемпотентность отмены)
Вход: hold(cancelled), cancel повторно
Ожидание: метод завершился, событий нет
Имя: `test_cancel_twice_is_noop`

**Тест 5** — confirm протухшего hold (expires_at в прошлом) → бросает исключение, статус не меняется (просрочка проверяется до арбитража; на API мапится в 422)
Вход: hold(held), expires_at = now − 1 мин
Ожидание: `HoldExpiredException`, статус остался held
Имя: `test_confirm_expired_hold_throws`

### `tests/Unit/Services/HoldCreatorTest.php`

**Тест 6** — создание холда: запись со статусом held и expires_at = now + 5 мин
Вход: слот, ключ
Ожидание: запись создана, expires_at ≈ now+300с
Имя: `test_create_creates_held_with_expiry`

**Тест 7** — повтор с тем же ключом возвращает существующий hold, новой записи не создаётся (страж-тест: count не вырос)
Вход: тот же ключ, повторный create
Ожидание: вернулся тот же id, записей по ключу 1
Имя: `test_create_same_key_returns_existing_no_new_row`

**Тест 8** — при исчерпании (confirmed == capacity) → бросает, запись не создаётся (страж-тест)
Вход: capacity=1, confirmed=1, новый ключ
Ожидание: `CapacityExhaustedException`, записей не прибавилось
Имя: `test_create_when_exhausted_throws_no_row`

### `tests/Unit/Services/CapacityArbiterTest.php` (БД sqlite :memory: — признанный интеграционный контур)

**Тест 9** — равенство confirmed == capacity — НЕ перепродажа; переход разрешён только при строгом <
Вход: capacity=2, confirmed=2, новый confirm
Ожидание: исключение CapacityExhaustedException
Имя: `test_reserve_when_equal_to_capacity_throws`

**Тест 10** — атомарность: UPDATE WHERE status='held' — из двух конкурирующих вызовов (confirm vs cancel) ровно один переводит, второй получает отказ
Вход: hold(held), два конкурирующих вызова
Ожидание: ровно один переход, второй — отказ
Имя: `test_reserve_only_held_hold_transitions`

### `tests/Feature/HoldApiTest.php`

**Тест 11** — hold 201 + тело с id/status held; повтор с тем же ключом → 200, тот же id
Вход: POST /slots/1/hold c Idempotency-Key, повтор
Ожидание: 201 → 200, id совпадает
Имя: `test_hold_created_then_replay_same_key`

**Тест 12** — hold без заголовка Idempotency-Key → 422
Имя: `test_hold_without_key_422`

**Тест 13** — confirm 200; повторный confirm → 200 идемпотентно, повторно `SlotChanged` не публикуется (страж)
Вход: confirm, повторный confirm
Ожидание: 200/200, событий ровно 1
Имя: `test_confirm_then_confirm_again_200_single_event`

**Тест 14** — confirm при исчерпании → 409 (capacity=1, два hold, два confirm — второй 409)
Имя: `test_confirm_over_capacity_409`

**Тест 15** — confirm просроченного hold → 422
Вход: hold(held, expires_at в прошлом), confirm
Ожидание: 422, статус остался held
Имя: `test_confirm_expired_hold_422`

**Тест 16** — DELETE → 204; повторный DELETE → 204; запись осталась со статусом cancelled (страж-тест)
Имя: `test_delete_then_delete_again_204_row_cancelled`

**Тест 17** — после confirm и после cancel кэш доступности инвалидирован
Имя: `test_confirm_and_cancel_invalidate_cache`

### `tests/Feature/AvailabilityApiTest.php`

**Тест 18** — GET /slots/availability: поля slot_id, capacity, remaining; remaining == capacity − confirmed
Вход: capacity=3, confirmed=1
Ожидание: remaining=2
Имя: `test_availability_remaining_computed`

**Тест 19** — остаток не учитывает held (удержание не резервирует, ADR 0001)
Вход: capacity=1, held=5, confirmed=0
Ожидание: remaining=1
Имя: `test_availability_ignores_holds`

### Конкурентность (решено: вариант В — artisan-команда + параллельные curl)

**Тест 20** — 200 конкурентных подтверждений на слот вместимостью 10 → подтверждено ровно 10 (мера характеристики 1)
Вход: команда `holds:stress {slot_id} {requests=200}` против живого стека (nginx :5580)
Процесс: команда создаёт N удержаний через API hold (N уникальных Idempotency-Key), затем запускает N параллельных `curl POST /holds/{id}/confirm` (xargs -P), собирает HTTP-коды, сверяет с БД.
Ожидание: confirmed == min(capacity, N); вывод команды — сводка 200/409 и результат сверки
Имя: `test_200_concurrent_confirms_yield_exactly_capacity` (сценарий, не phpunit-тест; запуск вручную, не входит в `artisan test`)

## Прогоны

**Красный (до реализации):** 19 тестов, все падают по ожидаемым причинам — `Class "App\Services\..." not found` (unit) и 404 на незарегистрированные маршруты / отсутствие `remaining` (feature).

**Зелёный (после реализации):** 19 passed (49 assertions). phpstan level 6 — 0 ошибок, pint — PASS 52 файла.

**Конкурентная проверка (тест 20, вариант В):** `holds:stress 13 200` на живом стеке — 200×200, 190×409, `OK: confirmed = 10, ожидалось = 10` (мера характеристики 1 подтверждена).
