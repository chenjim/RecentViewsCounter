<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// Panel/Actions.php — POST handlers & helpers (extracted from Panel.php:15-318)
/**
 * 解析时间范围参数，返回天数（null表示全部时间）
 */
function parseTimeRangeDays($range) {
    if ($range === '7') return 7;
    if ($range === '30') return 30;
    return null;
}

// CSRF 保护：对修改数据的 POST 请求校验 Referer（只读查询如 get_visit_records 不拦截）
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
