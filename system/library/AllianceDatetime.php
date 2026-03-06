<?php

class AllianceDatetime {
    public static function getSiteDateTime($config, $format = 'Y-m-d H:i:s.vP') {
        $timezone = $config->get('config_timezone') ?: 'UTC';
        $datetime = new \Datetime('now', new \DateTimeZone($timezone));

        return preg_replace('/(\.\d{2})\d/', '$1',$datetime->format($format));
    }
}
