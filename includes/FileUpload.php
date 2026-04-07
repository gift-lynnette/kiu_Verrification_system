<?php
/**
 * File Upload Handler Class
 */

class FileUpload {
    private $allowed_types;
    private $max_size;
    private $upload_dir;
    private $errors = [];
    
    public function __construct($upload_dir, $allowed_types = null, $max_size = null) {
        $this->upload_dir = $upload_dir;
        $this->allowed_types = $allowed_types ?? ALLOWED_DOCUMENT_TYPES;
        $this->max_size = $max_size ?? UPLOAD_MAX_SIZE;
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
    }
    
    /**
     * Upload file
     */
    public function upload($file, $custom_name = null) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = "No file was uploaded";
            return false;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }
        
        // Validate file size
        if ($file['size'] > $this->max_size) {
            $this->errors[] = "File size exceeds maximum allowed size of " . format_file_size($this->max_size);
            return false;
        }
        
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_types)) {
            $this->errors[] = "File type not allowed. Allowed types: " . implode(', ', $this->allowed_types);
            return false;
        }
        
        // Generate unique filename
        $extension = $this->getExtensionFromMime($mime_type);
        if ($custom_name) {
            $filename = $custom_name . '.' . $extension;
        } else {
            $filename = uniqid() . '_' . time() . '.' . $extension;
        }
        
        $destination = $this->upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $destination,
                'size' => $file['size'],
                'mime_type' => $mime_type,
                'hash' => hash_file('sha256', $destination)
            ];
        }
        
        $this->errors[] = "Failed to move uploaded file";
        return false;
    }
    
    /**
     * Upload multiple files
     */
    public function uploadMultiple($files) {
        $results = [];
        
        foreach ($files['tmp_name'] as $key => $tmp_name) {
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $tmp_name,
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];
            
            $results[] = $this->upload($file);
        }
        
        return $results;
    }
    
    /**
     * Delete file
     */
    public function delete($filename) {
        $filepath = $this->upload_dir . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
    
    /**
     * Get upload errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error_code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $errors[$error_code] ?? 'Unknown upload error';
    }
    
    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMime($mime_type) {
        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt'
        ];
        
        return $mime_map[$mime_type] ?? 'bin';
    }
    
    /**
     * Validate image dimensions
     */
    public function validateImageDimensions($file, $min_width, $min_height, $max_width = null, $max_height = null) {
        $image_info = getimagesize($file['tmp_name']);
        
        if ($image_info === false) {
            $this->errors[] = "File is not a valid image";
            return false;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        
        if ($width < $min_width || $height < $min_height) {
            $this->errors[] = "Image dimensions must be at least {$min_width}x{$min_height}px";
            return false;
        }
        
        if ($max_width && $max_height && ($width > $max_width || $height > $max_height)) {
            $this->errors[] = "Image dimensions must not exceed {$max_width}x{$max_height}px";
            return false;
        }
        
        return true;
    }
}
