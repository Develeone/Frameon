<?php
class GirlPageController extends Controller {
	function actionRead ($id = '0') {
		$girls = new Girls();
		$girl = $girls->getById($id);
		$this->render('read', array('girl' => $girl), true);
	}
}