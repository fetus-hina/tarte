<?php
function plugin_twitterreki(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$status || !$status->user) {
        throw new Exception('precondition failed');
    }

    $ts = $status->user->created_at->getTimestamp();
    $day_diff = (time() - $ts) / 86400;
    return sprintf("あなたのツイッター歴は【%.1f日】(%s～)でした。みんなもチェック♫", $day_diff, $status->user->created_at->__toString());
}
