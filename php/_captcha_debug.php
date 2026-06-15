<?php
// ─────────────────────────────────────────────────────────────────
//  DIAGNÓSTICO DE reCAPTCHA  —  archivo temporal, BORRAR al terminar
//  Abrir en: http://localhost/PROYECTO_ADS/php/_captcha_debug.php
// ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

$SITE_KEY = '6Le59_QsAAAAAIC2rUZhr5G0gqItBk3h7-KT-JvA'; // misma del HTML
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['g-recaptcha-response'] ?? '';

    $secretCargado = defined('RECAPTCHA_SECRET') ? RECAPTCHA_SECRET : '(no definido)';

    $payload = http_build_query(['secret' => RECAPTCHA_SECRET, 'response' => $token]);
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $resultado = [
        'URL_que_usas'        => $_SERVER['HTTP_HOST'] ?? '(?)',
        'secret_cargado'      => $secretCargado === '' ? '⚠️ VACÍO (revisa el .env)' : substr($secretCargado, 0, 13) . '…(' . strlen($secretCargado) . ' chars)',
        'token_recibido'      => $token === '' ? '⚠️ VACÍO' : 'OK (' . strlen($token) . ' chars)',
        'error_de_red_cURL'   => $curlErr ?: '(ninguno)',
        'respuesta_de_Google' => json_decode($resp, true),
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico reCAPTCHA</title>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; }
        pre  { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 8px; overflow: auto; }
        .key { color: #9cdcfe; }
    </style>
</head>
<body>
    <h1>Diagnóstico reCAPTCHA</h1>
    <p>Marca el captcha y pulsa <b>Probar</b>. Manda una captura del resultado.</p>

    <form method="POST">
        <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($SITE_KEY) ?>"></div>
        <br>
        <button type="submit">Probar</button>
    </form>

    <?php if ($resultado !== null): ?>
        <h2>Resultado</h2>
        <pre><?= htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    <?php endif; ?>
</body>
</html>
