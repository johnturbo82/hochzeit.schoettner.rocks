<?php
$token = trim((string)($_GET['token'] ?? ''));
$expectedToken = trim((string)getenv('FOTOUPLOAD_TOKEN'));
$tokenConfigured = ($expectedToken !== '');
$tokenMissing = ($token === '');
$tokenValid = ($tokenConfigured && !$tokenMissing && hash_equals($expectedToken, $token));

$messages = [];
$errors = [];
$uploadDir = __DIR__ . '/uploads';

function normalizeMimeType(string $mimeType): string
{
    $map = [
        'image/jpg' => 'image/jpeg',
        'image/pjpeg' => 'image/jpeg',
        'image/x-png' => 'image/png',
    ];

    return $map[$mimeType] ?? $mimeType;
}

function detectImageMimeType(string $filePath, string $originalName = ''): string
{
    $mimeType = '';

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = @finfo_file($finfo, $filePath);
            if (is_string($detected)) {
                $mimeType = trim($detected);
            }
            finfo_close($finfo);
        }
    }

    if ($mimeType === '' && function_exists('mime_content_type')) {
        $detected = @mime_content_type($filePath);
        if (is_string($detected)) {
            $mimeType = trim($detected);
        }
    }

    if ($mimeType === '' && function_exists('getimagesize')) {
        $size = @getimagesize($filePath);
        if (is_array($size) && isset($size['mime']) && is_string($size['mime'])) {
            $mimeType = trim($size['mime']);
        }
    }

    $mimeType = normalizeMimeType($mimeType);

    if ($mimeType === '' || $mimeType === 'application/octet-stream') {
        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $extensionMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
        ];
        $mimeType = $extensionMap[$extension] ?? $mimeType;
    }

    return normalizeMimeType($mimeType);
}

function parseIniBytes(string $value): int
{
    $trimmed = trim($value);
    if ($trimmed === '' || $trimmed === '-1') {
        return -1;
    }

    $unit = strtolower(substr($trimmed, -1));
    $number = (int)$trimmed;

    switch ($unit) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
            break;
    }

    return $number;
}

function hasEnoughMemoryForImage(int $width, int $height): bool
{
    $memoryLimit = parseIniBytes((string)ini_get('memory_limit'));
    if ($memoryLimit === -1) {
        return true;
    }

    // Konservative Schätzung: Original + Thumbnail + GD-Overhead.
    $estimatedBytes = (int)($width * $height * 8);
    $headroom = 32 * 1024 * 1024;
    $usage = memory_get_usage(true);

    return ($usage + $estimatedBytes + $headroom) < $memoryLimit;
}

function rotateImageResource($image, int $degrees)
{
    if (!function_exists('imagerotate')) {
        return $image;
    }

    $rotated = @imagerotate($image, $degrees, 0);
    if ($rotated === false) {
        return $image;
    }

    imagedestroy($image);
    return $rotated;
}

function applyExifOrientation($image, string $sourcePath, string $mimeType)
{
    if ($mimeType !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($sourcePath);
    if (!is_array($exif) || !isset($exif['Orientation'])) {
        return $image;
    }

    $orientation = (int)$exif['Orientation'];

    switch ($orientation) {
        case 2:
            if (function_exists('imageflip')) {
                imageflip($image, IMG_FLIP_HORIZONTAL);
            }
            break;
        case 3:
            $image = rotateImageResource($image, 180);
            break;
        case 4:
            if (function_exists('imageflip')) {
                imageflip($image, IMG_FLIP_VERTICAL);
            }
            break;
        case 5:
            if (function_exists('imageflip')) {
                imageflip($image, IMG_FLIP_HORIZONTAL);
            }
            $image = rotateImageResource($image, -90);
            break;
        case 6:
            $image = rotateImageResource($image, -90);
            break;
        case 7:
            if (function_exists('imageflip')) {
                imageflip($image, IMG_FLIP_HORIZONTAL);
            }
            $image = rotateImageResource($image, 90);
            break;
        case 8:
            $image = rotateImageResource($image, 90);
            break;
    }

    return $image;
}

function createThumbnail(string $sourcePath, string $targetPath, string $mimeType, int $maxEdge = 640): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $size = @getimagesize($sourcePath);
    if ($size === false) {
        return false;
    }

    $sourceWidth = (int)$size[0];
    $sourceHeight = (int)$size[1];
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        return false;
    }

    if (!hasEnoughMemoryForImage($sourceWidth, $sourceHeight)) {
        return false;
    }

    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = @imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                return false;
            }
            $sourceImage = @imagecreatefromwebp($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = @imagecreatefromgif($sourcePath);
            break;
        case 'image/bmp':
            if (!function_exists('imagecreatefrombmp')) {
                return false;
            }
            $sourceImage = @imagecreatefrombmp($sourcePath);
            break;
        default:
            return false;
    }

    if ($sourceImage === false) {
        return false;
    }

    $sourceImage = applyExifOrientation($sourceImage, $sourcePath, $mimeType);

    $sourceWidth = (int)imagesx($sourceImage);
    $sourceHeight = (int)imagesy($sourceImage);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($sourceImage);
        return false;
    }

    $scale = min($maxEdge / $sourceWidth, $maxEdge / $sourceHeight, 1);
    $thumbWidth = (int)max(1, floor($sourceWidth * $scale));
    $thumbHeight = (int)max(1, floor($sourceHeight * $scale));

    $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
    if ($thumbImage === false) {
        imagedestroy($sourceImage);
        return false;
    }

    if ($mimeType === 'image/png' || $mimeType === 'image/gif' || $mimeType === 'image/webp') {
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 0, 0, 0, 127);
        imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    }

    $resampled = imagecopyresampled(
        $thumbImage,
        $sourceImage,
        0,
        0,
        0,
        0,
        $thumbWidth,
        $thumbHeight,
        $sourceWidth,
        $sourceHeight
    );

    if ($resampled === false) {
        imagedestroy($thumbImage);
        imagedestroy($sourceImage);
        return false;
    }

    switch ($mimeType) {
        case 'image/jpeg':
            $written = imagejpeg($thumbImage, $targetPath, 82);
            break;
        case 'image/png':
            $written = imagepng($thumbImage, $targetPath, 7);
            break;
        case 'image/webp':
            if (!function_exists('imagewebp')) {
                $written = false;
                break;
            }
            $written = imagewebp($thumbImage, $targetPath, 82);
            break;
        case 'image/gif':
            $written = imagegif($thumbImage, $targetPath);
            break;
        case 'image/bmp':
            if (!function_exists('imagebmp')) {
                $written = false;
                break;
            }
            $written = imagebmp($thumbImage, $targetPath);
            break;
        default:
            $written = false;
    }

    imagedestroy($thumbImage);
    imagedestroy($sourceImage);

    return $written === true;
}

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
                'image/gif' => 'gif',
                'image/bmp' => 'bmp',
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

                $mimeType = detectImageMimeType($tmpName, $originalName);
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

                $targetBase = pathinfo($targetName, PATHINFO_FILENAME);
                $thumbName = $targetBase . '_tn.' . $extension;
                $thumbPath = $uploadDir . '/' . $thumbName;
                $thumbnailCreated = createThumbnail($targetPath, $thumbPath, $mimeType);
                if (!$thumbnailCreated) {
                    $messages[] = '"' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" wurde hochgeladen, aber kein Thumbnail erstellt (Server-Unterstützung fehlt oder Format nicht unterstützt).';
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
