// modules/chart.js — 图表绘制 (drawChart/drawMonthlyChart + 初始化)
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
