<?php
/**
 * Created by PhpStorm.
 * User: Ouden
 * Date: 05.03.2016
 * Time: 23:35
 */

class GirlsFilterController extends Controller {
    function actionFilter () {
        $girls = new Girls();
        return (print_r($girls->getByFilters(App::get()->uri->args)));
    }
}
