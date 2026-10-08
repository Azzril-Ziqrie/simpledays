<?php
// ==============================
// DEBUG SCRIPT - RajaOngkir Komerce API
// Cari ID Origin: Kabupaten Bogor
// ==============================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Konfigurasi
$apiKey  = 'wbQ8qEQw45504a9c0c336fa4zhGf8sdE';
$keyword = 'Klapanunggal'; // bisa diganti nama kecamatan spesifik, misal 'cibinong', 'ciawi', dll

$endpoint = 'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination?search='
            . urlencode($keyword) . '&limit=50&offset=0';

// 2. Cek ekstensi cURL
if (!function_exists('curl_init')) {
    die("Error: Ekstensi cURL belum aktif di PHP kamu! Silakan aktifkan di php.ini.");
}

// 3. Request ke API
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL            => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING       => "",
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST  => "GET",
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => array(
        "key: " . $apiKey
    ),
));

$response  = curl_exec($curl);
$httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err       = curl_error($curl);
curl_close($curl);

echo "<h2>DEBUG RAJAONGKIR - SEARCH: '" . htmlspecialchars($keyword) . "'</h2>";
echo "<p><b>Endpoint yang dipanggil:</b><br>" . htmlspecialchars($endpoint) . "</p>";
echo "<p><b>HTTP Status Code:</b> " . $httpCode . "</p>";
echo "<hr>";

// 4. Handle error cURL
if ($err) {
    echo "<h3 style='color:red;'>Oops, ada error dari cURL:</h3>";
    echo "<p>" . htmlspecialchars($err) . "</p>";
    exit;
}

// 5. Raw response
echo "<h3>1. Raw Response (teks asli dari server):</h3>";
echo "<pre style='background:#f4f4f4;padding:10px;border:1px solid #ccc;'>";
echo htmlspecialchars($response);
echo "</pre>";
echo "<hr>";

// 6. Decode JSON
$data = json_decode($response, true);

echo "<h3>2. Hasil Decode JSON:</h3>";
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<p style='color:red;'>Gagal membaca data. Error: " . json_last_error_msg() . "</p>";
    exit;
}

echo "<pre style='background:#f4f4f4;padding:10px;border:1px solid #ccc;'>";
print_r($data);
echo "</pre>";
echo "<hr>";

// 7. Cek status meta
if (isset($data['meta']['code']) && $data['meta']['code'] != 200) {
    echo "<h3 style='color:red;'>API mengembalikan error:</h3>";
    echo "<p>" . htmlspecialchars($data['meta']['message']) . "</p>";
    echo "<p>Kemungkinan penyebab: API key salah/expired, atau format header salah (harus lowercase 'key').</p>";
    exit;
}

if (empty($data['data'])) {
    echo "<h3 style='color:orange;'>Data kosong.</h3>";
    echo "<p>Keyword '$keyword' tidak ketemu. Coba keyword lebih spesifik, misal nama kecamatan (contoh: 'cibinong', 'ciawi', 'leuwiliang').</p>";
    exit;
}

// 8. Filter khusus yang city_name mengandung "KABUPATEN BOGOR"
echo "<h3>3. Filter Hasil - Khusus 'KABUPATEN BOGOR':</h3>";
$found = false;

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;'>";
echo "<tr style='background:#eee;'>
        <th>ID (pakai ini utk origin)</th>
        <th>Label Lengkap</th>
        <th>Kecamatan</th>
        <th>Kabupaten/Kota</th>
        <th>Provinsi</th>
        <th>Kode Pos</th>
      </tr>";

foreach ($data['data'] as $item) {
    $cityName = strtoupper($item['city_name']);
    if (strpos($cityName, 'KABUPATEN BOGOR') !== false) {
        $found = true;
        echo "<tr>";
        echo "<td><b>" . htmlspecialchars($item['id']) . "</b></td>";
        echo "<td>" . htmlspecialchars($item['label']) . "</td>";
        echo "<td>" . htmlspecialchars($item['district_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['city_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['province_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['zip_code']) . "</td>";
        echo "</tr>";
    }
}
echo "</table>";

if (!$found) {
    echo "<p style='color:orange;'>Tidak ada hasil dengan city_name 'KABUPATEN BOGOR' di keyword ini. 
    Coba search pakai nama kecamatan spesifik tempat toko/gudang kamu berada, 
    contoh: 'cibinong', 'gunung putri', 'ciawi', 'leuwiliang', dll — supaya lebih presisi 
    (bukan 'KOTA BOGOR' yang beda wilayah).</p>";
}
?>