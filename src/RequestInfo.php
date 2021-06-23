<?php
namespace Worksection;
use Exception;

/**
 * Объект-контейнер параметров запроса
 * 
 * @since 01.04.2010 12:25:00
 * @author megaplan
 */
class SdfApiInfo
{
	/** Список параметров @var array */
	protected $params;

	/** Список поддерживаемых HTTP-методов @var array */
	protected static $supportingMethods = array( 'GET', 'POST', 'PUT', 'DELETE' );

	/** Список принимаемых HTTP-заголовков @var array */
	protected static $acceptedHeaders = array( 'Date', 'Content-Type', 'Content-MD5', 'Post-Fields' );


	/**
	 * Создает и возвращает объект
	 * @since 01.04.2010 13:46
	 * @author megaplan
	 * @param string $Method Метод запроса
	 * @param string $Host Хост мегаплана
	 * @param string $Uri URI запроса
	 * @param array $Headers Заголовки запроса
	 * @return SdfApiInfo
	 * @throws Exception
	 */
	public static function create(string $Method, string $Host, string $Uri, array $Headers): SdfApiInfo
	{
		$Method = mb_strtoupper($Method);
		if (!in_array($Method, self::$supportingMethods)) throw new Exception( "Unsupporting HTTP-Method '$Method'" );
		$params = array(
			'Method' => $Method,
			'Host' => $Host,
			'Uri' => $Uri
		);

		// фильтруем заголовки
		$validHeaders = array_intersect_key( $Headers, array_flip( self::$acceptedHeaders ) );
		$params = array_merge( $params, $validHeaders );
		$request = new self($params);
		return $request;
	}


	/**
	 * Создает объект
	 * @since 01.04.2010 13:59
	 * @author megaplan
	 * @param array $Params Параметры запроса
	 */
	protected function __construct(array $Params)
	{
		$this->params = $Params;
	}


	/**
	 * Возвращает параметры запроса
	 * @since 01.04.2010 14:26
	 * @author megaplan
	 * @param string $Name
	 * @return string
	 */
	public function __get($Name): string
	{
		$Name = preg_replace( "/([a-z]{1})([A-Z]{1})/u", '$1-$2', $Name );
		if (!empty( $this->params[$Name])) return $this->params[$Name];
		else return '';
	}
}
