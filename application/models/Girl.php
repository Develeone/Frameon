<?php
class Girl extends Registry {
	public $table = '';
	public $safe = array('id','name','content');

	public function __construct()
	{
		$this->table = App::get()->config->db->girls_table;
	}

	public function getById ($id) {
		return DB::select()
			->from($this->table)
			->where('id', $id)
			->one();
	}
}