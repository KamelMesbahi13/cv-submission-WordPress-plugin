<?php
/**
 * Plugin Name: CV Submissions Manager
 * Description: Gestion des soumissions de CV depuis le formulaire "Déposez votre CV"
 * Version: 1.1.0
 * Author: KML
 * Text Domain: cv-submissions-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CVSM_VERSION', '1.1.0');
define('CVSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CVSM_PLUGIN_URL', plugin_dir_url(__FILE__));

define('CVSM_DEBUG', false);

function cvsm_log($message, $data = null) {
    if (!CVSM_DEBUG) return;
    
    $log_message = '[CVSM ' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $log_message .= ' | Data: ' . print_r($data, true);
    }
    error_log($log_message);
    
    $log_file = CVSM_PLUGIN_DIR . 'cvsm-debug.log';
    file_put_contents($log_file, $log_message . "\n", FILE_APPEND | LOCK_EX);
}

function cvsm_activate() {
    cvsm_create_table();
    
    $upload_dir = wp_upload_dir();
    $cv_upload_dir = $upload_dir['basedir'] . '/cv-submissions';
    if (!file_exists($cv_upload_dir)) {
        wp_mkdir_p($cv_upload_dir);
        file_put_contents($cv_upload_dir . '/.htaccess', 'Options -Indexes');
    }
    
    add_option('cvsm_version', CVSM_VERSION);
    cvsm_log('Plugin activated');
}
register_activation_hook(__FILE__, 'cvsm_activate');

function cvsm_deactivate() {
    cvsm_log('Plugin deactivated');
}
register_deactivation_hook(__FILE__, 'cvsm_deactivate');

function cvsm_init() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        cvsm_log('Table not found, creating...');
        cvsm_create_table();
    }
}
add_action('plugins_loaded', 'cvsm_init');

function cvsm_create_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cv_submissions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(255) NOT NULL DEFAULT '',
        email VARCHAR(255) NOT NULL DEFAULT '',
        phone VARCHAR(50) NOT NULL DEFAULT '',
        wilaya VARCHAR(100) NOT NULL DEFAULT '',
        domaine VARCHAR(100) NOT NULL DEFAULT '',
        specialite VARCHAR(255) NOT NULL DEFAULT '',
        experience VARCHAR(50) NOT NULL DEFAULT '',
        cv_file VARCHAR(500) DEFAULT '',
        profile_type VARCHAR(100) DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        admin_notes TEXT,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    cvsm_log('Table creation executed');
}

/**
 * Add admin menu
 */
function cvsm_admin_menu() {
    add_menu_page(
        'CV Submissions',
        'CV Submissions',
        'manage_options',
        'cv-submissions',
        'cvsm_dashboard_page',
        'dashicons-id-alt',
        30
    );
}
add_action('admin_menu', 'cvsm_admin_menu');

/**
 * Enqueue admin styles and scripts
 */
function cvsm_admin_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_cv-submissions') {
        return;
    }
    
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array('jquery'), null, true);
    
    wp_enqueue_style('cvsm-admin-css', CVSM_PLUGIN_URL . 'css/admin-styles.css', array(), CVSM_VERSION);
    
    wp_enqueue_script('cvsm-admin-js', CVSM_PLUGIN_URL . 'js/admin-scripts.js', array('jquery', 'datatables-js'), CVSM_VERSION, true);
    
    wp_localize_script('cvsm-admin-js', 'cvsmAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cvsm_nonce'),
        'emploiUrl' => home_url('/accept-page/')
    ));
}
add_action('admin_enqueue_scripts', 'cvsm_admin_enqueue_scripts');

function cvsm_dashboard_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        echo '<div class="notice notice-error"><p>La table de base de données n\'existe pas. <a href="' . admin_url('admin.php?page=cv-submissions&action=create_table') . '">Cliquez ici pour la créer</a></p></div>';
        return;
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'create_table') {
        cvsm_create_table();
        echo '<div class="notice notice-success"><p>Table créée avec succès!</p></div>';
    }
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name") ?: 0;
    $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'") ?: 0;
    $accepted = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'accepted'") ?: 0;
    $rejected = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'rejected'") ?: 0;
    
    $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC");
    
    $debug_log = '';
    $log_file = CVSM_PLUGIN_DIR . 'cvsm-debug.log';
    if (file_exists($log_file)) {
        $debug_log = file_get_contents($log_file);
        $debug_log = nl2br(esc_html(substr($debug_log, -5000)));
    }
    
    ?>
    <div class="wrap cvsm-dashboard">
        <h1 class="cvsm-title">
            <span class="dashicons dashicons-id-alt"></span>
            Gestion des CV Soumis
        </h1>
        
        <?php if (CVSM_DEBUG): ?>
        <div class="cvsm-debug-panel" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
            <h3 style="margin-top: 0;">🔧 Debug Panel (CVSM_DEBUG is ON)</h3>
            <p><strong>Table Status:</strong> <?php echo $table_exists ? '✅ Exists' : '❌ Missing'; ?></p>
            <p><strong>Total Records:</strong> <?php echo $total; ?></p>
            <p><strong>Elementor Pro Active:</strong> <?php echo class_exists('ElementorPro\Plugin') ? '✅ Yes' : '❌ No'; ?></p>
            <p><strong>Hook Test:</strong> <a href="<?php echo admin_url('admin.php?page=cv-submissions&action=test_insert'); ?>" class="button button-secondary">Insert Test Record</a></p>
            <p><strong>Clear Log:</strong> <a href="<?php echo admin_url('admin.php?page=cv-submissions&action=clear_log'); ?>" class="button button-secondary">Clear Debug Log</a></p>
            
            <?php if ($debug_log): ?>
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer;"><strong>Debug Log (last 5000 chars)</strong></summary>
                <pre style="background: #1e1e1e; color: #dcdcdc; padding: 10px; max-height: 300px; overflow: auto; font-size: 11px;"><?php echo $debug_log; ?></pre>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php
        if (isset($_GET['action']) && $_GET['action'] === 'test_insert' && current_user_can('manage_options')) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'full_name' => 'Test User ' . time(),
                    'email' => 'test' . time() . '@example.com',
                    'phone' => '0555000000',
                    'wilaya' => 'Test Wilaya',
                    'domaine' => 'Test Domaine',
                    'specialite' => 'Test Spécialité',
                    'experience' => '5',
                    'profile_type' => 'Test',
                    'status' => 'pending',
                    'submitted_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            if ($result) {
                echo '<div class="notice notice-success"><p>✅ Test record inserted successfully! ID: ' . $wpdb->insert_id . '</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=cv-submissions') . '"; }, 2000);</script>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Failed to insert test record. Error: ' . $wpdb->last_error . '</p></div>';
            }
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'clear_log' && current_user_can('manage_options')) {
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
            }
            echo '<div class="notice notice-success"><p>✅ Debug log cleared!</p></div>';
        }
        ?>
        
        <!-- Statistics Cards -->
        <div class="cvsm-stats-grid">
            <div class="cvsm-stat-card total">
                <div class="stat-icon"><span class="dashicons dashicons-groups"></span></div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo esc_html($total); ?></span>
                    <span class="stat-label">Total des CV</span>
                </div>
            </div>
            <div class="cvsm-stat-card pending">
                <div class="stat-icon"><span class="dashicons dashicons-clock"></span></div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo esc_html($pending); ?></span>
                    <span class="stat-label">En attente</span>
                </div>
            </div>
            <div class="cvsm-stat-card accepted">
                <div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo esc_html($accepted); ?></span>
                    <span class="stat-label">Acceptés</span>
                </div>
            </div>
            <div class="cvsm-stat-card rejected">
                <div class="stat-icon"><span class="dashicons dashicons-dismiss"></span></div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo esc_html($rejected); ?></span>
                    <span class="stat-label">Rejetés</span>
                </div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="cvsm-filter-tabs">
            <button class="filter-tab active" data-filter="all">Tous</button>
            <button class="filter-tab" data-filter="pending">En attente</button>
            <button class="filter-tab" data-filter="accepted">Acceptés</button>
            <button class="filter-tab" data-filter="rejected">Rejetés</button>
        </div>
        
        <!-- Custom Search Bar (outside scrolling area) -->
        <div class="cvsm-controls-bar">
            <div class="cvsm-controls-left">
                <label>Afficher 
                    <select id="cvsm-page-length">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                entrées</label>
            </div>
            <div class="cvsm-controls-right">
                <div class="cvsm-search-box">
                    <input type="text" id="cvsm-search-input" placeholder="Rechercher par nom, email, téléphone...">
                </div>
            </div>
        </div>
        
        <!-- Submissions Table -->
        <div class="cvsm-table-container">
            <table id="cv-submissions-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom et Prénom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Wilaya</th>
                        <th>Domaine</th>
                        <th>Spécialité</th>
                        <th>Expérience</th>
                        <th>CV</th>
                        <th>Type de Profil</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions): foreach ($submissions as $sub): ?>
                    <tr data-status="<?php echo esc_attr($sub->status); ?>" data-id="<?php echo esc_attr($sub->id); ?>">
                        <td><?php echo esc_html($sub->id); ?></td>
                        <td><strong><?php echo esc_html($sub->full_name); ?></strong></td>
                        <td><a href="mailto:<?php echo esc_attr($sub->email); ?>"><?php echo esc_html($sub->email); ?></a></td>
                        <td><a href="tel:<?php echo esc_attr($sub->phone); ?>"><?php echo esc_html($sub->phone); ?></a></td>
                        <td><?php echo esc_html($sub->wilaya); ?></td>
                        <td><?php echo esc_html($sub->domaine); ?></td>
                        <td><?php echo esc_html($sub->specialite); ?></td>
                        <td><?php echo esc_html($sub->experience); ?> ans</td>
                        <td>
                            <?php if (!empty($sub->cv_file)): ?>
                                <a href="<?php echo esc_url($sub->cv_file); ?>" target="_blank" class="cv-download-btn">
                                    <span class="dashicons dashicons-pdf"></span> Voir CV
                                </a>
                            <?php else: ?>
                                <span class="no-cv">Pas de CV</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($sub->profile_type); ?></td>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($sub->submitted_at)); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($sub->status); ?>">
                                <?php 
                                $status_labels = array(
                                    'pending' => 'En attente',
                                    'accepted' => 'Accepté',
                                    'rejected' => 'Rejeté'
                                );
                                echo esc_html($status_labels[$sub->status] ?? $sub->status);
                                ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <?php if ($sub->status === 'pending'): ?>
                                <button class="cvsm-btn cvsm-btn-accept" data-id="<?php echo esc_attr($sub->id); ?>">
                                    <span class="dashicons dashicons-yes"></span> Accepter
                                </button>
                                <button class="cvsm-btn cvsm-btn-reject" data-id="<?php echo esc_attr($sub->id); ?>">
                                    <span class="dashicons dashicons-no"></span> Rejeter
                                </button>
                            <?php else: ?>
                                <span class="action-done">
                                    <?php echo $sub->status === 'accepted' ? '✓ Traité' : '✗ Fermé'; ?>
                                </span>
                            <?php endif; ?>
                            <button class="cvsm-btn cvsm-btn-delete" data-id="<?php echo esc_attr($sub->id); ?>" title="Supprimer définitivement">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler for accepting a CV
 */
function cvsm_accept_cv() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    $result = $wpdb->update(
        $table_name,
        array(
            'status' => 'accepted',
            'processed_at' => current_time('mysql')
        ),
        array('id' => $id),
        array('%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        cvsm_log('CV accepted', array('id' => $id));
        wp_send_json_success(array(
            'message' => 'CV accepté avec succès',
            'redirect' => home_url('/accept-page/')
        ));
    } else {
        wp_send_json_error('Erreur lors de la mise à jour');
    }
}
add_action('wp_ajax_cvsm_accept_cv', 'cvsm_accept_cv');

/**
 * AJAX handler for rejecting a CV
 */
function cvsm_reject_cv() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    $result = $wpdb->update(
        $table_name,
        array(
            'status' => 'rejected',
            'processed_at' => current_time('mysql')
        ),
        array('id' => $id),
        array('%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        cvsm_log('CV rejected', array('id' => $id));
        wp_send_json_success(array('message' => 'CV rejeté'));
    } else {
        wp_send_json_error('Erreur lors de la mise à jour');
    }
}
add_action('wp_ajax_cvsm_reject_cv', 'cvsm_reject_cv');

/**
 * AJAX handler for deleting a CV
 */
function cvsm_delete_cv() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    $result = $wpdb->delete(
        $table_name,
        array('id' => $id),
        array('%d')
    );
    
    if ($result !== false) {
        cvsm_log('CV deleted', array('id' => $id));
        wp_send_json_success(array('message' => 'CV supprimé définitivement'));
    } else {
        wp_send_json_error('Erreur lors de la suppression');
    }
}
add_action('wp_ajax_cvsm_delete_cv', 'cvsm_delete_cv');

/**
 * ============================================
 * ELEMENTOR PRO FORM INTEGRATION (FIXED)
 * ============================================
 * Using multiple hooks at different priorities to ensure capture
 */

/**
 * Primary hook: elementor_pro/forms/new_record - Priority 1 (very early)
 */
function cvsm_capture_elementor_form($record, $handler) {
    cvsm_log('=== Elementor Form Hook Triggered ===');
    
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_submissions';
        
        // Ensure table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            cvsm_log('Table not found, creating...');
            cvsm_create_table();
        }
        
        // Get form name for logging
        $form_name = $record->get_form_settings('form_name');
        cvsm_log('Form Name: ' . $form_name);
        
        // Get all form fields
        $raw_fields = $record->get('fields');
        cvsm_log('Raw Fields Received', $raw_fields);
        
        $fields = array();
        foreach ($raw_fields as $id => $field) {
            $fields[$id] = $field['value'];
        }
        
        cvsm_log('Processed Fields', $fields);
        
        // Map using EXACT field IDs from your Elementor form
        $full_name = isset($fields['name']) ? $fields['name'] : '';
        $email = isset($fields['email']) ? $fields['email'] : '';
        $phone = isset($fields['field_f02977c']) ? $fields['field_f02977c'] : '';
        $wilaya = isset($fields['field_f2f6b29']) ? $fields['field_f2f6b29'] : '';
        $domaine = isset($fields['field_4b4ee37']) ? $fields['field_4b4ee37'] : '';
        $specialite = isset($fields['field_5a7fc87']) ? $fields['field_5a7fc87'] : '';
        $experience = isset($fields['field_3b0b51f']) ? $fields['field_3b0b51f'] : '';
        $profile_type = isset($fields['field_77273a6']) ? $fields['field_77273a6'] : '';
        
        cvsm_log('Mapped Values', array(
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'wilaya' => $wilaya,
            'domaine' => $domaine,
            'specialite' => $specialite,
            'experience' => $experience,
            'profile_type' => $profile_type
        ));
        
        // Handle arrays (checkboxes, multi-select)
        if (is_array($profile_type)) {
            $profile_type = implode(', ', $profile_type);
        }
        
        // Only proceed if we have at least name or email
        if (empty($full_name) && empty($email)) {
            cvsm_log('SKIP: No name or email found');
            return;
        }
        
        // Handle CV file upload - field ID: field_9f19160
        $cv_file = '';
        $files = $record->get('files');
        cvsm_log('Files Received', $files);
        
        if (!empty($files) && isset($files['field_9f19160'])) {
            $file_data = $files['field_9f19160'];
            if (!empty($file_data['url'])) {
                $url = $file_data['url'];
                // Handle case where URL is an array
                if (is_array($url)) {
                    $cv_file = isset($url[0]) ? $url[0] : '';
                } else {
                    $cv_file = $url;
                }
            }
        }
        
        // Also check general files array
        if (empty($cv_file) && !empty($files)) {
            foreach ($files as $file_key => $file_data) {
                if (is_array($file_data) && !empty($file_data['url'])) {
                    $url = $file_data['url'];
                    // Handle case where URL is an array
                    if (is_array($url)) {
                        $cv_file = isset($url[0]) ? $url[0] : '';
                    } else {
                        $cv_file = $url;
                    }
                    break;
                } elseif (is_string($file_data)) {
                    $cv_file = $file_data;
                    break;
                }
            }
        }
        
        // Final safety check - ensure cv_file is a string
        if (is_array($cv_file)) {
            $cv_file = isset($cv_file[0]) ? $cv_file[0] : '';
        }
        
        cvsm_log('CV File URL (final)', $cv_file);
        
        // Insert into database
        $insert_data = array(
            'full_name' => sanitize_text_field($full_name),
            'email' => sanitize_email($email),
            'phone' => sanitize_text_field($phone),
            'wilaya' => sanitize_text_field($wilaya),
            'domaine' => sanitize_text_field($domaine),
            'specialite' => sanitize_text_field($specialite),
            'experience' => sanitize_text_field($experience),
            'cv_file' => is_string($cv_file) ? esc_url_raw($cv_file) : '',
            'profile_type' => sanitize_text_field($profile_type),
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        );
        
        cvsm_log('Inserting data', $insert_data);
        
        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            cvsm_log('INSERT FAILED! DB Error: ' . $wpdb->last_error);
        } else {
            cvsm_log('INSERT SUCCESS! New ID: ' . $wpdb->insert_id);
        }
        
    } catch (Exception $e) {
        cvsm_log('EXCEPTION: ' . $e->getMessage());
    } catch (Error $e) {
        cvsm_log('ERROR: ' . $e->getMessage());
    }
}

// Hook with priority 1 (very early)
add_action('elementor_pro/forms/new_record', 'cvsm_capture_elementor_form', 1, 2);

/**
 * ============================================
 * REST API WEBHOOK ENDPOINT (RELIABLE METHOD)
 * ============================================
 * Configure Elementor form to send webhook to:
 * https://bet-ces-cet.com/wp-json/cvsm/v1/submit
 */
function cvsm_register_rest_routes() {
    register_rest_route('cvsm/v1', '/submit', array(
        'methods' => 'POST',
        'callback' => 'cvsm_webhook_handler',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'cvsm_register_rest_routes');

/**
 * Webhook handler - receives form data via REST API
 */
function cvsm_webhook_handler($request) {
    cvsm_log('=== WEBHOOK RECEIVED ===');
    
    $params = $request->get_params();
    cvsm_log('Webhook Params', $params);
    
    // Get the body as JSON if params are empty
    if (empty($params)) {
        $body = $request->get_body();
        cvsm_log('Webhook Body', $body);
        $params = json_decode($body, true);
        if ($params === null) {
            parse_str($body, $params);
        }
    }
    
    cvsm_log('Parsed Params', $params);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    // Ensure table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        cvsm_create_table();
    }
    
    // Try to map fields - be flexible with field names
    $full_name = '';
    $email = '';
    $phone = '';
    $wilaya = '';
    $domaine = '';
    $specialite = '';
    $experience = '';
    $profile_type = '';
    $cv_file = '';
    
    // Direct mapping by your field IDs
    if (isset($params['name'])) $full_name = $params['name'];
    if (isset($params['email'])) $email = $params['email'];
    if (isset($params['field_f02977c'])) $phone = $params['field_f02977c'];
    if (isset($params['field_f2f6b29'])) $wilaya = $params['field_f2f6b29'];
    if (isset($params['field_4b4ee37'])) $domaine = $params['field_4b4ee37'];
    if (isset($params['field_5a7fc87'])) $specialite = $params['field_5a7fc87'];
    if (isset($params['field_3b0b51f'])) $experience = $params['field_3b0b51f'];
    if (isset($params['field_77273a6'])) $profile_type = $params['field_77273a6'];
    if (isset($params['field_9f19160'])) $cv_file = $params['field_9f19160'];
    
    // Also check for nested 'fields' structure
    if (isset($params['fields']) && is_array($params['fields'])) {
        foreach ($params['fields'] as $field) {
            $id = $field['id'] ?? '';
            $value = $field['value'] ?? '';
            
            switch ($id) {
                case 'name': $full_name = $value; break;
                case 'email': $email = $value; break;
                case 'field_f02977c': $phone = $value; break;
                case 'field_f2f6b29': $wilaya = $value; break;
                case 'field_4b4ee37': $domaine = $value; break;
                case 'field_5a7fc87': $specialite = $value; break;
                case 'field_3b0b51f': $experience = $value; break;
                case 'field_77273a6': $profile_type = $value; break;
                case 'field_9f19160': $cv_file = $value; break;
            }
        }
    }
    
    // Fallback: Search all params for common field name patterns
    foreach ($params as $key => $value) {
        if (is_array($value)) continue;
        $key_lower = strtolower($key);
        
        if (empty($full_name) && (strpos($key_lower, 'nom') !== false || strpos($key_lower, 'name') !== false || strpos($key_lower, 'prenom') !== false)) {
            $full_name = $value;
        }
        if (empty($email) && (strpos($key_lower, 'email') !== false || strpos($key_lower, 'mail') !== false)) {
            $email = $value;
        }
        if (empty($phone) && (strpos($key_lower, 'phone') !== false || strpos($key_lower, 'tel') !== false)) {
            $phone = $value;
        }
        if (empty($wilaya) && strpos($key_lower, 'wilaya') !== false) {
            $wilaya = $value;
        }
        if (empty($domaine) && strpos($key_lower, 'domaine') !== false) {
            $domaine = $value;
        }
        if (empty($specialite) && strpos($key_lower, 'specialite') !== false) {
            $specialite = $value;
        }
        if (empty($experience) && strpos($key_lower, 'experience') !== false) {
            $experience = $value;
        }
        if (empty($profile_type) && (strpos($key_lower, 'profile') !== false || strpos($key_lower, 'profil') !== false)) {
            $profile_type = $value;
        }
    }
    
    // Handle array values
    if (is_array($profile_type)) {
        $profile_type = implode(', ', $profile_type);
    }
    
    cvsm_log('Mapped Values', array(
        'full_name' => $full_name,
        'email' => $email,
        'phone' => $phone,
        'wilaya' => $wilaya,
        'domaine' => $domaine,
        'specialite' => $specialite,
        'experience' => $experience,
        'profile_type' => $profile_type,
        'cv_file' => $cv_file
    ));
    
    // Only proceed if we have at least name or email
    if (empty($full_name) && empty($email)) {
        cvsm_log('WEBHOOK: No name or email found, skipping insert');
        return new WP_REST_Response(array('status' => 'skipped', 'message' => 'No name or email provided'), 200);
    }
    
    // Insert into database
    $result = $wpdb->insert(
        $table_name,
        array(
            'full_name' => sanitize_text_field($full_name),
            'email' => sanitize_email($email),
            'phone' => sanitize_text_field($phone),
            'wilaya' => sanitize_text_field($wilaya),
            'domaine' => sanitize_text_field($domaine),
            'specialite' => sanitize_text_field($specialite),
            'experience' => sanitize_text_field($experience),
            'cv_file' => esc_url_raw($cv_file),
            'profile_type' => sanitize_text_field($profile_type),
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        cvsm_log('WEBHOOK INSERT FAILED! Error: ' . $wpdb->last_error);
        return new WP_REST_Response(array('status' => 'error', 'message' => $wpdb->last_error), 500);
    }
    
    cvsm_log('WEBHOOK INSERT SUCCESS! ID: ' . $wpdb->insert_id);
    return new WP_REST_Response(array('status' => 'success', 'id' => $wpdb->insert_id), 200);
}

/**
 * Hook into Contact Form 7 submission - using before_send_mail for reliability
 */
function cvsm_capture_cf7_form($contact_form) {
    // Wrap everything in error handling to never break CF7
    try {
        // Check if WPCF7_Submission class exists
        if (!class_exists('WPCF7_Submission')) {
            return $contact_form;
        }
        
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return $contact_form;
        }
        
        $data = $submission->get_posted_data();
        if (empty($data)) {
            return $contact_form;
        }
        
        cvsm_log('CF7 Form Submission', $data);
        
        // Check if our table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_submissions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            cvsm_create_table();
        }
        
        $files = $submission->uploaded_files();
        
        // Flexible field mapping
        $full_name = '';
        $email = '';
        $phone = '';
        $wilaya = '';
        $domaine = '';
        $specialite = '';
        $experience = '';
        $profile_type = '';
        
        // Search through all fields for matching names
        foreach ($data as $key => $value) {
            if ($key === '_wpcf7' || $key === '_wpcf7_version' || strpos($key, '_wpcf7') === 0) {
                continue; // Skip CF7 internal fields
            }
            
            $key_lower = strtolower($key);
            $val = is_array($value) ? implode(', ', $value) : $value;
            $val = trim($val);
            
            if (empty($val)) continue;
            
            if (strpos($key_lower, 'nom') !== false || strpos($key_lower, 'name') !== false || strpos($key_lower, 'prenom') !== false) {
                if (empty($full_name)) $full_name = $val;
            }
            if (strpos($key_lower, 'email') !== false || strpos($key_lower, 'mail') !== false) {
                if (empty($email)) $email = $val;
            }
            if (strpos($key_lower, 'phone') !== false || strpos($key_lower, 'tel') !== false) {
                if (empty($phone)) $phone = $val;
            }
            if (strpos($key_lower, 'wilaya') !== false) {
                $wilaya = $val;
            }
            if (strpos($key_lower, 'domaine') !== false || strpos($key_lower, 'domain') !== false) {
                $domaine = $val;
            }
            if (strpos($key_lower, 'specialite') !== false || strpos($key_lower, 'specialty') !== false) {
                $specialite = $val;
            }
            if (strpos($key_lower, 'experience') !== false || strpos($key_lower, 'annee') !== false) {
                $experience = $val;
            }
            if (strpos($key_lower, 'profile') !== false || strpos($key_lower, 'profil') !== false) {
                if (empty($profile_type)) $profile_type = $val;
            }
        }
        
        // Only proceed if we have at least name or email
        if (empty($full_name) && empty($email)) {
            return $contact_form;
        }
        
        // Handle CV file
        $cv_file = '';
        if (!empty($files) && is_array($files)) {
            foreach ($files as $file_key => $file_paths) {
                if (!empty($file_paths)) {
                    $file_path = is_array($file_paths) ? reset($file_paths) : $file_paths;
                    if (!empty($file_path) && is_string($file_path) && file_exists($file_path)) {
                        $upload_dir = wp_upload_dir();
                        $cv_upload_dir = $upload_dir['basedir'] . '/cv-submissions';
                        if (!file_exists($cv_upload_dir)) {
                            wp_mkdir_p($cv_upload_dir);
                        }
                        $filename = sanitize_file_name(basename($file_path));
                        $unique_name = time() . '_' . $filename;
                        $new_path = $cv_upload_dir . '/' . $unique_name;
                        if (@copy($file_path, $new_path)) {
                            $cv_file = $upload_dir['baseurl'] . '/cv-submissions/' . $unique_name;
                        }
                    }
                    break;
                }
            }
        }
        
        // Insert into database
        $result = $wpdb->insert(
            $table_name,
            array(
                'full_name' => sanitize_text_field($full_name),
                'email' => sanitize_email($email),
                'phone' => sanitize_text_field($phone),
                'wilaya' => sanitize_text_field($wilaya),
                'domaine' => sanitize_text_field($domaine),
                'specialite' => sanitize_text_field($specialite),
                'experience' => sanitize_text_field($experience),
                'cv_file' => esc_url_raw($cv_file),
                'profile_type' => sanitize_text_field($profile_type),
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            cvsm_log('CF7 Insert Success! ID: ' . $wpdb->insert_id);
        } else {
            cvsm_log('CF7 Insert Failed! Error: ' . $wpdb->last_error);
        }
        
    } catch (Exception $e) {
        cvsm_log('CF7 Exception: ' . $e->getMessage());
    } catch (Error $e) {
        cvsm_log('CF7 Error: ' . $e->getMessage());
    }
    
    return $contact_form;
}
add_action('wpcf7_before_send_mail', 'cvsm_capture_cf7_form', 10, 1);

/**
 * Hook into WPForms submission (fallback)
 */
function cvsm_capture_wpforms($fields, $entry, $form_data, $entry_id) {
    $full_name = '';
    $email = '';
    $phone = '';
    $wilaya = '';
    $domaine = '';
    $specialite = '';
    $experience = '';
    $profile_type = '';
    $cv_file = '';
    
    foreach ($fields as $field) {
        $name = strtolower($field['name'] ?? '');
        $value = $field['value'] ?? '';
        
        if (strpos($name, 'nom') !== false || strpos($name, 'name') !== false) {
            $full_name = $value;
        }
        if (strpos($name, 'email') !== false) {
            $email = $value;
        }
        if (strpos($name, 'phone') !== false || strpos($name, 'tel') !== false) {
            $phone = $value;
        }
        if (strpos($name, 'wilaya') !== false) {
            $wilaya = $value;
        }
        if (strpos($name, 'domaine') !== false) {
            $domaine = $value;
        }
        if (strpos($name, 'specialite') !== false) {
            $specialite = $value;
        }
        if (strpos($name, 'experience') !== false) {
            $experience = $value;
        }
        if (strpos($name, 'profile') !== false || strpos($name, 'type') !== false) {
            $profile_type = $value;
        }
        if ($field['type'] === 'file-upload' && !empty($value)) {
            $cv_file = $value;
        }
    }
    
    if (empty($full_name) && empty($email)) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    $wpdb->insert(
        $table_name,
        array(
            'full_name' => sanitize_text_field($full_name),
            'email' => sanitize_email($email),
            'phone' => sanitize_text_field($phone),
            'wilaya' => sanitize_text_field($wilaya),
            'domaine' => sanitize_text_field($domaine),
            'specialite' => sanitize_text_field($specialite),
            'experience' => sanitize_text_field($experience),
            'cv_file' => esc_url_raw($cv_file),
            'profile_type' => sanitize_text_field($profile_type),
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
}
add_action('wpforms_process_complete', 'cvsm_capture_wpforms', 10, 4);

/**
 * Add test data for development (remove in production)
 */
function cvsm_add_test_data() {
    if (!isset($_GET['cvsm_add_test']) || $_GET['cvsm_add_test'] !== '1') {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    $test_data = array(
        array(
            'full_name' => 'Ahmed Benali',
            'email' => 'ahmed.benali@example.com',
            'phone' => '0555123456',
            'wilaya' => 'Alger',
            'domaine' => 'Architecture',
            'specialite' => 'Design Urbain',
            'experience' => '+5',
            'profile_type' => 'pour recrutement',
            'status' => 'pending'
        ),
        array(
            'full_name' => 'Fatima Zohra',
            'email' => 'fatima.zohra@example.com',
            'phone' => '0661789012',
            'wilaya' => 'Oran',
            'domaine' => 'Génie Civil',
            'specialite' => 'Structures',
            'experience' => '+3',
            'profile_type' => 'sous-traitance',
            'status' => 'pending'
        ),
        array(
            'full_name' => 'Mohamed Cherif',
            'email' => 'mohamed.cherif@example.com',
            'phone' => '0770456789',
            'wilaya' => 'Constantine',
            'domaine' => 'Topographie',
            'specialite' => 'Cartographie',
            'experience' => '+10',
            'profile_type' => 'sous-traitance, pour recrutement',
            'status' => 'pending'
        )
    );
    
    foreach ($test_data as $data) {
        $wpdb->insert(
            $table_name,
            array_merge($data, array('submitted_at' => current_time('mysql'))),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    wp_redirect(admin_url('admin.php?page=cv-submissions&test_added=1'));
    exit;
}
add_action('admin_init', 'cvsm_add_test_data');

/**
 * Frontend Shortcode: [cvsm_accepted_list]
 * Displays accepted candidates in a grid
 */
function cvsm_shortcode_accepted_list($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    // Query accepted submissions
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'accepted' ORDER BY processed_at DESC");
    
    // Enqueue styles if not already (or add inline)
    ob_start();
    ?>
    <style>
        .cvsm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin: 40px 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .cvsm-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .cvsm-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
        .cvsm-card-header {
            background-color: #0F172A;
            padding: 24px;
            color: white;
            position: relative;
        }
        .cvsm-card-header h3 {
            margin: 0;
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .cvsm-badges {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .cvsm-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cvsm-badge.domaine {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .cvsm-badge.type {
            background: #3b82f6;
            color: #fff;
        }
        .cvsm-card-body {
            padding: 24px;
            flex-grow: 1;
            color: #334155;
        }
        .cvsm-info-group {
            margin-bottom: 20px;
        }
        .cvsm-info-group:last-child {
            margin-bottom: 0;
        }
        .cvsm-info-row {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .cvsm-info-row:last-child {
            margin-bottom: 0;
        }
        .cvsm-label {
            min-width: 90px;
            font-weight: 600;
            color: #64748b;
            flex-shrink: 0;
        }
        .cvsm-value {
            color: #1e293b;
            font-weight: 500;
            word-break: break-word;
        }
        .cvsm-value a {
            color: #2563eb;
            text-decoration: none;
        }
        .cvsm-value a:hover {
            text-decoration: underline;
        }
        .cvsm-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 16px 0;
        }
        .cvsm-card-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cvsm-date {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .cvsm-btn-download {
            display: inline-flex;
            align-items: center;
            background: #0F172A;
            color: white !important;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none !important;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        .cvsm-btn-download:hover {
            background: #1e293b;
        }
        .cvsm-empty {
            text-align: center;
            padding: 40px;
            background: #f8fafc;
            border-radius: 8px;
            color: #64748b;
            border: 1px dashed #cbd5e1;
        }
    </style>

    <div class="cvsm-accepted-container">
        <?php if ($results): ?>
            <div class="cvsm-grid">
                <?php foreach ($results as $profile): ?>
                    <div class="cvsm-card">
                        <div class="cvsm-card-header">
                            <h3><?php echo esc_html($profile->full_name); ?></h3>
                            <div class="cvsm-badges">
                                <span class="cvsm-badge domaine"><?php echo esc_html($profile->domaine); ?></span>
                                <?php if (!empty($profile->profile_type)): ?>
                                    <span class="cvsm-badge type"><?php echo esc_html($profile->profile_type); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="cvsm-card-body">
                            <div class="cvsm-info-group">
                                <div class="cvsm-info-row">
                                    <span class="cvsm-label">Spécialité:</span>
                                    <span class="cvsm-value"><?php echo esc_html($profile->specialite); ?></span>
                                </div>
                                <div class="cvsm-info-row">
                                    <span class="cvsm-label">Expérience:</span>
                                    <span class="cvsm-value"><?php echo esc_html($profile->experience); ?> ans</span>
                                </div>
                                <?php if (!empty($profile->wilaya)): ?>
                                <div class="cvsm-info-row">
                                    <span class="cvsm-label">Wilaya:</span>
                                    <span class="cvsm-value"><?php echo esc_html($profile->wilaya); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cvsm-divider"></div>
                            
                            <div class="cvsm-info-group">
                                <?php if (!empty($profile->email)): ?>
                                <div class="cvsm-info-row">
                                    <span class="cvsm-label">Email:</span>
                                    <span class="cvsm-value"><a href="mailto:<?php echo esc_attr($profile->email); ?>"><?php echo esc_html($profile->email); ?></a></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile->phone)): ?>
                                <div class="cvsm-info-row">
                                    <span class="cvsm-label">Tél:</span>
                                    <span class="cvsm-value"><a href="tel:<?php echo esc_attr($profile->phone); ?>"><?php echo esc_html($profile->phone); ?></a></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="cvsm-card-footer">
                            <span class="cvsm-date">Soumis le <?php echo date_i18n('d/m/Y', strtotime($profile->submitted_at)); ?></span>
                            <?php if (!empty($profile->cv_file)): ?>
                                <a href="<?php echo esc_url($profile->cv_file); ?>" target="_blank" class="cvsm-btn-download">
                                    Voir CV →
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="cvsm-empty">
                <p>Aucun profil accepté pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cvsm_accepted_list', 'cvsm_shortcode_accepted_list');
