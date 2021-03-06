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

        $animal_text = function($text) {
            $animal_text = trim(mb_convert_kana($text, 'asKV', 'UTF-8'));
            $animal_text = preg_replace('/[\x00-\x20\x7f]+/', ' ', $animal_text);
            return preg_replace('/([[:space:]\'"\\\\])/', "\\\\$1", $animal_text);
        };

        // 凝ったことが必要ないシンプルなわーくフローと wakuflow コマンドの一覧
        $simple_wakuflow_map = array(
            '新枠'              => 'waku_v2 ' . $status->user->screen_name,
            'ろまのふ'          => 'romanov',
            'モノクロ'          => 'grayscale',
            '白黒'              => 'grayscale',
            'セピア'            => 'sepia',
            '二値化'            => 'binarize otsu none',
            '二値化A'           => 'binarize otsu none',
            '二値化B'           => 'binarize otsu floyd',
            '二値化C'           => 'binarize otsu atkinson',
            '二値化D'           => 'binarize otsu sierra3',
            '二値化E'           => 'binarize otsu sierra2',
            '二値化F'           => 'binarize otsu sierra-lite',
            '八色'              => '8colors otsu none',
            '八色A'             => '8colors otsu none',
            '八色B'             => '8colors otsu floyd',
            '八色C'             => '8colors otsu atkinson',
            '八色D'             => '8colors otsu sierra3',
            '八色E'             => '8colors otsu sierra2',
            '八色F'             => '8colors otsu sierra-lite',
            'ウェブセーフ'      => 'websafe atkinson',
            'ウェブセーフA'     => 'websafe none',
            'ウェブセーフB'     => 'websafe floyd',
            'ウェブセーフC'     => 'websafe atkinson',
            'ウェブセーフD'     => 'websafe sierra3',
            'ウェブセーフE'     => 'websafe sierra2',
            'ウェブセーフF'     => 'websafe sierra-lite',
            'ファミコン'        => 'famicom atkinson',
            'ファミコンA'       => 'famicom none',
            'ファミコンB'       => 'famicom floyd',
            'ファミコンC'       => 'famicom atkinson',
            'ファミコンD'       => 'famicom sierra3',
            'ファミコンE'       => 'famicom sierra2',
            'ファミコンF'       => 'famicom sierra-lite',
            'ゲームボーイ'      => 'gameboy scale atkinson',
            'ゲームボーイA'     => 'gameboy scale none',
            'ゲームボーイB'     => 'gameboy scale floyd',
            'ゲームボーイC'     => 'gameboy scale atkinson',
            'ゲームボーイD'     => 'gameboy scale sierra3',
            'ゲームボーイE'     => 'gameboy scale sierra2',
            'ゲームボーイF'     => 'gameboy scale sierra-lite',
            'バーチャルボーイ'  => 'virtualboy scale atkinson',
            'バーチャルボーイA' => 'virtualboy scale none',
            'バーチャルボーイB' => 'virtualboy scale floyd',
            'バーチャルボーイC' => 'virtualboy scale atkinson',
            'バーチャルボーイD' => 'virtualboy scale sierra3',
            'バーチャルボーイE' => 'virtualboy scale sierra2',
            'バーチャルボーイF' => 'virtualboy scale sierra-lite',
            '色反転'            => 'negate',
            'ネガ'              => 'negate',
            'ぼかし'            => 'gaussian_blur',
            '上下反転'          => 'flip vertical',
            '左右反転'          => 'flip horizontal',
            '左回転'            => 'rotate 90',
            '半回転'            => 'rotate 180',
            '右回転'            => 'rotate 270',
            'シャープ'          => 'sharpen',
            'エッジ'            => 'edge',
            'エッヂ'            => 'edge',
            'エンボス'          => 'emboss',
            '半額'              => 'half_price',
            '中破'              => 'kankore_half_damage',
            '大破'              => 'kankore_badly_damage',
            '集中線1'           => 'shuchusen1',
            '集中線黒'          => 'shuchusen1',
            '集中線2'           => 'shuchusen2',
            '集中線白'          => 'shuchusen2',
            '集中線3'           => 'shuchusen3',
            '集中線緑'          => 'shuchusen3',
            '集中線'            => function() { return mt_rand(0, 1) == 0 ? 'shuchusen1' : 'shuchusen2'; },
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
        
        $pixelate_regex = 'モザイク\s*(\d+)?';
        $animal_regex = 'ごはん\s*(?:「(.+?)」\s*「(.+?)」)?';
        $brain_regex = '脳\s*「(.+?)」';

        $commands = array();
        if(!preg_match_all("/{$pixelate_regex}|{$animal_regex}|{$brain_regex}|{$simple_wakuflow_regex}/u", $status->parsed->text, $matches, PREG_SET_ORDER)) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            return '(わーくフローの解析に失敗)';
        }
        foreach($matches as $match) {
            $done = false;
            foreach($simple_wakuflow_map as $wakuflow_ja => $wakuflow_cmd) {
                if($match[0] === $wakuflow_ja) {
                    if(!is_string($wakuflow_cmd)) {
                        if(is_callable($wakuflow_cmd)) {
                            $wakuflow_cmd = $wakuflow_cmd();
                        } else {
                            $wakuflow_cmd = '';
                        }
                    }
                    $commands[] = $wakuflow_cmd;
                    $done = true;
                    break;
                }
            }

            if(!$done) {
                if(preg_match("/^{$pixelate_regex}$/u", $match[0], $smatch)) {
                    // モザイク
                    $size = isset($smatch[1]) ? min(99, max(1, (int)$smatch[1])) : 15;
                    $commands[] = "pixelate {$size}";
                } elseif(preg_match("/^{$animal_regex}$/u", $match[0], $smatch)) {
                    // ごはんじゃない
                    $text1 = isset($smatch[1]) ? $smatch[1] : sprintf('%sはあなたのごはんじゃない。', $status->user->name);
                    $text2 = isset($smatch[2]) ? $smatch[2] : sprintf('@%s is not your food prodcts.', $status->user->screen_name);
                    //                                                                      ^^^^^^^ オリジナルに忠実
                    $commands[] = 'animal ' . $animal_text($text1) . ' ' . $animal_text($text2);
                } elseif(preg_match("/^{$brain_regex}$/u", $match[0], $smatch)) {
                    // 水槽の脳
                    $tmp = array();
                    foreach (explode('\n', $smatch[1]) as $line) {
                        $line = trim($line);
                        if ($line !== '') {
                            $tmp[] = $animal_text($line);
                        }
                    }
                    $commands[] = 'brain ' . implode(' ', $tmp);
	    	}
            }
        }
        $wakuflow = Yii::app()->wakuflow;
        if(!$wakuflow->proc($tmp_in, $tmp_out, implode("\n", $commands))) {
            @unlink($tmp_out);
            @unlink($tmp_in);
            Yii::log(sprintf(
                    '%s(): わーくフロー呼び出し失敗: %s, %s, %s',
                    __METHOD__,
                    $tmp_in, $tmp_out, implode('/', $commands)
            ));
            return '(わーくフローの処理に失敗: コマンド内部エラー)';
        }

        $save_dir = Yii::getPathOfAlias(Yii::app()->params['wakuflow']['path']);
        if(!file_exists($save_dir)) {
            mkdir($save_dir, 0755, true);
        }
        do {
            $save_path = $save_dir . '/' .
                         substr(hash('sha512', getmypid() . '@' . php_uname('n') . '@' . microtime(false)), 0, 8) .
                         '.png';
        } while(file_exists($save_path));
        $cmdline = '/usr/bin/env ' . escapeshellarg('/usr/bin/pngcrush') . ' -rem alla -brute ' . escapeshellarg($tmp_out) . ' ' . escapeshellarg($save_path);
        @exec($cmdline, $line, $status);
	@unlink($tmp_out);
	@unlink($tmp_in);
        if($status == 0) {
            return Yii::app()->params['wakuflow']['url'] . 'p/' . basename($save_path);
        }
        @unlink($save_path);
        return '(画像の保存に失敗)';
    } catch(Exception $e) {
        @unlink($tmp_out);
        @unlink($tmp_in);
        return '(わーくフローの処理に失敗: 例外)';
    }
}
