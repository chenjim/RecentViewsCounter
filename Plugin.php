<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 文章近期浏览量统计插件，记录每次访问的详细信息，包括IP地址和归属地
 *
 * @package RecentViewsCounter
 * @author  chenjim
 * @version 1.0.0
 * @link https://github.com/chenjim/RecentViewsCounter
 */
class RecentViewsCounter_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @throws Typecho_Db_Exception
     */
    public static function activate()
    {
        try {
            // 创建访问记录表
            self::createTable();
            
            // 绑定限流检查到index.php的begin钩子（在路由分发前执行）
            Typecho_Plugin::factory('index.php')->begin = array(
                'RecentViewsCounter_Plugin',
                'handleIndexBegin'
            );
            
            // 绑定访问统计钩子
            Typecho_Plugin::factory('Widget_Archive')->beforeRender = array(
                'RecentViewsCounter_Plugin',
                'recordVisit'
            );
            
            // 绑定定时清理钩子到不同的事件
            Typecho_Plugin::factory('Widget_Archive')->footer = array(
                'RecentViewsCounter_Plugin',
                'cleanOldRecords'
            );
            
            // 添加管理菜单
            Typecho_Plugin::factory('admin/menu.php')->navBar = array(
                'RecentViewsCounter_Plugin',
                'addMenu'
            );
            
            // 注册管理面板
            Helper::addPanel(1, 'RecentViewsCounter/Panel.php', '近期统计', '查看文章访问统计', 'administrator');
            
            return '插件已成功激活，数据库表结构已更新！';
        } catch (Exception $e) {
            // 记录详细错误信息
            $error_msg = '插件激活失败: ' . $e->getMessage() . ' 在文件 ' . $e->getFile() . ' 第 ' . $e->getLine() . ' 行';
            error_log($error_msg);
            throw new Typecho_Plugin_Exception($error_msg);
        }
    }

    /**
     * 禁用插件方法
     */
    public static function deactivate()
    {
        // 移除管理面板注册
        Helper::removePanel(1, 'RecentViewsCounter/Panel.php');
        
        // 可选择是否删除数据表
        // self::dropTable();
    }

    /**
     * 创建访问记录数据表
     */
    private static function createTable()
    {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $driver = $db->getAdapterName();
            $isSqlite = stripos($driver, 'sqlite') !== false;

            // 创建访问记录表
            $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}views_records` (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                `cid` INTEGER NOT NULL,
                `visit_time` INTEGER NOT NULL,
                `ip` TEXT NOT NULL,
                `location` TEXT,
                `user_agent` TEXT,
                `referer` TEXT
            )";
            if (!$isSqlite) {
                $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            }
            
            error_log('RecentViewsCounter: 执行创建views_records表SQL');
            $db->query($sql);
            
            // 创建月度统计表
            $monthly_sql = "CREATE TABLE IF NOT EXISTS `{$prefix}monthly_stats` (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                `year_month` TEXT NOT NULL,
                `total_visits` INTEGER NOT NULL DEFAULT 0,
                `unique_visitors` INTEGER NOT NULL DEFAULT 0,
                `created_time` INTEGER NOT NULL,
                `updated_time` INTEGER NOT NULL
            )";
            if (!$isSqlite) {
                $monthly_sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            }
            
            error_log('RecentViewsCounter: 执行创建monthly_stats表SQL');
            $db->query($monthly_sql);
            
            // 创建IP归属地本地数据库表
            $ip_location_sql = "CREATE TABLE IF NOT EXISTS `{$prefix}ip_location` (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                `ip` TEXT NOT NULL,
                `location` TEXT NOT NULL,
                `country` TEXT,
                `region` TEXT,
                `city` TEXT,
                `isp` TEXT,
                `created_time` INTEGER NOT NULL,
                `updated_time` INTEGER NOT NULL
            )";
            if (!$isSqlite) {
                $ip_location_sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            }
            
            error_log('RecentViewsCounter: 执行创建ip_location表SQL');
            $db->query($ip_location_sql);
            error_log('RecentViewsCounter: 数据表创建完成');
        } catch (Exception $e) {
            error_log('RecentViewsCounter createTable错误: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 数据库升级方法
     */
    private static function upgradeDatabase()
    {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            
            error_log('RecentViewsCounter: 开始数据库升级检查');
            
            // 检查referer字段是否存在
            try {
                $columns = $db->fetchAll("SHOW COLUMNS FROM `{$prefix}views_records` LIKE 'referer'");
                if (empty($columns)) {
                    error_log('RecentViewsCounter: referer字段不存在，开始添加');
                    // 添加referer字段
                    $db->query("ALTER TABLE `{$prefix}views_records` ADD COLUMN `referer` varchar(500) DEFAULT NULL COMMENT '访问来源URL' AFTER `user_agent`");
                    // 添加索引
                    $db->query("ALTER TABLE `{$prefix}views_records` ADD INDEX `idx_referer` (`referer`(255))");
                    error_log('RecentViewsCounter: referer字段和索引添加完成');
                } else {
                    error_log('RecentViewsCounter: referer字段已存在，跳过添加');
                }
            } catch (Exception $e) {
                error_log('RecentViewsCounter: 检查或添加referer字段时出错: ' . $e->getMessage());
            }
            
            error_log('RecentViewsCounter: 数据库升级检查完成');
        } catch (Exception $e) {
            error_log('RecentViewsCounter upgradeDatabase错误: ' . $e->getMessage());
            // 静默处理错误，避免影响插件启用
        }
    }

    /**
     * 删除数据表
     */
    private static function dropTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        try {
            $db->query("DROP TABLE IF EXISTS `{$prefix}views_records`");
            $db->query("DROP TABLE IF EXISTS `{$prefix}monthly_stats`");
            $db->query("DROP TABLE IF EXISTS `{$prefix}ip_location`");
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 删除数据表时出错: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $enable_location = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_location',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '1',
            _t('IP归属地查询'),
            _t('是否启用IP归属地查询功能（需要网络连接）')
        );
        
        $top_count = new Typecho_Widget_Helper_Form_Element_Text(
            'top_count',
            NULL,
            20,
            _t('《近期统计》菜单中热门文章显示数量'),
            _t('在管理界面显示的热门文章数量')
        );
        
        $cookie_time = new Typecho_Widget_Helper_Form_Element_Text(
            'cookie_time',
            NULL,
            3600,
            _t('重复访问间隔时间（秒）'),
            _t('同一IP对同一文章的重复访问间隔时间，避免刷量')
        );
        
        $auto_clean = new Typecho_Widget_Helper_Form_Element_Radio(
            'auto_clean',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '1',
            _t('自动清理过期记录'),
            _t('是否自动清理30天前的访问记录以节省存储空间')
        );
        
        $clean_days = new Typecho_Widget_Helper_Form_Element_Text(
            'clean_days',
            NULL,
            30,
            _t('记录保留天数'),
            _t('访问记录保留的天数，超过此天数的记录将被自动清理')
        );
        
        $enable_cache = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_cache',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '1',
            _t('启用缓存'),
            _t('是否启用热门文章列表缓存以提高性能')
        );
        
        $cache_time = new Typecho_Widget_Helper_Form_Element_Text(
            'cache_time',
            NULL,
            3600,
            _t('缓存更新间隔（秒）'),
            _t('热门文章列表缓存的更新间隔时间，建议设置为3600秒（1小时）')
        );
        
        $cache_file = new Typecho_Widget_Helper_Form_Element_Text(
            'cache_file',
            NULL,
            '/usr/RecentViewsCounter.xml',
            _t('缓存文件存放位置'),
            _t('请确保缓存文件存放的目录可写！')
        );
        
        $enable_referer = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_referer',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '1',
            _t('访问来源统计'),
            _t('是否启用访问来源统计功能，记录用户从哪个网站跳转而来')
        );
        

        
        $enable_local_ip_db = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_local_ip_db',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '1',
            _t('本地IP数据库'),
            _t('是否启用本地IP归属地数据库，优先从本地查询以提高性能')
        );
        
        $ip_cache_days = new Typecho_Widget_Helper_Form_Element_Text(
            'ip_cache_days',
            NULL,
            90,
            _t('IP归属地缓存天数'),
            _t('本地IP归属地数据的缓存天数，超过此天数的数据将被重新查询更新')
        );
        
        $auto_clean_ip = new Typecho_Widget_Helper_Form_Element_Radio(
            'auto_clean_ip',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '0',
            _t('自动清理IP数据'),
            _t('是否自动清理过期的IP归属地数据以节省存储空间')
        );
        
        $enable_rate_limit = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_rate_limit',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '1',
            _t('启用访问频率限制'),
            _t('是否启用高频访问拦截功能，防止恶意刷量')
        );
        
        $rate_limit_window = new Typecho_Widget_Helper_Form_Element_Text(
            'rate_limit_window',
            NULL,
            60,
            _t('限流窗口时间（秒）'),
            _t('统计访问频率的时间窗口，默认60秒')
        );
        
        $rate_limit_threshold = new Typecho_Widget_Helper_Form_Element_Text(
            'rate_limit_threshold',
            NULL,
            10,
            _t('限流阈值（次数）'),
            _t('在窗口时间内允许的最大访问次数，超过则返回429状态码')
        );
        
        $ip138_token = new Typecho_Widget_Helper_Form_Element_Text(
            'ip138_token',
            NULL,
            '',
            _t('IP138 API Token'),
            _t('IP138接口的访问令牌，留空将使用默认token')
        );
        
        $form->addInput($enable_location);
        $form->addInput($ip138_token);
        $form->addInput($enable_local_ip_db);
        $form->addInput($ip_cache_days);
        $form->addInput($auto_clean_ip);
        $form->addInput($enable_rate_limit);
        $form->addInput($rate_limit_window);
        $form->addInput($rate_limit_threshold);
        $form->addInput($top_count);
        $form->addInput($cookie_time);
        $form->addInput($auto_clean);
        $form->addInput($clean_days);
        $form->addInput($enable_cache);
        $form->addInput($cache_time);
        $form->addInput($cache_file);
        $form->addInput($enable_referer);

    }

    /**
     * 个人用户的配置面板
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // 暂无个人配置
    }

    /**
     * 检查访问频率限制
     */
    public static function checkRateLimit()
    {
        // 添加调试日志
        error_log('RecentViewsCounter: checkRateLimit被调用');
        
        // 排除管理员访问
        if (Typecho_Widget::widget('Widget_User')->hasLogin()) {
            error_log('RecentViewsCounter: 管理员访问，跳过限流检查');
            return;
        }
        
        $ip = self::getClientIp();
        $current_time = time();
        error_log('RecentViewsCounter: 检查IP ' . $ip . ' 的访问频率');
        
        // 获取插件配置
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_config = $options->plugin('RecentViewsCounter');
        
        // 高频访问硬拦截
        if ($plugin_config && $plugin_config->enable_rate_limit == '1') {
            error_log('RecentViewsCounter: 限流功能已启用');
            try {
                $rateWindow = $plugin_config->rate_limit_window ? intval($plugin_config->rate_limit_window) : 60;
                $rateLimit = $plugin_config->rate_limit_threshold ? intval($plugin_config->rate_limit_threshold) : 10;
                error_log('RecentViewsCounter: 限流配置 - 窗口时间: ' . $rateWindow . '秒, 阈值: ' . $rateLimit . '次');
                
                $row = $db->fetchRow(
                    $db->select('COUNT(*) as count')
                       ->from('table.views_records')
                       ->where('ip = ?', $ip)
                       ->where('visit_time >= ?', $current_time - $rateWindow)
                );
                $recentCount = isset($row['count']) ? intval($row['count']) : 0;
                error_log('RecentViewsCounter: IP ' . $ip . ' 在过去 ' . $rateWindow . ' 秒内访问了 ' . $recentCount . ' 次');
                
                if ($recentCount >= $rateLimit) {
                    error_log('RecentViewsCounter: 触发限流！访问次数 ' . $recentCount . ' >= 阈值 ' . $rateLimit);
                    if (!headers_sent()) {
                        // 设置HTTP状态码和响应头
                        http_response_code(429);
                        header('HTTP/1.1 429 Too Many Requests');
                        header('Retry-After: ' . $rateWindow);
                        header('Content-Type: text/plain; charset=utf-8');
                        echo '请求过于频繁，请稍后再试。';
                    } else {
                        // 如果headers已发送，只能输出错误信息
                        echo '<script>console.error("Rate limit exceeded: 429 Too Many Requests");</script>';
                        echo '请求过于频繁，请稍后再试。';
                    }
                    exit;
                } else {
                    error_log('RecentViewsCounter: 访问正常，未触发限流');
                }
            } catch (Exception $e) {
                error_log('RecentViewsCounter: 限流检查异常: ' . $e->getMessage());
                // 忽略限流统计过程中的异常，避免影响正常访问
            }
        } else {
            error_log('RecentViewsCounter: 限流功能未启用或配置不存在');
        }
    }
    
    /**
     * 处理index.php的begin钩子
     */
    public static function handleIndexBegin()
    {
        // 添加调试日志
        error_log('RecentViewsCounter: handleIndexBegin被调用');
        
        // 执行限流检查
        self::checkRateLimit();
    }
    
    /**
     * 记录访问信息（异步化IP查询版本）
     */
    public static function recordVisit($archive_obj)
    {
        // 仅对文章进行统计，排除管理员访问
        if (!$archive_obj->is('single') || Typecho_Widget::widget('Widget_User')->hasLogin()) {
            return;
        }

        $cid = $archive_obj->cid;
        $ip = self::getClientIp();
        $current_time = time();
        
        // 检查是否为重复访问
        if (self::isDuplicateVisit($cid, $ip, $current_time)) {
            return;
        }

        // 获取数据库连接和插件配置
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_config = $options->plugin('RecentViewsCounter');

        // 快速获取IP归属地（优先本地缓存）
        $location = null;
        if ($plugin_config && $plugin_config->enable_location == '1') {
            // 检查是否为内网IP
            if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
                $location = '本地';
            } elseif (self::isPrivateIp($ip)) {
                $location = '内网';
            } else {
                // 尝试从本地数据库快速查询
                $local_result = self::getIpLocationFromLocal($ip);
                if ($local_result && !empty($local_result['location'])) {
                    $location = $local_result['location'];
                }
                // 如果本地没有，先设为null，后续异步查询
            }
        }
        
        // 获取用户代理
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // 获取访问来源（根据配置决定是否记录）
        $referer = null;
        if ($plugin_config && $plugin_config->enable_referer == '1') {
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        }
        
        // 插入访问记录（先快速记录，IP归属地可能为空）
        try {
            $db->query($db->insert('table.views_records')->rows(array(
                'cid' => $cid,
                'visit_time' => $current_time,
                'ip' => $ip,
                'location' => $location,
                'user_agent' => $user_agent,
                'referer' => $referer
            )));
            
            // 设置Cookie防止重复统计
            $cookie_time = $plugin_config ? intval($plugin_config->cookie_time) : 3600;
            Typecho_Cookie::set("views_month_{$cid}_{$ip}", '1', $current_time + $cookie_time);
            
            // 自动更新当月统计
            self::autoUpdateCurrentMonthStats();
            
            // 如果IP归属地为空且需要查询，则异步更新
            if ($location === null && $plugin_config && $plugin_config->enable_location == '1') {
                register_shutdown_function(array('RecentViewsCounter_Plugin', 'updateIpLocationAsync'), $ip);
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 记录访问信息时出错: ' . $e->getMessage());
        }
    }

    /**
     * 获取客户端真实IP
     */
    private static function getClientIp()
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

    /**
     * 检查是否为重复访问
     */
    private static function isDuplicateVisit($cid, $ip, $current_time)
    {
        // 检查Cookie
        $cookie_key = "views_month_{$cid}_{$ip}";
        if (Typecho_Cookie::get($cookie_key)) {
            return true;
        }
        
        return false;
    }

    /**
     * 从本地数据库查询IP归属地
     */
    private static function getIpLocationFromLocal($ip)
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
    
    /**
     * 保存IP归属地到本地数据库
     */
    private static function saveIpLocationToLocal($ip, $location, $country = null, $region = null, $city = null, $isp = null)
    {
        if (empty($ip) || empty($location)) {
            return false;
        }
        
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $current_time = time();
            
            // 检查IP是否已存在
            $exists = $db->fetchRow($db->select('id')
                ->from($prefix . 'ip_location')
                ->where('ip = ?', $ip));
            
            if ($exists) {
                // 更新现有记录
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
                // 插入新记录
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
    
    /**
     * 异步更新IP归属地
     */
    public static function updateIpLocationAsync($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1' || self::isPrivateIp($ip)) {
            return;
        }
        
        try {
            // 查询IP归属地
            $location_data = self::getIpLocationFromApi($ip);
            if ($location_data && !empty($location_data['location'])) {
                $db = Typecho_Db::get();
                
                // 保存到本地数据库
                self::saveIpLocationToLocal(
                    $ip,
                    $location_data['location'],
                    $location_data['country'],
                    $location_data['region'],
                    $location_data['city'],
                    $location_data['isp']
                );
                
                // 更新访问记录中的IP归属地
                $db->query($db->update('table.views_records')
                              ->rows(array('location' => $location_data['location']))
                              ->where('ip = ? AND (location IS NULL OR location = "")', $ip));
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 异步更新IP归属地错误: ' . $e->getMessage());
        }
    }
    
    /**
     * 从IP138 API获取IP归属地
     */
    private static function getIpLocationFromIp138($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
            return null;
        }
        
        try {
             // 使用IP138 API
             $options = Typecho_Widget::widget('Widget_Options');
             $plugin_config = $options->plugin('RecentViewsCounter');
             $token = ($plugin_config && !empty($plugin_config->ip138_token)) ? $plugin_config->ip138_token : '';
             
             // 如果没有配置token，则跳过IP138 API调用
             if (empty($token)) {
                 throw new Exception('IP138 token not configured');
             }
             
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
                // IP138返回的是JSONP格式，需要提取JSON部分
                if (preg_match('/\((.+)\)/', $response, $matches)) {
                    $json_data = $matches[1];
                    $data = json_decode($json_data, true);
                    
                    if ($data && isset($data['ret']) && $data['ret'] == 'ok' && isset($data['data'])) {
                        $location_info = $data['data'];
                        $location_parts = array();
                        
                        if (!empty($location_info[0])) $location_parts[] = $location_info[0]; // 国家
                        if (!empty($location_info[1])) $location_parts[] = $location_info[1]; // 省份
                        if (!empty($location_info[2])) $location_parts[] = $location_info[2]; // 城市
                        
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
    
    /**
     * 从外部API获取IP归属地（支持多个API源）
     */
    private static function getIpLocationFromApi($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
            return null;
        }
        
        // 优先使用IP138 API
        $result = self::getIpLocationFromIp138($ip);
        if ($result && !empty($result['location'])) {
            return $result;
        }
        
        // 备用：使用ip-api.com
        try {
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

    /**
     * 检查是否为内网IP
     */
    private static function isPrivateIp($ip)
    {
        if (empty($ip)) {
            return false;
        }
        
        // 检查IPv4内网IP段
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = ip2long($ip);
            if ($ip_long === false) {
                return false;
            }
            
            // 10.0.0.0/8 (10.0.0.0 - 10.255.255.255)
            if (($ip_long >= ip2long('10.0.0.0')) && ($ip_long <= ip2long('10.255.255.255'))) {
                return true;
            }
            
            // 172.16.0.0/12 (172.16.0.0 - 172.31.255.255)
            if (($ip_long >= ip2long('172.16.0.0')) && ($ip_long <= ip2long('172.31.255.255'))) {
                return true;
            }
            
            // 192.168.0.0/16 (192.168.0.0 - 192.168.255.255)
            if (($ip_long >= ip2long('192.168.0.0')) && ($ip_long <= ip2long('192.168.255.255'))) {
                return true;
            }
            
            // 127.0.0.0/8 (127.0.0.0 - 127.255.255.255) 本地回环
            if (($ip_long >= ip2long('127.0.0.0')) && ($ip_long <= ip2long('127.255.255.255'))) {
                return true;
            }
            
            // 169.254.0.0/16 (169.254.0.0 - 169.254.255.255) 链路本地地址
            if (($ip_long >= ip2long('169.254.0.0')) && ($ip_long <= ip2long('169.254.255.255'))) {
                return true;
            }
        }
        
        // 检查IPv6内网地址
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // ::1 本地回环
            if ($ip == '::1') {
                return true;
            }
            
            // fe80::/10 链路本地地址
            if (substr($ip, 0, 4) == 'fe80') {
                return true;
            }
            
            // fc00::/7 唯一本地地址
            if (substr($ip, 0, 2) == 'fc' || substr($ip, 0, 2) == 'fd') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 获取IP归属地（优先本地查询，然后API查询并存储）
     */
    private static function getIpLocation($ip)
    {
        if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
            return '本地';
        }
        
        // 检查是否为内网IP
        if (self::isPrivateIp($ip)) {
            return '内网';
        }
        
        // 获取插件配置
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_options = $options->plugin('RecentViewsCounter');
        
        // 检查是否启用本地IP数据库
        $enable_local_db = $plugin_options && $plugin_options->enable_local_ip_db == '1';
        
        if ($enable_local_db) {
            // 首先尝试从本地数据库查询
            $local_result = self::getIpLocationFromLocal($ip);
            if ($local_result && !empty($local_result['location'])) {
                // 检查数据是否过期（如果配置了缓存天数）
                if ($plugin_options && $plugin_options->ip_cache_days) {
                    $cache_days = intval($plugin_options->ip_cache_days);
                    $expire_time = time() - ($cache_days * 24 * 3600);
                    
                    // 如果数据已过期，重新查询
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
            
            // 本地没有找到，尝试从API查询并保存
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
            // 不使用本地数据库，直接从API查询
            $api_result = self::getIpLocationFromApi($ip);
            if ($api_result && !empty($api_result['location'])) {
                return $api_result['location'];
            }
        }
        
        return 'Unknown';
    }

    /**
     * 获取最近一个月访问量前N的文章
     */
    public static function getTopArticlesThisMonth($limit = 10)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_options = $options->plugin('RecentViewsCounter');
        
        // 检查是否启用缓存
        if ($plugin_options && $plugin_options->enable_cache == '1') {
            $cache_file = __TYPECHO_ROOT_DIR__ . $plugin_options->cache_file;
            $cache_time = intval($plugin_options->cache_time);
            
            // 检查缓存文件是否存在且未过期
            if (file_exists($cache_file)) {
                $cache_mtime = filemtime($cache_file);
                if (time() - $cache_mtime < $cache_time) {
                    // 从缓存读取数据
                    $cache_data = self::readCacheFile($cache_file, $limit);
                    if ($cache_data !== false) {
                        return $cache_data;
                    }
                }
            }
            
            // 缓存不存在或已过期，从数据库查询
            // 为了确保缓存能满足不同的limit需求，我们查询更多的数据存入缓存
            $cache_data = self::queryTopArticlesFromDB(30); // 缓存30条数据
            
            // 写入缓存
            self::writeCacheFile($cache_file, $cache_data);
            
            // 返回所需数量的数据
            $data = array_slice($cache_data, 0, $limit);
            
            return $data;
        } else {
            // 未启用缓存，直接从数据库查询
            return self::queryTopArticlesFromDB($limit);
        }
    }
    
    /**
     * 从数据库查询热门文章
     */
    private static function queryTopArticlesFromDB($limit = 10)
    {
        $db = Typecho_Db::get();
        $one_month_ago = time() - (30 * 24 * 3600); // 30天前
        
        try {
            $sql = $db->select('vr.cid', 'COUNT(*) as visit_count', 'c.title', 'c.slug')
                      ->from('table.views_records vr')
                      ->join('table.contents c', 'vr.cid = c.cid')
                      ->where('vr.visit_time > ?', $one_month_ago)
                      ->where('c.type = ?', 'post')
                      ->where('c.status = ?', 'publish')
                      ->group('vr.cid')
                      ->order('visit_count', Typecho_Db::SORT_DESC)
                      ->limit($limit);
            
            return $db->fetchAll($sql);
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 查询热门文章时出错: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 读取缓存文件
     */
    private static function readCacheFile($cache_file, $limit)
    {
        try {
            if (!file_exists($cache_file)) {
                return false;
            }
            
            $xml_content = file_get_contents($cache_file);
            if (empty($xml_content)) {
                return false;
            }
            
            $xml = simplexml_load_string($xml_content);
            if ($xml === false) {
                return false;
            }
            
            $data = array();
            // 读取缓存文件中的所有数据
            foreach ($xml->article as $article) {
                $data[] = array(
                    'cid' => (string)$article->cid,
                    'visit_count' => (string)$article->visit_count,
                    'title' => (string)$article->title,
                    'slug' => (string)$article->slug
                );
            }
            
            // 根据需要的数量返回数据
            return array_slice($data, 0, $limit);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 写入缓存文件
     */
    private static function writeCacheFile($cache_file, $data)
    {
        try {
            // 确保缓存目录存在
            $cache_dir = dirname($cache_file);
            if (!is_dir($cache_dir)) {
                @mkdir($cache_dir, 0755, true);
            }
            
            // 生成XML内容
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><articles></articles>');
            
            foreach ($data as $item) {
                $article = $xml->addChild('article');
                $article->addChild('cid', htmlspecialchars($item['cid']));
                $article->addChild('visit_count', htmlspecialchars($item['visit_count']));
                $article->addChild('title', htmlspecialchars($item['title']));
                $article->addChild('slug', htmlspecialchars($item['slug']));
            }
            
            // 写入文件
            $xml_content = $xml->asXML();
            return file_put_contents($cache_file, $xml_content) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取文章在最近一个月的访问次数
     */
    public static function getMonthlyViews($cid)
    {
        $db = Typecho_Db::get();
        $one_month_ago = time() - (30 * 24 * 3600);
        
        try {
            $result = $db->fetchRow($db->select('COUNT(*) as count')
                                     ->from('table.views_records')
                                     ->where('cid = ?', $cid)
                                     ->where('visit_time > ?', $one_month_ago));
            
            return $result ? intval($result['count']) : 0;
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 获取文章月度访问量时出错: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 自动清理过期的访问记录
     */
    public static function cleanOldRecords()
    {
        // 使用静态变量避免重复执行
        static $cleaned = false;
        if ($cleaned) {
            return;
        }
        
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_config = $options->plugin('RecentViewsCounter');
        
        // 检查是否启用自动清理
        if (!$plugin_config || $plugin_config->auto_clean != '1') {
            $cleaned = true;
            return;
        }
        
        // 获取保留天数配置
        $clean_days = $plugin_config->clean_days ? intval($plugin_config->clean_days) : 30;
        $cutoff_time = time() - ($clean_days * 24 * 3600);
        
        // 随机执行清理（约1%的概率），避免每次访问都执行
        if (rand(1, 100) <= 1) {
            $db = Typecho_Db::get();
            try {
                $db->query($db->delete('table.views_records')
                             ->where('visit_time < ?', $cutoff_time));
            } catch (Exception $e) {
                // 静默处理错误，避免影响正常访问
            }
        }
        
        $cleaned = true;
    }
    
    /**
     * 手动清理过期记录
     */
    public static function manualCleanOldRecords($days = 30)
    {
        $cutoff_time = time() - ($days * 24 * 3600);
        $db = Typecho_Db::get();
        
        try {
            $result = $db->query($db->delete('table.views_records')
                                   ->where('visit_time < ?', $cutoff_time));
            
            return $result;
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 手动清理记录时出错: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取数据库统计信息
     */
    public static function getDatabaseStats()
    {
        $db = Typecho_Db::get();
        
        try {
            // 总记录数
            $total_records = $db->fetchRow($db->select('COUNT(*) as count')
                                             ->from('table.views_records'));
            
            // 30天内记录数
            $one_month_ago = time() - (30 * 24 * 3600);
            $recent_records = $db->fetchRow($db->select('COUNT(*) as count')
                                              ->from('table.views_records')
                                              ->where('visit_time > ?', $one_month_ago));
            
            // 最早记录时间
            $earliest_record = $db->fetchRow($db->select('MIN(visit_time) as earliest')
                                               ->from('table.views_records'));
            
            return array(
                'total_records' => $total_records ? $total_records['count'] : 0,
                'recent_records' => $recent_records ? $recent_records['count'] : 0,
                'earliest_time' => $earliest_record ? $earliest_record['earliest'] : null
            );
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 获取数据库统计信息时出错: ' . $e->getMessage());
            return array(
                'total_records' => 0,
                'recent_records' => 0,
                'earliest_time' => null
            );
        }
    }
    
    /**
     * 更新月度统计数据
     */
    public static function updateMonthlyStats($year_month = null)
    {
        if (!$year_month) {
            $year_month = date('Y-m');
        }
        
        $db = Typecho_Db::get();
        
        try {
            // 计算该月的开始和结束时间戳
            $start_time = strtotime($year_month . '-01 00:00:00');
            $end_time = strtotime($year_month . '-01 00:00:00 +1 month') - 1;
            
            // 统计该月总访问量
            $total_visits = $db->fetchRow($db->select('COUNT(*) as count')
                                            ->from('table.views_records')
                                            ->where('visit_time >= ?', $start_time)
                                            ->where('visit_time <= ?', $end_time));
            
            // 统计该月独立访客数
            $unique_visitors = $db->fetchRow($db->select('COUNT(DISTINCT ip) as count')
                                               ->from('table.views_records')
                                               ->where('visit_time >= ?', $start_time)
                                               ->where('visit_time <= ?', $end_time));
            
            $current_time = time();
            
            // 检查是否已存在该月记录
            $existing = $db->fetchRow($db->select('id')
                                        ->from('table.monthly_stats')
                                        ->where('year_month = ?', $year_month));
            
            if ($existing) {
                // 更新现有记录
                $db->query($db->update('table.monthly_stats')
                             ->rows(array(
                                 'total_visits' => $total_visits['count'],
                                 'unique_visitors' => $unique_visitors['count'],
                                 'updated_time' => $current_time
                             ))
                             ->where('year_month = ?', $year_month));
            } else {
                // 插入新记录
                $db->query($db->insert('table.monthly_stats')
                             ->rows(array(
                                 'year_month' => $year_month,
                                 'total_visits' => $total_visits['count'],
                                 'unique_visitors' => $unique_visitors['count'],
                                 'created_time' => $current_time,
                                 'updated_time' => $current_time
                             )));
            }
            
            return array(
                'year_month' => $year_month,
                'total_visits' => $total_visits ? $total_visits['count'] : 0,
                'unique_visitors' => $unique_visitors ? $unique_visitors['count'] : 0
            );
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 更新月度统计时出错: ' . $e->getMessage());
            return array(
                'year_month' => $year_month,
                'total_visits' => 0,
                'unique_visitors' => 0
            );
        }
    }
    
    /**
     * 获取月度统计数据
     */
    public static function getMonthlyStats($limit = 12)
    {
        $db = Typecho_Db::get();
        
        try {
            $stats = $db->fetchAll($db->select('year_month', 'total_visits', 'unique_visitors', 'updated_time')
                                     ->from('table.monthly_stats')
                                     ->order('year_month', Typecho_Db::SORT_DESC)
                                     ->limit($limit));
            
            return array_reverse($stats); // 按时间正序返回
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 获取月度统计时出错: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 自动更新当月统计（在访问记录时调用）
     */
    public static function autoUpdateCurrentMonthStats()
    {
        // 使用静态变量避免重复执行
        static $updated = false;
        if ($updated) {
            return;
        }
        
        // 随机执行更新（约5%的概率），避免每次访问都执行
        if (rand(1, 100) <= 5) {
            try {
                self::updateMonthlyStats();
            } catch (Exception $e) {
                // 静默处理错误，避免影响正常访问
            }
        }
        
        $updated = true;
    }

    /**
     * 添加管理菜单
     */
    public static function addMenu()
    {
        echo '<a href="' . Typecho_Common::url('extending.php?panel=RecentViewsCounter%2FPanel.php', 
             Typecho_Widget::widget('Widget_Options')->adminUrl) . '">近期统计</a>';
    }

    /**
     * 分析访问来源类型
     * @param string $referer 来源URL
     * @return array 包含类型和域名的数组
     */
    public static function analyzeReferer($referer)
    {
        if (empty($referer)) {
            return array('type' => 'direct', 'domain' => '', 'name' => '直接访问');
        }

        $parsed = parse_url($referer);
        $domain = isset($parsed['host']) ? strtolower($parsed['host']) : '';
        
        // 移除www前缀
        $domain = preg_replace('/^www\./', '', $domain);

        // 检查是否为站内链接
        $current_host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        // 移除端口号，只保留主机名
        $current_host = preg_replace('/:\d+$/', '', $current_host);
        $current_host = preg_replace('/^www\./', '', $current_host);
        if ($domain === $current_host) {
            return array('type' => 'internal', 'domain' => $domain, 'name' => '站内跳转');
        }

        // 搜索引擎识别
        $search_engines = array(
            'baidu.com' => '百度',
            'google.com' => 'Google',
            'google.cn' => 'Google',
            'bing.com' => '必应',
            'so.com' => '360搜索',
            'sogou.com' => '搜狗',
            'sm.cn' => '神马搜索',
            'yahoo.com' => 'Yahoo',
            'yandex.com' => 'Yandex'
        );

        foreach ($search_engines as $engine_domain => $engine_name) {
            if (strpos($domain, $engine_domain) !== false) {
                return array('type' => 'search', 'domain' => $domain, 'name' => $engine_name);
            }
        }

        // 社交媒体识别
        $social_media = array(
            'weibo.com' => '微博',
            'weibo.cn' => '微博',
            'qq.com' => 'QQ',
            'wechat.com' => '微信',
            'facebook.com' => 'Facebook',
            'twitter.com' => 'Twitter',
            'linkedin.com' => 'LinkedIn',
            'zhihu.com' => '知乎',
            'douban.com' => '豆瓣',
            'jianshu.com' => '简书',
            'csdn.net' => 'CSDN',
            'cnblogs.com' => '博客园',
            'github.com' => 'GitHub',
            'juejin.cn' => '掘金',
            'gitee.com' => 'Gitee'
        );

        foreach ($social_media as $social_domain => $social_name) {
            if (strpos($domain, $social_domain) !== false) {
                return array('type' => 'social', 'domain' => $domain, 'name' => $social_name);
            }
        }

        // 其他外部链接
        return array('type' => 'external', 'domain' => $domain, 'name' => $domain);
    }

    /**
     * 获取来源统计数据
     * @param int $days 统计天数，默认30天
     * @return array 来源统计数组
     */
    public static function getRefererStats($days = 30)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $start_time = time() - ($days * 24 * 3600);

        try {
            // 使用原生SQL查询避免Typecho查询构建器的语法问题
            $prefix = $db->getPrefix();
            $rows = $db->fetchAll($db->query("SELECT `referer` FROM `{$prefix}views_records` WHERE `visit_time` > {$start_time} AND `referer` IS NOT NULL AND `referer` != ''"));

            $stats = array(
                'search' => array('count' => 0, 'items' => array()),
                'social' => array('count' => 0, 'items' => array()),
                'external' => array('count' => 0, 'items' => array()),
                'internal' => array('count' => 0, 'items' => array()),
                'direct' => array('count' => 0, 'items' => array())
            );

            foreach ($rows as $row) {
                $analysis = self::analyzeReferer($row['referer']);
                $type = $analysis['type'];
                $name = $analysis['name'];

                $stats[$type]['count']++;
                if (!isset($stats[$type]['items'][$name])) {
                    $stats[$type]['items'][$name] = 0;
                }
                $stats[$type]['items'][$name]++;
            }

            // 统计直接访问
            $direct_row = $db->fetchRow($db->query("SELECT COUNT(*) as count FROM `{$prefix}views_records` WHERE `visit_time` > {$start_time} AND (`referer` IS NULL OR `referer` = '')"));
            $direct_count = isset($direct_row['count']) ? intval($direct_row['count']) : 0;

            $stats['direct']['count'] = intval($direct_count);
            $stats['direct']['items']['直接访问'] = intval($direct_count);

            // 按访问量排序
            foreach ($stats as &$category) {
                if (!empty($category['items'])) {
                    arsort($category['items']);
                }
            }

            return $stats;
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 获取来源统计时出错: ' . $e->getMessage());
            return array(
                'search' => array('count' => 0, 'items' => array()),
                'social' => array('count' => 0, 'items' => array()),
                'external' => array('count' => 0, 'items' => array()),
                'internal' => array('count' => 0, 'items' => array()),
                'direct' => array('count' => 0, 'items' => array())
            );
        }
    }

    /**
     * 获取带完整信息的近期热门文章列表（用于主题侧边栏）
     * @param int $limit 返回文章数量
     * @param object $widget 主题Widget对象，用于生成链接
     * @return array 包含完整文章信息的数组
     */
    public static function getTopArticlesWithFullInfo($limit = 8, $widget = null)
    {
        // 获取基础的热门文章数据
        $monthlyPosts = self::getTopArticlesThisMonth($limit);
        
        if (empty($monthlyPosts)) {
            return array();
        }
        
        $db = Typecho_Db::get();
        $result = array();
        
        try {
            foreach ($monthlyPosts as $post) {
                // 获取文章完整信息以生成正确的链接
                $article = $db->fetchRow($db->select('*')
                                           ->from('table.contents')
                                           ->where('cid = ?', $post['cid']));
                
                if ($article) {
                    // 生成文章链接
                    $permalink = '';
                    if ($widget && method_exists($widget, 'options')) {
                        $permalink = Typecho_Router::url('post', $article, $widget->options->index);
                    } else {
                        // 如果没有传入widget，尝试获取默认的options
                        $options = Typecho_Widget::widget('Widget_Options');
                        $permalink = Typecho_Router::url('post', $article, $options->index);
                    }
                    
                    $result[] = array(
                        'cid' => $post['cid'],
                        'title' => $post['title'],
                        'visit_count' => $post['visit_count'],
                        'permalink' => $permalink,
                        'slug' => $post['slug']
                    );
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 获取完整文章信息时出错: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * 渲染近期热门文章HTML（一键调用接口）
     * @param int $limit 返回文章数量
     * @param object $widget 主题Widget对象
     * @param string $title 标题文本，默认为"近期热门"
     * @param string $emptyText 无文章时显示的文本，默认为"暂无近期热门文章"
     * @return string 返回完整的HTML内容
     */
    public static function renderRecentHotArticles($limit = 8, $widget = null, $title = '近期热门', $emptyText = '暂无近期热门文章')
    {
        // 检查插件是否激活
        $activatedPlugins = Typecho_Plugin::export();
        $activatedPluginsList = isset($activatedPlugins['activated']) ? $activatedPlugins['activated'] : array();
        
        if (!isset($activatedPluginsList['RecentViewsCounter']) || !class_exists('RecentViewsCounter_Plugin')) {
            return '';
        }
        
        $html = '<h3 class="widget-title">' . htmlspecialchars($title) . '</h3>' . "\n";
        
        $recentHotArticles = self::getTopArticlesWithFullInfo($limit, $widget);
        
        if (!empty($recentHotArticles)) {
            foreach ($recentHotArticles as $article) {
                $html .= '    <li>' . "\n";
                $html .= '        <a href="' . htmlspecialchars($article['permalink']) . '" title="本月访问: ' . intval($article['visit_count']) . ' 次">' . "\n";
                $html .= '            ' . htmlspecialchars($article['title']) . ' (' . intval($article['visit_count']) . ')' . "\n";
                $html .= '        </a>' . "\n";
                $html .= '    </li>' . "\n";
            }
        } else {
            $html .= '    <li>' . htmlspecialchars($emptyText) . '</li>' . "\n";
        }
        
        return $html;
    }
}