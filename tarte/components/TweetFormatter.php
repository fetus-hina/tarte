<?php
class TweetFormatter extends CComponent {
    const LOGCAT = 'tarte.TweetFormatter';

    public function init() {
    }

    public function format(TwUser $user = null, TwStatus $status = null, DictionaryCandidate $candidate) {
        $action = $candidate->action;
        if($action === DictionaryCandidate::ACTION_REPLY) {
            if(!$user) {
                $action = DictionaryCandidate::ACTION_TWEET;
            }
        } elseif($action === DictionaryCandidate::ACTION_QT) {
            if(!$status) {
                $action = DictionaryCandidate::ACTION_TWEET;
            }
        } elseif($action === DictionaryCandidate::ACTION_TWEET) {
            // nothing to do
        } else {
            $action = $status ? DictionaryCandidate::ACTION_REPLY : DictionaryCandidate::ACTION_TWEET;
        }

        $text = $this->textReplace($user, $status, $candidate);
        switch($action) {
        case DictionaryCandidate::ACTION_TWEET:  return $text;
        case DictionaryCandidate::ACTION_REPLY:  return '@' . $user->screen_name . ' ' . $text;
        case DictionaryCandidate::ACTION_QT:     return $text . ' RT @' . $user->screen_name . ': ' . $status->text;
        }
    }

    private function textReplace(
        TwUser $user = null, TwStatus $status = null, DictionaryCandidate $candidate
    ) {
        Yii::log(__METHOD__ . '(): ' . $candidate->text);
        return preg_replace_callback(
            '/\{(.*?)\}/u',
            function (array $match) use ($user, $status, $candidate) {
                $parameters = explode(':', $match[1]);
                $plugin_name = trim(array_shift($parameters));
                $plugin_function = 'plugin_' . $plugin_name;
                Yii::log(__METHOD__ . '(): Call plugin: ' . $plugin_function, 'info', TweetFormatter::LOGCAT);
                if(!function_exists($plugin_function)) {
                    $plugin_file = $plugin_name . '.plugin.php';
                    $plugin_pathes = array(__DIR__ . '/../tartePlugins/' . $plugin_file); // FIXME: user plugin
                    foreach($plugin_pathes as $path) {
                        if(@file_exists($path)) {
                            include_once($path);
                            if(function_exists($plugin_function)) {
                                break;
                            }
                        }
                    }
                    if(!function_exists($plugin_function)) {
                        throw new CException('Could not load plugin: ' . $plugin_function);
                    }
                }
                return call_user_func($plugin_function, $status, $candidate, $parameters);
            },
            $candidate->text
        );
    }
}
