<?php
class HttpClient {
    static public function factory() {
        return new Zend_Http_Client(
            null,
            array(
                'maxredirects' => 0,
                'useragent' => self::getUserAgent(),
                'timeout' => 30,
                'keepalive' => false,
                'storeresponse' => false,
            )
        );
    }

    static public function getUserAgent() {
        return sprintf(
            '%s (%s) %s/%s %s/%s',
            'Tarte', 'https://twitter.com/fetus_hina',
            'Yii', Yii::getVersion(),
            'ZendFramework', Zend_Version::VERSION
        );
    }
}
