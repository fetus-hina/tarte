<?php
class Twitpic {
    const API_URL       = 'http://api.twitpic.com/2/upload.json';
    const API_REALM     = 'http://api.twitter.com/';
    const PROVIDER_URL  = 'https://api.twitter.com/1.1/account/verify_credentials.json';

    public function upload($api_key, $message, $media_file) {
        $resp = $this->request($api_key, $message, $media_file);
        if(!$resp->isSuccessful()) {
            return false;
        }
        if(!$json = Zend_Json::decode($resp->getBody())) {
            return false;
        }
        if(!isset($json['url'])) {
            return false;
        }
        return Zend_Uri::check($json['url']) ? $json['url'] : false;
    }

    private function request($api_key, $message, $media_file) {
        $headers = array(
            'X-Verify-Credentials-Authorization'    => $this->createProviderAuthToken(),
            'X-Auth-Service-Provider'               => self::PROVIDER_URL,
        );
        $parameters = array(
            'key'       => $api_key,
            'message'   => $message,
        );
        $client = HttpClient::factory();
        $client->setUri(self::API_URL);
        $client->setHeaders($headers);
        $client->setEncType(Zend_Http_Client::ENC_FORMDATA);
        $client->setParameterPost($parameters);
        $client->setFileUpload($media_file, 'media');
        return $client->request('POST');
    }

    private function createProviderAuthToken() {
        $oauth = Yii::app()->oauth;
        $oauth->user = BotConfig::getInstance()->oauth;
        return $oauth->getAuthorizationHeader('GET', Zend_Uri::factory(self::PROVIDER_URL), '', self::API_REALM);
    }
}
