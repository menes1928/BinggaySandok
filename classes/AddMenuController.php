<?php
require_once __DIR__ . '/database.php';

class AddMenuController {
    private database $db;

    public function __construct() {
        $this->db = new database();
    }

    public function validate(array $input, array $files = []): array {
        $errors = [];
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
    $pax = trim($input['pax'] ?? '');
        $price = $input['price'] ?? '';
        $availability = $input['availability'] ?? '1';

        if ($name === '') { $errors['name'] = 'Name is required.'; }
        if ($price === '' || !is_numeric($price) || floatval($price) < 0) {
            $errors['price'] = 'Enter a valid non-negative price.';
        }
        if ($availability !== '0' && $availability !== '1' && $availability !== 0 && $availability !== 1) {
            $errors['availability'] = 'Invalid availability.';
        }
        // PAX: allow 6-8, 10-15, or "per pieces" or "N pieces"
        if ($pax === '') {
            $errors['pax'] = 'Please select a PAX option.';
        }

        // Photo validation (optional but if present, must be image)
        $photoName = null;
        if (isset($files['photo']) && is_array($files['photo']) && ($files['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $err = $files['photo']['error'] ?? UPLOAD_ERR_OK;
            if ($err !== UPLOAD_ERR_OK) {
                $errors['photo'] = 'Image upload failed.';
            } else {
                $tmp = $files['photo']['tmp_name'];
                $mime = mime_content_type($tmp);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($allowed[$mime])) {
                    $errors['photo'] = 'Only JPG, PNG, or WebP are allowed.';
                } else {
                    $ext = $allowed[$mime];
                    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower(pathinfo($files['photo']['name'], PATHINFO_FILENAME)));
                    $photoName = uniqid() . '_' . $safeBase . '.' . $ext;
                }
            }
        }

        return [
            'data' => [
                'name' => $name,
                'description' => $description,
                'pax' => $pax,
                'price' => is_numeric($price) ? number_format((float)$price, 2, '.', '') : $price,
                'availability' => (int)$availability === 1 ? 1 : 0,
                'photoName' => $photoName,
            ],
            'errors' => $errors,
        ];
    }

    public function add(array $input, array $files = []): array {
        [$validated, $errors] = (function($input, $files){
            $res = $this->validate($input, $files);
            return [$res['data'], $res['errors']];
        })($input, $files);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Validation failed.'];
        }

        $photoName = $validated['photoName'];
        // Move uploaded file if any
        if ($photoName && isset($files['photo']['tmp_name'])) {
            $destDir = realpath(__DIR__ . '/../menu');
            if ($destDir === false) { $destDir = __DIR__ . '/../menu'; }
            if (!is_dir($destDir)) { @mkdir($destDir, 0777, true); }
            $target = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $photoName;
            if (!@move_uploaded_file($files['photo']['tmp_name'], $target)) {
                return ['success' => false, 'errors' => ['photo' => 'Failed to save image.'], 'message' => 'Upload error.'];
            }
        }

        $ok = $this->db->addMenu($validated['name'], $validated['description'], $validated['pax'], $validated['price'], $photoName ?? '', $validated['availability']);
        if (!$ok) {
            return ['success' => false, 'message' => 'Database insert failed.'];
        }
        return ['success' => true];
    }
}
