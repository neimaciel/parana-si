<?php
/**
 * parana.si - Rastreador interno e silencioso de visitas
 * Não exibe nada no frontend e roda de forma ultra-rápida.
 */

// Timezone de Brasília
date_default_timezone_set('America/Sao_Paulo');

// Prevenção de cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Diretório de dados e proteção
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// Garante proteção .htaccess para não permitir download direto do banco SQLite
$htaccessFile = $dataDir . '/.htaccess';
if (!file_exists($htaccessFile)) {
    $htaccessContent = "# Bloqueia acesso direto a arquivos desta pasta\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
    @file_put_contents($htaccessFile, $htaccessContent);
}

$indexHtml = $dataDir . '/index.html';
if (!file_exists($indexHtml)) {
    @file_put_contents($indexHtml, '');
}

// User-Agent e filtro de bots/crawlers
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$botPattern = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|uptime|curl|wget|python|scan|headless|lighthouse|pingdom|google-structured-data/i';
if (empty($userAgent) || preg_match($botPattern, $userAgent)) {
    sendResponse();
    exit;
}

// Captura de IP confiável (Cloudflare / Proxy / Direct)
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] 
    ?? $_SERVER['HTTP_X_REAL_IP'] 
    ?? (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]) : null)
    ?? $_SERVER['REMOTE_ADDR'] 
    ?? '127.0.0.1';

// Dados da requisição
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }
}

$path = $input['path'] ?? $_GET['path'] ?? '/';
$referrerRaw = $input['ref'] ?? $_SERVER['HTTP_REFERER'] ?? '';
$screen = $input['screen'] ?? '';
$country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';

// Classificação da Origem (Referrer)
$referrerDomain = 'Direto';
if (!empty($referrerRaw)) {
    $parsedHost = parse_url($referrerRaw, PHP_URL_HOST);
    if ($parsedHost) {
        $parsedHost = strtolower(preg_replace('/^www\./', '', $parsedHost));
        $currentHost = strtolower(preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'parana.si'));
        
        if ($parsedHost === $currentHost) {
            $referrerDomain = 'Direto / Interno';
        } elseif (strpos($parsedHost, 'google.') !== false) {
            $referrerDomain = 'Google';
        } elseif (strpos($parsedHost, 'instagram.com') !== false) {
            $referrerDomain = 'Instagram';
        } elseif (strpos($parsedHost, 'facebook.com') !== false || strpos($parsedHost, 'fb.com') !== false) {
            $referrerDomain = 'Facebook';
        } elseif (strpos($parsedHost, 't.co') !== false || strpos($parsedHost, 'twitter.com') !== false || strpos($parsedHost, 'x.com') !== false) {
            $referrerDomain = 'X (Twitter)';
        } elseif (strpos($parsedHost, 'linkedin.com') !== false) {
            $referrerDomain = 'LinkedIn';
        } elseif (strpos($parsedHost, 'whatsapp.com') !== false || strpos($parsedHost, 'wa.me') !== false) {
            $referrerDomain = 'WhatsApp';
        } elseif (strpos($parsedHost, 'youtube.com') !== false) {
            $referrerDomain = 'YouTube';
        } elseif (strpos($parsedHost, 'bing.com') !== false) {
            $referrerDomain = 'Bing';
        } elseif (strpos($parsedHost, 'yahoo.com') !== false) {
            $referrerDomain = 'Yahoo';
        } elseif (strpos($parsedHost, 'random.marketing') !== false) {
            $referrerDomain = 'random.marketing';
        } else {
            $referrerDomain = $parsedHost;
        }
    }
}

// Detecção de Dispositivo
$device = 'Desktop';
if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $userAgent)) {
    $device = 'Tablet';
} elseif (preg_match('/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|NetFront|Silk-Accelerated|(hpw|web)OS|Fennec|Minimo|Opera M(obi|ini)|Blazer/i', $userAgent)) {
    $device = 'Mobile';
}

// Detecção de Sistema Operacional
$os = 'Outro';
if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
    $os = 'iOS';
} elseif (preg_match('/Android/i', $userAgent)) {
    $os = 'Android';
} elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
    $os = 'macOS';
} elseif (preg_match('/Windows/i', $userAgent)) {
    $os = 'Windows';
} elseif (preg_match('/Linux/i', $userAgent)) {
    $os = 'Linux';
} elseif (preg_match('/CrOS/i', $userAgent)) {
    $os = 'Chrome OS';
}

// Detecção de Navegador
$browser = 'Outro';
if (preg_match('/Edg/i', $userAgent)) {
    $browser = 'Edge';
} elseif (preg_match('/Chrome/i', $userAgent) && !preg_match('/OPR/i', $userAgent)) {
    $browser = 'Chrome';
} elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
    $browser = 'Safari';
} elseif (preg_match('/Firefox/i', $userAgent)) {
    $browser = 'Firefox';
} elseif (preg_match('/OPR|Opera/i', $userAgent)) {
    $browser = 'Opera';
} elseif (preg_match('/SamsungBrowser/i', $userAgent)) {
    $browser = 'Samsung Internet';
}

// Hashing para privacidade e identificação de visitantes únicos
$salt = 'parana_si_analytics_salt_2026';
$ipHash = hash('sha256', $ip . $salt);
$dateBrt = date('Y-m-d');
$hourBrt = (int)date('H');
$visitorHash = hash('sha256', $ip . $userAgent . $dateBrt . $salt);
$createdAt = date('Y-m-d H:i:s');

try {
    $dbFile = $dataDir . '/analytics.db';
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    
    // Criação da tabela se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            date_brt TEXT NOT NULL,
            hour_brt INTEGER NOT NULL,
            visitor_hash TEXT NOT NULL,
            is_unique_day INTEGER DEFAULT 0,
            ip_hash TEXT NOT NULL,
            device TEXT NOT NULL,
            os TEXT NOT NULL,
            browser TEXT NOT NULL,
            referrer TEXT NOT NULL,
            referrer_domain TEXT NOT NULL,
            screen TEXT,
            country TEXT,
            path TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_visits_date ON visits(date_brt);
        CREATE INDEX IF NOT EXISTS idx_visits_date_hour ON visits(date_brt, hour_brt);
        CREATE INDEX IF NOT EXISTS idx_visits_hash ON visits(visitor_hash, date_brt);
    ");

    // Verifica se já acessou hoje
    $checkStmt = $pdo->prepare("SELECT 1 FROM visits WHERE visitor_hash = ? AND date_brt = ? LIMIT 1");
    $checkStmt->execute([$visitorHash, $dateBrt]);
    $isUnique = $checkStmt->fetchColumn() ? 0 : 1;

    // Registra a visita
    $stmt = $pdo->prepare("
        INSERT INTO visits (
            created_at, date_brt, hour_brt, visitor_hash, is_unique_day,
            ip_hash, device, os, browser, referrer,
            referrer_domain, screen, country, path
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?
        )
    ");
    $stmt->execute([
        $createdAt, $dateBrt, $hourBrt, $visitorHash, $isUnique,
        $ipHash, $device, $os, $browser, $referrerRaw,
        $referrerDomain, $screen, $country, $path
    ]);
} catch (Exception $e) {
    // Falha silenciosa para nunca quebrar a experiência do visitante
}

sendResponse();

function sendResponse() {
    if (isset($_GET['type']) && $_GET['type'] === 'img') {
        // Retorna GIF transparente 1x1 pixel
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    } else {
        http_response_code(204); // No Content para requisições fetch/beacon
    }
    exit;
}
