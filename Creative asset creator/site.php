<?php

namespace BigPicture\Sites\Creative;

$site = [
	'site'=> [
		'mixins'=>['SiteConfig'],
		'populate'=>function($site, $container) use ($siteId, $sitePath) {
			$site->renderer = $container->get('TwigRenderer', [
				'viewPath'=>$sitePath.'/views',
				'siteId'=>$siteId,
			]);
			$site->namespace = 'BigPicture\Sites\Creative';
			$site->serviceInjections = [
				'BigPicture\Sites\Creative\Controller'=>'Controller',
			];
			$site->defaultServiceId = "home";
			$site->assets = array(
				'creative'=>$sitePath.'/assets',
			);
		},
	],
	'dependencies' => [
		'Controller'=>[
			'populate'=>function($object, $container) {
				$object->dataLayer = $container->get('CreativeDataLayer');
				$object->mediaUnitFactory = $container->get('MediaUnitFactory');
			}
		],

		'MediaUnitFactory'=>[
			'shared'=>true,
			'instantiate'=>function($appContainer) {
				// these are not used here, but they need to be available to the mediaunit
				// dependency file's scope
				$settings = $this->front->settings;

				$factory = new \BM\Injector\Container();
				$factory->import(require __DIR__.'/mediaunits.php');

				return $factory;
			},
		],

		'CreativeDataLayer'=>function($container) {
			$dl = new Data\CreativeDataLayer(
				$container->get('ContentDbServer'),
				$container->get('FilePushClient'),
				$container->get('MediaUnitFactory'),
				$container->get('CacheShared')
			);

			return $dl;
		},
		
		'CreativePersistence'=>function($container) {
			return new \BM\Creative\Persistence($container->get('ContentDbServer'));
		},
		
		'RichMediaDb'=>function($container) {
			return new \PDODbConnector($container->get('ContentDbServer'), 'builder');
		},
		
		'JSONSerializer'=> function($container) {
			return new \Big\Builder\Serializer\JSONSerializer();
		},

		'RmuPersistence'=> function($container) {
			$db = $container->get('RichMediaDb');
			return new \Big\Builder\Persistence\MySQL($db, $container->get('JSONSerializer'));
		},

		'DataMapper'=>function($container) {
			$cache = $container->get('CacheShared');
			
			if (\BMLib::$debug)
				$cache = null;
			
			$manager = new \Amiss\Sql\Manager($container->get('ContentDbServer'), $cache);
			
			return $manager;
		},

		'FilePushClient'=>function($container){
			$filePusher = $container->get('FilePushClient');
			return $filePusher;
		}
	],
];

return $site;
