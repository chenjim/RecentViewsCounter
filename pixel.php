<?php
/**
 * 浏览器异步追踪像素 —— 后台更新 IP 归属地
 */
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__, 4));
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
require_once __DIR__ . '/lib/IpLookup.php';

$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

$ip = RecentViewsCounter_IpLookup::getClientIp();
if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || RecentViewsCounter_IpLookup::isPrivateIp($ip)) {
    header('Content-Type: image/gif');
    header('Content-Length: 43');
    echo $gif;
    exit;
}

$cached = RecentViewsCounter_IpLookup::getIpLocationFromLocal($ip);
if ($cached && !empty($cached['location'])) {
    header('Content-Type: image/gif');
    header('Content-Length: 43');
    echo $gif;
    exit;
}

try {
    RecentViewsCounter_IpLookup::updateIpLocationAsync($ip);
} catch (Exception $e) {
    error_log('RecentViewsCounter pixel: ' . $e->getMessage());
}

header('Content-Type: image/gif');
header('Content-Length: 43');
echo $gif;
