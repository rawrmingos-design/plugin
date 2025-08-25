<?php

/**
 * Modern PDF Template for PPDB Form
 * Minimalist & contemporary design
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
$colors = $config['colors'] ?? ['primary' => '#10b981', 'secondary' => '#374151', 'text' => '#111827'];
$institution = $config['institution'] ?? [];
$fields = $config['fields'] ?? ['nama_lengkap', 'email', 'jurusan'];
$qr_position = $config['qr_position'] ?? 'top_right';
$custom_footer = $config['custom_footer'] ?? 'Dokumen ini adalah bukti pendaftaran resmi yang sah.';

// QR Code URL
$qr_code_url = '';
if (class_exists('PPDB_Form_QR_Generator')) {
  $verification_hash = wp_hash($reg_number . '|certificate|' . wp_salt());
  $qr_code_url = PPDB_Form_QR_Generator::generate_certificate_qr($reg_number, $verification_hash, 80);
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
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica Neue', 'Arial', sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: <?php echo esc_attr($colors['text']); ?>;
            background: white;
            font-weight: 400;
        }
        
        .certificate-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            position: relative;
            min-height: 297mm;
            padding: 40px;
        }
        
        /* Header Styles - Left Aligned Logo */
        .certificate-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 50px;
            position: relative;
        }
        
        .header-left {
            flex: 1;
        }
        
        .institution-logo img {
            max-height: 60px;
            max-width: 120px;
            object-fit: contain;
            margin-bottom: 15px;
        }
        
        .institution-name {
            font-size: 28px;
            font-weight: 300;
            color: <?php echo esc_attr($colors['text']); ?>;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        
        .institution-tagline {
            font-size: 14px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            font-weight: 300;
            margin-bottom: 20px;
        }
        
        .certificate-title {
            font-size: 18px;
            font-weight: 600;
            color: <?php echo esc_attr($colors['primary']); ?>;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        
        .registration-number {
            font-size: 13px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
        }
        
        .registration-number .reg-value {
            color: <?php echo esc_attr($colors['text']); ?>;
            font-family: 'Monaco', 'Consolas', monospace;
            font-weight: 600;
            font-size: 15px;
        }
        
        /* Content Styles - Minimal Cards */
        .certificate-content {
            margin-bottom: 60px;
        }
        
        .data-card {
            background: #fafafa;
            border-left: 4px solid <?php echo esc_attr($colors['primary']); ?>;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 0 8px 8px 0;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: <?php echo esc_attr($colors['text']); ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        
        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .data-item {
            margin-bottom: 15px;
        }
        
        .field-label {
            font-size: 11px;
            font-weight: 500;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .field-value {
            font-size: 15px;
            font-weight: 500;
            color: <?php echo esc_attr($colors['text']); ?>;
            line-height: 1.3;
        }
        
        /* QR Code - Minimal Style */
        .qr-section {
            position: absolute;
        }
        
        .qr-section.top-right {
            top: 40px;
            right: 40px;
        }
        
        .qr-section.bottom-right {
            bottom: 100px;
            right: 40px;
        }
        
        .qr-section.bottom-left {
            bottom: 100px;
            left: 40px;
        }
        
        .qr-section.bottom-center {
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .qr-code {
            background: white;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .qr-code img {
            display: block;
            width: 80px;
            height: 80px;
            border-radius: 4px;
        }
        
        .qr-placeholder {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border: 1px dashed <?php echo esc_attr($colors['secondary']); ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            text-align: center;
            border-radius: 4px;
        }
        
        .qr-label {
            font-size: 9px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            text-align: center;
            margin-top: 6px;
            font-weight: 500;
        }
        
        /* Status Badge */
        .status-badge {
            position: absolute;
            top: 40px;
            left: 40px;
            background: <?php echo esc_attr($colors['primary']); ?>;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Footer - Minimal */
        .certificate-footer {
            position: absolute;
            bottom: 40px;
            left: 40px;
            right: 40px;
        }
        
        .footer-divider {
            height: 1px;
            background: linear-gradient(to right, <?php echo esc_attr($colors['primary']); ?>, transparent);
            margin-bottom: 15px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
        }
        
        .footer-left {
            flex: 1;
        }
        
        .footer-right {
            text-align: right;
            font-size: 9px;
        }
        
        .footer-message {
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        /* Subtle Geometric Accent */
        .geometric-accent {
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, <?php echo esc_attr($colors['primary']); ?>08, transparent);
            border-radius: 0 0 0 100%;
            z-index: -1;
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
                padding: 20mm;
            }
            
            .data-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Single column on small fields */
        @media (max-width: 600px) {
            .data-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <!-- Geometric Accent -->
        <div class="geometric-accent"></div>
        
        <!-- Status Badge -->
        <div class="status-badge">Verified</div>
        
        <!-- Header -->
        <div class="certificate-header">
            <div class="header-left">
                <?php if (!empty($institution['logo'])): ?>
                    <div class="institution-logo">
                        <img src="<?php echo esc_url($institution['logo']); ?>" alt="Logo">
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
                
                <div style="margin-top: 30px;">
                    <div class="certificate-title">Registration Proof</div>
                    <div class="registration-number">
                        No. <span class="reg-value"><?php echo esc_html($reg_number); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="certificate-content">
            <div class="data-card">
                <div class="section-title">Applicant Information</div>
                
                <div class="data-grid">
                    <?php
                    $field_registry = PPDB_Form_Plugin::get_field_registry();
                    foreach ($fields as $field_key):
                      if (isset($data[$field_key]) && !empty($data[$field_key])):
                        $label = $field_registry[$field_key]['label'] ?? ucfirst(str_replace('_', ' ', $field_key));
                        $value = is_array($data[$field_key]) ? implode(', ', $data[$field_key]) : $data[$field_key];
                        ?>
                        <div class="data-item">
                            <div class="field-label"><?php echo esc_html($label); ?></div>
                            <div class="field-value"><?php echo esc_html($value); ?></div>
                        </div>
                    <?php
                      endif;
                    endforeach;
                    ?>
                    
                    <!-- System fields -->
                    <div class="data-item">
                        <div class="field-label">Registration Date</div>
                        <div class="field-value"><?php echo esc_html(date('M d, Y', strtotime($submission_date ?? 'now'))); ?></div>
                    </div>
                    <div class="data-item">
                        <div class="field-label">Status</div>
                        <div class="field-value" style="color: <?php echo esc_attr($colors['primary']); ?>; font-weight: 600;">
                            ✓ Registered
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- QR Code -->
        <?php if ($qr_code_url): ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-code">
                    <img src="<?php echo esc_url($qr_code_url); ?>" alt="Verification QR">
                </div>
                <div class="qr-label">Verify</div>
            </div>
        <?php else: ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-placeholder">
                    QR<br>Code
                </div>
                <div class="qr-label">Verify</div>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="certificate-footer">
            <div class="footer-divider"></div>
            <div class="footer-content">
                <div class="footer-left">
                    <?php if (!empty($custom_footer)): ?>
                        <div class="footer-message"><?php echo esc_html($custom_footer); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($institution['contact'])): ?>
                        <div>Contact: <?php echo esc_html($institution['contact']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="footer-right">
                    Generated: <?php echo esc_html(date('M d, Y H:i')); ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
