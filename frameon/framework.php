<?php

	// Если мы вдруг где-то использовали какой-то класс, который еще не был подключен,
	// 	то пробуем предварительно подключить его из имеющихся папочек.
	// Это чтоб лишний раз инклюды не писать, а использовать каждый класс везде где угодно.
	
	// Повторяющийся кусок кода в одну функцию залепить не получилось


	// Автозагрузка класса
	function class_autoload($class_name) {	
		$file = FRAMEON . 'classes/'.ucfirst($class_name).'.php';
		if (file_exists($file) == false)
			return false;
		require_once ($file);
		return true;
	}

	// Автозагрузка контроллера
	function controller_autoload($class_name) {
		$file = APP . 'controllers/'.ucfirst($class_name).'.php';
        if (file_exists($file) == false) {
            $file = APP . 'controllers/ajax/' . ucfirst($class_name) . '.php';
            if (file_exists($file) == false) {
                return false;
            }
        }
		require_once ($file);
		return true;
	}

	// Автозагрузка модели
	function model_autoload($class_name) {
		$file = APP . 'models/'.ucfirst($class_name).'.php';
		if (file_exists($file) == false)
			return false;
		require_once ($file);
		return true;
	}

	// Регистрируем наши три функции как автозагрузчики классов
	spl_autoload_register('class_autoload');
	spl_autoload_register('controller_autoload');
	spl_autoload_register('model_autoload');
