<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * RecentViewsCounter 插件辅助函数
 * 用于在主题中方便地调用插件功能
 */
class RecentViewsCounter_Helper
{
    /**
     * 获取当前文章的月度访问量
     * 在文章页面中调用
     */
    public static function getCurrentMonthlyViews()
    {
        $widget = Typecho_Widget::widget('Widget_Archive');
        if ($widget->is('single')) {
            return RecentViewsCounter_Plugin::getMonthlyViews($widget->cid);
        }
        return 0;
    }

    /**
     * 显示当前文章的月度访问量
     * 在主题中直接输出
     */
    public static function showCurrentMonthlyViews($format = '本月访问: %d 次')
    {
        $views = self::getCurrentMonthlyViews();
        printf($format, $views);
    }

    /**
     * 获取热门文章列表
     * 用于在侧边栏或其他位置显示热门文章
     */
    public static function getPopularPosts($limit = 5)
    {
        return RecentViewsCounter_Plugin::getTopArticlesThisMonth($limit);
    }

    /**
     * 显示热门文章列表的HTML
     */
    public static function showPopularPosts($limit = 5, $title = '本月热门文章')
    {
        $articles = self::getPopularPosts($limit);
        $options = Typecho_Widget::widget('Widget_Options');
        
        if (!empty($articles)) {
            echo '<div class="popular-posts">';
            if ($title) {
                echo '<h3>' . htmlspecialchars($title) . '</h3>';
            }
            echo '<ul>';
            
            foreach ($articles as $article) {
                $url = Typecho_Common::url($article['slug'], $options->index);
                echo '<li>';
                echo '<a href="' . $url . '" title="访问量: ' . $article['visit_count'] . '">';
                echo htmlspecialchars($article['title']);
                echo '</a>';
                echo '<span class="views-count">(' . $article['visit_count'] . ')</span>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * 获取指定文章的月度访问量
     */
    public static function getPostMonthlyViews($cid)
    {
        return RecentViewsCounter_Plugin::getMonthlyViews($cid);
    }

    /**
     * 在文章列表中显示月度访问量
     */
    public static function showPostMonthlyViews($cid, $format = '%d')
    {
        $views = self::getPostMonthlyViews($cid);
        printf($format, $views);
    }

    /**
     * 获取网站总体统计信息
     */
    public static function getSiteStats()
    {
        $db = Typecho_Db::get();
        $one_month_ago = time() - (30 * 24 * 3600);
        $today_start = strtotime(date('Y-m-d 00:00:00'));
        
        // 本月总访问量
        try {
            $monthly_total = $db->fetchRow($db->select('COUNT(*) as count')
                                            ->from('table.views_records')
                                            ->where('visit_time > ?', $one_month_ago));
        } catch (Exception $e) {
            $monthly_total = array('count' => 0);
        }
        
        // 本月独立访客
        try {
            $monthly_unique = $db->fetchRow($db->select('COUNT(DISTINCT ip) as count')
                                              ->from('table.views_records')
                                              ->where('visit_time > ?', $one_month_ago));
        } catch (Exception $e) {
            $monthly_unique = array('count' => 0);
        }
        
        // 今日访问量
        try {
            $today_total = $db->fetchRow($db->select('COUNT(*) as count')
                                          ->from('table.views_records')
                                          ->where('visit_time > ?', $today_start));
        } catch (Exception $e) {
            $today_total = array('count' => 0);
        }
        
        return array(
            'monthly_total' => $monthly_total ? intval($monthly_total['count']) : 0,
            'monthly_unique' => $monthly_unique ? intval($monthly_unique['count']) : 0,
            'today_total' => $today_total ? intval($today_total['count']) : 0
        );
    }

    /**
     * 显示网站统计信息
     */
    public static function showSiteStats($template = null)
    {
        $stats = self::getSiteStats();
        
        if ($template) {
            printf($template, $stats['monthly_total'], $stats['monthly_unique'], $stats['today_total']);
        } else {
            echo '<div class="site-stats">';
            echo '<span>本月访问: ' . $stats['monthly_total'] . '</span> | ';
            echo '<span>独立访客: ' . $stats['monthly_unique'] . '</span> | ';
            echo '<span>今日访问: ' . $stats['today_total'] . '</span>';
            echo '</div>';
        }
    }

    /**
     * 检查插件是否已激活
     */
    public static function isPluginActive()
    {
        $plugins = Typecho_Plugin::export();
        return isset($plugins['activated']['RecentViewsCounter']);
    }

    /* ==================================================================
     * 累计浏览量（原 ViewsCounter 的能力，已并入本插件）
     * 与「月度访问量」的区别：月度取 views_records 明细，累计取 contents.views
     * ================================================================== */

    /**
     * 获取当前文章的累计浏览量
     */
    public static function getCurrentTotalViews()
    {
        return RecentViewsCounter_Plugin::getViews();
    }

    /**
     * 显示当前文章的累计浏览量
     */
    public static function showCurrentTotalViews($format = '阅读量: %d')
    {
        printf($format, self::getCurrentTotalViews());
    }

    /**
     * 获取指定文章的累计浏览量
     */
    public static function getPostTotalViews($cid)
    {
        return RecentViewsCounter_Plugin::getViewsById($cid);
    }

    /**
     * 显示指定文章的累计浏览量
     */
    public static function showPostTotalViews($cid, $format = '%d')
    {
        printf($format, self::getPostTotalViews($cid));
    }

    /**
     * 获取累计浏览量排行榜（总榜）
     */
    public static function getMostViewedAllTime($limit = 5)
    {
        return RecentViewsCounter_Plugin::getMostViewedAllTime($limit);
    }
}

/**
 * 便捷函数，用于在主题中快速调用
 */

// 显示当前文章月度访问量
function showMonthlyViews($format = '本月访问: %d 次') {
    if (RecentViewsCounter_Helper::isPluginActive()) {
        RecentViewsCounter_Helper::showCurrentMonthlyViews($format);
    }
}

// 显示热门文章
function showPopularPosts($limit = 5, $title = '本月热门文章') {
    if (RecentViewsCounter_Helper::isPluginActive()) {
        RecentViewsCounter_Helper::showPopularPosts($limit, $title);
    }
}

// 显示网站统计
function showSiteStats($template = null) {
    if (RecentViewsCounter_Helper::isPluginActive()) {
        RecentViewsCounter_Helper::showSiteStats($template);
    }
}

// 获取文章月度访问量
function getMonthlyViews($cid = null) {
    if (RecentViewsCounter_Helper::isPluginActive()) {
        if ($cid) {
            return RecentViewsCounter_Helper::getPostMonthlyViews($cid);
        } else {
            return RecentViewsCounter_Helper::getCurrentMonthlyViews();
        }
    }
    return 0;
}

// 获取文章累计浏览量（cid 为空时取当前文章）
function getTotalViews($cid = null) {
    if (RecentViewsCounter_Helper::isPluginActive()) {
        return $cid
            ? RecentViewsCounter_Helper::getPostTotalViews($cid)
            : RecentViewsCounter_Helper::getCurrentTotalViews();
    }
    return 0;
}