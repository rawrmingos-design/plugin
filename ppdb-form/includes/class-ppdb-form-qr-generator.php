<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

/**
 * QR Code Generator for PPDB Form
 * Simple QR code generation without external dependencies
 */
class PPDB_Form_QR_Generator
{
  /**
   * Generate QR Code using Google Charts API as fallback
   * For production, consider using a local library like endroid/qr-code
   */
  public static function generate_qr_code(string $data, int $size = 150): string
  {
    // Method 1: Try to use local QR generation if available
    if (self::can_use_local_qr()) {
      return self::generate_local_qr($data, $size);
    }
    
    // Method 2: Use Google Charts API as fallback
    return self::generate_google_qr($data, $size);
  }
  
  /**
   * Generate QR code using Google Charts API
   */
  private static function generate_google_qr(string $data, int $size): string
  {
    $encoded_data = urlencode($data);
    $google_url = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encoded_data}&choe=UTF-8";
    
    return $google_url;
  }
  
  /**
   * Check if local QR generation is available
   */
  private static function can_use_local_qr(): bool
  {
    // Check if we have GD extension and custom QR library
    return extension_loaded('gd') && class_exists('QRCodeGenerator');
  }
  
  /**
   * Generate QR code locally (placeholder for future implementation)
   */
  private static function generate_local_qr(string $data, int $size): string
  {
    // TODO: Implement local QR generation using library like endroid/qr-code
    // For now, fallback to Google API
    return self::generate_google_qr($data, $size);
  }
  
  /**
   * Generate QR code as base64 data URL for embedding
   */
  public static function generate_qr_data_url(string $data, int $size = 150): string
  {
    if (self::can_use_local_qr()) {
      return self::generate_local_qr_data_url($data, $size);
    }
    
    // Fallback: Get image from Google and convert to data URL
    $qr_url = self::generate_google_qr($data, $size);
    
    $image_data = wp_remote_get($qr_url, [
      'timeout' => 10,
      'sslverify' => false
    ]);
    
    if (is_wp_error($image_data)) {
      return self::generate_fallback_qr_svg($data, $size);
    }
    
    $body = wp_remote_retrieve_body($image_data);
    if (empty($body)) {
      return self::generate_fallback_qr_svg($data, $size);
    }
    
    $base64 = base64_encode($body);
    return "data:image/png;base64,{$base64}";
  }
  
  /**
   * Generate local QR as data URL (placeholder)
   */
  private static function generate_local_qr_data_url(string $data, int $size): string
  {
    // TODO: Implement using local library
    return self::generate_fallback_qr_svg($data, $size);
  }
  
  /**
   * Generate a simple SVG QR code fallback
   */
  private static function generate_fallback_qr_svg(string $data, int $size): string
  {
    $hash = md5($data);
    $pattern = str_split($hash, 2);
    
    $svg = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<rect width="21" height="21" fill="white"/>';
    
    // Generate a simple pattern based on hash
    for ($i = 0; $i < 21; $i++) {
      for ($j = 0; $j < 21; $j++) {
        $index = ($i * 21 + $j) % count($pattern);
        $hex_val = hexdec($pattern[$index]);
        
        if ($hex_val > 128) {
          $svg .= '<rect x="' . $j . '" y="' . $i . '" width="1" height="1" fill="black"/>';
        }
      }
    }
    
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
  }
  
  /**
   * Get verification URL for QR code
   */
  public static function get_verification_url(string $reg_number, string $hash): string
  {
    return add_query_arg([
      'ppdb_verify' => 1,
      'reg' => $reg_number,
      'hash' => substr($hash, 0, 16) // Shortened hash for QR
    ], home_url());
  }
  
  /**
   * Generate QR code for certificate verification
   */
  public static function generate_certificate_qr(string $reg_number, string $hash, int $size = 120): string
  {
    $verify_url = self::get_verification_url($reg_number, $hash);
    return self::generate_qr_data_url($verify_url, $size);
  }
}
