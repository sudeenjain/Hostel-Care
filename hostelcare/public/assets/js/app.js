(function () {
  'use strict';

  // Category choice shows its default priority and target time, and presets the priority field.
  var cat = document.getElementById('category');
  var prio = document.getElementById('priority');
  var hint = document.getElementById('cat-hint');
  function showCategory() {
    if (!cat || !hint) return;
    var opt = cat.options[cat.selectedIndex];
    if (!opt || !opt.value) { hint.textContent = ''; return; }
    var sla = parseInt(opt.getAttribute('data-sla'), 10);
    var when = sla >= 48 ? Math.round(sla / 24) + ' days' : sla + ' hours';
    hint.textContent = 'Target for this kind of complaint: resolved within ' + when + '.';
    if (prio && !prio.dataset.touched) prio.value = opt.getAttribute('data-priority');
  }
  if (cat) {
    if (prio) prio.addEventListener('change', function () { prio.dataset.touched = '1'; });
    cat.addEventListener('change', showCategory);
    showCategory();
  }

  // Live character counter.
  document.querySelectorAll('[data-count]').forEach(function (ta) {
    var out = document.getElementById(ta.getAttribute('data-count'));
    if (!out) return;
    var max = ta.getAttribute('maxlength');
    function update() { out.textContent = ta.value.length + (max ? ' / ' + max : '') + ' characters'; }
    ta.addEventListener('input', update);
    update();
  });

  // Limit number of photos before upload.
  var photos = document.getElementById('photos');
  if (photos) {
    photos.addEventListener('change', function () {
      var max = parseInt(photos.getAttribute('data-max'), 10);
      if (photos.files.length > max) {
        alert('You can attach at most ' + max + ' photos.');
        photos.value = '';
      }
    });
  }

  // Confirm before sensitive form submits.
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Hide block/room fields unless creating a student account.
  var role = document.getElementById('new-role');
  var room = document.getElementById('room-fields');
  function toggleRoom() { if (role && room) room.style.display = role.value === 'student' ? '' : 'none'; }
  if (role) { role.addEventListener('change', toggleRoom); toggleRoom(); }

  // Fade success messages.
  document.querySelectorAll('.flash-ok').forEach(function (el) {
    setTimeout(function () { el.style.transition = 'opacity .6s'; el.style.opacity = '0'; }, 5000);
  });
})();
