// modules/ui.js — 标签切换 + 频率日志弹窗
// ---- 标签切换（保留兼容，黑名单已改为 dialog） ----
function switchTab(tab) {
    // 兼容旧调用：黑名单改为 dialog
    if (tab === 'blacklist') {
        showBlacklistModal();
        return;
    }
    var statsEl = document.getElementById('tab_content_stats');
    var blEl = document.getElementById('tab_content_blacklist');
    // 旧 tab 结构已移除，若存在则切换
    if (statsEl) statsEl.style.display = tab === 'stats' ? '' : 'none';
    if (blEl) blEl.style.display = tab === 'blacklist' ? '' : 'none';
    if (tab === 'stats' && blEl) closeBlacklistModal();
}

// ---- 文章页面频率日志 / 自动封禁弹窗 ----
function showRateLogModal() {
    var m = document.getElementById('rateLogModal');
    if (m) m.style.display = 'flex';
}
function closeRateLogModal() {
    var m = document.getElementById('rateLogModal');
    if (m) m.style.display = 'none';
}
function showArchiveBanModal() {
    var m = document.getElementById('archiveBanModal');
    if (m) m.style.display = 'flex';
}
function closeArchiveBanModal() {
    var m = document.getElementById('archiveBanModal');
    if (m) m.style.display = 'none';
}
// ---- 黑名单管理 dialog ----
function showBlacklistModal() {
    var m = document.getElementById('blacklistModal');
    if (m) {
        m.style.display = 'flex';
        m.style.alignItems = 'center';
        m.style.justifyContent = 'center';
        if (typeof loadBlacklistPage === 'function') loadBlacklistPage(1);
    }
}
function closeBlacklistModal() {
    var m = document.getElementById('blacklistModal');
    if (m) m.style.display = 'none';
}
// 点击遮罩关闭
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'rateLogModal') closeRateLogModal();
    if (e.target && e.target.id === 'archiveBanModal') closeArchiveBanModal();
    if (e.target && e.target.id === 'blacklistModal') closeBlacklistModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeRateLogModal(); closeArchiveBanModal(); closeBlacklistModal(); }
});
