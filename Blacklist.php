<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class RecentViewsCounter_Blacklist
{
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
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) return false;
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
            return !empty($row);
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
