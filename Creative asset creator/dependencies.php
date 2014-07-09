<?php

return array(
	'FrontController'=>function($container) use ($settings, $env) {
		$front = new \BM\SiteBuilder\FrontController([
			'basePath'=>BASE_PATH,
			'settings'=>$settings,
			'container'=>$container,
		]);
		$front->siteLoader = $container->get('SiteLoader', [$front]);
		$front->assetMode = $settings['assetMode'];
		$front->assetPath = BASE_PATH.'/pub/assets/site';
		$front->assetUrl = '/assets/site';
		
		$front->environment = $env;
		$front->debug = \BMLib::$debug;
		$front->unsetSuperglobals = true;
		$front->defaultSiteId = 'base';
		
		$front->responders[422] = null;
		$front->responders['*'] = new \BM\Framework\Responder\StaticFile(BASE_PATH.'/error', ['baseUrl'=>$settings['baseUrl']]);

		return $front;
	},
	
	'SiteConfig'=>[
		'shared'=>true,
		'instantiate'=>function($container, $args) {
			if (!isset($args[0]))
				throw new \InvalidArgumentException();
			
			$config = new \BM\SiteBuilder\Site\DynamicConfig($args[0]);
			$config->defaultServiceId = 'index';
			return $config;
		},
	],
	
	'SiteLoader'=>function($container, $args) {
		if (!isset($args[0]))
			throw new \InvalidArgumentException();
		
		return new \BM\SiteBuilder\Site\File\Loader($args[0], BASE_PATH.'/sites');
	},
	
	'LegacyTwigSiteConfig'=>array(
		'mixins'=>'SiteConfig',
		'populate'=>function($object, $container) {
			$object->renderer = $container->get('LegacyTwigRenderer');
		},
	),
	
	'RendererSelector'=>function($container, $args) {
		$selector = new \BM\SiteBuilder\View\Renderer\Selector();
		$selector->renderers['twig'] = $container->get('TwigRenderer', $args);
		$selector->renderers['php'] = $container->get('PHPRenderer', $args);
		return $selector;
	},
	
	'Twig'=>function($container, $loader) {
		$twig = new BM\SiteBuilder\View\Renderer\Twig($loader);
		$twig->environment = array(
			'debug'=>\BMLib::$debug,
			'cacheManager'=>$container->get('Cache'),
		);

		if (BMLib::$debug) {
			$twig->extensions[] = new Twig_Extension_Debug();
		}

		return $twig;
	},
	
	'TwigRenderer'=>function($container, $args) {
		if (!isset($args['viewPath']))
			throw new \InvalidArgumentException("'viewPath' must be passed with args");
		
		if (!isset($args['siteId']))
			throw new \InvalidArgumentException("'siteId' must be passed with args");
		
		$loader = new BM\SiteBuilder\View\Loader\Alias(array(
			$args['siteId']=>$args['viewPath'],
			'global'=>BASE_PATH.'/views',

			// legacy global views
			'BigPicture/views'=>BASE_PATH.'/views',
		));
		
		$twig = $container->get('Twig', $loader);
		return $twig; 
	},
	
	'LegacyTwigRenderer'=>function($container, $args) {
		$loader = new BM\SiteBuilder\View\Loader\Namespaced();
		return $container->get('Twig', $loader);
	},
	
	'PHPRenderer'=>function($container, $args) {
		if (!isset($args['viewPath']))
			throw new \InvalidArgumentException("'viewPath' must be passed with args");
		
		return new BM\SiteBuilder\View\Renderer\PHP($args['viewPath']);
	},
	
	'Cache'=>function($container, $args) {
		return new \BM\Cache\PhpRedis('bigpicture', $container->get('Redis'));
	},
	
	'CacheShared'=>function($container, $args) {
		return new \BM\Cache\PhpRedis('bigpicture', $container->get('RedisShared'), 'sharedcache');
	},
	
	'DataMapper'=>function($container) use ($settings) {
		$cacheWrapper = null;
		
		if (!\BMLib::$debug) {
			$systemCache = $container->get('CacheShared');
			$cacheWrapper = new \Amiss\Cache('get', 'set', $systemCache);
			$cacheWrapper->prefix = 'amiss-';
		}
		
		// note: do not use objectNamespace with the datamapper - BigPicture has
		// too many models that share the same mapper.
		$manager = \Amiss::createSqlManager($container->get('ContentDbServer'), [
			'dbTimeZone'=>$settings['dbTimeZone'],
			'appTimeZone'=>$settings['appTimeZone'],
			'cache'=>$cacheWrapper,
		]);
		
		return $manager;
	},
	
	'Redis'=>array(
		'shared'=>true,
		'instantiate'=>function($container) use ($settings) {
			return new \BM\Data\PhpRedisConnector(array(
				'host'=>$settings['redisServer'],
				'db'=>$settings['redisDb'],
			));
		},
	),
	
	'RedisShared'=>array(
		'shared'=>true,
		'instantiate'=>function($container) use ($settings) {
			$redis = new \BM\Data\PhpRedisConnector($settings['redisSharedServer']);
			$redis->dbMap = [
				// See http://doc.bmdev.net/redis/keyspace.html
				'sharedcache'=>1,
				'errors'=>$settings['errorRedisDb'],
			];
			return $redis;
		},
	),

	'AdserverDataLayer'=>function($container) use ($settings) {
		$adl = new \BigPicture\Adserver\AdserverDataLayer(
			$container->get('ContentDbServer'),
			$settings['bannerDefaultBaseUrl']
		);

		return $adl;
	},
	
	'ContentDbServer'=>array(
		'type'=>'PDOConnector',
		'shared'=>true,
		'arguments'=>array(
			'dsn'=>$settings['contentDbDsn'],
			'user'=>$settings['contentDbUser'],
			'password'=>$settings['contentDbPassword'],
			'options'=>[\PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8'],
		)
	),

	'LogsDbServer'=>array(
		'type'=>'PDOConnector',
		'shared'=>true,
		'arguments'=>array(
			'dsn'=>$settings['logsDbDsn'],
			'user'=>$settings['logsDbUser'],
			'password'=>$settings['logsDbPassword'],
			'options'=>[\PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8'],
		)
	),

	'UtilityMongo'=>array(
		'type'=>'BM\Data\MongoConnector',
		'shared'=>true,
		'arguments'=>array(
			'server'=>$settings['utilityMongoServer'],
		),
	),

	'FilePushClient'=>array(
		'shared'=>true,
		'instantiate'=>function($container) use ($settings) {
			return new \BM\Helpers\FilePushClient(
				$settings['filePushUrl'],
				$settings['filePushUser'],
				$settings['filePushPassword']
			);
		}
	),
	
	'BigDDR'=>function($container) use ($settings) {
		$bigddr = new \BM\Device\BigDDR\WebLoader($settings['bigDDRBaseUrl']);
		$bigddr->cache = new \BM\Cache\PhpRedis('device', new \BM\Data\PhpRedisConnector());
		$bigddr->cache->defaultExpiration = new \BM\Cache\Expirations\FixedLength(3000);		
		return $bigddr;
	},

	// Our pattern at BM seems to be to encode the DB name into the query. These
	// should be deprecated in favour of just using ContentDbServer.
	'AdserverDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'adserver'),
	),

	'AdserverSiteDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'adserver_site'),
	),

	'InsertionOrdersDb'=>array(
		'type'=>'PDODbConnector', 
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'sales'),
	),

	'LoginDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector', 
		'injection'=>'PDODbConnector',
		// temporarily on Logs. should be moved to Content when legacy login is disabled
		'arguments'=>array('db'=>array('injectionref'=>'LogsDbServer'), 'database'=>'login'),
	),

	'SessionDb'=>array(
		'type'=>'PDODbConnector',
		'shared'=>true,
		'arguments'=>array('db'=>array('injectionref'=>'LogsDbServer'), 'database'=>'session'),
	),

	'MessagingDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector', 
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'messaging'),
	),

	'SiteContentBespokeDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'site_content_bespoke'),
	),

	'MSiteBuilderDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'bigpicture_site'),
	),

	'ImpressionsDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'impressions'),
	),

	'DigestsDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'digests'),
	),

	'LogsDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector', 
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'logs'),
	),

	'ResourceDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'resource_management'),
	),
	'CurrencyDb'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'currency'),
	),
	'ThirdPartyDigestsDb'=>array(
		'injection'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'third_party_digests'),
	),
	'HandsetsDb'=>array(
		'injection'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'handset'),
	),
	'CarriersDb'=>array(
		'injection'=>'PDODbConnector',
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'carriers'),
	),
	'form_entries_db'=>array(
		'injection'=>'PDODbConnector', 
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'site_form_data'),
	),
	
	'mrt_test_db'=>array(
		'injection'=>'PDODbConnector', 
		'arguments'=>array('db'=>array('injectionref'=>'ContentDbServer'), 'database'=>'mrt_testing'),
	),

	'AggregatorDb'=>function($container) {
		$db = new \PDODbConnector($container->get("ContentDbServer"), 'aggregator');
		return $db;
	},
	
	//Dont you dare use these...
	'LinodeDbServer'=>array(
		'type'=>'PDOConnector', 
		'shared'=>true,
		'arguments'=>array(
			'dsn'=>$settings['linodeDbDsn'],
			'user'=>$settings['linodeDbUser'],
			'password'=>$settings['linodeDbPassword'],
		)
	),
	'HackedLinodeDigests'=>array(
		'shared'=>true,
		'type'=>'PDODbConnector', 
		'arguments'=>array('db'=>array('injectionref'=>'LinodeDbServer'), 'database'=>'digests'),
	),
);
