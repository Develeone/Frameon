<?php
class Girls extends Registry {
	public $table = '';
	public $safe = array('id','name','content');

	public function __construct()
	{
		$this->table = Config::get()->db->girls_table;
	}

	public function getById ($id) {
		return DB::select()
			->from($this->table)
			->where('id', $id)
			->one();
	}

    public function getAll () {
        $fields = func_get_args();
        return call_user_func_array(array("DB", "select"), $fields)
            ->from($this->table)
            ->get();
    }

	public function getByFilters($filters) {
		$selectBuilder = DB::select()
			->from($this->table);

        foreach ($filters as $key => $value)
            $selectBuilder = $selectBuilder->where($key, $value);

		return $selectBuilder->get();
	}
}