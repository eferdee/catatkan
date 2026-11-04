<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

start_session();
security_headers();
header('Cache-Control: no-store');

$uid = current_user_id();
if ($uid === null) {
    header('Location: login.php');
    exit;
}
$st = db()->prepare('SELECT name, email, is_demo FROM users WHERE id = ?');
$st->execute([$uid]);
$user = $st->fetch();
if (!$user) {
    logout_user();
    header('Location: login.php');
    exit;
}

$boot = [
    'csrf'       => csrf_token(),
    'user'       => ['name' => $user['name'], 'email' => $user['email']],
    'categories' => categories(),
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f2f5ee">
<title>Dashboard — CatatKan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="assets/js/theme.js"></script>
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php include __DIR__ . '/includes/icons.php'; ?>
<script type="application/json" id="sw-boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="shell">
  <!-- ============ Sidebar (desktop) ============ -->
  <aside class="sidebar" aria-label="Navigasi utama">
    <a class="brand" href="#/dashboard">
      <span class="brand-mark"><svg class="i"><use href="#i-arrow-up-right"/></svg></span>
      <span>Catat<b>Kan</b></span>
    </a>

    <p class="nav-label">Menu utama</p>
    <nav class="nav">
      <a href="#/dashboard" data-nav="dashboard"><svg class="i"><use href="#i-home"/></svg>Dashboard</a>
      <a href="#/transactions" data-nav="transactions"><svg class="i"><use href="#i-swap"/></svg>Transaksi</a>
      <a href="#/budgets" data-nav="budgets"><svg class="i"><use href="#i-pie"/></svg>Anggaran</a>
      <a href="#/analytics" data-nav="analytics"><svg class="i"><use href="#i-trend"/></svg>Analisis</a>
    </nav>
    <p class="nav-label">Preferensi</p>
    <nav class="nav">
      <a href="#/settings" data-nav="settings"><svg class="i"><use href="#i-sliders"/></svg>Pengaturan</a>
    </nav>

    <div class="side-foot">
      <div class="side-tip">
        <strong>Tekan <kbd>N</kbd></strong>
        <span>untuk menambah transaksi dengan cepat.</span>
      </div>
      <div class="user-chip">
        <span class="avatar" id="sideAvatar">–</span>
        <div class="user-meta"><b id="sideName">–</b><small id="sideEmail">–</small></div>
        <button class="icon-btn sm dark" id="logoutBtn" aria-label="Keluar" title="Keluar"><svg class="i"><use href="#i-logout"/></svg></button>
      </div>
    </div>
  </aside>

  <!-- ============ Konten ============ -->
  <main class="main">
    <header class="topbar">
      <div class="top-title">
        <h1 id="pageTitle">Dashboard</h1>
        <p id="pageSub">&nbsp;</p>
      </div>
      <div class="top-actions">
        <button class="icon-btn" id="themeBtn" aria-label="Ganti tema" title="Ganti tema">
          <svg class="i theme-moon"><use href="#i-moon"/></svg>
          <svg class="i theme-sun"><use href="#i-sun"/></svg>
        </button>
        <button class="btn btn-primary btn-add-top" data-open-tx><svg class="i"><use href="#i-plus"/></svg>Tambah transaksi</button>
        <a class="avatar-btn" href="#/settings" id="topAvatar" aria-label="Pengaturan akun">–</a>
      </div>
    </header>

    <!-- ---------- Dashboard ---------- -->
    <section class="view" id="view-dashboard" hidden>
      <div class="empty-hero" id="emptyState" hidden>
        <div>
          <h2>Mulai catat keuanganmu</h2>
          <p>Belum ada transaksi. Tambahkan pemasukan atau pengeluaran pertamamu, dan dashboard akan terisi otomatis.</p>
        </div>
        <div class="row">
          <button class="btn btn-primary" data-open-tx><svg class="i"><use href="#i-plus"/></svg>Tambah transaksi</button>        </div>
      </div>

      <div class="grid-overview">
        <article class="card hero" id="heroCard">
          <div class="hero-top">
            <span class="eyebrow">Total saldo</span>
            <span class="hero-period" id="heroPeriod"></span>
          </div>
          <div class="hero-balance">
            <div class="balance-number" id="heroBalance">Rp 0</div>
            <button class="eye" id="heroEye" aria-label="Sembunyikan saldo"><svg class="i"><use href="#i-eye"/></svg></button>
          </div>
          <div class="hero-delta" id="heroDelta"></div>
          <div class="hero-foot">
            <div><span>Pemasukan bulan ini</span><b id="heroIncome">Rp 0</b></div>
            <div><span>Pengeluaran bulan ini</span><b id="heroExpense">Rp 0</b></div>
          </div>
        </article>
        <article class="card stat" id="statIncome"><div class="skeleton" style="height:96px"></div></article>
        <article class="card stat" id="statExpense"><div class="skeleton" style="height:96px"></div></article>
      </div>

      <div class="grid-main">
        <article class="card">
          <div class="card-head">
            <div><h2>Arus kas</h2><p id="cashflowMeta">Pemasukan vs pengeluaran per bulan</p></div>
            <div class="seg" id="periodSeg" role="group" aria-label="Rentang waktu">
              <button data-months="6" class="on">6 bln</button><button data-months="12">12 bln</button>
            </div>
          </div>
          <div class="chart-host" id="cashflowChart"></div>
          <div class="legend"><span><i style="background:var(--c-income)"></i>Pemasukan</span><span><i style="background:var(--c-expense)"></i>Pengeluaran</span></div>
        </article>

        <article class="card">
          <div class="card-head">
            <div><h2>Anggaran bulanan</h2><p>Pantau batas pengeluaranmu</p></div>
            <a class="link" href="#/budgets">Lihat semua</a>
          </div>
          <div id="dashBudgets"><div class="skeleton" style="height:180px"></div></div>
        </article>
      </div>

      <div class="grid-main alt">
        <article class="card">
          <div class="card-head">
            <div><h2>Transaksi terbaru</h2><p>Aktivitas keuanganmu belakangan ini</p></div>
            <a class="link" href="#/transactions">Lihat semua</a>
          </div>
          <div id="dashRecent"><div class="skeleton" style="height:220px"></div></div>
        </article>
        <div class="stack">
          <article class="card">
            <div class="card-head"><div><h2>Insight keuangan</h2><p>Hal kecil yang membuat perbedaan</p></div></div>
            <div id="dashInsights"><div class="skeleton" style="height:90px"></div></div>
          </article>
          <article class="card" id="goalCard"><div class="skeleton" style="height:110px"></div></article>
        </div>
      </div>
    </section>

    <!-- ---------- Transaksi ---------- -->
    <section class="view" id="view-transactions" hidden>
      <article class="card">
        <div class="filters">
          <label class="search"><svg class="i"><use href="#i-search"/></svg><input class="input" id="txSearch" type="search" placeholder="Cari nama, kategori, atau catatan…" autocomplete="off"></label>
          <div class="seg" id="txTypeFilter" role="group" aria-label="Jenis">
            <button data-type="" class="on">Semua</button><button data-type="income">Pemasukan</button><button data-type="expense">Pengeluaran</button>
          </div>
          <select class="input" id="txCatFilter" aria-label="Kategori"><option value="">Semua kategori</option></select>
          <input class="input" id="txMonthFilter" type="month" aria-label="Bulan">
          <a class="btn btn-ghost btn-sm" id="exportBtn" href="api/transactions.php?action=export"><svg class="i"><use href="#i-download"/></svg>Ekspor CSV</a>
        </div>
        <div class="tx-sum" id="txSummary"></div>
        <div class="tx-list" id="txList"><div class="skeleton" style="height:260px"></div></div>
        <div class="pager" id="txPager"></div>
      </article>
    </section>

    <!-- ---------- Anggaran ---------- -->
    <section class="view" id="view-budgets" hidden>
      <article class="card budget-total" id="budgetTotal"></article>
      <div class="budget-grid" id="budgetGrid"></div>
    </section>

    <!-- ---------- Analisis ---------- -->
    <section class="view" id="view-analytics" hidden>
      <div class="kpis" id="kpis"></div>
      <div class="grid-main">
        <article class="card">
          <div class="card-head"><div><h2>Tren 12 bulan</h2><p>Pemasukan dan pengeluaran sepanjang tahun</p></div></div>
          <div class="chart-host" id="trendChart"></div>
          <div class="legend"><span><i style="background:var(--c-income)"></i>Pemasukan</span><span><i style="background:var(--c-expense)"></i>Pengeluaran</span></div>
        </article>
        <article class="card">
          <div class="card-head"><div><h2>Pengeluaran per kategori</h2><p id="donutPeriod">Bulan ini</p></div></div>
          <div id="donutBox"></div>
        </article>
      </div>
      <div class="grid-main alt">
        <article class="card">
          <div class="card-head"><div><h2>Merchant teratas</h2><p>Paling banyak menyerap uangmu bulan ini</p></div></div>
          <div id="merchants"></div>
        </article>
        <article class="card">
          <div class="card-head"><div><h2>Semua insight</h2><p>Disusun otomatis dari datamu</p></div></div>
          <div id="allInsights"></div>
        </article>
      </div>
    </section>

    <!-- ---------- Pengaturan ---------- -->
    <section class="view" id="view-settings" hidden>
      <div class="settings-grid">
        <article class="card">
          <div class="card-head"><div><h2>Profil</h2><p>Akun yang sedang masuk</p></div></div>
          <div class="profile-row">
            <span class="avatar lg" id="setAvatar">–</span>
            <div><b id="setName">–</b><small id="setEmail">–</small></div>
          </div>
          <button class="btn btn-soft-danger" id="logoutBtn2"><svg class="i"><use href="#i-logout"/></svg>Keluar dari akun</button>
        </article>

        <article class="card">
          <div class="card-head"><div><h2>Tampilan</h2><p>Pilih tema yang nyaman di matamu</p></div></div>
          <div class="seg seg-full" id="themeSeg" role="group" aria-label="Tema">
            <button data-mode="light">Terang</button><button data-mode="dark">Gelap</button><button data-mode="auto">Otomatis</button>
          </div>
        </article>

        <article class="card">
          <div class="card-head"><div><h2>Target tabungan</h2><p>Atur tujuan finansialmu</p></div></div>
          <form id="goalForm" novalidate>
            <label class="field"><span>Nama target</span><input class="input" id="goalTitle" maxlength="80"><em class="err" data-err="title"></em></label>
            <div class="field-row">
              <label class="field"><span>Target (Rp)</span><input class="input" id="goalTarget" inputmode="numeric" autocomplete="off"><em class="err" data-err="target"></em></label>
              <label class="field"><span>Terkumpul (Rp)</span><input class="input" id="goalSaved" inputmode="numeric" autocomplete="off"><em class="err" data-err="saved"></em></label>
            </div>
            <button class="btn btn-primary" type="submit"><span class="btn-label">Simpan target</span><span class="spinner"></span></button>
          </form>
        </article>

        <article class="card">
          <div class="card-head"><div><h2>Data</h2><p>Bawa datamu ke mana saja</p></div></div>
          <p class="muted-p">Unduh seluruh transaksi sebagai file CSV yang bisa dibuka di Excel atau Google Sheets.</p>
          <a class="btn btn-ghost" href="api/transactions.php?action=export"><svg class="i"><use href="#i-download"/></svg>Ekspor semua transaksi</a>
        </article>

        <?php if (!(int) $user['is_demo']): ?>
        <article class="card danger-zone">
          <div class="card-head"><div><h2>Hapus semua data</h2><p>Mulai lagi dari awal</p></div></div>
          <p class="muted-p">Menghapus seluruh transaksi, anggaran, dan target tabunganmu secara permanen. Akunmu tetap ada dan datanya kembali kosong. Sebaiknya ekspor CSV dulu bila perlu.</p>
          <button class="btn btn-soft-danger" id="resetBtn" type="button"><svg class="i"><use href="#i-trash"/></svg>Hapus semua data</button>
        </article>
        <?php endif; ?>
      </div>
    </section>
  </main>
</div>

<!-- ============ Navigasi bawah (mobile) ============ -->
<nav class="bottom-nav" aria-label="Navigasi">
  <a href="#/dashboard" data-nav="dashboard"><svg class="i"><use href="#i-home"/></svg><span>Beranda</span></a>
  <a href="#/transactions" data-nav="transactions"><svg class="i"><use href="#i-swap"/></svg><span>Transaksi</span></a>
  <button class="fab" data-open-tx aria-label="Tambah transaksi"><svg class="i"><use href="#i-plus"/></svg></button>
  <a href="#/budgets" data-nav="budgets"><svg class="i"><use href="#i-pie"/></svg><span>Anggaran</span></a>
  <a href="#/analytics" data-nav="analytics"><svg class="i"><use href="#i-trend"/></svg><span>Analisis</span></a>
</nav>

<!-- ============ Dialog ============ -->
<dialog class="dlg" id="txDialog">
  <form class="sheet" id="txForm" novalidate>
    <header class="dlg-head"><h3 id="txTitle">Tambah transaksi</h3><button type="button" class="icon-btn sm" data-close aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button></header>
    <div class="seg seg-full" id="txTypeSeg" role="group" aria-label="Jenis transaksi">
      <button type="button" data-type="expense" class="on">Pengeluaran</button><button type="button" data-type="income">Pemasukan</button>
    </div>
    <div class="field amount-field">
      <span>Jumlah</span>
      <div class="amount-wrap"><b>Rp</b><input id="txAmount" inputmode="numeric" autocomplete="off" placeholder="0" aria-label="Jumlah dalam rupiah"></div>
      <em class="err" data-err="amount"></em>
    </div>
    <label class="field"><span>Nama transaksi</span><input class="input" id="txName" maxlength="80" placeholder="Contoh: Makan siang" autocomplete="off"><em class="err" data-err="name"></em></label>
    <div class="field"><span>Kategori</span><div class="chips" id="txCats"></div><em class="err" data-err="category"></em></div>
    <div class="field-row">
      <label class="field"><span>Tanggal</span><input class="input" id="txDate" type="date"><em class="err" data-err="date"></em></label>
      <label class="field"><span>Catatan (opsional)</span><input class="input" id="txNote" maxlength="255" autocomplete="off"><em class="err" data-err="note"></em></label>
    </div>
    <footer class="dlg-actions">
      <button type="button" class="btn btn-ghost" data-close>Batal</button>
      <button type="submit" class="btn btn-primary" id="txSubmit"><span class="btn-label">Simpan</span><span class="spinner"></span></button>
    </footer>
  </form>
</dialog>

<dialog class="dlg" id="budgetDialog">
  <form class="sheet" id="budgetForm" novalidate>
    <header class="dlg-head"><h3 id="budgetTitle">Tambah anggaran</h3><button type="button" class="icon-btn sm" data-close aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button></header>
    <label class="field"><span>Kategori</span><select class="input" id="budgetCat"></select><em class="err" data-err="category"></em></label>
    <div class="field amount-field">
      <span>Batas per bulan</span>
      <div class="amount-wrap"><b>Rp</b><input id="budgetLimit" inputmode="numeric" autocomplete="off" placeholder="0" aria-label="Batas anggaran"></div>
      <em class="err" data-err="limit"></em>
    </div>
    <footer class="dlg-actions">
      <button type="button" class="btn btn-soft-danger" id="budgetDelete" hidden>Hapus</button>
      <button type="button" class="btn btn-ghost" data-close>Batal</button>
      <button type="submit" class="btn btn-primary"><span class="btn-label">Simpan</span><span class="spinner"></span></button>
    </footer>
  </form>
</dialog>

<dialog class="dlg" id="depositDialog">
  <form class="sheet" id="depositForm" novalidate>
    <header class="dlg-head"><h3>Tambah tabungan</h3><button type="button" class="icon-btn sm" data-close aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button></header>
    <div class="field amount-field">
      <span id="depositFor">Untuk target</span>
      <div class="amount-wrap"><b>Rp</b><input id="depositAmount" inputmode="numeric" autocomplete="off" placeholder="0" aria-label="Jumlah tabungan"></div>
      <em class="err" data-err="amount"></em>
    </div>
    <footer class="dlg-actions">
      <button type="button" class="btn btn-ghost" data-close>Batal</button>
      <button type="submit" class="btn btn-primary"><span class="btn-label">Tambahkan</span><span class="spinner"></span></button>
    </footer>
  </form>
</dialog>

<dialog class="dlg dlg-sm" id="confirmDialog">
  <form class="sheet" method="dialog">
    <h3 id="confirmTitle">Hapus?</h3>
    <p class="muted-p" id="confirmText"></p>
    <footer class="dlg-actions">
      <button class="btn btn-ghost" value="cancel">Batal</button>
      <button class="btn btn-danger" value="ok" id="confirmOk">Hapus</button>
    </footer>
  </form>
</dialog>

<div class="toasts" id="toasts" aria-live="polite"></div>
<script src="assets/js/app.js"></script>
</body>
</html>
