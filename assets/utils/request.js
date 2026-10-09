// utils/request.js — 通用 POST 请求封装
// 按站点时区格式化时间戳（秒），与 PHP 端 Typecho\Date 口径一致。
// 站点偏移由页面注入 window.RVC_TZ_OFFSET；缺失时回落到浏览器本地时区。
function rvcFormatTime(ts) {
  var d = new Date((Number(ts) + (window.RVC_TZ_OFFSET || 0)) * 1000);
  var p = function(n) { return (n < 10 ? '0' : '') + n; };
  return d.getUTCFullYear() + '-' + p(d.getUTCMonth() + 1) + '-' + p(d.getUTCDate()) + ' ' +
         p(d.getUTCHours()) + ':' + p(d.getUTCMinutes()) + ':' + p(d.getUTCSeconds());
}

function rvcPost(action, params, callback) {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', window.location.href, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) callback(xhr);
  };
  var body = 'action=' + encodeURIComponent(action);
  if (params) {
    for (var k in params) if (params.hasOwnProperty(k)) body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
  }
  xhr.send(body);
}
