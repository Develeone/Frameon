<?php
class App extends Singleton {

    public $config = null; // Общая конфигурация сайта
    public $uri = null;

	public function __construct () {
        // Переопределяем вывод ошибок
		$this->initSystemHandlers();
	}

	// Запускаем приложение
    function start () {
		DB::connect(Config::get()->db);

		// Определяем роут, по которому выполнен запрос
		$this->uri = new Registry(Router::get()->parse($_SERVER['REQUEST_URI']));

        // Используем App как фабрику для размножения контроллеров
        // $this->uri->controller   есть имя класса, который требуется вызвать
        // $this->uri->action       есть имя метода, вызываемого в контроллере
        // TODO: Перепилить array($this->uri->id)
		$controller = App::get($this->uri->controller);
		$controller->__call('action'.$this->uri->action, array($this->uri->id));
    }


	// Переопределяем функции, вызываемые при выбросе Exception'ов и Error'ов
	protected function initSystemHandlers () {
		set_exception_handler(array($this,'handleException'));
		set_error_handler(array($this,'handleError'),error_reporting());
	}

	public function handleError ($code,$message,$file,$line) {
		if($code & error_reporting()) {
			restore_error_handler();
			restore_exception_handler();
			try{
				$this->displayError($code,$message,$file,$line);
			} catch(Exception $e) {
				$this->displayException($e);
			}
		}
	}

	public function handleException ($exception) {
		restore_error_handler();
		restore_exception_handler();
		$this->displayException($exception);
	}

	public function displayError ($code,$message,$file,$line) {
		echo "<h1>PHP Error [$code]</h1>\n";
		echo "<p>$message ($file:$line)</p>\n";
		echo '<pre>';

		$trace = debug_backtrace();

		if (count($trace)>3)
			$trace = array_slice($trace,3);
		
		foreach ($trace as $i => $t) {
			if (!isset($t['file']))
				$t['file'] = 'unknown';
			if (!isset($t['line']))
				$t['line'] = 0;
			if (!isset($t['function']))
				$t['function'] = 'unknown';

			echo "#$i {$t['file']}({$t['line']}): ";

			if (isset($t['object']) && is_object($t['object']))
				echo get_class($t['object']).'->';

			echo "{$t['function']}()\n";
		}

		echo '</pre>';

		exit();
	}

	public function displayException ($exception) {
		echo '<h1>'.get_class($exception)."</h1>\n";
		echo '<p>'.$exception->getMessage().' ('.$exception->getFile().':'.$exception->getLine().')</p>';
		echo '<pre>'.$exception->getTraceAsString().'</pre>';
	}
}