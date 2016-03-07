<?php
class IndexController extends Controller {

	// Стандартный запрос на вывод
	function actionShow () {
        $girls = new Girls();

        $catalogue_items = $girls->getAll('id', 'name', 'age', 'price_for_hour', 'main_photo_url');

		$this->render(
			'index',
			array(
				'catalogue_items' => $catalogue_items
			)
		);
	}
}
