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

		$config->add('token', $config->get('consumer_key'));

		if(!isset($guzzle_conf['base_url']))
			$guzzle_conf['base_url'] = $config->get('base_endpoint');

		if($config->hasKey('base_endpoint'))
			$config->remove('base_endpoint');

		if(!isset($guzzle_conf['defaults']))
			$guzzle_conf['defaults'] = [];

		if(!isset($guzzle_conf['defaults']['auth']) || $guzzle_conf['defaults']['auth'] != 'oauth')
			$guzzle_conf['defaults']['auth'] = 'oauth';

		parent::__construct($guzzle_conf);

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


}
