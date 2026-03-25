<?php
die("Anmeldung ist abgeschlossen. Vielen Dank für eure Anmeldungen!");
// Gmail-Konfiguration (Bitte anpassen!)
define('GMAIL_USER', 'email');
define('GMAIL_PASSWORD', 'passwort'); // App-Passwort von Google

// Empfänger-Adressen (mehrere möglich)
$recipients = [
    ['email' => 'test@test.de', 'name' => 'Test Hans'],
];

// Autoload für PHPMailer
require_once '/var/www/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;
use Throwable;

/**
 * Persistiert die Anmeldung in einer lokale SQLite-DB.
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

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// Formularfelder empfangen und validieren
$vorname = trim($_POST['vorname'] ?? '');
$nachname = trim($_POST['nachname'] ?? '');
$essen = $_POST['essen'] ?? [];
$song = trim($_POST['song'] ?? '');
$kuchen = trim($_POST['kuchen'] ?? '');
$allergien = trim($_POST['allergien'] ?? '');
$transport = $_POST['transport'] ?? 'nein';
$transport_welches = trim($_POST['transport_welches'] ?? '');
$sonstiges = trim($_POST['sonstiges'] ?? '');

// Pflichtfelder prüfen
if (empty($vorname) || empty($nachname) || empty($song)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bitte alle Pflichtfelder ausfüllen']);
    exit;
}

// Essen-Array in String umwandeln
$essen_text = !empty($essen) ? implode(', ', $essen) : 'Keine Auswahl';

// Daten in SQLite schreiben (darf die E-Mail nicht blockieren)
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

// E-Mail-Inhalt erstellen
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

// PHPMailer initialisieren
$mail = new PHPMailer(true);

try {
    // Server-Einstellungen
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = GMAIL_USER;
    $mail->Password   = GMAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    
    // Anti-Spam Einstellungen
    $mail->XMailer = ' '; // Versteckt PHPMailer Version
    $mail->Priority = 3; // Normal priority

    // Absender und Empfänger
    $mail->setFrom(GMAIL_USER, 'Hochzeit Schöttner');
    
    // Alle Empfänger hinzufügen
    foreach ($recipients as $recipient) {
        $mail->addAddress($recipient['email'], $recipient['name']);
    }
    
    $mail->addReplyTo(GMAIL_USER, 'Hochzeit Schöttner');
    
    // Zusätzliche Header für bessere Zustellbarkeit
    $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
    $mail->addCustomHeader('X-Priority', '3');
    $mail->addCustomHeader('Importance', 'Normal');

    // Inhalt
    $mail->isHTML(true);
    $mail->Subject = 'Hochzeitsanmeldung - ' . $vorname . ' ' . $nachname;
    $mail->Body    = $email_body;
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $email_body));

    // E-Mail senden
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
