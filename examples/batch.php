<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Da41b94c\YandexTranslate\YandexTranslate;

putenv('YANDEX_API_KEY=YOUR_API_KEY');
putenv('YANDEX_FOLDER_ID=YOUR_FOLDER_ID');

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
