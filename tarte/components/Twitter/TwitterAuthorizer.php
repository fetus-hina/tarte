<?php
class TwitterAuthorizer extends CComponent {
    private $consumer;
    private $oauth_token, $oauth_token_secret;

    public function init() {
        $this->consumer = Yii::app()->oauth->consumer;
    }

    public function startAuthorization() {
        $uri = Zend_Uri::factory('https://api.twitter.com/oauth/request_token');
        $authorization = OAuthSignature::buildAuthorization(
            'POST', $uri, '', '',
            $this->consumer->key, $this->consumer->secret,
            '', '', 'oob'
        );
        $client = HttpClient::factory();
        $client->setUri($uri);
        $client->setMethod('POST');
        $client->setHeaders('Authorization', $authorization);
        $resp = $client->request();
        if(!$resp->isSuccessful()) {
            throw new CException('request_token failed: ' . $resp->getBody());
        }
        parse_str($resp->getBody(), $next_params);
        $this->oauth_token          = $next_params['oauth_token'];
        $this->oauth_token_secret   = $next_params['oauth_token_secret'];
        return
            'https://api.twitter.com/oauth/authenticate?' .
            http_build_query(array('oauth_token' => $this->oauth_token), '', '&');
    }

    public function getUserToken($pin_code) {
        $uri = Zend_Uri::factory('https://api.twitter.com/oauth/access_token');
        $parameters = array('oauth_verifier' => $pin_code);
        $authorization = OAuthSignature::buildAuthorization(
            'POST', $uri, http_build_query($parameters, '', '&'), '',
            $this->consumer->key, $this->consumer->secret,
            $this->oauth_token, $this->oauth_token_secret
        );
        $client = HttpClient::factory();
        $client->setUri($uri);
        $client->setMethod('POST');
        $client->setParameterPost($parameters);
        $client->setHeaders('Authorization', $authorization);
        $resp = $client->request();
        if(!$resp->isSuccessful()) {
            throw new CException('access_token failed: ' . $resp->getBody());
        }
        parse_str($resp->getBody(), $next_params);
        return $next_params;
    }
}
