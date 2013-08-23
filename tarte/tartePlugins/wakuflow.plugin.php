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

        $commands = array();
        if(!preg_match_all('/新枠|ろまのふ|モノクロ|白黒|セピア|(?:2|二)値化|(?:8|八)色|中破|大破/u', $status->parsed->text, $matches, PREG_SET_ORDER)) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            return '(わーくフローの解析に失敗)';
        }
        foreach($matches as $match) {
            switch($match[0]) {
            case '新枠':
                $commands[] = 'waku_v2 ' . $status->user->screen_name;
                break;
            case 'ろまのふ':
                $commands[] = 'romanov';
                break;
            case 'モノクロ':
            case '白黒':
                $commands[] = 'grayscale';
                break;
            case 'セピア':
                $commands[] = 'sepia';
                break;
            case '二値化':
            case '2値化':
                $commands[] = 'binarize';
                break;
            case '八色':
            case '8色':
                $commands[] = '8colors';
                break;
            case '中破':
                $commands[] = 'kankore_half_damage';
                break;
            case '大破':
                $commands[] = 'kankore_badly_damage';
                break;
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
