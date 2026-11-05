# Laravel VK Community Publisher

Публикация постов и ссылок с превью на стены сообществ ВКонтакте.

Пакет не зависит от моделей, страниц и структуры хранения конкретного приложения.
Данные сообщества передаются при каждом вызове, поэтому один пакет может
публиковать в любое количество сообществ.

## Требования

- PHP 8.3+
- Laravel 12
- Пользовательский токен ВК с доступом к стене сообщества. Пользователь должен
  иметь право публиковать от имени сообщества.
- Scope `photos` **не нужен**: превью ВК забирает самостоятельно по публичному URL.

## Установка

```bash
composer require goodport/laravel-vk-community-publisher
```

При необходимости опубликуйте конфиг:

```bash
php artisan vendor:publish --tag=vk-community-publisher-config
```

## Настройка

Файл `config/vk-community-publisher.php`:

- `api_version` — версия VK API (по умолчанию `5.199`);
- `base_url` — адрес API (по умолчанию `https://api.vk.ru/method`);
- `timeout` — таймаут HTTP-запроса в секундах;
- `log_channel` — канал Laravel для логов; токены в логи не попадают;
- `link_parse_attempts` и `link_parse_retry_delay_ms` — повторы
  `wall.parseAttachedLink`, который иногда отвечает без фото;
- `preview` — максимальный и минимальный размеры превью, качество JPEG.

Токены и id сообществ в конфиг пакета не входят. Передавайте их через
`CommunityCredentials`.

## Использование

```php
use Goodport\VkCommunityPublisher\Contracts\CommunityPublisher;
use Goodport\VkCommunityPublisher\Data\CommunityCredentials;
use Goodport\VkCommunityPublisher\Data\CommunityPost;

$publisher = app(CommunityPublisher::class);

$credentials = new CommunityCredentials(
    groupId: 123456789,
    accessToken: config('services.vk.token'),
);

$published = $publisher->publish(new CommunityPost(
    credentials: $credentials,
    message: "Текст поста\n\nhttps://example.com/page",
    linkUrl: 'https://example.com/page',
));

$publisher->delete($credentials, $published->postId);
```

### Ссылки и превью

Карточку ссылки ВК строит из `linkUrl`. Фото для превью берётся из результата
`wall.parseAttachedLink` для `linkParseUrl` (по умолчанию — `linkUrl`).

ВК принимает в `link_photo_id` только фото, которое сам подготовил для этой
ссылки. Если нужно отдать конкретный кроп, страница по `linkParseUrl` должна
показать его в `vk:image` или `og:image`. Пакет страницы не рендерит — этот
адаптер остаётся в приложении.

Если ВК закешировал превью, передайте в `linkParseUrl` уникальный параметр,
например метку времени. Пакет отправляет URL в ВК без изменений.

Подготовить JPEG 1200x630 можно через `PreviewImageFactory`:

```php
use Goodport\VkCommunityPublisher\Preview\PreviewImageFactory;

$preview = app(PreviewImageFactory::class)->make($binary);

// $preview?->contents, $preview?->width, $preview?->height
```

По умолчанию фабрика не увеличивает картинку. Для старых источников, которые
нужно растянуть, передайте `allowUpscale: true`.

## Ошибки

`VkApiException` выбрасывается при ошибке ВК, отсутствии фото для превью и
ответе без `post_id`. Пакет не подменяет ссылку постом без превью: либо пост
получает валидное фото, либо операция завершается ошибкой.

## Тесты

```bash
composer install
vendor/bin/phpunit
```

Тесты используют HTTP-фейки Laravel и не обращаются к реальному ВК.

## Лицензия

MIT.
