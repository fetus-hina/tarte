<?php
function plugin_is_prime(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$status || !$status->user) {
        throw new CException('precondition failed');
    }
    if(!$config = BotConfig::getInstance()) {
        throw new CException('precondition failed');
    }

    if(!preg_match('/[+-]?\d+/', $status->parsed->text, $match)) {
        return '対象の数値がみつかりません';
    }
    if(strlen(ltrim($match[0], '+-')) > 100) {
        return '100桁以下で指定してください';
    }

    $gmp = gmp_init($match[0]);
    $status = gmp_prob_prime($gmp);
    if($status == 0) {
        return sprintf('%sは素数ではありません', gmp_strval($gmp));
    }
    if($status == 1) {
        return sprintf('%sは素数っぽいです', gmp_strval($gmp));
    }
    if($status == 2) {
        return sprintf('%sは素数です', gmp_strval($gmp));
    }
    return sprintf('%sはよくわかりません(%d)', gmp_strval($gmp), $status);
}
