<?php
function plugin_ieee754(TwStatus $status = null, DictionaryCandidate $candidate, array $params) {
    if(!$status || !$status->user) {
        throw new CException('precondition failed');
    }

    $value = preg_replace('/IEEE754/i', '', $status->parsed->text);

    $in_fmt_hex = '0x(?:[[:xdigit:]]{2})+';
    $in_fmt_e = '[+-]?' .
                '(?:[[:digit:]]+|[[:digit:]]+\.[[:digit:]]*|[[:digit:]]*\.[[:digit:]]+)' .
                '[Ee]' .
                '[+-]?[[:digit:]]+';
    $in_fmt_f = '[+-]?' .
                '(?:[[:digit:]]+\.[[:digit:]]*|[[:digit:]]*\.[[:digit:]]+|[[:digit:]]+)';
    $in_fmt_num = "(?:{$in_fmt_e})|(?:{$in_fmt_f})";
    $in_fmt_inf = '[+-]?INF';
    $in_fmt_nan = 'NaN';
    $regex = "/(?:(?<hex>{$in_fmt_hex})|(?<num>{$in_fmt_num})|(?<inf>{$in_fmt_inf})|(?<nan>{$in_fmt_nan}))\b/i";

    if(!preg_match($regex, $value, $match)) {
        return '対象がみつかりません';
    }

    if(isset($match['hex']) && $match['hex'] != '') {
        $float = plugin_ieee754__binary2double($match['hex']);
        if(is_nan($float)) {
            return '値は NaN です';
        } elseif(is_infinite($float)) {
            if($float == -INF) {
                return '値は -INF です';
            } else {
                return '値は +INF です';
            }
        }
        return sprintf("値は %2\$s ( %1\$e ) です", $float, plugin_ieee754__valueformat($float));
    }
    if(isset($match['num']) && $match['num'] != '') {
        $value = doubleval($match['num']);
        $hex = plugin_ieee754__double2binary($value);
        $dec = plugin_ieee754__binary2double($hex);
        return sprintf("バイナリ表現は %1\$s です。値は %3\$s ( %2\$e ) です", $hex, $dec, plugin_ieee754__valueformat($dec));
    }
    if(isset($match['inf']) && $match['inf'] != '') {
        if(strtolower($match['inf']) === '-inf') {
            return '値は -INF です。バイナリ表現は ' . plugin_ieee754__double2binary(-INF) . ' です';
        } else {
            return '値は +INF です。バイナリ表現は ' . plugin_ieee754__double2binary(+INF) . ' です';
        }
    }
    if(isset($match['nan']) && $match['nan'] != '') {
        return '値は NaN です。バイナリ表現は ' . plugin_ieee754__double2binary(NAN) . ' です';
    }
    return '対象がみつかりません';
}

function plugin_ieee754__double2binary($value) {
    $packed = strrev(pack('d', (double)$value));
    return '0x' . strtolower(bin2hex($packed));
}

function plugin_ieee754__binary2double($value) {
    if(!preg_match('/(?:0x)?((?:[[:xdigit:]]{2}){1,8})/', $value, $match)) {
        return false;
    }
    $value = substr($match[1] . '0000000000000000', 0, 16);
    $packed = pack('h*', strrev($value));
    $unpacked = unpack('d', $packed);
    return $unpacked[1];
}

function plugin_ieee754__valueformat($value) {
    if(preg_match('/e([+-][[:digit:]]+)/', sprintf('%e', $value), $match)) {
        if((int)$match[1] > 50) {
            return '(too long)';
        }
    }
    return sprintf('%f', $value);
}
