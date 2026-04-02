<?php
$token = trim((string)($_GET['token'] ?? ''));
$expectedToken = trim((string)getenv('FOTOUPLOAD_TOKEN'));
$tokenConfigured = ($expectedToken !== '');
$tokenMissing = ($token === '');
$tokenValid = ($tokenConfigured && !$tokenMissing && hash_equals($expectedToken, $token));

$messages = [];
$errors = [];
$uploadDir = __DIR__ . '/uploads';

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['photos'])) {
        $errors[] = 'Es wurden keine Dateien übertragen.';
    } else {
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $errors[] = 'Der Upload-Ordner konnte nicht erstellt werden.';
        }

        if (empty($errors)) {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/heic' => 'heic',
                'image/heif' => 'heif',
            ];

            $maxFileSize = 20 * 1024 * 1024; // 20 MB pro Datei
            $files = $_FILES['photos'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 0;

            for ($i = 0; $i < $fileCount; $i++) {
                $originalName = (string)$files['name'][$i];
                $tmpName = (string)$files['tmp_name'][$i];
                $errorCode = (int)$files['error'][$i];
                $size = (int)$files['size'][$i];

                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($errorCode !== UPLOAD_ERR_OK) {
                    $errors[] = '"' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" konnte nicht hochgeladen werden (Fehlercode ' . $errorCode . ').';
                    continue;
                }

                if ($size <= 0 || $size > $maxFileSize) {
                    $errors[] = '"' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" ist zu gross (max. 20 MB).';
                    continue;
                }

                $mimeType = mime_content_type($tmpName);
                if (!isset($allowedTypes[$mimeType])) {
                    $errors[] = '"' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" ist kein unterstütztes Bildformat.';
                    continue;
                }

                $safeBase = pathinfo($originalName, PATHINFO_FILENAME);
                $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $safeBase);
                $safeBase = trim((string)$safeBase, '_');
                if ($safeBase === '') {
                    $safeBase = 'foto';
                }

                $extension = $allowedTypes[$mimeType];
                $targetName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $extension;
                $targetPath = $uploadDir . '/' . $targetName;

                if (!move_uploaded_file($tmpName, $targetPath)) {
                    $errors[] = '"' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" konnte nicht gespeichert werden.';
                    continue;
                }

                $messages[] = '"' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" wurde erfolgreich hochgeladen.';
            }

            if ($fileCount > 0 && empty($messages) && empty($errors)) {
                $errors[] = 'Bitte wähle mindestens ein Foto aus.';
            }
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="text fotoupload-wrap">
    <h1>Fotos hochladen</h1>
    <p>Wir wollen den Tag auch durch eure Linse erleben – und freuen uns über jedes Foto, das ihr mit uns teilt! Hier kannst du deine Bilder direkt hochladen, damit wir sie in unsere Sammlung aufnehmen können.</p>
    <?php if (!$tokenValid): ?>
        <div class="fotoupload-card">
            <div class="fotoupload-hint">
                <?php if (!$tokenConfigured): ?>
                    Der Foto-Upload ist aktuell noch nicht freigeschaltet. Bitte melde dich kurz beim Brautpaar.
                <?php elseif ($tokenMissing): ?>
                    Der Upload-Link ist unvollständig: Bitte öffne den Link mit einem gültigen Token, z. B. <strong>?token=DEIN_TOKEN</strong>.
                <?php else: ?>
                    Der Upload-Link ist ungültig. Bitte verwende den vollständigen, gültigen Link mit Token.
                <?php endif; ?>
            </div>
            <p class="fotoupload-subtle">Wenn du den Link neu brauchst, melde dich kurz beim Brautpaar.</p>
        </div>
    <?php else: ?>
        <div class="fotoupload-card">
            <?php foreach ($messages as $message): ?>
                <div class="fotoupload-alert fotoupload-ok"><?php echo $message; ?></div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="fotoupload-alert fotoupload-err"><?php echo $error; ?></div>
            <?php endforeach; ?>

            <form method="post" enctype="multipart/form-data" action="?token=<?php echo urlencode($token); ?>">
                <label for="photos" class="fotoupload-label">Bilder auswählen</label>
                <input
                    class="fotoupload-input"
                    type="file"
                    id="photos"
                    name="photos[]"
                    accept="image/*"
                    multiple
                    required
                >
                <div class="fotoupload-preview" id="preview-block" hidden>
                    <p class="fotoupload-preview-title">Deine Auswahl vor dem Upload</p>
                    <p class="fotoupload-subtle fotoupload-subtle-compact" id="preview-meta"></p>
                    <div class="fotoupload-preview-grid" id="preview-grid"></div>
                </div>
                <p class="fotoupload-subtle">Tipp: Du kannst direkt mehrere Bilder gleichzeitig markieren und senden.</p>
                <button class="fotoupload-button" type="submit">Fotos jetzt hochladen</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="/fotoupload/styles.css?v=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
<script src="/fotoupload/script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>

<?php include '../includes/footer.php'; ?>
