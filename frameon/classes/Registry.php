<?php

// Делаем из массива подобие stdClass'а, чтоб потом обращаться к переменным этого класса
// как к переменным, а не элементам массива
class Registry {
	private $data = array();

	// Конструктор сразу забивает массив
    function __construct ($data = array()) {
	   $this->data = $data;
    }

	// При получении параметра по имени
	function __get ($name) {
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}

	// При утановке параметра по имени
	function __set ($name,$value) {
		$this->data[$name] = $value;
	}
}
