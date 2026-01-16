<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Da41b94c\YandexTranslate\YandexTranslate;

putenv('YANDEX_API_KEY=YOUR_API_KEY');
putenv('YANDEX_FOLDER_ID=YOUR_FOLDER_ID');

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
