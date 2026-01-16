<?php
declare(strict_types=1);

namespace Da41b94c\YandexTranslate;

use stdClass;

final class YandexTranslate
{
	public const TRANSLATE_URL = 'https://translate.api.cloud.yandex.net/translate/v2/translate';

	private string $YandexApiKey = '';
	private string $YandexFolderId = '';

	private int $Timeout = 15;
	private int $ConnectTimeout = 8;

	private int $MaxRetries = 2;			// 0 = без повторов
	private int $RetryBaseDelayMs = 300;	// базовая задержка перед повтором (экспоненциально)
	private string $UserAgent = 'yandex-translate-php/1.0';

	private string $CaBundlePath = '';		// опционально: путь к CA bundle (если нужно явно)
	private bool $Debug = false;			// сырой ответ в ошибках

	public function __construct(array $Options = [])
	{
		$this->LoadCredentials($Options);

		if (isset($Options['Timeout'])) {
			$this->Timeout = max(1, (int)$Options['Timeout']);
		}
		if (isset($Options['ConnectTimeout'])) {
			$this->ConnectTimeout = max(1, (int)$Options['ConnectTimeout']);
		}
		if (isset($Options['MaxRetries'])) {
			$this->MaxRetries = max(0, (int)$Options['MaxRetries']);
		}
		if (isset($Options['RetryBaseDelayMs'])) {
			$this->RetryBaseDelayMs = max(50, (int)$Options['RetryBaseDelayMs']);
		}
		if (isset($Options['UserAgent'])) {
			$this->UserAgent = trim((string)$Options['UserAgent']);
		}
		if (isset($Options['CaBundlePath'])) {
			$this->CaBundlePath = trim((string)$Options['CaBundlePath']);
		}
		if (isset($Options['Debug'])) {
			$this->Debug = (bool)$Options['Debug'];
		}
	}

	private function LoadCredentials(array $Options): void
	{
		$ApiKey = isset($Options['ApiKey']) ? trim((string)$Options['ApiKey']) : '';
		$FolderId = isset($Options['FolderId']) ? trim((string)$Options['FolderId']) : '';

		if ($ApiKey === '') {
			$EnvApiKey = getenv('YANDEX_API_KEY');
			if ($EnvApiKey !== false) {
				$ApiKey = trim((string)$EnvApiKey);
			}
		}
		if ($FolderId === '') {
			$EnvFolderId = getenv('YANDEX_FOLDER_ID');
			if ($EnvFolderId !== false) {
				$FolderId = trim((string)$EnvFolderId);
			}
		}

		$this->YandexApiKey = $ApiKey;
		$this->YandexFolderId = $FolderId;
	}

	public function setCredentials(string $ApiKey, string $FolderId): void
	{
		$this->YandexApiKey = trim($ApiKey);
		$this->YandexFolderId = trim($FolderId);
	}

	public function get(string $Text, string $LangOriginal = 'ru', string $LangTarget = 'en', string $Format = 'PLAIN_TEXT'): object
	{
		return $this->getBatch([$Text], $LangOriginal, $LangTarget, $Format);
	}

	public function getBatch(array $Texts, string $LangOriginal = 'ru', string $LangTarget = 'en', string $Format = 'PLAIN_TEXT'): object
	{
		if ($this->YandexApiKey === '' || $this->YandexFolderId === '') {
			return $this->Err('NO_CREDENTIALS', 'Missing Yandex credentials (ApiKey/FolderId)', 0, null, null);
		}

		$Texts = $this->NormalizeTexts($Texts);
		if (count($Texts) === 0) {
			return $this->Err('EMPTY_TEXT', 'Texts are empty', 0, null, null);
		}

		$Payload = [
			'sourceLanguageCode'	=> $LangOriginal,
			'targetLanguageCode'	=> $LangTarget,
			'format'				=> $Format,
			'texts'					=> $Texts,
			'folderId'				=> $this->YandexFolderId,
		];

		$Headers = [
			'Content-Type: application/json',
			'Authorization: Api-Key ' . $this->YandexApiKey,
			'User-Agent: ' . ($this->UserAgent !== '' ? $this->UserAgent : 'yandex-translate-php/1.0'),
		];

		return $this->RequestWithRetry(self::TRANSLATE_URL, $Payload, $Headers);
	}

	private function NormalizeTexts(array $Texts): array
	{
		$Out = [];
		foreach ($Texts as $t) {
			if (!is_scalar($t)) {
				continue;
			}
			$s = trim((string)$t);
			if ($s === '') {
				continue;
			}
			$Out[] = $s;
		}
		return $Out;
	}

	private function RequestWithRetry(string $Url, array $Payload, array $Headers): object
	{
		$Try = 0;
		$LastError = null;

		while (true) {
			$Try++;

			$Res = $this->DoRequest($Url, $Payload, $Headers);

			if (!empty($Res->ok)) {
				return $Res;
			}

			$LastError = $Res;

			if ($Try > (1 + $this->MaxRetries)) {
				return $LastError;
			}

			if (!$this->IsRetryable($Res)) {
				return $LastError;
			}

			$DelayMs = $this->ComputeBackoffMs($Try);
			$this->SleepMs($DelayMs);
		}
	}

	private function DoRequest(string $Url, array $Payload, array $Headers): object
	{
		$Json = json_encode($Payload, JSON_UNESCAPED_UNICODE);
		if ($Json === false) {
			return $this->Err('BAD_JSON', 'Failed to encode JSON payload', 0, null, null);
		}

		$ch = curl_init($Url);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $Headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $Json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_TIMEOUT, $this->Timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->ConnectTimeout);

		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		if ($this->CaBundlePath !== '' && is_file($this->CaBundlePath)) {
			curl_setopt($ch, CURLOPT_CAINFO, $this->CaBundlePath);
		}

		$Body = curl_exec($ch);
		$CurlErrNo = curl_errno($ch);
		$CurlErr = curl_error($ch);
		$HttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($Body === false || $CurlErrNo) {
			return $this->Err('CURL_ERROR', $CurlErr !== '' ? $CurlErr : 'Unknown cURL error', $HttpCode, null, [
				'curlErrNo' => $CurlErrNo,
			]);
		}

		$Decoded = json_decode($Body);
		if (!is_object($Decoded)) {
			$Extra = $this->Debug ? ['raw' => $Body] : null;
			return $this->Err('BAD_JSON', 'Invalid JSON response', $HttpCode, $Extra, null);
		}

		if ($HttpCode >= 400) {
			return $this->ParseApiError($Decoded, $HttpCode, $Body);
		}

		$Decoded->ok = true;
		$Decoded->httpCode = $HttpCode;

		return $Decoded;
	}

	private function ParseApiError(object $Decoded, int $HttpCode, string $RawBody): object
	{
		$Msg = isset($Decoded->message) ? (string)$Decoded->message : 'API error';

		$LocalCode = 'API_ERROR';

		if ($HttpCode === 401 || $HttpCode === 403) {
			$LocalCode = 'AUTH_OR_PERMISSIONS';
		} elseif ($HttpCode === 429) {
			$LocalCode = 'RATE_LIMIT';
		} elseif ($HttpCode >= 500) {
			$LocalCode = 'UPSTREAM_5XX';
		} elseif ($HttpCode === 400) {
			$LocalCode = 'BAD_REQUEST';
		}

		$Extra = [
			'response' => $Decoded,
		];

		if ($this->Debug) {
			$Extra['raw'] = $RawBody;
		}

		return $this->Err($LocalCode, $Msg, $HttpCode, $Extra, null);
	}

	private function IsRetryable(object $ErrObj): bool
	{
		$HttpCode = isset($ErrObj->httpCode) ? (int)$ErrObj->httpCode : 0;
		$Code = isset($ErrObj->code) ? (string)$ErrObj->code : '';

		if ($HttpCode === 429) {
			return true;
		}
		if ($HttpCode >= 500 && $HttpCode <= 599) {
			return true;
		}
		if ($HttpCode === 408) {
			return true;
		}

		if ($Code === 'CURL_ERROR' && isset($ErrObj->meta) && is_object($ErrObj->meta) && isset($ErrObj->meta->curlErrNo)) {
			$No = (int)$ErrObj->meta->curlErrNo;
			if (in_array($No, [6, 7, 28, 52, 56], true)) {
				return true;
			}
		}

		return false;
	}

	private function ComputeBackoffMs(int $Try): int
	{
		$Pow = $Try - 1;
		if ($Pow < 0) {
			$Pow = 0;
		}
		if ($Pow > 6) {
			$Pow = 6;
		}

		$Delay = $this->RetryBaseDelayMs * (int)pow(2, $Pow);

		$Jitter = function_exists('random_int') ? random_int(0, 250) : mt_rand(0, 250);
		$Delay += $Jitter;

		if ($Delay > 5000) {
			$Delay = 5000;
		}

		return (int)$Delay;
	}

	private function SleepMs(int $Ms): void
	{
		if ($Ms <= 0) {
			return;
		}
		usleep($Ms * 1000);
	}

	private function Err(string $Code, string $Message, int $HttpCode, $ExtraArrayOrNull, $MetaArrayOrNull): object
	{
		$Obj = new stdClass();
		$Obj->ok = false;
		$Obj->code = $Code;
		$Obj->message = $Message;
		$Obj->httpCode = $HttpCode;

		if (is_array($ExtraArrayOrNull)) {
			$Obj->extra = (object)$ExtraArrayOrNull;
		} elseif (is_object($ExtraArrayOrNull)) {
			$Obj->extra = $ExtraArrayOrNull;
		}

		if (is_array($MetaArrayOrNull)) {
			$Obj->meta = (object)$MetaArrayOrNull;
		} elseif (is_object($MetaArrayOrNull)) {
			$Obj->meta = $MetaArrayOrNull;
		}

		return $Obj;
	}
}
