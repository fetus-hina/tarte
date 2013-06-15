<?php
function plugin_malkovchain(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$config = BotConfig::getInstance()) {
        throw new CException('precondition failed');
    }
    if(!$malkov_conf = $config->malkov) {
        throw new CException('malkov chain not configured');
    }

    for($i = 0; $i < 5; ++$i) {
        try {
            if($text = MalkovChain::createText($config->getScreenName(), !!$status)) {
                return $text;
            }
        } catch(Exception $e) {
        }
    }
    throw new CException('malkov chain failed');
}
