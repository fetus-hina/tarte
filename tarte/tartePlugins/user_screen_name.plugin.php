<?php
function plugin_user_screen_name(TwStatus $status = null, DictionaryCandidate $candidate) {
    if(!$status || !$status->user) {
        throw new Exception('precondition failed');
    }
    return Util::ignoreTwitterAutoLink($status->user->screen_name);
}
