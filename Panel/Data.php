<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// Panel/Data.php — 数据聚合 (Panel.php:320-819)
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
$blacklisted_cidrs = array();
try {
    $bl_result = RecentViewsCounter_Blacklist::getList(1, 99999);
    foreach ($bl_result['data'] as $bl_item) {
        if (strpos($bl_item['ip'], '/') !== false) {
            $blacklisted_cidrs[] = $bl_item['ip'];
        } else {
            $blacklisted_ips[$bl_item['ip']] = true;
        }
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
    if (!$ip_data['is_blacklisted'] && !empty($blacklisted_cidrs)) {
        foreach ($blacklisted_cidrs as $cidr) {
            $parts = explode('/', $cidr);
            $network = ip2long($parts[0]);
            $ip_long = ip2long($ip_data['ip']);
            if ($network !== false && $ip_long !== false) {
                $mask = -1 << (32 - intval($parts[1]));
                if (($ip_long & $mask) === ($network & $mask)) {
                    $ip_data['is_blacklisted'] = 1;
                    break;
                }
            }
        }
    }
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
    // 站点时区偏移（秒），用于按站点本地日期聚合；date('Z') 只反映服务器时区，此处须用站点 timezone
    $tz_offset = intval($options->timezone);
    
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
            // day_start 已在 SQL 中按站点时区平移到本地日，*86400 即为本地零点，不能再减 tz_offset
            'date' => date('m-d', $ds * 86400),
            'full_date' => date('Y-m-d', $ds * 86400),
            'visits' => $total,
            'real_users' => $total - $bot,
            'bots' => $bot
        );
    }
} catch (Exception $e) {
    $week_data = array();
}

// 文章页面频率日志（最近50条）及自动封禁记录（最多50条）
$rateLogs = array();
$archiveBans = array();
try {
    $rateLogs = $db->fetchAll($db->select('*')->from('table.ip_rate_log')->order('access_time', Typecho_Db::SORT_DESC)->limit(50));
    if (!$rateLogs) $rateLogs = array();
} catch (Exception $e) { $rateLogs = array(); }
try {
    $archiveBans = $db->fetchAll($db->select('*')->from('table.ip_blacklist')->where('reason LIKE ?', '%archives%')->order('created_time', Typecho_Db::SORT_DESC)->limit(50));
    if (!$archiveBans) $archiveBans = array();
} catch (Exception $e) { $archiveBans = array(); }
