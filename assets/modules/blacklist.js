// modules/blacklist.js — 黑名单管理 (TOP列表拉黑 + 黑名单页)
// 拉黑/解封 IP（来自 TOP 20 列表）
function addToBlacklist(ip, el) {
    var detail = el.getAttribute('data-detail') || '';
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
    xhr.send('action=add_to_blacklist&ip=' + encodeURIComponent(ip) + '&reason=' + encodeURIComponent(detail));
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
        var time = rvcFormatTime(item.created_time);
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
