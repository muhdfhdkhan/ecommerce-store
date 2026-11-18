<?php
// includes/uploads.php — image upload pipeline (SECURITY.md §12):
// 1) size + real-mime checks, 2) GD re-encode strips any embedded payloads,
// 3) random filename under public/uploads/<subdir>/YYYY-MM/, 4) never trust original name.
declare(strict_types=1);

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5 MB per image
const UPLOAD_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Validate + re-encode one uploaded image.
 * Returns the relative web path (e.g. products/2026-11/ab12cd34.jpg).
 * Throws RuntimeException with a safe message on any rejection.
 */
function image_upload_store(array $file, string $subdir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Image must be under 5 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Invalid upload.');
    }

    // Real mime from content — extension/ClientMimeType never trusted (SECURITY.md §12.1).
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
        throw new RuntimeException('Only JPG, PNG or WebP images are allowed.');
    }

    $img = imagecreatefromstring((string)file_get_contents($file['tmp_name']));
    if ($img === false) {
        throw new RuntimeException('Image could not be processed.');
    }

    // Hard cap dimensions to prevent decompression bombs.
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 10 || $h < 10 || $w > 6000 || $h > 6000) {
        imagedestroy($img);
        throw new RuntimeException('Image dimensions must be 10–6000 px.');
    }

    // Re-encode to JPEG (strips EXIF/metadata/payloads). Preserve alpha via
    // compositing onto white for PNG/WebP transparency.
    $out = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefill($out, 0, 0, $white);
    imagecopy($out, $img, 0, 0, 0, 0, $w, $h);
    imagedestroy($img);

    $relDir = $subdir . '/' . gmdate('Y-m');
    $absDir = PUBLIC_PATH . '/uploads/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        imagedestroy($out);
        throw new RuntimeException('Storage unavailable.');
    }

    $name = bin2hex(random_bytes(12)) . '.jpg';
    $ok = imagejpeg($out, $absDir . '/' . $name, 85);
    imagedestroy($out);
    if (!$ok) {
        throw new RuntimeException('Could not save the image.');
    }
    return $relDir . '/' . $name;
}

/** Delete an uploaded image by relative path (path-traversal guarded). */
function image_upload_delete(string $relPath): void
{
    $base = realpath(PUBLIC_PATH . '/uploads');
    $target = realpath(PUBLIC_PATH . '/uploads/' . $relPath);
    if ($base === false || $target === false || !str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
        return; // outside uploads dir — ignore silently
    }
    @unlink($target);
}

/** Fetch a product's images (primary first). */
function product_images_all(int $productId): array
{
    $st = db()->prepare(
        'SELECT id, file_path, alt_text, is_primary FROM product_images
         WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $st->execute([':id' => $productId]);
    return $st->fetchAll();
}
