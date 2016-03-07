<?php
return array(
    'sitename' => 'Тестовая страница php фреймворка',
    'db' => new Registry(include 'config.db.php'),
	'router' => array(
		'girl/([0-9]+)' => 'GirlPageController/Read/$id',
        'ajax/GirlsFilter/' => 'GirlsFilterController/filter'
//		'([a-z0-9+_\-]+)/([a-z0-9+_\-]+)/([0-9]+)' => '$controller/$action/$id',
//        '([a-z0-9+_\-]+)/([a-z0-9+_\-]+)' => '$controller/$action',
//        '([a-z0-9+_\-]+)/?' => '$controller',
//        '([a-z0-9+_\-]+)\.html' => 'page/read/$id',
	),
);