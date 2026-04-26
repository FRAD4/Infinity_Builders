<?php
/**
 * Security Headers for Infinity Builders
 * Include this at the top of every page to add security headers
 * 
 * Usage: require_once 'includes/security_headers.php';
 */

// Prevent clickjacking
header('X-Frame-Options: DENY');

// Prevent XSS
header('X-XSS-Protection: 1; mode=block');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Content Security Policy (CSP)
// Customize based on your needs
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
       "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
       "img-src 'self' data: https:; " .
       "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://r2cdn.perplexity.ai; " .
       "connect-src 'self' https://cdn.jsdelivr.net; " .
       "frame-ancestors 'none'";

header("Content-Security-Policy: $csp");

// Referrer Policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions Policy (restrict browser features)
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Force HTTPS (if behind a reverse proxy)
// Uncomment when HTTPS is enabled
// if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
//     header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
//     exit;
// }
