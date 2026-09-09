// modules/time-range.js — 时间范围切换
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
