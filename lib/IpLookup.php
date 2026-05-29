<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class RecentViewsCounter_IpLookup
{
    public static function getClientIp()
    {
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_X_REAL_IP']) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    public static function getIpLocationFromLocal($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
            return null;
        }

        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();

            $result = $db->fetchRow($db->select('location', 'country', 'region', 'city', 'isp', 'updated_time')
                ->from($prefix . 'ip_location')
                ->where('ip = ?', $ip));

            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 本地IP查询错误: ' . $e->getMessage());
        }

        return null;
    }

    public static function saveIpLocationToLocal($ip, $location, $country = null, $region = null, $city = null, $isp = null)
    {
        if (empty($ip) || empty($location)) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $current_time = time();

            $exists = $db->fetchRow($db->select('id')
                ->from($prefix . 'ip_location')
                ->where('ip = ?', $ip));

            if ($exists) {
                $db->query($db->update($prefix . 'ip_location')
                    ->rows(array(
                        'location' => $location,
                        'country' => $country,
                        'region' => $region,
                        'city' => $city,
                        'isp' => $isp,
                        'updated_time' => $current_time
                    ))
                    ->where('ip = ?', $ip));
            } else {
                $db->query($db->insert($prefix . 'ip_location')
                    ->rows(array(
                        'ip' => $ip,
                        'location' => $location,
                        'country' => $country,
                        'region' => $region,
                        'city' => $city,
                        'isp' => $isp,
                        'created_time' => $current_time,
                        'updated_time' => $current_time
                    )));
            }

            return true;
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 保存IP归属地错误: ' . $e->getMessage());
            return false;
        }
    }

    public static function updateIpLocationAsync($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1' || self::isPrivateIp($ip)) {
            return;
        }

        try {
            $location_data = self::getIpLocationFromApi($ip);
            if ($location_data && !empty($location_data['location'])) {
                $db = Typecho_Db::get();

                self::saveIpLocationToLocal(
                    $ip,
                    $location_data['location'],
                    $location_data['country'],
                    $location_data['region'],
                    $location_data['city'],
                    $location_data['isp']
                );

                $db->query($db->update('table.views_records')
                              ->rows(array('location' => $location_data['location']))
                              ->where('ip = ? AND (location IS NULL OR location = "")', $ip));
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 异步更新IP归属地错误: ' . $e->getMessage());
        }
    }

    public static function getIpLocationFromIp138($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
            return null;
        }

        try {
             $options = Typecho_Widget::widget('Widget_Options');
             $plugin_config = $options->plugin('RecentViewsCounter');
             $token = ($plugin_config && !empty($plugin_config->ip138_token)) ? $plugin_config->ip138_token : '';

             if (empty($token)) {
                 throw new Exception('IP138 token not configured');
             }

             // ip138 仅支持 HTTPS，需付费 token，直连 443 可能被封锁（需代理）
             $url = "https://api.ip138.com/ipdata/?ip={$ip}&datatype=jsonp&token={$token}";

            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 2,
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (compatible; RecentViewsCounter/1.0)\r\n"
                )
            ));

            $response = @file_get_contents($url, false, $context);
            if ($response) {
                if (preg_match('/\((.+)\)/', $response, $matches)) {
                    $json_data = $matches[1];
                    $data = json_decode($json_data, true);

                    if ($data && isset($data['ret']) && $data['ret'] == 'ok' && isset($data['data'])) {
                        $location_info = $data['data'];
                        $location_parts = array();

                        if (!empty($location_info[0])) $location_parts[] = $location_info[0];
                        if (!empty($location_info[1])) $location_parts[] = $location_info[1];
                        if (!empty($location_info[2])) $location_parts[] = $location_info[2];

                        $location = implode(' ', $location_parts);

                        return array(
                            'location' => $location,
                            'country' => isset($location_info[0]) ? $location_info[0] : null,
                            'region' => isset($location_info[1]) ? $location_info[1] : null,
                            'city' => isset($location_info[2]) ? $location_info[2] : null,
                            'isp' => isset($location_info[4]) ? $location_info[4] : null
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: IP138 API查询错误: ' . $e->getMessage());
        }

        return null;
    }

    public static function getIpLocationFromApi($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
            return null;
        }

        $result = self::getIpLocationFromIp138($ip);
        if ($result && !empty($result['location'])) {
            return $result;
        }

        try {
            // ip-api.com 免费版不支持 HTTPS（需付费），必须用 HTTP
            $url = "http://ip-api.com/json/{$ip}?lang=zh-CN";
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 2,
                    'method' => 'GET'
                )
            ));

            $response = @file_get_contents($url, false, $context);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && $data['status'] == 'success') {
                    return array(
                        'location' => $data['country'] . ' ' . $data['regionName'] . ' ' . $data['city'],
                        'country' => $data['country'],
                        'region' => $data['regionName'],
                        'city' => $data['city'],
                        'isp' => isset($data['isp']) ? $data['isp'] : null
                    );
                }
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: ip-api.com查询错误: ' . $e->getMessage());
        }

        return null;
    }

    public static function isPrivateIp($ip)
    {
        if (empty($ip)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = ip2long($ip);
            if ($ip_long === false) {
                return false;
            }

            if (($ip_long >= ip2long('10.0.0.0')) && ($ip_long <= ip2long('10.255.255.255'))) {
                return true;
            }

            if (($ip_long >= ip2long('172.16.0.0')) && ($ip_long <= ip2long('172.31.255.255'))) {
                return true;
            }

            if (($ip_long >= ip2long('192.168.0.0')) && ($ip_long <= ip2long('192.168.255.255'))) {
                return true;
            }

            if (($ip_long >= ip2long('127.0.0.0')) && ($ip_long <= ip2long('127.255.255.255'))) {
                return true;
            }

            if (($ip_long >= ip2long('169.254.0.0')) && ($ip_long <= ip2long('169.254.255.255'))) {
                return true;
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($ip == '::1') {
                return true;
            }

            if (substr($ip, 0, 4) == 'fe80') {
                return true;
            }

            if (substr($ip, 0, 2) == 'fc' || substr($ip, 0, 2) == 'fd') {
                return true;
            }
        }

        return false;
    }

    public static function getIpLocation($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
            return '本地';
        }

        if (self::isPrivateIp($ip)) {
            return '内网';
        }

        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_options = $options->plugin('RecentViewsCounter');

        $enable_local_db = $plugin_options && $plugin_options->enable_local_ip_db == '1';

        if ($enable_local_db) {
            $local_result = self::getIpLocationFromLocal($ip);
            if ($local_result && !empty($local_result['location'])) {
                if ($plugin_options && $plugin_options->ip_cache_days) {
                    $cache_days = intval($plugin_options->ip_cache_days);
                    $expire_time = time() - ($cache_days * 24 * 3600);

                    if (isset($local_result['updated_time']) && $local_result['updated_time'] < $expire_time) {
                        $api_result = self::getIpLocationFromApi($ip);
                        if ($api_result && !empty($api_result['location'])) {
                            self::saveIpLocationToLocal(
                                $ip,
                                $api_result['location'],
                                $api_result['country'],
                                $api_result['region'],
                                $api_result['city'],
                                $api_result['isp']
                            );
                            return $api_result['location'];
                        }
                    }
                }

                return $local_result['location'];
            }

            $api_result = self::getIpLocationFromApi($ip);
            if ($api_result && !empty($api_result['location'])) {
                self::saveIpLocationToLocal(
                    $ip,
                    $api_result['location'],
                    $api_result['country'],
                    $api_result['region'],
                    $api_result['city'],
                    $api_result['isp']
                );

                return $api_result['location'];
            }
        } else {
            $api_result = self::getIpLocationFromApi($ip);
            if ($api_result && !empty($api_result['location'])) {
                return $api_result['location'];
            }
        }

        return 'Unknown';
    }
}
