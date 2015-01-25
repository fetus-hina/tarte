<?php
function plugin_shindanmaker(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$status || !$status->user || count($params) < 1) {
        throw new Exception('precondition failed');
    }
    $screen_name = 
        preg_match('/\b[0-9a-z_]{3,15}\b/i', $status->parsed->text, $match)
            ? $match[0]
            : $status->user->screen_name;

    if(preg_match('/^a(\d+)$/', trim($params[0]), $match)) {
        $prefix = 'a/';
        $id = (int)$match[1];
    } else {
        $prefix = '';
        $id = (int)$params[0];
    }

    $client = HttpClient::factory();
    $resp = $client
        ->setUri(sprintf('http://shindanmaker.com/%s%d', $prefix, $id))
        ->setMethod(Zend_Http_Client::POST)
        ->setEncType(Zend_Http_Client::ENC_FORMDATA)
        ->setParameterPost(array('u' => trim($screen_name), 'from' => ''))
        ->request();
    if(!$resp->isSuccessful()) {
        return '診断メーカーとの通信に失敗';
    }

    $html = $resp->getBody();
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    if(!@$doc->loadHTML($html)) {
        return '診断メーカーのHTML解析に失敗';
    }
    $xpath = new DOMXpath($doc);
    $textarea = $xpath->query('//form[@id="forcopy"]/textarea[1]');
    if($textarea->length !== 1) {
        return '診断メーカーのHTMLからテキストの検索に失敗';
    }
    $text = $textarea->item(0)->textContent;
    $text = Normalizer::normalize($text, Normalizer::FORM_C);
    $text = preg_replace('/[[:space:]]+/', ' ', $text);
    $text = trim($text);
    return $text;
}
