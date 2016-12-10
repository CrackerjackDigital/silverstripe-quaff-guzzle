<?php

namespace Quaff\Transports;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Modular\Debugger;
use Quaff\Endpoints\Endpoint;
use Quaff\Exceptions\Transport as Exception;
use Quaff\Transports\MetaData\http;

class Guzzle extends Transport {
	use \Quaff\Transports\Protocol\http;

	/** @var Client */
	protected $client;

	public function __construct(Endpoint $endpoint, array $options = []) {
		parent::__construct($endpoint, $options);

		$this->options(array_merge_recursive(
			$this->headers(),
			$this->auth(),
			$this->native_options()
		));
		$this->client = new Client(
			$options
		);
	}

	/**
	 * @param array $uri including query parameters, fragments etc
	 * @param array $queryParams
	 * @return array|\SimpleXMLElement
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function get($path, array $queryParams = []) {
		try {
			$uri = $this->uri(
				$path,
				$this->queryParams()
			);

			/** @var GuzzleResponse $response */
			$response = $this->client->get(
				$uri
			);

			self::debug_message('sync', Debugger::DebugInfo);
			self::debug_message($response->getBody(), Debugger::DebugTrace);

			return static::make_response($this->getEndpoint(), $response);

		} catch (\Exception $e) {
			// rethrow as transport exception
			throw new Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Check if a resource, file etc exists, connection can be made etc. e.g. HTTP may do a HEAD request instead of getting the full document.
	 *
	 * @param       $uri
	 * @param mixed $responseCode
	 * @return mixed
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function ping($uri, &$responseCode) {
		try {
			$response = $this->client->head(
				$uri
			);
			return static::make_response($this->getEndpoint(), $response);

		} catch (\Exception $e) {
			// rethrow as transport exception
			throw new Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Check response code is one of our 'OK' response codes from config.response_code_decode
	 *
	 * @param $code
	 * @return bool
	 */
	protected function isError($code) {
		return static::match_response_code($code, self::ResponseDecodeError);
	}

	protected function isOK($code) {
		return static::match_response_code($code, self::response_decode_ok());
	}

	/**
	 * Decode the Guzzle Response into a Quaff Response, which may be a ErrorResponse if we got an
	 * error back from the api call.
	 *
	 * @param \Quaff\Endpoints\Endpoint $endpoint
	 * @param GuzzleResponse            $response
	 * @return \Quaff\Responses\Response
	 */
	public static function make_response(Endpoint $endpoint, GuzzleResponse $response) {
		if (!static::match_response_code($response->getStatusCode(), self::response_decode_ok())) {
			// fail
			return \Injector::inst()->create(
				$endpoint->getErrorClass(),
				$endpoint,
				$response->getBody(),
				[
					'ResultCode'    => $response->getStatusCode(),
					'ResultMessage' => $response->getReasonPhrase(),
					'ContentType'   => $response->getHeader('Content-Type'),
				]
			);
		}
		// ok
		return \Injector::inst()->create(
			$endpoint->getResponseClass(),
			$endpoint,
			$response->getBody(),
			[
				'ResultCode'  => $response->getStatusCode(),
				'ContentType' => $response->getHeader('Content-Type'),
			]
		);
	}

	/**
	 * Merge in headers
	 *
	 * @return array
	 * @internal param $info
	 */
	protected function headers() {
		return [
			self::ActionRead   => [
				'request.options' => [
					'headers' => [
						'Accept' => $this->endpoint->getAcceptType(),
					],
				],
			],
			self::ActionExists => [
				'request.options' => [
					'headers' => [
						'Accept' => $this->endpoint->getAcceptType(),
					],
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function auth() {
		return $this->endpoint->auth() ?: [];
	}
}