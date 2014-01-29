<?php
function plugin_tokusub(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    $f_downloadTokusubInfo = function() { // {{{
        $client = HttpClient::factory();
        $resp = $client->setUri('http://www.subway.co.jp/campaign/tokusub/')->setMethod(Zend_Http_Client::GET)->request();
        if(!$resp->isSuccessful()) {
            return false;
        }
        $html = mb_convert_encoding($resp->getBody(), 'HTML-ENTITIES', 'UTF-8,CP932');
        $document = new DOMDocument();
        if(!@$document->loadHtml($html)) {
            return false;
        }
        $xpath = new DOMXpath($document);
        $ret = array();
        foreach($xpath->query('//div[@id="toku"]//ul/li') as $li) {
            foreach(preg_split('/[[:space:]]+/', $li->getAttribute('class')) as $c) {
                if(preg_match('/^menu(\d+)$/', $c, $match) &&
                   1 <= $match[1] && $match[1] <= 7)
                {
                    $wday = (int)$match[1] % 7;
                    $name = null;
                    foreach($xpath->query('.//a[@href]/img[@alt]', $li) as $img) {
                        $name = trim($img->getAttribute('alt'));
                        if($name != '') {
                            $ret[$wday] = $name;
                            break;
                        }
                    }
                }
            }
        }
        foreach(range(0, 6) as $i) {
            if(!isset($ret[$i])) {
                return false;
            }
        }
        ksort($ret);
        return $ret;
    }; // }}}

    $f_getTokusub = function() use ($f_downloadTokusubInfo) { // {{{
        $cache_path = Yii::getPathOfAlias('application.runtime') . '/tokusub.plugin.json';
        $data = null;
        if(@file_exists($cache_path) && is_readable($cache_path) && time() - filemtime($cache_path) <= 604800) {
            if(!$data = @Zend_Json::decode(file_get_contents($cache_path))) {
                $data = null;
            }
            if(!is_array($data) || count($data) != 7) {
                $data = null;
            }
        }
        if(!is_array($data)) {
            if($data = $f_downloadTokusubInfo()) {
                @file_put_contents($cache_path, Zend_Json::encode($data));
            }
        }
        return $data ? $data : false;
    }; // }}}

    if($data = $f_getTokusub()) {
        $wday_today     = (int)date('w', time());
        $wday_tomorrow  = ($wday_today + 1) % 7;
        if(isset($data[$wday_today]) && isset($data[$wday_tomorrow])) {
            return sprintf(
                '今日の得サブは「%s」、明日の得サブは「%s」',
                $data[$wday_today], $data[$wday_tomorrow]
            );
        }
    }
    return 'ヽ(ﾟ∀。)ノ';
}
