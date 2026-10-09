<div class="main" id="tab_content_stats">
    <div class="body container">
        <div class="colgroup">
            <div class="col-mb-12">


                <!-- 统一时间范围选择器 -->
                <div class="typecho-page-main" style="margin-bottom: 20px;">
                    <div style="background: #fff; padding: 15px; border: 1px solid #e9e9e6; border-radius: 4px;">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 20px;">
                            <h4 style="margin: 0; color: #495057;">📊 数据时间范围</h4>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                                <label style="font-size: 14px; color: #666;">选择时间范围：</label>
                                <select id="global_time_range" onchange="changeGlobalTimeRange()" style="width: 140px; min-width: 140px; height: 36px; flex: 0 0 140px; padding: 4px 32px 4px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; line-height: 20px; background: #fff; box-sizing: border-box; vertical-align: middle;">
                                    <option value="7">近7天</option>
                                    <option value="30">近30天</option>
                                    <option value="all">全部时间</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 访问统计 -->
                <div class="typecho-page-title">
                    <h2><?php _e('访问统计'); ?></h2>
                </div>
                
                <!-- 统计概览 -->
                <div class="row typecho-page-main" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; max-width: 900px;">
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #467b96;"><?php echo $total_visits ? $total_visits['count'] : 0; ?></h3>
                        <p style="margin: 5px 0 0 0;">非蜘蛛访问次数</p>
                    </div>
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #dc3545;"><?php echo $total_bots_data ? $total_bots_data['count'] : 0; ?></h3>
                        <p style="margin: 5px 0 0 0;">总蜘蛛访问次数</p>
                    </div>
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #467b96;"><?php echo $non_bot_ip_count; ?></h3>
                        <p style="margin: 5px 0 0 0;">非蜘蛛IP数</p>
                    </div>
                    <div style="flex: 1; min-width: 130px; text-align: center; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin: 0; color: #dc3545;"><?php echo $bot_ip_count; ?></h3>
                        <p style="margin: 5px 0 0 0;">蜘蛛IP数</p>
                    </div>
                </div>

                <!-- 访问趋势 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>每日访问趋势 <span style="font-size: 14px; color: #666; font-weight: normal;">(最近30天，不受上方时间选择器影响)</span></h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: auto;">
                        <canvas id="visitChart" style="width: 100%; max-width: 1200px; height: 300px; display: block; margin: 0 auto;"></canvas>
                    </div>
                </div>

                <!-- 月度访问统计 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>月度访问统计 <span style="font-size: 14px; color: #666; font-weight: normal;">(最近24个月，不受上方时间选择器影响)</span></h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: auto;">
                        <canvas id="monthlyChart" style="width: 100%; max-width: 1200px; height: 300px; display: block; margin: 0 auto;"></canvas>
                    </div>
                </div>

                <!-- 地域分布统计 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问者地域分布 TOP 10</h3>
                    <?php if (!empty($location_stats)): ?>
                    <div class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 600px;">
                            <colgroup>
                                <col width="10%"/>
                                <col width="60%"/>
                                <col width="20%"/>
                                <col width="10%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>地区</th>
                                    <th>访问次数</th>
                                    <th>占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_location_visits = array_sum(array_column($location_stats, 'count'));
                                $rank = 1; 
                                foreach ($location_stats as $location): 
                                    $percentage = $total_location_visits > 0 ? round(($location['count'] / $total_location_visits) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($location['location'] ?: '未知地区'); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <?php echo number_format($location['count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #6c757d;">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="typecho-page-main">
                        <div class="message notice">
                            <p>暂无地域访问数据</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 访问来源统计 -->
                <?php 
                $has_referer_data = false;
                if ($plugin_config && $plugin_config->enable_referer == '1' && !empty($referer_stats)) {
                    $total_referer_count = $referer_stats['search']['count'] + $referer_stats['social']['count'] + $referer_stats['external']['count'] + $referer_stats['internal']['count'] + $referer_stats['direct']['count'];
                    $has_referer_data = $total_referer_count > 0;
                }
                if ($has_referer_data): ?>
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问来源统计</h3>
                    
                    <!-- 来源类型概览 -->
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; max-width: 900px;">
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #f3e5f5; border-radius: 4px;">
                            <h4 style="margin: 0; color: #9c27b0;"><?php echo $referer_stats['direct']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">直接访问</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #e8f5e8; border-radius: 4px;">
                            <h4 style="margin: 0; color: #28a745;"><?php echo $referer_stats['search']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">搜索引擎</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #e3f2fd; border-radius: 4px;">
                            <h4 style="margin: 0; color: #2196f3;"><?php echo $referer_stats['social']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">社交媒体</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #fff3e0; border-radius: 4px;">
                            <h4 style="margin: 0; color: #ff9800;"><?php echo $referer_stats['external']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">外部链接</p>
                        </div>
                        <div style="flex: 1; min-width: 120px; text-align: center; padding: 15px; background: #f0f4ff; border-radius: 4px;">
                            <h4 style="margin: 0; color: #6366f1;"><?php echo $referer_stats['internal']['count']; ?></h4>
                            <p style="margin: 5px 0 0 0; font-size: 12px;">站内跳转</p>
                        </div>
                    </div>
                    
                    <!-- 详细来源列表 -->
                    <div class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 700px;">
                            <colgroup>
                                <col width="15%"/>
                                <col width="35%"/>
                                <col width="20%"/>
                                <col width="15%"/>
                                <col width="15%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>类型</th>
                                    <th>来源</th>
                                    <th>访问次数</th>
                                    <th>占比</th>
                                    <th>排名</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_referer_visits = $referer_stats['search']['count'] + $referer_stats['social']['count'] + $referer_stats['external']['count'] + $referer_stats['internal']['count'] + $referer_stats['direct']['count'];
                                $all_sources = array();
                                
                                // 合并所有来源数据
                                foreach ($referer_stats as $type => $data) {
                                    foreach ($data['items'] as $name => $count) {
                                        $all_sources[] = array(
                                            'type' => $type,
                                            'name' => $name,
                                            'count' => $count
                                        );
                                    }
                                }
                                
                                // 按访问量排序
                                usort($all_sources, function($a, $b) {
                                    return $b['count'] - $a['count'];
                                });
                                
                                // 显示前20个来源
                                $rank = 1;
                                foreach (array_slice($all_sources, 0, 20) as $source):
                                    $percentage = $total_referer_visits > 0 ? round(($source['count'] / $total_referer_visits) * 100, 1) : 0;
                                    
                                    // 设置类型标签样式
                                    $type_styles = array(
                                        'search' => 'background: #28a745; color: white;',
                                        'social' => 'background: #2196f3; color: white;',
                                        'external' => 'background: #ff9800; color: white;',
                                        'internal' => 'background: #6366f1; color: white;',
                                        'direct' => 'background: #9c27b0; color: white;'
                                    );
                                    
                                    $type_names = array(
                                        'search' => '搜索',
                                        'social' => '社交',
                                        'external' => '外链',
                                        'internal' => '站内',
                                        'direct' => '直接'
                                    );
                                ?>
                                <tr>
                                    <td>
                                        <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; <?php echo $type_styles[$source['type']]; ?>">
                                            <?php echo $type_names[$source['type']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($source['name']); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <?php echo number_format($source['count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #6c757d;">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #007cba; font-weight: bold;">
                                            #<?php echo $rank++; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($plugin_config && $plugin_config->enable_referer == '1'): ?>
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问来源统计</h3>
                    <div class="message notice">
                        <p>暂无访问来源数据</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- IP访问统计 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>访问最多的IP地址 TOP 20
                        <select id="ip_view_selector" onchange="switchIpView()" style="font-size: 13px; padding: 2px 8px; margin-left: 10px; vertical-align: middle;">
                            <option value="all">全部</option>
                            <option value="nonbot">仅非Bot</option>
                        </select>
                        <small style="color: #666;" 
                                title="<?php echo htmlspecialchars($bot_detection_info); ?>">
                             (智能Bot检测)
                         </small>
                    </h3>
                    <?php if (!empty($display_top_ips) || !empty($non_bot_top_ips)): ?>
                    <!-- 全部IP视图 -->
                    <div id="ip_view_all" class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 1000px;">
                            <colgroup>
                                <col width="5%"/>
                                <col width="16%"/>
                                <col width="16%"/>
                                <col width="10%"/>
                                <col width="8%"/>
                                <col width="9%"/>
                                <col width="14%"/>
                                <col width="12%"/>
                                <col width="10%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>IP地址</th>
                                    <th>归属地</th>
                                    <th>访问次数</th>
                                    <th>占比</th>
                                    <th>Bot评分</th>
                                    <th>检测详情</th>
                                    <th>访问详情</th>
                                    <th>黑名单</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_ip_visits = array_sum(array_column($display_top_ips, 'count'));
                                $rank = 1; 
                                foreach ($display_top_ips as $ip_data): 
                                    $percentage = $total_ip_visits > 0 ? round(($ip_data['count'] / $total_ip_visits) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <?php 
                                        $ip_color = $ip_data['is_bot'] ? '#ff6b35' : '#333';
                                        $bot_level = '';
                                        if ($ip_data['is_bot']) $bot_level = 'Bot';
                                        else $bot_level = '正常用户';
                                        $ip_title = 'title="' . $bot_level . ' (评分: ' . $ip_data['bot_score'] . '分)"';
                                        ?>
                                        <strong style="font-family: monospace; color: <?php echo $ip_color; ?>;" <?php echo $ip_title; ?>>
                                            <?php echo htmlspecialchars($ip_data['ip']); ?>
                                            <?php if ($ip_data['is_bot']): ?>
                                                <span style="font-size: 12px; color: #ff6b35; margin-left: 5px;">🤖</span>
                                            <?php endif; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($ip_data['location'] ?: '未知地区'); ?>
                                    </td>
                                    <td>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <?php echo number_format($ip_data['count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #6c757d;">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $score_color = '#28a745'; // 绿色 - 正常
                                        if ($ip_data['is_bot']) $score_color = '#dc3545'; // 红色 - Bot
                                        else $score_color = '#28a745'; // 绿色 - 正常
                                        ?>
                                        <span style="color: <?php echo $score_color; ?>; font-weight: bold;" 
                                              title="<?php echo htmlspecialchars($bot_detection_info); ?>">
                                            <?php echo $ip_data['bot_score']; ?>分
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $detection_details = array();
                                        if ($ip_data['is_bot_ua'] && !empty($ip_data['detected_bot_name'])) {
                                            $detection_details[] = $ip_data['detected_bot_name'];
                                        } elseif ($ip_data['is_bot_ua']) {
                                            $detection_details[] = 'UA识别';
                                        }
                                        if ($ip_data['is_high_frequency']) $detection_details[] = '高频访问';
                                        if ($ip_data['is_focused_crawler']) $detection_details[] = '专注爬取';
                                        if ($ip_data['ua_variety'] == 1 && $ip_data['count'] > 10) $detection_details[] = '单一UA';
                                        
                                        $visit_duration = $ip_data['last_visit'] - $ip_data['first_visit'];
                                        $visit_rate = $visit_duration > 0 ? $ip_data['count'] / ($visit_duration / 3600) : 0;
                                        if ($visit_rate > 10) $detection_details[] = '高频率';
                                        
                                        $detail_text = implode(', ', $detection_details);
                                        ?>
                                        <?php if (empty($detection_details)): ?>
                                            <span style="color: #28a745;">正常</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545; font-size: 11px;"><?php echo htmlspecialchars($detail_text); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0);" onclick="showIpVisitDetails('<?php echo htmlspecialchars($ip_data['ip']); ?>')" 
                                           style="color: #0073aa; text-decoration: none; font-size: 12px;">
                                            📋 查看详情
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($ip_data['is_blacklisted']): ?>
                                            <span style="color: #dc3545; font-size: 12px;">☑ 已拉黑</span>
                                        <?php else: ?>
                                            <a href="javascript:void(0);" onclick="addToBlacklist('<?php echo htmlspecialchars($ip_data['ip']); ?>', this)"
                                               data-detail="<?php echo htmlspecialchars($detail_text); ?>"
                                               style="color: #999; text-decoration: none; font-size: 12px;"
                                               title="加入黑名单">❌ 拉黑</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- 仅非Bot视图 -->
                    <div id="ip_view_nonbot" class="typecho-table-wrap" style="overflow-x: auto; display: none;">
                        <?php if (!empty($non_bot_top_ips)): ?>
                        <table class="typecho-list-table" style="min-width: 1000px;">
                            <colgroup>
                                <col width="5%"/>
                                <col width="16%"/>
                                <col width="16%"/>
                                <col width="10%"/>
                                <col width="8%"/>
                                <col width="9%"/>
                                <col width="14%"/>
                                <col width="12%"/>
                                <col width="10%"/>
                            </colgroup>
                            <thead><tr>
                                <th>排名</th><th>IP地址</th><th>归属地</th>
                                <th>访问次数</th><th>占比</th>
                                <th>Bot评分</th><th>检测详情</th><th>访问详情</th><th>黑名单</th>
                            </tr></thead>
                            <tbody>
                                <?php $rank = 1; $total_nb = array_sum(array_column($non_bot_top_ips, 'count')); foreach ($non_bot_top_ips as $ip_data): ?>
                                <?php 
                                $nb_detection_details = array();
                                if ($ip_data['is_bot_ua'] && !empty($ip_data['detected_bot_name'])) {
                                    $nb_detection_details[] = $ip_data['detected_bot_name'];
                                } elseif ($ip_data['is_bot_ua']) {
                                    $nb_detection_details[] = 'UA识别';
                                }
                                if ($ip_data['is_high_frequency']) $nb_detection_details[] = '高频访问';
                                if ($ip_data['is_focused_crawler']) $nb_detection_details[] = '专注爬取';
                                if ($ip_data['ua_variety'] == 1 && $ip_data['count'] > 10) $nb_detection_details[] = '单一UA';
                                $nb_visit_duration = $ip_data['last_visit'] - $ip_data['first_visit'];
                                $nb_visit_rate = $nb_visit_duration > 0 ? $ip_data['count'] / ($nb_visit_duration / 3600) : 0;
                                if ($nb_visit_rate > 10) $nb_detection_details[] = '高频率';
                                $nb_detail_text = implode(', ', $nb_detection_details);
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><strong style="font-family: monospace; color: #467b96;"><?php echo htmlspecialchars($ip_data['ip']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ip_data['location'] ?: '未知地区'); ?></td>
                                    <td><span style="color: #28a745; font-weight: bold;"><?php echo number_format($ip_data['count']); ?></span></td>
                                    <td><span style="color: #6c757d;"><?php echo $total_nb > 0 ? round(($ip_data['count'] / $total_nb) * 100, 1) : 0; ?>%</span></td>
                                    <td><span style="color: #28a745; font-weight: bold;"><?php echo $ip_data['bot_score']; ?>分</span></td>
                                    <td>
                                        <?php if (empty($nb_detection_details)): ?>
                                            <span style="color: #28a745;">正常</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545; font-size: 11px;"><?php echo htmlspecialchars($nb_detail_text); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="javascript:void(0);" onclick="showIpVisitDetails('<?php echo htmlspecialchars($ip_data['ip']); ?>')" style="color: #0073aa; font-size: 12px;">📋 查看详情</a></td>
                                    <td>
                                        <?php if ($ip_data['is_blacklisted']): ?>
                                            <span style="color: #dc3545; font-size: 12px;">☑ 已拉黑</span>
                                        <?php else: ?>
                                            <a href="javascript:void(0);" onclick="addToBlacklist('<?php echo htmlspecialchars($ip_data['ip']); ?>', this)"
                                               data-detail="<?php echo htmlspecialchars($nb_detail_text); ?>"
                                               style="color: #999; text-decoration: none; font-size: 12px;"
                                               title="加入黑名单">❌ 拉黑</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="typecho-page-main"><div class="message notice"><p>当前时间范围内无非Bot IP数据</p></div></div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="typecho-page-main">
                        <div class="message notice">
                            <p>暂无IP访问数据</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 黑名单管理入口 -->
                    <div style="background: #fff8f0; padding: 12px 15px; border: 1px solid #ffe0b2; border-radius: 4px; margin-top: 15px;">
                        <span style="color: #e65100; font-size: 13px;">🛡️ <strong>IP黑名单：</strong>可手动将恶意IP加入黑名单，加入后该IP将无法访问网站。
                        <a href="#" onclick="showBlacklistModal(); return false;"
                           style="color: #0073aa; text-decoration: none; margin-left: 8px;">管理黑名单 →</a></span>
                    </div>

                    <!-- Bot检测说明 -->
                    <div id="botDetectionInfo" style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin-top: 15px; display: none;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">🤖 智能Bot检测说明</h4>
                        <div style="font-size: 13px; color: #6c757d; line-height: 1.5;">
                            <p style="margin: 5px 0;"><strong>检测机制：</strong>基于多维度分析，包括User-Agent识别、访问频率、行为模式等</p>
                            <p style="margin: 5px 0;"><strong>评分规则：</strong></p>
                            <ul style="margin: 5px 0; padding-left: 20px;">
                                <li><strong>UA识别</strong> (40分): 检测到已知爬虫User-Agent</li>
                                <li><strong>高频访问</strong> (20分): 访问次数超过50次</li>
                                <li><strong>专注爬取</strong> (15分): 访问内容集中度高 (访问文章种类/总访问 < 0.3)</li>
                                <li><strong>单一UA</strong> (25分): 只使用一种User-Agent且访问超过5次</li>
                                <li><strong>高频率</strong> (10分): 每小时访问超过5次</li>
                            </ul>
                            <p style="margin: 5px 0;"><strong>判定规则：</strong>
                                <span style="color: #467b96;">UA匹配 → Bot</span> 或
                                <span style="color: #dc3545;">行为评分 ≥<?php echo RecentViewsCounter_Plugin::BOT_THRESHOLD; ?>分 → Bot</span>
                            </p>
                            <p style="margin: 5px 0; font-size: 12px; color: #999;">
                                (IP TOP 20表显示纯行为评分，不含UA识别分)
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 热门文章列表 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>近期热门文章 TOP <?php echo $top_count; ?></h3>
                    <?php if (!empty($top_articles)): ?>
                    <div class="typecho-table-wrap" style="overflow-x: auto;">
                        <table class="typecho-list-table" style="min-width: 700px;">
                            <colgroup>
                                <col width="5%"/>
                                <col width="50%"/>
                                <col width="12%"/>
                                <col width="18%"/>
                                <col width="15%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>文章标题</th>
                                    <th>访问次数</th>
                                    <th>访问记录</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($top_articles as $article): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <?php 
                        try {
                            $full_article = $db->fetchRow($db->select('*')->from('table.contents')->where('cid = ?', $article['cid']));
                        } catch (Exception $e) {
                            $full_article = null;
                        }
                                        if ($full_article) {
                                            $permalink = Typecho_Router::url('post', $full_article, $options->index);
                                        } else {
                                            $permalink = '#';
                                        }
                                        ?>
                                        <a href="<?php echo $permalink; ?>" 
                                           target="_blank" 
                                           title="点击查看文章" 
                                           style="color: #467b96; text-decoration: none; font-weight: bold;">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                        <span style="color: #999; font-size: 12px; margin-left: 8px;">🔗</span>
                                    </td>
                                    <td>
                                        <span style="color: #467b96; font-weight: bold;">
                                            <?php echo $article['visit_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0)" 
                                           onclick="showVisitRecords(<?php echo $article['cid']; ?>, '<?php echo htmlspecialchars(addslashes($article['title'])); ?>')" 
                                           style="color: #467b96; text-decoration: none;" 
                                           title="查看该文章的访问记录">
                                            📊 查看记录
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo Typecho_Common::url('write-post.php?cid=' . $article['cid'], $options->adminUrl); ?>">
                                            编辑
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="typecho-page-main">
                        <div class="message notice">
                            <p>本月暂无访问记录</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 数据库统计信息 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>数据库统计</h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: hidden; overflow-wrap: anywhere;">
                        <?php if (isset($clean_message)): ?>
                        <div style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; border-radius: 4px;">
                            <?php echo htmlspecialchars($clean_message); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($cache_message)): ?>
                        <div style="background: #d1ecf1; color: #0c5460; padding: 10px; margin-bottom: 15px; border: 1px solid #bee5eb; border-radius: 4px;">
                            <?php echo htmlspecialchars($cache_message); ?>
                        </div>
                        <?php endif; ?> 
                        
                        <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                            <p><strong>数据库信息：</strong>总访问记录 <?php echo number_format($db_stats['total_records']); ?> 条 ｜ 最近30天记录 <?php echo number_format($db_stats['recent_records']); ?> 条<?php if ($db_stats['earliest_time']): ?> ｜ 最早记录时间 <?php echo (new \Typecho\Date(intval($db_stats['earliest_time'])))->format('Y-m-d H:i:s'); ?><?php endif; ?></p>
                            
                            <form method="post" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 10px; width: 100%; max-width: 100%;">
                                <input type="hidden" name="action" value="clean_records">
                                <label for="clean_days">清理</label>
                                <input type="number" name="clean_days" id="clean_days" value="30" min="1" max="365" style="width: 60px;">
                                <label for="clean_days">天前的记录</label>
                                <button type="submit" class="btn btn-s" onclick="return confirm('确定要清理过期的访问记录吗？此操作不可恢复！')" style="white-space: normal; max-width: 100%;">立即清理</button>
                            </form>
                            <p style="margin-top: 10px; color: #666; font-size: 12px;">建议定期清理过期记录以节省存储空间</p>
                        </div>
                        <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                            <?php 
                            $cache_file = __TYPECHO_ROOT_DIR__ . $plugin_config->cache_file;
                            $cache_enabled = $plugin_config && $plugin_config->enable_cache == '1';
                            ?>
                            <p><strong>缓存信息：</strong>缓存状态 <?php if ($cache_enabled): ?><span style="color: #28a745;">已启用</span><?php else: ?><span style="color: #dc3545;">已禁用</span><?php endif; ?><?php if ($cache_enabled): ?> ｜ 缓存文件 <?php if (file_exists($cache_file)): ?><span style="color: #28a745;">存在</span> (<?php echo (new \Typecho\Date(filemtime($cache_file)))->format('Y-m-d H:i:s'); ?>)<?php else: ?><span style="color: #ffc107;">不存在</span><?php endif; ?> ｜ 缓存间隔 <?php echo intval($plugin_config->cache_time); ?> 秒<?php endif; ?></p>
                            
                            <?php if ($cache_enabled): ?>
                            <form method="post" style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 10px; width: 100%; max-width: 100%;"><input type="hidden" name="action" value="clear_cache"><button type="submit" class="btn btn-s" onclick="return confirm('确定要清理缓存文件吗？下次访问时将重新生成缓存。')" style="white-space: normal; max-width: 100%;">清理缓存</button></form>
                            <p style="margin-top: 10px; color: #666; font-size: 12px;">清理缓存后下次访问时会自动重新生成</p>
                            <?php else: ?><p style="color: #666;">缓存功能已禁用，可在插件配置中启用</p><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 文章页面频率限制：访问日志 / 自动封禁（默认隐藏，点击按钮弹窗） -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>文章页面访问频率日志 <span style="font-size: 13px; color:#666; font-weight:normal;">（最近50条 / 24h阈值50次）</span></h3>
                    <p style="color:#666;font-size:12px;margin:8px 0;">仅记录 <code>/archives/*.html</code> 页面，默认不展开，点击按钮弹窗查看。</p>
                    <button type="button" onclick="showRateLogModal();return false;" class="btn btn-s" style="background:#467b96;color:#fff;border:none;padding:6px 14px;border-radius:4px;cursor:pointer;">&#x1F4CB; 查看访问日志（<?php echo count($rateLogs); ?>/50）</button>
                    <button type="button" onclick="showArchiveBanModal();return false;" class="btn btn-s" style="background:#fff;color:#467b96;border:1px solid #467b96;padding:6px 14px;border-radius:4px;cursor:pointer;margin-left:8px;">&#x1F6AB; 查看自动封禁（<?php echo count($archiveBans); ?>/50）</button>
                </div>
                <!-- 访问日志弹窗 -->
                <div id="rateLogModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:8px;width:90%;max-width:900px;max-height:85%;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.3);display:flex;flex-direction:column;">
                        <div style="padding:16px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;flex-shrink:0;">
                            <h3 style="margin:0;color:#333;font-size:16px;">文章页面访问频率日志 <span style="font-size:12px;color:#666;font-weight:normal;">最近50条 / 24h阈值50次</span></h3>
                            <button type="button" onclick="closeRateLogModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#666;padding:0 4px;">×</button>
                        </div>
                        <div style="padding:16px 20px;overflow-y:auto;flex:1;">
                            <?php if (!empty($rateLogs)): ?>
                            <div class="typecho-table-wrap" style="overflow-x:auto;">
                                <table class="typecho-list-table" style="min-width:600px;">
                                    <colgroup><col width="25%"/><col width="50%"/><col width="25%"/></colgroup>
                                    <thead><tr><th>IP</th><th>URL</th><th>访问时间</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($rateLogs as $rl): ?>
                                        <tr>
                                            <td><span style="font-family:monospace;"><?php echo htmlspecialchars($rl['ip']); ?></span></td>
                                            <td style="word-break:break-all;"><?php echo htmlspecialchars($rl['url']); ?></td>
                                            <td><?php echo (new \Typecho\Date(intval($rl['access_time'])))->format('Y-m-d H:i:s'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?><div class="message notice"><p>暂无文章页面访问日志（仅记录 /archives/*.html）</p></div><?php endif; ?>
                        </div>
                        <div style="padding:12px 20px;border-top:1px solid #eee;text-align:center;flex-shrink:0;">
                            <button type="button" onclick="closeRateLogModal()" style="background:#467b96;color:#fff;border:none;padding:7px 18px;border-radius:4px;cursor:pointer;">关闭</button>
                        </div>
                    </div>
                </div>
                <!-- 自动封禁弹窗 -->
                <div id="archiveBanModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:8px;width:90%;max-width:900px;max-height:85%;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.3);display:flex;flex-direction:column;">
                        <div style="padding:16px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;flex-shrink:0;">
                            <h3 style="margin:0;color:#333;font-size:16px;">文章页面自动封禁记录 <span style="font-size:12px;color:#666;font-weight:normal;">reason 含 archives，最近50条</span></h3>
                            <button type="button" onclick="closeArchiveBanModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#666;padding:0 4px;">×</button>
                        </div>
                        <div style="padding:16px 20px;overflow-y:auto;flex:1;">
                            <?php if (!empty($archiveBans)): ?>
                            <div class="typecho-table-wrap" style="overflow-x:auto;">
                                <table class="typecho-list-table" style="min-width:700px;">
                                    <colgroup><col width="25%"/><col width="45%"/><col width="20%"/><col width="10%"/></colgroup>
                                    <thead><tr><th>IP</th><th>封禁原因</th><th>封禁时间</th><th>操作</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($archiveBans as $ab): ?>
                                        <tr>
                                            <td><span style="font-family:monospace;"><?php echo htmlspecialchars($ab['ip']); ?></span></td>
                                            <td><?php echo htmlspecialchars($ab['reason']); ?></td>
                                            <td><?php echo (new \Typecho\Date(intval($ab['created_time'])))->format('Y-m-d H:i:s'); ?></td>
                                            <td><button type="button" onclick="removeFromBlacklist('<?php echo htmlspecialchars($ab['ip']); ?>'); closeArchiveBanModal();" style="background:#28a745;color:#fff;border:none;padding:4px 10px;border-radius:3px;cursor:pointer;font-size:12px;">解除封禁</button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?><div class="message notice"><p>暂无自动封禁记录</p></div><?php endif; ?>
                        </div>
                        <div style="padding:12px 20px;border-top:1px solid #eee;text-align:center;flex-shrink:0;">
                            <button type="button" onclick="closeArchiveBanModal()" style="background:#467b96;color:#fff;border:none;padding:7px 18px;border-radius:4px;cursor:pointer;">关闭</button>
                        </div>
                    </div>
                </div>

                <!-- IP归属地数据管理 -->
                <div class="typecho-page-main" style="margin-bottom: 30px;">
                    <h3>IP归属地数据管理</h3>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; max-width: 100%; box-sizing: border-box; overflow-x: hidden; overflow-wrap: anywhere;">
                        <?php if (isset($ip_clean_message)): ?>
                        <div style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; border-radius: 4px;">
                            <?php echo htmlspecialchars($ip_clean_message); ?>
                        </div>
                        <?php endif; ?>
                        
                            <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                                <?php
                                try {
                                    $ip_stats = $db->fetchRow($db->select('COUNT(*) as total_ips')
                                                                ->from('table.ip_location'));
                                    $recent_ip_stats = $db->fetchRow($db->select('COUNT(*) as recent_ips')
                                                                       ->from('table.ip_location')
                                                                       ->where('created_time > ?', time() - 30 * 24 * 3600));
                                    $oldest_ip = $db->fetchRow($db->select('MIN(created_time) as oldest_time')
                                                                 ->from('table.ip_location'));
                                } catch (Exception $e) {
                                    $ip_stats = array('total_ips' => 0);
                                    $recent_ip_stats = array('recent_ips' => 0);
                                    $oldest_ip = array('oldest_time' => null);
                                }
                                ?>
                                <p><strong>本地IP数据库信息：</strong>总IP记录 <?php echo number_format($ip_stats['total_ips']); ?> 条 ｜ 最近30天新增 <?php echo number_format($recent_ip_stats['recent_ips']); ?> 条<?php if ($oldest_ip['oldest_time']): ?> ｜ 最早记录时间 <?php echo (new \Typecho\Date(intval($oldest_ip['oldest_time'])))->format('Y-m-d H:i:s'); ?><?php endif; ?></p>
                                
                                <div style="margin-top: 15px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px;"><button onclick="loadIpLocationData(1)" class="btn btn-s" style="white-space: normal; max-width: 100%;">查看IP数据</button><form method="post" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; max-width: 100%;"><input type="hidden" name="action" value="clean_expired_ip_data"><label for="ip_clean_days">清理</label><input type="number" name="clean_days" id="ip_clean_days" value="90" min="1" max="365" style="width: 60px;"><label for="ip_clean_days">天前的IP数据</label><button type="submit" class="btn btn-s" onclick="return confirm('确定要清理过期的IP归属地数据吗？此操作不可恢复！')" style="white-space: normal; max-width: 100%;">清理过期数据</button></form></div>
                                <p style="margin-top: 10px; color: #666; font-size: 12px;">建议定期清理过期IP数据以节省存储空间</p>
                            </div>
                            <div style="float: none; width: 100%; flex: 1 1 100%; min-width: 0; max-width: 100%; margin: 0; padding: 0; box-sizing: border-box; overflow-wrap: anywhere;">
                                <p><strong>IP归属地配置：</strong>本地数据库 <?php if ($plugin_config && $plugin_config->enable_local_ip_db == '1'): ?><span style="color: #28a745;">已启用</span><?php else: ?><span style="color: #dc3545;">已禁用</span><?php endif; ?> ｜ 缓存天数 <?php echo $plugin_config ? intval($plugin_config->ip_cache_days) : 30; ?> 天 ｜ 自动清理 <?php if ($plugin_config && $plugin_config->auto_clean_ip == '1'): ?><span style="color: #28a745;">已启用</span><?php else: ?><span style="color: #dc3545;">已禁用</span><?php endif; ?></p>
                                <p style="color: #666; font-size: 12px; margin-top: 15px;">可在插件配置中修改这些设置</p>
                            </div>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>

<!-- 黑名单管理（dialog 弹出） -->
<div id="blacklistModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10001;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:90%;max-width:900px;max-height:85%;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.3);display:flex;flex-direction:column;">
        <div style="padding:16px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;flex-shrink:0;">
            <h3 style="margin:0;color:#333;font-size:16px;">黑名单管理</h3>
            <button type="button" onclick="closeBlacklistModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#666;padding:0 4px;">×</button>
        </div>
        <div style="padding:16px 20px;overflow-y:auto;flex:1;">
                    <!-- 添加 IP 到黑名单 -->
                    <div class="typecho-page-main" style="margin-bottom: 20px;">
                        <div style="background: #fff; padding: 20px; border: 1px solid #e9e9e6; border-radius: 4px;">
                            <h3 style="margin-top: 0;">添加 IP 到黑名单</h3>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                                <input type="text" id="blacklist_ip" placeholder="输入IP地址，如 192.168.1.1"
                                       style="flex: 1; min-width: 200px; height: 36px; padding: 4px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                                <input type="text" id="blacklist_reason" placeholder="拉黑理由（可选）"
                                       style="flex: 1; min-width: 150px; height: 36px; padding: 4px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                                <button type="button" onclick="addToBlacklistFromInput()" class="btn btn-s" style="height: 36px; white-space: nowrap;">加入黑名单</button>
                            </div>
                            <div id="add_result" style="margin-top: 10px; font-size: 13px;"></div>
                        </div>
                    </div>

                    <!-- 黑名单列表 -->
                    <div class="typecho-page-main" style="margin-bottom: 30px;">
                        <h3>黑名单列表 <span id="blacklist_count" style="font-size: 14px; color: #666; font-weight: normal;">(共 <?php echo $blacklist_total; ?> 条)</span></h3>
                        <div id="blacklist_table_wrap" class="typecho-table-wrap" style="overflow-x: auto;">
                            <?php if (!empty($blacklist_result['data'])): ?>
                            <table class="typecho-list-table" style="min-width: 700px;">
                                <colgroup>
                                    <col width="25%"/>
                                    <col width="35%"/>
                                    <col width="25%"/>
                                    <col width="15%"/>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>IP地址</th>
                                        <th>拉黑理由</th>
                                        <th>拉黑时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody id="blacklist_tbody">
                                    <?php foreach ($blacklist_result['data'] as $item): ?>
                                    <tr id="bl_<?php echo htmlspecialchars($item['ip']); ?>">
                                        <td><strong style="font-family: monospace;"><?php echo htmlspecialchars($item['ip']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['reason'] ?: '-'); ?></td>
                                        <td><?php echo (new \Typecho\Date(intval($item['created_time'])))->format('Y-m-d H:i:s'); ?></td>
                                        <td>
                                            <button onclick="removeFromBlacklist('<?php echo htmlspecialchars($item['ip']); ?>')"
                                                    style="background: #dc3545; color: white; border: none; padding: 4px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">解封</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div id="blacklist_pagination" style="margin-top: 15px; text-align: center;">
                                <?php
                                $bl_perPage = 20;
                                $bl_totalPages = ceil($blacklist_total / $bl_perPage);
                                if ($bl_totalPages > 1):
                                    for ($i = 1; $i <= $bl_totalPages; $i++):
                                ?>
                                    <button onclick="loadBlacklistPage(<?php echo $i; ?>)"
                                            class="btn btn-s" style="margin: 0 2px; <?php echo $i === 1 ? 'background: #0073aa; color: white;' : ''; ?>">
                                        <?php echo $i; ?>
                                    </button>
                                <?php
                                    endfor;
                                endif;
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div id="blacklist_empty" style="<?php echo empty($blacklist_result['data']) ? '' : 'display: none;'; ?>">
                            <div class="message notice"><p>黑名单为空</p></div>
                        </div>
                    </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #eee;text-align:center;flex-shrink:0;">
            <button type="button" onclick="closeBlacklistModal()" style="background:#467b96;color:#fff;border:none;padding:7px 18px;border-radius:4px;cursor:pointer;">关闭</button>
        </div>
    </div>
</div>


<script>
var weekData = <?php echo json_encode($week_data); ?>;
var monthlyStatsData = <?php echo json_encode($monthly_stats); ?>;
window.RVC_TZ_OFFSET = <?php echo intval($options->timezone); ?>;
</script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/utils/request.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/utils/request.js'); ?>"></script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/modules/chart.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/modules/chart.js'); ?>"></script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/modules/time-range.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/modules/time-range.js'); ?>"></script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/modules/blacklist.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/modules/blacklist.js'); ?>"></script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/modules/ip-details.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/modules/ip-details.js'); ?>"></script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/modules/ip-location.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/modules/ip-location.js'); ?>"></script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/modules/ui.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/modules/ui.js'); ?>"></script>
<script src="<?php $options->pluginUrl('/RecentViewsCounter/assets/visit-records.js'); ?>?v=<?php echo @filemtime(dirname(__DIR__).'/assets/visit-records.js'); ?>"></script>
<script>
// 内联兜底：防止浏览器缓存旧模块导致弹窗不弹出（用 window 赋值避免块级作用域问题）
if(typeof window.showRateLogModal!=='function'){
  window.showRateLogModal=function(){ var m=document.getElementById('rateLogModal'); if(m){ m.style.display='flex'; m.style.alignItems='center'; m.style.justifyContent='center'; } else { alert('未找到弹窗容器 #rateLogModal'); } };
}
if(typeof window.closeRateLogModal!=='function'){
  window.closeRateLogModal=function(){ var m=document.getElementById('rateLogModal'); if(m) m.style.display='none'; };
}
if(typeof window.showArchiveBanModal!=='function'){
  window.showArchiveBanModal=function(){ var m=document.getElementById('archiveBanModal'); if(m){ m.style.display='flex'; m.style.alignItems='center'; m.style.justifyContent='center'; } else { alert('未找到弹窗容器 #archiveBanModal'); } };
}
if(typeof window.closeArchiveBanModal!=='function'){
  window.closeArchiveBanModal=function(){ var m=document.getElementById('archiveBanModal'); if(m) m.style.display='none'; };
}
if(typeof window.showBlacklistModal!=='function'){
  window.showBlacklistModal=function(){ var m=document.getElementById('blacklistModal'); if(m){ m.style.display='flex'; m.style.alignItems='center'; m.style.justifyContent='center'; if(typeof loadBlacklistPage==='function') loadBlacklistPage(1); } else { alert('未找到弹窗容器 #blacklistModal'); } };
}
if(typeof window.closeBlacklistModal!=='function'){
  window.closeBlacklistModal=function(){ var m=document.getElementById('blacklistModal'); if(m) m.style.display='none'; };
}
if(typeof window.switchTab!=='function'){
  window.switchTab=function(tab){ if(tab==='blacklist'){ window.showBlacklistModal(); return; } };
}
// 遮罩点击与 ESC 关闭（兜底绑定）
document.addEventListener('click',function(e){
  if(e.target&&e.target.id==='rateLogModal') window.closeRateLogModal();
  if(e.target&&e.target.id==='archiveBanModal') window.closeArchiveBanModal();
  if(e.target&&e.target.id==='blacklistModal') window.closeBlacklistModal();
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'){ try{window.closeRateLogModal();}catch(_){} try{window.closeArchiveBanModal();}catch(_){} try{window.closeBlacklistModal();}catch(_){} }
});
console.log('[RecentViewsCounter] modules loaded, chart v=<?php echo @filemtime(dirname(__DIR__)."/assets/modules/chart.js"); ?>');
