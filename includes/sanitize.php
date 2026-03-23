<?php
/**
 * Input Sanitization Helpers for Infinity Builders
 */

/**
 * Sanitize input based on type
 */
function sanitize_input(mixed $value, string $type = 'string'): mixed {
    if ($value === null) {
        return null;
    }
    
    switch ($type) {
        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT) !== false 
                ? (int)$value 
                : 0;
        
        case 'email':
            return filter_var(trim($value), FILTER_VALIDATE_EMAIL) 
                ? strtolower(trim($value)) 
                : '';
        
        case 'html':
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        
        case 'string':
        default:
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Recursively sanitize array inputs
 */
function sanitize_input_array(array $data, string $defaultType = 'string'): array {
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[sanitize_input($key)] = sanitize_input_array($value, $defaultType);
        } else {
            $sanitized[sanitize_input($key)] = sanitize_input($value, $defaultType);
        }
    }
    return $sanitized;
}

/**
 * Sanitize POST data
 */
function sanitize_post(string $key, string $type = 'string'): mixed {
    if (!isset($_POST[$key])) {
        return null;
    }
    return sanitize_input($_POST[$key], $type);
}

/**
 * Sanitize GET data
 */
function sanitize_get(string $key, string $type = 'string'): mixed {
    if (!isset($_GET[$key])) {
        return null;
    }
    return sanitize_input($_GET[$key], $type);
}
