<?php

// Класс организует управление всеми синглтонами приложения

abstract class Singleton {
	private static $_allInstances = array(); 	// все экзмепляры всех классов, наследующих класс Singleton

	// Получение экземпляра класса $className
	public static function getInstance ($className = false) {
		$className = ($className === false) ? get_called_class() : $className;

		if (class_exists($className)) {
			if (!isset(self::$_allInstances[$className])) // Если только создаем экземпляр
				self::$_allInstances[$className] = new $className(); // Записываем в массив имеющихся

			$instance = self::$_allInstances[$className]; // Выдаем экземпляр

			return $instance;
		} else
			throw new Except('Class "'.$className.'" extends Singleton not found!');
	}

	// Удобная обертка для получения экземпляра
	public static function get($className = false) {
		return self::getInstance($className);
	}

	final private function __clone () {}

	private function __construct () {}
}