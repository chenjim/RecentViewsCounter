<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once dirname(__FILE__) . '/Blacklist.php';

// 包含管理后台公共文件
include_once __TYPECHO_ROOT_DIR__ . '/admin/common.php';
include_once __TYPECHO_ROOT_DIR__ . '/admin/header.php';
include_once __TYPECHO_ROOT_DIR__ . '/admin/menu.php';

// 获取插件配置
$options = Typecho_Widget::widget('Widget_Options');
$plugin_config = $options->plugin('RecentViewsCounter');
$top_count = $plugin_config ? intval($plugin_config->top_count) : 10;

/**
 * 解析时间范围参数，返回天数（null表示全部时间）
 */
function parseTimeRangeDays($range) {
    if ($range === '7') return 7;
    if ($range === '30') return 30;
    return null;
}

// CSRF 保护：对修改数据的 POST 请求校验 Referer
$request = Typecho_Request::getInstance();
if ($request->isPost()) {
    $destructiveActions = array('clean_records', 'clear_cache', 'clean_expired_ip_data', 'delete_ip_location', 'add_to_blacklist', 'remove_from_blacklist', 'get_blacklist');
    if (in_array($request->get('action'), $destructiveActions)) {
        $siteHost = parse_url($options->siteUrl, PHP_URL_HOST);
        $refererHost = $request->getReferer() ? parse_url($request->getReferer(), PHP_URL_HOST) : null;
        if (!$refererHost || $refererHost !== $siteHost) {
            die('Forbidden');
        }
    }
}

// 智能Bot检测说明内容（复用）
$threshold = RecentViewsCounter_Plugin::BOT_THRESHOLD;
$bot_detection_info = "智能Bot检测说明：
基于多维度分析(User-Agent识别、访问频率、行为模式等)。
评分规则：
• UA识别40分、高频访问20分、专注爬取15分
• 单一UA25分、高频率10分
判定条件：UA匹配 或 行为评分 ≥{$threshold}分 即标记为Bot
(注意：TOP 20表显示的是纯行为评分，不含UA识别分)";

// 处理手动清理请求
if (isset($_POST['action']) && $_POST['action'] == 'clean_records') {
    $clean_days = isset($_POST['clean_days']) ? intval($_POST['clean_days']) : 30;
    if ($clean_days > 0) {
        RecentViewsCounter_Plugin::manualCleanOldRecords($clean_days);
        $clean_message = "已清理 {$clean_days} 天前的访问记录";
    }
}

// 处理缓存清理请求
if (isset($_POST['action']) && $_POST['action'] == 'clear_cache') {
    $cache_file = __TYPECHO_ROOT_DIR__ . $plugin_config->cache_file;
    if (file_exists($cache_file)) {
        if (unlink($cache_file)) {
            $cache_message = "缓存文件已清理";
        } else {
            $cache_message = "缓存文件清理失败";
        }
    } else {
        $cache_message = "缓存文件不存在";
    }
}

// 处理黑名单 AJAX 请求
if (isset($_POST['action']) && $_POST['action'] === 'get_blacklist') {
    if (ob_get_level()) ob_clean();
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
    $result = RecentViewsCounter_Blacklist::getList($page, $limit);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => true,
        'data' => $result['data'],
        'total' => $result['total'],
        'page' => $page,
        'limit' => $limit
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'add_to_blacklist') {
    if (ob_get_level()) ob_clean();
    $ip = isset($_POST['ip']) ? trim($_POST['ip']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $ok = RecentViewsCounter_Blacklist::add($ip, $reason);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => $ok,
        'message' => $ok ? 'IP 已加入黑名单' : 'IP 已存在或添加失败'
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'remove_from_blacklist') {
    if (ob_get_level()) ob_clean();
    $ip = isset($_POST['ip']) ? trim($_POST['ip']) : '';
    if (!empty($ip)) {
        RecentViewsCounter_Blacklist::remove($ip);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('success' => true, 'message' => 'IP 已从黑名单移除'), JSON_UNESCAPED_UNICODE);
    exit;
}

// 处理获取访问记录请求
if (isset($_POST['action']) && $_POST['action'] == 'get_visit_records') {
    // 清理输出缓冲区，确保没有其他输出
    if (ob_get_level()) {
        ob_clean();
    }
    
    $cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
    
    if ($cid > 0) {
        try {
            $db = Typecho_Db::get();
            // 获取该文章的访问记录（最近100条）
            $records = $db->fetchAll($db->select('*')
                                       ->from('table.views_records')
                                       ->where('cid = ?', $cid)
                                       ->order('visit_time', Typecho_Db::SORT_DESC)
                                       ->limit(100));
            
            // 确保数据格式正确
            if (!$records) {
                $records = array();
            }
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => true,
                'data' => $records
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'message' => '数据库查询失败：' . $e->getMessage()
            ), JSON_UNESCAPED_UNICODE);
        }
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'message' => '无效的文章ID'
        ), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 处理获取IP访问详情请求
if (isset($_POST['action']) && $_POST['action'] === 'get_ip_visit_details') {
    ob_clean();
    
    $ip = isset($_POST['ip']) ? trim($_POST['ip']) : '';
    
    if (!empty($ip)) {
        try {
            $db = Typecho_Db::get();
            
            // 获取该IP的访问记录，包含文章标题
            $records = $db->fetchAll($db->select('vr.visit_time', 'vr.ip', 'vr.user_agent', 'vr.location', 'vr.cid', 'c.title as article_title')
                                       ->from('table.views_records vr')
                                       ->join('table.contents c', 'vr.cid = c.cid', Typecho_Db::LEFT_JOIN)
                                       ->where('vr.ip = ?', $ip)
                                       ->order('vr.visit_time', Typecho_Db::SORT_DESC)
                                  ->limit(500));
            
            // 确保数据格式正确
            if (!$records) {
                $records = array();
            }
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => true,
                'data' => $records
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'message' => '数据库查询失败：' . $e->getMessage()
            ), JSON_UNESCAPED_UNICODE);
        }
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'message' => '无效的IP地址'
        ), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 处理IP归属地数据管理请求
if (isset($_POST['action']) && $_POST['action'] === 'get_ip_location_data') {
    ob_clean();
    
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
    $offset = ($page - 1) * $limit;
    
    try {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        // 获取总数
        $total = $db->fetchRow($db->select('COUNT(*) as count')
                                 ->from($prefix . 'ip_location'));
        
        // 获取分页数据
        $records = $db->fetchAll($db->select('*')
                                   ->from($prefix . 'ip_location')
                                   ->order('updated_time', Typecho_Db::SORT_DESC)
                                   ->limit($limit)
                                   ->offset($offset));
        
        if (!$records) {
            $records = array();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => true,
            'data' => $records,
            'total' => $total['count'],
            'page' => $page,
            'limit' => $limit
        ), JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'message' => '数据库查询失败：' . $e->getMessage()
        ), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 处理删除IP归属地数据请求
if (isset($_POST['action']) && $_POST['action'] === 'delete_ip_location') {
    ob_clean();
    
    $ip = isset($_POST['ip']) ? trim($_POST['ip']) : '';
    
    if (!empty($ip)) {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            
            $result = $db->query($db->delete($prefix . 'ip_location')
                                   ->where('ip = ?', $ip));
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => true,
                'message' => 'IP归属地数据已删除'
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ), JSON_UNESCAPED_UNICODE);
        }
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'message' => '无效的IP地址'
        ), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 处理清理过期IP归属地数据请求
if (isset($_POST['action']) && $_POST['action'] === 'clean_expired_ip_data') {
    ob_clean();
    
    $days = isset($_POST['days']) ? intval($_POST['days']) : 90;
    
    if ($days > 0) {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $expire_time = time() - ($days * 24 * 3600);
            
            $result = $db->query($db->delete($prefix . 'ip_location')
                                   ->where('updated_time < ?', $expire_time));
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => true,
                'message' => '过期IP归属地数据已清理'
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'message' => '清理失败：' . $e->getMessage()
            ), JSON_UNESCAPED_UNICODE);
        }
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'message' => '无效的天数参数'
        ), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 获取统计数据
$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$one_month_ago = time() - (30 * 24 * 3600);

// 获取数据库统计信息
$db_stats = RecentViewsCounter_Plugin::getDatabaseStats();

// 获取热门文章的时间范围参数（优先使用统一参数）
$article_time_range = isset($_GET['article_time_range']) ? $_GET['article_time_range'] : 
                     (isset($_GET['location_time_range']) ? $_GET['location_time_range'] : '7');
$article_days = parseTimeRangeDays($article_time_range);

// 获取热门文章
if ($article_days) {
    // 使用自定义查询获取指定天数的热门文章
    try {
        $time_limit = time() - ($article_days * 24 * 3600);
        $sql = $db->select('vr.cid', 'COUNT(*) as visit_count', 'c.title', 'c.slug')
                  ->from('table.views_records vr')
                  ->join('table.contents c', 'vr.cid = c.cid')
                  ->where('vr.visit_time > ?', $time_limit)
                  ->where('c.type = ?', 'post')
                  ->where('c.status = ?', 'publish')
                  ->group('vr.cid')
                  ->order('visit_count', Typecho_Db::SORT_DESC)
                  ->limit($top_count);
        $top_articles = $db->fetchAll($sql);
    } catch (Exception $e) {
        $top_articles = array();
    }
} else {
    // 全部时间的热门文章
    try {
        $sql = $db->select('vr.cid', 'COUNT(*) as visit_count', 'c.title', 'c.slug')
                  ->from('table.views_records vr')
                  ->join('table.contents c', 'vr.cid = c.cid')
                  ->where('c.type = ?', 'post')
                  ->where('c.status = ?', 'publish')
                  ->group('vr.cid')
                  ->order('visit_count', Typecho_Db::SORT_DESC)
                  ->limit($top_count);
        $top_articles = $db->fetchAll($sql);
    } catch (Exception $e) {
        $top_articles = array();
    }
}

// 获取统一时间范围参数（优先从location_time_range获取，因为changeGlobalTimeRange函数会设置这个参数）
// 注意：当选择7天时，JavaScript会删除URL参数，所以没有参数时默认为7天
$global_time_range = isset($_GET['location_time_range']) ? $_GET['location_time_range'] : '7';
$global_days = parseTimeRangeDays($global_time_range);
$global_time_condition = $global_days ? time() - $global_days * 86400 : null;

// 计算Bot IP列表（用于过滤统计概览）
// 规则：命中Bot UA 或 行为评分超过阈值即标记为Bot
$bot_ip_list = array();
$bot_ip_where = '';
try {
    $prefix = $db->getPrefix();
    // 查询该时间窗口内匹配Bot UA的IP
    $bot_conds = array();
    foreach (RecentViewsCounter_Plugin::getBotPatterns() as $p) {
        $bot_conds[] = "LOWER(user_agent) LIKE '%" . strtolower($p) . "%'";
    }
    $ua_sql = "SELECT DISTINCT ip FROM {$prefix}views_records WHERE (" . implode(' OR ', $bot_conds) . ")";
    if ($global_time_condition !== null) {
        $ua_sql .= " AND visit_time > " . intval($global_time_condition);
    }
    $ua_bot_ips = array();
    foreach ($db->fetchAll($db->query($ua_sql)) as $r) {
        $ua_bot_ips[$r['ip']] = true;
    }

    // 查询行为评分
    $ip_query = $db->select('ip',
        array('COUNT(*)' => 'cnt'),
        array('COUNT(DISTINCT cid)' => 'distinct_cids'),
        array('COUNT(DISTINCT user_agent)' => 'ua_variety'),
        array('MIN(visit_time)' => 'first_visit'),
        array('MAX(visit_time)' => 'last_visit'))
        ->from('table.views_records');
    if ($global_time_condition !== null) {
        $ip_query = $ip_query->where('visit_time > ?', $global_time_condition);
    }
    $ip_aggs = $db->fetchAll($ip_query->group('ip'));
    foreach ($ip_aggs as $ip_agg) {
        $score = 0;
        if ($ip_agg['cnt'] > 50) $score += 20;
        $focus_ratio = $ip_agg['cnt'] > 0 ? $ip_agg['distinct_cids'] / $ip_agg['cnt'] : 1;
        if ($focus_ratio < 0.3 && $ip_agg['cnt'] > 10) $score += 15;
        if ($ip_agg['ua_variety'] == 1 && $ip_agg['cnt'] > 5) $score += 25;
        $visit_duration = $ip_agg['last_visit'] - $ip_agg['first_visit'];
        $rate = $visit_duration > 0 ? $ip_agg['cnt'] / ($visit_duration / 3600) : 0;
        if ($rate > 5) $score += 10;
        // 命中Bot UA或行为评分≥60才标记
        if (isset($ua_bot_ips[$ip_agg['ip']]) || $score >= RecentViewsCounter_Plugin::BOT_THRESHOLD) {
            $bot_ip_list[] = $ip_agg['ip'];
        }
    }
    if (!empty($bot_ip_list)) {
        $escaped_ips = array();
        foreach ($bot_ip_list as $bot_ip) {
            $escaped_ips[] = "'" . str_replace("'", "''", $bot_ip) . "'";
        }
        $bot_ip_where = ' AND ip NOT IN (' . implode(',', $escaped_ips) . ')';
    }
} catch (Exception $e) {
    $bot_ip_list = array();
}

// 获取总访问量（排除行为检测Bot）
try {
    $prefix = $db->getPrefix();
    $sql = "SELECT COUNT(*) as count FROM {$prefix}views_records WHERE 1=1";
    if ($global_time_condition !== null) {
        $sql .= " AND visit_time > " . intval($global_time_condition);
    }
    $sql .= $bot_ip_where;
    $total_visits = $db->fetchRow($db->query($sql));
} catch (Exception $e) {
    $total_visits = array('count' => 0);
}

// 获取今日访问量（排除行为检测Bot）
$today_start = strtotime(date('Y-m-d 00:00:00'));
try {
    $prefix = $db->getPrefix();
    $sql = "SELECT COUNT(*) as count FROM {$prefix}views_records WHERE visit_time > " . intval($today_start) . $bot_ip_where;
    $today_visits = $db->fetchRow($db->query($sql));
} catch (Exception $e) {
    $today_visits = array('count' => 0);
}

// 获取Bot IP的总访问量（用于"总蜘蛛数"）
$total_bots_data = array('count' => 0);
if (!empty($bot_ip_list)) {
    try {
        $prefix = $db->getPrefix();
        $escaped_bot_ips = array();
        foreach ($bot_ip_list as $bot_ip) {
            $escaped_bot_ips[] = "'" . str_replace("'", "''", $bot_ip) . "'";
        }
        $sql = "SELECT COUNT(*) as count FROM {$prefix}views_records WHERE 1=1";
        if ($global_time_condition !== null) {
            $sql .= " AND visit_time > " . intval($global_time_condition);
        }
        $sql .= ' AND ip IN (' . implode(',', $escaped_bot_ips) . ')';
        $total_bots_data = $db->fetchRow($db->query($sql));
    } catch (Exception $e) {
        $total_bots_data = array('count' => 0);
    }
}

// 获取IP数统计
$bot_ip_count = count($bot_ip_list);
$total_distinct_ips = 0;
try {
    $prefix = $db->getPrefix();
    $sql = "SELECT COUNT(DISTINCT ip) as count FROM {$prefix}views_records WHERE 1=1";
    if ($global_time_condition !== null) {
        $sql .= " AND visit_time > " . intval($global_time_condition);
    }
    $r = $db->fetchRow($db->query($sql));
    $total_distinct_ips = $r ? intval($r['count']) : 0;
} catch (Exception $e) {
    $total_distinct_ips = 0;
}
$non_bot_ip_count = max(0, $total_distinct_ips - $bot_ip_count);

// 获取访问来源统计数据
$referer_stats = array();
if ($plugin_config && $plugin_config->enable_referer == '1') {
    $referer_time_range = isset($_GET['referer_time_range']) ? $_GET['referer_time_range'] : 
                         (isset($_GET['location_time_range']) ? $_GET['location_time_range'] : '7');
    $stats_days = parseTimeRangeDays($referer_time_range) ?? 3650;
    $referer_stats = RecentViewsCounter_Plugin::getRefererStats($stats_days);
}

$bot_patterns = RecentViewsCounter_Plugin::getBotPatterns();

// 获取地域分布统计的时间范围参数
$location_time_range = isset($_GET['location_time_range']) ? $_GET['location_time_range'] : '7';
$loc_days = parseTimeRangeDays($location_time_range);
$location_time_condition = $loc_days ? ' AND `visit_time` > ' . (time() - $loc_days * 86400) : '';

// 获取访问者地域分布统计
try {
    // 使用原生SQL查询避免Typecho查询构建器的语法问题
    $prefix = $db->getPrefix();
    $location_stats = $db->fetchAll($db->query("SELECT `location`, COUNT(*) as count FROM `{$prefix}views_records` WHERE `location` IS NOT NULL AND `location` != '' AND `location` != 'Unknown' AND `location` != 'Unkown' AND `location` != '内网' AND `location` != '本地'{$location_time_condition} GROUP BY `location` ORDER BY count DESC LIMIT 10"));
} catch (Exception $e) {
    $location_stats = array();
}

// 获取月度统计数据
$monthly_stats = RecentViewsCounter_Plugin::getMonthlyStats(24); // 获取最近24个月的数据

// 如果月度统计表为空，尝试更新当前月份的数据
if (empty($monthly_stats)) {
    try {
        RecentViewsCounter_Plugin::updateMonthlyStats();
        $monthly_stats = RecentViewsCounter_Plugin::getMonthlyStats(24);
    } catch (Exception $e) {
        $monthly_stats = array();
    }
}



// 获取访问最多的IP统计，同时检查是否为bot（复用bot_patterns数组）
$bot_regex_patterns = array();
foreach ($bot_patterns as $pattern) {
    $bot_regex_patterns[] = preg_quote($pattern, '/');
}
$bot_regex = '(' . implode('|', $bot_regex_patterns) . ')';

// 获取时间范围参数（优先使用统一参数）
$ip_time_range = isset($_GET['ip_time_range']) ? $_GET['ip_time_range'] : 
                (isset($_GET['location_time_range']) ? $_GET['location_time_range'] : '7');
$ip_days = parseTimeRangeDays($ip_time_range);
$time_condition = $ip_days ? time() - $ip_days * 86400 : null;

// 高级bot检测：基于访问频率和行为模式
try {
    $query = $db->select('ip', 
                        array('MAX(location)' => 'location'),
                        array('COUNT(*)' => 'count'),
                        array('COUNT(DISTINCT cid)' => 'distinct_cids'),
                        array('COUNT(DISTINCT user_agent)' => 'ua_variety'),
                        array('MIN(visit_time)' => 'first_visit'),
                        array('MAX(visit_time)' => 'last_visit'),
                        array('MIN(user_agent)' => 'sample_user_agent_min'),
                        array('MAX(user_agent)' => 'sample_user_agent'))
                ->from('table.views_records');
    
    // 如果有时间条件，添加WHERE子句
    if ($time_condition !== null) {
        $query = $query->where('visit_time >= ?', $time_condition);
    }
    
    $top_ips = $db->fetchAll($query->group('ip')
                                  ->order('count', Typecho_Db::SORT_DESC)
                                  ->limit(200));
} catch (Exception $e) {
    $top_ips = array();
}

// 获取每个IP的Bot UA匹配数（替代仅依赖MAX(user_agent)的采样方式）
$bot_ua_counts = array();
if (!empty($top_ips)) {
    try {
        $prefix = $db->getPrefix();
        $bot_conditions = array();
        foreach ($bot_patterns as $pattern) {
            $bot_conditions[] = "LOWER(user_agent) LIKE '%" . strtolower($pattern) . "%'";
        }
        $bot_sql = "SELECT ip, COUNT(*) as bot_count FROM {$prefix}views_records WHERE (" . implode(' OR ', $bot_conditions) . ")";
        if ($time_condition !== null) {
            $bot_sql .= " AND visit_time >= " . intval($time_condition);
        }
        $bot_sql .= " GROUP BY ip";
        $bot_results = $db->fetchAll($db->query($bot_sql));
        foreach ($bot_results as $row) {
            $bot_ua_counts[$row['ip']] = intval($row['bot_count']);
        }
    } catch (Exception $e) {
        error_log('RecentViewsCounter: Bot UA count query failed: ' . $e->getMessage());
    }
}

// 为每个IP计算综合bot评分
foreach ($top_ips as &$ip_data) {
    $behavioral_score = 0;
    
    $bot_ua_count = isset($bot_ua_counts[$ip_data['ip']]) ? $bot_ua_counts[$ip_data['ip']] : 0;
    $detected_bot_name = '';
    if ($bot_ua_count > 0) {
        $uas_to_check = array($ip_data['sample_user_agent'] ?: '', $ip_data['sample_user_agent_min'] ?: '');
        foreach ($uas_to_check as $ua) {
            foreach ($bot_patterns as $pattern) {
                if (stripos($ua, $pattern) !== false) {
                    $detected_bot_name = $pattern;
                    break 2;
                }
            }
        }
        if (empty($detected_bot_name)) {
            $detected_bot_name = 'Bot';
        }
    }
    
    // 行为评分（不含UA匹配）
    if ($ip_data['count'] > 50) $behavioral_score += 20;
    $focus_ratio = $ip_data['count'] > 0 ? $ip_data['distinct_cids'] / $ip_data['count'] : 1;
    if ($focus_ratio < 0.3 && $ip_data['count'] > 10) $behavioral_score += 15;
    if ($ip_data['ua_variety'] == 1 && $ip_data['count'] > 5) $behavioral_score += 25;
    $visit_duration = $ip_data['last_visit'] - $ip_data['first_visit'];
    $visit_rate = $visit_duration > 0 ? $ip_data['count'] / ($visit_duration / 3600) : 0;
    if ($visit_rate > 5) $behavioral_score += 10;
    
    $ip_data['bot_score'] = ($bot_ua_count > 0 ? 40 : 0) + $behavioral_score;
    $ip_data['is_bot'] = ($bot_ua_count > 0 || $behavioral_score >= RecentViewsCounter_Plugin::BOT_THRESHOLD) ? 1 : 0;
    $ip_data['is_bot_ua'] = ($bot_ua_count > 0) ? 1 : 0;
    $ip_data['detected_bot_name'] = $detected_bot_name;
    $ip_data['is_high_frequency'] = $ip_data['count'] > 50 ? 1 : 0;
    $ip_data['is_focused_crawler'] = ($focus_ratio < 0.3 && $ip_data['count'] > 10) ? 1 : 0;
}

// 补全未能通过MIN/MAX UA采样识别出具体Bot名称的IP
$need_name_ips = array();
foreach ($top_ips as &$ip_data) {
    if ($ip_data['is_bot_ua'] && $ip_data['detected_bot_name'] === 'Bot') {
        $need_name_ips[] = $ip_data['ip'];
    }
}
if (!empty($need_name_ips)) {
    try {
        $prefix = $db->getPrefix();
        $esc_ips = array();
        foreach ($need_name_ips as $ip) {
            $esc_ips[] = "'" . str_replace("'", "''", $ip) . "'";
        }
        $ua_samples = $db->fetchAll($db->query("SELECT ip, user_agent FROM {$prefix}views_records WHERE ip IN (" . implode(',', $esc_ips) . ") GROUP BY ip, user_agent"));
        $ip_names = array();
        foreach ($ua_samples as $s) {
            $ip = $s['ip'];
            if (isset($ip_names[$ip])) continue;
            $ua = $s['user_agent'] ?: '';
            foreach ($bot_patterns as $pattern) {
                if (stripos($ua, $pattern) !== false) {
                    $ip_names[$ip] = $pattern;
                    break;
                }
            }
        }
        foreach ($top_ips as &$ip_data) {
            if (isset($ip_names[$ip_data['ip']])) {
                $ip_data['detected_bot_name'] = $ip_names[$ip_data['ip']];
            }
        }
    } catch (Exception $e) {
        // 静默失败，保留现有名称
    }
}

// 加载黑名单IP列表，标记每个IP的黑名单状态
try {
    RecentViewsCounter_Blacklist::createTable();
} catch (Exception $e) {}
$blacklisted_ips = array();
try {
    $bl_result = RecentViewsCounter_Blacklist::getList(1, 99999);
    foreach ($bl_result['data'] as $bl_item) {
        $blacklisted_ips[$bl_item['ip']] = true;
    }
} catch (Exception $e) {}

// 首次加载黑名单列表数据（分页）
try {
    $blacklist_result = RecentViewsCounter_Blacklist::getList(1, 20);
    $blacklist_total = $blacklist_result['total'];
} catch (Throwable $e) {
    $blacklist_result = array('data' => array(), 'total' => 0);
    $blacklist_total = 0;
}

foreach ($top_ips as &$ip_data) {
    $ip_data['is_blacklisted'] = isset($blacklisted_ips[$ip_data['ip']]) ? 1 : 0;
}
unset($ip_data);

// 提取非Bot TOP 20用于切换显示
$non_bot_top_ips = array();
foreach ($top_ips as $ip_data) {
    if (!$ip_data['is_bot']) {
        $non_bot_top_ips[] = $ip_data;
        if (count($non_bot_top_ips) >= 20) break;
    }
}

// "全部"视图保留前20条
$display_top_ips = array_slice($top_ips, 0, 20);

// 获取访问趋势数据（不受全局时间选择限制，固定最近30天）
$week_data = array();

try {
    $thirty_days_ago = time() - 30 * 24 * 3600;
    $prefix = $db->getPrefix();
    $tz_offset = intval(date('Z'));
    
    // 1. 按IP聚合，计算Bot评分（仅加载独特IP）
    $ip_query = $db->select(
        'ip',
        array('COUNT(*)' => 'cnt'),
        array('COUNT(DISTINCT cid)' => 'distinct_cids'),
        array('COUNT(DISTINCT user_agent)' => 'ua_variety'),
        array('MIN(visit_time)' => 'first_visit'),
        array('MAX(visit_time)' => 'last_visit'),
        array('MAX(user_agent)' => 'sample_ua')
    )->from('table.views_records')
     ->where('visit_time > ?', $thirty_days_ago)
     ->group('ip');
    
    $ip_aggs = $db->fetchAll($ip_query);
    $chart_bot_ips = array();
    $bot_patterns = RecentViewsCounter_Plugin::getBotPatterns();
    foreach ($ip_aggs as $agg) {
        $ua_match = false;
        $ua = strtolower($agg['sample_ua'] ?: '');
        foreach ($bot_patterns as $p) {
            if (strpos($ua, strtolower($p)) !== false) { $ua_match = true; break; }
        }
        $score = 0;
        if ($agg['cnt'] > 50) $score += 20;
        $focus = $agg['cnt'] > 0 ? $agg['distinct_cids'] / $agg['cnt'] : 1;
        if ($focus < 0.3 && $agg['cnt'] > 10) $score += 15;
        if ($agg['ua_variety'] == 1 && $agg['cnt'] > 5) $score += 25;
        $dur = $agg['last_visit'] - $agg['first_visit'];
        if ($dur > 0 && $agg['cnt'] / ($dur / 3600) > 5) $score += 10;
        if ($ua_match || $score >= RecentViewsCounter_Plugin::BOT_THRESHOLD) {
            $chart_bot_ips[] = $agg['ip'];
        }
    }
    
    // 2. 每日总访问量（SQL聚合）
    $daily_totals = $db->fetchAll($db->query(
        "SELECT CAST((visit_time + {$tz_offset}) / 86400 AS INTEGER) AS day_start, COUNT(*) AS cnt
         FROM {$prefix}views_records
         WHERE visit_time > {$thirty_days_ago}
         GROUP BY day_start ORDER BY day_start"
    ));
    
    // 3. 每日Bot访问量（SQL聚合）
    $bot_map = array();
    if (!empty($chart_bot_ips)) {
        $escaped = array();
        foreach ($chart_bot_ips as $bip) {
            $escaped[] = "'" . str_replace("'", "''", $bip) . "'";
        }
        $daily_bots = $db->fetchAll($db->query(
            "SELECT CAST((visit_time + {$tz_offset}) / 86400 AS INTEGER) AS day_start, COUNT(*) AS cnt
              FROM {$prefix}views_records
              WHERE visit_time > {$thirty_days_ago}
                AND ip IN (" . implode(',', $escaped) . ")
              GROUP BY day_start ORDER BY day_start"
        ));
        foreach ($daily_bots as $row) {
            $bot_map[intval($row['day_start'])] = intval($row['cnt']);
        }
    }
    
    // 合并生成图表数据
    foreach ($daily_totals as $row) {
        $ds = intval($row['day_start']);
        $bot = isset($bot_map[$ds]) ? $bot_map[$ds] : 0;
        $total = intval($row['cnt']);
        $week_data[] = array(
            'date' => date('m-d', $ds * 86400 - $tz_offset),
            'full_date' => date('Y-m-d', $ds * 86400 - $tz_offset),
            'visits' => $total,
            'real_users' => $total - $bot,
            'bots' => $bot
        );
    }
} catch (Exception $e) {
    $week_data = array();
}
?>

<div class="main" id="tab_content_stats">
    <div class="body container">
        <div class="colgroup">
            <div class="col-mb-12">


                <!-- 统一时间范围选择器 -->
                <div class="typecho-page-main" style="margin-bottom: 20px;">
                    <div style="background: #fff; padding: 15px; border: 1px solid #e9e9e6; border-radius: 4px;">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 20px;">
                            <h4 style="margin: 0; color: #495057;">📊 数据时间范围</h4>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                                <label style="font-size: 14px; color: #666;">选择时间范围：</label>
                                <select id="global_time_range" onchange="changeGlobalTimeRange()" style="width: 140px; min-width: 140px; height: 36px; flex: 0 0 140px; padding: 4px 32px 4px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; line-height: 20px; background: #fff; box-sizing: border-box; vertical-align: middle;">
                                    <option value="7">近7天</option>
                                    <option value="30">近30天</option>
                                    <option value="all">全部时间</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 访问统计 -->
                <div class="typecho-page-title">
                    <h2><?php _e('访问统计'); ?></h2>
                </div>
                
                <!-- 统计概览 -->
                <div class="row typecho-page-main" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; max-width: 900px;">
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #467b96;"><?php echo $total_visits ? $total_visits['count'] : 0; ?></h3>
                        <p style="margin: 5px 0 0 0;">非蜘蛛访问次数</p>
                    </div>
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #dc3545;"><?php echo $total_bots_data ? $total_bots_data['count'] : 0; ?></h3>
                        <p style="margin: 5px 0 0 0;">总蜘蛛访问次数</p>
                    </div>
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #467b96;"><?php echo $non_bot_ip_count; ?></h3>
                        <p style="margin: 5px 0 0 0;">非蜘蛛IP数</p>
                    </div>
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #dc3545;"><?php echo $bot_ip_count; ?></h3>
                        <p style="margin: 5px 0 0 0;">蜘蛛IP数</p>
                    </div>
                </div>

                <!-- 访问趋势 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>每日访问趋势 <span style="font-size: 14px; color: #666; font-weight: normal;">(最近30天，不受上方时间选择器影响)</span></h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: auto;">
                        <canvas id="visitChart" style="width: 100%; max-width: 1200px; height: 300px; display: block; margin: 0 auto;"></canvas>
                    </div>
                </div>

                <!-- 月度访问统计 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>月度访问统计 <span style="font-size: 14px; color: #666; font-weight: normal;">(最近24个月，不受上方时间选择器影响)</span></h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: auto;">
                        <canvas id="monthlyChart" style="width: 100%; max-width: 1200px; height: 300px; display: block; margin: 0 auto;"></canvas>
                    </div>
                </div>

                <!-- 地域分布统计 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问者地域分布 TOP 10</h3>
                    <?php if (!empty($location_stats)): ?>
                    <div class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 600px;">
                            <colgroup>
                                <col width="10%"/>
                                <col width="60%"/>
                                <col width="20%"/>
                                <col width="10%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>地区</th>
                                    <th>访问次数</th>
                                    <th>占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_location_visits = array_sum(array_column($location_stats, 'count'));
                                $rank = 1; 
                                foreach ($location_stats as $location): 
                                    $percentage = $total_location_visits > 0 ? round(($location['count'] / $total_location_visits) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($location['location'] ?: '未知地区'); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <?php echo number_format($location['count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #6c757d;">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="typecho-page-main">
                        <div class="message notice">
                            <p>暂无地域访问数据</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 访问来源统计 -->
                <?php 
                $has_referer_data = false;
                if ($plugin_config && $plugin_config->enable_referer == '1' && !empty($referer_stats)) {
                    $total_referer_count = $referer_stats['search']['count'] + $referer_stats['social']['count'] + $referer_stats['external']['count'] + $referer_stats['internal']['count'] + $referer_stats['direct']['count'];
                    $has_referer_data = $total_referer_count > 0;
                }
                if ($has_referer_data): ?>
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问来源统计</h3>
                    
                    <!-- 来源类型概览 -->
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; max-width: 900px;">
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #f3e5f5; border-radius: 4px;">
                            <h4 style="margin: 0; color: #9c27b0;"><?php echo $referer_stats['direct']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">直接访问</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #e8f5e8; border-radius: 4px;">
                            <h4 style="margin: 0; color: #28a745;"><?php echo $referer_stats['search']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">搜索引擎</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #e3f2fd; border-radius: 4px;">
                            <h4 style="margin: 0; color: #2196f3;"><?php echo $referer_stats['social']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">社交媒体</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #fff3e0; border-radius: 4px;">
                            <h4 style="margin: 0; color: #ff9800;"><?php echo $referer_stats['external']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">外部链接</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #f0f4ff; border-radius: 4px;">
                            <h4 style="margin: 0; color: #6366f1;"><?php echo $referer_stats['internal']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">站内跳转</p>
                        </div>
                    </div>
                    
                    <!-- 详细来源列表 -->
                    <div class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 700px;">
                            <colgroup>
                                <col width="15%"/>
                                <col width="35%"/>
                                <col width="20%"/>
                                <col width="15%"/>
                                <col width="15%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>类型</th>
                                    <th>来源</th>
                                    <th>访问次数</th>
                                    <th>占比</th>
                                    <th>排名</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_referer_visits = $referer_stats['search']['count'] + $referer_stats['social']['count'] + $referer_stats['external']['count'] + $referer_stats['internal']['count'] + $referer_stats['direct']['count'];
                                $all_sources = array();
                                
                                // 合并所有来源数据
                                foreach ($referer_stats as $type => $data) {
                                    foreach ($data['items'] as $name => $count) {
                                        $all_sources[] = array(
                                            'type' => $type,
                                            'name' => $name,
                                            'count' => $count
                                        );
                                    }
                                }
                                
                                // 按访问量排序
                                usort($all_sources, function($a, $b) {
                                    return $b['count'] - $a['count'];
                                });
                                
                                // 显示前20个来源
                                $rank = 1;
                                foreach (array_slice($all_sources, 0, 20) as $source):
                                    $percentage = $total_referer_visits > 0 ? round(($source['count'] / $total_referer_visits) * 100, 1) : 0;
                                    
                                    // 设置类型标签样式
                                    $type_styles = array(
                                        'search' => 'background: #28a745; color: white;',
                                        'social' => 'background: #2196f3; color: white;',
                                        'external' => 'background: #ff9800; color: white;',
                                        'internal' => 'background: #6366f1; color: white;',
                                        'direct' => 'background: #9c27b0; color: white;'
                                    );
                                    
                                    $type_names = array(
                                        'search' => '搜索',
                                        'social' => '社交',
                                        'external' => '外链',
                                        'internal' => '站内',
                                        'direct' => '直接'
                                    );
                                ?>
                                <tr>
                                    <td>
                                        <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; <?php echo $type_styles[$source['type']]; ?>">
                                            <?php echo $type_names[$source['type']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($source['name']); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <?php echo number_format($source['count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #6c757d;">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #007cba; font-weight: bold;">
                                            #<?php echo $rank++; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($plugin_config && $plugin_config->enable_referer == '1'): ?>
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问来源统计</h3>
                    <div class="message notice">
                        <p>暂无访问来源数据</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- IP访问统计 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问最多的IP地址 TOP 20
                        <select id="ip_view_selector" onchange="switchIpView()" style="font-size: 13px; padding: 2px 8px; margin-left: 10px; vertical-align: middle;">
                            <option value="all">全部</option>
                            <option value="nonbot">仅非Bot</option>
                        </select>
                        <small style="color: #666;" 
                                title="<?php echo htmlspecialchars($bot_detection_info); ?>">
                             (智能Bot检测)
                         </small>
                    </h3>
                    <?php if (!empty($display_top_ips) || !empty($non_bot_top_ips)): ?>
                    <!-- 全部IP视图 -->
                    <div id="ip_view_all" class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 1000px;">
                            <colgroup>
                                <col width="5%"/>
                                <col width="16%"/>
                                <col width="16%"/>
                                <col width="10%"/>
                                <col width="8%"/>
                                <col width="9%"/>
                                <col width="14%"/>
                                <col width="12%"/>
                                <col width="10%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>IP地址</th>
                                    <th>归属地</th>
                                    <th>访问次数</th>
                                    <th>占比</th>
                                    <th>Bot评分</th>
                                    <th>检测详情</th>
                                    <th>访问详情</th>
                                    <th>黑名单</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_ip_visits = array_sum(array_column($display_top_ips, 'count'));
                                $rank = 1; 
                                foreach ($display_top_ips as $ip_data): 
                                    $percentage = $total_ip_visits > 0 ? round(($ip_data['count'] / $total_ip_visits) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <?php 
                                        $ip_color = $ip_data['is_bot'] ? '#ff6b35' : '#333';
                                        $bot_level = '';
                                        if ($ip_data['is_bot']) $bot_level = 'Bot';
                                        else $bot_level = '正常用户';
                                        $ip_title = 'title="' . $bot_level . ' (评分: ' . $ip_data['bot_score'] . '分)"';
                                        ?>
                                        <strong style="font-family: monospace; color: <?php echo $ip_color; ?>;" <?php echo $ip_title; ?>>
                                            <?php echo htmlspecialchars($ip_data['ip']); ?>
                                            <?php if ($ip_data['is_bot']): ?>
                                                <span style="font-size: 12px; color: #ff6b35; margin-left: 5px;">🤖</span>
                                            <?php endif; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($ip_data['location'] ?: '未知地区'); ?>
                                    </td>
                                    <td>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <?php echo number_format($ip_data['count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #6c757d;">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $score_color = '#28a745'; // 绿色 - 正常
                                        if ($ip_data['is_bot']) $score_color = '#dc3545'; // 红色 - Bot
                                        else $score_color = '#28a745'; // 绿色 - 正常
                                        ?>
                                        <span style="color: <?php echo $score_color; ?>; font-weight: bold;" 
                                              title="<?php echo htmlspecialchars($bot_detection_info); ?>">
                                            <?php echo $ip_data['bot_score']; ?>分
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $detection_details = array();
                                        if ($ip_data['is_bot_ua'] && !empty($ip_data['detected_bot_name'])) {
                                            $detection_details[] = $ip_data['detected_bot_name'];
                                        } elseif ($ip_data['is_bot_ua']) {
                                            $detection_details[] = 'UA识别';
                                        }
                                        if ($ip_data['is_high_frequency']) $detection_details[] = '高频访问';
                                        if ($ip_data['is_focused_crawler']) $detection_details[] = '专注爬取';
                                        if ($ip_data['ua_variety'] == 1 && $ip_data['count'] > 10) $detection_details[] = '单一UA';
                                        
                                        $visit_duration = $ip_data['last_visit'] - $ip_data['first_visit'];
                                        $visit_rate = $visit_duration > 0 ? $ip_data['count'] / ($visit_duration / 3600) : 0;
                                        if ($visit_rate > 10) $detection_details[] = '高频率';
                                        
                                        if (empty($detection_details)) {
                                            echo '<span style="color: #28a745;">正常</span>';
                                        } else {
                                            echo '<span style="color: #dc3545; font-size: 11px;">' . implode(', ', $detection_details) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0);" onclick="showIpVisitDetails('<?php echo htmlspecialchars($ip_data['ip']); ?>')" 
                                           style="color: #0073aa; text-decoration: none; font-size: 12px;">
                                            📋 查看详情
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($ip_data['is_blacklisted']): ?>
                                            <span style="color: #dc3545; font-size: 12px;">☑ 已拉黑</span>
                                        <?php else: ?>
                                            <a href="javascript:void(0);" onclick="addToBlacklist('<?php echo htmlspecialchars($ip_data['ip']); ?>', this)"
                                               style="color: #999; text-decoration: none; font-size: 12px;"
                                               title="加入黑名单">❌ 拉黑</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- 仅非Bot视图 -->
                    <div id="ip_view_nonbot" class="typecho-table-wrap" style="overflow-x: auto; display: none;">
                        <?php if (!empty($non_bot_top_ips)): ?>
                        <table class="typecho-list-table" style="min-width: 1000px;">
                            <colgroup>
                                <col width="5%"/>
                                <col width="16%"/>
                                <col width="16%"/>
                                <col width="10%"/>
                                <col width="8%"/>
                                <col width="9%"/>
                                <col width="14%"/>
                                <col width="12%"/>
                                <col width="10%"/>
                            </colgroup>
                            <thead><tr>
                                <th>排名</th><th>IP地址</th><th>归属地</th>
                                <th>访问次数</th><th>占比</th>
                                <th>Bot评分</th><th>检测详情</th><th>访问详情</th><th>黑名单</th>
                            </tr></thead>
                            <tbody>
                                <?php $rank = 1; $total_nb = array_sum(array_column($non_bot_top_ips, 'count')); foreach ($non_bot_top_ips as $ip_data): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><strong style="font-family: monospace; color: #467b96;"><?php echo htmlspecialchars($ip_data['ip']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ip_data['location'] ?: '未知地区'); ?></td>
                                    <td><span style="color: #28a745; font-weight: bold;"><?php echo number_format($ip_data['count']); ?></span></td>
                                    <td><span style="color: #6c757d;"><?php echo $total_nb > 0 ? round(($ip_data['count'] / $total_nb) * 100, 1) : 0; ?>%</span></td>
                                    <td><span style="color: #28a745; font-weight: bold;"><?php echo $ip_data['bot_score']; ?>分</span></td>
                                    <td><span style="color: #28a745;">正常</span></td>
                                    <td><a href="javascript:void(0);" onclick="showIpVisitDetails('<?php echo htmlspecialchars($ip_data['ip']); ?>')" style="color: #0073aa; font-size: 12px;">📋 查看详情</a></td>
                                    <td>
                                        <?php if ($ip_data['is_blacklisted']): ?>
                                            <span style="color: #dc3545; font-size: 12px;">☑ 已拉黑</span>
                                        <?php else: ?>
                                            <a href="javascript:void(0);" onclick="addToBlacklist('<?php echo htmlspecialchars($ip_data['ip']); ?>', this)"
                                               style="color: #999; text-decoration: none; font-size: 12px;"
                                               title="加入黑名单">❌ 拉黑</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="typecho-page-main"><div class="message notice"><p>当前时间范围内无非Bot IP数据</p></div></div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="typecho-page-main">
                        <div class="message notice">
                            <p>暂无IP访问数据</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 黑名单管理入口 -->
                    <div style="background: #fff8f0; padding: 12px 15px; border: 1px solid #ffe0b2; border-radius: 4px; margin-top: 15px;">
                        <span style="color: #e65100; font-size: 13px;">🛡️ <strong>IP黑名单：</strong>可手动将恶意IP加入黑名单，加入后该IP将无法访问网站。
                        <a href="#" onclick="switchTab('blacklist'); return false;"
                           style="color: #0073aa; text-decoration: none; margin-left: 8px;">管理黑名单 →</a></span>
                    </div>

                    <!-- Bot检测说明 -->
                    <div id="botDetectionInfo" style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin-top: 15px; display: none;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">🤖 智能Bot检测说明</h4>
                        <div style="font-size: 13px; color: #6c757d; line-height: 1.5;">
                            <p style="margin: 5px 0;"><strong>检测机制：</strong>基于多维度分析，包括User-Agent识别、访问频率、行为模式等</p>
                            <p style="margin: 5px 0;"><strong>评分规则：</strong></p>
                            <ul style="margin: 5px 0; padding-left: 20px;">
                                <li><strong>UA识别</strong> (40分): 检测到已知爬虫User-Agent</li>
                                <li><strong>高频访问</strong> (20分): 访问次数超过50次</li>
                                <li><strong>专注爬取</strong> (15分): 访问内容集中度高 (访问文章种类/总访问 < 0.3)</li>
                                <li><strong>单一UA</strong> (25分): 只使用一种User-Agent且访问超过5次</li>
                                <li><strong>高频率</strong> (10分): 每小时访问超过5次</li>
                            </ul>
                            <p style="margin: 5px 0;"><strong>判定规则：</strong>
                                <span style="color: #467b96;">UA匹配 → Bot</span> 或
                                <span style="color: #dc3545;">行为评分 ≥<?php echo RecentViewsCounter_Plugin::BOT_THRESHOLD; ?>分 → Bot</span>
                            </p>
                            <p style="margin: 5px 0; font-size: 12px; color: #999;">
                                (IP TOP 20表显示纯行为评分，不含UA识别分)
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 热门文章列表 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>近期热门文章 TOP <?php echo $top_count; ?></h3>
                    <?php if (!empty($top_articles)): ?>
                    <div class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 700px;">
                            <colgroup>
                                <col width="5%"/>
                                <col width="50%"/>
                                <col width="12%"/>
                                <col width="18%"/>
                                <col width="15%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>文章标题</th>
                                    <th>访问次数</th>
                                    <th>访问记录</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($top_articles as $article): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <?php 
                        try {
                            $full_article = $db->fetchRow($db->select('*')->from('table.contents')->where('cid = ?', $article['cid']));
                        } catch (Exception $e) {
                            $full_article = null;
                        }
                                        if ($full_article) {
                                            $permalink = Typecho_Router::url('post', $full_article, $options->index);
                                        } else {
                                            $permalink = '#';
                                        }
                                        ?>
                                        <a href="<?php echo $permalink; ?>" 
                                           target="_blank" 
                                           title="点击查看文章" 
                                           style="color: #467b96; text-decoration: none; font-weight: bold;">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                        <span style="color: #999; font-size: 12px; margin-left: 8px;">🔗</span>
                                    </td>
                                    <td>
                                        <span style="color: #467b96; font-weight: bold;">
                                            <?php echo $article['visit_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0)" 
                                           onclick="showVisitRecords(<?php echo $article['cid']; ?>, '<?php echo htmlspecialchars(addslashes($article['title'])); ?>')" 
                                           style="color: #467b96; text-decoration: none;" 
                                           title="查看该文章的访问记录">
                                            📊 查看记录
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo Typecho_Common::url('write-post.php?cid=' . $article['cid'], $options->adminUrl); ?>">
                                            编辑
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="typecho-page-main">
                        <div class="message notice">
                            <p>本月暂无访问记录</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 数据库统计信息 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>数据库统计</h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: hidden; overflow-wrap: anywhere;">
                        <?php if (isset($clean_message)): ?>
                        <div style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; border-radius: 4px;">
                            <?php echo htmlspecialchars($clean_message); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($cache_message)): ?>
                        <div style="background: #d1ecf1; color: #0c5460; padding: 10px; margin-bottom: 15px; border: 1px solid #bee5eb; border-radius: 4px;">
                            <?php echo htmlspecialchars($cache_message); ?>
                        </div>
                        <?php endif; ?> 
                        
                        <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                            <p><strong>数据库信息：</strong>总访问记录 <?php echo number_format($db_stats['total_records']); ?> 条 ｜ 最近30天记录 <?php echo number_format($db_stats['recent_records']); ?> 条<?php if ($db_stats['earliest_time']): ?> ｜ 最早记录时间 <?php echo date('Y-m-d H:i:s', $db_stats['earliest_time']); ?><?php endif; ?></p>
                            
                            <form method="post" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 10px; width: 100%; max-width: 100%;">
                                <input type="hidden" name="action" value="clean_records">
                                <label for="clean_days">清理</label>
                                <input type="number" name="clean_days" id="clean_days" value="30" min="1" max="365" style="width: 60px;">
                                <label for="clean_days">天前的记录</label>
                                <button type="submit" class="btn btn-s" onclick="return confirm('确定要清理过期的访问记录吗？此操作不可恢复！')" style="white-space: normal; max-width: 100%;">立即清理</button>
                            </form>
                            <p style="margin-top: 10px; color: #666; font-size: 12px;">建议定期清理过期记录以节省存储空间</p>
                        </div>
                        <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                            <?php 
                            $cache_file = __TYPECHO_ROOT_DIR__ . $plugin_config->cache_file;
                            $cache_enabled = $plugin_config && $plugin_config->enable_cache == '1';
                            ?>
                            <p><strong>缓存信息：</strong>缓存状态 <?php if ($cache_enabled): ?><span style="color: #28a745;">已启用</span><?php else: ?><span style="color: #dc3545;">已禁用</span><?php endif; ?><?php if ($cache_enabled): ?> ｜ 缓存文件 <?php if (file_exists($cache_file)): ?><span style="color: #28a745;">存在</span> (<?php echo date('Y-m-d H:i:s', filemtime($cache_file)); ?>)<?php else: ?><span style="color: #ffc107;">不存在</span><?php endif; ?> ｜ 缓存间隔 <?php echo intval($plugin_config->cache_time); ?> 秒<?php endif; ?></p>
                            
                            <?php if ($cache_enabled): ?>
                            <form method="post" style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 10px; width: 100%; max-width: 100%;"><input type="hidden" name="action" value="clear_cache"><button type="submit" class="btn btn-s" onclick="return confirm('确定要清理缓存文件吗？下次访问时将重新生成缓存。')" style="white-space: normal; max-width: 100%;">清理缓存</button></form>
                            <p style="margin-top: 10px; color: #666; font-size: 12px;">清理缓存后下次访问时会自动重新生成</p>
                            <?php else: ?><p style="color: #666;">缓存功能已禁用，可在插件配置中启用</p><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- IP归属地数据管理 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>IP归属地数据管理</h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: hidden; overflow-wrap: anywhere;">
                        <?php if (isset($ip_clean_message)): ?>
                        <div style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; border-radius: 4px;">
                            <?php echo htmlspecialchars($ip_clean_message); ?>
                        </div>
                        <?php endif; ?>
                        
                            <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                                <?php
                                try {
                                    $ip_stats = $db->fetchRow($db->select('COUNT(*) as total_ips')
                                                                ->from('table.ip_location'));
                                    $recent_ip_stats = $db->fetchRow($db->select('COUNT(*) as recent_ips')
                                                                       ->from('table.ip_location')
                                                                       ->where('created_time > ?', time() - 30 * 24 * 3600));
                                    $oldest_ip = $db->fetchRow($db->select('MIN(created_time) as oldest_time')
                                                                 ->from('table.ip_location'));
                                } catch (Exception $e) {
                                    $ip_stats = array('total_ips' => 0);
                                    $recent_ip_stats = array('recent_ips' => 0);
                                    $oldest_ip = array('oldest_time' => null);
                                }
                                ?>
                                <p><strong>本地IP数据库信息：</strong>总IP记录 <?php echo number_format($ip_stats['total_ips']); ?> 条 ｜ 最近30天新增 <?php echo number_format($recent_ip_stats['recent_ips']); ?> 条<?php if ($oldest_ip['oldest_time']): ?> ｜ 最早记录时间 <?php echo date('Y-m-d H:i:s', $oldest_ip['oldest_time']); ?><?php endif; ?></p>
                                
                                <div style="margin-top: 15px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px;"><button onclick="loadIpLocationData(1)" class="btn btn-s" style="white-space: normal; max-width: 100%;">查看IP数据</button><form method="post" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; max-width: 100%;"><input type="hidden" name="action" value="clean_expired_ip_data"><label for="ip_clean_days">清理</label><input type="number" name="clean_days" id="ip_clean_days" value="90" min="1" max="365" style="width: 60px;"><label for="ip_clean_days">天前的IP数据</label><button type="submit" class="btn btn-s" onclick="return confirm('确定要清理过期的IP归属地数据吗？此操作不可恢复！')" style="white-space: normal; max-width: 100%;">清理过期数据</button></form></div>
                                <p style="margin-top: 10px; color: #666; font-size: 12px;">建议定期清理过期IP数据以节省存储空间</p>
                            </div>
                            <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                                <p><strong>IP归属地配置：</strong>本地数据库 <?php if ($plugin_config && $plugin_config->enable_local_ip_db == '1'): ?><span style="color: #28a745;">已启用</span><?php else: ?><span style="color: #dc3545;">已禁用</span><?php endif; ?> ｜ 缓存天数 <?php echo $plugin_config ? intval($plugin_config->ip_cache_days) : 30; ?> 天 ｜ 自动清理 <?php if ($plugin_config && $plugin_config->auto_clean_ip == '1'): ?><span style="color: #28a745;">已启用</span><?php else: ?><span style="color: #dc3545;">已禁用</span><?php endif; ?></p>
                                <p style="color: #666; font-size: 12px; margin-top: 15px;">可在插件配置中修改这些设置</p>
                            </div>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>

<!-- 黑名单管理 -->
<div id="tab_content_blacklist" style="display:none;">
    <div class="main">
        <div class="body container">
            <div class="colgroup">
                <div class="col-mb-12">
                    <div style="margin-bottom: 15px;">
                        <a href="#" onclick="switchTab('stats'); return false;" style="color: #467b96; text-decoration: none;">← 返回访问统计</a>
                    </div>
                    <!-- 添加 IP 到黑名单 -->
                    <div class="typecho-page-main" style="margin-bottom: 20px;">
                        <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; border-radius: 4px;">
                            <h3 style="margin-top: 0;">添加 IP 到黑名单</h3>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                                <input type="text" id="blacklist_ip" placeholder="输入IP地址，如 192.168.1.1"
                                       style="flex: 1; min-width: 200px; height: 36px; padding: 4px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                                <input type="text" id="blacklist_reason" placeholder="拉黑理由（可选）"
                                       style="flex: 1; min-width: 150px; height: 36px; padding: 4px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                                <button onclick="addToBlacklistFromInput()" class="btn btn-s" style="height: 36px; white-space: nowrap;">加入黑名单</button>
                            </div>
                            <div id="add_result" style="margin-top: 10px; font-size: 13px;"></div>
                        </div>
                    </div>

                    <!-- 黑名单列表 -->
                    <div class="typecho-page-main" style="margin-bottom: 30px;">
                        <h3>黑名单列表 <span id="blacklist_count" style="font-size: 14px; color: #666; font-weight: normal;">(共 <?php echo $blacklist_total; ?> 条)</span></h3>
                        <div id="blacklist_table_wrap" class="typecho-table-wrap" style="overflow-x: auto;">
                            <?php if (!empty($blacklist_result['data'])): ?>
                            <table class="typecho-list-table" style="min-width: 700px;">
                                <colgroup>
                                    <col width="25%"/>
                                    <col width="35%"/>
                                    <col width="25%"/>
                                    <col width="15%"/>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>IP地址</th>
                                        <th>拉黑理由</th>
                                        <th>拉黑时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody id="blacklist_tbody">
                                    <?php foreach ($blacklist_result['data'] as $item): ?>
                                    <tr id="bl_<?php echo htmlspecialchars($item['ip']); ?>">
                                        <td><strong style="font-family: monospace;"><?php echo htmlspecialchars($item['ip']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['reason'] ?: '-'); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', $item['created_time']); ?></td>
                                        <td>
                                            <button onclick="removeFromBlacklist('<?php echo htmlspecialchars($item['ip']); ?>')"
                                                    style="background: #dc3545; color: white; border: none; padding: 4px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">解封</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div id="blacklist_pagination" style="margin-top: 15px; text-align: center;">
                                <?php
                                $bl_perPage = 20;
                                $bl_totalPages = ceil($blacklist_total / $bl_perPage);
                                if ($bl_totalPages > 1):
                                    for ($i = 1; $i <= $bl_totalPages; $i++):
                                ?>
                                    <button onclick="loadBlacklistPage(<?php echo $i; ?>)"
                                            class="btn btn-s" style="margin: 0 2px; <?php echo $i === 1 ? 'background: #0073aa; color: white;' : ''; ?>">
                                        <?php echo $i; ?>
                                    </button>
                                <?php
                                    endfor;
                                endif;
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div id="blacklist_empty" style="<?php echo empty($blacklist_result['data']) ? '' : 'display: none;'; ?>">
                            <div class="message notice"><p>黑名单为空</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
var weekData = <?php echo json_encode($week_data); ?>;
var monthlyStatsData = <?php echo json_encode($monthly_stats); ?>;
</script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/panel.js'); ?>"></script>

<?php include_once __TYPECHO_ROOT_DIR__ . '/admin/footer.php'; ?>
