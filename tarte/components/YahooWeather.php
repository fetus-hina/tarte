<?php
class YahooWeather {
    public function get($location) {
        $obj = $this->query($location);
        $item = $obj->item;
        $tomorrow = $item->forecast[1]; // fixme
        $ret = [
            'now' => [
                'temp'      => self::formatTemperature($item->condition->temp, $obj->units->temperature),
                'humidity'  => sprintf('%d%%', $obj->atmosphere->humidity),
                'pressure'  => self::formatPressure($obj->atmosphere->pressure, $obj->units->pressure),
                'wind'      => self::formatWind($obj->wind->direction, $obj->wind->speed, $obj->units->speed),
                'weather'   => self::formatWeather($item->condition->code, $item->condition->text),
            ],
            'tomorrow' => [
                'temp_l'    => self::formatTemperature($tomorrow->low, $obj->units->temperature),
                'temp_h'    => self::formatTemperature($tomorrow->high, $obj->units->temperature),
                'weather'   => self::formatWeather($tomorrow->code, $tomorrow->text),
            ],
            'updated_at'    => @strtotime($item->pubDate),
        ];
        return $ret;
    }

    private function query($location) {
        $parameters = [
            'q'         => sprintf('select * from weather.forecast where woeid in (select woeid from geo.places(1) where text="%s")', addslashes($location)),
            'format'    => 'json',
            'env'       => 'store://datatables.org/alltableswithkeys',
        ];
        return $this->requestJson(
            'https://query.yahooapis.com/v1/public/yql?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)
        );
    }

    private function requestJson($uri) {
        $curl = curl_init($uri);
        curl_setopt_array($curl, [
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $result = @curl_exec($curl);
        if($result != '') {
            $result = @json_decode($result);
        }
        return $result && isset($result->query->results->channel) ? $result->query->results->channel : false;
    }

    static private function formatTemperature($temp, $unit) {
        switch(strtoupper($unit)) {
        case 'C':   return (string)(int)round((float)$temp) . '℃';
        case 'F':   return (string)(int)round(self::f2c($temp)) . '℃';
        default:    return '??℃';
        }
    }

    static private function formatPressure($pressure, $unit) {
        switch($unit) {
        case 'in': return sprintf('%dhPa', round(self::inhg2hpa($pressure)));
        default:   return '??hPa';
        }
    }

    static private function formatWind($compass, $speed, $unit) {
        switch($unit) {
        default:    return '風向風速不明';
        case 'mph': $speed = self::miph2mps($speed); break;
        }
        if($speed < 1) {
            return 'ほぼ無風';
        }
        $compass16 = floor((11.25 + $compass) * 16 / 360) % 16;
        $compass_map = [
            '北', '北北東', '北東', '東北東', '東', '東南東', '南東', '南南東',
            '南', '南南西', '南西', '西南西', '西', '西北西', '北西', '北北西',
        ];
        return sprintf('%sの風%dm/s', $compass_map[$compass16], round($speed));
    }

    static private function formatWeather($code, $fallback) {
        $map = [
             0  => '竜巻',
             1  => '台風',
             2  => 'ハリケーン',
             3  => '激しい雷雨',
             4  => '雷雨',
             5  => '雪混じりの雨',
             6  => 'みぞれ混じりの雨',
             7  => 'みぞれ混じりの雪',
             8  => '着氷性の霧雨',
             9  => '霧雨',
            10  => '着氷性の雨',
            11  => 'にわか雨',
            12  => 'にわか雨',
            13  => '雪の突風',
            14  => '時々雪',
            15  => '吹雪',
            16  => '雪',
            17  => '雹',
            18  => 'みぞれ',
            19  => 'ほこり',
            20  => '霧',
            21  => '靄',
            22  => '埃っぽい',
            23  => '荒れ模様',
            24  => '強風',
            25  => '寒い',
            26  => '曇り',
            27  => 'おおむね曇り',
            28  => 'おおむね曇り',
            29  => 'ところにより曇り',
            30  => 'ところにより曇り',
            31  => '快晴',
            32  => '晴れ',
            33  => '晴れ',
            34  => '晴れ',
            35  => '雨と雹',
            36  => '暑い',
            37  => '局地的に雷雨',
            38  => 'ところにより雷雨',
            39  => 'ところにより雷雨',
            40  => 'ところによりにわか雨',
            41  => '大雪',
            42  => '吹雪',
            43  => '大雪',
            44  => 'ところにより曇り',
            45  => '雷雨',
            46  => '吹雪',
            47  => 'ところにより雷雨',
        ];
        if(isset($map[$code])) {
            return $map[$code];
        }
        return sprintf('%s(%d)', $fallback, $code);
    }

    static private function f2c($f) {
        return ((float)$f - 32) * 5 / 9;
    }

    static private function inhg2hpa($inhg) {
        return 3386.39 * (float)$inhg / 100;
    }

    static private function miph2mps($mi) {
        return 1609.344 * (float)$mi / 3600;
    }
}
