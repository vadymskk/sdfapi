<?php
namespace Worksection;
use DateTime;

/**
 * Библиотека для формирования запроса к API Мегаплана
 * Полное описание API Мегаплана: http://wiki.megaplan.ru/API
 * 
 * @since      31.03.2010 19:44:58
 * @author     megaplan
 */
class SdfApi
{
	/** Идентификатор пользователя @var string */
	protected $accessId;

	/** Секретный ключ @var string */
	protected $secretKey;

	/** Название хоста @var string */
	protected $host;

	/** Индикатор использования https @var bool */
	protected $https = false;

	/** Результат последнего запроса @var string */
	protected $result;

	/** Информация о последнем запросе @var array */
	protected $info;

	/** Таймаут соединения в секундах @var integer */
	protected $timeout;

	/** Последняя ошибка CURL-запроса @var string */
	protected $error;

	/** Путь к файлу, который будет записан всё содержимое ответа  @var string */
	protected $outputFile = NULL;


	/**
	 * Создает объект
	 * @since 01.04.2010 14:43
	 * @author megaplan
	 * @param string $AccessId Идентификатор пользователя
	 * @param string $SecretKey Секретный ключ
	 * @param string $Host Имя хоста мегаплана
	 * @param bool $Https Использовать SSL-соединение (false)
	 * @param integer $Timeout Таймаут подключения
	 */
	public function __construct($AccessId, $SecretKey, $Host, $Https = false, $Timeout = 60)
	{
		$this->accessId = $AccessId;
		$this->secretKey = $SecretKey;
		$this->host = $Host;
		$this->https = $Https;
		$this->timeout = $Timeout;
	}


	/**
	 * Устанавливает нужно ли использовать https-соединение
	 * @since  20.12.2010 13:43
	 * @author megaplan
	 * @param bool $Https true
	 */
	public function useHttps($Https = true)
	{
		$this->https = $Https;
	}


	/**
	 * Устанавливает путь к файлу, в который будет записан всё содержимое ответа
	 * @since  20.12.2010 13:46
	 * @author megaplan
	 * @param string $FilePath Путь к файлу
	 */
	public function setOutputFile($FilePath)
	{
		$this->outputFile = $FilePath;
	}


	/**
	 * Отправляет GET-запрос
	 * @param $Uri
	 * @param $Params
	 * @return mixed
	 */
	public function get($Uri, array $Params = NULL)
	{
		$date = new DateTime();
		$Uri = $this->processUri( $Uri, $Params );
		$request = SdfApiInfo::create( 'GET', $this->host, $Uri, array( 'Date' => $date->format( 'r' ) ) );

		return $this->send( $request );
	}

	/**
	 * Отправляет POST-запрос
	 * @param $Uri
	 * @param $Params
	 * @return mixed
	 */
	public function post( $Uri, array $Params = NULL )
	{
		$date = new DateTime();
		$Uri = $this->processUri( $Uri );
		$headers = array(
			'Date' => $date->format( 'r' ),
			'Post-Fields' => $Params,
			'Content-Type' => 'application/x-www-form-urlencoded',
		);

		$request = SdfApiInfo::create(	'POST', $this->host, $Uri, $headers	);
		return $this->send( $request );
	}


	/**
	 * Собирает строку запроса из URI и параметров
	 * @param $Uri
	 * @param $Params
	 * @return string
	 */
	public function processUri($Uri, array $Params = NULL): string
	{
		$part = parse_url( $Uri );
		if (!preg_match( "/\.[a-z]+$/u", $part['path'])) $part['path'] .= '.easy';
		$Uri = $part['path'];
		if ($Params)
		{
			if (!empty( $part['query'])) parse_str( $part['query'], $Params );
			$Uri .= '?'.http_build_query( $Params );
		}
		elseif (!empty( $part['query']))
		{
			$Uri .= '?' . $part['query'];
		}

		return $Uri;
	}


	/**
	 * Осуществляет отправку запроса
	 * @since 01.04.2010 14:53
	 * @author megaplan
	 * @param SdfApiInfo $Request Параметры запроса
	 * @return string Ответ на запрос
	 */
	protected function send(SdfApiInfo $Request)
	{
		$signature = self::calcSignature( $Request, $this->secretKey );
		$headers = array(
			'Date: '.$Request->Date,
			'X-Sdf-Date:'.$Request->Date,
			'X-Authorization: '.$this->accessId . ':' . $signature,
			'Accept: application/json'
		);
		if ($Request->ContentType) {
			$headers[] = 'Content-Type: '.$Request->ContentType;
		}
		if ($Request->ContentMD5) {
			$headers[] = 'Content-MD5: '.$Request->ContentMD5;
			die(var_dump($headers));
		}

		$url = 'http' . ($this->https ? 's' : '') . '://' . $this->host . $Request->Uri;
        $ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, __CLASS__);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $Request->Method);
		if ($Request->Method == 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, true);
			if ($Request->PostFields)
			{
				$postFields = is_array($Request->PostFields) ? http_build_query($Request->PostFields) : $Request->PostFields;
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
			}
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		if ($this->https)
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		if ($this->outputFile)
		{
			$fh = fopen($this->outputFile, 'wb');
			curl_setopt($ch, CURLOPT_FILE, $fh);
		}
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

		if ($this->outputFile)
		{
			curl_exec($ch);
			$this->result = NULL;
			fclose($fh);
		}
		else
		{
			$this->result = curl_exec($ch);
		}
		$this->info = curl_getinfo($ch);
		$this->error = curl_error($ch);
        curl_close($ch);

		return $this->result;
	}


	/**
	 * Вычисляет подпись запроса
	 * @since 31.03.2010 20:21
	 * @author megaplan
	 * @param SdfApiInfo $Request Параметры запроса
	 * @param string $SecretKey Секретный ключ
	 * @return string Подпись запроса
	 */
	public static function calcSignature(SdfApiInfo $Request, $SecretKey)
	{
		$stringToSign = $Request->Method . "\n" .
			$Request->ContentMD5 . "\n" .
			$Request->ContentType . "\n" .
			$Request->Date . "\n" .
			$Request->Host . $Request->Uri;

		$signature = base64_encode(self::hashHmac('sha1', $stringToSign, $SecretKey));
		return $signature;
	}


	/**
	 * Клон функции hash_hmac
	 * @since 14.05.2010
	 * @author megaplan
	 * @param string $Algo алгоритм, по которому производится шифрование
	 * @param string $Data строка для шифрования
	 * @param string $Key ключ
	 * @param boolean $RawOutput
	 * @return string
	 */
	public static function hashHmac($Algo, $Data, $Key, $RawOutput = false)
	{
		if ( function_exists( 'hash_hmac' ) ) {
			return hash_hmac( $Algo, $Data, $Key, $RawOutput );
		}
		$Algo = strtolower( $Algo );
		$pack = 'H' . strlen( $Algo( 'test' ) );
		$size = 64;
		$opad = str_repeat( chr( 0x5C ), $size );
		$ipad = str_repeat( chr( 0x36 ), $size );

		if ( strlen( $Key ) > $size ){
			$Key = str_pad( pack( $pack, $Algo( $Key ) ), $size, chr( 0x00 ) );
		} else {
			$Key = str_pad( $Key, $size, chr( 0x00 ) );
		}

		for ( $i = 0; $i < strlen( $Key ) - 1; $i++ ) {
			$opad[$i] = $opad[$i] ^ $Key[$i];
			$ipad[$i] = $ipad[$i] ^ $Key[$i];
		}

		$output = $Algo( $opad.pack( $pack, $Algo( $ipad.$Data ) ) );

		return ( $RawOutput ) ? pack( $pack, $output ) : $output;
	}


	/**
	 * Возвращает результат последнего запроса
	 * @since 07.10.2010 17:45
	 * @author megaplan
	 * @return mixed
	 */
	public function getResult()
	{
		return $this->result;
	}


	/**
	 * Возвращает информацию о последнем запросе
	 * @since 07.10.2010 17:52
	 * @author megaplan
	 * @param string $Param Параметр запроса (если не указан, возвращается вся информация)
	 * @return mixed
	 */
	public function getInfo($Param = NULL)
	{
		if ($Param) return isset( $this->info[$Param] ) ? $this->info[$Param] : NULL;
		else return $this->info;
	}


	/**
	 * Возвращает последнюю ошибку запроса
	 * @since 14.10.2010 12:58:23
	 * @author megaplan
	 * @return string
	 */
	public function getError(): string
	{
		return $this->error;
	}
}
