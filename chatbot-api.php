<?php
// ==========================
// Konfigurasi API Gemini
// ==========================
$api_key = 'AIzaSyA3C_cBXWBICDGl_beOKGvv90Z1eRi0dwU'; // GANTI dengan API key milikmu
$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

// ==========================
// Koneksi ke Database
// ==========================
$host = 'localhost';
$db   = 'jalanyukproject';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['reply' => 'Koneksi ke database gagal.']);
    exit;
}

// ==========================
// Terima Input dari Frontend
// ==========================
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$user_message = trim($input['message'] ?? '');

// Ambil temperature dari input (opsional, default 0.6)
$temperature = isset($input['temperature']) ? floatval($input['temperature']) : 0.6;

// Validasi temperature (0.0 - 2.0)
if ($temperature < 0.0) $temperature = 0.0;
if ($temperature > 2.0) $temperature = 2.0;

session_start();
$session_id = session_id();

if (empty($user_message)) {
    echo json_encode(['reply' => 'Pesan kosong tidak bisa dikirim.']);
    exit;
}

// ==========================
// Instruksi ke Gemini
// ==========================
$instruction = <<<EOD
Kamu adalah asisten perjalanan digital. Jawablah hanya jika pertanyaan berkaitan dengan:
1. Harga tiket pesawat antar kota di Indonesia → tampilkan daftar maskapai (contoh: Garuda Indonesia, Citilink, Lion Air, AirAsia) beserta estimasi harga.
2. Biaya penginapan → tampilkan rata-rata harga hotel dari kelas ekonomi hingga premium di lokasi yang ditanyakan.
3. Estimasi biaya perjalanan pribadi atau kelompok ke suatu daerah (akomodasi, makan, transportasi lokal).
4. Rekomendasi tempat wisata di daerah tertentu → berikan nama tempat, deskripsi singkat, dan harga tiket masuk (atau tulis "Gratis").

Tolak dengan sopan jika pertanyaan di luar topik tersebut. Jawaban maksimal 500 kata. Tulis dengan bahasa Indonesia yang sopan, jelas, dan mudah dipahami. Gunakan format daftar jika memungkinkan.

Disclaimer: *Ini hanyalah saran berbasis data publik dan bukan nasihat keuangan profesional.*
EOD;

// ==========================
// Siapkan Payload ke Gemini (Sederhana dulu)
// ==========================
$payload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $instruction . "\n\nPertanyaan pengguna: " . $user_message]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => $temperature,
        'maxOutputTokens' => 1000
    ]
]);

// ==========================
// Kirim ke Gemini API
// ==========================
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-goog-api-key: ' . $api_key
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 30,              // Timeout 30 detik
    CURLOPT_CONNECTTIMEOUT => 10        // Connection timeout 10 detik
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// ==========================
// Cek hasil API dengan Debug Info
// ==========================
if ($httpCode !== 200 || !$response) {
    // Log error untuk debugging
    error_log("Gemini API Error - HTTP Code: $httpCode, Error: $error, Response: $response");
    
    echo json_encode([
        'reply' => 'Maaf, terjadi kesalahan sementara. Silakan coba lagi.',
        'error' => false, // Jangan tampilkan detail error ke user
        'temperature_used' => $temperature
    ]);
    exit;
}

$result = json_decode($response, true);

// Cek jika response JSON tidak valid
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON Decode Error: " . json_last_error_msg() . ", Raw Response: " . $response);
    echo json_encode(['reply' => 'Maaf, terjadi kesalahan dalam memproses respons.']);
    exit;
}

$bot_reply_raw = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya belum bisa menjawab.';

// ==========================
// Batasi 500 kata
// ==========================
$words = explode(' ', $bot_reply_raw);
if (count($words) > 500) {
    $words = array_slice($words, 0, 500);
    $bot_reply_raw = implode(' ', $words) . '... (dipotong karena melebihi 500 kata)';
}

// ==========================
// Tambahkan disclaimer jika belum ada
// ==========================
if (stripos($bot_reply_raw, 'nasihat keuangan') === false) {
    $bot_reply_raw .= "\n\n*Ini hanyalah saran berbasis data publik dan bukan nasihat keuangan profesional.*";
}

// ==========================
// Konversi ke HTML (Markdown → HTML)
// ==========================
require_once 'Parsedown.php';
$Parsedown = new Parsedown();
$Parsedown->setSafeMode(true);
$html_reply = $Parsedown->text($bot_reply_raw);

// ==========================
// Simpan ke Database (Fallback ke struktur lama jika kolom baru belum ada)
// ==========================
$stmt = $conn->prepare("INSERT INTO chatbot_logs (session_id, sender, message) VALUES (?, ?, ?)");
if ($stmt) {
    // Simpan pesan user
    $sender = 'user';
    $msg = $user_message;
    $stmt->bind_param("sss", $session_id, $sender, $msg);
    $stmt->execute();

    // Simpan jawaban bot
    $sender = 'bot';
    $msg = $bot_reply_raw;
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// ==========================
// Kirim ke Frontend dengan Info Temperature
// ==========================
echo json_encode([
    'reply' => $html_reply,
    'temperature_used' => $temperature,
    'model_info' => [
        'model' => 'gemini-2.0-flash',
        'temperature' => $temperature,
        'topP' => 0.8,
        'topK' => 40,
        'maxTokens' => 1000
    ]
]);
?>