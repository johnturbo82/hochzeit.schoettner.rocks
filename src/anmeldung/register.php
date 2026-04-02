<?php
die("Anmeldung ist abgeschlossen. Vielen Dank für eure Anmeldungen!");
// Mail configuration comes from environment variables (.env / docker compose)
$gmailUser = trim((string)getenv('GMAIL_USER'));
$gmailPassword = trim((string)getenv('GMAIL_PASSWORD'));
$rawRecipients = trim((string)getenv('MAIL_RECIPIENTS'));

/**
 * Expected format:
 * MAIL_RECIPIENTS=email1@example.com|Name Eins;email2@example.com|Name Zwei
 */
function parseRecipients(string $value): array
{
    $result = [];
    if ($value === '') {
        return $result;
    }

    $pairs = explode(';', $value);
    foreach ($pairs as $pair) {
        $pair = trim($pair);
        if ($pair === '') {
            continue;
        }

        $parts = explode('|', $pair, 2);
        $email = trim($parts[0] ?? '');
        $name = trim($parts[1] ?? '');
        if ($email === '') {
            continue;
        }

        $result[] = [
            'email' => $email,
            'name' => $name,
        ];
    }

    return $result;
}

$recipients = parseRecipients($rawRecipients);

// PHPMailer autoload
require_once '/var/www/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;
use Throwable;

/**
 * Persists the registration in a local SQLite database.
 */
function saveRegistrationToSqlite(array $payload): ?string
{
    try {
        $dbDir = dirname(__DIR__) . '/data';
        if (!is_dir($dbDir) && !mkdir($dbDir, 0755, true) && !is_dir($dbDir)) {
            return 'Datenverzeichnis konnte nicht erstellt werden.';
        }

        $dbPath = $dbDir . '/registrations.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE IF NOT EXISTS registrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            vorname TEXT NOT NULL,
            nachname TEXT NOT NULL,
            essen TEXT,
            song TEXT,
            kuchen TEXT,
            allergien TEXT,
            transport TEXT,
            transport_welches TEXT,
            sonstiges TEXT
        )');

        $stmt = $pdo->prepare('INSERT INTO registrations (
            vorname, nachname, essen, song, kuchen, allergien, transport, transport_welches, sonstiges
        ) VALUES (
            :vorname, :nachname, :essen, :song, :kuchen, :allergien, :transport, :transport_welches, :sonstiges
        )');

        $stmt->execute([
            ':vorname' => $payload['vorname'],
            ':nachname' => $payload['nachname'],
            ':essen' => $payload['essen'],
            ':song' => $payload['song'],
            ':kuchen' => $payload['kuchen'],
            ':allergien' => $payload['allergien'],
            ':transport' => $payload['transport'],
            ':transport_welches' => $payload['transport_welches'],
            ':sonstiges' => $payload['sonstiges'],
        ]);

        return null;
    } catch (Throwable $e) {
        error_log('SQLite-Speicherung fehlgeschlagen: ' . $e->getMessage());
        return 'Speicherung in der Datenbank ist aktuell nicht möglich.';
    }
}

header('Content-Type: application/json');

if ($gmailUser === '' || $gmailPassword === '' || empty($recipients)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Mail-Konfiguration unvollständig. Bitte Admin kontaktieren.',
    ]);
    exit;
}

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// Receive and validate form fields
$vorname = trim($_POST['vorname'] ?? '');
$nachname = trim($_POST['nachname'] ?? '');
$essen = $_POST['essen'] ?? [];
$song = trim($_POST['song'] ?? '');
$kuchen = trim($_POST['kuchen'] ?? '');
$allergien = trim($_POST['allergien'] ?? '');
$transport = $_POST['transport'] ?? 'nein';
$transport_welches = trim($_POST['transport_welches'] ?? '');
$sonstiges = trim($_POST['sonstiges'] ?? '');

// Validate required fields
if (empty($vorname) || empty($nachname) || empty($song)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bitte alle Pflichtfelder ausfüllen']);
    exit;
}

// Convert selected food options array to string
$essen_text = !empty($essen) ? implode(', ', $essen) : 'Keine Auswahl';

// Write data to SQLite (must not block email sending)
$dbWarning = saveRegistrationToSqlite([
    'vorname' => $vorname,
    'nachname' => $nachname,
    'essen' => $essen_text,
    'song' => $song,
    'kuchen' => $kuchen,
    'allergien' => $allergien,
    'transport' => $transport,
    'transport_welches' => $transport_welches,
    'sonstiges' => $sonstiges,
]);

// Build email content
$email_body = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        h2 { color: #4a5568; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #2d3748; }
        .value { margin-left: 10px; }
    </style>
</head>
<body>
    <h2>Neue Hochzeitsanmeldung</h2>
    
    <div class='field'>
        <span class='label'>Name:</span>
        <span class='value'>" . htmlspecialchars($vorname) . " " . htmlspecialchars($nachname) . "</span>
    </div>
    
    <div class='field'>
        <span class='label'>Essen:</span>
        <span class='value'>" . htmlspecialchars($essen_text) . "</span>
    </div>
    
    <div class='field'>
        <span class='label'>Songwunsch:</span>
        <span class='value'>" . htmlspecialchars($song) . "</span>
    </div>
";

if (!empty($kuchen)) {
    $email_body .= "
    <div class='field'>
        <span class='label'>Kuchen/Snack:</span>
        <span class='value'>" . htmlspecialchars($kuchen) . "</span>
    </div>
    ";
}

if (!empty($allergien)) {
    $email_body .= "
    <div class='field'>
        <span class='label'>Allergien:</span>
        <span class='value'>" . htmlspecialchars($allergien) . "</span>
    </div>
    ";
}

$email_body .= "
    <div class='field'>
        <span class='label'>Transport zum Hotel:</span>
        <span class='value'>" . htmlspecialchars($transport) . "</span>
    </div>
";

if ($transport === 'ja' && !empty($transport_welches)) {
    $email_body .= "
    <div class='field'>
        <span class='label'>Hotel/Infos:</span>
        <span class='value'>" . htmlspecialchars($transport_welches) . "</span>
    </div>
    ";
}

if (!empty($sonstiges)) {
    $email_body .= "
    <div class='field'>
        <span class='label'>Sonstiges:</span>
        <span class='value'>" . nl2br(htmlspecialchars($sonstiges)) . "</span>
    </div>
    ";
}

$email_body .= "
</body>
</html>
";

// Initialize PHPMailer
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmailUser;
    $mail->Password   = $gmailPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    
    // Anti-spam settings
    $mail->XMailer = ' '; // Hide PHPMailer version
    $mail->Priority = 3; // Normal priority

    // Sender and recipients
    $mail->setFrom($gmailUser, 'Hochzeit Schöttner');
    
    // Add all recipients
    foreach ($recipients as $recipient) {
        $mail->addAddress($recipient['email'], $recipient['name']);
    }
    
    $mail->addReplyTo($gmailUser, 'Hochzeit Schöttner');
    
    // Additional headers for better deliverability
    $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
    $mail->addCustomHeader('X-Priority', '3');
    $mail->addCustomHeader('Importance', 'Normal');

    // Message content
    $mail->isHTML(true);
    $mail->Subject = 'Hochzeitsanmeldung - ' . $vorname . ' ' . $nachname;
    $mail->Body    = $email_body;
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $email_body));

    // Send email
    $mail->send();
    
    $responseMessage = 'Vielen Dank für deine Anmeldung! Wir haben deine Daten erhalten.';
    if ($dbWarning !== null) {
        $responseMessage .= ' Hinweis: ' . $dbWarning;
    }

    echo json_encode([
        'success' => true,
        'message' => $responseMessage,
    ]);
    
} catch (Exception $e) {
    error_log("E-Mail konnte nicht gesendet werden. Fehler: {$mail->ErrorInfo}");
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Es gab ein Problem beim Versenden. Bitte versuche es später erneut.'
    ]);
}
