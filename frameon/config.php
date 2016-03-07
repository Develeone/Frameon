<?php
return array(
	'sitename' => 'SiteName',
	'encode' => 'utf-8',
	'cookietime' => 3600, // время жизни куков администратора в секундах
	'version' => '1.0.0',
	'debug' => false,
	'default_controller' => 'IndexController',
	'default_action' => 'show',
    'db' => array(),
	'router' => array( 
//		'([a-z0-9+_\-]+)/([a-z0-9+_\-]+)/([0-9]+)' => '$controller/$action/$id',
//        '([a-z0-9+_\-]+)/([a-z0-9+_\-]+)' => '$controller/$action',
//        '([a-z0-9+_\-]+)/?' => '$controller',
	),
	'scripts' => array(
		'/assets/js/libs/jquery-2.1.3.min.js',
		'/assets/js/libs/bootstrap/js/bootstrap.min.js',
	),
	'styles' => array(
		'/assets/js/libs/bootstrap/css/bootstrap.min.css',
		'/assets/js/libs/bootstrap/css/bootstrap-theme.min.css',
	),
);
