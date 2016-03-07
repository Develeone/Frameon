<?php
class Config extends Singleton{
	
	private $data = array();

    function __construct()
    {
        // Создаем конфигурационный реестр
        $default_config = include FRAMEON.CONFIG_FILE;
        $custom_config = include APP.CONFIG_FILE;
        $this->data = array_merge($default_config, $custom_config);
    }

    function associate ($group, &$array) {
		$this->data[$group] = $array;
	}
	
	function __get ($name){
		return isset ($this->data[$name]) ? $this->data[$name] : null;
	}
	
	function __set ($name, $value){
		$this->data[$name] = $value;
	}
}
