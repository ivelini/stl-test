# stl-test — сервис бронирования слотов

Тестовое задание (вакансия): минимальный сервис бронирования слотов с горячим кэшем и защитой от перепродажи. Слот — окно склада/доставки с вместимостью; пользователь создаёт удержание, затем подтверждает или отменяет. ТЗ: `documentations/tz/test-task.md`.

## Состояние разработки — важно

**Архитектура согласована полностью, код — ранний каркас.** Прежде чем менять код, сверяйся с целевой схемой `documentations/architecture/05-scheme.md`.

| Слой | Состояние |
|---|---|
| Архитектура (характеристики, компоненты, стиль, схема) | Готово, согласовано (шаги 1–3, 5) |
| ADR (7 решений) | Готово |
| `GET /slots/availability` | Каркас: `SlotController@index` есть, кэш и остаток не реализованы |
| `POST /slots/{id}/hold`, `POST /holds/{id}/confirm`, `DELETE /holds/{id}` | Стабы: контроллеры есть, логики нет, маршруты не зарегистрированы |
| `app/Services` (AvailabilityReader, HoldCreator, CapacityArbiter, HoldLifecycle) | **Не создан** — целевой слой по ADR 0005, реализовать |
| Инвалидация кэша (`InvalidateCacheSlotListener`) | Пустой стаб; событие `SlotChangedEvent` есть |
| БД | `slots`, `holds` + `expires_at` (миграция добавлена); `HoldStatusEnum`: held, confirmed, cancelled |
| Тесты | Не написаны (стоковые ExampleTest удалены) — пишутся по тест-листу плана |

## Запуск (Docker)

```bash
make up          # поднять nginx + app + mysql + redis
make setup       # up + composer install + key:generate + storage:link
make fresh       # migrate:fresh --seed
make down        # погасить
```

- Порты: nginx **5580**, mysql **53306**, redis **56379** (переопределяются в `.env` через `DOCKER_*`).
- Артизан изнутри: `docker compose exec app php artisan ...`
- Тесты: `docker compose exec app php artisan test` (phpunit; sqlite `:memory:`, `CACHE_STORE=array` — изоляция от окружения).
- `make fix` — pint (пути: `app config database routes tests`); `make lint` — phpstan 2.2 + larastan 3.10, уровень 6 (`phpstan.neon`). Нюанс: правило pint `fully_qualified_strict_types` отключено (конфликт с phpstan на docblock `@use`); в `phpstan.neon` точечный ignore для HasFactory (регресс phpstan 2.2 с `@use`).
- Вне контейнера приложение работает на sqlite (раскомментировать блок в `.env.example`), но целевой стек по ТЗ — MySQL 8 + Redis.

`.env.example` адаптирован под docker-стек: mysql/redis активны, блок `DOCKER_*` (порты, имена, storage-пути), sqlite-вариант закомментирован.

## Структура (реальная, `app/`)

```
app/
├── Enums/    HoldStatusEnum (held, confirmed, cancelled; просрочка — производная от expires_at, статуса нет)
├── Events/   SlotChangedEvent ($slot_id) — публикуется после коммита
├── Http/
│   ├── Controllers/Slots/  SlotController (availability), SlotHoldController (стаб)
│   ├── Controllers/Holds/  HoldController (destroy — стаб), HoldConfirmController (стаб)
│   ├── Requests/           SlotRequest (пустой), HoldRequest (slot_id, idempotency_key)
│   └── Resources/          SlotResource (slot_id, capacity), HoldResource
├── Listeners/ InvalidateCacheSlotListener (пустой стаб)
├── Models/    Slot, Hold, User
└── Providers/ AppServiceProvider
```

Целевая структура (по ADR 0005): добавить `app/Services/` — `AvailabilityReader`, `HoldCreator`, `CapacityArbiter`, `HoldLifecycle`. Класса `SlotService` нет и не будет — «SlotService» из ТЗ трактуется как сервисный слой (ADR 0005).

## Домен и доменные правила

Сущности:
- **Slot** — окно с вместимостью. `capacity` — неизменяемая конфигурация, счётчика остатка нет (ADR 0002).
- **Hold** — билет на слот: `slot_id`, `status`, `idempotency_key`, (целевой — `expires_at`).

Правила:
- **Нельзя подтвердить больше вместимости** — центральное правило. Остаток вычисляется: `capacity − количество confirmed`. Решение о месте принимает только CapacityArbiter, в транзакции, по данным хранилища (ADR 0002, 0004).
- **Удержание не резервирует место** (ADR 0001): отказ приходит на подтверждении, активные удержания не видны в остатке, просрочка ничего не возвращает. `expires_at` — срок годности билета (5 мин).
- **Идемпотентность hold**: `Idempotency-Key` (UUID) — ровно один билет на ключ; повтор возвращает исходный результат.
- Машина состояний билета: `held → confirmed` (если арбитраж разрешил), `held → cancelled`, просроченные: `held → expired`. Словарь — в `app/Enums/HoldStatusEnum.php`.

## Архитектура (целевая, согласована)

- **Стиль:** многоуровневая, монолит (ADR 0003): Представление (`app/Http`) → Рабочий процесс (`app/Services`) → Хранение (`app/Models` + кэш).
- **Логические компоненты** (шаг 2 архитектуры): Витрина доступности → `AvailabilityReader`; Выдача удержаний → `HoldCreator`; Арбитраж вместимости → `CapacityArbiter` (единственный решает «есть ли место»); Жизненный цикл → `HoldLifecycle`. Выдача и Витрина не проходят через точку сериализации арбитража.
- **Кэш только на пути чтения** (ADR 0004): решение о вместимости никогда не принимается по кэшу.
- **Окно кэша 5–15 с** (ADR 0007): две фазы `Cache::flexible(key, [5, 15])`; при инвалидации (после confirm/cancel) старого значения нет — первое наполнение под `Cache::lock`, остальные читатели ждут.
- **Инвалидация событием** (ADR 0006): после коммита публикуется `SlotChanged`, слушатель `InvalidateCacheSlotListener` сбрасывает кэш; жизненный цикл не знает о кэше.
- **Приоритет характеристик:** целостность данных > масштабируемость (шаг 1). Окно устаревания до 15 с — осознанная плата за масштабируемость чтения.

## API

| Метод | Маршрут | Контроллер | Состояние |
|---|---|---|---|
| GET | `/slots/availability` | `SlotController@index` | каркас: отдаёт все слоты, `remaining` и кэш отсутствуют |
| POST | `/slots/{id}/hold` (заголовок `Idempotency-Key`) | `SlotHoldController` | стаб, маршрут не зарегистрирован |
| POST | `/holds/{id}/confirm` | `HoldConfirmController` | стаб, маршрут не зарегистрирован |
| DELETE | `/holds/{id}` | `HoldController@destroy` | стаб: удаляет запись вместо перевода в `cancelled` |

Ошибки: 409 при исчерпании вместимости (hold и confirm), 422 на невалидный UUID/ключ. Контракт ответа availability: `{slot_id, capacity, remaining}` (ТЗ).

## Документация — индекс слоёв

| Слой | Файл |
|---|---|
| ТЗ | `documentations/tz/test-task.md` (+ раздел противоречий — как решены) |
| Решения (ADR) | `documentations/adr/` — см. таблицу ниже |
| Архитектура | `documentations/architecture/` — 01 характеристики, 02 компоненты, 03 стиль, 05 схема |
| Эксплуатация | `documentations/operations.md` — **отсутствует**, создать при первом деплой/ручном сценарии |
| Контракты API | `documentations/api-maps/` — отсутствует; контракт пока задан ТЗ |
| Интеграции | `documentations/integrations/` — нет внешних сервисов (MySQL/Redis — внутри docker-стека) |

### ADR

| № | Решение | Статус |
|---|---|---|
| 0001 | Удержание проверяет, но не резервирует | Accepted |
| 0002 | Остаток вычисляется, не хранится | Accepted |
| 0003 | Многоуровневая архитектура | Accepted |
| 0004 | Кэш только на пути чтения | Accepted |
| 0005 | «SlotService» — сервисный слой, не класс | Accepted |
| 0006 | Инвалидация кэша событием | Accepted |
| 0007 | Окно кэша: две фазы + защита от лавины | Accepted |

Полные тексты — `documentations/adr/`, правила ведения — глобальное правило `adr.md`.

## Окружение и инструменты

- Стек: Laravel 12 (PHP 8.2+; в docker — php 8.5-fpm), MySQL 8.4, Redis, nginx.
- Тесты: PHPUnit 11 (`phpunit.xml`: sqlite `:memory:`, `CACHE_STORE=array`, очередь sync).
- Стиль: Pint (конфиг в `Makefile fix`); статический анализ phpstan — заявлен, не установлен.
- Единого файла `.env` в репозитории нет — окружение поднимается через `docker-compose.yml` и переменные `DOCKER_*`/`DB_*`.
