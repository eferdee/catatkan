/* Terapkan tema sebelum halaman dirender agar tidak berkedip. Mode: light | dark | auto */
(function () {
  var KEY = 'sw-theme';
  var root = document.documentElement;
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function mode() {
    try { return localStorage.getItem(KEY) || 'auto'; } catch (e) { return 'auto'; }
  }
  function apply() {
    var m = mode();
    var t = m === 'auto' ? (mq && mq.matches ? 'dark' : 'light') : m;
    root.setAttribute('data-theme', t);
    root.setAttribute('data-theme-mode', m);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', t === 'dark' ? '#08120d' : '#f2f5ee');
  }
  apply();
  if (mq && mq.addEventListener) mq.addEventListener('change', apply);

  window.SWTheme = {
    get: mode,
    set: function (m) {
      try { localStorage.setItem(KEY, m); } catch (e) { /* abaikan */ }
      apply();
    }
  };
})();
