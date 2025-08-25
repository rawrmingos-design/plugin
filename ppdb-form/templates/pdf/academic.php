<?php

/**
 * Academic PDF Template for PPDB Form
 * University-style dengan elemen akademik
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
$colors = $config['colors'] ?? ['primary' => '#7c3aed', 'secondary' => '#4b5563', 'text' => '#1f2937'];
$institution = $config['institution'] ?? [];
$fields = $config['fields'] ?? ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan', 'asal_sekolah'];
$qr_position = $config['qr_position'] ?? 'bottom_left';
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
    <title>Certificate of Registration - <?php echo esc_html($reg_number); ?></title>
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
            font-family: 'Palatino', 'Georgia', serif;
            font-size: 13px;
            line-height: 1.7;
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
            padding: 40px;
        }
        
        /* Academic Shield Border */
        .shield-border {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 4px solid <?php echo esc_attr($colors['primary']); ?>;
            border-radius: 20px 20px 0 0;
        }
        
        .inner-shield {
            position: absolute;
            top: 6px;
            left: 6px;
            right: 6px;
            bottom: 6px;
            border: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
            border-radius: 16px 16px 0 0;
        }
        
        /* Header - Academic Style */
        .certificate-header {
            text-align: center;
            padding-bottom: 35px;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }
        
        .academic-crest {
            width: 120px;
            height: 120px;
            border: 3px solid <?php echo esc_attr($colors['primary']); ?>;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            position: relative;
            box-shadow: 0 0 0 3px white, 0 0 0 6px <?php echo esc_attr($colors['secondary']); ?>;
        }
        
        .academic-crest::before {
            content: "";
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            border-radius: 50%;
            border-style: dashed;
        }
        
        .academic-crest .crest-content {
            text-align: center;
            color: <?php echo esc_attr($colors['primary']); ?>;
        }
        
        .academic-crest img {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 50%;
        }
        
        .academic-crest .crest-text {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.2;
        }
        
        .university-name {
            font-size: 28px;
            font-weight: bold;
            color: <?php echo esc_attr($colors['primary']); ?>;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-family: serif;
        }
        
        .university-motto {
            font-size: 14px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            font-style: italic;
            margin-bottom: 5px;
            font-family: serif;
        }
        
        .university-address {
            font-size: 11px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-bottom: 25px;
            line-height: 1.4;
        }
        
        .certificate-title {
            font-size: 24px;
            font-weight: normal;
            color: <?php echo esc_attr($colors['text']); ?>;
            margin-bottom: 10px;
            font-family: serif;
            font-style: italic;
        }
        
        .certificate-subtitle {
            font-size: 18px;
            color: <?php echo esc_attr($colors['primary']); ?>;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        
        .registration-details {
            background: linear-gradient(135deg, <?php echo esc_attr($colors['primary']); ?>10, <?php echo esc_attr($colors['secondary']); ?>05);
            border: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            border-radius: 12px;
            padding: 15px 25px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .registration-number {
            font-size: 14px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-bottom: 5px;
        }
        
        .registration-number .reg-value {
            color: <?php echo esc_attr($colors['primary']); ?>;
            font-family: 'Monaco', monospace;
            font-weight: bold;
            font-size: 16px;
            letter-spacing: 1px;
        }
        
        .academic-year {
            font-size: 12px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            font-weight: 600;
        }
        
        /* Content - Academic Style */
        .certificate-content {
            margin-bottom: 50px;
            position: relative;
            z-index: 2;
        }
        
        .academic-section {
            background: white;
            border: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .section-header {
            background: linear-gradient(135deg, <?php echo esc_attr($colors['primary']); ?>, <?php echo esc_attr($colors['primary']); ?>cc);
            color: white;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section-content {
            padding: 25px;
        }
        
        .academic-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .academic-table tr {
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .academic-table td {
            padding: 12px 18px;
            vertical-align: top;
        }
        
        .academic-table td:first-child {
            border-radius: 6px 0 0 6px;
            background: <?php echo esc_attr($colors['primary']); ?>08;
        }
        
        .academic-table td:last-child {
            border-radius: 0 6px 6px 0;
        }
        
        .academic-table .field-label {
            width: 35%;
            font-weight: 600;
            color: <?php echo esc_attr($colors['text']); ?>;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .academic-table .field-value {
            font-weight: normal;
            color: <?php echo esc_attr($colors['text']); ?>;
            font-size: 14px;
        }
        
        /* Verification Section */
        .verification-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 2;
        }
        
        .verification-left {
            flex: 1;
        }
        
        .verification-right {
            flex: 1;
            text-align: right;
        }
        
        .verification-title {
            font-size: 12px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .verification-date {
            font-size: 13px;
            color: <?php echo esc_attr($colors['text']); ?>;
            margin-bottom: 40px;
        }
        
        .verification-signature {
            border-bottom: 2px solid <?php echo esc_attr($colors['text']); ?>;
            padding-bottom: 2px;
            display: inline-block;
            min-width: 180px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: <?php echo esc_attr($colors['text']); ?>;
        }
        
        .verification-position {
            font-size: 11px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* QR Code - Academic Style */
        .qr-section {
            position: absolute;
            z-index: 2;
        }
        
        .qr-section.bottom-left {
            bottom: 120px;
            left: 50px;
        }
        
        .qr-section.bottom-right {
            bottom: 120px;
            right: 50px;
        }
        
        .qr-section.bottom-center {
            bottom: 120px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .qr-section.top-right {
            top: 50px;
            right: 50px;
        }
        
        .qr-container {
            text-align: center;
            background: white;
            border: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(<?php
$hex = str_replace('#', '', $colors['primary']);
$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));
echo "{$r}, {$g}, {$b}";
?>, 0.2);
        }
        
        .qr-container img {
            display: block;
            width: 100px;
            height: 100px;
            margin: 0 auto;
            border-radius: 6px;
        }
        
        .qr-placeholder {
            width: 100px;
            height: 100px;
            background: <?php echo esc_attr($colors['primary']); ?>08;
            border: 2px dashed <?php echo esc_attr($colors['primary']); ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: <?php echo esc_attr($colors['primary']); ?>;
            text-align: center;
            margin: 0 auto;
            border-radius: 6px;
        }
        
        .qr-label {
            font-size: 10px;
            color: <?php echo esc_attr($colors['primary']); ?>;
            margin-top: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Academic Watermark */
        .academic-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 100px;
            color: rgba(<?php
$hex = str_replace('#', '', $colors['primary']);
$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));
echo "{$r}, {$g}, {$b}";
?>, 0.03);
            font-weight: bold;
            z-index: 1;
            pointer-events: none;
            user-select: none;
            font-family: serif;
        }
        
        /* Footer - Academic */
        .certificate-footer {
            position: absolute;
            bottom: 40px;
            left: 50px;
            right: 50px;
            text-align: center;
            border-top: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
            padding-top: 15px;
            z-index: 2;
        }
        
        .footer-content {
            font-size: 10px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            line-height: 1.5;
        }
        
        .footer-motto {
            font-style: italic;
            margin-bottom: 8px;
            color: <?php echo esc_attr($colors['primary']); ?>;
            font-weight: 600;
        }
        
        .footer-contact {
            margin-top: 5px;
        }
        
        .print-timestamp {
            font-size: 9px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-top: 8px;
            border-top: 1px dotted <?php echo esc_attr($colors['secondary']); ?>;
            padding-top: 5px;
        }
        
        /* Academic decorations */
        .academic-ornament {
            color: <?php echo esc_attr($colors['primary']); ?>;
            font-size: 16px;
            margin: 0 8px;
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
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <!-- Academic Borders -->
        <div class="shield-border"></div>
        <div class="inner-shield"></div>
        
        <!-- Academic Watermark -->
        <div class="academic-watermark">UNIVERSITAS</div>
        
        <!-- Header -->
        <div class="certificate-header">
            <!-- Academic Crest -->
            <div class="academic-crest">
                <div class="crest-content">
                    <?php if (!empty($institution['logo'])): ?>
                        <img src="<?php echo esc_url($institution['logo']); ?>" alt="University Seal">
                    <?php else: ?>
                        <div class="crest-text">
                            SEAL<br>OF<br>KNOWLEDGE
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="university-name">
                <?php echo esc_html($institution['name'] ?? get_bloginfo('name')); ?>
            </div>
            
            <?php if (!empty($institution['tagline'])): ?>
                <div class="university-motto">
                    <?php echo esc_html($institution['tagline']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($institution['address'])): ?>
                <div class="university-address">
                    <?php echo nl2br(esc_html($institution['address'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="certificate-title">Certificate of Registration</div>
            <div class="certificate-subtitle">
                <span class="academic-ornament">⚜</span>
                REGISTRATION PROOF
                <span class="academic-ornament">⚜</span>
            </div>
            
            <div class="registration-details">
                <div class="registration-number">
                    Registration No: <span class="reg-value"><?php echo esc_html($reg_number); ?></span>
                </div>
                <div class="academic-year">
                    Academic Year <?php echo esc_html(date('Y')); ?>/<?php echo esc_html(date('Y') + 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="certificate-content">
            <div class="academic-section">
                <div class="section-header">
                    <span class="academic-ornament">📚</span>
                    Applicant Information
                    <span class="academic-ornament">📚</span>
                </div>
                <div class="section-content">
                    <table class="academic-table">
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
                        
                        <!-- Academic specific fields -->
                        <tr>
                            <td class="field-label">Registration Date</td>
                            <td class="field-value"><?php echo esc_html(date('F d, Y', strtotime($submission_date ?? 'now'))); ?></td>
                        </tr>
                        <tr>
                            <td class="field-label">Academic Status</td>
                            <td class="field-value" style="color: <?php echo esc_attr($colors['primary']); ?>; font-weight: 600;">
                                ✓ REGISTERED STUDENT
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Valid Until</td>
                            <td class="field-value">End of admission process</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Verification Section -->
            <div class="verification-section">
                <div class="verification-left">
                    <div class="verification-title">Verified By:</div>
                    <div class="verification-date">
                        <?php echo esc_html(date('F d, Y')); ?>
                    </div>
                    <div class="verification-signature">Registrar's Office</div>
                    <div class="verification-position">Academic Registrar</div>
                </div>
                
                <div class="verification-right">
                    <div class="verification-title">Dean's Office:</div>
                    <div class="verification-date">
                        <?php echo esc_html(date('F d, Y')); ?>
                    </div>
                    <div class="verification-signature">Academic Dean</div>
                    <div class="verification-position">Dean of Admissions</div>
                </div>
            </div>
        </div>
        
        <!-- QR Code -->
        <?php if ($qr_code_url): ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-container">
                    <img src="<?php echo esc_url($qr_code_url); ?>" alt="Academic Verification QR">
                    <div class="qr-label">Academic Verify</div>
                </div>
            </div>
        <?php else: ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-container">
                    <div class="qr-placeholder">
                        ACADEMIC<br>QR CODE
                    </div>
                    <div class="qr-label">Academic Verify</div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="certificate-footer">
            <div class="footer-content">
                <?php if (!empty($custom_footer)): ?>
                    <div class="footer-motto">
                        <?php echo esc_html($custom_footer); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($institution['contact'])): ?>
                    <div class="footer-contact">
                        Academic Information: <?php echo esc_html($institution['contact']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="print-timestamp">
                    Document generated: <?php echo esc_html(date('F d, Y H:i:s')); ?> | 
                    Academic Year <?php echo esc_html(date('Y')); ?>/<?php echo esc_html(date('Y') + 1); ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
