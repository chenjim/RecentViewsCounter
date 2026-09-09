// utils/request.js — 通用 POST 请求封装
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
