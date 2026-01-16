# yandex-translate-php

PHP-клиент для **Yandex Cloud Translate API**:

- Авторизация через **Api-Key** (API key сервисного аккаунта)
- Передаёт **folderId** в теле запроса
- Поддерживает `get()` и `getBatch()`
- Повторы (retry) при **429 / 5xx / таймаутах** (настраивается)
- Проверка SSL **включена по умолчанию**

## Требования

- PHP >= 7.2
- ext-curl

## Установка (Composer)

```bash
composer require da41b94c/yandex-translate-php
```

## Настройка

Рекомендуется задавать переменные окружения (на сервере/в CI secrets, не в репозитории):

- `YANDEX_API_KEY`
- `YANDEX_FOLDER_ID`

Пример (Linux):

```bash
export YANDEX_API_KEY="YOUR_API_KEY"
export YANDEX_FOLDER_ID="YOUR_FOLDER_ID"
```

## Использование (одна строка)

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Da41b94c\YandexTranslate\YandexTranslate;

$Tr = new YandexTranslate([
	'MaxRetries' => 2,
	'Debug' => false,
]);

$Res = $Tr->get('Привет мир', 'ru', 'en');

if (!empty($Res->ok)) {
	echo $Res->translations[0]->text . PHP_EOL;
} else {
	echo 'Error: ' . $Res->code . ' :: ' . $Res->message . ' (HTTP ' . $Res->httpCode . ')' . PHP_EOL;
}
```

## Использование (пакетный перевод)

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Da41b94c\YandexTranslate\YandexTranslate;

$Tr = new YandexTranslate();

$Res = $Tr->getBatch([
	'Первый текст',
	'Второй текст',
	'Третий текст',
], 'ru', 'de');

if (!empty($Res->ok)) {
	foreach ($Res->translations as $i => $t) {
		echo ($i + 1) . ') ' . $t->text . PHP_EOL;
	}
} else {
	echo 'Error: ' . $Res->code . ' :: ' . $Res->message . ' (HTTP ' . $Res->httpCode . ')' . PHP_EOL;
}
```

## Опции

Опции передаются в конструктор:

- `ApiKey` (string) — опционально, переопределяет env
- `FolderId` (string) — опционально, переопределяет env
- `Timeout` (int) — таймаут запроса, секунды (по умолчанию 15)
- `ConnectTimeout` (int) — таймаут подключения, секунды (по умолчанию 8)
- `MaxRetries` (int) — число повторов для временных ошибок (по умолчанию 2)
- `RetryBaseDelayMs` (int) — базовая задержка backoff, мс (по умолчанию 300)
- `UserAgent` (string) — свой User-Agent
- `CaBundlePath` (string) — путь к CA bundle (опционально)
- `Debug` (bool) — добавлять сырой ответ в `extra` при ошибках (по умолчанию false)

## Коды ошибок

- `NO_CREDENTIALS` — не заданы ApiKey/FolderId
- `EMPTY_TEXT` — список текстов пуст
- `RATE_LIMIT` — 429, слишком много запросов
- `UPSTREAM_5XX` — 5xx от API
- `AUTH_OR_PERMISSIONS` — 401/403 (неверный ключ/не хватает прав)
- `BAD_REQUEST` — 400 (неверный payload и т.п.)
- `BAD_JSON` — некорректный JSON-ответ / проблемы кодирования JSON
- `CURL_ERROR` — сетевые/SSL/таймаут ошибки (см. `meta.curlErrNo`)

## Заметки по безопасности

- **Никогда не коммить** API ключи и `.env`.
- Держи SSL-проверку включённой. Если в окружении нет корневых сертификатов — корректно установи CA certificates или укажи `CaBundlePath`.

## Лицензия

MIT
