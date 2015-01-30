<?php
function plugin_yahooj_geo(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$status || !$status->user) {
         throw new CException('precondition failed');
    }
    if(!$geo = $status->geo) {
         return 'ツイートに位置情報がありません';
    }

    try {
        $lat = (float)$geo->getLatitude();
        $lon = (float)$geo->getLongitude();
        $ll_text = plugin_yahooj_weather__formatll(['北緯', '南緯'], $lat) . ' ' . plugin_yahooj_weather__formatll(['東経', '西経'], $lon);
        $place_text = plugin_yahooj_weather__placeinfo($lat, $lon);
        $altitude_text = plugin_yahooj_weather__altitude($lat, $lon);
        $text = $ll_text . '。' . $place_text . $altitude_text;
        if(mb_strlen($text, 'UTF-8') > 140 - 17) {
            $text = mb_substr($text, 0, 140 - 17 - 1, 'UTF-8') . '…';
        }
        return $text;
    } catch(Exception $e) {
        return '通信に失敗しました';
    }
}

function plugin_yahooj_weather__formatll(array $label, $value) {
    $label_index = $value < 0 ? 1 : 0;
    $value = abs($value);

    $deg = floor($value);
    $value = ($value - $deg) * 60;
    $min = floor($value);
    $value = ($value - $min) * 60;
    $sec = round($value);
    return sprintf('%s%d度%d分%d秒', $label[$label_index], $deg, $min, $sec);
}

function plugin_yahooj_weather__placeinfo($lat, $lon) {
    $url = 'http://placeinfo.olp.yahooapis.jp/V1/get?' . http_build_query(array(
            'appid' => Yii::app()->params['yahoo']['application_id'],
            'lat' => (string)$lat,
            'lon' => (string)$lon,
            'output' => 'json',
        ), '', '&'
    );

    $client = HttpClient::factory();
    $resp = $client
        ->setUri($url)
        ->setMethod(Zend_Http_Client::GET)
        ->request();
    if(!$resp->isSuccessful()) {
        throw new Exception('通信に失敗');
    }
    $json = @json_decode($resp->getBody(), true);
    if(!$json || isset($json['Error'])) {
        throw new Exception('通信に失敗');
    }
    return sprintf(
        '%sエリア、%s、住所は%s%s%s%s。',
        isset($json['ResultSet']['Area'][0]['Name']) ? $json['ResultSet']['Area'][0]['Name'] : '不明な',
	isset($json['ResultSet']['Result'][0]['Combined']) ? $json['ResultSet']['Result'][0]['Combined'] : '付近の建物等不明',
        isset($json['ResultSet']['Address'][0]) ? $json['ResultSet']['Address'][0] : '住所不明',
        isset($json['ResultSet']['Address'][0]) ? $json['ResultSet']['Address'][1] : '',
        isset($json['ResultSet']['Address'][0]) ? $json['ResultSet']['Address'][2] : '',
        isset($json['ResultSet']['Address'][0]) ? $json['ResultSet']['Address'][3] : ''
    );
}

function plugin_yahooj_weather__altitude($lat, $lon) {
    $url = 'http://alt.search.olp.yahooapis.jp/OpenLocalPlatform/V1/getAltitude?' . http_build_query(array(
            'appid' => Yii::app()->params['yahoo']['application_id'],
            'coordinates' => sprintf('%f,%f', $lon, $lat),
            'output' => 'json',
        ), '', '&'
    );

    $client = HttpClient::factory();
    $resp = $client
        ->setUri($url)
        ->setMethod(Zend_Http_Client::GET)
        ->request();
    if(!$resp->isSuccessful()) {
        throw new Exception('通信に失敗');
    }
    $json = @json_decode($resp->getBody(), true);
    if(!$json || isset($json['Error'])) {
        throw new Exception('通信に失敗');
    }
    return sprintf('標高は%.1fmです', $json['Feature'][0]['Property']['Altitude']);
}
