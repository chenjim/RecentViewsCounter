<?php
/**
 * Cron 定时回填 IP 归属地
 * 后台回填 IP 归属地，绕过广告拦截器屏蔽
 *
 * cron 用法: php /app/usr/plugins/RecentViewsCounter/cron-update-ip-locations.php
 */
require_once dirname(__FILE__, 4) . '/config.inc.php';
require_once __DIR__ . '/lib/IpLookup.php';

$batchSize = 30;
$apiDelay = 2;
$maxRetries = 5;
$retryFile = sys_get_temp_dir() . '/rvc_retry_counts.json';
$processed = 0;
$succeeded = 0;

// 读取重试计数
$retryCounts = array();
if (file_exists($retryFile)) {
    $content = @file_get_contents($retryFile);
    if ($content) $retryCounts = json_decode($content, true) ?: array();
}

try {
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();

    $ips = $db->fetchAll($db->query(
        "SELECT v.ip, COUNT(*) as cnt
         FROM {$prefix}views_records v
         WHERE (v.location IS NULL OR v.location = '')
           AND v.ip != '' AND v.ip NOT IN ('127.0.0.1','::1')
           AND v.ip NOT LIKE '192.168.%'
           AND v.ip NOT LIKE '10.%'
           AND v.ip NOT LIKE '172.%'
           AND v.ip NOT LIKE '0.%'
           AND v.ip NOT LIKE '169.254.%'
           AND NOT EXISTS (
               SELECT 1 FROM {$prefix}ip_location l
               WHERE l.ip = v.ip AND l.location = 'Unknown'
           )
         GROUP BY v.ip
         ORDER BY MAX(v.visit_time) ASC
         LIMIT {$batchSize}"
    ));

    if (empty($ips)) {
        echo "[" . date('Y-m-d H:i:s') . "] 无待处理 IP\n";
        exit(0);
    }

    $total = count($ips);
    echo "[" . date('Y-m-d H:i:s') . "] 本轮 {$total} 个 IP\n";

    foreach ($ips as $row) {
        $ip = $row['ip'];
        $processed++;

        echo "  [{$processed}/{$total}] {$ip} ... ";

        $result = RecentViewsCounter_IpLookup::getIpLocationFromApi($ip);

        if ($result && !empty($result['location'])) {
            RecentViewsCounter_IpLookup::saveIpLocationToLocal(
                $ip,
                $result['location'],
                $result['country'],
                $result['region'],
                $result['city'],
                $result['isp']
            );

            $db->query($db->update('table.views_records')
                          ->rows(array('location' => $result['location']))
                          ->where('ip = ? AND (location IS NULL OR location = "")', $ip));

            echo "{$result['location']}\n";
            $succeeded++;

            // 清除该 IP 的重试记录
            unset($retryCounts[$ip]);
        } else {
            $retryCounts[$ip] = ($retryCounts[$ip] ?? 0) + 1;

            if ($retryCounts[$ip] >= $maxRetries) {
                RecentViewsCounter_IpLookup::saveIpLocationToLocal($ip, 'Unknown');
                echo "失败 {$retryCounts[$ip]} 次, 已标记 Unknown\n";
                unset($retryCounts[$ip]);
            } else {
                echo "失败 (第 {$retryCounts[$ip]} 次, 下次重试)\n";
            }
        }

        if ($processed < $total) {
            sleep($apiDelay);
        }
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] 错误: " . $e->getMessage() . "\n";
    exit(1);
}

// 持久化重试计数（保留最近 10000 条，防止文件膨胀）
if (count($retryCounts) > 10000) {
    $retryCounts = array_slice($retryCounts, -10000, null, true);
}
@file_put_contents($retryFile, json_encode($retryCounts), LOCK_EX);

echo "[" . date('Y-m-d H:i:s') . "] 完成: 成功 {$succeeded} / 处理 {$processed}\n";
