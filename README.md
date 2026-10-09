# RecentViewsCounter 近期访问统计插件

## 功能特性

- 📊 记录每篇文章的详细访问信息
- 🌍 自动获取访客IP归属地信息
- 📈 显示最近一个月访问量排行榜
- 📅 提供每日访问趋势图表
- 📆 月度访问统计和历史数据保存
- 👥 统计独立访客数量
- 🔍 详细的访问记录查看
- 📋 文章访问记录详情查看功能
- 🌐 IP访问统计和地域分布分析
- 🔎 IP访问详情查看，支持查看每个IP的完整访问历史
- 🤖 智能识别搜索引擎爬虫访问
- 🛡️ 访问频率限制和防刷量保护
- 📊 访问来源统计和分析（搜索引擎、社交媒体、外部链接等）
- 💾 本地IP归属地数据库，减少外部API依赖
- ⚡ IP 归属地由服务端后台进程/定时任务回填，不阻塞页面加载
- 🔄 多IP查询接口支持（IP138需token、ip-api.com免费）
- ⚙️ 灵活的配置选项
- 🗂️ 完善的数据管理和清理功能

## 数据记录

插件维护两张存储，各负责一段口径，互补使用：

| 口径 | 存储 | 保留 | 用途 |
| --- | --- | --- | --- |
| 时间窗访问明细 | `typecho_views_records` | 按 `clean_days` 滚动清理（默认 30 天） | 周榜/月榜、报表、IP 与来源分析 |
| 累计浏览量 | `typecho_contents.views` | 永久保留，单调递增 | 文章页/列表页「阅读量」、总榜 |

> `views` 列原本由 ViewsCounter 插件维护，现已并入本插件（写入在 `recordVisit()` 里原子自增，
> 插件升级时会自动确保该列存在，不再需要安装 ViewsCounter；独立 ViewsCounter 插件已移除，见下文迁移说明）。

插件会为每次访问记录以下信息：
- 文章ID (cid)
- 访问时间戳 (visit_time)
- 访客IP地址 (ip)
- IP归属地 (location)
- 用户代理字符串 (user_agent)

## 主要功能
启用插件后，在"近期统计"页面可以查看以下数据：
![近期统计](./screenshot/30.jpg)

### 1. 访问统计
- 自动记录文章访问信息
- 防止同一IP短时间内重复统计
- 排除管理员访问

### 2. 数据展示
- 近期总访问量
- 近期独立访客数
- 今日访问量
- 搜索引擎爬虫访问统计
- 访问趋势图
- **月度访问统计**: 显示最近24个月的访问趋势折线图，包含总访问量和独立访客数

### 3. 热门文章
- 显示最近一个月访问量前N的文章
- 提供文章标题、访问次数、链接
- 支持直接跳转到文章编辑页面
- **文章访问记录**: 每篇文章都可查看详细的访问记录，包括访问时间、IP地址、User Agent、归属地等信息

### 4. 详细统计
- 单篇文章的详细访问记录
- 按日期和IP的访问统计
- 访客地理位置分布
- 访问最多的IP地址排行榜（前20名）
- IP访问次数和占比统计
- **IP访问详情查看**: 点击IP地址可查看该IP的详细访问记录，包括访问的文章、时间、User Agent等信息

### 5. IP统计和爬虫识别
- **地域分布统计**: 自动统计访客来源地区，显示前10个访问量最高的地区
- **IP访问排行**: 展示访问次数最多的前20个IP地址，包含归属地信息
- **访问占比分析**: 计算每个IP的访问占比，便于识别异常访问
- **IP访问详情**: 为每个IP提供"查看详情"功能，可查看该IP访问的所有文章记录
- **详细访问信息**: 包含访问时间、文章标题、User Agent、归属地等完整信息
- **智能爬虫识别**: 自动识别主流搜索引擎爬虫（Google、百度、Bing等）
- **爬虫访问统计**: 单独统计爬虫访问次数，与正常用户访问区分
- **支持爬虫类型**: Googlebot、Baiduspider、Bingbot、Slurp、DuckDuckBot、YandexBot等30+种爬虫

## 配置选项

- **IP归属地查询**: 启用/禁用IP地理位置查询
- **IP138 API Token**: IP138接口的访问令牌。留空则不使用IP138，改用免费的ip-api.com接口查询
- **本地IP数据库**: 启用/禁用本地IP归属地数据库，优先从本地查询以提高性能
- **IP归属地缓存天数**: 本地IP归属地数据的缓存天数
- **自动清理IP数据**: 启用/禁用自动清理过期的IP归属地数据
- **异步IP查询**: 首次访问未知IP时，插件由服务端后台进程/定时任务回填归属地，不阻塞页面加载
- **访问频率限制**: 启用/禁用高频访问拦截功能，防止恶意刷量
- **限流窗口时间**: 统计访问频率的时间窗口（秒）
- **限流阈值**: 在窗口时间内允许的最大访问次数
- **IP 白名单**: 白名单内的 IP 不受限流(429)、文章页频率限制与自动封禁，也不受黑名单拦截
- **热门文章数量**: 设置显示的热门文章数量
- **重复访问间隔**: 防止刷量的时间间隔设置
- **自动清理过期记录**: 启用/禁用自动清理功能
- **记录保留天数**: 设置访问记录的保留天数
- **启用缓存**: 启用/禁用热门文章列表缓存功能
- **缓存更新间隔**: 设置缓存文件的更新间隔时间（秒）
- **缓存文件位置**: 设置缓存文件的存放路径
- **访问来源统计**: 启用/禁用访问来源统计功能

## IP 白名单

白名单用于放行可信 IP（自己、公司/家庭出口 IP、内网、CDN 回源等），被放行的 IP 不会触发任何拦截：

- 跳过高频访问限流（429）
- 跳过文章页 `/archives/*.html` 频率限制
- 跳过黑名单拦截
- 不参与自动封禁

> 放行只是跳过拦截，访问仍会正常记录与统计。

### 配置位置

后台 **插件 → RecentViewsCounter → 设置 → IP 白名单** 文本框。白名单是插件配置项 `whitelist_ips`，逗号分隔，保存即生效。

### 支持格式

| 格式 | 示例 | 说明 |
| --- | --- | --- |
| 精确 IP | `47.100.19.78` | IPv4/IPv6 均可 |
| IPv4 CIDR | `192.168.0.0/16` | 网段匹配 |
| IPv6 CIDR | `240e:b8f:96a::/48` | 按前缀位数匹配 |
| IPv6 前缀 | `240e:b8f:96a:` | 前缀写法，等价于按字符前缀匹配 |

分隔符支持逗号、分号与空白。`127.0.0.1` 与 `::1` 恒放行，无需配置。

### 默认值

```
127.0.0.1,::1,192.168.0.0/16,10.0.0.0/8,58.35.118.0/24,240e:b8f:96a:,47.100.19.78
```

即本机回环、内网网段，加上站点自身的出口 IP（请按实际环境调整，勿直接照搬）。

> **注意**：白名单在插件启用初始化时写入配置表。若是旧版升级后才新增该配置，需在设置页**保存一次**配置，否则 `whitelist_ips` 不会入库，此时仅回环地址被放行。

## 截图展示

### 应用设置界面
![应用设置10](./screenshot/10.jpg)
![应用设置11](./screenshot/11.jpg)
![应用设置12](./screenshot/12.jpg)
![应用设置13](./screenshot/13.jpg)

### 访问统计概览
- 显示近期总访问量、独立访客数、搜索引擎爬虫访问统计 
- 实时数据更新
![访问统计概览21](./screenshot/21.jpg)

### 每日访问趋势图
- 最近30天的访问趋势折线图
- 周末和工作日颜色区分显示
- 数据点和时间轴清晰标注
![每日访问趋势图22](./screenshot/22.jpg)

### 月度访问统计
- 最近24个月的访问趋势
- 总访问量和独立访客数对比
- 历史数据完整保存
![月度访问统计](./screenshot/23.jpg)

### 热门文章排行
- 近期访问量排行榜
- 文章标题、访问次数、直接链接
- 支持查看详细访问记录
![近期访问量排行榜](./screenshot/27.jpg)

### IP统计和地域分布
- 访问最多的IP地址排行
- 地域分布统计分析
- 访问来源分析
- IP访问详情查看功能
![地域分布统计分析](./screenshot/24.jpg)
![访问来源](./screenshot/25.jpg)

### 爬虫识别统计
- 智能识别30+种搜索引擎爬虫
- 爬虫访问次数统计
- 与正常用户访问区分显示
![访问最多的IP地址排行](./screenshot/26.jpg)

### 近期访问最多列表
参见 <https://h89.cn/> 右侧"近期热门"

## 使用方法

1. 将当前项目所有文件放文件夹RecentViewsCounter，上传到typecho的 `usr/plugins/` 目录
2. 在后台插件管理中激活插件RecentViewsCounter
3. 在管理菜单中点击"近期统计"查看数据
4. 根据需要调整插件配置
5. 查看IP统计、地域分布、爬虫访问等详细数据
6. **查看文章访问记录**: 在"近期热门文章"列表中点击"访问记录"查看单篇文章的详细访问信息
7. **查看IP访问详情**: 在"访问最多的IP地址"列表中点击"查看详情"查看该IP的完整访问历史
8. 使用数据管理功能清理过期记录和缓存

## 主题集成

### 显示文章累计浏览量（替代 ViewsCounter）

`getViews()` / `getViewsById()` / `getMostViewed()` 与原 ViewsCounter 同名同语义，
主题里把调用从 `ViewsCounter_Plugin::` 换成 `RecentViewsCounter_Plugin::` 即可：

```php
<?php // 文章页 / 列表页：当前文章的累计浏览量
_e('阅读量: '); echo RecentViewsCounter_Plugin::getViews(); ?>

<?php // 列表页循环里：指定文章的累计浏览量
echo RecentViewsCounter_Plugin::getViewsById($post->cid); ?>

<?php // 累计浏览量总榜
foreach (RecentViewsCounter_Plugin::getMostViewedAllTime(10) as $post) {
    echo '<a href="' . Typecho_Common::url(Typecho_Router::url('post', $post), $this->options->index) . '">'
        . htmlspecialchars($post['title']) . ' (' . intval($post['views']) . ')</a>';
} ?>
```

列表页如果逐条调用，建议先批量预热一次，避免 N 次查询：

```php
<?php
$cids = array_map(function ($post) { return $post['cid']; }, $posts);
RecentViewsCounter_Plugin::preloadViews($cids);
// 之后重复调用 getViewsById() 都走内存缓存
?>
```

也可以通过 `RecentViewsCounter_Helper` 调用：

```php
<?php
RecentViewsCounter_Helper::showCurrentTotalViews('阅读量: %d');   // 当前文章
RecentViewsCounter_Helper::showPostTotalViews($post->cid);         // 指定文章
$top = RecentViewsCounter_Helper::getMostViewedAllTime(10);        // 总榜
?>
```

### 从 ViewsCounter 迁移

独立的 `ViewsCounter` 插件（`usr/plugins/ViewsCounter/`）已删除，`ViewsCounter_Plugin` 类不再存在；
累计浏览量能力已并入本插件，同名方法把类名换成 `RecentViewsCounter_Plugin::` 即可
（`getViews()` / `getViewsById()` / `getMostViewed()`）。主题已改用 `$this->views` 与
`lib/rankings.php`，`contents.views` 列由本插件维护，删旧插件不丢数据。旧站若仍启用
ViewsCounter，先停用再删目录。

### 在侧边栏显示近期热门文章

如果您想在主题的侧边栏中显示近期热门文章，可以在主题的 `sidebar.php` 文件中添加以下代码：

```php
<?php
// 近期热门功能 -https://github.com/chenjim/RecentViewsCounter
if (class_exists('RecentViewsCounter_Plugin')) {
    echo RecentViewsCounter_Plugin::renderRecentHotArticles(8, $this, '近期热门', '暂无近期热门文章');
}
?>
```

效果参见 <https://h89.cn/>

### 在文章页显示"查看记录"按钮

插件已通过 `Widget_Archive::footer` 钩子自动注入前端 JS（`assets/visit-records.js`），主题无需额外引入。只需在模板中添加链接即可：

```php
<a href="javascript:void(0)"
   onclick="showVisitRecords(<?php echo $this->cid; ?>, '<?php echo htmlspecialchars(addslashes($this->title)); ?>')"
   style="color: #467b96; text-decoration: none;"
   title="查看该文章的访问记录">查看记录</a>
```

通常在 `post.php`、`archive.php`、`index.php` 等文章列表模板中添加。

### 代码说明

- `class_exists('RecentViewsCounter_Plugin')`: 检查插件是否已激活
- `getTopArticlesThisMonth(8)`: 获取近期访问量前8的文章，可根据需要调整数量
- `Typecho_Db::get()`: 获取数据库连接，用于查询文章完整信息
- `Typecho_Router::url()`: 生成正确的文章链接
- `htmlspecialchars()`: 防止XSS攻击，安全显示文章标题

### 自定义样式

您可以通过CSS自定义热门文章列表的样式：

```css
.widget-list li {
    margin: 5px 0;
    line-height: 1.6;
}

.widget-list a {
    text-decoration: none;
    color: #333;
}

.widget-list a:hover {
    color: #0066cc;
}
```

## API接口

### 获取文章月度访问量
```php
RecentViewsCounter_Plugin::getMonthlyViews($cid);
```

### 获取热门文章列表
```php
RecentViewsCounter_Plugin::getTopArticlesThisMonth($limit);
```

### 获取带完整信息的热门文章列表
```php
RecentViewsCounter_Plugin::getTopArticlesWithFullInfo($limit, $widget);
```

### 渲染近期热门文章HTML（一键调用）
```php
RecentViewsCounter_Plugin::renderRecentHotArticles($limit, $widget, $title, $emptyText);
```

### 获取访问来源统计
```php
RecentViewsCounter_Plugin::getRefererStats($days);
```

### 获取IP归属地信息
```php
// IP 定位类库（lib/IpLookup.php）
RecentViewsCounter_IpLookup::getIpLocation($ip);
```

### 异步更新IP归属地信息
```php
RecentViewsCounter_IpLookup::updateIpLocationAsync($ip);
```

### 使用IP138接口查询IP归属地
```php
RecentViewsCounter_IpLookup::getIpLocationFromIp138($ip);
```

### 获取数据库统计信息
```php
RecentViewsCounter_Plugin::getDatabaseStats();
```

### 获取月度统计数据
```php
RecentViewsCounter_Plugin::getMonthlyStats($months);
```

### 更新月度统计
```php
RecentViewsCounter_Plugin::updateMonthlyStats();
```

### 手动清理过期记录
```php
RecentViewsCounter_Plugin::manualCleanOldRecords($days);
```

## 数据库表结构

插件会自动创建以下数据表：
- 访问记录表 `typecho_views_records`
- 月度统计表 `typecho_monthly_stats`
- IP归属地本地数据库表 `typecho_ip_location`

## 数据清理功能

### 自动清理
- 插件会自动清理超过指定天数的访问记录
- 清理操作随机触发（约1%概率），避免影响性能
- 可在插件配置中设置保留天数和开启/关闭自动清理

### 手动清理
- 在管理界面的"数据库统计"部分可手动清理过期记录
- 支持自定义清理天数
- 提供数据库统计信息，包括总记录数、最近30天记录数等

## 缓存功能

### 缓存机制
- 插件支持XML文件缓存，大幅提升热门文章列表的查询性能
- 缓存文件按设定的时间间隔自动更新
- 首次访问或缓存过期时会自动从数据库查询并生成新缓存

### 缓存管理
- 在管理界面的"缓存信息"部分可查看缓存状态
- 支持手动清理缓存文件，强制重新生成
- 缓存文件存储为XML格式，便于调试和维护

### 性能优化
- 启用缓存后，热门文章列表查询速度提升显著
- 建议设置合理的缓存间隔（如1小时），平衡性能和数据实时性
- 缓存文件较小，对服务器存储空间影响微乎其微

## IP 归属地回填

IP 归属地由 `cron-update-ip-locations.php` 回填，绕过广告拦截器屏蔽，无需浏览器端追踪像素。

### 原理

- `cron-update-ip-locations.php` — 每次批量处理 30 个未查询 IP（2s 间隔避免 API 限频），在真实访问时自动触发
- 查询成功 → 写入 `typecho_ip_location` 缓存表 + 回填 `typecho_views_records.location`
- 查询失败 → 标记 `Unknown`，不再重复轮询

## 注意事项

- **异步IP查询**: 未知IP由 `cron-update-ip-locations.php` 自动回填归属地，首次访问可能显示为空，后续访问显示正确归属地信息
- 插件会自动清理过期记录，无需手动维护
- 插件会自动排除管理员的访问记录
- 清理操作不可恢复，请谨慎操作
- 缓存文件存放目录需要有写入权限
- 缓存功能可根据需要启用或禁用
- 插件兼容MySQL严格模式（sql_mode=only_full_group_by）
- 数据库查询已优化，支持高并发访问场景
- **访问频率限制功能可能影响正常用户访问，请合理设置阈值**
- **IP 白名单**：可信 IP 建议加入白名单，避免误伤；白名单为插件配置项而非后台列表，只能在插件设置页维护
- 本地IP数据库会占用一定存储空间，可根据需要启用自动清理
- 访问来源统计依赖HTTP Referer头，某些情况下可能不准确
- **IP查询接口**: 插件优先使用IP138接口，备用ip-api.com接口，确保查询成功率
- **性能优化**: IP 归属地查询在后台进程/定时任务中完成，页面响应不受影响

## 版本信息

- 版本: 1.1.0
- 兼容: Typecho 1.0+
- 作者: chenjim
- 插件地址: https://github.com/chenjim/RecentViewsCounter

## 更新日志

### v1.1.1 (2026-09-18)
- 🔐 新增 IP 白名单：白名单内 IP 跳过限流(429)、文章页频率限制、自动封禁与黑名单拦截
- 🌐 白名单支持精确 IP、IPv4 CIDR、IPv6 CIDR/前缀，`127.0.0.1`/`::1` 恒放行
- 📝 补充白名单配置说明（入口为插件设置页，无独立后台 UI）
- 🕒 后台与文章页访问时间统一按站点时区显示，修正原先按服务器 UTC 显示导致的 8 小时偏差
- 📅 访问趋势图按站点本地日期聚合，修正跨时区导致的日期/周末判定偏移

### v1.1.0 (2026-05-29)
- 🛡️ AJAX 端点 Referer 校验覆盖所有 POST 操作，防止外部直接调用获取数据
- 📦 前端访问记录 JS 独立为 assets/visit-records.js，panel.js 复用统一定义
- 🧹 从主题 footer.php 移除 ~160 行内联 JS，换主题无需同步迁移
- 🛡️ IP黑名单功能完善：SQLite唯一索引、filter_var校验、403/429状态码修复
- 🎨 黑名单管理合并到统计面板（标签切换），删除独立 BlacklistPanel.php
- 🔍 IP详情弹窗新增「加入黑名单」按钮
- 📦 JS独立提取到 assets/panel.js（Panel.php 代码量减少 43%）
- ♻️ IP定位方法提取到 lib/IpLookup.php（Plugin.php 代码量减少 25%）
- ⚡ 改由服务端后台进程/定时任务回填 IP 归属地，移除阻塞式 shutdown 调用
- 🔧 ip-api.com 改用 HTTP（免费版不支持 HTTPS）
- 📝 插件配置说明和 README 同步更新

> **升级提示**：升级到 v1.1.0 后，请在后台 **停用并重新启用** 插件，否则新增的后台 IP 归属地回填和统计面板路由不会生效。

### v1.0.0 (2025-08-25)
- 🎉 初始版本发布
- 📊 完整的访问统计功能
- 🌍 IP归属地自动查询和本地数据库支持
- 📈 热门文章排行榜和近期热门功能
- 📅 访问趋势图表和每日统计
- 📆 月度访问统计功能和历史数据保存
- 👥 独立访客数量统计
- 🔍 详细的访问记录查看功能
- 📋 文章访问记录详情查看
- 🌐 IP访问统计和地域分布分析
- 🔎 IP访问详情查看，支持查看每个IP的完整访问历史
- 🤖 智能识别搜索引擎爬虫访问（支持30+种爬虫）
- 📈 月度访问趋势折线图显示（最近24个月）
- 🗂️ 完善的数据管理和清理功能
- ⚡ 缓存机制支持，提升查询性能
- ⚙️ 灵活的配置选项和主题集成支持
- 🔧 优化数据库查询性能和界面体验
- 🛡️ MySQL严格模式兼容性支持
- 📝 完善的API接口和文档说明
- 🎨 一键调用接口，简化主题集成