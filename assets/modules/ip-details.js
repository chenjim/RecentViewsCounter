// modules/ip-details.js — IP访问详情弹窗
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
