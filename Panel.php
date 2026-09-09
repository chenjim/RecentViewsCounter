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

?>
<?php require_once __DIR__ . '/Panel/Actions.php'; ?>
<?php require_once __DIR__ . '/Panel/Data.php'; ?>
<?php include __DIR__ . '/Panel/Views.php'; ?>
<?php include_once __TYPECHO_ROOT_DIR__ . '/admin/footer.php'; ?>
