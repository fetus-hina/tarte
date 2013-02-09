<?php
//FIXME
function plugin_user_name_kana_first(TwStatus $status = null, DictionaryCandidate $candidate) {
    if(!$status || !$status->user) {
        throw new Exception('precondition failed');
    }
    return Util::ignoreTwitterAutoLink($status->user->name);
}
