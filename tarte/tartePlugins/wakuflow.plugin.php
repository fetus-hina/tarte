<?php
function plugin_wakuflow(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$status || !$status->user) {
        throw new CException('precondition failed');
    }
    if(!$config = BotConfig::getInstance()) {
        throw new CException('precondition failed');
    }

    $tmp_dir = Yii::getPathOfAlias('application.runtime');
    $tmp_in  = tempnam($tmp_dir, 'wakuflow-');
    $tmp_out = tempnam($tmp_dir, 'wakuflow-');

    try {
        // オリジナル画像の URL を取得
        // http://.../image12345_normal.png とか入っているはずで、_normal を消せばオリジナルになるはず。
        // 拡張子はどうもオリジナルを維持するらしい（=無いかもしれない）ので超適当正規表現。
        // 多分大丈夫だと思うんだけど…まあ嫌だねこういう適当な置換は。
        $image_url = preg_replace('/_normal\b/', '', $status->user->profile_image_url);

        // オリジナル画像を取得
        $client = HttpClient::factory();
        $resp = $client->setUri($image_url)->setMethod(Zend_Http_Client::GET)->request();
        if(!$resp->isSuccessful()) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            return '(プロフィール画像の取得に失敗)';
        }
        if(!@file_put_contents($tmp_in, $resp->getBody())) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            return '(プロフィール画像の保存に失敗)';
        }
        unset($resp);
        unset($client);

        // 凝ったことが必要ないシンプルなわーくフローと wakuflow コマンドの一覧
        $simple_wakuflow_map = array(
            '新枠'      => 'waku_v2 ' . $status->user->screen_name,
            'ろまのふ'  => 'romanov',
            'モノクロ'  => 'grayscale',
            '白黒'      => 'grayscale',
            'セピア'    => 'sepia',
            '二値化'    => 'binarize',
            '2値化'     => 'binarize',
            '八色'      => '8colors',
            '8色'       => '8colors',
            '色反転'    => 'negate',
            'ネガ'      => 'negate',
            'ぼかし'    => 'gaussian_blur',
            '上下反転'  => 'flip vertical',
            '左右反転'  => 'flip horizontal',
            '左回転'    => 'rotate 90',
            '半回転'    => 'rotate 180',
            '右回転'    => 'rotate 270',
            'シャープ'  => 'sharpen',
            '半額'      => 'half_price',
            '中破'      => 'kankore_half_damage',
            '大破'      => 'kankore_badly_damage',
        );

        // 正規表現生成のために長い順に並べる
        uksort(
            $simple_wakuflow_map,
            function ($a, $b) {
                $la = mb_strlen($a, 'UTF-8');
                $lb = mb_strlen($b, 'UTF-8');
                if($la != $lb) {
                    return $la < $lb ? 1 : -1;
                }
                return strnatcasecmp($a, $b);
            }
        );

        // シンプルなわーくフローのための正規表現(パーツ)
        $simple_wakuflow_regex = implode('|', array_map(function($a){return preg_quote($a, '/');}, array_keys($simple_wakuflow_map)));

        $commands = array();
        if(!preg_match_all('/' . $simple_wakuflow_regex . '/u', $status->parsed->text, $matches, PREG_SET_ORDER)) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            return '(わーくフローの解析に失敗)';
        }
        foreach($matches as $match) {
            $done = false;
            foreach($simple_wakuflow_map as $wakuflow_ja => $wakuflow_cmd) {
                if($match[0] === $wakuflow_ja) {
                    $commands[] = $wakuflow_cmd;
                    $done = true;
                    break;
                }
            }

            if(!$done) {
                //TODO: 凝ったことをするコマンドの処理
            }
        }
        $wakuflow = Yii::app()->wakuflow;
        if(!$wakuflow->proc($tmp_in, $tmp_out, implode("\n", $commands))) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            return '(わーくフローの処理に失敗)';
        }

        $twitpic = new Twitpic();
        if(!$url = $twitpic->upload(Yii::app()->params['twitpic'], '', $tmp_out)) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            return '(画像アップロードに失敗)';
        }
        @unlink($tmp_out);
        @unlink($tmp_in);
        return $url;
    } catch(Exception $e) {
        @unlink($tmp_out);
        @unlink($tmp_in);
        return '(わーくフローの処理に失敗)';
    }
}
