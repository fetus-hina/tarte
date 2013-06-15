<?php
class TarteCommand extends CConsoleCommand {
    const LOGCAT = 'tarte.TarteCommand';

    static private $child_process_list = array();

    public function __destruct() {
        self::terminateChildren();
    }

    public function init() {
        $logger = Yii::getLogger();
        $logger->autoFlush = 1;
        $logger->autoDump = true;
        parent::init();
    }

    public function actionIndex() {
        foreach(Yii::app()->params['accounts'] as $screen_name) {
            $command = sprintf(
                'ps x | grep %s | grep -i %s | grep -vc grep',
                escapeshellarg('yiic tarte stream'),
                escapeshellarg($screen_name)
            );
            $line = trim(@exec($command, $lines, $status));
            if($line === '0') {
                Yii::log(__METHOD__ . '(): 起動します: @' . $screen_name, 'info', self::LOGCAT);
                $command = sprintf(
                    '/usr/bin/env %s %s %s --screen_name=%s </dev/null >/dev/null 2>/dev/null &',
                    escapeshellarg(realpath(__DIR__ . '/..') . '/yiic'),
                    escapeshellarg('tarte'),
                    escapeshellarg('stream'),
                    escapeshellarg($screen_name)
                );
                exec($command);
            }
        }
    }

    public function actionStream($screen_name) {
        $retry_wait_usec = null;
        while(true) {
            $handler = new UserStreamEventHandler($screen_name);
            $handler->init();

            $client = new TwitterUserStream($screen_name);
            $client->onAfterConnect             = array($handler, 'onAfterConnect');
            $client->onAfterHandshake           = array($handler, 'onAfterHandshake');
            $client->onIdleStart                = array(__CLASS__, 'processChildren');
            $client->onIdling                   = array(__CLASS__, 'processChildren');
            $client->onIdleStart                = array($handler, 'onIdleStart');
            $client->onStatus                   = array($handler, 'onStatus');
            $client->onUserIsFollowed           = array($handler, 'onUserIsFollowed');
            $client->onUsersTweetIsFavorited    = array($handler, 'onUsersTweetIsFavorited');

            try {
                try {
                    $client->init();
                } catch(Exception $e) {
                    $retry_wait_usec =
                        is_null($retry_wait_usec)
                            ? mt_rand(20 * 1000 * 1000, 40 * 1000 * 1000)
                            : min($retry_wait_usec * 2, 300 * 1000 * 1000);
                    throw $e;
                }
                $retry_wait_usec = null;
                $client->run();
            } catch(Exception $e) {
                Yii::log(__METHOD__ . '(): 実行時例外捕捉: ' . $e->getMessage(), 'error', self::LOGCAT);
                if(!is_null($retry_wait_usec)) {
                    usleep($retry_wait_usec);
                }
            }
            unset($client);
            unset($handler);
        }
    }

    public function actionFollow($screen_name, $user) {
        if(!$user = self::decodeParameter($user)) {
            Yii::log(__METHOD__ . '(): デコード失敗: user', 'error', self::LOGCAT);
            return 1;
        }
        if(!$user instanceof TwUser) {
            Yii::log(__METHOD__ . '(): user が TwUser のインスタンスでない', 'error', self::LOGCAT);
            return 1;
        }

        $client = new Twitter($screen_name);
        $client->init();
        $client->friendshipsCreate($user->id);
    }

    public function actionAct($screen_name, $status) {
        if(!$status = self::decodeParameter($status)) {
            Yii::log(__METHOD__ . '(): デコード失敗: status', 'error', self::LOGCAT);
            return 1;
        }
        if(!$status instanceof TwStatus) {
            Yii::log(__METHOD__ . '(): status が TwStatus のインスタンスでない', 'error', self::LOGCAT);
            return 1;
        }

        $parsed = $status->getParsed();
        if($parsed->getRetweet() != '') {
            //Yii::log(__METHOD__ . '(): retweet データが含まれるので無視', 'info', self::LOGCAT);
            return 0;
        }
        if($parsed->hasMension()) {
            if(!$parsed->isInReplyTo($screen_name)) {
                //Yii::log(__METHOD__ . '(): 他人へのリプライ', 'info', self::LOGCAT);
                return 0;
            }
            $dictionary = Dictionary::factory($screen_name);
            if(!$candidate = $dictionary->getReply($status)) {
                return 0;
            }
        } else {
            $dictionary = Dictionary::factory($screen_name);
            if(!$candidate = $dictionary->getTimeline($status)) {
                return 0;
            }
        }

        $formatter = new TweetFormatter();
        $formatter->init();
        if(!$text = $formatter->format($status->user, $status, $candidate)) {
            return 0;
        }

        $client = new Twitter($screen_name);
        $client->init();
        $client->statusesUpdate($text, array('in_reply_to_status_id' => $status->id));
        return 0;
    }

    public function actionVoluntary($screen_name) {
        $dictionary = Dictionary::factory($screen_name);
        if(!$candidate = $dictionary->getRandom()) {
            return 0;
        }

        $formatter = new TweetFormatter();
        $formatter->init();
        if(!$text = $formatter->format(null, null, $candidate)) {
            return 0;
        }

        $client = new Twitter($screen_name);
        $client->init();
        $client->statusesUpdate($text);
        return 0;
    }

    public function actionDumpDictionary($screen_name) {
        $dictionary = Dictionary::factory($screen_name);
        $dictionary->dump();
    }

    static public function startChildCommand($action, $screen_name, array $options) {
        $cmd_options = array(
            sprintf('--screen_name=%s', escapeshellarg($screen_name)),
        );
        foreach($options as $key => $value) {
            if(is_null($value)) {
                $cmd_options[] = sprintf('--%s', $key);
            } else {
                $cmd_options[] = sprintf('--%s=%s', $key, escapeshellarg(self::encodeParameter($value)));
            }
        }
        $command = realpath(__DIR__ . '/..') . '/yiic';
        $cmdline = sprintf(
            '/usr/bin/env %s %s %s %s',
            escapeshellarg($command),
            escapeshellarg('tarte'), // command name
            escapeshellarg($action),
            implode(' ', $cmd_options)
        );

        $descriptorspec = array(
            array('file', '/dev/null', 'r'),
            array('file', '/dev/null', 'w'),
            array('file', '/dev/null', 'w'),
        );
        $pipes = array();
        if(!$handle = @proc_open($cmdline, $descriptorspec, $pipes, dirname($command))) {
            Yii::log(__METHOD__ . '(): 子プロセスの作成に失敗', 'error', self::LOGCAT);
            throw new CException('Could not create child process');
        }
        @fclose($pipes[0]);
        self::$child_process_list[] = $handle;
        //Yii::log(__METHOD__ . "(): 子供ができました (@{$screen_name} / {$action})", 'info', self::LOGCAT);
    }

    static private function encodeParameter($value) {
        return rtrim(strtr(base64_encode(gzcompress(serialize($value), 3)), '+/', '-_'), '=');
    }

    static private function decodeParameter($str) {
        return @unserialize(gzuncompress(base64_decode(strtr($str, '-_', '+/'))));
    }

    static public function processChildren() {
        if(!self::$child_process_list) {
            return;
        }

        $term_count = 0;
        foreach(self::$child_process_list as $i => $handle) {
            if(!$status = @proc_get_status($handle)) {
                @proc_close($handle);
                unset(self::$child_process_list[$i]);
                ++$term_count;
                continue;
            }
            if(!$status['running']) {
                @proc_close($handle);
                unset(self::$child_process_list[$i]);
                ++$term_count;
            }
        }

        //if($term_count > 0) {
        //    Yii::log(__METHOD__ . '(): 子供が ' . $term_count . ' 人死にました', 'info', 'tarte.command');
        //}
    }

    static public function terminateChildren() {
        if(!self::$child_process_list) {
            return;
        }
        foreach(self::$child_process_list as $handle) {
            @proc_terminate($handle);
        }
        while(self::$child_process_list) {
            self::processChildren();
        }
    }
}
