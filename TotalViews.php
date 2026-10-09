<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 累计浏览量（原 ViewsCounter 插件能力的兼容层）
 *
 * 为什么单独一个文件：这部分是为「替掉 ViewsCounter」而做的兼容能力，
 * 与「窗口内访问明细」是两条独立的口径，拆出来 Plugin.php 只留同名入口，
 * 便于日后单独维护/移除，不用在 2000 行的主文件里翻。
 *
 * 存储沿用 typecho_contents.views 列：永久保留、单调递增。
 * 与时间窗明细（views_records，按 clean_days 滚动清理）互补：
 *   累计浏览量 → contents.views  → 文章页/列表页「阅读量」、总榜
 *   时间窗明细 → views_records   → 周榜/月榜、后台报表
 *
 * 对外的同名入口仍在 RecentViewsCounter_Plugin（见该文件末尾的兼容层转发）。
 */
class RecentViewsCounter_TotalViews
{
    /**
     * 单次请求内的累计浏览量缓存，避免列表页逐条查库
     *
     * @var array
     */
    private static $cache = array();

    /**
     * 确保 contents.views 列存在
     *
     * 该列原本由 ViewsCounter 插件在 activate() 时创建，能力并入后由本插件自己保证。
     * 由 Plugin::upgradeDatabase() 调用（只在插件启用/升级时执行）。
     *
     * @return void
     */
    public static function ensureColumn()
    {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $isSqlite = stripos($db->getAdapterName(), 'sqlite') !== false;

            $hasViews = false;
            if ($isSqlite) {
                foreach ($db->fetchAll("PRAGMA table_info(`{$prefix}contents`)") as $col) {
                    if (isset($col['name']) && 'views' === $col['name']) {
                        $hasViews = true;
                        break;
                    }
                }
            } else {
                $hasViews = !empty($db->fetchAll("SHOW COLUMNS FROM `{$prefix}contents` LIKE 'views'"));
            }

            if (!$hasViews) {
                $db->query("ALTER TABLE `{$prefix}contents` ADD COLUMN `views` INT NOT NULL DEFAULT 0");
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 检查或添加 contents.views 列时出错: ' . $e->getMessage());
        }
    }

    /**
     * 累计浏览量原子自增
     *
     * 这里走裸 SQL 而不是 Query::rows()，原因有二：
     *   1. rows() 会对每个值做 quoteValue，'views + 1' 会被当成字符串写入；
     *   2. 原子自增避免了「先 SELECT 再 UPDATE」在并发下丢计数。
     *
     * @param int $cid
     * @return void
     */
    public static function increment($cid)
    {
        try {
            $db = Typecho_Db::get();
            $db->query(
                'UPDATE `' . $db->getPrefix() . 'contents`'
                . ' SET `views` = `views` + 1 WHERE `cid` = ' . (int) $cid,
                Typecho_Db::WRITE,
                Typecho_Db::UPDATE
            );
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 累计浏览量自增失败 cid=' . $cid . '：' . $e->getMessage());
        }
    }

    /**
     * 批量预热累计浏览量缓存，列表页建议先调一次
     *
     * @param array $cids
     * @return void
     */
    public static function preload(array $cids)
    {
        $cids = array_values(array_unique(array_map('intval', $cids)));
        $cids = array_values(array_diff($cids, array_keys(self::$cache)));
        if (empty($cids)) {
            return;
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll(
                $db->select('cid', 'views')->from('table.contents')->where('cid IN ?', $cids)
            );
            foreach ($rows as $row) {
                self::$cache[(int) $row['cid']] = (int) $row['views'];
            }
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 批量读取累计浏览量失败：' . $e->getMessage());
        }

        // 查不到的行（已删除等）补 0，避免反复回查
        foreach ($cids as $cid) {
            if (!array_key_exists($cid, self::$cache)) {
                self::$cache[$cid] = 0;
            }
        }
    }

    /**
     * 取指定文章的累计浏览量
     *
     * @param int $cid
     * @return int
     */
    public static function getById($cid)
    {
        $cid = (int) $cid;
        if (!array_key_exists($cid, self::$cache)) {
            self::preload(array($cid));
        }
        return self::$cache[$cid];
    }

    /**
     * 取当前文章的累计浏览量
     *
     * @return int
     */
    public static function getCurrent()
    {
        $cid = (int) Typecho_Widget::widget('Widget_Archive')->cid;
        return $cid > 0 ? self::getById($cid) : 0;
    }

    /**
     * 累计浏览量排行榜（总榜）
     *
     * @param int|null $limit 传 null 时使用插件配置 top_count
     * @return array 每项为 push 后的文章数组（含 title/views/permalink 等）
     */
    public static function getTop($limit = null)
    {
        if (null === $limit) {
            $config = Typecho_Widget::widget('Widget_Options')->plugin('RecentViewsCounter');
            $limit = ($config && isset($config->top_count)) ? intval($config->top_count) : 10;
        }
        $limit = max(1, (int) $limit);

        try {
            $db = Typecho_Db::get();
            $posts = $db->fetchAll(
                $db->select()->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('password IS NULL')
                    ->order('views', Typecho_Db::SORT_DESC)
                    ->limit($limit)
            );
        } catch (Exception $e) {
            error_log('RecentViewsCounter: 查询累计排行榜失败：' . $e->getMessage());
            return array();
        }

        $contents = Typecho_Widget::widget('Widget_Abstract_Contents');
        $list = array();
        foreach ($posts as $post) {
            $list[] = $contents->push($post);
        }
        return $list;
    }

    /**
     * getTop 的语义别名，避免与「本月热门」混淆
     *
     * @param int|null $limit
     * @return array
     */
    public static function getTopAllTime($limit = null)
    {
        return self::getTop($limit);
    }
}
