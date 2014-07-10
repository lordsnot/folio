<?php

BMLib::$aliases['WapSpiderman'] = __DIR__;

$mapSiteId = 4906;

$site = array(
	'site' => array(
		'derive'=>'ConfigMultiSite',
		'properties'=>array(
			'namespace'=>'Wap\Sites\Spiderman',
			'mapSiteId' => $mapSiteId,
			'defaultServiceId'=>'main',
			'serviceInjections'=>array(
				'BM\SiteBuilder\ViewController'=>'SpidermanController',
			),
			'assets'=>array(
				'spiderman-smart'=>__DIR__.'/views/smartphone/assets',
			),
		),
	),
	
	'dependencies'=>array(
		'SpidermanController'=>array(
			'properties'=>array(
				'dataLayer'=>array('injectionref'=>'DataLayer'),
			),
		),
			
		'DataLayer'=>array(
			'type'=>'Wap\Sites\Spiderman\Data\DataLayer',
			'arguments'=>array(
				'bespokeDb'=>array('injectionref'=>'site_content_bespoke_db'),
				'geodataDb'=>array('injectionref'=>'geo_data_sites_db'),
				'formDb'=>array('injectionref'=>'form_entries_new_db'),
			),
			'properties'=>array(
				'storeTable'=>'site_content_bespoke.cinema_venues',
				'tableName'=>'form_'.$mapSiteId. '_entries',
			),
		),

		'ServiceDownload' => array(
			'type' => 'BM\SiteBuilder\Service\SiteDownload',
		),

		'ServiceRedirect' => array(
			'type' => 'BM\SiteBuilder\Controller\Services\UrlRedirection',
			'properties' => array(
				'allowedHosts'=>array(
					'http://www.hoyts.com.au',
					'http://m.eventcinemas.com.au',
					'http://m.villagecinemas.com.au',
					'http://m.dendy.com.au',
					'http://mobile.grandcinemas.com',
					'http://theamazingspiderman.com/'
				),
			),
		),
	),
);

return $site;

