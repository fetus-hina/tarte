<?php
class OAuthSignature {
    // 3.6.
    // rawurlencode() でも良いが、rawurlencode() は upper-case を保証していない気がする
    // OAuth では SHA-1 に通す関係で upper-case 必須
    static public function percentEncoding($text) {
        return preg_replace_callback(
            '/[^[:alnum:]\-._~]/',
            function (array $match) { return sprintf('%%%02X', ord($match[0])); },
            (string)$text
        );
    }

    // 3.4.1.1
    static private function signatureBaseString(
        $http_method,
        $base_string_uri,
        $normalized_parameters)
    {
        return implode(
            '&',
            array(
                self::percentEncoding(strtoupper($http_method)),
                self::percentEncoding($base_string_uri),
                self::percentEncoding($normalized_parameters),
            )
        );
    }

    // 3.4.2.1
    static private function formatBaseStringUri(Zend_Uri_Http $uri) {
        $scheme = strtolower(trim($uri->getScheme()));
        $host   = strtolower(trim($uri->getHost()));
        $port   = (int)$uri->getPort();
        $path   = trim($uri->getPath());
        $result = $scheme . '://' . $host;
        if($port > 0) {
            if(($scheme === 'http'  && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
                $result .= ':' . (string)$port;
            }
        }
        $result .= ($path === '') ? '/' : $path;
        return $result;
    }

    // 3.4.1.3.1. Parameter Sources
    static private function parameterSources(Zend_Uri_Http $uri, array $oauth_params, $http_content) {
        $parameters = array();

        // URL クエリのコピー
        if(($query = $uri->getQuery()) != '') {
            foreach(explode('&', $query) as $pair_) {
                if($pair = explode('=', $pair_)) {
                    $key = urldecode($pair[0]);
                    $val = isset($pair[1]) ? urldecode($pair[1]) : null;
                    if(!isset($parameters[$key])) {
                        $parameters[$key] = array();
                    }
                    $parameters[$key][] = $val;
                }
            }
        }

        // OAuth パラメータのコピー
        foreach($oauth_params as $key => $value) {
            if($key !== 'realm' && $key !== 'oauth_signature') {
                if(!isset($parameters[$key])) {
                    $parameters[$key] = array();
                }
                $parameters[$key][] = $value;
            }
        }

        // リクエストボディのコピー
        if($http_content != '') {
            foreach(explode('&', $http_content) as $pair_) {
                if($pair = explode('=', $pair_)) {
                    $key = urldecode($pair[0]);
                    $val = isset($pair[1]) ? urldecode($pair[1]) : null;
                    if(!isset($parameters[$key])) {
                        $parameters[$key] = array();
                    }
                    $parameters[$key][] = $val;
                }
            }
        }
        return $parameters;
    }

    // 3.4.1.3.2. パラメータのノーマライゼーション
    //     $parameters =
    //         array(
    //         'key1'  => 'val1-1',
    //         'key2'  => array('val2-1', 'val2-2'));
    static private function parameterNormalization(array $parameters) {
        $result = array();
        uksort($parameters, function($a,$b){return strcmp(OAuthSignature::percentEncoding($a), OAuthSignature::percentEncoding($b));});
        foreach($parameters as $key => $value) {
            if(is_array($value)) {
                usort($value, function($a,$b){return strcmp(OAuthSignature::percentEncoding($a), OAuthSignature::percentEncoding($b));});
                foreach($value as $v) {
                    $result[] = sprintf('%s=%s', OAuthSignature::percentEncoding($key), OAuthSignature::percentEncoding($v));
                }
            } else {
                $result[] = sprintf('%s=%s', OAuthSignature::percentEncoding($key), OAuthSignature::percentEncoding($value));
            }
        }
        return implode('&', $result);
    }

    // 3.4.2 HMAC-SHA1
    static private function hmacSha1($text, $client_shared_key, $token_shared_key) {
        $key = self::percentEncoding($client_shared_key) . '&' . self::percentEncoding($token_shared_key);
        return Zend_Crypt_Hmac::compute($key, 'sha1', $text, Zend_Crypt_Hmac::BINARY);
    }

    static private function buildSignature(
        $http_method,           // "GET", "POST"
        Zend_Uri_Http $uri,     // URI
        $http_post_content,     // string
        array $oauth_params,
        $client_shared_key,
        $token_shared_key)
    {
        $parameter_normalized = self::parameterNormalization(
            self::parameterSources(
                $uri,
                $oauth_params,
                $http_post_content
            )
        );
        return base64_encode(
            self::hmacSha1(
                self::signatureBaseString(
                    $http_method,
                    self::formatBaseStringUri($uri),
                    $parameter_normalized
                ),
                $client_shared_key,
                $token_shared_key
            )
        );
    }

    static private function createNonce() {
        return
            strtr(
                rtrim(
                    base64_encode(
                        file_get_contents('/dev/urandom', false, null, 0, 24)
                    ),
                    '='
                ),
                '+/',
                '-_'
            );
    }

    static public function buildAuthorization(
        $http_method,
        Zend_Uri_Http $uri,     // URI
        $http_post_content,     // string
        $realm,
        $oauth_consumer_key,
        $oauth_consumer_secret,
        $oauth_token,
        $oauth_token_secret,
        $oauth_callback = null)
    {
        $oauth = array();
        if($oauth_consumer_key != '') {
            $oauth['oauth_consumer_key'] = $oauth_consumer_key;
        }
        if($oauth_token != '') {
            $oauth['oauth_token'] = $oauth_token;
        }
        if($oauth_callback != '') {
            $oauth['oauth_callback'] = $oauth_callback;
        }
        $oauth['oauth_signature_method'] = 'HMAC-SHA1';
        $oauth['oauth_timestamp'] = (string)time();
        $oauth['oauth_nonce'] = self::createNonce();
        $oauth['oauth_version'] = '1.0';

        $signature = self::buildSignature(
            $http_method,
            $uri,
            $http_post_content,
            $oauth,
            $oauth_consumer_secret,
            $oauth_token_secret
        );
        $tmp = array_merge(
            array('realm' => $realm),
            $oauth,
            array('oauth_signature' => $signature)
        );
        $result = array();
        foreach($tmp as $k => $v) {
            $result[] = sprintf('%s="%s"', self::percentEncoding($k), self::percentEncoding($v));
        }
        return 'OAuth ' . implode(',', $result);
    }
}
