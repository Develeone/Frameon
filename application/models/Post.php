<?php
class Post extends ModelTable {
	static public $table = '#__posts';
	public $safe = array('id', 'name', 'content');

	public function beforeSave() {
		if (strlen($this->name) < 3) {
			$this->errors['name'] = 'Слишком короткий заголовок';
		}
		return parent::beforeSave();
	}
}