<?php

namespace BM\BigMobile\Wap;

use BM\Cache;
use BM\Injector;
use BM\UrlMapper\UrlRouter;

class WapFrontController extends \BM\SiteBuilder\FrontController
{
	public function __construct($options)
	{
		parent::__construct($options);
		
		if (!$this->siteLoader) {
			$this->siteLoader = new \BM\SiteBuilder\Site\File\Loader($this, $this->basePath.'/sites');
			$this->siteLoader->searchPaths[] = $this->publicPath.'/sites';
		}	
		
		$this->assetUrl = '/assets';
		$this->urlRouter = new UrlRouter();
		
		$this->loadDependencies();
	}
	
	public function configure()
	{
		parent::configure();
		
		// we need to set the base url by hand in wap so certain command line scripts
		// like the cron runner and the impression tester know how to construct URLs.
		if (!$this->settings->has('baseUrl'))
			throw new \Exception("Please set baseUrl in your parameters environment file");
		
		$this->baseUrl = $this->settings->get('baseUrl');
		
		// FIXME: having this happen this late is not ideal. handler is dependent on  
		// environmental config to get the error queue path, which is only available after 
		// FrontController->configure. Settings need to be pulled out of the Application
		// hierarchy.
		$handler = new \BM\Error\HandlerChain();
		
		if (!\BMLib::$debug) {
			$handler->handlers[] = new \BM\Error\RedisExceptionHandler(
				$this->container->get('RedisUtility'),
				$this->settings->get('errorTube'),
				$this->settings->get('redisUtilityErrorDb'),
				new \BM\Error\RequestPopulator\Web($this)
			);
		
			if ($this->settings['errorHandledLog']) {
				$handler->handlers[] = function($exception) {
					file_put_contents($this->settings['errorHandledLog'], date('Y-m-d H:i:s').":\n".$exception."\n\n", FILE_APPEND);
				};
			}
		}
		
		$self = $this;
		
		$handler->handlers[] = function($exception) use ($self) {
			$isCli = php_sapi_name()=='cli';
			
			// DON'T bubble up to SAPI handler. Error service double-logs.
			// error_log($exception);
			
			if (!$isCli && !headers_sent()) {
				header(ifnull($_SERVER['SERVER_PROTOCOL'], 'HTTP/1.0').' 500 Internal Server Error');
			}
			if (\BMLib::$debug) {
				echo "<pre>".$exception."</pre>";
			}
			if (!$isCli) {
				$errorTemplate = null;
				if ($self->publicPath)
					$errorTemplate = $self->publicPath.'/500.html';
				
				if ($errorTemplate && file_exists($errorTemplate)) {
					echo file_get_contents($errorTemplate);
				}
				else {
					echo "500 Internal Server Error";
				}
			}
			exit(1);
		};
		
		$handler->register();
	}

	protected function loadDependencies()
	{
		$self = $this;
		$settings = $this->settings;
		
		$this->container->import(array(
			'ConfigMultiSite'=>array(
				'type'=>'BM\SiteBuilder\Recipe\Multi\Config',
				'properties'=>array(
					'cache'=>array('injectionref'=>'CacheWap'),
				),
			),
			
			'ConfigSimple'=>array(
				'type'=>'BM\SiteBuilder\Recipe\Simple\Config',
			),
			
			'RemoteAddressAdministrator'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					return new \RemoteAddressAdministrator($container->get('ContentDbServer'));
				},
			),
			
			'ModuleDevice'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					return new \BM\SiteBuilder\Module\Device($container->get('DeviceLoader'));
				},
			),
			
			'DeviceLoader'=>new Injector\Ref('DeviceLoader'.$settings->tryget('deviceLoader', 'BigDDR')),
			
			'DeviceLoaderBigDDR'=>array(
				'shared'=>true,
				'instantiate'=>function($container) use ($self) {
					$loader = new \BM\Device\BigDDR\WebLoader($self->settings['bigDDRBaseUrl']);
					$deviceCacheInjection = $self->settings['deviceCacheId'];
					if ($deviceCacheInjection) {
						$loader->cache = $container->get($deviceCacheInjection);
					}
					return $loader;
				},
			),
			
			'DeviceLoaderWurfl'=>array(
				'shared'=>true,
				'instantiate'=>function($container) use ($settings) {
					$wurfl = new \WurflHelper($settings['wurflDataPath'], $settings['wurflXmlPath']);
					$loader = new \BM\Device\Wurfl\Loader($wurfl);
					$deviceCacheInjection = $settings['deviceCacheId'];
					if ($deviceCacheInjection) {
						$cache = new Cache\Adapter\Wurfl($container->get($deviceCacheInjection));
						$wurfl->persistence = $cache;
						$wurfl->cache = $cache;
					}
					return $loader;
				},
			),
			

			'ModuleQuery'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					$module = new \BM\SiteBuilder\Module\Query();
					$module->keys = array('imppid');
					return $module;
				}
			),

			'ModuleImpressionUser'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					$module = new \BigMobileImpressionUserModule(
						null, $container->get('ContentDbServer'), $container->get('RemoteAddressAdministrator')
					);
					$module->userAgentMapper = $container->get('UserAgentMapper');
					return $module;
				},
			),
			
			// impression log module needs to appear after impression user module
			// as it is dependent on the user module being initialised first
			'ModuleImpressionLog'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					$module = new \ImpressionLogModule(null, 'ImpressionUser');
					$module->remoteAddressSelector = $container->get('RemoteAddressSelector');
					$module->impressionLog = $container->get('ImpressionLogger');
					return $module;
				},
			),
			
			'RemoteAddressSelector'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) use ($settings) {
					return new \BM\Helpers\RemoteAddressSelector($settings['proxies']);
				},
			),
			
			'CacheDevice'=>array(
				'shared'=>true,
				'instantiate'=>function($container) use ($self) {
					$cache = new \BM\Cache\PhpRedis('wurfl', $container->get('RedisLocal'), 'device');
					return $cache;
				},
			),
			
			'ImpressionLogger'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					return new \ImpressionLogger(
						$container->get('UserAgentMapper'),
						$container->get('ImpressionsDb'),
						$container->get('RemoteAddressAdministrator')
					);
				},
			),
			
			'UserAgentMapper'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					return new \UserAgentMapper($container->get('ContentDbServer'));
				},
			),
			
			'CacheWap'=>array(
				'shared'=>true,
				'instantiate'=>function($container) use ($self) {
					$cache = new \BM\Cache\PhpRedis('wap', $container->get('RedisLocal'), 'wapcache');
					return $cache;
				},
			),
			
			'RedisLocal'=>array(
				'shared'=>true,
				'instantiate'=>function($container) use ($self, $settings) {
					$redis = new \BM\Data\PhpRedisConnector($settings['redisServer']);
					$redis->dbMap = array(
						// DO NOT CHANGE THESE.
						// See http://doc.bmdev.net/redis/keyspace.html
					 	
						// Add at the end only, go no higher than 15.
						// Do not use cache 0 - it's a good warning sign that something went 
						// wrong if stuff appears in db 0
						'device'=>1,
						
						// deprecated. don't use this key, use wapcache
						'wap'=>2,
						
						// volatile store, flushed on every deployment
						'wapcache'=>2,
						
						// persistent store 
						'wapdata'=>3,
					);
					return $redis;
				},
			),
			
			'RedisUtility'=>array(
				'shared'=>true,
				'instantiate'=>function($container) use ($self, $settings) {
					$redis = new \BM\Data\PhpRedisConnector($settings['redisUtilityServer']);
					
					// See http://doc.bmdev.net/redis/keyspace.html
					$redis->dbMap = array(
						'sharedcache'=>1,
						'tracking'=>3,
						'error'=>6,
					);
					
					return $redis;
				},
			),
			
			'ContentDbServer'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) use ($self) {
					return new \PDOConnector(
						$self->settings['contentDbDsn'],
						$self->settings['contentDbUser'],
						$self->settings['contentDbPassword']
					);
				},
			)
		));
	}
}
