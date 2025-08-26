<?php
// Input validation functions

// Prevent direct access
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('Direct access not permitted');
}

class Validation {
    
    private static $errors = [];
    
    // Get validation errors
    public static function getErrors() {
        return self::$errors;
    }
    
    // Clear errors
    public static function clearErrors() {
        self::$errors = [];
    }
    
    // Add error
    public static function addError($field, $message) {
        self::$errors[$field][] = $message;
    }
    
    // Check if has errors
    public static function hasErrors() {
        return !empty(self::$errors);
    }
    
    // Validate required field
    public static function required($field, $value, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (empty($value) || trim($value) === '') {
            self::addError($field, "$field_name is required.");
            return false;
        }
        return true;
    }
    
    // Validate email
    public static function email($field, $value, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            self::addError($field, "$field_name must be a valid email address.");
            return false;
        }
        return true;
    }
    
    // Validate minimum length
    public static function minLength($field, $value, $min, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (strlen($value) < $min) {
            self::addError($field, "$field_name must be at least $min characters long.");
            return false;
        }
        return true;
    }
    
    // Validate maximum length
    public static function maxLength($field, $value, $max, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (strlen($value) > $max) {
            self::addError($field, "$field_name must not exceed $max characters.");
            return false;
        }
        return true;
    }
    
    // Validate numeric value
    public static function numeric($field, $value, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (!is_numeric($value)) {
            self::addError($field, "$field_name must be a number.");
            return false;
        }
        return true;
    }
    
    // Validate integer
    public static function integer($field, $value, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            self::addError($field, "$field_name must be an integer.");
            return false;
        }
        return true;
    }
    
    // Validate decimal
    public static function decimal($field, $value, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (!is_numeric($value) || !preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            self::addError($field, "$field_name must be a valid decimal number.");
            return false;
        }
        return true;
    }
    
    // Validate minimum value
    public static function min($field, $value, $min, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if ($value < $min) {
            self::addError($field, "$field_name must be at least $min.");
            return false;
        }
        return true;
    }
    
    // Validate maximum value
    public static function max($field, $value, $max, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if ($value > $max) {
            self::addError($field, "$field_name must not exceed $max.");
            return false;
        }
        return true;
    }
    
    // Validate date
    public static function date($field, $value, $format = 'Y-m-d', $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        $date = DateTime::createFromFormat($format, $value);
        if (!$date || $date->format($format) !== $value) {
            self::addError($field, "$field_name must be a valid date.");
            return false;
        }
        return true;
    }
    
    // Validate date not in future
    public static function notFuture($field, $value, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        $date = strtotime($value);
        if ($date > time()) {
            self::addError($field, "$field_name cannot be in the future.");
            return false;
        }
        return true;
    }
    
    // Validate username
    public static function username($field, $value, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $value)) {
            self::addError($field, "$field_name must be 3-20 characters and contain only letters, numbers, and underscores.");
            return false;
        }
        return true;
    }
    
    // Validate password
    public static function password($field, $value, $field_name = null) {
        $field_name = $field_name ?: 'Password';
        
        $errors = Security::validatePasswordStrength($value);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                self::addError($field, $error);
            }
            return false;
        }
        return true;
    }
    
    // Validate password confirmation
    public static function passwordConfirm($field, $value, $password, $field_name = null) {
        $field_name = $field_name ?: 'Password confirmation';
        
        if ($value !== $password) {
            self::addError($field, "Passwords do not match.");
            return false;
        }
        return true;
    }
    
    // Validate unique in database
    public static function unique($field, $value, $table, $column, $exclude_id = null, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        try {
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = :value";
            $params = [':value' => $value];
            
            if ($exclude_id) {
                $sql .= " AND id != :id";
                $params[':id'] = $exclude_id;
            }
            
            $result = $db->fetchOne($sql, $params);
            
            if ($result['count'] > 0) {
                self::addError($field, "$field_name is already taken.");
                return false;
            }
            return true;
            
        } catch (Exception $e) {
            self::addError($field, "Unable to validate $field_name.");
            return false;
        }
    }
    
    // Validate exists in database
    public static function exists($field, $value, $table, $column, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        try {
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = :value";
            $result = $db->fetchOne($sql, [':value' => $value]);
            
            if ($result['count'] == 0) {
                self::addError($field, "$field_name does not exist.");
                return false;
            }
            return true;
            
        } catch (Exception $e) {
            self::addError($field, "Unable to validate $field_name.");
            return false;
        }
    }
    
    // Validate in array
    public static function inArray($field, $value, $allowed, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (!in_array($value, $allowed)) {
            self::addError($field, "$field_name has an invalid value.");
            return false;
        }
        return true;
    }
    
    // Validate file upload
    public static function file($field, $file, $allowed_types = [], $max_size = 5242880, $field_name = null) {
        $field_name = $field_name ?: ucfirst($field);
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            self::addError($field, "$field_name upload failed.");
            return false;
        }
        
        if ($file['size'] > $max_size) {
            $max_size_mb = $max_size / 1024 / 1024;
            self::addError($field, "$field_name must not exceed {$max_size_mb}MB.");
            return false;
        }
        
        if (!empty($allowed_types)) {
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_types)) {
                $types = implode(', ', $allowed_types);
                self::addError($field, "$field_name must be one of: $types.");
                return false;
            }
        }
        
        return true;
    }
    
    // Validate CSRF token
    public static function csrf($token) {
        if (!Security::verifyCSRFToken($token)) {
            self::addError('csrf', "Invalid security token. Please refresh and try again.");
            return false;
        }
        return true;
    }
}