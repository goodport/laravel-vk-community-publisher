# AGENTS.md

## О пакете

`goodport/laravel-vk-community-publisher` — публичный Laravel-пакет для публикации постов и ссылок с превью на стены сообществ ВКонтакте. Не зависит от моделей, страниц и хранения конкретного приложения.

## Стек

- PHP 8.3+
- Laravel 12
- `intervention/image` ^2.5
- `psr/log` ^3.0
- Тесты: Orchestra Testbench, PHPUnit 11
- Форматирование: Laravel Pint

## Структура

- `src/Contracts/CommunityPublisher.php` — контракт `publish()` и `delete()`.
- `src/VkCommunityPublisher.php` — сценарий публикации поста и ссылки с превью.
- `src/VkApiClient.php` — HTTP-клиент VK API и маскировка токена в логах.
- `src/VkApiException.php` — ошибки VK API и отсутствие валидного превью.
- `src/Data/CommunityCredentials.php` — id сообщества и токен.
- `src/Data/CommunityPost.php` — текст, ссылка и параметры превью.
- `src/Data/PublishedPost.php` — id опубликованного поста.
- `src/Preview/` — подготовка JPEG под карточку ссылки.
- `src/VkCommunityPublisherServiceProvider.php` — конфиг и singleton-биндинги.
- `config/vk-community-publisher.php` — публикуемый конфиг.
- `tests/` — тесты на Orchestra Testbench с HTTP-фейками.

## Ключевое поведение

- Пост без ссылки публикуется через `wall.post`.
- Пост со ссылкой сначала вызывает `wall.parseAttachedLink`, проверяет размер фото, затем публикует через `wall.post` с `attachments` и `link_photo_id`.
- Токены и id сообществ передаются при каждом вызове и не хранятся в конфиге пакета.
- Токен не должен попадать в логи.
- Превью для ссылки предоставляет страница приложения через `vk:image` или `og:image`; пакет страницы не рендерит.

## Команды

- `composer install`
- `composer test` или `vendor/bin/phpunit`
- `vendor/bin/pint --dirty`

## Тесты

- Используют Orchestra Testbench и `Http::fake`.
- Реальные запросы к ВК недопустимы.
- Запускай только связанные с изменением тесты.

## Правила

- Держи адаптер тонким: вызовы VK API и маппинг ответов, без бизнес-логики приложения.
- Меняй публичный API и конфиг обратносовместимо.
- Этот файл описывает структуру пакета; обновляй его при изменении архитектуры.
