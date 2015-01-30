<?php
require_once(__DIR__ . '/../components/YahooWeather.php');

function plugin_yahoo_weather(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
     if(!$status || !$status->user) {
         throw new CException('precondition failed');
     }
 
    $locations = plugin_yahoo_weather__locations();
    $location_regex = '/' . implode('|', array_map(function($a) { return preg_quote($a, '/'); }, array_keys($locations))) . '/u';

    if(preg_match($location_regex, $status->parsed->text, $match)) {
        $location = $match[0];
    } else {
        $keys = array_keys($locations);
        $location = $keys[mt_rand(0, count($keys) - 1)];
    }
    $weather = new YahooWeather();
    if(!$ret = $weather->get($locations[$location])) {
        return '気象情報の取得に失敗しました';
    }
    return sprintf(
        '%s現在の%sの天気は%s、気温%s、湿度%s、%s、気圧%s(%.3f気圧)、明日の天気は%s、最低気温%s、最高気温%sです',
        $ret['updated_at'] === false ? '' : date('H:i', $ret['updated_at']),
        $location,
        $ret['now']['weather'],
        $ret['now']['temp'],
        $ret['now']['humidity'],
        $ret['now']['wind'],
        $ret['now']['pressure'],
        $ret['now']['pressure'] / 1013.25,
        $ret['tomorrow']['weather'],
        $ret['tomorrow']['temp_l'],
        $ret['tomorrow']['temp_h']
    );
}

function plugin_yahoo_weather__locations() {
    return array_merge(
        [
            '北海道'    => 'hokkaido prefecture',
            '青森'      => 'aomori prefecture',
            '岩手'      => 'iwate prefecture',
            '宮城'      => 'miyagi prefecture',
            '秋田'      => 'akita prefecture',
            '山形'      => 'yamagata prefecture',
            '福島'      => 'fukushima prefecture',
            '茨城'      => 'ibaraki prefecture',
            '栃木'      => 'tochigi prefecture',
            '群馬'      => 'gunma prefecture',
            '埼玉'      => 'saitama prefecture',
            '千葉'      => 'chiba prefecture',
            '東京'      => 'tokyo prefecture',
            '神奈川'    => 'kanagawa prefecture',
            '新潟'      => 'niigata prefecture',
            '富山'      => 'toyama prefecture',
            '石川'      => 'ishikawa prefecture',
            '福井'      => 'fukui prefecture',
            '山梨'      => 'yamanashi prefecture',
            '長野'      => 'nagano prefecture',
            '岐阜'      => 'gifu prefecture',
            '静岡'      => 'shizuoka prefecture',
            '愛知'      => 'aichi prefecture',
            '三重'      => 'mie prefecture',
            '滋賀'      => 'shiga prefecture',
            '京都'      => 'kyoto prefecture',
            '大阪'      => 'osaka prefecture',
            '兵庫'      => 'hyogo prefecture',
            '奈良'      => 'nara prefecture',
            '和歌山'    => 'wakayama prefecture',
            '鳥取'      => 'tottori prefecture',
            '島根'      => 'shimane prefecture',
            '岡山'      => 'okayama prefecture',
            '広島'      => 'hiroshima prefecture',
            '山口'      => 'yamaguchi prefecture',
            '徳島'      => 'tokushima prefecture',
            '香川'      => 'kagawa prefecture',
            '愛媛'      => 'ehime prefecture',
            '高知'      => 'kochi prefecture',
            '福岡'      => 'fukuoka prefecture',
            '佐賀'      => 'saga prefecture',
            '長崎'      => 'nagasaki prefecture',
            '熊本'      => 'kumamoto prefecture',
            '大分'      => 'oita prefecture',
            '宮崎'      => 'miyazaki prefecture',
            '鹿児島'    => 'kagoshima prefecture',
            '沖縄'      => 'okinawa prefecture',
        ], [
            '札幌'      => 'sapporo-shi',
            '仙台'      => 'sendai-shi',
            'さいたま'  => 'saitama-shi',
            '千葉'      => 'chiba-shi',
            '横浜'      => 'yokohama-shi',
            '川崎'      => 'kawasaki-shi',
            '相模原'    => 'sagamihara-shi',
            '新潟'      => 'niigata-shi',
            '静岡'      => 'shizuoka-shi',
            '浜松'      => 'hamamatsu-shi',
            '名古屋'    => 'nagoya-shi',
            '京都'      => 'kyoto-shi',
            '大阪'      => 'osaka-shi',
            '堺'        => 'sakai-shi',
            '神戸'      => 'kobe-shi',
            '岡山'      => 'okayama-shi',
            '広島'      => 'hiroshima-shi',
            '北九州'    => 'kitakyushu-shi',
            '福岡'      => 'fukuoka-shi',
            '熊本'      => 'kumamoto-shi',
        ]
    );
}
