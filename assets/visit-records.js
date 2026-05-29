// RecentViewsCounter - 前端访问记录查看
function showVisitRecords(cid, title) {
    var modal = document.createElement('div');
    modal.id = 'visitRecordsModal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:center;justify-content:center;';
    var mc = document.createElement('div');
    mc.style.cssText = 'background:white;padding:20px;border-radius:8px;max-width:80%;max-height:80%;overflow:auto;position:relative;';
    mc.innerHTML = '<h3 style="margin-top:0;color:#467b96;">文章访问记录</h3>' +
        '<p><strong>文章标题：</strong>' + title + '</p>' +
        '<div id="recordsContent">正在加载访问记录...</div>' +
        '<button onclick="closeVisitRecords()" style="position:absolute;top:10px;right:15px;background:none;border:none;font-size:20px;cursor:pointer;color:#999;">\u00d7</button>' +
        '<div style="text-align:center;margin-top:20px;"><button onclick="closeVisitRecords()" style="background:#467b96;color:white;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;">关闭</button></div>';
    modal.appendChild(mc);
    document.body.appendChild(modal);
    loadVisitRecords(cid);
}

function closeVisitRecords() {
    var modal = document.getElementById('visitRecordsModal');
    if (modal) modal.remove();
}

function loadVisitRecords(cid) {
    var xhr = new XMLHttpRequest();
    var base = window.RVC_ADMIN_URL;
    var url = base ? base + 'extending.php?panel=RecentViewsCounter%2FPanel.php' : window.location.href;
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            var el = document.getElementById('recordsContent');
            if (!el) return;
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        displayVisitRecords(resp.data);
                    } else {
                        el.innerHTML = '<p style="color:#dc3545;">加载失败：' + (resp.message || '未知错误') + '</p>';
                    }
                } catch (e) {
                    el.innerHTML = '<p style="color:#dc3545;">数据解析失败</p>';
                }
            } else {
                el.innerHTML = '<p style="color:#dc3545;">网络请求失败</p>';
            }
        }
    };
    xhr.send('action=get_visit_records&cid=' + cid);
}

function displayVisitRecords(records) {
    var el = document.getElementById('recordsContent');
    if (!el) return;
    if (!records || records.length === 0) {
        el.innerHTML = '<p>暂无访问记录</p>';
        return;
    }
    var h = '<div style="max-height:400px;overflow-y:auto;"><table style="width:100%;border-collapse:collapse;margin:0;"><thead><tr style="background:#f5f5f5;">' +
        '<th style="padding:8px;border:1px solid #ddd;text-align:left;">访问时间</th>' +
        '<th style="padding:8px;border:1px solid #ddd;text-align:left;">IP地址</th>' +
        '<th style="padding:8px;border:1px solid #ddd;text-align:left;">User Agent</th>' +
        '<th style="padding:8px;border:1px solid #ddd;text-align:left;">归属地</th>' +
        '</tr></thead><tbody>';
    records.forEach(function(r) {
        var t = new Date(r.visit_time * 1000).toLocaleString('zh-CN');
        var ua = r.user_agent || '未知';
        var loc = r.location || '未知地区';
        if (ua.length > 50) ua = ua.substring(0, 50) + '...';
        if (loc.length > 30) loc = loc.substring(0, 30) + '...';
        h += '<tr><td style="padding:8px;border:1px solid #ddd;">' + t + '</td>' +
            '<td style="padding:8px;border:1px solid #ddd;">' + r.ip + '</td>' +
            '<td style="padding:8px;border:1px solid #ddd;" title="' + (r.user_agent || '') + '">' + ua + '</td>' +
            '<td style="padding:8px;border:1px solid #ddd;" title="' + (r.location || '') + '">' + loc + '</td></tr>';
    });
    h += '</tbody></table></div><p style="margin-top:15px;color:#666;font-size:12px;">显示最近100条访问记录</p>';
    el.innerHTML = h;
}
