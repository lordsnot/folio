<?php


$mapSiteId = 2187;

$subscribeTableName = 'form_'.$mapSiteId.'_entries';

$site = array(
	'site' => array(
		'type' => 'BM\SiteBuilder\Recipe\Simple\Config',
		'properties' => array(
			'dialogueSiteId' => 'DellVostro',
			'defaultServiceId' => 'Home',
			'mapSiteId' => $mapSiteId,
		),
	),
	
	'dependencies' => array(
		"ServiceHome" => array(
			'derive'=>'ServiceGet',
			'properties' => array(
				'requestUrl' => 'Home',
				'forms' => array(array('injectionref' => 'Form')),
			),
		),
	
		'Form'=>function ($container) use ($self, $subscribeTableName) {
			$h = new \SimpleDynamicFormHandler;
			$h->hashRowData = true;
			$h->formId = 'form1';
			$h->redirectUrl = $self->front->serviceUrl('Get', array('url' => 'Home/Confirm'));
			$h->definition = new \SessionTableDefinition(
				$container->get('form_entries_new_db'),
				$container->get('adserver_site_db')
			);
			$h->definition->name = $subscribeTableName;
			$h->definition->fields = array(
				'email' => array(
					'rules' => array('required', 
						array('regex', 
							'pattern' => '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/', 
							'message' => 'Please make sure that the email address you have entered is correct'
						),
					),
				),
				'checkbox' => array(
					'rules' => array('required', 
						array('type', 'type' => 'boolean'),
					),
				),
			);
			return $h;
		},
	),
);

return $site;

