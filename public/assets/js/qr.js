(function () {
  var box = document.getElementById('qr');
  if (!box || typeof qrcode === 'undefined') { return; }
  var qr = qrcode(0, 'M');
  qr.addData(box.getAttribute('data-link'));
  qr.make();
  var n = qr.getModuleCount(), cell = 10, quiet = 4, size = (n + quiet * 2) * cell;
  var canvas = document.createElement('canvas');
  canvas.width = canvas.height = size;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, size, size);
  ctx.fillStyle = '#000';
  for (var r = 0; r < n; r++) { for (var c = 0; c < n; c++) { if (qr.isDark(r, c)) { ctx.fillRect((c + quiet) * cell, (r + quiet) * cell, cell, cell); } } }
  canvas.style.width = '280px'; canvas.style.height = '280px'; canvas.id = 'qr-canvas';
  box.appendChild(canvas);
  document.getElementById('qr-print').addEventListener('click', function () { window.print(); });
  document.getElementById('qr-png').addEventListener('click', function () {
    var a = document.createElement('a'); a.href = canvas.toDataURL('image/png'); a.download = 'hostel-qr.png'; a.click();
  });
})();
