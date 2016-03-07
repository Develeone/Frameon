<?php
	// Устанавливаем константы путей, чтоб потом проще было обращаться ко всему
	define('ROOT', dirname(__FILE__).'/');
	define('FRAMEON', dirname(__FILE__).'/frameon/');
	define('APP', dirname(__FILE__).'/application/');

	define('CONFIG_FILE', 'config.php');

	include FRAMEON.'framework.php';

	include FRAMEON.'classes/adapter/db.php';

	App::get()->start();