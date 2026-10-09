// modules/ip-location.js — IP归属地管理
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
        const updateTime = rvcFormatTime(record.updated_time);
        
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
