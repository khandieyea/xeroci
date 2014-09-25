<?php
namespace XeroCi;

use \GuzzleHttp\Client;
use \GuzzleHttp\Collection;

class ClientPrivate extends Client {
	
	private $defaultPrivateConfigPath = '/xeroci/private_application';

	private $defaultConfigFile 		= 'config.php';

	private $defaultPrivateKeyFile 	= 'privatekey.pem';

	private $defaultBaseEndPoint 	= 'https://api.xero.com/api.xro/2.0/';

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

		$config = Collection::fromConfig(
			Collection::fromConfig($xero_conf, $fileConfig)->toArray(), 
			$this->configDefaults(), 
			$this->configRequired()
		);

		//Reuse the consumer_key as the token.
		$config->add('token', $config->get('consumer_key'));
		
		//If our config has a guzzle_conf, lets merge the passed in and set the config['guzzle_conf'] as default
		
		$guzzle_conf = Collection::fromConfig(
			$guzzle_conf,  
			( $config->hasKey('guzzle_conf') ? $config->get('guzzle_conf') : [])
		);
		
		
		if(!$guzzle_conf->hasKey('base_url'))
			$guzzle_conf->add('base_url', $config->get('base_endpoint'));
		
		$defaults = ($guzzle_conf->hasKey('defaults') ? $guzzle_conf['defaults'] : []);
		
		$guzzle_conf->remove('defaults');
		
		if(!isset($defaults['auth']) || $defaults['auth'] != 'oauth')
			$defaults['auth'] = 'oauth';
			
		$guzzle_conf->add('defaults', $defaults);	
	
		if($config->hasKey('base_endpoint'))
			$config->remove('base_endpoint');

			
		parent::__construct($guzzle_conf->toArray());

		parent::getEmitter()->attach(
			new \GuzzleHttp\Subscriber\Oauth\Oauth1(
				$config->toArray()
			)
		);

		return $this;

	}
	
	public function getDefaultFileConfigPaths()
	{

		$ch = ['//','\\','\\\\','/'];

		$base = defined('CI_BASE_PATH') ? CI_BASE_PATH : (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');

		return [
			str_replace($ch, DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR.APPPATH.'config'.DIRECTORY_SEPARATOR.ENVIRONMENT.DIRECTORY_SEPARATOR.$this->defaultPrivateConfigPath),
			str_replace($ch, DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR.APPPATH.'config'.DIRECTORY_SEPARATOR.$this->defaultPrivateConfigPath),
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
			
			$file = $p.DIRECTORY_SEPARATOR.$this->defaultConfigFile;

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

	public function post($url = null, array $options = [])
    	{
    		return $this->_reroute_request($url, $options, 'post');
    	}

    	private function _reroute_request($url, $options=[], $verb='')
    	{

    		if(isset($options['xml']))
    		{

    			if(is_array($options['xml']) && !isset($options['body']))
    			{
    			
    				$body = $options['xml'];


    				$cutpos = strpos($url, '?');

    				$node = $url; 

    				if($cutpos !== false)
    					$node = substr($node, 0, $cutpos);

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
