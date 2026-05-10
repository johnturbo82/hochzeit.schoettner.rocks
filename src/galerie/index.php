<?php
$uploadsDir = realpath(__DIR__ . '/../fotoupload/uploads');
$webUploadPrefix = '/fotoupload/uploads';
$images = [];

if ($uploadsDir !== false && is_dir($uploadsDir)) {
    $items = scandir($uploadsDir);

    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $filePath = $uploadsDir . DIRECTORY_SEPARATOR . $item;
            if (!is_file($filePath)) {
                continue;
            }

            $extension = strtolower((string)pathinfo($item, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'heic', 'heif'], true)) {
                continue;
            }

            $images[] = [
                'name' => $item,
                'url' => $webUploadPrefix . '/' . rawurlencode($item),
                'mtime' => (int)filemtime($filePath),
            ];
        }

        usort($images, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime'];
        });
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="text galerie-wrap">
    <h1>Galerie</h1>
    <p class="galerie-intro">Hier findet ihr alle Fotos, die bisher hochgeladen wurden. Tippt auf ein Thumbnail, um das Bild groß anzusehen.</p>

    <?php if (empty($images)): ?>
        <div class="galerie-empty">
            Noch keine Fotos vorhanden. Sobald Uploads eingehen, erscheinen sie hier automatisch.
        </div>
    <?php else: ?>
        <div class="galerie-grid" id="galerie-grid">
            <?php foreach ($images as $index => $image): ?>
                <button
                    class="galerie-item"
                    type="button"
                    data-fullsrc="<?php echo htmlspecialchars($image['url'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-filename="<?php echo htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-index="<?php echo (int)$index; ?>"
                    aria-label="Foto <?php echo (int)($index + 1); ?> vergroessern"
                >
                    <img
                        class="galerie-thumb"
                        src="<?php echo htmlspecialchars($image['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Foto <?php echo (int)($index + 1); ?>"
                        loading="lazy"
                        decoding="async"
                    >
                </button>
            <?php endforeach; ?>
        </div>

        <div class="galerie-lightbox" id="galerie-lightbox" hidden>
            <div class="galerie-actions">
                <a class="galerie-download" id="galerie-download" href="#" download>Download</a>
                <button class="galerie-close" id="galerie-close" type="button" aria-label="Bildansicht schliessen">Schliessen</button>
            </div>
            <img class="galerie-full" id="galerie-full" src="" alt="Grossansicht">
            <p class="galerie-caption" id="galerie-caption"></p>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="/galerie/styles.css?v=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
<script src="/galerie/script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>

<?php include '../includes/footer.php'; ?>
