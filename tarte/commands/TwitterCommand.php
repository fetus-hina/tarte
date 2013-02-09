<?php
class TwitterCommand extends CConsoleCommand {
    public function actionVerify($screen_name) {
        $client = new Twitter($screen_name);
        $user = $client->accountVerifyCredentials();
        if(strtolower($screen_name) !== strtolower($user->screen_name)) {
            echo "Verify NG:\n";
            echo "    設定 @name:   " . $screen_name . "\n";
            echo "    実際の @name: " . $user->screen_name . "\n";
            Yii::end(1);
        }
        echo "Verify OK:\n";
        echo "    id: " . $user->id . "\n";
        echo "    @name: " . $user->screen_name . "\n";
        echo "    protected?: " . ($user->protected ? 'yes' : 'no') . "\n";
        echo "    tweets: " . number_format($user->statuses_count) . "\n";
        echo "    friends: " . number_format($user->friends_count) . "\n";
        echo "    follower: " . number_format($user->followers_count) . "\n";
    }

    public function actionTweet($screen_name, $text) {
        $client = new Twitter($screen_name);
        $status = $client->statusesUpdate($text);
        echo "ツイート完了:\n";
        echo "    id: " . $status->id . "\n";
        echo "    text: " . $status->text . "\n";
        echo "    at: " . $status->created_at->__toString() . "\n";
    }

    public function actionAuthorize($create = false) {
        $client = new TwitterAuthorizer();
        $client->init();
        $next_url = $client->startAuthorization();
        echo "次の URL にアクセスして認証: \n";
        echo "    " . $next_url . "\n";
        do {
            echo "\n";
            echo "PIN コードを入力: \n";
            echo "    ";
            $pin = trim(fgets(STDIN));
            if(preg_match('/^[[:digit:]]{7}$/', $pin)) {
                break;
            }
        } while(true);
        echo "\n";
        $data = $client->getUserToken($pin);
        echo "認証されました:\n";
        echo "    ユーザID: " . $data['user_id'] . "\n";
        echo "    @name:    " . $data['screen_name'] . "\n";
        echo "\n";
        echo "設定:\n";
        echo "    'oauth' => array(\n";
        echo "        'token' => '{$data['oauth_token']}',\n";
        echo "        'secret' => '{$data['oauth_token_secret']}',\n";
        echo "    ),\n";
        echo "\n";

        if($create) {
            $config_path =
                realpath(__DIR__ . '/../..') .
                '/users/' . strtolower($data['screen_name']) . '/config.php';
            if(file_exists($config_path)) {
                echo "コンフィグファイルが既に存在するため、新規作成しませんでした。\n";
            } else {
                if(!file_exists(dirname($config_path))) {
                    if(!@mkdir(dirname($config_path), 0755, true)) {
                        echo "コンフィグファイル用ディレクトリが作成できませんでした。\n";
                    }
                }
                if(!$fh = @fopen($config_path, 'c')) {
                    echo "コンフィグファイルに書き込めません。\n";
                } else {
                    flock($fh, LOCK_EX);
                    $min_interval = mt_rand(25,  45) * 60;
                    $max_interval = mt_rand(90, 120) * 60;
                    $config = array();
                    $config[] = '<?php';
                    $config[] = "return array(";
                    $config[] = "    'oauth' => array(";
                    $config[] = "        'token' => '{$data['oauth_token']}',";
                    $config[] = "        'secret' => '{$data['oauth_token_secret']}',";
                    $config[] = "    ),";
                    $config[] = "    'voluntary' => array(";
                    $config[] = "        'min_interval' => {$min_interval},";
                    $config[] = "        'max_interval' => {$max_interval},";
                    $config[] = "    ),";
                    $config[] = "    'dictionary' => array(";
                    $config[] = "    ),";
                    $config[] = ");";
                    $config[] = '';
                    fwrite($fh, implode("\n", $config));
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    echo "コンフィグファイルを作成しました。\n";
                }
            }
        }
    }
}
