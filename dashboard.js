document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('[data-payram-start]');
    var statusBox = document.querySelector('[data-payram-status]');
    var frameWrap = document.querySelector('[data-payram-frame]');

    function say(kind, text) {
        if (!statusBox) return;
        statusBox.textContent = '';
        var box = document.createElement('div');
        box.className = 'result-box ' + kind;
        var inner = document.createElement('div');
        inner.textContent = text;
        box.appendChild(inner);
        statusBox.appendChild(box);
    }

    function openFrame(url, button) {
        frameWrap.textContent = '';
        var bar = document.createElement('div');
        bar.className = 'pf-bar';
        var left = document.createElement('span');
        left.textContent = 'Защищённая оплата картой';
        var right = document.createElement('span');
        var alt = document.createElement('a');
        alt.href = url; alt.target = '_blank'; alt.rel = 'noopener';
        alt.textContent = 'Не открывается? Открыть в новом окне';
        right.appendChild(alt);
        bar.appendChild(left); bar.appendChild(right);
        var f = document.createElement('iframe');
        f.src = url;
        f.setAttribute('allow', 'payment *; camera *; microphone *; clipboard-write *; fullscreen *');
        f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        f.title = 'Оплата';
        frameWrap.appendChild(bar); frameWrap.appendChild(f);
        frameWrap.hidden = false;
        button.style.display = 'none';
        frameWrap.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', async function (event) {
            event.preventDefault();
            var label = button.textContent;
            button.disabled = true;
            button.textContent = 'Создаём платёж...';
            say('await', 'Подготавливаем оплату...');
            try {
                var response = await fetch('/api/payram-create.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({stage: button.dataset.stage})
                });
                var text = await response.text();
                var data;
                try { data = JSON.parse(text); }
                catch (e) { throw new Error('Сервер вернул некорректный ответ (HTTP ' + response.status + ')'); }
                if (!response.ok || !data.ok) throw new Error(data.message || data.error || 'Ошибка создания платежа');
                if (!data.url) throw new Error('Не получена ссылка на оплату');

                if (data.mode === 'redirect' || !frameWrap) {
                    say('ok', 'Переходим к оплате...');
                    window.location.href = data.url;
                    return;
                }
                say('await', 'Ожидаем оплату. После подтверждения страница обновится автоматически.');
                openFrame(data.url, button);
            } catch (error) {
                console.error('Payment error:', error);
                say('no', 'Ошибка: ' + (error.message || error));
                button.disabled = false;
                button.textContent = label;
            }
        });
    });

    /* Проверка статуса каждые 5 секунд */
    var stageContainer = document.querySelector('[data-stage]');
    if (stageContainer) {
        var stage = stageContainer.dataset.stage;
        var timer = setInterval(async function () {
            try {
                var r = await fetch('/api/payram-status.php?stage=' + encodeURIComponent(stage), {credentials: 'same-origin', cache: 'no-store'});
                var d = await r.json();
                if (d.ok && d.status === 'approved') {
                    clearInterval(timer);
                    say('ok', 'Оплата подтверждена.');
                    setTimeout(function () { window.location.reload(); }, 1000);
                }
            } catch (e) { /* сеть моргнула — повторим */ }
        }, 5000);
    }
});
