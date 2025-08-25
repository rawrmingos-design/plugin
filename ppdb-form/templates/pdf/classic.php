<?php

/**
 * Classic PDF Template for PPDB Form
 * Traditional & formal untuk institusi konservatif
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
$colors = $config['colors'] ?? ['primary' => '#dc2626', 'secondary' => '#1f2937', 'text' => '#000000'];
$institution = $config['institution'] ?? [];
$fields = $config['fields'] ?? ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan', 'alamat'];
$qr_position = $config['qr_position'] ?? 'bottom_center';
$custom_footer = $config['custom_footer'] ?? 'Dokumen ini adalah bukti pendaftaran resmi yang sah.';

// QR Code URL
$qr_code_url = '';
if (class_exists('PPDB_Form_QR_Generator')) {
  $verification_hash = wp_hash($reg_number . '|certificate|' . wp_salt());
  $qr_code_url = PPDB_Form_QR_Generator::generate_certificate_qr($reg_number, $verification_hash, 120);
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
            margin: 25mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
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
            border: 3px double <?php echo esc_attr($colors['primary']); ?>;
            padding: 30px;
        }
        
        /* Ornamental Border */
        .ornamental-border {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            border: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
        }
        
        .inner-border {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid <?php echo esc_attr($colors['primary']); ?>;
        }
        
        /* Header Styles - Formal */
        .certificate-header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }
        
        .header-ornament {
            font-size: 24px;
            color: <?php echo esc_attr($colors['primary']); ?>;
            margin-bottom: 15px;
        }
        
        .institution-logo {
            margin-bottom: 20px;
        }
        
        .institution-logo img {
            max-height: 100px;
            max-width: 250px;
            object-fit: contain;
            border: 2px solid <?php echo esc_attr($colors['secondary']); ?>;
            padding: 10px;
            background: white;
        }
        
        .institution-name {
            font-size: 26px;
            font-weight: bold;
            color: <?php echo esc_attr($colors['primary']); ?>;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-family: serif;
        }
        
        .institution-tagline {
            font-size: 14px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            font-style: italic;
            margin-bottom: 15px;
        }
        
        .institution-address {
            font-size: 12px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .certificate-title {
            font-size: 22px;
            font-weight: bold;
            color: <?php echo esc_attr($colors['text']); ?>;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: underline;
            text-decoration-color: <?php echo esc_attr($colors['primary']); ?>;
        }
        
        .certificate-subtitle {
            font-size: 16px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .registration-number {
            font-size: 14px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            border: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            padding: 10px 20px;
            display: inline-block;
            background: #f9f9f9;
        }
        
        .registration-number .reg-label {
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .registration-number .reg-value {
            color: <?php echo esc_attr($colors['primary']); ?>;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 16px;
            letter-spacing: 1px;
        }
        
        /* Content Styles - Formal Table */
        .certificate-content {
            margin-bottom: 50px;
            position: relative;
            z-index: 2;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: <?php echo esc_attr($colors['primary']); ?>;
            text-align: center;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-top: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
            border-bottom: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
            padding: 10px 0;
        }
        
        .formal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            border: 2px solid <?php echo esc_attr($colors['primary']); ?>;
        }
        
        .formal-table tr {
            border-bottom: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
        }
        
        .formal-table tr:last-child {
            border-bottom: none;
        }
        
        .formal-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .formal-table td {
            padding: 15px 20px;
            vertical-align: top;
            border-right: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
        }
        
        .formal-table td:last-child {
            border-right: none;
        }
        
        .formal-table .field-label {
            width: 35%;
            font-weight: bold;
            color: <?php echo esc_attr($colors['text']); ?>;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        .formal-table .field-value {
            font-weight: normal;
            color: <?php echo esc_attr($colors['text']); ?>;
            font-size: 14px;
        }
        
        /* Signature Section */
        .signature-section {
            margin-top: 40px;
            text-align: right;
            position: relative;
            z-index: 2;
        }
        
        .signature-title {
            font-size: 14px;
            color: <?php echo esc_attr($colors['text']); ?>;
            margin-bottom: 5px;
        }
        
        .signature-location {
            font-size: 12px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-bottom: 50px;
        }
        
        .signature-name {
            font-size: 14px;
            color: <?php echo esc_attr($colors['text']); ?>;
            border-bottom: 2px solid <?php echo esc_attr($colors['text']); ?>;
            padding-bottom: 2px;
            display: inline-block;
            min-width: 200px;
            text-align: center;
        }
        
        .signature-title-name {
            font-size: 12px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-top: 5px;
        }
        
        /* QR Code - Formal Style */
        .qr-section {
            position: absolute;
            z-index: 2;
        }
        
        .qr-section.bottom-center {
            bottom: 150px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .qr-section.bottom-right {
            bottom: 150px;
            right: 50px;
        }
        
        .qr-section.bottom-left {
            bottom: 150px;
            left: 50px;
        }
        
        .qr-section.top-right {
            top: 50px;
            right: 50px;
        }
        
        .qr-code {
            text-align: center;
            border: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            padding: 15px;
            background: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .qr-code img {
            display: block;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        
        .qr-placeholder {
            width: 120px;
            height: 120px;
            background: #f8f9fa;
            border: 2px dashed <?php echo esc_attr($colors['secondary']); ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            text-align: center;
            margin: 0 auto;
        }
        
        .qr-label {
            font-size: 11px;
            color: <?php echo esc_attr($colors['text']); ?>;
            margin-top: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Official Seal */
        .official-seal {
            position: absolute;
            top: 50px;
            left: 50px;
            width: 80px;
            height: 80px;
            border: 3px solid <?php echo esc_attr($colors['primary']); ?>;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            font-size: 10px;
            text-align: center;
            color: <?php echo esc_attr($colors['primary']); ?>;
            font-weight: bold;
            text-transform: uppercase;
            z-index: 2;
        }
        
        /* Footer - Formal */
        .certificate-footer {
            position: absolute;
            bottom: 40px;
            left: 50px;
            right: 50px;
            text-align: center;
            border-top: 2px solid <?php echo esc_attr($colors['primary']); ?>;
            padding-top: 15px;
            z-index: 2;
        }
        
        .footer-content {
            font-size: 11px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            line-height: 1.5;
        }
        
        .footer-message {
            font-weight: bold;
            margin-bottom: 8px;
            color: <?php echo esc_attr($colors['text']); ?>;
            font-style: italic;
        }
        
        .footer-contact {
            margin-top: 8px;
        }
        
        .print-date {
            font-size: 10px;
            color: <?php echo esc_attr($colors['secondary']); ?>;
            margin-top: 8px;
            border-top: 1px solid <?php echo esc_attr($colors['secondary']); ?>;
            padding-top: 5px;
        }
        
        /* Classical ornaments */
        .ornament {
            font-size: 20px;
            color: <?php echo esc_attr($colors['primary']); ?>;
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
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <!-- Ornamental Borders -->
        <div class="ornamental-border"></div>
        <div class="inner-border"></div>
        
        <!-- Official Seal -->
        <div class="official-seal">
            RESMI<br>
            VALID<br>
            <?php echo esc_html(date('Y')); ?>
        </div>
        
        <!-- Header -->
        <div class="certificate-header">
            <div class="header-ornament ornament">❖ ❖ ❖</div>
            
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
                    "<?php echo esc_html($institution['tagline']); ?>"
                </div>
            <?php endif; ?>
            
            <?php if (!empty($institution['address'])): ?>
                <div class="institution-address">
                    <?php echo nl2br(esc_html($institution['address'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="certificate-title">Surat Bukti Pendaftaran</div>
            <div class="certificate-subtitle">Certificate of Registration</div>
            
            <div class="registration-number">
                <span class="reg-label">Nomor:</span>
                <span class="reg-value"><?php echo esc_html($reg_number); ?></span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="certificate-content">
            <div class="section-title">
                <span class="ornament">❖</span> Data Pendaftar <span class="ornament">❖</span>
            </div>
            
            <table class="formal-table">
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
                
                <!-- System fields -->
                <tr>
                    <td class="field-label">Tanggal Pendaftaran</td>
                    <td class="field-value"><?php echo esc_html(date('d F Y', strtotime($submission_date ?? 'now'))); ?></td>
                </tr>
                <tr>
                    <td class="field-label">Status Pendaftaran</td>
                    <td class="field-value" style="color: <?php echo esc_attr($colors['primary']); ?>; font-weight: bold;">
                        ✓ TERDAFTAR SAH
                    </td>
                </tr>
                <tr>
                    <td class="field-label">Masa Berlaku</td>
                    <td class="field-value">Sampai dengan proses seleksi selesai</td>
                </tr>
            </table>
            
            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-title">Mengetahui,</div>
                <div class="signature-location">
                    <?php echo esc_html($institution['address'] ? explode(',', $institution['address'])[0] : ''); ?>, 
                    <?php echo esc_html(date('d F Y')); ?>
                </div>
                <div class="signature-name">Kepala Sekolah/Institusi</div>
                <div class="signature-title-name">Authorized Signature</div>
            </div>
        </div>
        
        <!-- QR Code -->
        <?php if ($qr_code_url): ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-code">
                    <img src="<?php echo esc_url($qr_code_url); ?>" alt="QR Code Verifikasi">
                    <div class="qr-label">Kode Verifikasi</div>
                </div>
            </div>
        <?php else: ?>
            <div class="qr-section <?php echo esc_attr(str_replace('_', '-', $qr_position)); ?>">
                <div class="qr-code">
                    <div class="qr-placeholder">
                        QR CODE<br>VERIFIKASI
                    </div>
                    <div class="qr-label">Kode Verifikasi</div>
                </div>
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
                        Informasi: <?php echo esc_html($institution['contact']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="print-date">
                    Dicetak pada tanggal: <?php echo esc_html(date('d F Y, H:i')); ?> WIB
                </div>
            </div>
        </div>
    </div>
</body>
</html>
