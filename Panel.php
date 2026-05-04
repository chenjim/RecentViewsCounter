<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 包含管理后台公共文件
include_once __TYPECHO_ROOT_DIR__ . '/admin/common.php';
include_once __TYPECHO_ROOT_DIR__ . '/admin/header.php';
include_once __TYPECHO_ROOT_DIR__ . '/admin/menu.php';

// 获取插件配置
$options = Typecho_Widget::widget('Widget_Options');
$plugin_config = $options->plugin('RecentViewsCounter');
$top_count = $plugin_config ? intval($plugin_config->top_count) : 10;

// 获取蜘蛛检测模式数组（复用函数）
function getBotPatterns() {
    return array(
        'Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider',
        'YandexBot', 'YandexAccessibilityBot', 'YandexMobileBot', 'YandexDirectDyn',
        'facebookexternalhit', 'Twitterbot', 'rogerbot', 'linkedinbot',
        'embedly', 'quora link preview', 'showyoubot', 'outbrain', 'pinterest',
        'developers.google.com/+/web/snippet', 'slackbot', 'vkShare',
        'W3C_Validator', 'redditbot', 'Applebot', 'WhatsApp', 'flipboard',
        'tumblr', 'bitlybot', 'SkypeUriPreview', 'nuzzel', 'Discordbot',
        'Google Page Speed', 'Qwantify', 'pinterestbot', 'Bitrix link preview',
        'XING-contenttabreceiver', 'Chrome-Lighthouse', 'TelegramBot',
        'SemrushBot', 'AhrefsBot', 'MJ12bot', 'DotBot', 'Mediapartners-Google',
        'AdsBot-Google', 'Googlebot-News', 'Googlebot-Image', 'Googlebot-Video',
        'Googlebot-Mobile', 'msnbot', 'bingbot', 'ia_archiver', 'Wayback',
        'archive.org_bot', 'CCBot', 'SeznamBot', 'MojeekBot', 'DuckDuckGo-Favicons-Bot',
        // 中国搜索引擎爬虫
        'Baiduspider-render', 'Baiduspider-image', 'Baiduspider-news', 'Baiduspider-video',
        'Sogou web spider', 'Sogou News Spider', 'Sogou Pic Spider', 'Sogou Video Spider',
        'HaoSouSpider', '360Spider', 'JikeSpider', 'YisouSpider', 'ToutiaoSpider',
        'ByteSpider', 'PetalBot', 'spider', 'crawler', 'bot'
    );
}

// 智能Bot检测说明内容（复用）
$bot_detection_info = "智能Bot检测说明：
基于多维度分析(User-Agent识别、访问频率、行为模式等)。
评分规则：
• UA识别40分、高频访问20分、专注爬取15分
• 空UA10分、单一UA10分、高频率5分
风险等级：
• 0-29分正常用户
• 30-59分疑似Bot
• 60+分高风险Bot";

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
                                       ->limit(200));
            
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
$article_days = 30; // 默认30天
switch ($article_time_range) {
    case '7':
        $article_days = 7;
        break;
    case '30':
        $article_days = 30;
        break;
    default:
        $article_days = null; // 全部时间
        break;
}

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
$global_time_condition = null;
switch ($global_time_range) {
    case '7':
    case '':
    case null:
        $global_time_condition = time() - 7 * 24 * 3600;
        break;
    case '30':
        $global_time_condition = time() - 30 * 24 * 3600;
        break;
    case 'all':
        $global_time_condition = null; // 全部时间
        break;
    default:
        $global_time_condition = time() - 7 * 24 * 3600; // 默认7天
        break;
}

// 调试信息（可以在测试后删除）
echo "<!-- 调试信息: global_time_range = $global_time_range, global_time_condition = " . ($global_time_condition ? date('Y-m-d H:i:s', $global_time_condition) : 'null') . " -->";

// 获取总访问量统计
try {
    $query = $db->select('COUNT(*) as count')->from('table.views_records');
    if ($global_time_condition !== null) {
        $query = $query->where('visit_time > ?', $global_time_condition);
    }
    $total_visits = $db->fetchRow($query);
} catch (Exception $e) {
    $total_visits = array('count' => 0);
}

// 获取独立访客数
try {
    $query = $db->select('COUNT(DISTINCT ip) as count')->from('table.views_records');
    if ($global_time_condition !== null) {
        $query = $query->where('visit_time > ?', $global_time_condition);
    }
    $unique_visitors = $db->fetchRow($query);
} catch (Exception $e) {
    $unique_visitors = array('count' => 0);
}

// 获取今日访问量
$today_start = strtotime(date('Y-m-d 00:00:00'));
try {
    $today_visits = $db->fetchRow($db->select('COUNT(*) as count')
                                    ->from('table.views_records')
                                    ->where('visit_time > ?', $today_start));
} catch (Exception $e) {
    $today_visits = array('count' => 0);
}

// 获取访问来源统计的时间范围参数（优先使用统一参数）
$referer_time_range = isset($_GET['referer_time_range']) ? $_GET['referer_time_range'] : 
                     (isset($_GET['location_time_range']) ? $_GET['location_time_range'] : '7');

// 获取访问来源统计数据
$referer_stats = array();
if ($plugin_config && $plugin_config->enable_referer == '1') {
    // 根据时间范围参数确定统计天数
    switch ($referer_time_range) {
        case '7':
            $stats_days = 7;
            break;
        case '30':
            $stats_days = 30;
            break;
        case 'all':
            $stats_days = 365 * 10; // 10年，相当于全部
            break;
        default:
            $stats_days = 7; // 默认7天
    }
    $referer_stats = RecentViewsCounter_Plugin::getRefererStats($stats_days);
}

// 获取总蜘蛛数（通过User-Agent识别）
$bot_patterns = getBotPatterns();

// 使用原生SQL统计bot访问量，确保时间条件和bot条件都正确应用
try {
    // 构建bot检测条件
    $bot_conditions = array();
    foreach ($bot_patterns as $pattern) {
        // 使用addslashes进行简单转义，防止SQL注入
        $escaped_pattern = addslashes($pattern);
        $bot_conditions[] = "user_agent LIKE '%" . $escaped_pattern . "%'";
    }
    
    // 构建完整的SQL查询
    $sql = "SELECT COUNT(*) as count FROM " . $db->getPrefix() . "views_records WHERE 1=1";
    
    // 添加时间条件
    if ($global_time_condition !== null) {
        $sql .= " AND visit_time > " . intval($global_time_condition);
    }
    
    // 添加bot条件（用括号包围OR条件）
    if (!empty($bot_conditions)) {
        $sql .= " AND (" . implode(' OR ', $bot_conditions) . ")";
    }
    
    $total_bots = $db->fetchRow($db->query($sql));
} catch (Exception $e) {
    $total_bots = array('count' => 0);
}

// 获取地域分布统计的时间范围参数
$location_time_range = isset($_GET['location_time_range']) ? $_GET['location_time_range'] : '7';
$location_time_condition = '';
switch ($location_time_range) {
    case '7':
        $location_time_condition = ' AND `visit_time` > ' . (time() - 7 * 24 * 3600);
        break;
    case '30':
        $location_time_condition = ' AND `visit_time` > ' . (time() - 30 * 24 * 3600);
        break;
    default:
        $location_time_condition = '';
        break;
}

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
$time_condition = '';
switch ($ip_time_range) {
    case '7':
        $time_condition = time() - 7 * 24 * 3600;
        break;
    case '30':
        $time_condition = time() - 30 * 24 * 3600;
        break;
    default:
        $time_condition = null;
        break;
}

// 高级bot检测：基于访问频率和行为模式
try {
    $query = $db->select('ip', 
                        array('MAX(location)' => 'location'),
                        array('COUNT(*)' => 'count'),
                        array('COUNT(DISTINCT cid)' => 'distinct_cids'),
                        array('COUNT(DISTINCT user_agent)' => 'ua_variety'),
                        array('MIN(visit_time)' => 'first_visit'),
                        array('MAX(visit_time)' => 'last_visit'),
                        array('MAX(user_agent)' => 'sample_user_agent'))
                ->from('table.views_records');
    
    // 如果有时间条件，添加WHERE子句
    if ($time_condition !== null) {
        $query = $query->where('visit_time >= ?', $time_condition);
    }
    
    $top_ips = $db->fetchAll($query->group('ip')
                                  ->order('count', Typecho_Db::SORT_DESC)
                                  ->limit(20));
} catch (Exception $e) {
    $top_ips = array();
}

// 为每个IP计算综合bot评分
foreach ($top_ips as &$ip_data) {
    $bot_score = 0;
    
    // User-Agent检测 (权重: 40)
    $sample_ua = $ip_data['sample_user_agent'] ?: '';
    $detected_bot_name = '';
    foreach ($bot_patterns as $pattern) {
        if (stripos($sample_ua, $pattern) !== false) {
            $bot_score += 40;
            $detected_bot_name = $pattern;
            break;
        }
    }
    
    // 高频访问检测 (权重: 20)
    if ($ip_data['count'] > 100) $bot_score += 20;
    
    // 专注爬取检测 (权重: 15)
    $focus_ratio = $ip_data['count'] > 0 ? $ip_data['distinct_cids'] / $ip_data['count'] : 1;
    if ($focus_ratio < 0.1 && $ip_data['count'] > 20) $bot_score += 15;
    
    // User-Agent多样性检测 (权重: 25)
    if ($ip_data['ua_variety'] == 1 && $ip_data['count'] > 10) $bot_score += 25;
    
    // 访问时间模式检测 (权重: 10)
    $visit_duration = $ip_data['last_visit'] - $ip_data['first_visit'];
    $visit_rate = $visit_duration > 0 ? $ip_data['count'] / ($visit_duration / 3600) : 0; // 每小时访问次数
    if ($visit_rate > 10) $bot_score += 10; // 每小时超过10次访问
    
    $ip_data['bot_score'] = $bot_score;
    $ip_data['is_bot'] = ($bot_score >= 30) ? 1 : 0; // 30分以上认为是bot
    $ip_data['is_bot_ua'] = $bot_score >= 40 ? 1 : 0;
    $ip_data['detected_bot_name'] = $detected_bot_name; // 存储检测到的bot名称
    $ip_data['is_high_frequency'] = $ip_data['count'] > 100 ? 1 : 0;
    $ip_data['is_focused_crawler'] = ($focus_ratio < 0.1 && $ip_data['count'] > 20) ? 1 : 0;
}

// 获取访问趋势数据（不受全局时间选择限制，显示最近30天）
$week_data = array();

// 获取最近30天的访问趋势数据
try {
    $thirty_days_ago = time() - 30 * 24 * 3600;
    $query = $db->select('visit_time, user_agent')->from('table.views_records')
                ->where('visit_time > ?', $thirty_days_ago);
    
    $records = $db->fetchAll($query->order('visit_time', Typecho_Db::SORT_ASC));
    
    // Bot检测模式数组（复用）
     $bot_patterns = getBotPatterns();
    
    // 在PHP中按天分组，同时统计蜘蛛访问
    $grouped_stats = array();
    $grouped_bots = array();
    foreach ($records as $record) {
        $date = date('Y-m-d', $record['visit_time']);
        if (!isset($grouped_stats[$date])) {
            $grouped_stats[$date] = 0;
            $grouped_bots[$date] = 0;
        }
        $grouped_stats[$date]++;
        
        // 检测是否为蜘蛛
        $is_bot = false;
        $user_agent = strtolower($record['user_agent']);
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, strtolower($pattern)) !== false) {
                $is_bot = true;
                break;
            }
        }
        if ($is_bot) {
            $grouped_bots[$date]++;
        }
    }
    
    // 转换为图表数据格式
    foreach ($grouped_stats as $date => $count) {
        $bot_count = intval($grouped_bots[$date]);
        $daily_visits = intval($count); // 修改变量名避免与总访问量变量冲突
        $real_users = $daily_visits - $bot_count; // 非蜘蛛数（真实用户访问量）
        
        $week_data[] = array(
            'date' => date('m-d', strtotime($date)),
            'full_date' => $date,
            'visits' => $daily_visits,
            'real_users' => $real_users,
            'bots' => $bot_count
        );
    }
} catch (Exception $e) {
    $week_data = array();
}
?>

<div class="main">
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
                <div class="row typecho-page-main" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; max-width: 800px;">
                    <div style="flex: 1; min-width: 150px; text-align: center; padding: 20px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #467b96;"><?php echo $total_visits ? $total_visits['count'] : 0; ?></h3>
                        <p style="margin: 5px 0 0 0;">总访问量</p>
                    </div>
                    <div style="flex: 1; min-width: 150px; text-align: center; padding: 20px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #dc3545;"><?php echo $total_bots ? $total_bots['count'] : 0; ?></h3>
                        <p style="margin: 5px 0 0 0;">总蜘蛛数</p>
                    </div>
                    <div style="flex: 1; min-width: 150px; text-align: center; padding: 20px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #467b96;"><?php echo $unique_visitors ? $unique_visitors['count'] : 0; ?></h3>
                        <p style="margin: 5px 0 0 0;">独立访客</p>
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
                        <small style="color: #666;" 
                                title="<?php echo htmlspecialchars($bot_detection_info); ?>">
                             (智能Bot检测)
                         </small>
                    </h3>
                    <?php if (!empty($top_ips)): ?>
                    <div class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 900px;">
                            <colgroup>
                                <col width="5%"/>
                                <col width="18%"/>
                                <col width="22%"/>
                                <col width="10%"/>
                                <col width="8%"/>
                                <col width="10%"/>
                                <col width="15%"/>
                                <col width="12%"/>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_ip_visits = array_sum(array_column($top_ips, 'count'));
                                $rank = 1; 
                                foreach ($top_ips as $ip_data): 
                                    $percentage = $total_ip_visits > 0 ? round(($ip_data['count'] / $total_ip_visits) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <?php 
                                        $ip_color = $ip_data['is_bot'] ? '#ff6b35' : '#333'; // bot使用橙红色，普通用户使用默认颜色
                                        $bot_level = '';
                                        if ($ip_data['bot_score'] >= 60) $bot_level = '高风险Bot';
                                        elseif ($ip_data['bot_score'] >= 30) $bot_level = '疑似Bot';
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
                                        if ($ip_data['bot_score'] >= 60) $score_color = '#dc3545'; // 红色 - 高风险
                                        elseif ($ip_data['bot_score'] >= 30) $score_color = '#ffc107'; // 黄色 - 疑似
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
                                        if ($ip_data['empty_ua_ratio'] > 0.5) $detection_details[] = '空UA';
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
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="typecho-page-main">
                        <div class="message notice">
                            <p>暂无IP访问数据</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Bot检测说明 -->
                    <div id="botDetectionInfo" style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin-top: 15px; display: none;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">🤖 智能Bot检测说明</h4>
                        <div style="font-size: 13px; color: #6c757d; line-height: 1.5;">
                            <p style="margin: 5px 0;"><strong>检测机制：</strong>基于多维度分析，包括User-Agent识别、访问频率、行为模式等</p>
                            <p style="margin: 5px 0;"><strong>评分规则：</strong></p>
                            <ul style="margin: 5px 0; padding-left: 20px;">
                                <li><strong>UA识别</strong> (40分): 检测到已知爬虫User-Agent</li>
                                <li><strong>高频访问</strong> (20分): 访问次数超过100次</li>
                                <li><strong>专注爬取</strong> (15分): 访问内容集中度高 (访问文章种类/总访问 < 0.1)</li>
                                <li><strong>空UA</strong> (10分): 超过50%的请求User-Agent为空</li>
                                <li><strong>单一UA</strong> (10分): 只使用一种User-Agent且访问超过10次</li>
                                <li><strong>高频率</strong> (5分): 每小时访问超过10次</li>
                            </ul>
                            <p style="margin: 5px 0;"><strong>风险等级：</strong>
                                <span style="color: #28a745;">0-29分 正常用户</span> | 
                                <span style="color: #ffc107;">30-59分 疑似Bot</span> | 
                                <span style="color: #dc3545;">60+分 高风险Bot</span>
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
                                        // 获取文章完整信息以生成正确的链接
                        $db = Typecho_Db::get();
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



<script>
// 绘制访问趋势图
function drawChart() {
    var canvas = document.getElementById('visitChart');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var data = <?php echo json_encode($week_data); ?>;

    // 获取设备像素比,解决高DPI屏幕模糊问题
    var dpr = window.devicePixelRatio || 1;

    // 获取容器实际宽度
    var container = canvas.parentElement;
    var displayWidth = container.offsetWidth;
    var displayHeight = 300;

    // 根据宽度自适应调整padding和字体
    var padding = displayWidth < 500 ? 30 : 40;
    var fontSize = displayWidth < 500 ? 10 : 12;
    var labelFontSize = displayWidth < 500 ? 10 : 12;

    // 设置canvas实际尺寸(考虑DPI)
    canvas.width = displayWidth * dpr;
    canvas.height = displayHeight * dpr;

    // 设置canvas显示尺寸
    canvas.style.width = displayWidth + 'px';
    canvas.style.height = displayHeight + 'px';

    // 缩放绘图上下文以匹配DPI
    ctx.scale(dpr, dpr);

    var width = displayWidth;
    var height = displayHeight;
    var chartWidth = width - 2 * padding;
    var chartHeight = height - 2 * padding;
    
    // 清空画布
    ctx.clearRect(0, 0, width, height);
    
    if (!data || data.length === 0) {
        ctx.fillStyle = '#999';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('暂无访问趋势数据', width / 2, height / 2);
        return;
    }
    
    // 找出最大值
    var maxRealUsers = Math.max.apply(Math, data.map(function(item) { return item.real_users || 0; }));
    var maxBots = Math.max.apply(Math, data.map(function(item) { return item.bots || 0; }));
    var maxValue = Math.max(maxRealUsers, maxBots);
    if (maxValue === 0) maxValue = 1;
    
    // 绘制坐标轴
    ctx.strokeStyle = '#ccc';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();
    
    // 绘制网格线
    ctx.strokeStyle = '#f0f0f0';
    ctx.lineWidth = 0.5;
    for (var i = 1; i <= 5; i++) {
        var y = padding + (i * chartHeight / 5);
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
    }
    
    // 绘制非蜘蛛数折线图（真实用户访问量）
    ctx.strokeStyle = '#467b96';
    ctx.lineWidth = 2;
    ctx.beginPath();
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / (data.length - 1));
        var y = height - padding - ((data[i].real_users || 0) / maxValue * chartHeight);
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    ctx.stroke();
    
    // 绘制蜘蛛数折线图
    ctx.strokeStyle = '#ff6b35';
    ctx.lineWidth = 2;
    ctx.beginPath();
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / (data.length - 1));
        var y = height - padding - ((data[i].bots || 0) / maxValue * chartHeight);
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    ctx.stroke();
    
    // 绘制数据点和标签
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / (data.length - 1));
        var y = height - padding - ((data[i].real_users || 0) / maxValue * chartHeight);
        
        // 判断是否为周末
        var isWeekend = false;
        if (data[i].full_date) {
            var date = new Date(data[i].full_date);
            var dayOfWeek = date.getDay();
            isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
        }
        
        // 绘制非蜘蛛数据点 - 周末标红，工作日用蓝色
        ctx.fillStyle = isWeekend ? '#ff0000' : '#467b96';
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // 绘制日期标签 - 周末标红，工作日用灰色
        ctx.fillStyle = isWeekend ? '#ff0000' : '#666';
        ctx.font = fontSize + 'px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(data[i].date.split('-')[1], x, height - padding + 15);

        // 绘制非蜘蛛数值标签 - 周末标红，工作日用蓝色
        ctx.fillStyle = isWeekend ? '#ff0000' : '#467b96';
        ctx.fillText(data[i].real_users || 0, x, y - 10);
        
        // 绘制蜘蛛数据点
        var botY = height - padding - ((data[i].bots || 0) / maxValue * chartHeight);
        ctx.fillStyle = '#ff6b35';
        ctx.beginPath();
        ctx.arc(x, botY, 3, 0, 2 * Math.PI);
        ctx.fill();
        
        // 绘制蜘蛛数值标签
        ctx.fillStyle = '#ff6b35';
        ctx.fillText(data[i].bots || 0, x, botY - 10);
    }
    
    // 绘制图例
    var legendY = padding - 20;

    // 非蜘蛛数图例
    ctx.fillStyle = '#467b96';
    ctx.font = labelFontSize + 'px Arial';
    ctx.textAlign = 'left';
    ctx.fillText('● 非蜘蛛数', padding, legendY + 4);

    // 蜘蛛数图例
    ctx.fillStyle = '#ff6b35';
    ctx.fillText('● 蜘蛛数', padding + 80, legendY + 4);

    // 工作日图例
    ctx.fillStyle = '#666';
    ctx.fillText('工作日', padding + 160, legendY + 4);

    // 周末图例
    ctx.fillStyle = '#ff0000';
    ctx.fillText('周末', padding + 210, legendY + 4);
}

// 绘制月度访问统计图
function drawMonthlyChart() {
    var canvas = document.getElementById('monthlyChart');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var data = <?php echo json_encode($monthly_stats); ?>;

    // 获取设备像素比,解决高DPI屏幕模糊问题
    var dpr = window.devicePixelRatio || 1;

    // 获取容器实际宽度
    var container = canvas.parentElement;
    var displayWidth = container.offsetWidth;
    var displayHeight = 300;

    // 根据宽度自适应调整padding和字体
    var padding = displayWidth < 500 ? 35 : 50;
    var fontSize = displayWidth < 500 ? 10 : 11;
    var labelFontSize = displayWidth < 500 ? 10 : 12;

    // 设置canvas实际尺寸(考虑DPI)
    canvas.width = displayWidth * dpr;
    canvas.height = displayHeight * dpr;

    // 设置canvas显示尺寸
    canvas.style.width = displayWidth + 'px';
    canvas.style.height = displayHeight + 'px';

    // 缩放绘图上下文以匹配DPI
    ctx.scale(dpr, dpr);

    var width = displayWidth;
    var height = displayHeight;
    var chartWidth = width - 2 * padding;
    var chartHeight = height - 2 * padding;
    
    // 清空画布
    ctx.clearRect(0, 0, width, height);
    
    if (!data || data.length === 0) {
        // 显示无数据提示
        ctx.fillStyle = '#999';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('暂无月度统计数据', width / 2, height / 2);
        return;
    }
    
    // 找出最大值
    var maxValue = Math.max.apply(Math, data.map(function(item) { return parseInt(item.total_visits); }));
    if (maxValue === 0) maxValue = 1;
    
    // 绘制坐标轴
    ctx.strokeStyle = '#ddd';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();
    
    // 绘制网格线
    ctx.strokeStyle = '#f0f0f0';
    ctx.lineWidth = 0.5;
    for (var i = 1; i <= 5; i++) {
        var y = padding + (i * chartHeight / 5);
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
        
        // 绘制Y轴标签
        ctx.fillStyle = '#666';
        ctx.font = fontSize + 'px Arial';
        ctx.textAlign = 'right';
        var value = Math.round(maxValue * (5 - i) / 5);
        ctx.fillText(value.toString(), padding - 10, y + 4);
    }
    
    // 绘制总访问量折线
    ctx.strokeStyle = '#28a745';
    ctx.lineWidth = 3;
    ctx.beginPath();
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var visits = parseInt(data[i].total_visits);
        var y = height - padding - (visits / maxValue * chartHeight);
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    
    ctx.stroke();
    
    // 绘制总访问量数据点和标签
    ctx.fillStyle = '#28a745';
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var visits = parseInt(data[i].total_visits);
        var y = height - padding - (visits / maxValue * chartHeight);
        
        // 绘制数据点
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // 绘制月份标签
        ctx.fillStyle = '#666';
        ctx.font = fontSize + 'px Arial';
        ctx.textAlign = 'center';
        var monthLabel = data[i].year_month.split('-')[1];
        ctx.fillText(monthLabel, x, height - padding + 15);

        // 绘制数值标签
        ctx.fillStyle = '#28a745';
        ctx.font = 'bold ' + fontSize + 'px Arial';
        ctx.fillText(visits.toString(), x, y - 10);
        ctx.fillStyle = '#28a745';
    }
    
    // 绘制独立访客折线
    ctx.strokeStyle = '#17a2b8';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 5]); // 虚线
    ctx.beginPath();
    
    var maxUniqueVisitors = Math.max.apply(Math, data.map(function(item) { return parseInt(item.unique_visitors); }));
    if (maxUniqueVisitors === 0) maxUniqueVisitors = 1;
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var uniqueVisitors = parseInt(data[i].unique_visitors);
        var y = height - padding - (uniqueVisitors / maxValue * chartHeight); // 使用相同的比例尺
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    
    ctx.stroke();
    ctx.setLineDash([]); // 重置线条样式
    
    // 绘制独立访客数据点
    ctx.fillStyle = '#17a2b8';
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var uniqueVisitors = parseInt(data[i].unique_visitors);
        var y = height - padding - (uniqueVisitors / maxValue * chartHeight);
        
        // 绘制数据点
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    // 绘制图例
    var legendY = padding - 20;
    ctx.fillStyle = '#28a745';
    ctx.fillRect(padding, legendY, 15, 3);
    ctx.fillStyle = '#333';
    ctx.font = labelFontSize + 'px Arial';
    ctx.textAlign = 'left';
    ctx.fillText('总访问量', padding + 20, legendY + 10);

    ctx.strokeStyle = '#17a2b8';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 5]);
    ctx.beginPath();
    ctx.moveTo(padding + 120, legendY + 1.5);
    ctx.lineTo(padding + 135, legendY + 1.5);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle = '#333';
    ctx.fillText('独立访客', padding + 140, legendY + 10);
}

// 页面加载完成后绘制图表
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        drawChart();
        drawMonthlyChart();
    });
} else {
    drawChart();
    drawMonthlyChart();
}

// 窗口大小变化时重绘图表
var resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        drawChart();
        drawMonthlyChart();
    }, 250);
});

// 显示访问记录模态框
function showVisitRecords(cid, title) {
    // 创建模态框
    var modal = document.createElement('div');
    modal.id = 'visitRecordsModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    var modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-width: 80%;
        max-height: 80%;
        overflow: auto;
        position: relative;
    `;
    
    modalContent.innerHTML = `
        <h3 style="margin-top: 0; color: #467b96;">文章访问记录</h3>
        <p><strong>文章标题：</strong>${title}</p>
        <div id="recordsContent">正在加载访问记录...</div>
        <button onclick="closeVisitRecords()" style="
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #999;
        ">×</button>
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="closeVisitRecords()" class="btn btn-s">关闭</button>
        </div>
    `;
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // 加载访问记录数据
    loadVisitRecords(cid);
}

// 关闭访问记录模态框
function closeVisitRecords() {
    var modal = document.getElementById('visitRecordsModal');
    if (modal) {
        modal.remove();
    }
}

// 加载访问记录数据
function loadVisitRecords(cid) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            var recordsContent = document.getElementById('recordsContent');
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        displayVisitRecords(response.data);
                    } else {
                        recordsContent.innerHTML = '<p style="color: #dc3545;">加载失败：' + (response.message || '未知错误') + '</p>';
                    }
                } catch (e) {
                    console.error('JSON解析错误:', e);
                    console.error('响应内容:', xhr.responseText);
                    recordsContent.innerHTML = '<p style="color: #dc3545;">数据解析失败</p><details><summary>详细信息</summary><pre>' + xhr.responseText + '</pre></details>';
                }
            } else {
                recordsContent.innerHTML = '<p style="color: #dc3545;">网络请求失败</p>';
            }
        }
    };
    
    xhr.send('action=get_visit_records&cid=' + cid);
}

// 显示访问记录数据
function displayVisitRecords(records) {
    var recordsContent = document.getElementById('recordsContent');
    
    if (!records || records.length === 0) {
        recordsContent.innerHTML = '<p>暂无访问记录</p>';
        return;
    }
    
    var html = `
        <div style="max-height: 400px; overflow-y: auto;">
            <table class="typecho-list-table" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="width: 20%;">访问时间</th>
                        <th style="width: 25%;">IP地址</th>
                        <th style="width: 35%;">User Agent</th>
                        <th style="width: 20%;">归属地</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    records.forEach(function(record) {
        var visitTime = new Date(record.visit_time * 1000).toLocaleString('zh-CN');
        var userAgent = record.user_agent || '未知';
        var location = record.location || '未知地区';
        
        // 截断过长的User Agent
        if (userAgent.length > 50) {
            userAgent = userAgent.substring(0, 50) + '...';
        }
        
        // 截断过长的地区信息
        if (location.length > 30) {
            location = location.substring(0, 30) + '...';
        }
        
        html += `
            <tr>
                <td>${visitTime}</td>
                <td>${record.ip}</td>
                <td title="${record.user_agent || ''}">${userAgent}</td>
                <td title="${record.location || ''}">${location}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <p style="margin-top: 15px; color: #666; font-size: 12px;">显示最近100条访问记录</p>
    `;
    
    recordsContent.innerHTML = html;
}

// IP访问详情相关函数
function showIpVisitDetails(ip) {
    // 创建模态框
    if (!document.getElementById('ipVisitDetailsModal')) {
        const modal = document.createElement('div');
        modal.id = 'ipVisitDetailsModal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 1000px;
                max-height: 80%;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            ">
                <div style="
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f8f9fa;
                ">
                    <h3 style="margin: 0; color: #333;">IP访问详情</h3>
                    <button onclick="closeIpVisitDetails()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: #666;
                        padding: 0;
                        width: 30px;
                        height: 30px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">×</button>
                </div>
                <div id="ipVisitDetailsContent" style="
                    padding: 20px;
                    max-height: 500px;
                    overflow-y: auto;
                ">加载中...</div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    document.getElementById('ipVisitDetailsModal').style.display = 'flex';
    loadIpVisitDetails(ip);
}

function closeIpVisitDetails() {
    const modal = document.getElementById('ipVisitDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function loadIpVisitDetails(ip) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        displayIpVisitDetails(response.data, ip);
                    } else {
                        document.getElementById('ipVisitDetailsContent').innerHTML = 
                            '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ ' + 
                            (response.message || '获取数据失败') + '</div>';
                    }
                } catch (e) {
                    console.error('JSON解析错误:', e);
                    console.log('原始响应:', xhr.responseText);
                    document.getElementById('ipVisitDetailsContent').innerHTML = 
                        '<div style="color: #dc3545; text-align: center; padding: 20px;">' +
                        '❌ 数据解析失败<br>' +
                        '<details style="margin-top: 10px; text-align: left;">' +
                        '<summary style="cursor: pointer;">查看详细错误信息</summary>' +
                        '<pre style="background: #f8f9fa; padding: 10px; margin-top: 5px; font-size: 12px; overflow: auto;">' +
                        '解析错误: ' + e.message + '\n\n' +
                        '原始响应: ' + xhr.responseText.substring(0, 500) + 
                        (xhr.responseText.length > 500 ? '...' : '') +
                        '</pre></details></div>';
                }
            } else {
                document.getElementById('ipVisitDetailsContent').innerHTML = 
                    '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ 请求失败，状态码: ' + xhr.status + '</div>';
            }
        }
    };
    
    xhr.send('action=get_ip_visit_details&ip=' + encodeURIComponent(ip));
}

function displayIpVisitDetails(records, ip) {
    if (!records || records.length === 0) {
        document.getElementById('ipVisitDetailsContent').innerHTML = 
            '<div style="text-align: center; padding: 20px; color: #666;">📭 该IP暂无访问记录</div>';
        return;
    }
    
    let html = `
        <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h4 style="margin: 0 0 10px 0; color: #495057;">🌐 IP地址: ${ip}</h4>
            <p style="margin: 0; color: #6c757d; font-size: 14px;">共找到 ${records.length} 条访问记录</p>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">访问时间</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">访问文章</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">User Agent</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">归属地</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    records.forEach(record => {
        const visitTime = new Date(record.visit_time * 1000).toLocaleString('zh-CN');
        
        // 截断过长的User Agent
        let userAgent = record.user_agent || '未知';
        if (userAgent.length > 50) {
            userAgent = userAgent.substring(0, 50) + '...';
        }
        
        // 截断过长的地区信息
        let location = record.location || '未知';
        if (location.length > 30) {
            location = location.substring(0, 30) + '...';
        }
        
        // 获取文章标题
        let articleTitle = record.article_title || '未知文章';
        if (articleTitle.length > 40) {
            articleTitle = articleTitle.substring(0, 40) + '...';
        }
        
        html += `
            <tr>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${visitTime}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;" title="${record.article_title || ''}">${articleTitle}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;" title="${record.user_agent || ''}">${userAgent}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;" title="${record.location || ''}">${location}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('ipVisitDetailsContent').innerHTML = html;
}

// IP归属地数据管理相关函数
function loadIpLocationData(page = 1) {
    // 创建模态框
    if (!document.getElementById('ipLocationModal')) {
        const modal = document.createElement('div');
        modal.id = 'ipLocationModal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 1000px;
                max-height: 80%;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            ">
                <div style="
                    padding: 20px;
                    border-bottom: 1px solid #dee2e6;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f8f9fa;
                ">
                    <h3 style="margin: 0; color: #333;">IP归属地数据管理</h3>
                    <button onclick="closeIpLocationModal()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: #666;
                        padding: 0;
                        width: 30px;
                        height: 30px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">×</button>
                </div>
                <div id="ipLocationContent" style="
                    padding: 20px;
                    max-height: 500px;
                    overflow-y: auto;
                ">加载中...</div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    document.getElementById('ipLocationModal').style.display = 'flex';
    
    // 发送AJAX请求获取IP数据
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        displayIpLocationData(response.data, response.total, page);
                    } else {
                        document.getElementById('ipLocationContent').innerHTML = 
                            '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ ' + 
                            (response.message || '获取数据失败') + '</div>';
                    }
                } catch (e) {
                    console.error('JSON解析错误:', e);
                    document.getElementById('ipLocationContent').innerHTML = 
                        '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ 数据解析失败</div>';
                }
            } else {
                document.getElementById('ipLocationContent').innerHTML = 
                    '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ 请求失败，状态码: ' + xhr.status + '</div>';
            }
        }
    };
    
    xhr.send('action=get_ip_location_data&page=' + page);
}

function closeIpLocationModal() {
    const modal = document.getElementById('ipLocationModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function displayIpLocationData(data, total, currentPage) {
    if (!data || data.length === 0) {
        document.getElementById('ipLocationContent').innerHTML = 
            '<div style="text-align: center; padding: 20px; color: #666;">📭 暂无IP归属地数据</div>';
        return;
    }
    
    const perPage = 20;
    const totalPages = Math.ceil(total / perPage);
    
    let html = `
        <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h4 style="margin: 0 0 10px 0; color: #495057;">🌐 IP归属地数据库</h4>
            <p style="margin: 0; color: #6c757d; font-size: 14px;">共 ${total} 条记录，当前第 ${currentPage}/${totalPages} 页</p>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">IP地址</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">归属地</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">国家</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">省份</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">城市</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">运营商</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">更新时间</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">操作</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const updateTime = new Date(record.updated_time * 1000).toLocaleString('zh-CN');
        
        html += `
            <tr>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.ip}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.location || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.country || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.region || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.city || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.isp || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${updateTime}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">
                    <button onclick="deleteIpLocation('${record.ip}')" 
                            style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;"
                            title="删除此IP记录">删除</button>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    // 添加分页
    if (totalPages > 1) {
        html += '<div style="margin-top: 20px; text-align: center;">';
        
        if (currentPage > 1) {
            html += `<button onclick="loadIpLocationData(${currentPage - 1})" style="margin: 0 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;">上一页</button>`;
        }
        
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            if (i === currentPage) {
                html += `<button style="margin: 0 2px; padding: 5px 10px; background: #0073aa; color: white; border: none; border-radius: 3px;">${i}</button>`;
            } else {
                html += `<button onclick="loadIpLocationData(${i})" style="margin: 0 2px; padding: 5px 10px; background: #f1f1f1; color: #333; border: none; border-radius: 3px; cursor: pointer;">${i}</button>`;
            }
        }
        
        if (currentPage < totalPages) {
            html += `<button onclick="loadIpLocationData(${currentPage + 1})" style="margin: 0 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;">下一页</button>`;
        }
        
        html += '</div>';
    }
    
    document.getElementById('ipLocationContent').innerHTML = html;
}

function deleteIpLocation(ip) {
    if (!confirm('确定要删除IP "' + ip + '" 的归属地记录吗？此操作不可恢复！')) {
        return;
    }
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('删除成功！');
                        loadIpLocationData(1); // 重新加载第一页数据
                    } else {
                        alert('删除失败：' + (response.message || '未知错误'));
                    }
                } catch (e) {
                    alert('删除失败：数据解析错误');
                }
            } else {
                alert('删除失败：请求错误');
            }
        }
    };
    
    xhr.send('action=delete_ip_location&ip=' + encodeURIComponent(ip));
}

// IP时间范围切换函数
function changeIpTimeRange() {
    const timeRange = document.getElementById('ip_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === 'all') {
        currentUrl.searchParams.delete('ip_time_range');
    } else {
        currentUrl.searchParams.set('ip_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 访问来源时间范围切换函数
function changeRefererTimeRange() {
    const timeRange = document.getElementById('referer_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === '7') {
        currentUrl.searchParams.delete('referer_time_range'); // 7天是默认值，删除参数
    } else {
        currentUrl.searchParams.set('referer_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 统一时间范围切换函数
function changeGlobalTimeRange() {
    const timeRange = document.getElementById('global_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    // 同时设置地域分布、访问来源、IP统计和热门文章的时间范围
    if (timeRange === '7') {
        // 7天是默认值，删除参数
        currentUrl.searchParams.delete('location_time_range');
        currentUrl.searchParams.delete('article_time_range');
        currentUrl.searchParams.delete('referer_time_range');
        currentUrl.searchParams.delete('ip_time_range');
    } else {
        currentUrl.searchParams.set('location_time_range', timeRange);
        currentUrl.searchParams.set('article_time_range', timeRange);
        currentUrl.searchParams.set('referer_time_range', timeRange);
        currentUrl.searchParams.set('ip_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 地域分布时间范围切换函数
function changeLocationTimeRange() {
    const timeRange = document.getElementById('location_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === '7') {
        currentUrl.searchParams.delete('location_time_range'); // 7天是默认值，删除参数
    } else {
        currentUrl.searchParams.set('location_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 热门文章时间范围切换函数
function changeArticleTimeRange() {
    const timeRange = document.getElementById('article_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === '7') {
        currentUrl.searchParams.delete('article_time_range'); // 7天是默认值，删除参数
    } else {
        currentUrl.searchParams.set('article_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 页面加载时设置统一时间选择器的默认值
document.addEventListener('DOMContentLoaded', function() {
    const globalTimeRange = document.getElementById('global_time_range');
    if (globalTimeRange) {
        const urlParams = new URLSearchParams(window.location.search);
        const locationRange = urlParams.get('location_time_range') || '7';
        const articleRange = urlParams.get('article_time_range') || '7';
        const refererRange = urlParams.get('referer_time_range') || '7';
        const ipRange = urlParams.get('ip_time_range') || '7';
        
        // 如果所有模块的时间范围一致，则设置统一选择器
        if (locationRange === articleRange && articleRange === refererRange && refererRange === ipRange) {
            globalTimeRange.value = locationRange;
        } else {
            // 如果不一致，默认设置为7天
            globalTimeRange.value = '7';
        }
    }
});


</script>

<?php include_once __TYPECHO_ROOT_DIR__ . '/admin/footer.php'; ?>
