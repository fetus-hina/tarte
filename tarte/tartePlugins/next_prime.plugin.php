<?php
function plugin_next_prime(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$status || !$status->user) {
        throw new CException('precondition failed');
    }
    if(!$config = BotConfig::getInstance()) {
        throw new CException('precondition failed');
    }

    if(!preg_match('/\b\d{1,100}\b/', $status->parsed->text, $match)) {
        return '対象の数値がみつかりません';
    }

    $gmp = gmp_init($match[0]);
    $ret = gmp_nextprime($gmp);
    return sprintf('次の素数は%sです', gmp_strval($ret));
}
