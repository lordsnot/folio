<?php
// WARNING: if you see this loader in a new site, it DOES NOT BELONG.
$loader = new \BM\ClassLoader\Fallback(__DIR__.'/lib');
$loader->registryAppend();

$mapSiteId = 3431;

$site = array(
	'site' => array(
		'type' => 'BM\SiteBuilder\Recipe\Content\Config',
		'properties' => array(
			'siteId' => 'cokekingsx',
			'mapSiteId' => $mapSiteId,
			'viewRendererId'=>'smarty',
			'assets'=>array(
				'coke-email'=>__DIR__.'/email',
			),
			'resizerCaching'=>false,
			'resizerBaseUrls' => array(
				'maps.google.com',
				'localhost',
				'bigmobileads.com',
			),
		),
	),

	'dependencies' => array(
		'CokeKingsXContentManager' => array(
			'type' => 'CokeKingsXContentManager',
			'arguments'=>array(
				'dataLayer'=>array('injectionref'=>'CokeKingsXDataLayer'),
			),
		),

		'ServiceGet' => array(
			'derive'=>'ServiceGetBase',
			'methods' => array('setContentManager' => array(array('injectionref' => 'CokeKingsXContentManager'))),
		),

		'CokeKingsXDataLayer' => array(
			'type'=>'CokeKingsXDataLayer',
			'arguments'=>array(
				'db'=>array('injectionref'=>'site_content_bespoke_db'),
			),
		),

		'ServiceEmail' => array(
			'type'=>'CokeKingsXEmailService',
			'derive'=>'ServiceGet',
			'properties' => array(
				'db'=>array('injectionref'=>'form_entries_new_db'),
				'tableName' => 'form_'.$mapSiteId.'_email',
				'pageKey'=>'Email',
				'pageKeyRedirect' => 'thanks',
				
				'fields' => array(
					'sender_id' => array(
						'rules' => array('required'),
					),
					'yname' => array(
						'rules' => array('required'),
					),
					'name' => array(
						'rules' => array('required'),
					),
					'email' => array(
						'rules' => array('required'),
					),
				),
			),
		),

		'ServiceBrochureSend' => array(
			'type' => 'CokeKingsXEmailer',
			'properties' => array(
				'submitHandler' => array('injectionref' => 'ServiceEmail'),
				'assetsKey' => "coke-email",
				'mailouts' => array (
					'toCustomer' => array(
						'templateFile' => 'index.html', //twig instead?
						'mailer' => array(
							'subject' => "Your name is on a pic of the 'Coke' sign!",
							'host' => 'ssl://smtp.gmail.com',
							'username' => 'donotreply@bigmobile.com',
							'password' => 'b1gm0b1l3',
							'replyTo' => array('name'=>'Do not reply', 'email'=>'donotreply@bigmobile.com'),
							'from' => array('name'=>'Coca-Cola','email'=>'donotreply@bigmobile.com'),
							'to' => array(
								array('name'=>'{{name}}', 'email' => '{{email}}'),
							),
						),	 
					),//toCustomer
				),//mailouts
			),//properties
		),
	),
);

return $site;

