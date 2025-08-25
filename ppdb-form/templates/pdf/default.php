<?php

/**
 * Default PDF Template for PPDB Form
 * Clean & professional untuk semua institusi
 */
if (!defined('ABSPATH')) {
  exit;
}

// Extract variables
$config = $template_config ?? [];
$data = $submission_data ?? [];
$submission_id = $submission_id ?? 0;
$reg_number = $registration_number ?? '';

// Template configuration
$colors = $config['colors'] ?? ['primary' => '#3b82f6', 'secondary' => '#64748b', 'text' => '#1f2937'];
$institution = $config['institution'] ?? [];
$fields = $config['fields'] ?? ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan'];
$qr_position = $config['qr_position'] ?? 'bottom_right';
$custom_footer = $config['custom_footer'] ?? 'Dokumen ini adalah bukti pendaftaran resmi yang sah.';

// QR Code URL
$qr_code_url = '';
if (class_exists('PPDB_Form_QR_Generator')) {
  $verification_hash = wp_hash($reg_number . '|certificate|' . wp_salt());
  $qr_code_url = PPDB_Form_QR_Generator::generate_certificate_qr($reg_number, $verification_hash, 100);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bukti Pendaftaran - <?php echo esc_html($reg_number); ?></title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: <?php echo esc_attr($colors['text']); ?>;
            background: white;
        }
        
        .certificate-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            position: relative;
            min-height: 297mm;
        }
        
        /* Header Styles */
        .certificate-header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 3px solid <?php echo esc_attr($colors['primary']); ?>;
            margin-bottom: 30px;
            position: relative;
        }
        
        .institution-logo {
            margin-bottom: 15px;
        }
        
        .institution-logo img {
            max-height: 80px;
            max-width: 200px;
            object-fit: contain;
        }
        
        .institution-name {
            font-size: 24px;
            font-weight: bold;
            color: <?php echo esc_attr($colors['primary']); ?>;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .institution-tagline {
            font-size: 14px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            font-style: italic;
            margin-bottom: 15px;
        }
        
        .institution-address {
            font-size: 11px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        
        .certificate-title {
            font-size: 20px;
            font-weight: bold;
            color: <?php echo esc_attr($colors['text']); ?>;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .registration-number {
            font-size: 14px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            font-weight: 600;
        }
        
        .registration-number .reg-label {
            font-weight: normal;
        }
        
        .registration-number .reg-value {
            color: <?php echo esc_attr($colors['primary']); ?>;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 16px;
        }
        
        /* Content Styles */
        .certificate-content {
            margin-bottom: 40px;
        }
        
        .content-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: <?php echo esc_attr($colors['primary']); ?>;
            border-bottom: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            padding-bottom: 5px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .data-table tr {
            border-bottom: 1px solid #f0f0f0;
        }
        
        .data-table tr:last-child {
            border-bottom: none;
        }
        
        .data-table td {
            padding: 12px 0;
            vertical-align: top;
        }
        
        .data-table .field-label {
            width: 40%;
            font-weight: 600;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            padding-right: 20px;
        }
        
        .data-table .field-value {
            font-weight: 600;
            color: <?php echo esc_attr($colors['text']); ?>;
        }
        
        /* QR Code Positioning */
        .qr-section {
            position: absolute;
            text-align: center;
        }
        
        .qr-section.bottom-right {
            bottom: 80px;
            right: 30px;
        }
        
        .qr-section.bottom-left {
            bottom: 80px;
            left: 30px;
        }
        
        .qr-section.bottom-center {
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .qr-section.top-right {
            top: 20px;
            right: 30px;
        }
        
        .qr-code {
            border: 2px solid <?php echo esc_attr($colors['secondary']); ?>;
            border-radius: 8px;
            padding: 8px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .qr-code img {
            display: block;
            width: 100px;
            height: 100px;
        }
        
        .qr-placeholder {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border: 2px dashed <?php echo esc_attr($colors['secondary']); ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            text-align: center;
            line-height: 1.2;
        }
        
        .qr-label {
            font-size: 10px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-top: 8px;
            font-weight: 600;
        }
        
        /* Footer Styles */
        .certificate-footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            border-top: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
            padding-top: 15px;
        }
        
        .footer-content {
            font-size: 11px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            line-height: 1.4;
        }
        
        .footer-message {
            font-weight: 600;
            margin-bottom: 8px;
            color: <?php echo esc_attr($colors['text']); ?>;
        }
        
        .footer-contact {
            margin-top: 8px;
        }
        
        .print-date {
            font-size: 10px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-top: 5px;
        }
        
        /* Status Indicator */
        .status-indicator {
            position: absolute;
            top: 20px;
            left: 20px;
            background: <?php echo esc_attr($colors['primary']); ?>;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(<?php
$hex = str_replace('#', '', $colors['primary']);
$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));
echo "{$r}, {$g}, {$b}";
?>, 0.05);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
            user-select: none;
        }
        
        /* Print Styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .certificate-container {
                width: 100%;
                max-width: none;
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <!-- Watermark -->
        <div class="watermark">VALID</div>
        
        <!-- Status Indicator -->
        <div class="status-indicator">TERDAFTAR</div>
        
        <!-- Header -->
        <div class="certificate-header">
            <?php if (!empty($institution['logo'])): ?>
                <div class="institution-logo">
                    <img src="<?php echo esc_url($institution['logo']); ?>" alt="Logo Institusi">
                </div>
            <?php endif; ?>
            
            <div class="institution-name">
                <?php echo esc_html($institution['name'] ?? get_bloginfo('name')); ?>
            </div>
            
            <?php if (!empty($institution['tagline'])): ?>
                <div class="institution-tagline">
                    <?php echo esc_html($institution['tagline']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($institution['address'])): ?>
                <div class="institution-address">
                    <?php echo nl2br(esc_html($institution['address'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="certificate-title">Bukti Pendaftaran</div>
            
            <div class="registration-number">
                <span class="reg-label">Nomor Registrasi:</span>
                <span class="reg-value"><?php echo esc_html($reg_number); ?></span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="certificate-content">
            <div class="content-section">
                <div class="section-title">Data Pendaftar</div>
                
                <table class="data-table">
                    <?php
                    $field_registry = PPDB_Form_Plugin::get_field_registry();
                    foreach ($fields as $field_key):
                      if (isset($data[$field_key]) && !empty($data[$field_key])):
                        $label = $field_registry[$field_key]['label'] ?? ucfirst(str_replace('_', ' ', $field_key));
                        $value = is_array($data[$field_key]) ? implode(', ', $data[$field_key]) : $data[$field_key];
                        ?>
                        <tr>
                            <td class="field-label"><?php echo esc_html($label); ?></td>
                            <td class="field-value"><?php echo esc_html($value); ?></td>
                        </tr>
                    <?php
                      endif;
                    endforeach;
                    ?>
                    
                    <!-- Additional system fields -->
                    <tr>
                        <td class="field-label">Tanggal Pendaftaran</td>
                        <td class="field-value"><?php echo esc_html(date('d F Y', strtotime($submission_date ?? 'now'))); ?></td>
                    </tr>
                    <tr>
                        <td class="field-label">Status Pendaftaran</td>
                        <td class="field-value" style="color: <?php echo esc_attr($colors['primary']); ?>; font-weight: bold;">TERDAFTAR</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- QR Code -->
        <?php if ($qr_code_url): ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-code">
                    <img src="<?php echo esc_url($qr_code_url); ?>" alt="QR Code Verifikasi">
                </div>
                <div class="qr-label">Scan untuk verifikasi</div>
            </div>
        <?php else: ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-placeholder">
                    QR Code<br>Verifikasi
                </div>
                <div class="qr-label">Scan untuk verifikasi</div>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="certificate-footer">
            <div class="footer-content">
                <?php if (!empty($custom_footer)): ?>
                    <div class="footer-message">
                        <?php echo esc_html($custom_footer); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($institution['contact'])): ?>
                    <div class="footer-contact">
                        Informasi lebih lanjut: <?php echo esc_html($institution['contact']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="print-date">
                    Dicetak pada: <?php echo esc_html(date('d F Y H:i')); ?> WIB
                </div>
            </div>
        </div>
    </div>
</body>
</html>
