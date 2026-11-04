(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf"]').content;
  const form = document.querySelector('form[data-auth]');
  const alertBox = document.getElementById('formAlert');
  const mode = form.dataset.auth;

  /* ------------------------------ util ------------------------------ */
  async function post(action, body) {
    let res;
    try {
      res = await fetch('api/auth.php?action=' + action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(body || {}),
      });
    } catch {
      throw Object.assign(new Error('Tidak dapat terhubung ke server.'), { errors: {} });
    }
    let data = null;
    try { data = await res.json(); } catch { /* bukan JSON */ }
    if (!res.ok || !data || data.ok === false) {
      throw Object.assign(new Error((data && data.message) || 'Terjadi kesalahan. Coba lagi.'), {
        errors: (data && data.errors) || {},
        status: res.status,
      });
    }
    return data;
  }

  const setBusy = (btn, busy) => { btn.disabled = busy; btn.classList.toggle('busy', busy); };

  function clearErrors() {
    alertBox.hidden = true;
    form.querySelectorAll('.field').forEach(f => f.classList.remove('invalid'));
    form.querySelectorAll('[data-err]').forEach(el => { el.textContent = ''; });
  }
  function showErrors(errors) {
    Object.entries(errors).forEach(([key, msg]) => {
      const slot = form.querySelector(`[data-err="${key}"]`);
      if (slot) {
        slot.textContent = msg;
        slot.closest('.field').classList.add('invalid');
      }
    });
  }
  function showAlert(msg) {
    alertBox.textContent = msg;
    alertBox.hidden = false;
  }

  /* --------------------------- tampilkan sandi --------------------------- */
  document.querySelectorAll('[data-toggle-pw]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.parentElement.querySelector('input');
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.querySelector('use').setAttribute('href', show ? '#i-eye-off' : '#i-eye');
      btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
  });

  /* ------------------------- kekuatan kata sandi ------------------------- */
  if (mode === 'register') {
    const pw = form.elements.password;
    const meter = document.getElementById('pwMeter');
    const rules = {
      len: v => v.length >= 8,
      letter: v => /[A-Za-z]/.test(v),
      digit: v => /\d/.test(v),
    };
    const update = () => {
      const v = pw.value;
      let level = 0;
      Object.entries(rules).forEach(([key, test]) => {
        const ok = test(v);
        document.querySelector(`#pwRules [data-rule="${key}"]`).classList.toggle('ok', ok);
        if (ok) level++;
      });
      if (level === 3 && (v.length >= 12 || (/[A-Z]/.test(v) && /[a-z]/.test(v)) || /[^A-Za-z0-9]/.test(v))) level = 4;
      meter.dataset.level = v ? String(level) : '0';
    };
    pw.addEventListener('input', update);
  }

  /* -------------------------------- submit -------------------------------- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();
    const btn = form.querySelector('button[type="submit"]');
    const f = form.elements;
    const email = f.email.value.trim();
    const password = f.password.value;
    const errs = {};

    if (!email) errs.email = 'Email wajib diisi.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errs.email = 'Format email tidak valid.';
    if (!password) errs.password = 'Kata sandi wajib diisi.';

    let body = { email, password };
    if (mode === 'register') {
      const name = f.name.value.trim();
      if (name.length < 2) errs.name = 'Nama minimal 2 karakter.';
      if (password && password.length < 8) errs.password = 'Kata sandi minimal 8 karakter.';
      else if (password && !(/[A-Za-z]/.test(password) && /\d/.test(password))) errs.password = 'Gunakan kombinasi huruf dan angka.';
      if (f.confirm.value !== password) errs.confirm = 'Kata sandi tidak sama.';
      body = { name, email, password };
    }
    if (Object.keys(errs).length) return showErrors(errs);

    setBusy(btn, true);
    try {
      const data = await post(mode, body);
      location.href = data.redirect || 'dashboard.php';
    } catch (err) {
      if (err.errors && Object.keys(err.errors).length) showErrors(err.errors);
      if (!err.errors || !Object.keys(err.errors).length || err.status === 429 || err.status === 401) showAlert(err.message);
      if (err.status === 419) setTimeout(() => location.reload(), 1200);
      setBusy(btn, false);
    }
  });

  /* ------------------------------- akun demo ------------------------------- */
  const demoBtn = document.getElementById('demoBtn');
  if (demoBtn) {
    demoBtn.addEventListener('click', async () => {
      clearErrors();
      setBusy(demoBtn, true);
      try {
        const data = await post('demo');
        location.href = data.redirect || 'dashboard.php';
      } catch (err) {
        showAlert(err.message);
        setBusy(demoBtn, false);
      }
    });
  }
})();
