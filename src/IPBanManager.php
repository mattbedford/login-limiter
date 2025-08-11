<?php

namespace LAL\src;


abstract class IPBanManager {
    public static function is_banned($ip, $username) {
        $bans = get_option('lal_ban_list', ['ips' => [], 'users' => []]);
        return in_array($ip, $bans['ips']) || in_array($username, $bans['users']);
    }

    public static function is_whitelisted($ip) {
        $whitelist = get_option('lal_whitelist', []);
        return in_array($ip, $whitelist);
    }
}
