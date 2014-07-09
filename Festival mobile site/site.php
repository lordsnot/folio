<?php

BMLib::$aliases['WapButter'] = __DIR__;

$mapSiteId = 3050;

$site = array(
	'site' => array(
		'derive'=>'ConfigMultiSite',
		'properties'=>array(
			'namespace'=>'Wap\Sites\FatAsButter',
			'mapSiteId' => $mapSiteId,
			'defaultServiceId'=>'main',
			'serviceInjections'=>array(
				'BM\SiteBuilder\ViewController'=>'ButterController',
			),
			'assets'=>array(
				'butter-smart'=>__DIR__.'/views/smartphone/assets',
			),
		),
	),
	
	'dependencies'=>array(
		'ButterController'=>array(
			'properties'=>array(
				'dataLayer'=>array('injectionref'=>'DataLayer'),
			),
		),
		
		'ServiceRedirect' => array(
			'type' => 'RedirectService'
		),
		
		'DataLayer'=>array(
			'type'=>'Wap\Sites\FatAsButter\Data\DataLayer',
			'arguments'=>array(
				'formDb'=>array('injectionref'=>'form_entries_new_db'),
				'bespokeDb'=>array('injectionref'=>'site_content_bespoke_db'),
			),
			'properties'=>array(
				'tableName'=>'form_'.$mapSiteId. '_entries',
			),
		),
		
		'ServiceClickToCall'=>array(
			'type'=>'ClickToCallService'
		),
		
		//needs to support blackberry
		'Classifier'=>array(
			'type'=>'BM\SiteBuilder\Recipe\Multi\StandaloneDeviceClassifier',
		),
	),
);

return $site;
