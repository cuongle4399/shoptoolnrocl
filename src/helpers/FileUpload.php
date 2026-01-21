<?php
class FileUpload {
    private $uploadDir = '/ShopToolNro/assets/uploads/';
    private $maxSize = 5242880; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    public function upload($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Không có file được chọn'];
        }

        if ($file['size'] > $this->maxSize) {
            return ['success' => false, 'message' => 'File quá lớn (tối đa 5MB)'];
        }

        if (!in_array($file['type'], $this->allowedTypes)) {
            return ['success' => false, 'message' => 'Định dạng file không hợp lệ'];
        }

        $filename = time() . '_' . basename($file['name']);
        $filepath = $_SERVER['DOCUMENT_ROOT'] . $this->uploadDir . $filename;

        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $this->uploadDir)) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . $this->uploadDir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'url' => $this->uploadDir . $filename
            ];
        }

        return ['success' => false, 'message' => 'Lỗi upload file'];
    }

    public function deleteFile($filename) {
        $filepath = $_SERVER['DOCUMENT_ROOT'] . $this->uploadDir . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
            return true;
        }
        return false;
    }
}
?>
