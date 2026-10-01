<?php
require_once __DIR__ . '/includes/portal.php';
$pdo = db();

// --- auth + data + POST handling BEFORE any output (so redirect works) ---
require_user_redirect('/auth/login.html');
$u = current_user();

$st = $pdo->prepare("SELECT * FROM applications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$u['id']]);
$app = $st->fetch();

// accept action — must run before headers are sent
if ($app && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'accept' && csrf_check($_POST['csrf'] ?? '')) {
    if (empty($app['contract_accepted_at'])) {
        $pdo->prepare("UPDATE applications SET contract_accepted_at=datetime('now') WHERE id=?")->execute([$app['id']]);
    }
    header('Location: /payment.php'); exit;
}

// --- now render the page ---
portal_head('contract', 'Договор');

if (!$app) { ?>
  <h1 class="page-h1" data-i18n="contract.title">Договор на оказание услуг</h1>
  <div class="panel" style="text-align:center;padding:44px">
    <p class="muted" style="margin-bottom:18px" data-i18n="contract.need.appl">Сначала заполните анкету — договор сформируется автоматически.</p>
    <a class="btn btn-primary" href="/application.php" data-i18n="contract.goappl">Заполнить анкету</a>
  </div>
  <?php portal_foot(); exit;
}

$ans = json_decode($app['data_json'], true) ?: [];
$passport = trim($ans['passport'] ?? '') ?: '—';
$dobRaw = trim($ans['dob'] ?? '');
$dob = $dobRaw ? date('d.m.Y', strtotime($dobRaw)) : '—';
$clientName = $u['name'];
$accepted = !empty($app['contract_accepted_at']);
$s1 = payment_view(get_payment($u['id'], 1));
$isPaid = $s1 && $s1['status'] === 'approved';

$coName = setting('co_name', 'Gateway to Dreams');
$coDir = trim((string)setting('co_director')) ?: '[Rahbarning lavozimi, F.I.Sh.]';
$coBasis = trim((string)setting('co_basis')) ?: 'Nizom';
$coReq = trim((string)setting('co_requisites'));
$city = setting('contract_city', 'Toshkent sh.');
$sealFile = setting('co_seal_file');
$signFile = setting('co_signature_file');

// Uzbek date
$uzMonths = [1=>'yanvar',2=>'fevral',3=>'mart',4=>'aprel',5=>'may',6=>'iyun',7=>'iyul',8=>'avgust',9=>'sentabr',10=>'oktabr',11=>'noyabr',12=>'dekabr'];
$ts = strtotime($app['created_at']);
$dateStr = date('Y', $ts) . '-yil ' . (int)date('j', $ts) . '-' . $uzMonths[(int)date('n', $ts)];
$contractNo = 'GTD-' . date('Y', $ts) . '-' . str_pad($app['id'], 4, '0', STR_PAD_LEFT);
?>
<style>
@media print {
  .app-side, .no-print { display: none !important; }
  .app-main { padding: 0 !important; }
  .app-shell { display: block !important; }
  .contract-doc { border: none !important; box-shadow: none !important; }
  body { background: #fff !important; }
  .stamp-area { border: none !important; }
  .stamp-area .mp { display: none !important; }
  .stamp-area .sig, .stamp-area .seal { display: block !important; }
}
.contract-doc { background:#fff; border:1px solid var(--border); border-radius:14px; padding:44px 48px; box-shadow:var(--shadow-sm); line-height:1.6; color:#1a2733; }
.contract-doc h2.ct { text-align:center; font-size:1.2rem; margin-bottom:4px; }
.contract-doc .ct-sub { text-align:center; color:var(--muted); margin-bottom:22px; font-size:.95rem; }
.contract-doc h3 { font-size:1rem; margin:20px 0 8px; }
.contract-doc p { margin:8px 0; font-size:.95rem; }
.contract-doc .party { background:var(--soft); border-radius:10px; padding:14px 16px; margin:14px 0; }
.contract-doc .fill { background:#fff6d6; padding:0 4px; border-radius:3px; font-weight:600; }
.contract-doc ul { margin:8px 0; padding-left:22px; font-size:.95rem; }
.contract-doc .sign { display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-top:30px; }
.contract-doc .sign .blk { font-size:.92rem; }
.contract-doc .sign .blk b { display:block; margin-bottom:6px; }
.sign-cap { border-top:1px solid #9fb0c0; margin-top:10px; padding-top:6px; color:var(--muted); font-size:.85rem; max-width:230px; }
/* место печати рядом с ФИО — на экране пустой бокс, в PDF туда ложатся подпись+печать */
.stamp-area { position:relative; width:190px; height:120px; border:1px dashed #c4cfda; border-radius:8px; display:grid; place-items:center; margin:8px 0 2px; }
.stamp-area .mp { color:#98a6b5; font-size:.8rem; letter-spacing:.02em; }
.stamp-area .sig, .stamp-area .seal { display:none; position:absolute; }
.stamp-area .sig { left:6px; bottom:16px; max-height:56px; max-width:160px; }
.stamp-area .seal { left:52px; top:2px; max-height:116px; opacity:.92; mix-blend-mode:multiply; }
@media(max-width:640px){ .contract-doc{padding:24px 20px} .contract-doc .sign{grid-template-columns:1fr} }
</style>

<div class="no-print" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:6px">
  <div>
    <h1 class="page-h1" data-i18n="contract.title">Договор на оказание услуг</h1>
    <p class="page-sub" data-i18n="contract.sub">Проверьте данные и примите условия перед оплатой.</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;margin-top:6px">
    <?php if ($accepted): ?><span class="tag ok" data-i18n="contract.accepted">Договор принят</span><?php endif; ?>
    <button class="btn btn-ghost" onclick="window.print()" data-i18n="contract.download">Скачать / Печать PDF</button>
  </div>
</div>

<div class="contract-doc" id="contractDoc">
  <h2 class="ct">KONSALTING VA YURIDIK XIZMATLAR KO‘RSATISH SHARTNOMASI</h2>
  <p class="ct-sub">(H-1B Cap-Exempt dasturi bo‘yicha immigratsiya jarayonini jadallashtirish)<br>№ <?= e($contractNo) ?></p>
  <p style="display:flex;justify-content:space-between"><span><?= e($city) ?></span><span><?= e($dateStr) ?></span></p>

  <p>Bir tomondan, <?= e($coBasis) ?> asosida ish yurituvchi, keyingi o‘rinlarda «Ijrochi» deb yuritiladigan «<?= e($coName) ?>» uning <?= e($coDir) ?> timsolida, va ikkinchi tomondan, keyingi o‘rinlarda «Buyurtmachi» deb yuritiladigan:</p>
  <div class="party">
    <p style="margin:0"><b>Buyurtmachi:</b> <span class="fill"><?= e($clientName) ?></span>, pasport <span class="fill"><?= e($passport) ?></span>, tug‘ilgan sana <span class="fill"><?= e($dob) ?></span>.</p>
  </div>
  <p>birgalikda «Tomonlar» deb yuritiluvchilar, ushbu Shartnomani quyidagilar to‘g‘risida tuzdilar:</p>

  <h3>1. SHARTNOMA PREDMETI</h3>
  <p>1.1. Ijrochi Buyurtmachining topshirig‘iga binoan, AQSh Fuqarolik va immigratsiya xizmatiga (USCIS) I-129 shaklidagi (H-1B toifasidagi viza) immigratsiya petitsiyasini topshirish jarayonini tayyorlash va kuzatib borishga qaratilgan konsalting, rekruting va yuridik xizmatlar majmuasini ko‘rsatish majburiyatini oladi.</p>
  <p>1.2. Ushbu Shartnoma bo‘yicha ko‘rsatiladigan xizmatlarning o‘ziga xosligi yillik kvotalardan ozod qilinganlik (Cap-Exempt) maqomiga ega bo‘lgan Ish beruvchini tanlab berishdan iboratdir. Buyurtmachi AQSh hududidagi faqatgina nodavlat notijorat tashkilotlari (Non-profit organizations), oliy ta’lim muassasalari yoki akkreditatsiyadan o‘tgan tadqiqot markazlari Ish beruvchi sifatida ishtirok etishi haqida xabardor qilingan.</p>
  <p>1.3. Buyurtmachi Ijrochining xizmatlari uchun ushbu Shartnomada nazarda tutilgan tartibda va shartlarda haq to‘lash majburiyatini oladi.</p>

  <h3>2. XIZMAT KO‘RSATISH TARTIBI, BOSQICHLARI VA MUDDATLARI</h3>
  <p>Tomonlar Shartnomani amalga oshirishning quyidagi bosqichlari va taxminiy muddatlarini kelishib oldilar, ularning hisobi Buyurtmachi tomonidan to‘lov majburiyatlari bajarilgan paytdan boshlanadi:</p>
  <ul>
    <li><b>1-bosqich.</b> Hujjatlarni rasmiylashtirish va yurist xizmatlari uchun to‘lov (Muddat: 10 kungacha): Buyurtmachi barcha xizmatlar uchun 10 kun ichida to‘lovni amalga oshirish majburiyatini oladi.</li>
    <li><b>2-bosqich.</b> Profil yaratish va ma’lumotlarni ochish (Muddat: 1 oygacha): Ijrochi Buyurtmachining hujjatlarini auditdan o‘tkazadi, rekruting uchun texnik topshiriq (TT) tuzadi.</li>
    <li><b>3-bosqich.</b> Job Offer (Ish taklifi)ni imzolash va LCA olish (Muddat: 3-4 oy): Ijrochining yuridik bo‘limi hujjatlar paketini shakllantiradi, AQSh Mehnat vazirligiga (DOL) LCA uchun ariza topshiradi.</li>
    <li><b>4-bosqich.</b> USCIS’ga I-129 shaklini topshirish (Muddat: 15-30 kun). Premium Processing opsiyasida — 15 kalendar kungacha.</li>
  </ul>

  <h3>3. TOMONLARNING HUQUQ VA MAJBURIYATLARI</h3>
  <p>3.1. Ijrochi majburiyatlari: AQSh davlat organlariga hujjatlar yuborilishidan oldin rasmiy Job Offer’ni Buyurtmachiga taqdim etish; shaxsiy hujjatlarni (diplomlar, rezyume, tavsiyanomalar) yig‘ish bo‘yicha maslahat berish; har bir bosqich borishi haqida o‘z vaqtida xabardor qilish; xizmatlar ko‘rsatilishini kafolatlash.</p>
  <p>3.2. Buyurtmachi majburiyatlari: o‘z ma’lumoti, ish tajribasi, sudlanmaganligi va immigratsiya rejimini buzmaganligi to‘g‘risida haqiqiy va to‘liq ma’lumot berish; taqdim etilgan Job Offer’ni 5 (besh) ish kuni ichida ko‘rib chiqish va imzolash yoki asoslantirilgan yozma rad javobini berish.</p>

  <h3>4. ISH BERUVCHINI KELISHISH VA UNDAN VOZ KECHISH QOIDALARI</h3>
  <p>4.1. Taklif etilgan Ish beruvchi TTga to‘liq mos kelsa, biroq Buyurtmachi shaxsiy sabablarga ko‘ra Job Offer’ni imzolashdan bosh tortsa, Ijrochi ko‘pi bilan 2 (ikki) ta muqobil variant taqdim etadi.</p>
  <p>4.2. TTga mos keladigan, ketma-ket taklif qilingan 3 (uch) ta variantning rad etilishi Buyurtmachining tashabbusi bilan Shartnomadan bir tomonlama voz kechish sifatida talqin etiladi. Bunday holda xizmatlar to‘liq ko‘rsatilgan hisoblanadi, to‘langan mablag‘lar qaytarilmaydi.</p>

  <h3>5. MABLAG‘LARNI QAYTARISH VA KAFOLATLAR (REFUND POLICY)</h3>
  <p>5.1. USCIS tomonidan Ijrochining yuridik bo‘limi tomonidan keys tayyorlash sifati bilan bog‘liq sabablarga ko‘ra I-129 rasman rad etilsa (Denial), Ijrochi Buyurtmachi tanloviga ko‘ra: o‘z hisobidan keysni tuzatib qayta topshiradi yoki to‘langan mablag‘ni 14 ish kuni ichida qaytaradi.</p>
  <p>5.2. Agar rad javobi Buyurtmachining o‘z harakatlari yoki o‘tmishi tufayli bo‘lsa (qalbaki hujjatlar, sudlanganlik/deportatsiya/avvalgi rad etishlarni yashirish, 30 kalendar kundan ortiq aloqaga chiqmaslik) — mablag‘ qaytarilmaydi.</p>

  <h3>6. TOMONLARNING JAVOBGARLIGI VA MAXFIYLIK</h3>
  <p>6.1. Buyurtmachi Ijrochini chetlab o‘tib Ish beruvchi bilan to‘g‘ridan-to‘g‘ri aloqaga kirishmaslik va tijorat ma’lumotlarini oshkor qilmaslik majburiyatini oladi. Buzilgan taqdirda — Shartnoma qiymatining 100% miqdorida jarima.</p>
  <p>6.2. Ijrochi AQSh davlat organlari (USCIS, Mehnat vazirligi, Elchixonalar) qarorlari uchun javobgar emas.</p>

  <h3>7. TOMONLARNING IMZOLARI</h3>
  <div class="sign">
    <div class="blk">
      <b>Ijrochi:</b>
      «<?= e($coName) ?>», <?= e($coDir) ?>
      <?= $coReq ? '<div class="muted" style="font-size:.85rem;margin-top:4px">'.nl2br(e($coReq)).'</div>' : '' ?>
      <div class="stamp-area">
        <span class="mp">M.O‘. (Muhr o‘rni)</span>
        <?php if ($signFile): ?><img class="sig" src="/data/uploads/<?= e($signFile) ?>" alt=""><?php endif; ?>
        <?php if ($sealFile): ?><img class="seal" src="/data/uploads/<?= e($sealFile) ?>" alt=""><?php endif; ?>
      </div>
      <div class="sign-cap">____________________ (Imzo / Muhr)</div>
    </div>
    <div class="blk">
      <b>Buyurtmachi:</b>
      <?= e($clientName) ?><br>Pasport: <?= e($passport) ?><br>Tug‘ilgan sana: <?= e($dob) ?>
      <div class="sign-cap" style="margin-top:132px">____________________ (Imzo)</div>
    </div>
  </div>
</div>

<?php if (!$accepted): ?>
<form class="panel no-print" method="post" style="margin-top:20px" id="acceptForm">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="accept">
  <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin-bottom:16px">
    <input type="checkbox" id="acceptChk" style="margin-top:3px;width:18px;height:18px">
    <span data-i18n="contract.accept">Я прочитал(а) и принимаю условия договора</span>
  </label>
  <button class="btn btn-primary btn-lg" type="submit" id="acceptBtn" disabled data-i18n="contract.accept.btn">Принять и перейти к оплате</button>
</form>
<script>
  var chk=document.getElementById('acceptChk'), b=document.getElementById('acceptBtn');
  chk.addEventListener('change',function(){ b.disabled=!chk.checked; });
</script>
<?php else: ?>
<div class="panel no-print" style="margin-top:20px">
  <p class="muted" style="margin-bottom:14px"><span data-i18n="contract.accepted.at">Принят</span>: <?= e(date('d.m.Y H:i', strtotime($app['contract_accepted_at']))) ?></p>
  <?php if ($isPaid): ?>
    <span class="tag ok" style="font-size:.95rem;padding:.5rem 1rem">✓ <span data-i18n="contract.paid">Оплачено</span></span>
  <?php else: ?>
    <a class="btn btn-primary" href="/payment.php" data-i18n="portal.payment">Оплата</a>
  <?php endif; ?>
</div>
<?php endif;
portal_foot();
