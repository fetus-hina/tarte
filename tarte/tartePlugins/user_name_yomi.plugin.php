<?php
function plugin_user_name_yomi(TwStatus $status = null, DictionaryCandidate $candidate) {
    if(!$status || !$status->user) {
        throw new Exception('precondition failed');
    }
    $yomi = new Yomi($status->user->name);
    $yomi->init();
    return Util::ignoreTwitterAutoLink($yomi->voice);
}
