# Gateway to Dreams — установка

Полноценный сайт визового сервиса: лендинг, регистрация/вход, анкета с загрузкой документов,
личный кабинет, двухэтапная оплата (переводы карта→карта) с подтверждением через Telegram-бота,
и админка, где настраивается **всё**. RU/UZ, узбекский сум, курс задаётся вручную.

## Требования
- PHP **7.4+** (или 8.x) с расширениями: `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`.
- Никакой отдельной базы данных настраивать не нужно — используется SQLite, база создаётся сама.

## Установка на FASTPANEL (кратко)
1. **Создайте сайт** для домена `gatewaytodreams.info`, тип **PHP**. Запомните корневую папку сайта
   (обычно `/var/www/<пользователь>/data/www/gatewaytodreams.info`).
2. **Загрузите содержимое архива** в эту корневую папку (чтобы `index.html`, `admin/`, `api/`,
   `includes/`, `assets/`, `data/` лежали прямо в корне сайта).
3. **Права на запись** для папки `data` и `data/uploads`:
   ```
   chmod -R 775 data
   ```
   (если 775 не хватает — 777). Здесь хранятся база и загруженные файлы.
4. Откройте сайт в браузере — база данных создастся автоматически при первом заходе.
5. **HTTPS**: домен за Cloudflare — включите SSL (режим Full). Валидный HTTPS обязателен для
   Telegram-webhook (Cloudflare его обеспечивает).

## Настройка через админку
Откройте `https://gatewaytodreams.info/admin/`
Логин по умолчанию: **admin** / **admin123** → **сразу смените пароль** (вкладка «Настройки»).

Во вкладке **Настройки**:
- **Курс USD → UZS (Рапира)** — впишите текущий курс; суммы в сумах считаются автоматически.
- **Суммы**: первый платёж (по умолчанию $205), второй ($350), задержка второго (5 дней).
- **Контакт менеджера** — показывается клиенту при отказе оплаты.

**Telegram-бот** (тоже во вкладке «Настройки»):
1. Создайте бота у **@BotFather**, получите **токен** → вставьте в поле «Токен бота».
2. Напишите своему боту команду **/id** — он пришлёт ваш **chat_id** → вставьте в поле «Chat ID».
3. Нажмите **«Проверить связь»** (в чат придёт тестовое сообщение).
4. Нажмите **«Установить webhook»** — после этого кнопки ✅/❌ в чате начнут работать.

Во вкладке **Банки**:
- Отметьте банки, которые показывать на сайте, впишите **номер карты** и **получателя**.
- При желании загрузите **свой логотип** банка (по умолчанию стоит фирменный SVG).

## Как работает оплата
1. Клиент регистрируется → заполняет анкету (с загрузкой документов).
2. В кабинете видит **первый платёж** (сумма в сумах). Выбирает банк → видит реквизиты →
   переводит → загружает **скриншот оплаты**.
3. Скриншот моментально приходит вам в **Telegram** с кнопками ✅/❌.
   - **✅** → у клиента на сайте появляется «Оплата успешно принята».
   - **❌** → «Оплата отклонена» + просьба написать менеджеру.
   (То же можно делать прямо в админке во вкладке «Оплаты».)
4. После подтверждения первого платежа **второй платёж** появится у клиента **ровно через 5 дней**
   (число дней настраивается). До этого он скрыт/заблокирован.

## Что где
```
index.html            лендинг (RU/UZ)
auth/                 регистрация, вход, анкета
dashboard.php         личный кабинет клиента
admin/                админ-панель (всё управление)
api/                  бэкенд-эндпоинты + telegram-webhook.php
includes/             ядро (конфиг, БД, telegram) — закрыто от веба
assets/               стили, скрипты, логотипы банков
data/                 база SQLite + загрузки (должна быть с правом записи, закрыта от веба)
```

## Безопасность
- Обязательно смените пароль администратора после первого входа.
- Папка `data/` закрыта от веба через `.htaccess` (Apache/LiteSpeed).
  Если сервер на **чистом Nginx**, добавьте в конфиг сайта:
  ```
  location ^~ /data/ { deny all; }
  location ^~ /includes/ { deny all; }
  location ~* /data/uploads/.*\.(php|phtml|cgi|pl|py)$ { deny all; }
  ```
  (папку `data/uploads/` при этом отдавать нужно — там логотипы и файлы, но без исполнения скриптов.)

## PayRam

The payment page now uses PayRam instead of bank-transfer screenshots.

1. Deploy this site and ensure PHP 7.4+/8.x has `pdo_sqlite`, `sqlite3` and `curl` enabled.
2. Deploy your own PayRam node and enable the Card-to-Crypto payment channel in PayRam.
3. Open `/admin/settings.php` and configure **PayRam — Card → Crypto**:
   - PayRam URL
   - Merchant API key
   - Webhook shared secret
   - EUR → USD display rate
4. In PayRam register this webhook URL:

   `https://YOUR-DOMAIN/api/payram-webhook.php`

5. Enable PayRam in the admin settings.
6. Log in as a client, accept the contract and open `/payment.php`.
7. Click **Оплатить картой**. The backend creates `POST /api/v1/payment`; the returned checkout URL is embedded in the page where the PayRam host permits framing. A fallback link is shown if framing is blocked.

### Important

The merchant API key never needs to be sent by your own frontend: `/api/payram-create.php` and `/api/payram-status.php` call PayRam server-to-server with the `API-Key` header. PayRam's current merchant API uses `customerEmail`, `customerID` and `amountInUSD`, and returns `url` + `reference_id`. Webhooks are authenticated with the configured shared secret in the `API-Key` header and should be treated idempotently. See the current PayRam integration reference for the exact contract. 


## PayRam Core 3.8.x

This build uses PayRam's official `payram-add-credit-v1.js` widget mounted inside the payment page. The site reserves a local `reference_id` first and passes it to the widget as `data-reference-id`; PayRam then sends payment status to `/api/payram-webhook.php`.

### PayRam settings

In Admin → Settings → PayRam:
- PayRam URL: your self-hosted PayRam node, for example `https://payram.example.com`
- Merchant API key: project/merchant API key
- Webhook shared secret: the webhook access key configured in PayRam
- Enable PayRam

Register this public HTTPS endpoint in the PayRam project:
`https://YOUR-DOMAIN/api/payram-webhook.php`

### Current webhook authentication

PayRam Core 3.8.x sends the webhook shared secret in the `API-Key` header. The receiver compares it with `hash_equals()`. Current PayRam documentation does **not** use `X-PayRam-Signature` for merchant payment webhooks. This build additionally accepts `X-PayRam-Signature: sha256=<hex>` if an upstream/proxy or future PayRam deployment provides it; that path is HMAC-SHA256 over the raw request body.

### Widget

The official widget is loaded from:
`https://payram.com/widget/payram-add-credit-v1.js`

It is mounted directly inside the user's payment page. The merchant API key is required by the official widget and is therefore visible to the browser; use a dedicated PayRam project key for this site. If you require that no merchant key ever reaches the browser, use the server-only REST/hosted-checkout integration instead of the official widget.

### Production checklist

1. PayRam Core 3.8.x is installed and reachable over HTTPS.
2. Project API key is configured.
3. Card/on-ramp payment options are enabled in PayRam where available.
4. Wallets and supported settlement chains/tokens are configured.
5. Webhook URL above is registered and active.
6. Webhook shared secret in this site exactly matches PayRam.
7. Testnet payment succeeds end-to-end before mainnet.
8. After switching to mainnet, make one small real payment and verify the `FILLED` webhook before opening the service to customers.
