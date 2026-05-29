// 绘制访问趋势图
function drawChart() {
    var canvas = document.getElementById('visitChart');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var data = weekData;

    // 获取设备像素比,解决高DPI屏幕模糊问题
    var dpr = window.devicePixelRatio || 1;

    // 获取容器实际宽度
    var container = canvas.parentElement;
    var displayWidth = container.offsetWidth;
    var displayHeight = 300;

    // 根据宽度自适应调整padding和字体
    var padding = displayWidth < 500 ? 30 : 40;
    var fontSize = displayWidth < 500 ? 10 : 12;
    var labelFontSize = displayWidth < 500 ? 10 : 12;

    // 设置canvas实际尺寸(考虑DPI)
    canvas.width = displayWidth * dpr;
    canvas.height = displayHeight * dpr;

    // 设置canvas显示尺寸
    canvas.style.width = displayWidth + 'px';
    canvas.style.height = displayHeight + 'px';

    // 缩放绘图上下文以匹配DPI
    ctx.scale(dpr, dpr);

    var width = displayWidth;
    var height = displayHeight;
    var chartWidth = width - 2 * padding;
    var chartHeight = height - 2 * padding;
    
    // 清空画布
    ctx.clearRect(0, 0, width, height);
    
    if (!data || data.length === 0) {
        ctx.fillStyle = '#999';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('暂无访问趋势数据', width / 2, height / 2);
        return;
    }
    
    // 找出最大值
    var maxRealUsers = Math.max.apply(Math, data.map(function(item) { return item.real_users || 0; }));
    var maxBots = Math.max.apply(Math, data.map(function(item) { return item.bots || 0; }));
    var maxValue = Math.max(maxRealUsers, maxBots);
    if (maxValue === 0) maxValue = 1;
    
    // 绘制坐标轴
    ctx.strokeStyle = '#ccc';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();
    
    // 绘制网格线
    ctx.strokeStyle = '#f0f0f0';
    ctx.lineWidth = 0.5;
    for (var i = 1; i <= 5; i++) {
        var y = padding + (i * chartHeight / 5);
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
    }
    
    // 绘制独立访客折线图
    ctx.strokeStyle = '#467b96';
    ctx.lineWidth = 2;
    ctx.beginPath();
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / (data.length - 1));
        var y = height - padding - ((data[i].real_users || 0) / maxValue * chartHeight);
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    ctx.stroke();
    
    // 绘制蜘蛛数折线图
    ctx.strokeStyle = '#ff6b35';
    ctx.lineWidth = 2;
    ctx.beginPath();
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / (data.length - 1));
        var y = height - padding - ((data[i].bots || 0) / maxValue * chartHeight);
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    ctx.stroke();
    
    // 绘制数据点和标签
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / (data.length - 1));
        var y = height - padding - ((data[i].real_users || 0) / maxValue * chartHeight);
        
        // 判断是否为周末
        var isWeekend = false;
        if (data[i].full_date) {
            var date = new Date(data[i].full_date);
            var dayOfWeek = date.getDay();
            isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
        }
        
        // 绘制独立访客数据点 - 周末标红，工作日用蓝色
        ctx.fillStyle = isWeekend ? '#ff0000' : '#467b96';
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // 绘制日期标签 - 周末标红，工作日用灰色
        ctx.fillStyle = isWeekend ? '#ff0000' : '#666';
        ctx.font = fontSize + 'px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(data[i].date.split('-')[1], x, height - padding + 15);

        // 绘制独立访客数值标签 - 周末标红，工作日用蓝色
        ctx.fillStyle = isWeekend ? '#ff0000' : '#467b96';
        ctx.fillText(data[i].real_users || 0, x, y - 10);
        
        // 绘制蜘蛛数据点
        var botY = height - padding - ((data[i].bots || 0) / maxValue * chartHeight);
        ctx.fillStyle = '#ff6b35';
        ctx.beginPath();
        ctx.arc(x, botY, 3, 0, 2 * Math.PI);
        ctx.fill();
        
        // 绘制蜘蛛数值标签
        ctx.fillStyle = '#ff6b35';
        ctx.fillText(data[i].bots || 0, x, botY - 10);
    }
    
    // 绘制图例
    var legendY = padding - 20;

    // 独立访客图例（排除Bot后的真实访问量）
    ctx.fillStyle = '#467b96';
    ctx.font = labelFontSize + 'px Arial';
    ctx.textAlign = 'left';
    ctx.fillText('● 非蜘蛛', padding, legendY + 4);

    // 蜘蛛数图例
    ctx.fillStyle = '#ff6b35';
    ctx.fillText('● 蜘蛛数', padding + 80, legendY + 4);

    // 工作日图例
    ctx.fillStyle = '#666';
    ctx.fillText('工作日', padding + 160, legendY + 4);

    // 周末图例
    ctx.fillStyle = '#ff0000';
    ctx.fillText('周末', padding + 210, legendY + 4);
}

// 绘制月度访问统计图（总访问量 vs 非Bot访问量）
function drawMonthlyChart() {
    var canvas = document.getElementById('monthlyChart');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var data = monthlyStatsData;

    // 获取设备像素比,解决高DPI屏幕模糊问题
    var dpr = window.devicePixelRatio || 1;

    // 获取容器实际宽度
    var container = canvas.parentElement;
    var displayWidth = container.offsetWidth;
    var displayHeight = 300;

    // 根据宽度自适应调整padding和字体
    var padding = displayWidth < 500 ? 35 : 50;
    var fontSize = displayWidth < 500 ? 10 : 11;
    var labelFontSize = displayWidth < 500 ? 10 : 12;

    // 设置canvas实际尺寸(考虑DPI)
    canvas.width = displayWidth * dpr;
    canvas.height = displayHeight * dpr;

    // 设置canvas显示尺寸
    canvas.style.width = displayWidth + 'px';
    canvas.style.height = displayHeight + 'px';

    // 缩放绘图上下文以匹配DPI
    ctx.scale(dpr, dpr);

    var width = displayWidth;
    var height = displayHeight;
    var chartWidth = width - 2 * padding;
    var chartHeight = height - 2 * padding;
    
    // 清空画布
    ctx.clearRect(0, 0, width, height);
    
    if (!data || data.length === 0) {
        // 显示无数据提示
        ctx.fillStyle = '#999';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('暂无月度统计数据', width / 2, height / 2);
        return;
    }
    
    // 找出最大值
    var maxValue = Math.max.apply(Math, data.map(function(item) { return parseInt(item.total_visits); }));
    if (maxValue === 0) maxValue = 1;
    
    // 绘制坐标轴
    ctx.strokeStyle = '#ddd';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();
    
    // 绘制网格线
    ctx.strokeStyle = '#f0f0f0';
    ctx.lineWidth = 0.5;
    for (var i = 1; i <= 5; i++) {
        var y = padding + (i * chartHeight / 5);
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
        
        // 绘制Y轴标签
        ctx.fillStyle = '#666';
        ctx.font = fontSize + 'px Arial';
        ctx.textAlign = 'right';
        var value = Math.round(maxValue * (5 - i) / 5);
        ctx.fillText(value.toString(), padding - 10, y + 4);
    }
    
    // 绘制总访问量折线
    ctx.strokeStyle = '#28a745';
    ctx.lineWidth = 3;
    ctx.beginPath();
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var visits = parseInt(data[i].total_visits);
        var y = height - padding - (visits / maxValue * chartHeight);
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    
    ctx.stroke();
    
    // 绘制总访问量数据点和标签
    ctx.fillStyle = '#28a745';
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var visits = parseInt(data[i].total_visits);
        var y = height - padding - (visits / maxValue * chartHeight);
        
        // 绘制数据点
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // 绘制月份标签
        ctx.fillStyle = '#666';
        ctx.font = fontSize + 'px Arial';
        ctx.textAlign = 'center';
        var monthLabel = data[i].year_month.split('-')[1];
        ctx.fillText(monthLabel, x, height - padding + 15);

        // 绘制数值标签
        ctx.fillStyle = '#28a745';
        ctx.font = 'bold ' + fontSize + 'px Arial';
        ctx.fillText(visits.toString(), x, y - 10);
        ctx.fillStyle = '#28a745';
    }
    
    // 绘制非Bot访问折线
    ctx.strokeStyle = '#17a2b8';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 5]); // 虚线
    ctx.beginPath();
    
    var maxNonBotVisits = Math.max.apply(Math, data.map(function(item) { return parseInt(item.unique_visitors || 0); }));
    if (maxNonBotVisits === 0) maxNonBotVisits = 1;
    
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var nonBotVisits = parseInt(data[i].unique_visitors || 0);
        var y = height - padding - (nonBotVisits / maxValue * chartHeight);
        
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    
    ctx.stroke();
    ctx.setLineDash([]);
    
    // 绘制非Bot访问数据点
    ctx.fillStyle = '#17a2b8';
    for (var i = 0; i < data.length; i++) {
        var x = padding + (i * chartWidth / Math.max(data.length - 1, 1));
        var nonBotVisits = parseInt(data[i].unique_visitors || 0);
        var y = height - padding - (nonBotVisits / maxValue * chartHeight);
        
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    // 绘制图例
    var legendY = padding - 20;
    ctx.fillStyle = '#28a745';
    ctx.fillRect(padding, legendY, 15, 3);
    ctx.fillStyle = '#333';
    ctx.font = labelFontSize + 'px Arial';
    ctx.textAlign = 'left';
    ctx.fillText('总访问量', padding + 20, legendY + 10);

    ctx.strokeStyle = '#17a2b8';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 5]);
    ctx.beginPath();
    ctx.moveTo(padding + 120, legendY + 1.5);
    ctx.lineTo(padding + 135, legendY + 1.5);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle = '#333';
    ctx.fillText('非Bot访问', padding + 140, legendY + 10);
}

// 页面加载完成后绘制图表
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        drawChart();
        drawMonthlyChart();
    });
} else {
    drawChart();
    drawMonthlyChart();
}

// 窗口大小变化时重绘图表
var resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        drawChart();
        drawMonthlyChart();
    }, 250);
});

// 显示访问记录模态框
function showVisitRecords(cid, title) {
    // 创建模态框
    var modal = document.createElement('div');
    modal.id = 'visitRecordsModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    var modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-width: 80%;
        max-height: 80%;
        overflow: auto;
        position: relative;
    `;
    
    modalContent.innerHTML = `
        <h3 style="margin-top: 0; color: #467b96;">文章访问记录</h3>
        <p><strong>文章标题：</strong>${title}</p>
        <div id="recordsContent">正在加载访问记录...</div>
        <button onclick="closeVisitRecords()" style="
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #999;
        ">×</button>
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="closeVisitRecords()" class="btn btn-s">关闭</button>
        </div>
    `;
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // 加载访问记录数据
    loadVisitRecords(cid);
}

// 关闭访问记录模态框
function closeVisitRecords() {
    var modal = document.getElementById('visitRecordsModal');
    if (modal) {
        modal.remove();
    }
}

// 加载访问记录数据
function loadVisitRecords(cid) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            var recordsContent = document.getElementById('recordsContent');
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        displayVisitRecords(response.data);
                    } else {
                        recordsContent.innerHTML = '<p style="color: #dc3545;">加载失败：' + (response.message || '未知错误') + '</p>';
                    }
                } catch (e) {
                    console.error('JSON解析错误:', e);
                    console.error('响应内容:', xhr.responseText);
                    recordsContent.innerHTML = '<p style="color: #dc3545;">数据解析失败</p><details><summary>详细信息</summary><pre>' + xhr.responseText + '</pre></details>';
                }
            } else {
                recordsContent.innerHTML = '<p style="color: #dc3545;">网络请求失败</p>';
            }
        }
    };
    
    xhr.send('action=get_visit_records&cid=' + cid);
}

// 显示访问记录数据
function displayVisitRecords(records) {
    var recordsContent = document.getElementById('recordsContent');
    
    if (!records || records.length === 0) {
        recordsContent.innerHTML = '<p>暂无访问记录</p>';
        return;
    }
    
    var html = `
        <div style="max-height: 400px; overflow-y: auto;">
            <table class="typecho-list-table" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="width: 20%;">访问时间</th>
                        <th style="width: 25%;">IP地址</th>
                        <th style="width: 35%;">User Agent</th>
                        <th style="width: 20%;">归属地</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    records.forEach(function(record) {
        var visitTime = new Date(record.visit_time * 1000).toLocaleString('zh-CN');
        var userAgent = record.user_agent || '未知';
        var location = record.location || '未知地区';
        
        // 截断过长的User Agent
        if (userAgent.length > 50) {
            userAgent = userAgent.substring(0, 50) + '...';
        }
        
        // 截断过长的地区信息
        if (location.length > 30) {
            location = location.substring(0, 30) + '...';
        }
        
        html += `
            <tr>
                <td>${visitTime}</td>
                <td>${record.ip}</td>
                <td title="${record.user_agent || ''}">${userAgent}</td>
                <td title="${record.location || ''}">${location}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <p style="margin-top: 15px; color: #666; font-size: 12px;">显示最近100条访问记录</p>
    `;
    
    recordsContent.innerHTML = html;
}

// IP访问详情相关函数
function showIpVisitDetails(ip) {
    // 创建模态框
    if (!document.getElementById('ipVisitDetailsModal')) {
        const modal = document.createElement('div');
        modal.id = 'ipVisitDetailsModal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 1000px;
                max-height: 80%;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            ">
                <div style="
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f8f9fa;
                ">
                    <h3 style="margin: 0; color: #333;">IP访问详情</h3>
                    <button onclick="closeIpVisitDetails()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: #666;
                        padding: 0;
                        width: 30px;
                        height: 30px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">×</button>
                </div>
                <div id="ipVisitDetailsContent" style="
                    padding: 20px;
                    max-height: 500px;
                    overflow-y: auto;
                ">加载中...</div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    document.getElementById('ipVisitDetailsModal').style.display = 'flex';
    loadIpVisitDetails(ip);
}

function closeIpVisitDetails() {
    const modal = document.getElementById('ipVisitDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function loadIpVisitDetails(ip) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        displayIpVisitDetails(response.data, ip);
                    } else {
                        document.getElementById('ipVisitDetailsContent').innerHTML = 
                            '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ ' + 
                            (response.message || '获取数据失败') + '</div>';
                    }
                } catch (e) {
                    console.error('JSON解析错误:', e);
                    console.log('原始响应:', xhr.responseText);
                    document.getElementById('ipVisitDetailsContent').innerHTML = 
                        '<div style="color: #dc3545; text-align: center; padding: 20px;">' +
                        '❌ 数据解析失败<br>' +
                        '<details style="margin-top: 10px; text-align: left;">' +
                        '<summary style="cursor: pointer;">查看详细错误信息</summary>' +
                        '<pre style="background: #f8f9fa; padding: 10px; margin-top: 5px; font-size: 12px; overflow: auto;">' +
                        '解析错误: ' + e.message + '\n\n' +
                        '原始响应: ' + xhr.responseText.substring(0, 500) + 
                        (xhr.responseText.length > 500 ? '...' : '') +
                        '</pre></details></div>';
                }
            } else {
                document.getElementById('ipVisitDetailsContent').innerHTML = 
                    '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ 请求失败，状态码: ' + xhr.status + '</div>';
            }
        }
    };
    
    xhr.send('action=get_ip_visit_details&ip=' + encodeURIComponent(ip));
}

function displayIpVisitDetails(records, ip) {
    if (!records || records.length === 0) {
        document.getElementById('ipVisitDetailsContent').innerHTML = 
            '<div style="text-align: center; padding: 20px; color: #666;">📭 该IP暂无访问记录</div>';
        return;
    }
    
    let html = `
        <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h4 style="margin: 0 0 10px 0; color: #495057;">🌐 IP地址: ${ip}
                <button onclick="addToBlacklist('${ip}', this)" style="margin-left: 10px; background: #dc3545; color: white; border: none; padding: 3px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">加入黑名单</button>
            </h4>
            <p style="margin: 0; color: #6c757d; font-size: 14px;">共找到 ${records.length} 条访问记录</p>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">访问时间</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">访问文章</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">User Agent</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">归属地</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    records.forEach(record => {
        const visitTime = new Date(record.visit_time * 1000).toLocaleString('zh-CN');
        
        // 截断过长的User Agent
        let userAgent = record.user_agent || '未知';
        if (userAgent.length > 50) {
            userAgent = userAgent.substring(0, 50) + '...';
        }
        
        // 截断过长的地区信息
        let location = record.location || '未知';
        if (location.length > 30) {
            location = location.substring(0, 30) + '...';
        }
        
        // 获取文章标题
        let articleTitle = record.article_title || '未知文章';
        if (articleTitle.length > 40) {
            articleTitle = articleTitle.substring(0, 40) + '...';
        }
        
        html += `
            <tr>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${visitTime}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;" title="${record.article_title || ''}">${articleTitle}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;" title="${record.user_agent || ''}">${userAgent}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;" title="${record.location || ''}">${location}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('ipVisitDetailsContent').innerHTML = html;
}

// IP归属地数据管理相关函数
function loadIpLocationData(page = 1) {
    // 创建模态框
    if (!document.getElementById('ipLocationModal')) {
        const modal = document.createElement('div');
        modal.id = 'ipLocationModal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 1000px;
                max-height: 80%;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            ">
                <div style="
                    padding: 20px;
                    border-bottom: 1px solid #dee2e6;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f8f9fa;
                ">
                    <h3 style="margin: 0; color: #333;">IP归属地数据管理</h3>
                    <button onclick="closeIpLocationModal()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: #666;
                        padding: 0;
                        width: 30px;
                        height: 30px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">×</button>
                </div>
                <div id="ipLocationContent" style="
                    padding: 20px;
                    max-height: 500px;
                    overflow-y: auto;
                ">加载中...</div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    document.getElementById('ipLocationModal').style.display = 'flex';
    
    // 发送AJAX请求获取IP数据
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        displayIpLocationData(response.data, response.total, page);
                    } else {
                        document.getElementById('ipLocationContent').innerHTML = 
                            '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ ' + 
                            (response.message || '获取数据失败') + '</div>';
                    }
                } catch (e) {
                    console.error('JSON解析错误:', e);
                    document.getElementById('ipLocationContent').innerHTML = 
                        '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ 数据解析失败</div>';
                }
            } else {
                document.getElementById('ipLocationContent').innerHTML = 
                    '<div style="color: #dc3545; text-align: center; padding: 20px;">❌ 请求失败，状态码: ' + xhr.status + '</div>';
            }
        }
    };
    
    xhr.send('action=get_ip_location_data&page=' + page);
}

function closeIpLocationModal() {
    const modal = document.getElementById('ipLocationModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function displayIpLocationData(data, total, currentPage) {
    if (!data || data.length === 0) {
        document.getElementById('ipLocationContent').innerHTML = 
            '<div style="text-align: center; padding: 20px; color: #666;">📭 暂无IP归属地数据</div>';
        return;
    }
    
    const perPage = 20;
    const totalPages = Math.ceil(total / perPage);
    
    let html = `
        <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h4 style="margin: 0 0 10px 0; color: #495057;">🌐 IP归属地数据库</h4>
            <p style="margin: 0; color: #6c757d; font-size: 14px;">共 ${total} 条记录，当前第 ${currentPage}/${totalPages} 页</p>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">IP地址</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">归属地</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">国家</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">省份</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">城市</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">运营商</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">更新时间</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">操作</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const updateTime = new Date(record.updated_time * 1000).toLocaleString('zh-CN');
        
        html += `
            <tr>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.ip}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.location || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.country || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.region || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.city || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${record.isp || '未知'}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">${updateTime}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">
                    <button onclick="deleteIpLocation('${record.ip}')" 
                            style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;"
                            title="删除此IP记录">删除</button>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    // 添加分页
    if (totalPages > 1) {
        html += '<div style="margin-top: 20px; text-align: center;">';
        
        if (currentPage > 1) {
            html += `<button onclick="loadIpLocationData(${currentPage - 1})" style="margin: 0 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;">上一页</button>`;
        }
        
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            if (i === currentPage) {
                html += `<button style="margin: 0 2px; padding: 5px 10px; background: #0073aa; color: white; border: none; border-radius: 3px;">${i}</button>`;
            } else {
                html += `<button onclick="loadIpLocationData(${i})" style="margin: 0 2px; padding: 5px 10px; background: #f1f1f1; color: #333; border: none; border-radius: 3px; cursor: pointer;">${i}</button>`;
            }
        }
        
        if (currentPage < totalPages) {
            html += `<button onclick="loadIpLocationData(${currentPage + 1})" style="margin: 0 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;">下一页</button>`;
        }
        
        html += '</div>';
    }
    
    document.getElementById('ipLocationContent').innerHTML = html;
}

function deleteIpLocation(ip) {
    if (!confirm('确定要删除IP "' + ip + '" 的归属地记录吗？此操作不可恢复！')) {
        return;
    }
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('删除成功！');
                        loadIpLocationData(1); // 重新加载第一页数据
                    } else {
                        alert('删除失败：' + (response.message || '未知错误'));
                    }
                } catch (e) {
                    alert('删除失败：数据解析错误');
                }
            } else {
                alert('删除失败：请求错误');
            }
        }
    };
    
    xhr.send('action=delete_ip_location&ip=' + encodeURIComponent(ip));
}

// IP视图切换函数（全部/仅非Bot）
function switchIpView() {
    var val = document.getElementById('ip_view_selector').value;
    document.getElementById('ip_view_all').style.display = (val === 'all') ? '' : 'none';
    var nb = document.getElementById('ip_view_nonbot');
    if (nb) nb.style.display = (val === 'nonbot') ? '' : 'none';
}

// IP时间范围切换函数
function changeIpTimeRange() {
    const timeRange = document.getElementById('ip_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === 'all') {
        currentUrl.searchParams.delete('ip_time_range');
    } else {
        currentUrl.searchParams.set('ip_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 访问来源时间范围切换函数
function changeRefererTimeRange() {
    const timeRange = document.getElementById('referer_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === '7') {
        currentUrl.searchParams.delete('referer_time_range'); // 7天是默认值，删除参数
    } else {
        currentUrl.searchParams.set('referer_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 统一时间范围切换函数
function changeGlobalTimeRange() {
    const timeRange = document.getElementById('global_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    // 同时设置地域分布、访问来源、IP统计和热门文章的时间范围
    if (timeRange === '7') {
        // 7天是默认值，删除参数
        currentUrl.searchParams.delete('location_time_range');
        currentUrl.searchParams.delete('article_time_range');
        currentUrl.searchParams.delete('referer_time_range');
        currentUrl.searchParams.delete('ip_time_range');
    } else {
        currentUrl.searchParams.set('location_time_range', timeRange);
        currentUrl.searchParams.set('article_time_range', timeRange);
        currentUrl.searchParams.set('referer_time_range', timeRange);
        currentUrl.searchParams.set('ip_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 地域分布时间范围切换函数
function changeLocationTimeRange() {
    const timeRange = document.getElementById('location_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === '7') {
        currentUrl.searchParams.delete('location_time_range'); // 7天是默认值，删除参数
    } else {
        currentUrl.searchParams.set('location_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 热门文章时间范围切换函数
function changeArticleTimeRange() {
    const timeRange = document.getElementById('article_time_range').value;
    const currentUrl = new URL(window.location.href);
    
    if (timeRange === '7') {
        currentUrl.searchParams.delete('article_time_range'); // 7天是默认值，删除参数
    } else {
        currentUrl.searchParams.set('article_time_range', timeRange);
    }
    
    window.location.href = currentUrl.toString();
}

// 拉黑/解封 IP（来自 TOP 20 列表）
function addToBlacklist(ip, el) {
    if (!confirm('确定要将 IP "' + ip + '" 加入黑名单吗？加入后该IP将被拦截访问。')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    el.outerHTML = '<span style="color: #dc3545; font-size: 12px;">☑ 已拉黑</span>';
                } else {
                    alert(resp.message || '操作失败');
                }
            } catch (e) {
                alert('操作失败');
            }
        }
    };
    xhr.send('action=add_to_blacklist&ip=' + encodeURIComponent(ip));
}

// 页面加载时设置统一时间选择器的默认值
document.addEventListener('DOMContentLoaded', function() {
    const globalTimeRange = document.getElementById('global_time_range');
    if (globalTimeRange) {
        const urlParams = new URLSearchParams(window.location.search);
        const locationRange = urlParams.get('location_time_range') || '7';
        const articleRange = urlParams.get('article_time_range') || '7';
        const refererRange = urlParams.get('referer_time_range') || '7';
        const ipRange = urlParams.get('ip_time_range') || '7';
        
        // 如果所有模块的时间范围一致，则设置统一选择器
        if (locationRange === articleRange && articleRange === refererRange && refererRange === ipRange) {
            globalTimeRange.value = locationRange;
        } else {
            // 如果不一致，默认设置为7天
            globalTimeRange.value = '7';
        }
    }
});

// ---- 标签切换 ----
function switchTab(tab) {
    var statsEl = document.getElementById('tab_content_stats');
    var blEl = document.getElementById('tab_content_blacklist');
    if (tab === 'stats') {
        statsEl.style.display = '';
        blEl.style.display = 'none';
    } else {
        statsEl.style.display = 'none';
        blEl.style.display = '';
    }
}

// ---- 黑名单操作 ----
function loadBlacklistPage(page) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    renderBlacklistTable(resp);
                }
            } catch (e) {}
        }
    };
    xhr.send('action=get_blacklist&page=' + page);
}

function renderBlacklistTable(resp) {
    var tbody = document.getElementById('blacklist_tbody');
    var pagination = document.getElementById('blacklist_pagination');
    var empty = document.getElementById('blacklist_empty');
    var count = document.getElementById('blacklist_count');
    var wrap = document.getElementById('blacklist_table_wrap');

    count.textContent = '(共 ' + resp.total + ' 条)';

    if (!resp.data || resp.data.length === 0) {
        wrap.style.display = 'none';
        empty.style.display = '';
        return;
    }
    wrap.style.display = '';
    empty.style.display = 'none';

    var html = '';
    resp.data.forEach(function(item) {
        var time = new Date(item.created_time * 1000).toLocaleString('zh-CN');
        html += '<tr id="bl_' + item.ip + '">';
        html += '<td><strong style="font-family: monospace;">' + item.ip + '</strong></td>';
        html += '<td>' + (item.reason || '-') + '</td>';
        html += '<td>' + time + '</td>';
        html += '<td><button onclick="removeFromBlacklist(\'' + item.ip + '\')" style="background: #dc3545; color: white; border: none; padding: 4px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">解封</button></td>';
        html += '</tr>';
    });
    tbody.innerHTML = html;

    var totalPages = Math.ceil(resp.total / resp.limit);
    var pageHtml = '';
    if (totalPages > 1) {
        for (var i = 1; i <= totalPages; i++) {
            var active = i === resp.page ? ' background: #0073aa; color: white;' : '';
            pageHtml += '<button onclick="loadBlacklistPage(' + i + ')" class="btn btn-s" style="margin: 0 2px;' + active + '">' + i + '</button>';
        }
    }
    pagination.innerHTML = pageHtml;
}

function addToBlacklistFromInput() {
    var ip = document.getElementById('blacklist_ip').value.trim();
    var reason = document.getElementById('blacklist_reason').value.trim();
    var resultDiv = document.getElementById('add_result');

    if (!ip) {
        resultDiv.innerHTML = '<span style="color: #dc3545;">请输入IP地址</span>';
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    resultDiv.innerHTML = '<span style="color: #28a745;">' + resp.message + '</span>';
                    document.getElementById('blacklist_ip').value = '';
                    document.getElementById('blacklist_reason').value = '';
                    loadBlacklistPage(1);
                } else {
                    resultDiv.innerHTML = '<span style="color: #dc3545;">' + resp.message + '</span>';
                }
            } catch (e) {
                resultDiv.innerHTML = '<span style="color: #dc3545;">请求失败</span>';
            }
        }
    };
    xhr.send('action=add_to_blacklist&ip=' + encodeURIComponent(ip) + '&reason=' + encodeURIComponent(reason));
}

function removeFromBlacklist(ip) {
    if (!confirm('确定要将 IP "' + ip + '" 移出黑名单（解封）吗？')) return;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    var row = document.getElementById('bl_' + ip);
                    if (row) row.remove();
                    loadBlacklistPage(1);
                } else {
                    alert('解封失败：' + resp.message);
                }
            } catch (e) {
                alert('解封失败');
            }
        }
    };
    xhr.send('action=remove_from_blacklist&ip=' + encodeURIComponent(ip));
}
