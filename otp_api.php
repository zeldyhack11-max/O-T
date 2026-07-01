<?php
/**
 * Tek Dosya PHP OTP API Entegrasyonu + Admin Token Paneli (Sürücü Gerektirmeyen Sürüm)
 * URL: https://otp.xclusor.workers.dev/
 */

// --- 1. AYARLAR VE GÜVENLİ DOSYA DEPOSU KURULUMU ---
define('ADMIN_PASSWORD', 'ZeldyH€RE123+'); // Admin paneli şifreniz (Güvenliğiniz için değiştirin)
define('TOKEN_FILE', __DIR__ . '/api_storage.php'); // Tokenların saklanacağı güvenli dosya

// Depolama dosyasını ilk kez oluşturma (Doğrudan erişime karşı korumalı)
if (!file_exists(TOKEN_FILE)) {
    file_put_contents(TOKEN_FILE, "<?php die('Erişim engellendi.'); ?>\n" . json_encode([]));
}

// Tokenları Okuma Fonksiyonu
function get_tokens() {
    if (!file_exists(TOKEN_FILE)) return [];
    $content = file_get_contents(TOKEN_FILE);
    $json_start = strpos($content, '?>') + 2;
    $json_data = substr($content, $json_start);
    $decoded = json_decode($json_data, true);
    return is_array($decoded) ? $decoded : [];
}

// Tokenları Kaydetme Fonksiyonu
function save_tokens($tokens) {
    $content = "<?php die('Erişim engellendi.'); ?>\n" . json_encode($tokens, JSON_PRETTY_PRINT);
    file_put_contents(TOKEN_FILE, $content);
}

// --- 2. API SERVİS SINIFI ---
class OtpService {
    private $apiUrl = "https://otp.xclusor.workers.dev/";

    public function fetchSmsData() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}

// --- 3. URL YÖNLENDİRME (ROUTING) ---
$action = isset($_GET['action']) ? $_GET['action'] : 'api';

// A) API ÇIKTIŞI (Gelen İstekleri Karşılama)
if ($action === 'api') {
    header('Content-Type: application/json; charset=utf-8');
    $telegramLink = 't.me/Zeldy_here';
    
    $receivedKey = isset($_GET['key']) ? $_GET['key'] : '';

    if (empty($receivedKey)) {
        echo json_encode(['status' => 'error', 'message' => 'API key parametresi eksik.', 'APİ_OWNER' => $telegramLink]);
        exit;
    }

    // Token kontrolü
    $tokens = get_tokens();
    $validToken = null;
    foreach ($tokens as $t) {
        if ($t['token'] === $receivedKey && $t['is_active'] == 1) {
            $validToken = $t;
            break;
        }
    }

    if (!$validToken) {
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz veya pasif API key.', 'tAPİ_OWNER' => $telegramLink]);
        exit;
    }

    // Doğrulama başarılı, veriyi çekip formatlıyoruz
    $otp = new OtpService();
    $rawSmsList = $otp->fetchSmsData();
    $processedData = [];

    foreach ($rawSmsList as $sms) {
        if (is_array($sms)) {
            $processedData[] = [
                'title'     => isset($sms[0]) ? $sms[0] : null,
                'phone'     => isset($sms[1]) ? $sms[1] : null,
                'message'   => isset($sms[2]) ? $sms[2] : null,
                'date'      => isset($sms[3]) ? $sms[3] : null,
                'APİ_OWNER'  => $telegramLink
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'results' => $processedData,
        'APİ_OWNER' => $telegramLink
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// B) ADMIN PANELİ İŞLEMLERİ (Oturum Yönetimi)
session_start();
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged']);
    header('Location: ?action=admin');
    exit;
}

if (isset($_POST['login'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged'] = true;
    } else {
        $login_error = "Hatalı şifre girdiniz.";
    }
}

// Token Ekleme
if (isset($_POST['add_token']) && isset($_SESSION['admin_logged'])) {
    $tokens = get_tokens();
    $newToken = bin2hex(random_bytes(16));
    
    $tokens[] = [
        'id' => time(),
        'token' => $newToken,
        'description' => htmlspecialchars($_POST['description']),
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    save_tokens($tokens);
    header('Location: ?action=admin');
    exit;
}

// Token Silme
if (isset($_GET['delete']) && isset($_SESSION['admin_logged'])) {
    $tokens = get_tokens();
    $targetId = (int)$_GET['delete'];
    
    $tokens = array_filter($tokens, function($t) use ($targetId) {
        return $t['id'] !== $targetId;
    });
    
    save_tokens(array_values($tokens));
    header('Location: ?action=admin');
    exit;
}

// Durum Değiştirme (Aktif/Pasif)
if (isset($_GET['toggle']) && isset($_SESSION['admin_logged'])) {
    $tokens = get_tokens();
    $targetId = (int)$_GET['toggle'];
    
    foreach ($tokens as &$t) {
        if ($t['id'] === $targetId) {
            $t['is_active'] = $t['is_active'] == 1 ? 0 : 1;
        }
    }
    
    save_tokens($tokens);
    header('Location: ?action=admin');
    exit;
}
?>

<!-- C) ADMIN PANELİ HTML ARAYÜZÜ -->
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>API Key Yönetim Paneli</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f9; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; color: white; font-size: 14px; }
        .btn-green { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-passive { color: #dc3545; font-weight: bold; }
        .login-box { max-width: 300px; margin: 100px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        input[type="password"], input[type="text"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .flex-space { display: flex; justify-content: space-between; align-items: center; }
        code { background: #e9ecef; padding: 3px 6px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['admin_logged'])): ?>
    <div class="login-box">
        <h3>Yönetici Girişi</h3>
        <?php if(isset($login_error)) echo "<p style='color:red;'>$login_error</p>"; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Yönetici Şifresi" required>
            <button type="submit" name="login" class="btn btn-green" style="width:100%; padding:10px;">Giriş Yap</button>
        </form>
    </div>
<?php else: ?>
    <div class="container">
        <div class="flex-space">
            <h2>API Key Yönetimi</h2>
            <a href="?logout=1" class="btn btn-secondary">Çıkış Yap</a>
        </div>

        <form method="POST" style="margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 6px;">
            <h4 style="margin:0 0 10px 0;">Yeni API Key Üret</h4>
            <div style="display: flex; gap: 10px;">
                <input type="text" name="description" placeholder="Açıklama (Örn: Ahmet Kullanıcısı)" required style="margin:0; flex:1;">
                <button type="submit" name="add_token" class="btn btn-green">Rastgele Key Oluştur</button>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Açıklama</th>
                    <th>API Key (Kullanım Şekli)</th>
                    <th>Durum</th>
                    <th>Oluşturulma Tarihi</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tokens = get_tokens();
                
                if (empty($tokens)) {
                    echo "<tr><td colspan='5' style='text-align:center;'>Henüz oluşturulmuş bir API Key bulunmuyor.</td></tr>";
                }

                foreach ($tokens as $t) {
                    $apiUrlExample = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]?key=" . $t['token'];
                    $statusClass = $t['is_active'] ? 'status-active' : 'status-passive';
                    $statusText = $t['is_active'] ? 'Aktif' : 'Pasif';
                    $toggleText = $t['is_active'] ? 'Durdur' : 'Aktif Et';
                    
                    echo "<tr>";
                    echo "<td><strong>{$t['description']}</strong></td>";
                    echo "<td><code>{$apiUrlExample}</code></td>";
                    echo "<td><span class='{$statusClass}'>{$statusText}</span></td>";
                    echo "<td>{$t['created_at']}</td>";
                    echo "<td>
                            <a href='?action=admin&toggle={$t['id']}' class='btn btn-secondary' style='font-size:12px;'>{$toggleText}</a>
                            <a href='?action=admin&delete={$t['id']}' class='btn btn-danger' style='font-size:12px;' onclick='return confirm(\"Bu keyi silmek istediğinize emin misiniz?\")'>Sil</a>
                          </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

</body>
</html>
