<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class RecentViewsCounter_Blacklist
{
    private static function isValidIpOrCidr($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) return true;
        if (preg_match('#^(\d{1,3}\.){3}\d{1,3}/(\d{1,2})$#', $ip, $m)) {
            $parts = explode('/', $ip);
            $mask = intval($parts[1]);
            return $mask >= 0 && $mask <= 32 && filter_var($parts[0], FILTER_VALIDATE_IP);
        }
        return false;
    }

    private static function ipInCidr($ip, $cidr)
    {
        $parts = explode('/', $cidr);
        $network = ip2long($parts[0]);
        $ip_long = ip2long($ip);
        if ($network === false || $ip_long === false) return false;
        $mask = -1 << (32 - intval($parts[1]));
        return ($ip_long & $mask) === ($network & $mask);
    }

    /**
     * 解析白名单配置（逗号/分号/空白分隔）
     */
    public static function parseWhitelist($raw)
    {
        $list = array();
        if (!is_string($raw) || trim($raw) === '') return $list;
        foreach (preg_split('/[\s,;]+/', $raw) as $item) {
            $item = trim($item);
            if ($item !== '') $list[] = $item;
        }
        return $list;
    }

    /**
     * IP 是否命中白名单：精确 IP、IPv4 CIDR、IPv6 CIDR/前缀；127.0.0.1 与 ::1 恒放行
     */
    public static function isWhitelisted($ip, $raw = null)
    {
        if (empty($ip)) return false;
        if ($ip === '127.0.0.1' || $ip === '::1') return true;
        foreach (self::parseWhitelist($raw) as $rule) {
            if ($rule === $ip) return true;
            if (strpos($rule, '/') !== false) {
                if (strpos($rule, ':') !== false) {
                    if (self::ipv6InCidr($ip, $rule)) return true;
                } elseif (self::ipInCidr($ip, $rule)) {
                    return true;
                }
            } elseif (strpos($rule, ':') !== false) {
                if (strpos($ip, $rule) === 0) return true;   // IPv6 前缀写法，如 240e:b8f:96a:
            }
        }
        return false;
    }

    private static function ipv6InCidr($ip, $cidr)
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) return false;
        $binIp = @inet_pton($ip);
        $binNet = @inet_pton($parts[0]);
        if ($binIp === false || $binNet === false || strlen($binIp) !== strlen($binNet)) return false;
        $bits = intval($parts[1]);
        if ($bits < 0 || $bits > strlen($binNet) * 8) return false;
        $bytes = intdiv($bits, 8);
        $rest = $bits % 8;
        if ($bytes > 0 && substr($binIp, 0, $bytes) !== substr($binNet, 0, $bytes)) return false;
        if ($rest > 0) {
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($binIp[$bytes]) & $mask) !== (ord($binNet[$bytes]) & $mask)) return false;
        }
        return true;
    }

    public static function createTable()
    {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $driver = $db->getAdapterName();
            $isSqlite = stripos($driver, 'sqlite') !== false;
            $auto_inc = $isSqlite ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';

            $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}ip_blacklist` (
                `id` INTEGER NOT NULL PRIMARY KEY {$auto_inc},
                `ip` TEXT NOT NULL,
                `reason` TEXT,
                `created_time` INTEGER NOT NULL
            )";
            if (!$isSqlite) {
                $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            }
            $db->query($sql);

            try {
                $unique_sql = $isSqlite
                    ? "CREATE UNIQUE INDEX IF NOT EXISTS `idx_ip_blacklist_ip` ON `{$prefix}ip_blacklist` (`ip`)"
                    : "ALTER TABLE `{$prefix}ip_blacklist` ADD UNIQUE INDEX `idx_ip_blacklist_ip` (`ip`)";
                $db->query($unique_sql);
            } catch (Exception $e) {
                error_log('RecentViewsCounter_Blacklist: 唯一索引创建失败，可能存在重复IP: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter_Blacklist::createTable error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function add($ip, $reason = '')
    {
        if (empty($ip) || !self::isValidIpOrCidr($ip)) return false;
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();

            $exists = $db->fetchRow($db->select('id')->from($prefix . 'ip_blacklist')->where('ip = ?', $ip));
            if ($exists) return false;

            $db->query($db->insert($prefix . 'ip_blacklist')->rows(array(
                'ip' => $ip,
                'reason' => $reason,
                'created_time' => time()
            )));
            return true;
        } catch (Exception $e) {
            error_log('RecentViewsCounter_Blacklist::add error: ' . $e->getMessage());
            return false;
        }
    }

    public static function remove($ip)
    {
        if (empty($ip)) return false;
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $db->query($db->delete($prefix . 'ip_blacklist')->where('ip = ?', $ip));
            return true;
        } catch (Exception $e) {
            error_log('RecentViewsCounter_Blacklist::remove error: ' . $e->getMessage());
            return false;
        }
    }

    public static function isBlacklisted($ip)
    {
        if (empty($ip)) return false;
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();

            $row = $db->fetchRow($db->select('id')->from($prefix . 'ip_blacklist')->where('ip = ?', $ip));
            if (!empty($row)) return true;

            $cidrs = $db->fetchAll($db->select('ip')->from($prefix . 'ip_blacklist')->where('ip LIKE ?', '%/%'));
            foreach ($cidrs as $cidr) {
                if (self::ipInCidr($ip, $cidr['ip'])) return true;
            }
            return false;
        } catch (Exception $e) {
            error_log('RecentViewsCounter_Blacklist::isBlacklisted error: ' . $e->getMessage());
            return false;
        }
    }

    public static function getList($page = 1, $limit = 20)
    {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $offset = ($page - 1) * $limit;
            $total = $db->fetchRow($db->select('COUNT(*) as count')->from($prefix . 'ip_blacklist'));
            $totalCount = $total ? intval($total['count']) : 0;

            $data = $db->fetchAll($db->select('*')
                ->from($prefix . 'ip_blacklist')
                ->order('created_time', Typecho_Db::SORT_DESC)
                ->limit($limit)
                ->offset($offset));

            if (!$data) $data = array();

            return array('data' => $data, 'total' => $totalCount);
        } catch (Exception $e) {
            error_log('RecentViewsCounter_Blacklist::getList error: ' . $e->getMessage());
            return array('data' => array(), 'total' => 0);
        }
    }

    public static function getCount()
    {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $row = $db->fetchRow($db->select('COUNT(*) as count')->from($prefix . 'ip_blacklist'));
            return $row ? intval($row['count']) : 0;
        } catch (Exception $e) {
            error_log('RecentViewsCounter_Blacklist::getCount error: ' . $e->getMessage());
            return 0;
        }
    }
}
