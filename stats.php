<?php
/**
 * parana.si - Painel Interno de Métricas e Contador de Visitas
 * Acesso privado para acompanhamento diário, semanal, mensal e personalizado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Sao_Paulo');

// SENHA DE ACESSO AO PAINEL (Altere aqui se desejar)
define('ADMIN_PASSWORD', 'parana2026');

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['parana_admin_logged']);
    session_destroy();
    header('Location: stats.php');
    exit;
}

// Processar Login
$loginError = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['parana_admin_logged'] = true;
        header('Location: stats.php');
        exit;
    } else {
        $loginError = 'Senha incorreta. Tente novamente.';
    }
}

// Se não estiver logado, exibe tela de login
if (empty($_SESSION['parana_admin_logged'])):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>parana.si — Acesso ao Painel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #080808;
      color: #fff;
      font-family: 'Inter', -apple-system, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      -webkit-font-smoothing: antialiased;
    }
    .card {
      background: #121212;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 20px;
      padding: 40px;
      width: 100%;
      max-width: 380px;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.6);
    }
    .badge {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #FFD600;
      margin-bottom: 12px;
    }
    h1 {
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin-bottom: 8px;
    }
    p {
      font-size: 13px;
      color: rgba(255,255,255,0.5);
      margin-bottom: 28px;
    }
    .input-group {
      margin-bottom: 20px;
      text-align: left;
    }
    label {
      display: block;
      font-size: 11px;
      font-weight: 500;
      color: rgba(255,255,255,0.4);
      margin-bottom: 8px;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }
    input[type="password"] {
      width: 100%;
      padding: 14px 16px;
      background: #080808;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 12px;
      color: #fff;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      transition: border-color 0.2s;
    }
    input[type="password"]:focus {
      border-color: #FFD600;
    }
    button {
      width: 100%;
      padding: 14px;
      background: #FFD600;
      color: #080808;
      font-weight: 600;
      font-size: 14px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: opacity 0.2s, transform 0.1s;
    }
    button:hover { opacity: 0.92; }
    button:active { transform: scale(0.99); }
    .error {
      background: rgba(255, 70, 70, 0.12);
      border: 1px solid rgba(255, 70, 70, 0.3);
      color: #ff6b6b;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="badge">Analytics Interno</div>
    <h1>parana.si</h1>
    <p>Digite a senha para acessar o painel de visitas</p>
    
    <?php if (!empty($loginError)): ?>
      <div class="error"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="input-group">
        <label>Senha de acesso</label>
        <input type="password" name="password" placeholder="Digite sua senha" required autofocus />
      </div>
      <button type="submit">Entrar no Painel</button>
    </form>
  </div>
</body>
</html>
<?php
exit;
endif;

// Conexão com banco SQLite
$dataDir = __DIR__ . '/data';
$dbFile = $dataDir . '/analytics.db';
$pdo = null;
if (file_exists($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
        $pdo = null;
    }
}

// Filtro de Período
$period = $_GET['period'] ?? 'today';
$today = date('Y-m-d');

switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $endDate = $today;
        $periodLabel = 'Últimos 7 dias';
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-29 days'));
        $endDate = $today;
        $periodLabel = 'Últimos 30 dias';
        break;
    case 'custom':
        $startDate = !empty($_GET['from']) ? preg_replace('/[^0-9\-]/', '', $_GET['from']) : $today;
        $endDate = !empty($_GET['to']) ? preg_replace('/[^0-9\-]/', '', $_GET['to']) : $today;
        if ($startDate > $endDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }
        $periodLabel = date('d/m/Y', strtotime($startDate)) . ' até ' . date('d/m/Y', strtotime($endDate));
        break;
    case 'today':
    default:
        $period = 'today';
        $startDate = $today;
        $endDate = $today;
        $periodLabel = 'Hoje (' . date('d/m/Y') . ')';
        break;
}

// Exportação CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $pdo) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=visitas_parana_si_' . $period . '_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Data/Hora (BRT)', 'Dispositivo', 'SO', 'Navegador', 'Origem', 'Tela', 'País']);
    
    $stmt = $pdo->prepare("SELECT id, created_at, device, os, browser, referrer_domain, screen, country FROM visits WHERE date_brt BETWEEN ? AND ? ORDER BY id DESC");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Consultas de Métricas
$totalViews = 0;
$uniqueVisitors = 0;
$todayViews = 0;
$todayUniques = 0;
$deviceStats = [];
$referrerStats = [];
$osStats = [];
$browserStats = [];
$recentVisits = [];
$chartLabels = [];
$chartViews = [];
$chartUniques = [];

if ($pdo) {
    // Totais no período selecionado
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_views, COUNT(DISTINCT visitor_hash) as unique_visitors FROM visits WHERE date_brt BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalViews = (int)($totals['total_views'] ?? 0);
    $uniqueVisitors = (int)($totals['unique_visitors'] ?? 0);

    // Totais de hoje para referência rápida
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_views, COUNT(DISTINCT visitor_hash) as unique_visitors FROM visits WHERE date_brt = ?");
    $stmt->execute([$today]);
    $tTotals = $stmt->fetch(PDO::FETCH_ASSOC);
    $todayViews = (int)($tTotals['total_views'] ?? 0);
    $todayUniques = (int)($tTotals['unique_visitors'] ?? 0);

    // Dispositivos
    $stmt = $pdo->prepare("SELECT device, COUNT(*) as count FROM visits WHERE date_brt BETWEEN ? AND ? GROUP BY device ORDER BY count DESC");
    $stmt->execute([$startDate, $endDate]);
    $deviceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Origens / Referrers
    $stmt = $pdo->prepare("SELECT referrer_domain, COUNT(*) as count FROM visits WHERE date_brt BETWEEN ? AND ? GROUP BY referrer_domain ORDER BY count DESC LIMIT 8");
    $stmt->execute([$startDate, $endDate]);
    $referrerStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sistemas Operacionais
    $stmt = $pdo->prepare("SELECT os, COUNT(*) as count FROM visits WHERE date_brt BETWEEN ? AND ? GROUP BY os ORDER BY count DESC LIMIT 6");
    $stmt->execute([$startDate, $endDate]);
    $osStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Navegadores
    $stmt = $pdo->prepare("SELECT browser, COUNT(*) as count FROM visits WHERE date_brt BETWEEN ? AND ? GROUP BY browser ORDER BY count DESC LIMIT 6");
    $stmt->execute([$startDate, $endDate]);
    $browserStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimos acessos
    $stmt = $pdo->prepare("SELECT created_at, device, os, browser, referrer_domain, screen, country, is_unique_day FROM visits WHERE date_brt BETWEEN ? AND ? ORDER BY id DESC LIMIT 25");
    $stmt->execute([$startDate, $endDate]);
    $recentVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Montagem do Gráfico
    if ($period === 'today') {
        // Horas do dia (00h a 23h)
        $stmt = $pdo->prepare("
            SELECT hour_brt, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as uniques 
            FROM visits 
            WHERE date_brt = ? 
            GROUP BY hour_brt
        ");
        $stmt->execute([$today]);
        $hourData = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hourData[(int)$r['hour_brt']] = $r;
        }

        $currentHour = (int)date('H');
        for ($h = 0; $h <= 23; $h++) {
            $chartLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . 'h';
            $chartViews[] = isset($hourData[$h]) ? (int)$hourData[$h]['views'] : 0;
            $chartUniques[] = isset($hourData[$h]) ? (int)$hourData[$h]['uniques'] : 0;
        }
    } else {
        // Dias no período
        $stmt = $pdo->prepare("
            SELECT date_brt, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as uniques 
            FROM visits 
            WHERE date_brt BETWEEN ? AND ? 
            GROUP BY date_brt 
            ORDER BY date_brt ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $dayData = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dayData[$r['date_brt']] = $r;
        }

        $curr = strtotime($startDate);
        $end = strtotime($endDate);
        while ($curr <= $end) {
            $d = date('Y-m-d', $curr);
            $chartLabels[] = date('d/m', $curr);
            $chartViews[] = isset($dayData[$d]) ? (int)$dayData[$d]['views'] : 0;
            $chartUniques[] = isset($dayData[$d]) ? (int)$dayData[$d]['uniques'] : 0;
            $curr = strtotime('+1 day', $curr);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>parana.si — Analytics Interno</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #080808;
      color: #fff;
      font-family: 'Inter', -apple-system, sans-serif;
      min-height: 100vh;
      padding: clamp(20px, 3vw, 40px);
      -webkit-font-smoothing: antialiased;
    }
    .wrapper {
      max-width: 1140px;
      margin: 0 auto;
    }

    /* ── Header ── */
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
      margin-bottom: 32px;
      padding-bottom: 24px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .domain-title {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: #fff;
    }
    .live-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.25);
      color: #4ade80;
      font-size: 11px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 100px;
      letter-spacing: 0.05em;
    }
    .live-dot {
      width: 6px;
      height: 6px;
      background: #4ade80;
      border-radius: 50%;
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.4; transform: scale(0.9); }
    }
    .header-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .btn-secondary {
      font-size: 12px;
      font-weight: 500;
      color: rgba(255,255,255,0.7);
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      padding: 8px 16px;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-secondary:hover {
      background: rgba(255,255,255,0.1);
      color: #fff;
    }

    /* ── Filtro de Períodos ── */
    .filter-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
      margin-bottom: 28px;
      background: #111;
      padding: 12px 16px;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,0.06);
    }
    .nav-pills {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }
    .pill {
      font-size: 13px;
      font-weight: 500;
      color: rgba(255,255,255,0.6);
      background: transparent;
      border: 1px solid transparent;
      padding: 8px 16px;
      border-radius: 10px;
      text-decoration: none;
      transition: all 0.2s;
    }
    .pill:hover {
      color: #fff;
      background: rgba(255,255,255,0.04);
    }
    .pill.active {
      color: #080808;
      background: #FFD600;
      font-weight: 600;
    }
    .custom-form {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .custom-form input[type="date"] {
      background: #080808;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 8px;
      color: #fff;
      padding: 6px 10px;
      font-size: 12px;
      font-family: inherit;
      outline: none;
      color-scheme: dark;
    }
    .btn-filter {
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 7px 14px;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-filter:hover { background: rgba(255,255,255,0.2); }

    /* ── Cards de Métricas ── */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }
    .kpi-card {
      background: #121212;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 24px;
      position: relative;
      overflow: hidden;
    }
    .kpi-label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.45);
      margin-bottom: 8px;
    }
    .kpi-value {
      font-size: 34px;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #fff;
      margin-bottom: 6px;
      font-variant-numeric: tabular-nums;
    }
    .kpi-sub {
      font-size: 12px;
      color: rgba(255,255,255,0.4);
    }
    .kpi-highlight {
      color: #FFD600;
    }

    /* ── Gráfico ── */
    .chart-box {
      background: #121212;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 28px;
    }
    .chart-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }
    .chart-title {
      font-size: 15px;
      font-weight: 600;
      color: #fff;
    }
    .chart-legend {
      display: flex;
      align-items: center;
      gap: 14px;
      font-size: 12px;
      color: rgba(255,255,255,0.6);
    }
    .legend-dot {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 2px;
      margin-right: 4px;
    }

    /* ── Grids de Distribuição ── */
    .breakdown-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 20px;
      margin-bottom: 28px;
    }
    .panel {
      background: #121212;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 24px;
    }
    .panel-title {
      font-size: 14px;
      font-weight: 600;
      color: #fff;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .bar-row {
      margin-bottom: 14px;
    }
    .bar-row:last-child { margin-bottom: 0; }
    .bar-info {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      margin-bottom: 6px;
    }
    .bar-name { color: rgba(255,255,255,0.85); font-weight: 500; }
    .bar-count { color: rgba(255,255,255,0.45); font-variant-numeric: tabular-nums; }
    .bar-track {
      width: 100%;
      height: 6px;
      background: rgba(255,255,255,0.05);
      border-radius: 10px;
      overflow: hidden;
    }
    .bar-fill {
      height: 100%;
      background: #FFD600;
      border-radius: 10px;
      transition: width 0.4s ease;
    }
    .empty-note {
      font-size: 12px;
      color: rgba(255,255,255,0.3);
      padding: 20px 0;
      text-align: center;
    }

    /* ── Tabela de Últimos Acessos ── */
    .table-container {
      background: #121212;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 24px;
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      text-align: left;
    }
    th {
      padding: 12px 14px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(255,255,255,0.4);
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    td {
      padding: 14px;
      color: rgba(255,255,255,0.8);
      border-bottom: 1px solid rgba(255,255,255,0.04);
      white-space: nowrap;
    }
    tr:last-child td { border-bottom: none; }
    .tag {
      display: inline-block;
      font-size: 10px;
      font-weight: 600;
      padding: 2px 6px;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .tag-unique {
      background: rgba(255, 214, 0, 0.15);
      color: #FFD600;
    }
    .tag-return {
      background: rgba(255, 255, 255, 0.08);
      color: rgba(255,255,255,0.6);
    }

    footer {
      text-align: center;
      font-size: 11px;
      color: rgba(255,255,255,0.3);
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,0.06);
    }
  </style>
</head>
<body>
  <div class="wrapper">

    <!-- Topo -->
    <header>
      <div class="brand">
        <h1 class="domain-title">parana.si</h1>
        <div class="live-badge">
          <span class="live-dot"></span> Monitoramento Ativo
        </div>
      </div>
      <div class="header-actions">
        <a href="/" target="_blank" class="btn-secondary">Ver Landing Page ↗</a>
        <a href="?export=csv&period=<?= htmlspecialchars($period) ?>&from=<?= htmlspecialchars($startDate) ?>&to=<?= htmlspecialchars($endDate) ?>" class="btn-secondary">Exportar CSV</a>
        <a href="?action=logout" class="btn-secondary" style="color: #ff6b6b;">Sair</a>
      </div>
    </header>

    <!-- Filtro de Períodos -->
    <div class="filter-bar">
      <div class="nav-pills">
        <a href="?period=today" class="pill <?= $period === 'today' ? 'active' : '' ?>">Hoje</a>
        <a href="?period=week" class="pill <?= $period === 'week' ? 'active' : '' ?>">Últimos 7 dias</a>
        <a href="?period=month" class="pill <?= $period === 'month' ? 'active' : '' ?>">Últimos 30 dias</a>
      </div>
      <form class="custom-form" method="GET">
        <input type="hidden" name="period" value="custom" />
        <span style="font-size: 12px; color: rgba(255,255,255,0.4);">De:</span>
        <input type="date" name="from" value="<?= htmlspecialchars($startDate) ?>" required />
        <span style="font-size: 12px; color: rgba(255,255,255,0.4);">Até:</span>
        <input type="date" name="to" value="<?= htmlspecialchars($endDate) ?>" required />
        <button type="submit" class="btn-filter">Filtrar</button>
      </form>
    </div>

    <!-- Cards de Indicadores -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">Visualizações Totais</div>
        <div class="kpi-value"><?= number_format($totalViews, 0, ',', '.') ?></div>
        <div class="kpi-sub">No período: <?= htmlspecialchars($periodLabel) ?></div>
      </div>

      <div class="kpi-card">
        <div class="kpi-label">Visitantes Únicos</div>
        <div class="kpi-value kpi-highlight"><?= number_format($uniqueVisitors, 0, ',', '.') ?></div>
        <div class="kpi-sub"><?= $totalViews > 0 ? round(($uniqueVisitors / $totalViews) * 100, 1) . '% de visitantes únicos' : 'Sem visitas ainda' ?></div>
      </div>

      <div class="kpi-card">
        <div class="kpi-label">Hoje (Resumo Rápido)</div>
        <div class="kpi-value"><?= number_format($todayViews, 0, ',', '.') ?></div>
        <div class="kpi-sub"><?= number_format($todayUniques, 0, ',', '.') ?> visitantes únicos hoje</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-label">Dispositivo Principal</div>
        <?php
          $topDevice = !empty($deviceStats) ? $deviceStats[0]['device'] : '—';
          $topDevicePercent = ($totalViews > 0 && !empty($deviceStats)) ? round(($deviceStats[0]['count'] / $totalViews) * 100) : 0;
        ?>
        <div class="kpi-value" style="font-size: 26px;"><?= htmlspecialchars($topDevice) ?></div>
        <div class="kpi-sub"><?= $topDevicePercent > 0 ? $topDevicePercent . '% do tráfego' : 'Aguardando dados' ?></div>
      </div>
    </div>

    <!-- Gráfico de Evolução -->
    <div class="chart-box">
      <div class="chart-header">
        <div class="chart-title">Evolução do Tráfego (<?= htmlspecialchars($periodLabel) ?>)</div>
        <div class="chart-legend">
          <span><span class="legend-dot" style="background: rgba(255, 255, 255, 0.4);"></span> Visualizações</span>
          <span><span class="legend-dot" style="background: #FFD600;"></span> Únicos</span>
        </div>
      </div>
      <div style="height: 260px; width: 100%;">
        <canvas id="trafficChart"></canvas>
      </div>
    </div>

    <!-- Detalhamento: Origens e Dispositivos -->
    <div class="breakdown-grid">
      <!-- Origens -->
      <div class="panel">
        <div class="panel-title">
          <span>Principais Origens</span>
          <span style="font-size: 11px; color: rgba(255,255,255,0.4);">Referrers</span>
        </div>
        <?php if (empty($referrerStats)): ?>
          <div class="empty-note">Nenhum dado registrado para este período.</div>
        <?php else: ?>
          <?php foreach ($referrerStats as $row): 
            $percent = $totalViews > 0 ? round(($row['count'] / $totalViews) * 100) : 0;
          ?>
            <div class="bar-row">
              <div class="bar-info">
                <span class="bar-name"><?= htmlspecialchars($row['referrer_domain']) ?></span>
                <span class="bar-count"><?= $row['count'] ?> (<?= $percent ?>%)</span>
              </div>
              <div class="bar-track">
                <div class="bar-fill" style="width: <?= $percent ?>%;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Dispositivos -->
      <div class="panel">
        <div class="panel-title">
          <span>Dispositivos</span>
          <span style="font-size: 11px; color: rgba(255,255,255,0.4);">Aparelhos</span>
        </div>
        <?php if (empty($deviceStats)): ?>
          <div class="empty-note">Nenhum dado registrado para este período.</div>
        <?php else: ?>
          <?php foreach ($deviceStats as $row): 
            $percent = $totalViews > 0 ? round(($row['count'] / $totalViews) * 100) : 0;
          ?>
            <div class="bar-row">
              <div class="bar-info">
                <span class="bar-name"><?= htmlspecialchars($row['device']) ?></span>
                <span class="bar-count"><?= $row['count'] ?> (<?= $percent ?>%)</span>
              </div>
              <div class="bar-track">
                <div class="bar-fill" style="width: <?= $percent ?>%; background: #fff;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Navegadores & Sistemas -->
      <div class="panel">
        <div class="panel-title">
          <span>Sistemas & Navegadores</span>
          <span style="font-size: 11px; color: rgba(255,255,255,0.4);">Software</span>
        </div>
        <?php if (empty($osStats) && empty($browserStats)): ?>
          <div class="empty-note">Nenhum dado registrado para este período.</div>
        <?php else: ?>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
              <div style="font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 10px; font-weight: 600;">Sistemas</div>
              <?php foreach ($osStats as $r): ?>
                <div style="font-size: 12px; margin-bottom: 6px; display: flex; justify-content: space-between;">
                  <span style="color: rgba(255,255,255,0.8);"><?= htmlspecialchars($r['os']) ?></span>
                  <span style="color: rgba(255,255,255,0.4);"><?= $r['count'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <div>
              <div style="font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 10px; font-weight: 600;">Navegadores</div>
              <?php foreach ($browserStats as $r): ?>
                <div style="font-size: 12px; margin-bottom: 6px; display: flex; justify-content: space-between;">
                  <span style="color: rgba(255,255,255,0.8);"><?= htmlspecialchars($r['browser']) ?></span>
                  <span style="color: rgba(255,255,255,0.4);"><?= $r['count'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tabela de Últimos Acessos -->
    <div class="table-container">
      <div style="font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 16px;">
        Últimos Acessos em Tempo Real
      </div>
      <?php if (empty($recentVisits)): ?>
        <div class="empty-note">Ainda não há registros de visitas no banco de dados.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Horário (BRT)</th>
              <th>Tipo</th>
              <th>Dispositivo</th>
              <th>Sistema / Navegador</th>
              <th>Origem</th>
              <th>Tela</th>
              <th>País</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentVisits as $v): ?>
              <tr>
                <td><?= htmlspecialchars(date('d/m H:i:s', strtotime($v['created_at']))) ?></td>
                <td>
                  <?php if (!empty($v['is_unique_day'])): ?>
                    <span class="tag tag-unique">Novo</span>
                  <?php else: ?>
                    <span class="tag tag-return">Retorno</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($v['device']) ?></td>
                <td><?= htmlspecialchars($v['os'] . ' · ' . $v['browser']) ?></td>
                <td><?= htmlspecialchars($v['referrer_domain']) ?></td>
                <td><?= !empty($v['screen']) ? htmlspecialchars($v['screen']) : '—' ?></td>
                <td><?= !empty($v['country']) ? htmlspecialchars($v['country']) : 'BR' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <footer>
      parana.si · Rastreador interno privado · Todos os horários no fuso de Brasília (BRT)
    </footer>

  </div>

  <script>
    const ctx = document.getElementById('trafficChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
          {
            label: 'Visitantes Únicos',
            data: <?= json_encode($chartUniques) ?>,
            backgroundColor: '#FFD600',
            borderColor: '#FFD600',
            borderRadius: 4,
            borderWidth: 1,
            order: 1
          },
          {
            label: 'Visualizações',
            data: <?= json_encode($chartViews) ?>,
            backgroundColor: 'rgba(255, 255, 255, 0.15)',
            borderColor: 'rgba(255, 255, 255, 0.3)',
            borderRadius: 4,
            borderWidth: 1,
            order: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1f1f1f',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(255,255,255,0.04)' },
            ticks: { color: 'rgba(255,255,255,0.4)', font: { size: 11, family: 'Inter' } }
          },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(255,255,255,0.04)' },
            ticks: {
              color: 'rgba(255,255,255,0.4)',
              precision: 0,
              font: { size: 11, family: 'Inter' }
            }
          }
        }
      }
    });
  </script>
</body>
</html>
