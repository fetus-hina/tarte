<?php
function plugin_user_name_kana_first(TwStatus $status = null, DictionaryCandidate $candidate) {
    if(!function_exists('plugin_user_name_kana')) {
        require_once(__DIR__ . '/user_name_kana.plugin.php');
    }

    $kana = plugin_user_name_kana($status, $candidate);
    $ret = mb_substr($kana, 0, 1, 'UTF-8');
    if($ret <= 'ぁ' && 'ゔ' <= $ret) { // ひらがな
        // 小さい文字は 3 文字を限度にくっつける
        //     "ちょっと"→"ち"
        //   よりは
        //     "ちょっと"→"ちょっ"
        //   の方が結果として自然
        $max = min(3 + 1, mb_strlen($kana, 'UTF-8'));
        for($i = 1; $i < $max; ++$i) {
            $c = mb_substr($kana, $i, 1, 'UTF-8');
            if($c === 'ぁ' || $c === 'ぃ' || $c === 'ぅ' || $c === 'ぇ' || $c === 'ぉ' ||
               $c === 'っ' || $c === 'ゃ' || $c === 'ゅ' || $c === 'ょ' || $c === 'ゎ')
            {
                $ret .= $c;
            } else {
                break;
            }
        }
	}
    return $ret;
}
