/* auth pages: phone formatting + validation.
   NOTE: submits are client-side stubs for now — real backend (PHP) wires in later. */

function fmtPhone(input) {
  // keep +998 prefix, allow up to 9 digits, group as +998 90 123 45 67
  input.addEventListener('input', () => {
    let d = input.value.replace(/\D/g, '');
    if (d.startsWith('998')) d = d.slice(3);
    d = d.slice(0, 9);
    let out = '+998';
    if (d.length) out += ' ' + d.slice(0, 2);
    if (d.length > 2) out += ' ' + d.slice(2, 5);
    if (d.length > 5) out += ' ' + d.slice(5, 7);
    if (d.length > 7) out += ' ' + d.slice(7, 9);
    input.value = out + (d.length === 0 ? ' ' : '');
  });
  input.addEventListener('focus', () => { if (!input.value.trim()) input.value = '+998 '; });
}

function phoneDigits(v) {
  let d = (v || '').replace(/\D/g, '');
  if (d.startsWith('998')) d = d.slice(3);
  return d;
}

function setInvalid(fieldId, bad) {
  const f = document.getElementById(fieldId);
  if (!f) return;
  f.classList.toggle('invalid', bad);
  const inp = f.querySelector('input');
  if (inp) inp.classList.toggle('err', bad);
}

function initRegister() {
  const form = document.getElementById('regForm');
  const phone = document.getElementById('phone');
  if (phone) fmtPhone(phone);

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const v = (n) => form.elements[n].value.trim();
    let ok = true;
    const bad = (id) => { setInvalid(id, true); ok = false; };
    ['f-name','f-email','f-phone','f-pw','f-pw2'].forEach(id => setInvalid(id, false));

    if (v('name').length < 2) bad('f-name');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'))) bad('f-email');
    if (phoneDigits(v('phone')).length !== 9) bad('f-phone');
    if (v('password').length < 8) bad('f-pw');
    if (v('password') !== v('password2')) bad('f-pw2');

    if (!ok) {
      const first = form.querySelector('.field.invalid input');
      if (first) first.focus();
      return;
    }

    const dict = (window.I18N && window.I18N[window.getLang()]) || {};
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    fetch('/api/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: v('name'), email: v('email'),
        phone: '+998' + phoneDigits(v('phone')), password: v('password')
      })
    }).then(async (r) => {
      const data = await r.json().catch(() => ({}));
      if (r.ok && data.ok && data.verify) {
        const card = form;
        card.innerHTML = '<div style="text-align:center;padding:8px 4px">'
          + '<div style="width:64px;height:64px;border-radius:16px;background:#eef3fb;display:grid;place-items:center;margin:0 auto 18px">'
          + '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#16324f" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div>'
          + '<h1 style="font-size:1.4rem">' + (dict['auth.verify.title'] || 'Проверьте почту') + '</h1>'
          + '<p style="color:#5b6b7d;margin-top:10px">' + (dict['auth.verify.sent'] || '') + '</p></div>';
        return;
      }
      if (r.ok && data.ok) { window.location.href = '/application.php'; return; }
      if (r.status === 409 || (data.errors && data.errors.email === 'exists')) {
        setInvalid('f-email', true);
        const m = document.querySelector('#f-email .msg');
        if (m) { m.removeAttribute('data-i18n'); m.textContent = dict['auth.err.email_exists'] || 'Email exists'; }
      } else if (data.errors) {
        Object.keys(data.errors).forEach(k => setInvalid('f-' + (k === 'password' ? 'pw' : k), true));
      } else {
        alert(dict['auth.err.network'] || 'Network error');
      }
      btn.disabled = false;
    }).catch(() => { alert(dict['auth.err.network'] || 'Network error'); btn.disabled = false; });
  });
}

function initForgot() {
  const form = document.getElementById('forgotForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const dict = (window.I18N && window.I18N[window.getLang()]) || {};
    const email = form.elements['email'].value.trim();
    setInvalid('f-email', false);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setInvalid('f-email', true); document.getElementById('f-email').classList.add('invalid'); return; }
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    fetch('/api/forgot-password.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email })
    }).then(async () => {
      form.innerHTML = '<div style="text-align:center;padding:8px 4px">'
        + '<div style="width:64px;height:64px;border-radius:16px;background:#eef3fb;display:grid;place-items:center;margin:0 auto 18px">'
        + '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#16324f" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div>'
        + '<h1 style="font-size:1.4rem">' + (dict['auth.forgot.sent.title'] || 'Проверьте почту') + '</h1>'
        + '<p style="color:#5b6b7d;margin:10px 0 18px">' + (dict['auth.forgot.sent'] || '') + '</p>'
        + '<a class="btn btn-ghost btn-block" href="login.html">' + (dict['auth.forgot.back'] || 'Ко входу') + '</a></div>';
    }).catch(() => { alert(dict['auth.err.network'] || 'Ошибка сети'); btn.disabled = false; });
  });
}

function initLogin() {
  const form = document.getElementById('loginForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const v = (n) => form.elements[n].value.trim();
    let ok = true;
    setInvalid('f-email', false); setInvalid('f-pw', false);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'))) { setInvalid('f-email', true); ok = false; }
    if (v('password').length < 1) { setInvalid('f-pw', true); ok = false; }
    if (!ok) return;
    const dict = (window.I18N && window.I18N[window.getLang()]) || {};
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    fetch('/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: v('email'), password: v('password') })
    }).then(async (r) => {
      const data = await r.json().catch(() => ({}));
      if (r.ok && data.ok) { window.location.href = '/dashboard.php'; return; }
      setInvalid('f-email', true); setInvalid('f-pw', true);
      const m = document.querySelector('#f-email .msg');
      const txt = (r.status === 403 || data.error === 'unverified')
        ? (dict['auth.err.unverified'] || 'Подтвердите email')
        : (dict['auth.err.login'] || 'Invalid credentials');
      if (m) { m.removeAttribute('data-i18n'); m.textContent = txt; }
      document.getElementById('f-email').classList.add('invalid');
      btn.disabled = false;
    }).catch(() => { alert(dict['auth.err.network'] || 'Network error'); btn.disabled = false; });
  });
}
