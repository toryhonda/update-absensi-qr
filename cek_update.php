<?php
$currentVersion = "4.0.4";

$json_url = "https://raw.githubusercontent.com/toryhonda/update-absensi-qr/main/version.json?t=" . time();
$remote_data = @file_get_contents($json_url);
$data = $remote_data ? json_decode($remote_data, true) : null;

$latestVersion = $data['version'] ?? null;
$updateUrl = $data['url'] ?? '#';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cek Update Aplikasi</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    :root{
      --bg:#f6f8fb;
      --surface:#ffffff;
      --surface-2:#f9fafb;
      --line:#e8edf3;
      --line-strong:#dbe3ec;
      --ink:#0f172a;
      --ink-2:#334155;
      --muted:#64748b;
      --muted-2:#94a3b8;
      --brand:#2a5298;
      --brand-2:#1e3c72;
      --brand-soft:rgba(42,82,152,.08);
      --success:#10b981;
      --success-dark:#047857;
      --success-soft:rgba(16,185,129,.08);
      --success-line:rgba(16,185,129,.22);
      --danger:#ef4444;
      --danger-dark:#b91c1c;
      --danger-soft:rgba(239,68,68,.08);
      --danger-line:rgba(239,68,68,.22);
      --radius-xl:22px;
      --radius-lg:16px;
      --radius-md:12px;
      --radius-sm:10px;
      --shadow-sm:0 1px 2px rgba(15,23,42,.05);
      --shadow-md:0 6px 16px -8px rgba(15,23,42,.12), 0 2px 6px -2px rgba(15,23,42,.06);
      --shadow-lg:0 24px 50px -22px rgba(15,23,42,.28), 0 10px 22px -14px rgba(15,23,42,.14);
      --shadow-brand:0 14px 30px -14px rgba(30,60,114,.55);
      --shadow-success:0 14px 30px -14px rgba(16,185,129,.55);
    }

    *{box-sizing:border-box;}

    html,body{margin:0;padding:0;}

    body{
      font-family:'Rubik', system-ui, -apple-system, "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background:
        radial-gradient(circle at 0% 0%, rgba(42,82,152,.06), transparent 42%),
        radial-gradient(circle at 100% 100%, rgba(16,185,129,.05), transparent 42%),
        var(--bg);
      min-height:100vh;
      display:flex;
      justify-content:center;
      align-items:center;
      padding:28px 20px;
      color:var(--ink);
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
    }

    .card{
      background:var(--surface);
      border:1px solid var(--line);
      border-radius:var(--radius-xl);
      box-shadow:var(--shadow-lg);
      max-width:460px;
      width:100%;
      padding:38px 34px 32px;
      text-align:center;
      position:relative;
      overflow:hidden;
      animation:fadeUp .5s ease both;
    }

    .card::before{
      content:"";
      position:absolute;
      top:0;left:0;right:0;
      height:4px;
      background:linear-gradient(90deg, var(--brand), var(--brand-2));
    }

    .card.has-update::before{
      background:linear-gradient(90deg, #10b981, #047857);
    }

    .card.has-error::before{
      background:linear-gradient(90deg, #ef4444, #b91c1c);
    }

    @keyframes fadeUp{
      from{opacity:0; transform:translateY(10px);}
      to{opacity:1; transform:translateY(0);}
    }

    @keyframes fadeIn{
      from{opacity:0;}
      to{opacity:1;}
    }

    .icon-badge{
      width:66px;
      height:66px;
      border-radius:50%;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background:linear-gradient(135deg, var(--brand), var(--brand-2));
      color:#fff;
      font-size:26px;
      margin-bottom:18px;
      box-shadow:var(--shadow-brand);
    }

    .icon-badge.success{
      background:linear-gradient(135deg, #10b981, #047857);
      box-shadow:var(--shadow-success);
    }

    .icon-badge.danger{
      background:linear-gradient(135deg, #ef4444, #b91c1c);
      box-shadow:0 14px 30px -14px rgba(239,68,68,.55);
    }

    h2{
      font-size:18px;
      font-weight:600;
      letter-spacing:-.2px;
      color:var(--ink);
      margin:0 0 6px;
      line-height:1.35;
    }

    .subtitle{
      font-size:13px;
      color:var(--muted);
      margin:0 0 22px;
      line-height:1.55;
    }

    .status-block{
      display:flex;
      flex-direction:column;
      align-items:center;
      gap:14px;
      margin-bottom:24px;
    }

    .version-badges{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      flex-wrap:wrap;
    }

    .version-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:10px 18px;
      border-radius:var(--radius-md);
      font-size:14px;
      font-weight:600;
      letter-spacing:-.2px;
      line-height:1;
      border:1px solid;
    }

    .version-badge.current{
      background:var(--brand-soft);
      border-color:rgba(42,82,152,.18);
      color:var(--brand-2);
    }

    .version-badge.current i{
      font-size:12px;
      color:var(--brand);
    }

    .version-badge.latest{
      background:var(--success-soft);
      border-color:var(--success-line);
      color:var(--success-dark);
    }

    .version-badge.latest i{
      font-size:12px;
      color:var(--success);
    }

    .version-badge .label{
      font-size:11px;
      font-weight:500;
      letter-spacing:.4px;
      text-transform:uppercase;
      opacity:.75;
      margin-right:2px;
    }

    .version-arrow{
      color:var(--muted-2);
      font-size:13px;
    }

    .notice{
      display:flex;
      align-items:flex-start;
      gap:12px;
      padding:13px 16px;
      border-radius:var(--radius-md);
      font-size:13px;
      line-height:1.6;
      border:1px solid;
      text-align:left;
      animation:fadeUp .35s ease both;
    }

    .notice i{
      font-size:14px;
      margin-top:2px;
      flex-shrink:0;
      width:16px;
      text-align:center;
    }

    .notice.success{
      background:var(--success-soft);
      border-color:var(--success-line);
      color:var(--success-dark);
    }

    .notice.success i{color:var(--success);}

    .notice.danger{
      background:var(--danger-soft);
      border-color:var(--danger-line);
      color:var(--danger-dark);
    }

    .notice.danger i{color:var(--danger);}

    .notice strong{
      font-weight:600;
      color:inherit;
    }

    .notice small{
      display:block;
      margin-top:4px;
      font-size:12px;
      color:inherit;
      opacity:.78;
    }

    .actions{
      display:flex;
      flex-direction:column;
      gap:10px;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      padding:13px 20px;
      font-family:inherit;
      font-size:14.5px;
      font-weight:600;
      letter-spacing:.2px;
      border-radius:var(--radius-md);
      text-decoration:none;
      cursor:pointer;
      border:1px solid transparent;
      transition:transform .2s ease, box-shadow .2s ease, filter .2s ease, background .2s ease, border-color .2s ease, color .2s ease;
    }

    .btn i{font-size:13px;}

    .btn-success{
      color:#fff;
      background:linear-gradient(135deg, #10b981 0%, #047857 100%);
      box-shadow:var(--shadow-success);
    }

    .btn-success:hover{
      transform:translateY(-2px);
      filter:brightness(1.06);
      box-shadow:0 20px 36px -16px rgba(16,185,129,.65);
      color:#fff;
    }

    .btn-brand{
      color:#fff;
      background:linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
      box-shadow:var(--shadow-brand);
    }

    .btn-brand:hover{
      transform:translateY(-2px);
      filter:brightness(1.08);
      box-shadow:0 20px 36px -16px rgba(30,60,114,.85);
      color:#fff;
    }

    .btn-secondary{
      color:var(--ink-2);
      background:var(--surface-2);
      border-color:var(--line);
      box-shadow:var(--shadow-sm);
    }

    .btn-secondary:hover{
      background:#fff;
      border-color:var(--line-strong);
      color:var(--ink);
      transform:translateY(-2px);
      box-shadow:var(--shadow-md);
    }

    .btn:active{transform:translateY(0);}

    .btn:focus-visible{
      outline:3px solid var(--brand-soft);
      outline-offset:3px;
    }

    .btn-success:focus-visible{
      outline-color:rgba(16,185,129,.35);
    }

    .progress-overlay{
      position:fixed;
      inset:0;
      background:rgba(15,23,42,.62);
      backdrop-filter:blur(6px);
      -webkit-backdrop-filter:blur(6px);
      display:none;
      align-items:center;
      justify-content:center;
      padding:20px;
      z-index:9999;
      animation:fadeIn .25s ease;
    }

    .progress-overlay.active{display:flex;}

    .progress-card{
      background:var(--surface);
      border:1px solid var(--line);
      border-radius:var(--radius-xl);
      box-shadow:var(--shadow-lg);
      max-width:440px;
      width:100%;
      padding:36px 32px 30px;
      text-align:center;
      position:relative;
      overflow:hidden;
      animation:fadeUp .35s ease both;
    }

    .progress-card::before{
      content:"";
      position:absolute;
      top:0;left:0;right:0;
      height:4px;
      background:linear-gradient(90deg, #10b981, #047857);
    }

    .progress-card.is-error::before{
      background:linear-gradient(90deg, #ef4444, #b91c1c);
    }

    .progress-bar-wrap{
      margin:22px 0 16px;
    }

    .progress-bar-track{
      height:10px;
      background:var(--surface-2);
      border-radius:999px;
      overflow:hidden;
      border:1px solid var(--line);
      position:relative;
    }

    .progress-bar-fill{
      height:100%;
      width:0%;
      background:linear-gradient(90deg, #10b981, #047857);
      border-radius:999px;
      transition:width .45s cubic-bezier(.4,0,.2,1);
      position:relative;
      overflow:hidden;
    }

    .progress-bar-fill::after{
      content:"";
      position:absolute;
      inset:0;
      background:linear-gradient(90deg, transparent 0%, rgba(255,255,255,.4) 50%, transparent 100%);
      animation:shimmer 1.8s infinite;
    }

    @keyframes shimmer{
      0%{transform:translateX(-100%);}
      100%{transform:translateX(100%);}
    }

    .progress-percent{
      display:block;
      margin-top:12px;
      font-size:15px;
      font-weight:600;
      color:var(--ink);
      letter-spacing:-.2px;
    }

    .progress-percent small{
      font-size:11px;
      font-weight:500;
      color:var(--muted);
      letter-spacing:.4px;
      text-transform:uppercase;
      display:block;
      margin-top:2px;
    }

    .progress-step{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      padding:12px 16px;
      background:var(--brand-soft);
      border:1px solid rgba(42,82,152,.15);
      border-radius:var(--radius-md);
      font-size:13px;
      color:var(--brand-2);
      margin-bottom:20px;
      text-align:left;
      transition:background .3s ease, border-color .3s ease, color .3s ease;
    }

    .progress-step i{
      color:var(--brand);
      font-size:13px;
      flex-shrink:0;
    }

    .progress-step.is-success{
      background:var(--success-soft);
      border-color:var(--success-line);
      color:var(--success-dark);
    }

    .progress-step.is-success i{color:var(--success);}

    .progress-step.is-error{
      background:var(--danger-soft);
      border-color:var(--danger-line);
      color:var(--danger-dark);
    }

    .progress-step.is-error i{color:var(--danger);}

    .progress-actions{
      display:none;
      flex-direction:column;
      gap:10px;
    }

    .progress-actions.show{display:flex;}

    @media (max-width:480px){
      body{padding:20px 14px;}
      .card{padding:32px 22px 26px;border-radius:18px;}
      .icon-badge{width:58px;height:58px;font-size:22px;}
      h2{font-size:16.5px;}
      .subtitle{font-size:12.5px;}
      .version-badge{font-size:13px;padding:9px 14px;}
      .version-badge .label{font-size:10px;}
      .btn{font-size:14px;padding:12px 18px;}
      .notice{font-size:12.5px;padding:12px 14px;}
      .progress-card{padding:30px 20px 24px;border-radius:18px;}
      .progress-percent{font-size:14px;}
    }

    @media (prefers-reduced-motion:reduce){
      *{animation:none !important;transition:none !important;}
    }
  </style>
</head>
<body>
  <div class="card <?= !$latestVersion ? 'has-error' : (version_compare($latestVersion, $currentVersion, '>') ? 'has-update' : '') ?>">
    <?php if (!$latestVersion): ?>
      <div class="icon-badge danger">
        <i class="fas fa-triangle-exclamation"></i>
      </div>
      <h2>Gagal Mengecek Update</h2>
      <p class="subtitle">Tidak dapat terhubung ke server pembaruan.</p>

      <div class="status-block">
        <div class="notice danger">
          <i class="fas fa-circle-exclamation"></i>
          <div>
            <strong>Koneksi ke GitHub gagal.</strong>
            <small>Pastikan file <b>version.json</b> tersedia dan akses internet server aktif.</small>
          </div>
        </div>
      </div>

      <div class="actions">
        <a class="btn btn-brand" href="dashboard.php">
          <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
      </div>

    <?php elseif (version_compare($latestVersion, $currentVersion, '>')): ?>
      <div class="icon-badge success">
        <i class="fas fa-cloud-arrow-down"></i>
      </div>
      <h2>Versi Baru Tersedia</h2>
      <p class="subtitle">Pembaruan aplikasi siap dipasang.</p>

      <div class="status-block">
        <div class="version-badges">
          <span class="version-badge current">
            <i class="fas fa-code-branch"></i>
            <span class="label">Saat ini</span> <?= htmlspecialchars($currentVersion) ?>
          </span>
          <i class="fas fa-arrow-right version-arrow"></i>
          <span class="version-badge latest">
            <i class="fas fa-rocket"></i>
            <span class="label">Terbaru</span> <?= htmlspecialchars($latestVersion) ?>
          </span>
        </div>
      </div>

      <div class="actions">
        <a id="btnUpdate" class="btn btn-success" href="update_aplikasi.php?file=<?= urlencode($updateUrl) ?>">
          <i class="fas fa-download"></i> Update Sekarang
        </a>
        <a class="btn btn-secondary" href="dashboard.php">
          <i class="fas fa-arrow-left"></i> Nanti Saja
        </a>
      </div>

    <?php else: ?>
      <div class="icon-badge">
        <i class="fas fa-circle-check"></i>
      </div>
      <h2>Aplikasi Sudah Terbaru</h2>
      <p class="subtitle">Anda menggunakan versi terbaru dari sistem absensi.</p>

      <div class="status-block">
        <div class="version-badges">
          <span class="version-badge current">
            <i class="fas fa-shield-halved"></i>
            <span class="label">Versi</span> <?= htmlspecialchars($currentVersion) ?>
          </span>
        </div>
        <div class="notice success">
          <i class="fas fa-circle-check"></i>
          <div>
            <strong>Tidak ada pembaruan tersedia.</strong>
            <small>Sistem berjalan pada versi terbaru saat ini.</small>
          </div>
        </div>
      </div>

      <div class="actions">
        <a class="btn btn-brand" href="dashboard.php">
          <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($latestVersion && version_compare($latestVersion, $currentVersion, '>')): ?>
  <div class="progress-overlay" id="progressOverlay" role="dialog" aria-modal="true" aria-labelledby="progressTitle">
    <div class="progress-card" id="progressCard">
      <div class="icon-badge success" id="progressIcon">
        <i class="fas fa-cloud-arrow-down" id="progressIconInner"></i>
      </div>
      <h2 id="progressTitle">Memperbarui Aplikasi</h2>
      <p class="subtitle" id="progressSubtitle">Mohon tunggu, proses ini sedang berjalan...</p>

      <div class="progress-bar-wrap">
        <div class="progress-bar-track">
          <div class="progress-bar-fill" id="progressFill"></div>
        </div>
        <span class="progress-percent" id="progressPercent">0%<small>Progress</small></span>
      </div>

      <div class="progress-step" id="progressStep">
        <i class="fas fa-circle-notch fa-spin" id="progressStepIcon"></i>
        <span id="progressStepText">Menghubungi server pembaruan...</span>
      </div>

      <div class="progress-actions" id="progressActions">
        <a class="btn btn-success" href="dashboard.php" id="progressDoneBtn">
          <i class="fas fa-arrow-right"></i> Lanjut ke Dashboard
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
  (function () {
    const btnUpdate = document.getElementById('btnUpdate');
    const overlay = document.getElementById('progressOverlay');
    if (!btnUpdate || !overlay) return;

    const card = document.getElementById('progressCard');
    const fill = document.getElementById('progressFill');
    const percent = document.getElementById('progressPercent');
    const step = document.getElementById('progressStep');
    const stepText = document.getElementById('progressStepText');
    const stepIcon = document.getElementById('progressStepIcon');
    const progressTitle = document.getElementById('progressTitle');
    const progressSubtitle = document.getElementById('progressSubtitle');
    const progressIcon = document.getElementById('progressIcon');
    const progressIconInner = document.getElementById('progressIconInner');
    const progressActions = document.getElementById('progressActions');

    const STEPS = [
      { pct: 12,  text: 'Menghubungi server pembaruan...' },
      { pct: 35,  text: 'Mengunduh file update...' },
      { pct: 60,  text: 'Mengekstrak arsip...' },
      { pct: 80,  text: 'Menyalin file ke direktori aplikasi...' },
      { pct: 95,  text: 'Menyelesaikan instalasi...' }
    ];

    let stepIndex = 0;
    let intervalId = null;

    function setProgress(value, text) {
      fill.style.width = value + '%';
      percent.innerHTML = value + '%<small>Progress</small>';
      if (text) stepText.textContent = text;
    }

    function advanceStep() {
      if (stepIndex >= STEPS.length) return;
      const s = STEPS[stepIndex];
      setProgress(s.pct, s.text);
      stepIndex++;
    }

    function startProgressAnimation() {
      stepIndex = 0;
      advanceStep();
      intervalId = setInterval(() => {
        if (stepIndex < STEPS.length) {
          advanceStep();
        }
      }, 850);
    }

    function stopProgressAnimation() {
      if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
      }
    }

    function showSuccess() {
      stopProgressAnimation();
      setProgress(100, 'Instalasi selesai dengan sukses.');
      step.classList.add('is-success');
      stepIcon.className = 'fas fa-circle-check';
      progressTitle.textContent = 'Update Berhasil';
      progressSubtitle.textContent = 'Aplikasi telah diperbarui ke versi terbaru.';
      progressActions.classList.add('show');
    }

    function showError(message) {
      stopProgressAnimation();
      card.classList.add('is-error');
      step.classList.add('is-error');
      stepIcon.className = 'fas fa-times-circle';
      stepText.textContent = message || 'Terjadi kesalahan saat memperbarui aplikasi.';
      progressTitle.textContent = 'Update Gagal';
      progressSubtitle.textContent = 'Proses pembaruan tidak dapat diselesaikan.';
      progressIcon.classList.remove('success');
      progressIcon.classList.add('danger');
      progressIconInner.className = 'fas fa-times';
      progressActions.innerHTML =
        '<a class="btn btn-secondary" href="cek_update.php"><i class="fas fa-rotate-right"></i> Coba Lagi</a>' +
        '<a class="btn btn-brand" href="dashboard.php"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>';
      progressActions.classList.add('show');
    }

    btnUpdate.addEventListener('click', function (e) {
      e.preventDefault();
      const url = this.getAttribute('href');
      if (!url) return;

      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';

      startProgressAnimation();

      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 120000);

      fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        signal: controller.signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => {
        clearTimeout(timeout);
        if (!res.ok) {
          throw new Error('Server merespon dengan status ' + res.status);
        }
        return res.text();
      })
      .then(() => {
        setTimeout(showSuccess, 550);
      })
      .catch(err => {
        clearTimeout(timeout);
        const msg = (err && err.name === 'AbortError')
          ? 'Waktu proses habis. Silakan coba lagi.'
          : (err && err.message) || 'Gagal menghubungi server pembaruan.';
        setTimeout(() => showError(msg), 300);
      });
    });
  })();
  </script>
</body>
</html>