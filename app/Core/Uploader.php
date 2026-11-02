<?php
namespace App\Core;

class Uploader
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const DOC_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    private const MAX_WIDTH = 1800;

    public static function hasFile(string $field): bool
    {
        return !empty($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /** Public image: decoded and re-encoded by GD, which also resizes it and drops anything hidden inside. */
    public static function image(string $field, string $folder = 'general'): string
    {
        $file = self::validated($field, self::IMAGE_TYPES);
        $mime = $file['mime'];
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp']),
            'image/png' => @imagecreatefrompng($file['tmp']),
            'image/webp' => @imagecreatefromwebp($file['tmp']),
        };
        if (!$src) {
            throw new ValidationException([$field => 'That file could not be read as an image.']);
        }
        if (imagesx($src) > self::MAX_WIDTH) {
            $src = imagescale($src, self::MAX_WIDTH, -1, IMG_BICUBIC);
        }
        $folder = preg_replace('/[^a-z0-9_-]/', '', $folder);
        $dir = BASE_PATH . "/public/assets/uploads/$folder";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = bin2hex(random_bytes(12));
        if ($mime === 'image/png') {
            imagesavealpha($src, true);
            imagepng($src, "$dir/$name.png", 7);
            $name .= '.png';
        } else {
            imagejpeg($src, "$dir/$name.jpg", 82);
            $name .= '.jpg';
        }
        imagedestroy($src);
        return "uploads/$folder/$name";
    }

    /** Private document such as a receipt. Stored outside the web root and served through an authorized route. */
    public static function document(string $field, string $folder = 'receipts'): string
    {
        $file = self::validated($field, self::DOC_TYPES);
        $dir = BASE_PATH . "/storage/uploads/$folder";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = bin2hex(random_bytes(12)) . '.' . self::DOC_TYPES[$file['mime']];
        if (!move_uploaded_file($file['tmp'], "$dir/$name")) {
            throw new ValidationException([$field => 'The file could not be saved.']);
        }
        return "$folder/$name";
    }

    private static function validated(string $field, array $allowed): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || is_array($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {
            $tooBig = in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            throw new ValidationException([$field => $tooBig ? 'That file is too large.' : 'The upload failed. Try again.']);
        }
        $maxMb = config('app.upload_max_mb');
        if ($f['size'] > $maxMb * 1048576) {
            throw new ValidationException([$field => "Files must be {$maxMb} MB or smaller."]);
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            throw new ValidationException([$field => 'The upload failed. Try again.']);
        }
        // The browser-supplied type and name are ignored: the type comes from the file's content.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset($allowed[$mime])) {
            $names = strtoupper(implode(', ', array_unique(array_values($allowed))));
            throw new ValidationException([$field => "Upload a file of type $names."]);
        }
        return ['tmp' => $f['tmp_name'], 'mime' => $mime];
    }

    /** Removes a previously uploaded public file. Bundled site images are never deleted. */
    public static function deletePublic(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && !str_contains($path, '..')) {
            $full = BASE_PATH . '/public/assets/' . $path;
            if (is_file($full)) {
                unlink($full);
            }
        }
    }
}
