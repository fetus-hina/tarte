<?php
class ZendHelper extends CApplicationComponent {
    public function init() {
        self::setupAutoloader();
    }

    static private function setupAutoloader() {
        Yii::import('application.vendors.*');
        require_once('Zend/Loader/Autoloader.php');
        Yii::registerAutoloader(array('Zend_Loader_Autoloader', 'autoload'));
    }
}
