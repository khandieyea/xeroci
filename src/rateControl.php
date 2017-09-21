<?php
namespace XeroCi;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use bandwidthThrottle\tokenBucket\Rate;
use bandwidthThrottle\tokenBucket\TokenBucket;
use bandwidthThrottle\tokenBucket\storage\PredisStorage as TokenBucketStorage;
use bandwidthThrottle\tokenBucket\BlockingConsumer;

class RateControl {

  /*
    The bucket we consume tokens from.
  */
	static $bucket = null;
  /*
    The consumer bucket, will sleep untill tokens are available.
  */
	static $consumerBucket = null;

  /*
    Key prefix we can use to seperate api profiles etc
  */
	var $throttleKey = null;

  /*
    The redis client for managing the buckets/tokens
  */
	var $storageClientCreds = null;

	function __construct($storage=[], String $throttleKey =null)
	{

		$this->throttleKey = $throttleKey;

		$this->storageClientCreds = is_array($storage) ? $storage : [];

		$this->getConsumerBucket();


	}

	public function __invoke(callable $handler)
	{

		return function (RequestInterface $request, array $options) use ($handler) {

			$consumer = $this->getConsumerBucket();

      		//Sleep until we get green-lit
			$consumer->consume(1);

			return $handler($request ,	$options);

		};

	}


	private function getConsumerBucket(): BlockingConsumer
	{
		return static::$consumerBucket ?? $this->buildConsumerBucket();
	}


	private function buildConsumerBucket(): BlockingConsumer
	{
		static::$bucket = new TokenBucket( 30, new Rate(0.5, Rate::SECOND), $this->getBucketStorage() );

		return (static::$consumerBucket = new BlockingConsumer(static::$bucket));
	}

	private function getBucketStorage(): TokenBucketStorage
	{
		return new TokenBucketStorage($this->throttleKey.'xero.api', new \Predis\Client($this->storageClientCreds));
	}

	static function bootstrap()
	{
		return static::$bucket->bootstrap(static::$bucket->getCapacity());
	}


}
