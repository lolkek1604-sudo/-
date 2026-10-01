/* Onboarding questionnaire: conditional reveals, per-question uploads, validation.
   Uploads/data are held client-side for now; the PHP backend will receive them later. */
(function () {
  const form = document.getElementById('obForm');
  if (!form) return;
  const files = {}; // key -> File

  // ---------- conditional visibility ----------
  function radioVal(name) {
    const el = form.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : null;
  }
  function isVisible(el) { return !!(el.offsetParent !== null); }

  function refresh() {
    document.querySelectorAll('[data-show-when]').forEach(el => {
      const [name, val] = el.getAttribute('data-show-when').split('=');
      const show = radioVal(name) === val;
      el.classList.toggle('hide', !show);
      if (!show) {
        // clear inputs inside a hidden block so stale answers aren't submitted
        el.querySelectorAll('input[type="text"],textarea').forEach(i => i.value = '');
        el.querySelectorAll('input[type="radio"]').forEach(i => i.checked = false);
      }
    });
  }
  form.addEventListener('change', (e) => { if (e.target.type === 'radio') refresh(); });

  // ---------- uploads ----------
  const uploadHTML = (key) => `
    <div class="upload" data-u="${key}">
      <div class="u-ic" data-thumb>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 16l4-4 4 4 4-5 4 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="9" r="1.4" fill="currentColor"/></svg>
      </div>
      <div class="u-info">
        <div class="u-name" data-name data-i18n="ob.up.hint">JPG, PNG или PDF, до 10 МБ</div>
        <div class="u-hint" data-sub></div>
      </div>
      <button type="button" class="u-btn" data-i18n="ob.up.btn">Загрузить фото</button>
      <input type="file" accept="image/*,application/pdf">
    </div>`;

  document.querySelectorAll('[data-upload]').forEach(box => {
    const key = box.getAttribute('data-upload');
    box.innerHTML = uploadHTML(key);
    const card = box.querySelector('.upload');
    const input = box.querySelector('input[type=file]');
    const btn = box.querySelector('.u-btn');
    const nameEl = box.querySelector('[data-name]');
    const thumb = box.querySelector('[data-thumb]');

    btn.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      const f = input.files[0];
      if (!f) return;
      if (f.size > 10 * 1024 * 1024) { alert('Max 10 MB'); input.value = ''; return; }
      files[key] = f;
      card.classList.add('filled', 'err-clear');
      card.classList.remove('err');
      nameEl.removeAttribute('data-i18n');
      nameEl.textContent = f.name;
      btn.textContent = (window.I18N[window.getLang()] || {})['ob.up.change'] || 'Изменить фото';
      btn.removeAttribute('data-i18n');
      if (f.type.startsWith('image/')) {
        const url = URL.createObjectURL(f);
        thumb.innerHTML = '<img src="' + url + '" alt="">';
      } else {
        thumb.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M7 3h7l5 5v13H7z" stroke="#16324f" stroke-width="1.6"/><path d="M14 3v5h5" stroke="#16324f" stroke-width="1.6"/></svg>';
      }
    });
  });

  // ---------- validation ----------
  function markErr(node, bad) {
    if (!node) return;
    node.classList.toggle('err', bad);
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    let ok = true;
    const errBox = document.getElementById('obError');

    // yes/no groups (only when visible)
    document.querySelectorAll('[data-required-yn]').forEach(q => {
      if (!isVisible(q)) return;
      const name = q.getAttribute('data-required-yn');
      const yn = q.querySelector('[data-yn]');
      const bad = !radioVal(name);
      markErr(yn, bad);
      if (bad) ok = false;
      // visible detail input tied to this group
      q.querySelectorAll('.q-detail:not(.hide) input, .q-detail:not(.hide) textarea').forEach(inp => {
        const empty = !inp.value.trim();
        inp.classList.toggle('err', empty);
        if (empty) ok = false;
      });
    });

    // marital radio
    document.querySelectorAll('[data-required-radio]').forEach(q => {
      const name = q.getAttribute('data-required-radio');
      const rl = q.querySelector('[data-radio]');
      const bad = !radioVal(name);
      markErr(rl, bad);
      if (bad) ok = false;
    });

    // job text
    document.querySelectorAll('[data-required-text]').forEach(q => {
      const inp = q.querySelector('input');
      const bad = !inp.value.trim();
      inp.classList.toggle('err', bad);
      if (bad) ok = false;
    });

    // uploads
    document.querySelectorAll('[data-upload]').forEach(box => {
      const key = box.getAttribute('data-upload');
      const card = box.querySelector('.upload');
      const bad = !files[key];
      card.classList.toggle('err', bad);
      if (bad) ok = false;
    });

    if (!ok) {
      errBox.classList.add('show');
      const first = form.querySelector('.err');
      if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    errBox.classList.remove('show');

    // build multipart payload: answers + document files
    const fd = new FormData(form);
    Object.keys(files).forEach(k => fd.append(k, files[k], files[k].name));

    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    fetch('/api/submit-onboarding.php', { method: 'POST', body: fd })
      .then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (r.status === 401) { window.location.href = '/auth/login.html'; return; }
        if (r.ok && data.ok) { window.location.href = '/application.php'; return; }
        const dict = (window.I18N && window.I18N[window.getLang()]) || {};
        alert(dict['auth.err.network'] || 'Ошибка. Попробуйте ещё раз.');
        btn.disabled = false;
      })
      .catch(() => { btn.disabled = false; alert('Ошибка сети'); });
  });

  refresh();
})();
