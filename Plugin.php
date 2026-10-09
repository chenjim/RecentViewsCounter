<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once dirname(__FILE__) . '/Blacklist.php';
require_once dirname(__FILE__) . '/lib/IpLookup.php';
require_once dirname(__FILE__) . '/TotalViews.php';

/**
 * 文章近期浏览量统计插件，记录每次访问的详细信息，包括IP地址和归属地
 *
 * @package RecentViewsCounter
 * @author  chenjim
 * @version 1.1.0
 * @link https://github.com/chenjim/RecentViewsCounter
 */
class RecentViewsCounter_Plugin implements Typecho_Plugin_Interface
{
    // Bot判定阈值：UA匹配 或 行为评分 ≥ 此值 即标记为Bot
    const BOT_THRESHOLD = 30;

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
            
            // 执行数据库升级（索引、字段迁移等）
            self::upgradeDatabase();
            
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
            
            // 绑定定时清理和异步IP更新钩子
            Typecho_Plugin::factory('Widget_Archive')->footer = array(
                'RecentViewsCounter_Plugin',
                'handleFooter'
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
            $auto_inc = $isSqlite ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';

            // 创建访问记录表
            $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}views_records` (
                `id` INTEGER NOT NULL PRIMARY KEY {$auto_inc},
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
            $db->query($sql);
            
            // 创建月度统计表
            $monthly_sql = "CREATE TABLE IF NOT EXISTS `{$prefix}monthly_stats` (
                `id` INTEGER NOT NULL PRIMARY KEY {$auto_inc},
                `year_month` TEXT NOT NULL,
                `total_visits` INTEGER NOT NULL DEFAULT 0,
                `unique_visitors` INTEGER NOT NULL DEFAULT 0,
                `created_time` INTEGER NOT NULL,
                `updated_time` INTEGER NOT NULL
            )";
            if (!$isSqlite) {
                $monthly_sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            }
            $db->query($monthly_sql);
            
            // 创建IP归属地本地数据库表
            $ip_location_sql = "CREATE TABLE IF NOT EXISTS `{$prefix}ip_location` (
                `id` INTEGER NOT NULL PRIMARY KEY {$auto_inc},
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
            $db->query($ip_location_sql);
            // 创建IP黑名单表
            RecentViewsCounter_Blacklist::createTable();

            // 创建文章页面访问频率日志表（MySQL 用 VARCHAR(45) 便于联合索引；SQLite 用 TEXT 兼容）
            self::ensureIpRateLogTable();
        } catch (Exception $e) {
            error_log('RecentViewsCounter createTable错误: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 确保 ip_rate_log 表存在（MySQL 用 VARCHAR(45) 优化联合索引）
     */
    private static function ensureIpRateLogTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $driver = $db->getAdapterName();
        $isSqlite = stripos($driver, 'sqlite') !== false;
        $auto_inc = $isSqlite ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';
        $ipCol = $isSqlite ? 'TEXT' : 'VARCHAR(45)';
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}ip_rate_log` (
            `id` INTEGER NOT NULL PRIMARY KEY {$auto_inc},
            `ip` {$ipCol} NOT NULL,
            `url` TEXT NOT NULL,
            `access_time` INTEGER NOT NULL
        )";
        if (!$isSqlite) {
            $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        $db->query($sql);
        // 存量 MySQL TEXT → VARCHAR(45) 迁移（SQLite 无需）
        if (!$isSqlite) {
            try {
                $col = $db->fetchRow($db->query("SHOW COLUMNS FROM `{$prefix}ip_rate_log` LIKE 'ip'"));
                if ($col && stripos($col['Type'], 'varchar(45)') === false) {
                    $db->query("ALTER TABLE `{$prefix}ip_rate_log` MODIFY COLUMN `ip` VARCHAR(45) NOT NULL");
                }
            } catch (Exception $e) {}
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
            $driver = $db->getAdapterName();
            $isSqlite = stripos($driver, 'sqlite') !== false;

            // 创建性能索引（重复执行时静默跳过）
            $indexes = array(
                "idx_vr_cid_time" => "ON {$prefix}views_records (cid, visit_time)",
                "idx_vr_ip_time"  => "ON {$prefix}views_records (ip, visit_time)",
                "idx_vr_time"     => "ON {$prefix}views_records (visit_time)",
                "idx_irl_ip_time" => "ON {$prefix}ip_rate_log (ip, access_time)",
            );
            foreach ($indexes as $name => $def) {
                $sql = $isSqlite
                    ? "CREATE INDEX IF NOT EXISTS {$name} {$def}"
                    : "CREATE INDEX {$name} {$def}";
                try { $db->query($sql); } catch (Exception $e) {}
            }

            // 检查referer字段是否存在（仅MySQL，SQLite自动跳过）
            if (!$isSqlite) {
                try {
                    $columns = $db->fetchAll("SHOW COLUMNS FROM `{$prefix}views_records` LIKE 'referer'");
                    if (empty($columns)) {
                        $db->query("ALTER TABLE `{$prefix}views_records` ADD COLUMN `referer` varchar(500) DEFAULT NULL COMMENT '访问来源URL' AFTER `user_agent`");
                        $db->query("ALTER TABLE `{$prefix}views_records` ADD INDEX `idx_referer` (`referer`(255))");
                    }
                } catch (Exception $e) {
                    error_log('RecentViewsCounter: 检查或添加referer字段时出错: ' . $e->getMessage());
                }
            }

            // IP黑名单唯一索引迁移（兼容旧表无索引的情况）
            try {
                $bl_unique_sql = $isSqlite
                    ? "CREATE UNIQUE INDEX IF NOT EXISTS `idx_ip_blacklist_ip` ON `{$prefix}ip_blacklist` (`ip`)"
                    : "ALTER TABLE `{$prefix}ip_blacklist` ADD UNIQUE INDEX `idx_ip_blacklist_ip` (`ip`)";
                $db->query($bl_unique_sql);
            } catch (Exception $e) {
                error_log('RecentViewsCounter: IP黑名单唯一索引迁移失败，可能存在重复IP: ' . $e->getMessage());
            }

            // 确保 contents.views 列存在（累计浏览量的存储列，实现见 TotalViews.php）
            RecentViewsCounter_TotalViews::ensureColumn();
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
            _t('是否启用IP归属地查询。未知IP由服务端后台进程/定时任务回填，不阻塞页面加载')
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
            30,
            _t('限流阈值（次数）'),
            _t('在窗口时间内允许的最大访问次数，超过则返回429状态码')
        );
        
        $enable_blacklist = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_ip_blacklist',
            array(
                '1' => '启用',
                '0' => '禁用'
            ),
            '1',
            _t('IP黑名单拦截'),
            _t('是否启用IP黑名单功能，黑名单中的IP将被禁止访问并跳过统计')
        );

        $enable_archive_rate_limit = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_archive_rate_limit',
            array('1' => '启用', '0' => '禁用'),
            '1',
            _t('文章页面频率限制'),
            _t('是否启用对 /archives/*.html 页面的访问频率限制，超阈值自动封禁IP')
        );

        $archive_rate_window = new Typecho_Widget_Helper_Form_Element_Text(
            'archive_rate_window',
            NULL,
            '86400',
            _t('文章页面限流窗口（秒）'),
            _t('统计时间窗口，默认86400秒（24小时）')
        );

        $archive_rate_threshold = new Typecho_Widget_Helper_Form_Element_Text(
            'archive_rate_threshold',
            NULL,
            '50',
            _t('文章页面限流阈值（次）'),
            _t('窗口内允许的最大访问次数，超过则自动加入黑名单')
        );

        $whitelist_ips = new Typecho_Widget_Helper_Form_Element_Text(
            'whitelist_ips',
            NULL,
            '127.0.0.1,::1,192.168.0.0/16,10.0.0.0/8,58.35.118.0/24,240e:b8f:96a:,47.100.19.78',
            _t('IP 白名单'),
            _t('白名单内的 IP 不受访问频率限制(429)与自动封禁，也不受黑名单拦截。支持精确 IP、IPv4 CIDR(1.2.3.0/24)、IPv6 前缀(240e:b8f:96a:)，逗号分隔')
        );

        $ip138_token = new Typecho_Widget_Helper_Form_Element_Text(
            'ip138_token',
            NULL,
            '',
            _t('IP138 API Token'),
            _t('IP138接口的访问令牌。留空则不使用IP138，改用免费的ip-api.com接口查询')
        );
        
        $form->addInput($enable_location);
        $form->addInput($enable_blacklist);
        $form->addInput($ip138_token);
        $form->addInput($enable_local_ip_db);
        $form->addInput($ip_cache_days);
        $form->addInput($auto_clean_ip);
        $form->addInput($enable_rate_limit);
        $form->addInput($rate_limit_window);
        $form->addInput($rate_limit_threshold);
        $form->addInput($enable_archive_rate_limit);
        $form->addInput($archive_rate_window);
        $form->addInput($archive_rate_threshold);
        $form->addInput($whitelist_ips);
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
        // 排除管理员访问
        if (Typecho_Widget::widget('Widget_User')->hasLogin()) {
            return;
        }
        
        $ip = RecentViewsCounter_IpLookup::getClientIp();
        $current_time = time();
        
        // 获取插件配置
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_config = $options->plugin('RecentViewsCounter');
        
        // 白名单：不受限流与自动封禁（命中白名单属正常路径，不写日志，避免刷屏）
        $isWhitelistedIp = RecentViewsCounter_Blacklist::isWhitelisted($ip, isset($plugin_config->whitelist_ips) ? $plugin_config->whitelist_ips : null);

        // IP黑名单拦截
        if (!$isWhitelistedIp && $plugin_config && $plugin_config->enable_ip_blacklist == '1' && RecentViewsCounter_Blacklist::isBlacklisted($ip)) {
            while (ob_get_level()) ob_end_clean();
            header('HTTP/1.1 403 Forbidden', true, 403);
            header('Content-Type: text/plain; charset=utf-8');
            echo '403 您的IP已被列入黑名单，访问被拒绝。';
            exit;
        }
        
        // 高频访问硬拦截
        if (!$isWhitelistedIp && $plugin_config && $plugin_config->enable_rate_limit == '1') {
            try {
                $rateWindow = $plugin_config->rate_limit_window ? intval($plugin_config->rate_limit_window) : 60;
                $rateLimit = $plugin_config->rate_limit_threshold ? intval($plugin_config->rate_limit_threshold) : 10;
                
                $row = $db->fetchRow(
                    $db->select('COUNT(*) as count')
                       ->from('table.views_records')
                       ->where('ip = ?', $ip)
                       ->where('visit_time >= ?', $current_time - $rateWindow)
                );
                $recentCount = isset($row['count']) ? intval($row['count']) : 0;
                
                if ($recentCount >= $rateLimit) {
                    while (ob_get_level()) ob_end_clean();
                    header('HTTP/1.1 429 Too Many Requests', true, 429);
                    header('Retry-After: ' . $rateWindow);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo '429 请求过于频繁，请稍后再试。';
                    exit;
                }
            } catch (Exception $e) {
                error_log('RecentViewsCounter: 限流检查异常: ' . $e->getMessage());
            }
        }

        // 文章页面频率限制：/archives/*.html（旧库未配置时默认启用）
        $archiveRateEnabled = !$plugin_config || !isset($plugin_config->enable_archive_rate_limit) || $plugin_config->enable_archive_rate_limit == '1';
        if ($archiveRateEnabled && !$isWhitelistedIp) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: $requestUri;
            if (preg_match('#^(?:/index\.php)?/archives/[^/]+\.html$#i', $requestPath)) {
                $archiveWindow = isset($plugin_config->archive_rate_window) ? intval($plugin_config->archive_rate_window) : 86400;
                if ($archiveWindow <= 0) $archiveWindow = 86400;
                $archiveThreshold = isset($plugin_config->archive_rate_threshold) ? intval($plugin_config->archive_rate_threshold) : 50;
                if ($archiveThreshold <= 0) $archiveThreshold = 50;

                try {
                    // 惰性建表：兼容已激活未重建表的存量环境
                    try { self::ensureIpRateLogTable(); } catch (Exception $e) {}

                    $row = $db->fetchRow(
                        $db->select('COUNT(*) as count')
                           ->from('table.ip_rate_log')
                           ->where('ip = ?', $ip)
                           ->where('access_time >= ?', $current_time - $archiveWindow)
                    );
                    $recentCount = isset($row['count']) ? intval($row['count']) : 0;

                    // 含本次请求达到阈值即封禁（修正差一）
                    if ($recentCount + 1 >= $archiveThreshold) {
                        $reason = "archives页面{$archiveWindow}秒内访问{$recentCount}次(阈值{$archiveThreshold})";
                        RecentViewsCounter_Blacklist::add($ip, $reason);
                        error_log("RecentViewsCounter: archives限流自动封禁 IP={$ip} count={$recentCount} threshold={$archiveThreshold} uri={$requestUri}");

                        while (ob_get_level()) ob_end_clean();
                        header('HTTP/1.1 403 Forbidden', true, 403);
                        header('Content-Type: text/html; charset=utf-8');
                        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body>';
                        echo '<h1>403 Forbidden</h1>';
                        echo '<p>您的IP因频繁访问文章页面已被自动封禁。</p>';
                        echo '</body></html>';
                        exit;
                    }

                    // 记录本次访问
                    $db->query($db->insert('table.ip_rate_log')->rows(array(
                        'ip' => $ip,
                        'url' => $requestUri,
                        'access_time' => $current_time
                    )));
                } catch (Exception $e) {
                    error_log('RecentViewsCounter: 文章页面限流检查异常: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * 处理index.php的begin钩子
     */
    public static function handleIndexBegin()
    {
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
        $ip = RecentViewsCounter_IpLookup::getClientIp();
        $current_time = time();
        
        // 检查是否为重复访问
        if (self::isDuplicateVisit($cid, $ip, $current_time)) {
            return;
        }

        // 获取数据库连接和插件配置
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_config = $options->plugin('RecentViewsCounter');

        // IP黑名单拦截（不记录访问）
        if ($plugin_config && $plugin_config->enable_ip_blacklist == '1'
            && !RecentViewsCounter_Blacklist::isWhitelisted($ip, isset($plugin_config->whitelist_ips) ? $plugin_config->whitelist_ips : null)
            && RecentViewsCounter_Blacklist::isBlacklisted($ip)) {
            return;
        }

        // 快速获取IP归属地（优先本地缓存）
        $location = null;
        if ($plugin_config && $plugin_config->enable_location == '1') {
            // 检查是否为内网IP
            if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
                $location = '本地';
            } elseif (RecentViewsCounter_IpLookup::isPrivateIp($ip)) {
                $location = '内网';
            } else {
                // 尝试从本地数据库快速查询
                $local_result = RecentViewsCounter_IpLookup::getIpLocationFromLocal($ip);
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
            
            // 累计浏览量 +1（实现见 TotalViews.php）
            RecentViewsCounter_TotalViews::increment($cid);

            // 自动更新当月统计
            self::autoUpdateCurrentMonthStats();
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 记录访问信息时出错: ' . $e->getMessage());
        }
    }

    /**
     * 检查是否为重复访问
     */
    private static function isDuplicateVisit($cid, $ip, $current_time)
    {
        $cookie_key = "views_month_{$cid}_{$ip}";
        if (Typecho_Cookie::get($cookie_key)) {
            return true;
        }

        return false;
    }

    /**
     * 统一 footer 处理：清理 + 异步IP beacon
     */
    public static function handleFooter()
    {
        self::cleanOldRecords();
        self::cleanOldIpLocations();
        self::triggerIpLocationUpdate();
        self::injectVisitRecordsJs();
    }

    /**
     * 自动清理过期的 IP 归属地缓存（仅由 auto_clean_ip 控制，每日最多一次）
     * 过期记录直接删除，下次该 IP 访问时再重新查询记录
     */
    private static function cleanOldIpLocations()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_config = $options->plugin('RecentViewsCounter');

        $autoCleanIp = $plugin_config && isset($plugin_config->auto_clean_ip) && $plugin_config->auto_clean_ip == '1';
        if (!$autoCleanIp) return;

        // 每天最多执行一次
        $lockFile = sys_get_temp_dir() . '/rvc_ip_clean.lock';
        $cooldown = 86400;
        if (file_exists($lockFile)) {
            $mtime = @filemtime($lockFile);
            if ($mtime && (time() - $mtime) < $cooldown) return;
        }

        // 非阻塞文件锁，防止并发
        $fp = @fopen($lockFile, 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            if ($fp) fclose($fp);
            return;
        }

        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $ipCacheDays = $plugin_config && $plugin_config->ip_cache_days ? intval($plugin_config->ip_cache_days) : 90;
            if ($ipCacheDays <= 0) $ipCacheDays = 90;
            $ipCutoff = time() - ($ipCacheDays * 24 * 3600);

            $db->query("DELETE FROM {$prefix}ip_location WHERE updated_time < {$ipCutoff}");
            @touch($lockFile);
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 自动清理IP归属地数据出错: ' . $e->getMessage());
        }

        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * 后台进程触发 IP 归属地回填（绕过广告拦截器）
     */
    private static function triggerIpLocationUpdate()
    {
        static $triggered = false;
        if ($triggered) return;
        $triggered = true;

        if (!function_exists('exec')) return;

        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $plugin_config = $options->plugin('RecentViewsCounter');
            if (!$plugin_config || $plugin_config->enable_location != '1') return;

            // 5 分钟内仅触发一次
            $lockFile = sys_get_temp_dir() . '/rvc_ip_location.lock';
            $cooldown = 300;
            if (file_exists($lockFile)) {
                $mtime = @filemtime($lockFile);
                if ($mtime && (time() - $mtime) < $cooldown) return;
            }

            // 非阻塞文件锁，防止并发
            $fp = @fopen($lockFile, 'c');
            if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
                if ($fp) fclose($fp);
                return;
            }

            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $row = $db->fetchRow($db->query(
                "SELECT 1 FROM {$prefix}views_records v
                 WHERE (v.location IS NULL OR v.location = '')
                   AND v.ip != '' AND v.ip NOT IN ('127.0.0.1','::1')
                   AND NOT EXISTS (
                       SELECT 1 FROM {$prefix}ip_location l
                       WHERE l.ip = v.ip AND l.location = 'Unknown'
                   )
                 LIMIT 1"
            ));
            if (!empty($row)) {
                $phpBin = PHP_BINDIR . '/php';
                exec($phpBin . ' ' . escapeshellarg(__DIR__ . '/cron-update-ip-locations.php') . ' > /dev/null 2>&1 &');
            }

            flock($fp, LOCK_UN);
            fclose($fp);
        } catch (Exception $e) {
            error_log('RecentViewsCounter: triggerIpLocationUpdate error: ' . $e->getMessage());
        }
    }

    /**
     * 注入前端访问记录查看 JS（内联方式，不依赖外部文件加载）
     */
    private static function injectVisitRecordsJs()
    {
        static $injected = false;
        if ($injected) return;
        $injected = true;

        $options = Typecho_Widget::widget('Widget_Options');
        $adminUrl = $options->adminUrl;
        $tzOffset = intval($options->timezone);
        $requestJsPath = dirname(__FILE__) . '/assets/utils/request.js';
        $requestJsUrl = rtrim($options->pluginUrl, '/') . '/RecentViewsCounter/assets/utils/request.js';
        if (file_exists($requestJsPath)) {
            $requestJsUrl .= '?v=' . @filemtime($requestJsPath);
        }
        echo <<<EOS
<script>
window.RVC_ADMIN_URL="{$adminUrl}";
window.RVC_TZ_OFFSET={$tzOffset};
</script>
<script src="{$requestJsUrl}"></script>
<script>
function showVisitRecords(cid,title){
 var modal=document.createElement('div');
 modal.id='visitRecordsModal';
 modal.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:center;justify-content:center;';
 var mc=document.createElement('div');
 mc.style.cssText='background:white;padding:20px;border-radius:8px;max-width:80%;max-height:80%;overflow:auto;position:relative;';
 mc.innerHTML='<h3 style="margin-top:0;color:#467b96;">\u6587\u7ae0\u8bbf\u95ee\u8bb0\u5f55</h3><p><strong>\u6587\u7ae0\u6807\u9898\uff1a</strong>'+title+'</p><div id="recordsContent">\u6b63\u5728\u52a0\u8f7d\u8bbf\u95ee\u8bb0\u5f55...</div><button onclick="closeVisitRecords()" style="position:absolute;top:10px;right:15px;background:none;border:none;font-size:20px;cursor:pointer;color:#999;">\u00d7</button><div style="text-align:center;margin-top:20px;"><button onclick="closeVisitRecords()" style="background:#467b96;color:white;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;">\u5173\u95ed</button></div>';
 modal.appendChild(mc);
 document.body.appendChild(modal);
 loadVisitRecords(cid);
}
function closeVisitRecords(){
 var modal=document.getElementById('visitRecordsModal');
 if(modal)modal.remove();
}
function loadVisitRecords(cid){
 var xhr=new XMLHttpRequest();
 var base=window.RVC_ADMIN_URL;
 var url=base?base+'extending.php?panel=RecentViewsCounter%2FPanel.php':window.location.href;
 xhr.open('POST',url,true);
 xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
 xhr.onreadystatechange=function(){
  if(xhr.readyState===4){
   var el=document.getElementById('recordsContent');
   if(!el)return;
   if(xhr.status===200){
    try{
     var resp=JSON.parse(xhr.responseText);
     if(resp.success){displayVisitRecords(resp.data);}
     else{el.innerHTML='<p style="color:#dc3545;">\u52a0\u8f7d\u5931\u8d25\uff1a'+(resp.message||'\u672a\u77e5\u9519\u8bef')+'</p>';}
    }catch(e){el.innerHTML='<p style="color:#dc3545;">\u6570\u636e\u89e3\u6790\u5931\u8d25</p>';}
   }else{el.innerHTML='<p style="color:#dc3545;">\u7f51\u7edc\u8bf7\u6c42\u5931\u8d25</p>';}
  }
 };
 xhr.send('action=get_visit_records&cid='+cid);
}
function displayVisitRecords(records){
 var el=document.getElementById('recordsContent');
 if(!el)return;
 if(!records||records.length===0){el.innerHTML='<p>\u6682\u65e0\u8bbf\u95ee\u8bb0\u5f55</p>';return;}
 var h='<div style="max-height:400px;overflow-y:auto;"><table style="width:100%;border-collapse:collapse;margin:0;"><thead><tr style="background:#f5f5f5;"><th style="padding:8px;border:1px solid #ddd;text-align:left;">\u8bbf\u95ee\u65f6\u95f4</th><th style="padding:8px;border:1px solid #ddd;text-align:left;">IP\u5730\u5740</th><th style="padding:8px;border:1px solid #ddd;text-align:left;">User Agent</th><th style="padding:8px;border:1px solid #ddd;text-align:left;">\u5f52\u5c5e\u5730</th></tr></thead><tbody>';
 records.forEach(function(r){
  var t=rvcFormatTime(r.visit_time);
  var ua=r.user_agent||'\u672a\u77e5';
  var loc=r.location||'\u672a\u77e5\u5730\u533a';
  if(ua.length>50)ua=ua.substring(0,50)+'...';
  if(loc.length>30)loc=loc.substring(0,30)+'...';
  h+='<tr><td style="padding:8px;border:1px solid #ddd;">'+t+'</td><td style="padding:8px;border:1px solid #ddd;">'+r.ip+'</td><td style="padding:8px;border:1px solid #ddd;" title="'+(r.user_agent||'')+'">'+ua+'</td><td style="padding:8px;border:1px solid #ddd;" title="'+(r.location||'')+'">'+loc+'</td></tr>';
 });
 h+='</tbody></table></div><p style="margin-top:15px;color:#666;font-size:12px;">\u663e\u793a\u6700\u8fd1100\u6761\u8bbf\u95ee\u8bb0\u5f55</p>';
 el.innerHTML=h;
}
</script>
EOS;
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

    /* ==================================================================
     * ViewsCounter 兼容层（实现全部在 TotalViews.php）
     *
     * 保留与原 ViewsCounter 同名的入口，主题从 ViewsCounter_Plugin::x()
     * 换成 RecentViewsCounter_Plugin::x() 即可，无需改语义。
     * 这里只做转发，逻辑不放在本文件，避免主文件继续膨胀。
     * ================================================================== */

    /**
     * 累计浏览量原子自增（供 recordVisit 调用）
     *
     * @param int $cid
     */
    public static function incrementTotalViews($cid)
    {
        RecentViewsCounter_TotalViews::increment($cid);
    }

    /**
     * 批量预热累计浏览量缓存（兼容入口）
     *
     * @param array $cids
     */
    public static function preloadViews(array $cids)
    {
        RecentViewsCounter_TotalViews::preload($cids);
    }

    /**
     * 取指定文章的累计浏览量（兼容 ViewsCounter::getViewsById）
     *
     * @param int $cid
     * @return int
     */
    public static function getViewsById($cid)
    {
        return RecentViewsCounter_TotalViews::getById($cid);
    }

    /**
     * 取当前文章的累计浏览量（兼容 ViewsCounter::getViews）
     *
     * @return int
     */
    public static function getViews()
    {
        return RecentViewsCounter_TotalViews::getCurrent();
    }

    /**
     * 累计浏览量排行榜（兼容 ViewsCounter::getMostViewed）
     *
     * @param int|null $limit
     * @return array
     */
    public static function getMostViewed($limit = null)
    {
        return RecentViewsCounter_TotalViews::getTop($limit);
    }

    /**
     * getMostViewed 的语义别名
     *
     * @param int|null $limit
     * @return array
     */
    public static function getMostViewedAllTime($limit = null)
    {
        return RecentViewsCounter_TotalViews::getTopAllTime($limit);
    }

    /**
     * 自动清理过期的访问记录
     */
    public static function cleanOldRecords()
    {
        static $cleaned = false;
        if ($cleaned) {
            return;
        }
        
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_config = $options->plugin('RecentViewsCounter');
        
        if (!$plugin_config || $plugin_config->auto_clean != '1') {
            $cleaned = true;
            return;
        }
        
        $clean_days = $plugin_config->clean_days ? intval($plugin_config->clean_days) : 30;
        $cutoff_time = time() - ($clean_days * 24 * 3600);
        
        // 随机执行清理（约2%的概率），避免每次访问都执行
        if (rand(1, 100) <= 2) {
            $db = Typecho_Db::get();
            try {
                $db->query($db->delete('table.views_records')
                             ->where('visit_time < ?', $cutoff_time));
            } catch (Exception $e) {
                // 静默处理错误，避免影响正常访问
            }
            // 清理 ip_rate_log 过期记录（窗口+1小时，避免边界误删）
            try {
                $archiveWindow = $plugin_config && isset($plugin_config->archive_rate_window) ? intval($plugin_config->archive_rate_window) : 86400;
                if ($archiveWindow <= 0) $archiveWindow = 86400;
                $rateLogCutoff = time() - $archiveWindow - 3600;
                $db->query($db->delete('table.ip_rate_log')->where('access_time < ?', $rateLogCutoff));
            } catch (Exception $e) {}
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
            
            // 统计该月非Bot访问数（排除匹配Bot UA的记录数）
            $bot_conds = array();
            $prefix_tmp = $db->getPrefix();
            foreach (self::getBotPatterns() as $p) {
                $bot_conds[] = "LOWER(user_agent) LIKE '%" . strtolower($p) . "%'";
            }
            $bot_sql = "SELECT COUNT(*) as count FROM {$prefix_tmp}views_records WHERE (" . implode(' OR ', $bot_conds) . ") AND visit_time >= " . intval($start_time) . " AND visit_time <= " . intval($end_time);
            $bot_visit_count = $db->fetchRow($db->query($bot_sql));
            // unique_visitors 实际存储非Bot访问数（总访问量 - Bot UA匹配数），非字面独立访客
            $unique_visitors = array('count' => intval($total_visits['count']) - intval($bot_visit_count['count']));
            
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
     * 获取Bot UA模式列表（单一定义，多处复用）
     */
    public static function getBotPatterns()
    {
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
            'Baiduspider-render', 'Baiduspider-image', 'Baiduspider-news', 'Baiduspider-video',
            'Sogou web spider', 'Sogou News Spider', 'Sogou Pic Spider', 'Sogou Video Spider',
            'HaoSouSpider', '360Spider', 'JikeSpider', 'YisouSpider', 'ToutiaoSpider',
            'ByteSpider', 'PetalBot',
            'GPTBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web', 'PerplexityBot',
            'Google-Extended', 'Google-CloudVertex', 'Amazonbot',
            'Screaming Frog SEO Spider', 'Scrapy', 'python-requests', 'aiohttp',
            'httpx', 'curl', 'wget', 'Go-http-client', 'fasthttp',
            'spider', 'crawler', 'bot'
        );
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
            $prefix = $db->getPrefix();
            $rows = $db->fetchAll($db->query("SELECT `referer`, COUNT(*) AS cnt FROM `{$prefix}views_records` WHERE `visit_time` > {$start_time} AND `referer` IS NOT NULL AND `referer` != '' GROUP BY `referer`"));

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
                $cnt = intval($row['cnt']);

                $stats[$type]['count'] += $cnt;
                if (!isset($stats[$type]['items'][$name])) {
                    $stats[$type]['items'][$name] = 0;
                }
                $stats[$type]['items'][$name] += $cnt;
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