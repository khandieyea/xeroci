<?php
namespace XeroCi;

// use \GuzzleHttp\Client;
// use \GuzzleHttp\Collection;

class ClientPrivate extends \GuzzleHttp\Client {

	private $defaultPrivateConfigPath = '/xeroci/private_application';

	private $defaultConfigFile 		= 'config.php';

	private $defaultPrivateKeyFile 	= 'privatekey.pem';

	private $defaultBaseEndPoint 	= 'https://api.xero.com/api.xro/2.0/';


	// private $nodeMap = [
		//
	// ]

	function __construct($xero_conf=[], $guzzle_conf=[])
	{


		$fileConfig = false;

		if(isset($xero_conf['file_config']))
			$fileConfig = $this->getFileConfig($xero_conf['file_config']);
		else
			$fileConfig = $this->getFileConfig();

		if($fileConfig === false)
			$fileConfig = [];

		if(isset($xero_conf['file_config']))
			unset($xero_conf['file_config']); //You cant pass a file_config with in the file config.

		if(isset($xero_conf['signature_method']))
			unset($xero_conf['signature_method']);

		$config = ( $xero_conf + $fileConfig + $this->configDefaults() );

		// $config = Collection::fromConfig(
		// 	Collection::fromConfig($xero_conf, $fileConfig)->toArray(),
		// 	$this->configDefaults(),
		// 	$this->configRequired()
		// );

		//Reuse the consumer_key as the token.
		if(isset($config['consumer_key']))
			$config['token'] = $config['consumer_key'];

		// $config->add('token', $config->get('consumer_key'));

		//If our config has a guzzle_conf, lets merge the passed in and set the config['guzzle_conf'] as default

		// $guzzle_conf['xml'] = 'something';
		// $guzzle_conf['defaults'] = ['headers'=>['Accept'=>'something']];

		if(isset($config['guzzle_conf']))
			$guzzle_conf += $config['guzzle_conf'];

		// $guzzle_conf = Collection::fromConfig(
		// 	$guzzle_conf,
		// 	( isset($config['guzzle_conf']) ? $config['guzzle_conf'] : [])
		// );

		if(!isset($guzzle_conf['base_uri']))
			$guzzle_conf['base_uri'] = $config['base_endpoint'];

		// if(!$guzzle_conf->hasKey('base_url'))
		// 	$guzzle_conf->add('base_url', $config->get('base_endpoint'));


		// $defaults = (isset($guzzle_conf['defaults']) ? $guzzle_conf['defaults'] : []);

		// $defaults = ($guzzle_conf->hasKey('defaults') ? $guzzle_conf['defaults'] : []);

		// unset($guzzle_conf['defaults']);

		// $guzzle_conf->remove('defaults');


		if(!isset($guzzle_conf['auth']) || $guzzle_conf['auth'] != 'oauth')
			$guzzle_conf['auth'] = 'oauth';


		// $guzzle_conf['defaults'] = $defaults;
		// $guzzle_conf->add('defaults', $defaults);


		if( isset( $config['base_endpoint']) )
			unset($config['base_endpoint']);

		// if($config->hasKey('base_endpoint'))
		// 	$config->remove('base_endpoint');



		if(!isset($guzzle_conf['handler']))
			$guzzle_conf['handler'] = \GuzzleHttp\HandlerStack::create();

		$guzzle_conf['handler']->push( new \GuzzleHttp\Subscriber\Oauth\Oauth1($config) );

		if(isset($config['ratecontrol']))
		{

			$guzzle_conf['handler']->push(new rateControl($config['ratecontrol'], ($config['token']??'')));

			unset($config['ratecontrol']);

		}
		
		parent::__construct($guzzle_conf);

		return $this;

	}

	public function getDefaultFileConfigPaths()
	{

		$ch = ['//','\\','\\\\','/'];

		$base = defined('CI_BASE_PATH') ? CI_BASE_PATH : (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : getcwd());

		return [
			str_replace($ch, DIRECTORY_SEPARATOR, APPPATH.'config'.DIRECTORY_SEPARATOR.ENVIRONMENT.DIRECTORY_SEPARATOR.$this->defaultPrivateConfigPath),
			str_replace($ch, DIRECTORY_SEPARATOR, APPPATH.'config'.DIRECTORY_SEPARATOR.$this->defaultPrivateConfigPath),
			str_replace($ch, DIRECTORY_SEPARATOR, APPPATH.'config'),
		];

	}

	public function getDefaultBaseEndPoint()
	{

		return $this->defaultBaseEndPoint;

	}

	public function getDefaultPrivateKeyFile()
	{

		foreach($this->getDefaultFileConfigPaths() as $p)
		{
			if(is_readable($p.DIRECTORY_SEPARATOR.$this->defaultPrivateKeyFile))
			{
				return $p.DIRECTORY_SEPARATOR.$this->defaultPrivateKeyFile;
			}
		}

		return NULL;

	}

	public function getFileConfig($path=null)
	{

		if(is_null($path))
		{
			$path = $this->getDefaultFileConfigPaths();
		}

		if(!is_array($path))
		{
			$path = array($path);
		}

		foreach($path as $p)
		{

			$file = ($p.DIRECTORY_SEPARATOR.$this->defaultConfigFile);

			if(is_readable($file) && is_file($file))
			{

				return include $file;

			}

		}

		return false;

	}


	private function configDefaults()
	{

		$r = [
			'base_endpoint' => $this->getDefaultBaseEndPoint(),
			'signature_method' => 'RSA-SHA1'
		];

		if( ( $secret = $this->getDefaultPrivateKeyFile() ) !== false )
		{
			$r['consumer_secret'] = $secret;
			$r['private_key_file'] = $secret;

		}

		return $r;

	}


	private function configRequired()
	{

		return [
			'consumer_key',
			'consumer_secret',
			'signature_method'
		];

	}

	
	public function get($url = null, array $options = [])
	{
		return $this->_reroute_request($url, $options, 'get');
	}
	
	public function getAsync($url = null, array $options = [])
	{
		return $this->_reroute_request($url, $options, 'getAsync');
	}

	public function post($url = null, array $options = [])
	{
		return $this->_reroute_request($url, $options, 'post');
	}
	
	public function postAsync($url = null, array $options = [])
	{
		return $this->_reroute_request($url, $options, 'postAsync');
	}

	public function put($url = null, array $options = [])
	{
		return $this->_reroute_request($url, $options, 'put');
	}

	public function putAsync($url = null, array $options = [])
	{
		return $this->_reroute_request($url, $options, 'putAsync');
	}



	private function _reroute_request($url, $options=[], $verb='')
	{

			if(isset($options['xml']))
			{
				if(is_array($options['xml']) && !isset($options['body']))
				{

					$body = $options['xml'];

					$node = $url;
					$cutpos = strpos($node, '?');

					if($cutpos !== false)
						$node = substr($node, 0, $cutpos);

					$cutpos = strrpos($node,'/');

					if($cutpos !== false)
						$node = substr($node,$cutpos);

					$node = trim($node, '/');

					//Override body
					$options['body'] = \ArrayToXML::toXML($body, $node);


				}

				//Always remove the xml key from options.. even if it doesn't work.
				unset($options['xml']);

			}

    	return parent::$verb($url, $options);

	}


}
