(() => {
  'use strict';
  const root = document.documentElement;

  /* ganti tema terang/gelap */
  const themeBtn = document.getElementById('themeBtn');
  if (themeBtn) themeBtn.addEventListener('click', () => {
    SWTheme.set(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
  });

  /* animasi muncul saat di-scroll */
  const items = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    }), { threshold: 0.12 });
    items.forEach((el) => io.observe(el));
  } else {
    items.forEach((el) => el.classList.add('in'));
  }

  /* akun demo */
  const meta = document.querySelector('meta[name="csrf"]');
  const errBox = document.getElementById('demoErr');
  document.querySelectorAll('[data-demo]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (errBox) errBox.hidden = true;
      btn.disabled = true; btn.classList.add('busy');
      try {
        const res = await fetch('api/auth.php?action=demo', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': meta ? meta.content : '' },
          body: '{}',
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok === false) throw new Error((data && data.message) || 'Terjadi kesalahan. Coba lagi.');
        location.href = data.redirect || 'dashboard.php';
      } catch (err) {
        if (errBox) { errBox.textContent = err.message || 'Tidak dapat terhubung ke server.'; errBox.hidden = false; }
        btn.disabled = false; btn.classList.remove('busy');
      }
    });
  });
})();
