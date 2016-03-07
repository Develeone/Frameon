<?php

class SqlSelectBuilder {
	public $what = '';
	public $from = '';
	public $where = array();

	public function __construct () {
		$this->what = func_get_args();
		$this->what = $this->what[0];

		if ((count($this->what) == 0) || ((count($this->what) == 1) && ($this->what[0] == "*")))
			$this->what = "*";

		return $this;
	}

	// Для перегрузки методов используем их модификацию вызова
	public function __call($methodName, $arguments = array()) {
		$methodName = "_".$methodName;

		if (!method_exists($this, $methodName))
			throw new Except('Ошибка в контроллере '.get_called_class().'. Отсутствует метод '.$methodName.'!');
		else

		if ($methodName == "_where")
			$arguments = array($arguments[0], "=", $arguments[1]);

		return call_user_func_array(array($this, $methodName), $arguments);
    }

	public function from ($table_name) {
		$this->from = $table_name;
		return $this;
	}

	public function _where ($field, $compare, $value) {
		array_push(
			$this->where,
			array(
				"field" => $field,
				"compare" => $compare,
				"value" => $value
			)
		);
		
		return $this;
	}

	public function get () {
		return DB::runSqlSelectBuilder($this);
	}

	public function one () {
		$data = DB::runSqlSelectBuilder($this);
		return $data[0];
	}
}

/**
 * @author col.shrapnel@gmail.com
 * @link http://phpfaq.ru/safemysql
 * 
 * Safe and convenient way to handle SQL queries utilizing type-hinted placeholders.
 * 
 * Key features
 * - set of helper functions to get the desired result right out of query, like in PEAR::DB
 * - conditional query building using parse() method to build queries of whatever comlexity, 
 *   while keeping extra safety of placeholders
 * - type-hinted placeholders
 * 
 *  Type-hinted placeholders are great because 
 * - safe, as any other [properly implemented] placeholders
 * - no need for manual escaping or binding, makes the code extra DRY
 * - allows support for non-standard types such as identifier or array, which saves A LOT of pain in the back.
 * 
 * Supported placeholders at the moment are:
 * 
 * ?s ("string")  - strings (also DATE, FLOAT and DECIMAL)
 * ?i ("integer") - the name says it all 
 * ?n ("name")    - identifiers (table and field names) 
 * ?a ("array")   - complex placeholder for IN() operator  (substituted with string of 'a','b','c' format, without parentesis)
 * ?u ("update")  - complex placeholder for SET operator (substituted with string of `field`='value',`field`='value' format)
 * and
 * ?p ("parsed") - special type placeholder, for inserting already parsed statements without any processing, to avoid double parsing.
 * 
 * Connection:
 *
 * $db = new SafeMySQL(); // with default settings
 * 
 * $opts = array(
 *		'user'    => 'user',
 *		'pass'    => 'pass',
 *		'db'      => 'db',
 *		'charset' => 'latin1'
 * );
 * $db = new SafeMySQL($opts); // with some of the default settings overwritten
 * 
 * Alternatively, you can just pass an existing mysqli instance that will be used to run queries 
 * instead of creating a new connection.
 * Excellent choice for migration!
 * 
 * $db = new SafeMySQL(['mysqli' => $mysqli]);
 * 
 * Some examples:
 * 
 * $name = $db->getOne('SELECT name FROM table WHERE id = ?i',$_GET['id']);
 * $data = $db->getInd('id','SELECT * FROM ?n WHERE id IN ?a','table', array(1,2));
 * $data = $db->getAll("SELECT * FROM ?n WHERE mod=?s LIMIT ?i",$table,$mod,$limit);
 *
 * $ids  = $db->getCol("SELECT id FROM tags WHERE tagname = ?s",$tag);
 * $data = $db->getAll("SELECT * FROM table WHERE category IN (?a)",$ids);
 * 
 * $data = array('offers_in' => $in, 'offers_out' => $out);
 * $sql  = "INSERT INTO stats SET pid=?i,dt=CURDATE(),?u ON DUPLICATE KEY UPDATE ?u";
 * $db->query($sql,$pid,$data,$data);
 * 
 * if ($var === NULL) {
 *     $sqlpart = "field is NULL";
 * } else {
 *     $sqlpart = $db->parse("field = ?s", $var);
 * }
 * $data = $db->getAll("SELECT * FROM table WHERE ?p", $bar, $sqlpart);
 * 
 */
class DB
{
	private static $conn;
	private static $stats;
	private static $emode;
	private static $exname;
	private static $pfx;

	const RESULT_ASSOC = MYSQLI_ASSOC;
	const RESULT_NUM   = MYSQLI_NUM;

	private static $_instance = null;

	private function __construct () {}
	protected function __clone() {}

	static public function getInstance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public static function connect($config)
	{
		self::$emode  = $config->errmode;
		self::$exname = $opt['exception'];

		@self::$conn = mysqli_connect($config->host, $config->user, $config->password, $config->dbname);
		if (!self::$conn)
		{
			self::error(mysqli_connect_errno()." ".mysqli_connect_error());
		}

		self::query("SET NAMES '".$config->charset."'");
		self::query("SET CHARSET '".$config->charset."'");
		self::query("SET CHARACTER SET '".$config->charset."'");
		self::query("SET SESSION collation_connection = '".$config->charset."_general_ci'");

		unset($config); // I am paranoid
	}

	public function runSqlSelectBuilder ($SqlBuilder) {
		return self::_select($SqlBuilder->what, $SqlBuilder->from, $SqlBuilder->where);
	}

	public function select () {
		return new SqlSelectBuilder(func_get_args());
	}

	public function _select ($what, $from, $where = null) {
		$what_placeholder = $what === "*" ? '?p' : '?f';

		if ($where == null)
			return self::getAll('SELECT '.$what_placeholder.' FROM ?n', $what, $from);
		else
			return self::getAll('SELECT '.$what_placeholder.' FROM ?n WHERE ?w', $what, $from, $where);
	}

	/**
	 * Conventional function to run a query with placeholders. A mysqli_query wrapper with placeholders support
	 * 
	 * Examples:
	 * $db->query("DELETE FROM table WHERE id=?i", $id);
	 *
	 * @param string $query - an SQL query with placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the query
	 * @return resource|FALSE whatever mysqli_query returns
	 */
	public function query()
	{	
		return self::rawQuery(self::prepareQuery(func_get_args()));
	}

	/**
	 * Conventional function to fetch single row. 
	 * 
	 * @param resource $result - myqli result
	 * @param int $mode - optional fetch mode, RESULT_ASSOC|RESULT_NUM, default RESULT_ASSOC
	 * @return array|FALSE whatever mysqli_fetch_array returns
	 */
	public function fetch($result,$mode=self::RESULT_ASSOC)
	{
		return mysqli_fetch_array($result, $mode);
	}

	/**
	 * Conventional function to get number of affected rows. 
	 * 
	 * @return int whatever mysqli_affected_rows returns
	 */
	public function affectedRows()
	{
		return mysqli_affected_rows (self::$conn);
	}

	/**
	 * Conventional function to get last insert id. 
	 * 
	 * @return int whatever mysqli_insert_id returns
	 */
	public function insertId()
	{
		return mysqli_insert_id(self::$conn);
	}

	/**
	 * Conventional function to get number of rows in the resultset. 
	 * 
	 * @param resource $result - myqli result
	 * @return int whatever mysqli_num_rows returns
	 */
	public function numRows($result)
	{
		return mysqli_num_rows($result);
	}

	/**
	 * Conventional function to free the resultset. 
	 */
	public function free($result)
	{
		mysqli_free_result($result);
	}

	/**
	 * Helper function to get scalar value right out of query and optional arguments
	 * 
	 * Examples:
	 * $name = $db->getOne("SELECT name FROM table WHERE id=1");
	 * $name = $db->getOne("SELECT name FROM table WHERE id=?i", $id);
	 *
	 * @param string $query - an SQL query with placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the query
	 * @return string|FALSE either first column of the first row of resultset or FALSE if none found
	 */
	public function getOne()
	{
		$query = self::prepareQuery(func_get_args());
		if ($res = self::rawQuery($query))
		{
			$row = self::fetch($res);
			if (is_array($row)) {
				return reset($row);
			}
			self::free($res);
		}
		return FALSE;
	}

	/**
	 * Helper function to get single row right out of query and optional arguments
	 * 
	 * Examples:
	 * $data = $db->getRow("SELECT * FROM table WHERE id=1");
	 * $data = $db->getOne("SELECT * FROM table WHERE id=?i", $id);
	 *
	 * @param string $query - an SQL query with placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the query
	 * @return array|FALSE either associative array contains first row of resultset or FALSE if none found
	 */
	public function getRow()
	{
		$query = self::prepareQuery(func_get_args());
		if ($res = self::rawQuery($query)) {
			$ret = self::fetch($res);
			self::free($res);
			return $ret;
		}
		return FALSE;
	}

	/**
	 * Helper function to get single column right out of query and optional arguments
	 * 
	 * Examples:
	 * $ids = $db->getCol("SELECT id FROM table WHERE cat=1");
	 * $ids = $db->getCol("SELECT id FROM tags WHERE tagname = ?s", $tag);
	 *
	 * @param string $query - an SQL query with placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the query
	 * @return array|FALSE either enumerated array of first fields of all rows of resultset or FALSE if none found
	 */
	public function getCol()
	{
		$ret   = array();
		$query = self::prepareQuery(func_get_args());
		if ( $res = self::rawQuery($query) )
		{
			while($row = self::fetch($res))
			{
				$ret[] = reset($row);
			}
			self::free($res);
		}
		return $ret;
	}

	/**
	 * Helper function to get all the rows of resultset right out of query and optional arguments
	 * 
	 * Examples:
	 * $data = $db->getAll("SELECT * FROM table");
	 * $data = $db->getAll("SELECT * FROM table LIMIT ?i,?i", $start, $rows);
	 *
	 * @param string $query - an SQL query with placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the query
	 * @return array enumerated 2d array contains the resultset. Empty if no rows found. 
	 */
	public function getAll()
	{
		$ret   = array();

		$query = self::prepareQuery(func_get_args());

		if ( $res = self::rawQuery($query) )
		{
			while($row = self::fetch($res))
			{
				$ret[] = new Registry($row);
			}
			self::free($res);
		}
		return $ret;
	}

	/**
	 * Helper function to get all the rows of resultset into indexed array right out of query and optional arguments
	 * 
	 * Examples:
	 * $data = $db->getInd("id", "SELECT * FROM table");
	 * $data = $db->getInd("id", "SELECT * FROM table LIMIT ?i,?i", $start, $rows);
	 *
	 * @param string $index - name of the field which value is used to index resulting array
	 * @param string $query - an SQL query with placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the query
	 * @return array - associative 2d array contains the resultset. Empty if no rows found. 
	 */
	public function getInd()
	{
		$args  = func_get_args();
		$index = array_shift($args);
		$query = self::prepareQuery($args);
		$ret = array();
		if ( $res = self::rawQuery($query) )
		{
			while($row = self::fetch($res))
			{
				$ret[$row[$index]] = $row;
			}
			self::free($res);
		}
		return $ret;
	}

	/**
	 * Helper function to get a dictionary-style array right out of query and optional arguments
	 * 
	 * Examples:
	 * $data = $db->getIndCol("name", "SELECT name, id FROM cities");
	 *
	 * @param string $index - name of the field which value is used to index resulting array
	 * @param string $query - an SQL query with placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the query
	 * @return array - associative array contains key=value pairs out of resultset. Empty if no rows found. 
	 */
	public function getIndCol()
	{
		$args  = func_get_args();
		$index = array_shift($args);
		$query = self::prepareQuery($args);
		$ret = array();
		if ( $res = self::rawQuery($query) )
		{
			while($row = self::fetch($res))
			{
				$key = $row[$index];
				unset($row[$index]);
				$ret[$key] = reset($row);
			}
			self::free($res);
		}
		return $ret;
	}

	/**
	 * Function to parse placeholders either in the full query or a query part
	 * unlike native prepared statements, allows ANY query part to be parsed
	 * 
	 * useful for debug
	 * and EXTREMELY useful for conditional query building
	 * like adding various query parts using loops, conditions, etc.
	 * already parsed parts have to be added via ?p placeholder
	 * 
	 * Examples:
	 * $query = $db->parse("SELECT * FROM table WHERE foo=?s AND bar=?s", $foo, $bar);
	 * echo $query;
	 * 
	 * if ($foo) {
	 *     $qpart = $db->parse(" AND foo=?s", $foo);
	 * }
	 * $data = $db->getAll("SELECT * FROM table WHERE bar=?s ?p", $bar, $qpart);
	 *
	 * @param string $query - whatever expression contains placeholders
	 * @param mixed  $arg,... unlimited number of arguments to match placeholders in the expression
	 * @return string - initial expression with placeholders substituted with data. 
	 */
	public function parse()
	{
		return self::prepareQuery(func_get_args());
	}

	/**
	 * function to implement whitelisting feature
	 * sometimes we can't allow a non-validated user-supplied data to the query even through placeholder
	 * especially if it comes down to SQL OPERATORS
	 * 
	 * Example:
	 *
	 * $order = $db->whiteList($_GET['order'], array('name','price'));
	 * $dir   = $db->whiteList($_GET['dir'],   array('ASC','DESC'));
	 * if (!$order || !dir) {
	 *     throw new http404(); //non-expected values should cause 404 or similar response
	 * }
	 * $sql  = "SELECT * FROM table ORDER BY ?p ?p LIMIT ?i,?i"
	 * $data = $db->getArr($sql, $order, $dir, $start, $per_page);
	 * 
	 * @param string $iinput   - field name to test
	 * @param  array  $allowed - an array with allowed variants
	 * @param  string $default - optional variable to set if no match found. Default to false.
	 * @return string|FALSE    - either sanitized value or FALSE
	 */
	public function whiteList($input,$allowed,$default=FALSE)
	{
		$found = array_search($input,$allowed);
		return ($found === FALSE) ? $default : $allowed[$found];
	}

	/**
	 * function to filter out arrays, for the whitelisting purposes
	 * useful to pass entire superglobal to the INSERT or UPDATE query
	 * OUGHT to be used for this purpose, 
	 * as there could be fields to which user should have no access to.
	 * 
	 * Example:
	 * $allowed = array('title','url','body','rating','term','type');
	 * $data    = $db->filterArray($_POST,$allowed);
	 * $sql     = "INSERT INTO ?n SET ?u";
	 * $db->query($sql,$table,$data);
	 * 
	 * @param  array $input   - source array
	 * @param  array $allowed - an array with allowed field names
	 * @return array filtered out source array
	 */
	public function filterArray($input,$allowed)
	{
		foreach(array_keys($input) as $key )
		{
			if ( !in_array($key,$allowed) )
			{
				unset($input[$key]);
			}
		}
		return $input;
	}

	/**
	 * Function to get last executed query. 
	 * 
	 * @return string|NULL either last executed query or NULL if were none
	 */
	public function lastQuery()
	{
		$last = end(self::$stats);
		return $last['query'];
	}

	/**
	 * Function to get all query statistics. 
	 * 
	 * @return array contains all executed queries with timings and errors
	 */
	public function getStats()
	{
		return self::$stats;
	}

	/**
	 * private static function which actually runs a query against Mysql server.
	 * also logs some stats like profiling info and error message
	 * 
	 * @param string $query - a regular SQL query
	 * @return mysqli result resource or FALSE on error
	 */
	private static function rawQuery($query)
	{
		self::$pfx = Config::get()->db->dbprefix;

		$query = str_ireplace('#__', self::$pfx, $query);

		//echo($query."<br>");

		$start = microtime(TRUE);
		$res   = mysqli_query(self::$conn, $query);
		$timer = microtime(TRUE) - $start;
		self::$stats[] = array(
			'query' => $query,
			'start' => $start,
			'timer' => $timer,
		);
		if (!$res)
		{
			$error = mysqli_error(self::$conn);
			
			end(self::$stats);
			$key = key(self::$stats);
			self::$stats[$key]['error'] = $error;
			self::cutStats();
			
			self::error("$error. Full query: [$query]");
		}
		self::cutStats();
		return $res;
	}

	private static function prepareQuery($args)
	{
		$query = '';
		$raw   = array_shift($args);
		$array = preg_split('~(\?[nfwsiuap])~u',$raw,null,PREG_SPLIT_DELIM_CAPTURE);
		$anum  = count($args);
		$pnum  = floor(count($array) / 2);
		if ( $pnum != $anum )
		{
			self::error("Number of args ($anum) doesn't match number of placeholders ($pnum) in [$raw]");
		}
		foreach ($array as $i => $part)
		{
			if ( ($i % 2) == 0 )
			{
				$query .= $part;
				continue;
			}
			$value = array_shift($args);
			switch ($part)
			{
				case '?n':
					$part = self::escapeIdent($value);
					break;
				case '?f':
					$part = self::createIdentArr($value);
					break;
				case '?w':
					$part = self::createWhere($value);
					break;
				case '?s':
					$part = self::escapeString($value);
					break;
				case '?i':
					$part = self::escapeInt($value);
					break;
				case '?a':
					$part = self::createIN($value);
					break;
				case '?u':
					$part = self::createSET($value);
					break;
				case '?p':
					$part = $value;
					break;
			}
			$query .= $part;
		}
		return $query;
	}

	private static function escapeInt($value)
	{
		if ($value === NULL)
		{
			return 'NULL';
		}
		if(!is_numeric($value))
		{
			self::error("Integer (?i) placeholder expects numeric value, ".gettype($value)." given");
			return FALSE;
		}
		if (is_float($value))
		{
			$value = number_format($value, 0, '.', ''); // may lose precision on big numbers
		} 
		return $value;
	}

	private static function escapeString($value)
	{
		if ($value === NULL)
		{
			return 'NULL';
		}
		return	"'".mysqli_real_escape_string(self::$conn, $value)."'";
	}

	private static function escapeIdent($value)
	{
		if ($value)
		{
			return "`".str_replace("`","``",$value)."`";
		} else {
			self::error("Empty value for identifier (?n) placeholder");
		}
	}

	private static function createIN($data)
	{
		if (!is_array($data))
		{
			self::error("Value for IN (?a) placeholder should be array");
			return;
		}
		if (!$data)
		{
			return 'NULL';
		}
		$query = $comma = '';
		foreach ($data as $value)
		{
			$query .= $comma.self::escapeString($value);
			$comma  = ",";
		}
		return $query;
	}

	private static function createIdentArr($data)
	{
		if (!is_array($data))
		{
			self::error("Value for IN (?f) placeholder should be array");
			return;
		}
		if (!$data)
		{
			return 'NULL';
		}
		$query = $comma = '';
		foreach ($data as $value)
		{
			$query .= $comma.self::escapeIdent($value);
			$comma  = ",";
		}
		return $query;
	}

	private static function createWhere($data)
	{
		if (!is_array($data))
		{
			self::error("Value for IN (?w) placeholder should be array");
			return;
		}
		if (!$data)
		{
			return 'NULL';
		}
		$query = $comma = '';
		foreach ($data as $value)
		{
			$query .= $comma.self::escapeIdent($value["field"]);
			$query .= $value["compare"];
			$query .= self::escapeString($value["value"]);
			$comma  = " AND ";
		}
		return $query;
	}

	private static function createSET($data)
	{
		if (!is_array($data))
		{
			self::error("SET (?u) placeholder expects array, ".gettype($data)." given");
			return;
		}
		if (!$data)
		{
			self::error("Empty array for SET (?u) placeholder");
			return;
		}
		$query = $comma = '';
		foreach ($data as $key => $value)
		{
			$query .= $comma.self::escapeIdent($key).'='.self::escapeString($value);
			$comma  = ",";
		}
		return $query;
	}

	private static function error($err)
	{
		$err  = __CLASS__.": ".$err;
		$err .= ". Error initiated in ".self::caller().", thrown";
		echo($err." ".E_USER_ERROR);

		$langcharset = 'utf-8';
		echo "<HTML>\n";
		echo "<HEAD>\n";
		echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=".$langcharset."\">\n";
		echo "<TITLE>MySQL Debugging</TITLE>\n";
		echo "</HEAD>\n";
		echo "<div style=\"border:1px dotted #000000; font-size:11px; font-family:tahoma,verdana,arial; background-color:#f3f3f3; color:#A73C3C; margin:5px; padding:5px;\">";
		echo "<b><font style=\"color:#666666;\">MySQL Debugging</font></b><br /><br />";
		echo "<li><b>SQL.q :</b> <font style=\"color:#666666;\">".self::$sql."</font></li>";
		echo "<li><b>MySQL.e :</b> <font style=\"color:#666666;\">".mysqli_error(self::$conn)."</font></li>";
		echo "<li><b>MySQL.e.№ :</b> <font style=\"color:#666666;\">".mysqli_errno(self::$conn)."</font></li>";
		echo "<li><b>PHP.v :</b> <font style=\"color:#666666;\">".phpversion()."\n</font></li>";
		echo "<li><b>Data :</b> <font style=\"color:#666666;\">".date("d.m.Y H:i")."\n</font></li>";
		echo "<li><b>Script :</b> <font style=\"color:#666666;\">".getenv("REQUEST_URI")."</font></li>";
		echo "<li><b>Refer :</b> <font style=\"color:#666666;\">".getenv("HTTP_REFERER")."</li></div>";
		echo "</BODY>\n";
		echo "</HTML>";
		exit();
	}

	private static function caller()
	{
		$trace  = debug_backtrace();
		$caller = '';
		foreach ($trace as $t)
		{
			if ( isset($t['class']) && $t['class'] == __CLASS__ )
			{
				$caller = $t['file']." on line ".$t['line'];
			} else {
				break;
			}
		}
		return $caller;
	}

	/**
	 * On a long run we can eat up too much memory with mere statsistics
	 * Let's keep it at reasonable size, leaving only last 100 entries.
	 */
	private static function cutStats()
	{
		if ( count(self::$stats) > 100 )
		{
			reset(self::$stats);
			$first = key(self::$stats);
			unset(self::$stats[$first]);
		}
	}
}

/**
 @name Database Wrapper for mysql
 @author Chupurnov Valeriy
 
class db {
	private $connect_id = null;
	private $resource_id = null;
	private $sql = null;
	private $pfx = '';
	public $debug = true;
	
	function connect ($config) {
		self::$connect_id = mysql_connect($config->host, $config->user, $config->password);

		if (!self::$connect_id) {
			self::$error();
			return false;
		}

		if (mysql_select_db($config->dbname, self::$connect_id)) {
			if (!isset($config->charset)) {
				$config->charset = 'utf8';
			}

			self::$query("SET NAMES '".$config->charset."'")->
				query("SET CHARSET '".$config->charset."'")->
				query("SET CHARACTER SET '".$config->charset."'")->
				query("SET SESSION collation_connection = '".$config->charset."_general_ci'");
		}

		self::$pfx = $config->dbprefix;
	}

	function query ($sql) {
		self::$sql = str_ireplace('#__', self::$pfx, $sql);
		self::$resource_id = mysql_query(self::$sql, self::$connect_id);
		if (self::$debug and !self::$resource_id) {
			self::$error();
		}
		return $this;
	}

	function select (fields) {
		
	}
	
	
	
	
	
	
	
	
	function row($field = false){
		if (self::$resource_id and $row = mysql_fetch_object(self::$resource_id)) {
			return $field ? $row[$field] : $row;
		}
		return null;
	}
	function item($table, $where = '1', $fields = '*', $field = false){
		$item = self::$query('select '.$fields.' from '.$table.' where '.$where)->row($field);
		return $item; 
	}
	function items($table, $where = '1', $fields = '*', $field = false, $key = false){
		$items = self::$query('select '.$fields.' from '.$table.' where '.$where)->rows($field, $key);
		return $items; 
	}
	
	function rows($field = false, $key = false) {
		$rows = array();
		while($row = self::$row($field)) {
			if (!$key) {
				$rows[] = $row;
			} else {
				$rows[$row[$key]] = $row;
			}
		}
		return $rows;
	}
	function exists($table, $id, $field = 'id') {
		$pid = self::$query('select '.$field.' from '.$table.' where '.$field.'='.self::$__($id).' limit 1')->row($field);
		return $pid;
	}
	function cnt() {
		return mysql_affected_rows(self::$connect_id);
	}
	private static function _arrayKeysToSet($values){
		$ret='';
		if (is_array($values) or is_object($values)){
			foreach($values as $key=>$value){
			  if(!empty($ret))$ret.=',';
			  if (!is_numeric($key)) {
				$ret.="`$key`=".self::$__($value);
			  } else {
				$ret.=$value;
			  }
			}
		} else {
			$ret=$values;
		}
		return $ret;
	}
	function insert($table, $values){
		$ret = self::$_arrayKeysToSet($values);
		return self::$query('insert into '.$table.' set '.$ret);
	}
	function id(){
		return mysql_insert_id(self::$connect_id);
	}
	public function update( $table, $values, $where=1 ){
		$ret = self::$_arrayKeysToSet($values);
		return self::$query('update '.$table.' set '.$ret.' where '.$where);
	}
	public function delete($table, $where){
		return self::$query('delete from '.$table.' where '.$where);
	}
	function _($value) {
		return mysql_real_escape_string($value, self::$connect_id);
	}
	function __($value) {
		return '"'.self::$_($value).'"';
	}

	public function error(){
		$langcharset = 'utf-8';
		echo "<HTML>\n";
		echo "<HEAD>\n";
		echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=".$langcharset."\">\n";
		echo "<TITLE>MySQL Debugging</TITLE>\n";
		echo "</HEAD>\n";
		echo "<div style=\"border:1px dotted #000000; font-size:11px; font-family:tahoma,verdana,arial; background-color:#f3f3f3; color:#A73C3C; margin:5px; padding:5px;\">";
		echo "<b><font style=\"color:#666666;\">MySQL Debugging</font></b><br /><br />";
		echo "<li><b>SQL.q :</b> <font style=\"color:#666666;\">".self::$sql."</font></li>";
		echo "<li><b>MySQL.e :</b> <font style=\"color:#666666;\">".mysql_error(self::$connect_id)."</font></li>";
		echo "<li><b>MySQL.e.№ :</b> <font style=\"color:#666666;\">".mysql_errno(self::$connect_id)."</font></li>";
		echo "<li><b>PHP.v :</b> <font style=\"color:#666666;\">".phpversion()."\n</font></li>";
		echo "<li><b>Data :</b> <font style=\"color:#666666;\">".date("d.m.Y H:i")."\n</font></li>";
		echo "<li><b>Script :</b> <font style=\"color:#666666;\">".getenv("REQUEST_URI")."</font></li>";
		echo "<li><b>Refer :</b> <font style=\"color:#666666;\">".getenv("HTTP_REFERER")."</li></div>";
		echo "</BODY>\n";
		echo "</HTML>";
		exit();
	}
}*/

