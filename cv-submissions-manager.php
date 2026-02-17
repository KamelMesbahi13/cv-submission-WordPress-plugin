<?php
/**
 * Plugin Name: Emploi Et Devis
 * Description: Gestion des soumissions de CV depuis le formulaire "Déposez votre CV"
 * Version: 1.2.0
 * Author: KML
 * Text Domain: cv-submissions-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CVSM_VERSION', '1.2.1');
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
    devis_create_table();
    offre_service_create_table();
    
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
    $devis_table_name = $wpdb->prefix . 'devis_submissions';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    $devis_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$devis_table_name'") === $devis_table_name;
    
    if (!$table_exists) {
        cvsm_log('CV Table not found, creating...');
        cvsm_create_table();
    }
    
    if (!$devis_table_exists) {
        cvsm_log('Devis Table not found, creating...');
        devis_create_table();
    }
    
    $offre_table_name = $wpdb->prefix . 'offre_service_submissions';
    $offre_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$offre_table_name'") === $offre_table_name;
    
    if (!$offre_table_exists) {
        cvsm_log('Offre Service Table not found, creating...');
        offre_service_create_table();
    }
    
    // Migration: Add new columns to offre_service_submissions if they don't exist
    if ($offre_table_exists) {
        $offre_table_name = $wpdb->prefix . 'offre_service_submissions';
        $columns = $wpdb->get_col("DESCRIBE $offre_table_name", 0);
        
        if (!in_array('sexe', $columns)) {
            $wpdb->query("ALTER TABLE $offre_table_name ADD COLUMN sexe VARCHAR(50) NOT NULL DEFAULT '' AFTER offre_file");
            cvsm_log('Added sexe column to offre_service_submissions');
        }
        if (!in_array('niveau_education', $columns)) {
            $wpdb->query("ALTER TABLE $offre_table_name ADD COLUMN niveau_education VARCHAR(255) NOT NULL DEFAULT '' AFTER sexe");
            cvsm_log('Added niveau_education column to offre_service_submissions');
        }
        if (!in_array('description_offre', $columns)) {
            $wpdb->query("ALTER TABLE $offre_table_name ADD COLUMN description_offre TEXT DEFAULT '' AFTER niveau_education");
            cvsm_log('Added description_offre column to offre_service_submissions');
        }
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
 * Create devis_submissions table for quote requests
 */
function devis_create_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'devis_submissions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(255) NOT NULL DEFAULT '',
        email VARCHAR(255) NOT NULL DEFAULT '',
        phone VARCHAR(50) NOT NULL DEFAULT '',
        lieu_projet VARCHAR(255) NOT NULL DEFAULT '',
        lieu_siege VARCHAR(255) NOT NULL DEFAULT '',
        services TEXT DEFAULT '',
        plans_file VARCHAR(500) DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        admin_notes TEXT,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    cvsm_log('Devis table creation executed');
}

/**
 * Create offre_service_submissions table for service offers
 */
function offre_service_create_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'offre_service_submissions';
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
        offre_file VARCHAR(500) DEFAULT '',
        sexe VARCHAR(50) NOT NULL DEFAULT '',
        niveau_education VARCHAR(255) NOT NULL DEFAULT '',
        description_offre TEXT DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        admin_notes TEXT,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    cvsm_log('Offre Service table creation executed');
}

/**
 * Add admin menu
 */
function cvsm_admin_menu() {
    add_menu_page(
        'Emploi Et Devis',
        'Emploi Et Devis',
        'manage_options',
        'cv-submissions',
        'cvsm_dashboard_page',
        'dashicons-id-alt',
        30
    );
    
    // Explicitly add the first submenu page to rename it to "Emploi"
    add_submenu_page(
        'cv-submissions',
        'Emploi',
        'Emploi',
        'manage_options',
        'cv-submissions',
        'cvsm_dashboard_page'
    );
    
    // Add Devis submenu page
    add_submenu_page(
        'cv-submissions',
        'Devis',
        'Devis',
        'manage_options',
        'cvsm-devis',
        'cvsm_devis_page'
    );
    
    // Add Offre Service submenu page
    add_submenu_page(
        'cv-submissions',
        'Offre Service',
        'Offre Service',
        'manage_options',
        'cvsm-offre-service',
        'cvsm_offre_service_page'
    );
    
    add_submenu_page(
        'cv-submissions',
        'Wilaya',
        'Wilaya',
        'manage_options',
        'cvsm-locations',
        'cvsm_locations_page'
    );
    
    add_submenu_page(
        'cv-submissions',
        'Métier Offre d\'Emploi',
        'Métier Offre d\'Emploi',
        'manage_options',
        'cvsm-categories',
        'cvsm_categories_page'
    );
    
    add_submenu_page(
        'cv-submissions',
        'Métier Offre Service',
        'Métier Offre Service',
        'manage_options',
        'cvsm-service-categories',
        'cvsm_service_categories_page'
    );
    
    add_submenu_page(
        'cv-submissions',
        'Type de profil',
        'Type de profil',
        'manage_options',
        'cvsm-tags',
        'cvsm_tags_page'
    );
}
add_action('admin_menu', 'cvsm_admin_menu');

/**
 * Enqueue admin styles and scripts
 */
function cvsm_admin_enqueue_scripts($hook) {
    // Load on both Emploi (toplevel) and Devis pages
    $allowed_hooks = array(
        'toplevel_page_cv-submissions',
        'emploi-et-devis_page_cvsm-devis',
        'emploi-et-devis_page_cvsm-offre-service'
    );
    
    if (!in_array($hook, $allowed_hooks)) {
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
    
    // Handle Manual Add Submission
    if (isset($_POST['action']) && $_POST['action'] === 'cvsm_manual_add' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manual_add_nonce');
        
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $wilaya = sanitize_text_field($_POST['wilaya']);
        $domaine = sanitize_text_field($_POST['domaine']);
        $specialite = sanitize_text_field($_POST['specialite']);
        $experience = sanitize_text_field($_POST['experience']);
        $profile_type = sanitize_text_field($_POST['profile_type']);
        $cv_file = '';
        
        // Handle File Upload
        if (!empty($_FILES['cv_file']['name'])) {
            $uploaded = $_FILES['cv_file'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploaded, $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $cv_file = $movefile['url'];
            } else {
                echo '<div class="notice notice-error"><p>Erreur lors du téléchargement du fichier: ' . $movefile['error'] . '</p></div>';
            }
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'wilaya' => $wilaya,
                'domaine' => $domaine,
                'specialite' => $specialite,
                'experience' => $experience,
                'profile_type' => $profile_type,
                'cv_file' => $cv_file,
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            cvsm_trigger_cache_clear(); // Clear cache on new addition
            echo '<div class="notice notice-success"><p>Candidat ajouté avec succès!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erreur lors de l\'ajout: ' . $wpdb->last_error . '</p></div>';
        }
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
            Gestion des employeurs
            <button id="cvsm-open-modal" class="page-title-action">Ajouter un candidat</button>
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
                    <span class="stat-label">Total des employeurs</span>
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
                <div class="alignleft actions bulkactions" style="margin-right: 15px; display: inline-flex; align-items: center; gap: 5px;">
                    <select name="cvsm_bulk_action" id="cvsm-bulk-action-selector">
                        <option value="-1">Actions groupées</option>
                        <option value="accept">Accepter</option>
                        <option value="delete">Supprimer</option>
                    </select>
                    <input type="button" id="cvsm-doaction" class="button action" value="Appliquer">
                </div>
                
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
                        <th class="check-column"><input type="checkbox" id="cb-select-all-1"></th>
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
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="post[]" value="<?php echo esc_attr($sub->id); ?>">
                        </th>
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
        
        <!-- Add Candidate Modal -->
        <?php $lists = cvsm_get_lists(); ?>
        <div id="cvsm-modal" class="cvsm-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
            <div class="cvsm-modal-content" style="background-color:#fefefe; margin:5% auto; padding:20px; border:1px solid #888; width:50%; max-width:600px; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <h2 style="margin:0;">Ajouter un candidat</h2>
                    <span id="cvsm-close-modal" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
                </div>
                
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field('cvsm_manual_add_nonce'); ?>
                    <input type="hidden" name="action" value="cvsm_manual_add">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="full_name">Nom et Prénom</label></th>
                            <td><input name="full_name" type="text" id="full_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email">Email</label></th>
                            <td><input name="email" type="email" id="email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="phone">Téléphone</label></th>
                            <td><input name="phone" type="text" id="phone" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="wilaya">Wilaya</label></th>
                            <td>
                                <select name="wilaya" id="wilaya" class="regular-text">
                                    <option value="">Sélectionner une wilaya</option>
                                    <?php foreach ($lists['wilayas'] as $w): ?>
                                        <option value="<?php echo esc_attr($w); ?>"><?php echo esc_html($w); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="domaine">Domaine</label></th>
                            <td>
                                <select name="domaine" id="domaine" class="regular-text">
                                    <option value="">Sélectionner un domaine</option>
                                    <?php foreach ($lists['domaines'] as $d): ?>
                                        <option value="<?php echo esc_attr($d); ?>"><?php echo esc_html($d); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="specialite">Spécialité</label></th>
                            <td><input name="specialite" type="text" id="specialite" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="experience">Expérience (Années)</label></th>
                            <td><input name="experience" type="number" id="experience" class="regular-text" min="0" step="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="profile_type">Type de Profil</label></th>
                            <td>
                                <select name="profile_type" id="profile_type" class="regular-text">
                                    <?php foreach ($lists['profile_types'] as $pt): ?>
                                        <option value="<?php echo esc_attr($pt); ?>"><?php echo esc_html($pt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cv_file">CV (PDF)</label></th>
                            <td><input name="cv_file" type="file" id="cv_file" accept=".pdf,.doc,.docx"></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Ajouter le candidat">
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var modal = $('#cvsm-modal');
            var btn = $('#cvsm-open-modal');
            var span = $('#cvsm-close-modal');
            
            btn.on('click', function(e) {
                e.preventDefault();
                modal.fadeIn();
            });
            
            span.on('click', function() {
                modal.fadeOut();
            });
            
            $(window).on('click', function(event) {
                if ($(event.target).is(modal)) {
                    modal.fadeOut();
                }
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Devis Dashboard Page - Identical styling to Emploi
 */
function cvsm_devis_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'devis_submissions';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        echo '<div class="notice notice-error"><p>La table de base de données n\'existe pas. <a href="' . admin_url('admin.php?page=cvsm-devis&action=create_table') . '">Cliquez ici pour la créer</a></p></div>';
        return;
    }
    
    // Handle Manual Add Submission
    if (isset($_POST['action']) && $_POST['action'] === 'devis_manual_add' && current_user_can('manage_options')) {
        check_admin_referer('devis_manual_add_nonce');
        
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $lieu_projet = sanitize_text_field($_POST['lieu_projet']);
        $lieu_siege = sanitize_text_field($_POST['lieu_siege']);
        $services = isset($_POST['services']) ? sanitize_text_field(implode(', ', $_POST['services'])) : '';
        $plans_file = '';
        
        // Handle File Upload
        if (!empty($_FILES['plans_file']['name'])) {
            $uploaded = $_FILES['plans_file'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploaded, $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $plans_file = $movefile['url'];
            } else {
                echo '<div class="notice notice-error"><p>Erreur lors du téléchargement du fichier: ' . $movefile['error'] . '</p></div>';
            }
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'lieu_projet' => $lieu_projet,
                'lieu_siege' => $lieu_siege,
                'services' => $services,
                'plans_file' => $plans_file,
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            cvsm_trigger_cache_clear();
            echo '<div class="notice notice-success"><p>Devis ajouté avec succès!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erreur lors de l\'ajout: ' . $wpdb->last_error . '</p></div>';
        }
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'create_table') {
        devis_create_table();
        echo '<div class="notice notice-success"><p>Table créée avec succès!</p></div>';
    }
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name") ?: 0;
    $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'") ?: 0;
    $accepted = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'accepted'") ?: 0;
    $rejected = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'rejected'") ?: 0;
    
    $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC");
    
    ?>
    <div class="wrap cvsm-dashboard">
        <h1 class="cvsm-title">
            <span class="dashicons dashicons-media-document"></span>
            Gestion des Devis
            <button id="devis-open-modal" class="page-title-action">Ajouter un devis</button>
        </h1>
        
        <!-- Statistics Cards -->
        <div class="cvsm-stats-grid">
            <div class="cvsm-stat-card total">
                <div class="stat-icon"><span class="dashicons dashicons-clipboard"></span></div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo esc_html($total); ?></span>
                    <span class="stat-label">Total des demandes</span>
                </div>
            </div>
        </div>
        
        <!-- Custom Search Bar (outside scrolling area) -->
        <div class="cvsm-controls-bar">
            <div class="cvsm-controls-left">
                <div class="alignleft actions bulkactions" style="margin-right: 15px; display: inline-flex; align-items: center; gap: 5px;">
                    <select name="devis_bulk_action" id="devis-bulk-action-selector">
                        <option value="-1">Actions groupées</option>
                        <option value="delete">Supprimer</option>
                    </select>
                    <input type="button" id="devis-doaction" class="button action" value="Appliquer">
                </div>
                
                <label>Afficher 
                    <select id="devis-page-length">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                entrées</label>
            </div>
            <div class="cvsm-controls-right">
                <div class="cvsm-search-box">
                    <input type="text" id="devis-search-input" placeholder="Rechercher par nom, email, téléphone...">
                </div>
            </div>
        </div>
        
        <!-- Submissions Table -->
        <div class="cvsm-table-container">
            <table id="devis-submissions-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="devis-cb-select-all"></th>
                        <th>#</th>
                        <th>Nom et Prénom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Lieu de Projet</th>
                        <th>Lieu du Siège</th>
                        <th>Services</th>
                        <th>Plans</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions): foreach ($submissions as $sub): ?>
                    <tr data-status="<?php echo esc_attr($sub->status); ?>" data-id="<?php echo esc_attr($sub->id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="devis_post[]" value="<?php echo esc_attr($sub->id); ?>">
                        </th>
                        <td><?php echo esc_html($sub->id); ?></td>
                        <td><strong><?php echo esc_html($sub->full_name); ?></strong></td>
                        <td><a href="mailto:<?php echo esc_attr($sub->email); ?>"><?php echo esc_html($sub->email); ?></a></td>
                        <td><a href="tel:<?php echo esc_attr($sub->phone); ?>"><?php echo esc_html($sub->phone); ?></a></td>
                        <td><?php echo esc_html($sub->lieu_projet); ?></td>
                        <td><?php echo esc_html($sub->lieu_siege); ?></td>
                        <td><?php echo esc_html($sub->services); ?></td>
                        <td>
                            <?php if (!empty($sub->plans_file)): ?>
                                <a href="<?php echo esc_url($sub->plans_file); ?>" target="_blank" class="cv-download-btn">
                                    <span class="dashicons dashicons-media-document"></span> Voir Plans
                                </a>
                            <?php else: ?>
                                <span class="no-cv">Pas de plans</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($sub->submitted_at)); ?></td>
                        <td class="actions-cell">
                            <button class="cvsm-btn cvsm-btn-delete devis-btn-delete" data-id="<?php echo esc_attr($sub->id); ?>" title="Supprimer définitivement">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        </div>
        
        <!-- Add Devis Modal -->
        <div id="devis-modal" class="cvsm-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
            <div class="cvsm-modal-content" style="background-color:#fefefe; margin:5% auto; padding:20px; border:1px solid #888; width:50%; max-width:600px; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <h2 style="margin:0;">Ajouter une demande de devis</h2>
                    <span id="devis-close-modal" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
                </div>
                
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field('devis_manual_add_nonce'); ?>
                    <input type="hidden" name="action" value="devis_manual_add">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="devis_full_name">Nom et Prénom</label></th>
                            <td><input name="full_name" type="text" id="devis_full_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devis_email">Email</label></th>
                            <td><input name="email" type="email" id="devis_email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devis_phone">Téléphone</label></th>
                            <td><input name="phone" type="text" id="devis_phone" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devis_lieu_projet">Lieu de Projet</label></th>
                            <td><input name="lieu_projet" type="text" id="devis_lieu_projet" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devis_lieu_siege">Lieu du Siège</label></th>
                            <td><input name="lieu_siege" type="text" id="devis_lieu_siege" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Services</label></th>
                            <td>
                                <label><input type="checkbox" name="services[]" value="Étude électrique courants forts"> Étude électrique courants forts</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude électrique courants faibles"> Étude électrique courants faibles</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude HVAC/CVC"> Étude HVAC/CVC</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude des fluides médicaux"> Étude des fluides médicaux</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude de plomberie sanitaire et gaz"> Étude de plomberie sanitaire et gaz</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude de détection incendie"> Étude de détection incendie</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude de protection incendie"> Étude de protection incendie</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude de climatisation"> Étude de climatisation</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude de chauffage"> Étude de chauffage</label><br>
                                <label><input type="checkbox" name="services[]" value="Étude de Désenfumage"> Étude de Désenfumage</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devis_plans_file">Plans</label></th>
                            <td><input name="plans_file" type="file" id="devis_plans_file" accept=".pdf,.dwg,.dxf,.zip,.rar"></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="devis_submit" class="button button-primary" value="Ajouter le devis">
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var modal = $('#devis-modal');
            var btn = $('#devis-open-modal');
            var span = $('#devis-close-modal');
            
            btn.on('click', function(e) {
                e.preventDefault();
                modal.fadeIn();
            });
            
            span.on('click', function() {
                modal.fadeOut();
            });
            
            $(window).on('click', function(event) {
                if ($(event.target).is(modal)) {
                    modal.fadeOut();
                }
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Offre Service Dashboard Page - Identical styling to Emploi
 */
function cvsm_offre_service_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'offre_service_submissions';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        echo '<div class="notice notice-error"><p>La table de base de données n\'existe pas. <a href="' . admin_url('admin.php?page=cvsm-offre-service&action=create_table') . '">Cliquez ici pour la créer</a></p></div>';
        return;
    }
    
    // Handle Manual Add Submission
    if (isset($_POST['action']) && $_POST['action'] === 'offre_service_manual_add' && current_user_can('manage_options')) {
        check_admin_referer('offre_service_manual_add_nonce');
        
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $wilaya = sanitize_text_field($_POST['wilaya']);
        $domaine = sanitize_text_field($_POST['domaine']);
        $specialite = sanitize_text_field($_POST['specialite']);
        $experience = sanitize_text_field($_POST['experience']);
        $sexe = sanitize_text_field($_POST['sexe']);
        $niveau_education = sanitize_text_field($_POST['niveau_education']);
        $description_offre = sanitize_textarea_field($_POST['description_offre']);
        $offre_file = '';
        
        // Handle File Upload
        if (!empty($_FILES['offre_file']['name'])) {
            $uploaded = $_FILES['offre_file'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploaded, $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $offre_file = $movefile['url'];
            } else {
                echo '<div class="notice notice-error"><p>Erreur lors du téléchargement du fichier: ' . $movefile['error'] . '</p></div>';
            }
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'wilaya' => $wilaya,
                'domaine' => $domaine,
                'specialite' => $specialite,
                'experience' => $experience,
                'offre_file' => $offre_file,
                'sexe' => $sexe,
                'niveau_education' => $niveau_education,
                'description_offre' => $description_offre,
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            cvsm_trigger_cache_clear();
            echo '<div class="notice notice-success"><p>Offre de service ajoutée avec succès!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erreur lors de l\'ajout: ' . $wpdb->last_error . '</p></div>';
        }
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'create_table') {
        offre_service_create_table();
        echo '<div class="notice notice-success"><p>Table créée avec succès!</p></div>';
    }
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name") ?: 0;
    $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'") ?: 0;
    $accepted = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'accepted'") ?: 0;
    $rejected = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'rejected'") ?: 0;
    
    $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC");
    
    ?>
    <div class="wrap cvsm-dashboard">
        <h1 class="cvsm-title">
            <span class="dashicons dashicons-businessman"></span>
            Gestion des Offres de Service
            <button id="offre-service-open-modal" class="page-title-action">Ajouter une offre</button>
        </h1>
        
        <!-- Statistics Cards -->
        <div class="cvsm-stats-grid">
            <div class="cvsm-stat-card total">
                <div class="stat-icon"><span class="dashicons dashicons-groups"></span></div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo esc_html($total); ?></span>
                    <span class="stat-label">Total des offres</span>
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
                    <span class="stat-label">Acceptées</span>
                </div>
            </div>
            <div class="cvsm-stat-card rejected">
                <div class="stat-icon"><span class="dashicons dashicons-dismiss"></span></div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo esc_html($rejected); ?></span>
                    <span class="stat-label">Rejetées</span>
                </div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="cvsm-filter-tabs">
            <button class="filter-tab active" data-filter="all" data-table="offre-service">Tous</button>
            <button class="filter-tab" data-filter="pending" data-table="offre-service">En attente</button>
            <button class="filter-tab" data-filter="accepted" data-table="offre-service">Acceptées</button>
            <button class="filter-tab" data-filter="rejected" data-table="offre-service">Rejetées</button>
        </div>
        
        <!-- Custom Search Bar -->
        <div class="cvsm-controls-bar">
            <div class="cvsm-controls-left">
                <div class="alignleft actions bulkactions" style="margin-right: 15px; display: inline-flex; align-items: center; gap: 5px;">
                    <select name="offre_service_bulk_action" id="offre-service-bulk-action-selector">
                        <option value="-1">Actions groupées</option>
                        <option value="accept">Accepter</option>
                        <option value="delete">Supprimer</option>
                    </select>
                    <input type="button" id="offre-service-doaction" class="button action" value="Appliquer">
                </div>
                
                <label>Afficher 
                    <select id="offre-service-page-length">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                entrées</label>
            </div>
            <div class="cvsm-controls-right">
                <div class="cvsm-search-box">
                    <input type="text" id="offre-service-search-input" placeholder="Rechercher par nom, email, téléphone...">
                </div>
            </div>
        </div>
        
        <!-- Submissions Table -->
        <div class="cvsm-table-container">
            <table id="offre-service-submissions-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="offre-service-cb-select-all"></th>
                        <th>#</th>
                        <th>Nom et Prénom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Wilaya</th>
                        <th>Domaine</th>
                        <th>Spécialité</th>
                        <th>Expérience</th>
                        <th>Offre Service</th>
                        <th>Sexe</th>
                        <th>Niveau d'éducation</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions): foreach ($submissions as $sub): ?>
                    <tr data-status="<?php echo esc_attr($sub->status); ?>" data-id="<?php echo esc_attr($sub->id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="offre_service_post[]" value="<?php echo esc_attr($sub->id); ?>">
                        </th>
                        <td><?php echo esc_html($sub->id); ?></td>
                        <td><strong><?php echo esc_html($sub->full_name); ?></strong></td>
                        <td><a href="mailto:<?php echo esc_attr($sub->email); ?>"><?php echo esc_html($sub->email); ?></a></td>
                        <td><a href="tel:<?php echo esc_attr($sub->phone); ?>"><?php echo esc_html($sub->phone); ?></a></td>
                        <td><?php echo esc_html($sub->wilaya); ?></td>
                        <td><?php echo esc_html($sub->domaine); ?></td>
                        <td><?php echo esc_html($sub->specialite); ?></td>
                        <td><?php echo esc_html($sub->experience); ?> ans</td>
                        <td>
                            <?php if (!empty($sub->offre_file)): ?>
                                <a href="<?php echo esc_url($sub->offre_file); ?>" target="_blank" class="cv-download-btn">
                                    <span class="dashicons dashicons-media-document"></span> Voir Offre
                                </a>
                            <?php else: ?>
                                <span class="no-cv">Pas de fichier</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($sub->sexe); ?></td>
                        <td><?php echo esc_html($sub->niveau_education); ?></td>
                        <td><?php echo esc_html(wp_trim_words($sub->description_offre, 10, '...')); ?></td>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($sub->submitted_at)); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($sub->status); ?>">
                                <?php 
                                $status_labels = array(
                                    'pending' => 'En attente',
                                    'accepted' => 'Acceptée',
                                    'rejected' => 'Rejetée'
                                );
                                echo esc_html($status_labels[$sub->status] ?? $sub->status);
                                ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <?php if ($sub->status === 'pending'): ?>
                                <button class="cvsm-btn offre-service-btn-accept" data-id="<?php echo esc_attr($sub->id); ?>">
                                    <span class="dashicons dashicons-yes"></span> Accepter
                                </button>
                                <button class="cvsm-btn offre-service-btn-reject" data-id="<?php echo esc_attr($sub->id); ?>">
                                    <span class="dashicons dashicons-no"></span> Rejeter
                                </button>
                            <?php else: ?>
                                <span class="action-done">
                                    <?php echo $sub->status === 'accepted' ? '✓ Traité' : '✗ Fermé'; ?>
                                </span>
                            <?php endif; ?>
                            <button class="cvsm-btn offre-service-btn-delete" data-id="<?php echo esc_attr($sub->id); ?>" title="Supprimer définitivement">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        </div>
        
        <!-- Add Offre Service Modal -->
        <?php $lists = cvsm_get_lists(); ?>
        <div id="offre-service-modal" class="cvsm-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
            <div class="cvsm-modal-content" style="background-color:#fefefe; margin:5% auto; padding:20px; border:1px solid #888; width:50%; max-width:600px; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <h2 style="margin:0;">Ajouter une offre de service</h2>
                    <span id="offre-service-close-modal" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
                </div>
                
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field('offre_service_manual_add_nonce'); ?>
                    <input type="hidden" name="action" value="offre_service_manual_add">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="os_full_name">Nom et Prénom</label></th>
                            <td><input name="full_name" type="text" id="os_full_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_email">Email</label></th>
                            <td><input name="email" type="email" id="os_email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_phone">Téléphone</label></th>
                            <td><input name="phone" type="text" id="os_phone" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_wilaya">Wilaya</label></th>
                            <td>
                                <select name="wilaya" id="os_wilaya" class="regular-text">
                                    <option value="">Sélectionner une wilaya</option>
                                    <?php foreach ($lists['wilayas'] as $w): ?>
                                        <option value="<?php echo esc_attr($w); ?>"><?php echo esc_html($w); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_domaine">Domaine</label></th>
                            <td>
                                <select name="domaine" id="os_domaine" class="regular-text">
                                    <option value="">Sélectionner un domaine</option>
                                    <?php foreach ($lists['domaines'] as $d): ?>
                                        <option value="<?php echo esc_attr($d); ?>"><?php echo esc_html($d); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_specialite">Spécialité</label></th>
                            <td><input name="specialite" type="text" id="os_specialite" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_experience">Expérience (Années)</label></th>
                            <td><input name="experience" type="number" id="os_experience" class="regular-text" min="0" step="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_offre_file">Offre Service (PDF)</label></th>
                            <td><input name="offre_file" type="file" id="os_offre_file" accept=".pdf,.doc,.docx"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_sexe">Sexe</label></th>
                            <td>
                                <select name="sexe" id="os_sexe" class="regular-text">
                                    <option value="">Sélectionner</option>
                                    <option value="Homme">Homme</option>
                                    <option value="Femme">Femme</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_niveau_education">Niveau d'éducation</label></th>
                            <td><input name="niveau_education" type="text" id="os_niveau_education" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="os_description_offre">Description de l'offre</label></th>
                            <td><textarea name="description_offre" id="os_description_offre" class="regular-text" rows="4"></textarea></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="os_submit" class="button button-primary" value="Ajouter l'offre">
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var modal = $('#offre-service-modal');
            var btn = $('#offre-service-open-modal');
            var span = $('#offre-service-close-modal');
            
            btn.on('click', function(e) {
                e.preventDefault();
                modal.fadeIn();
            });
            
            span.on('click', function() {
                modal.fadeOut();
            });
            
            $(window).on('click', function(event) {
                if ($(event.target).is(modal)) {
                    modal.fadeOut();
                }
            });
        });
        </script>
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
        cvsm_trigger_cache_clear();
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
        cvsm_trigger_cache_clear();
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
        cvsm_trigger_cache_clear();
        cvsm_log('CV deleted', array('id' => $id));
        wp_send_json_success(array('message' => 'CV supprimé définitivement'));
    } else {
        wp_send_json_error('Erreur lors de la suppression');
    }
}
add_action('wp_ajax_cvsm_delete_cv', 'cvsm_delete_cv');

/**
 * AJAX handler for bulk actions
 */
function cvsm_bulk_action() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    
    if (empty($ids) || empty($action_type)) {
        wp_send_json_error('Paramètres manquants');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    $count = 0;
    
    if ($action_type === 'accept') {
        foreach ($ids as $id) {
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
            if ($result !== false) $count++;
        }
    } elseif ($action_type === 'delete') {
        foreach ($ids as $id) {
            $result = $wpdb->delete(
                $table_name,
                array('id' => $id),
                array('%d')
            );
            if ($result !== false) $count++;
        }
    }
    
    cvsm_trigger_cache_clear();
    wp_send_json_success(array('message' => "$count éléments traités avec succès"));
}
add_action('wp_ajax_cvsm_bulk_action', 'cvsm_bulk_action');

/**
 * ============================================
 * DEVIS AJAX HANDLERS
 * ============================================
 */

/**
 * AJAX handler for accepting a Devis
 */
function devis_accept_submission() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'devis_submissions';
    
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
        cvsm_trigger_cache_clear();
        cvsm_log('Devis accepted', array('id' => $id));
        wp_send_json_success(array('message' => 'Devis accepté avec succès'));
    } else {
        wp_send_json_error('Erreur lors de la mise à jour');
    }
}
add_action('wp_ajax_devis_accept', 'devis_accept_submission');

/**
 * AJAX handler for rejecting a Devis
 */
function devis_reject_submission() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'devis_submissions';
    
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
        cvsm_trigger_cache_clear();
        cvsm_log('Devis rejected', array('id' => $id));
        wp_send_json_success(array('message' => 'Devis rejeté'));
    } else {
        wp_send_json_error('Erreur lors de la mise à jour');
    }
}
add_action('wp_ajax_devis_reject', 'devis_reject_submission');

/**
 * AJAX handler for deleting a Devis
 */
function devis_delete_submission() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'devis_submissions';
    
    $result = $wpdb->delete(
        $table_name,
        array('id' => $id),
        array('%d')
    );
    
    if ($result !== false) {
        cvsm_trigger_cache_clear();
        cvsm_log('Devis deleted', array('id' => $id));
        wp_send_json_success(array('message' => 'Devis supprimé définitivement'));
    } else {
        wp_send_json_error('Erreur lors de la suppression');
    }
}
add_action('wp_ajax_devis_delete', 'devis_delete_submission');

/**
 * AJAX handler for Devis bulk actions
 */
function devis_bulk_action() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    
    if (empty($ids) || empty($action_type)) {
        wp_send_json_error('Paramètres manquants');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'devis_submissions';
    $count = 0;
    
    if ($action_type === 'accept') {
        foreach ($ids as $id) {
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
            if ($result !== false) $count++;
        }
    } elseif ($action_type === 'delete') {
        foreach ($ids as $id) {
            $result = $wpdb->delete(
                $table_name,
                array('id' => $id),
                array('%d')
            );
            if ($result !== false) $count++;
        }
    }
    
    cvsm_trigger_cache_clear();
    wp_send_json_success(array('message' => "$count éléments traités avec succès"));
}
add_action('wp_ajax_devis_bulk_action', 'devis_bulk_action');

/**
 * ============================================
 * OFFRE SERVICE AJAX HANDLERS
 * ============================================
 */

/**
 * AJAX handler for accepting an Offre Service
 */
function offre_service_accept() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'offre_service_submissions';
    
    // Ensure processed_at column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'processed_at'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN processed_at DATETIME NULL");
    }
    
    // Use direct query for reliability
    $result = $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table_name SET status = 'accepted', processed_at = %s WHERE id = %d",
            current_time('mysql'),
            $id
        )
    );
    
    if ($result !== false && $result > 0) {
        cvsm_trigger_cache_clear();
        cvsm_log('Offre Service accepted', array('id' => $id));
        wp_send_json_success(array(
            'message' => 'Offre de service acceptée avec succès'
        ));
    } else {
        $error = $wpdb->last_error;
        cvsm_log('Offre Service accept failed', array('id' => $id, 'error' => $error, 'result' => $result));
        wp_send_json_error('Erreur: ' . ($error ? $error : 'Aucune ligne mise à jour (ID: ' . $id . ')'));
    }
}
add_action('wp_ajax_offre_service_accept', 'offre_service_accept');

/**
 * AJAX handler for rejecting an Offre Service
 */
function offre_service_reject() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'offre_service_submissions';
    
    // Ensure processed_at column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'processed_at'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN processed_at DATETIME NULL");
    }
    
    // Use direct query for reliability
    $result = $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table_name SET status = 'rejected', processed_at = %s WHERE id = %d",
            current_time('mysql'),
            $id
        )
    );
    
    if ($result !== false && $result > 0) {
        cvsm_trigger_cache_clear();
        cvsm_log('Offre Service rejected', array('id' => $id));
        wp_send_json_success(array('message' => 'Offre de service rejetée'));
    } else {
        $error = $wpdb->last_error;
        cvsm_log('Offre Service reject failed', array('id' => $id, 'error' => $error, 'result' => $result));
        wp_send_json_error('Erreur: ' . ($error ? $error : 'Aucune ligne mise à jour (ID: ' . $id . ')'));
    }
}
add_action('wp_ajax_offre_service_reject', 'offre_service_reject');

/**
 * AJAX handler for deleting an Offre Service
 */
function offre_service_delete() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $id = intval($_POST['id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'offre_service_submissions';
    
    $result = $wpdb->delete(
        $table_name,
        array('id' => $id),
        array('%d')
    );
    
    if ($result !== false) {
        cvsm_trigger_cache_clear();
        cvsm_log('Offre Service deleted', array('id' => $id));
        wp_send_json_success(array('message' => 'Offre de service supprimée définitivement'));
    } else {
        wp_send_json_error('Erreur lors de la suppression');
    }
}
add_action('wp_ajax_offre_service_delete', 'offre_service_delete');

/**
 * AJAX handler for Offre Service bulk actions
 */
function offre_service_bulk_action() {
    check_ajax_referer('cvsm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    
    if (empty($ids) || empty($action_type)) {
        wp_send_json_error('Paramètres manquants');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'offre_service_submissions';
    $count = 0;
    
    if ($action_type === 'accept') {
        foreach ($ids as $id) {
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
            if ($result !== false) $count++;
        }
    } elseif ($action_type === 'delete') {
        foreach ($ids as $id) {
            $result = $wpdb->delete(
                $table_name,
                array('id' => $id),
                array('%d')
            );
            if ($result !== false) $count++;
        }
    }
    
    cvsm_trigger_cache_clear();
    wp_send_json_success(array('message' => "$count éléments traités avec succès"));
}
add_action('wp_ajax_offre_service_bulk_action', 'offre_service_bulk_action');

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
        
        // Detect if this is a Devis form by checking for devis-specific fields
        $is_devis_form = isset($fields['field_2effaf7']) || isset($fields['field_4c127c0']) || isset($fields['field_18c6a0e']);
        
        // Also check form name for "devis" keyword
        if (stripos($form_name, 'devis') !== false) {
            $is_devis_form = true;
        }
        
        // Detect if this is an Offre Service form
        $is_offre_service_form = false;
        // Check form name for "offre" keyword
        if (stripos($form_name, 'offre') !== false) {
            $is_offre_service_form = true;
        }
        // Also check field labels in raw_fields for "offre" keyword
        if (!$is_offre_service_form && !empty($raw_fields)) {
            foreach ($raw_fields as $field_id => $field_data) {
                $field_title = isset($field_data['title']) ? $field_data['title'] : '';
                if (stripos($field_title, 'offre') !== false) {
                    $is_offre_service_form = true;
                    break;
                }
            }
        }
        // Also check the referring page URL for "offre-service" or "offre_service"
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (!$is_offre_service_form && (stripos($referer, 'offre-service') !== false || stripos($referer, 'offre_service') !== false)) {
            $is_offre_service_form = true;
        }
        // Check page title from form settings
        if (!$is_offre_service_form) {
            $page_url = $record->get_form_settings('form_post_url');
            if (!empty($page_url) && stripos($page_url, 'offre') !== false) {
                $is_offre_service_form = true;
            }
        }
        
        cvsm_log('Is Devis Form: ' . ($is_devis_form ? 'Yes' : 'No'));
        cvsm_log('Is Offre Service Form: ' . ($is_offre_service_form ? 'Yes' : 'No'));
        
        // Common fields
        $full_name = isset($fields['name']) ? $fields['name'] : '';
        $email = isset($fields['email']) ? $fields['email'] : '';
        $phone = isset($fields['field_f02977c']) ? $fields['field_f02977c'] : '';
        
        // Only proceed if we have at least name or email
        if (empty($full_name) && empty($email)) {
            cvsm_log('SKIP: No name or email found');
            return;
        }
        
        // Handle file upload
        $file_url = '';
        $files = $record->get('files');
        cvsm_log('Files Received', $files);
        
        if (!empty($files) && isset($files['field_9f19160'])) {
            $file_data = $files['field_9f19160'];
            if (!empty($file_data['url'])) {
                $url = $file_data['url'];
                if (is_array($url)) {
                    $file_url = isset($url[0]) ? $url[0] : '';
                } else {
                    $file_url = $url;
                }
            }
        }
        
        // Also check general files array
        if (empty($file_url) && !empty($files)) {
            foreach ($files as $file_key => $file_data) {
                if (is_array($file_data) && !empty($file_data['url'])) {
                    $url = $file_data['url'];
                    if (is_array($url)) {
                        $file_url = isset($url[0]) ? $url[0] : '';
                    } else {
                        $file_url = $url;
                    }
                    break;
                } elseif (is_string($file_data)) {
                    $file_url = $file_data;
                    break;
                }
            }
        }
        
        // Final safety check - ensure file_url is a string
        if (is_array($file_url)) {
            $file_url = isset($file_url[0]) ? $file_url[0] : '';
        }
        
        cvsm_log('File URL (final)', $file_url);
        
        if ($is_devis_form) {
            // ============================================
            // DEVIS FORM PROCESSING
            // ============================================
            $table_name = $wpdb->prefix . 'devis_submissions';
            
            // Ensure table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$table_exists) {
                cvsm_log('Devis Table not found, creating...');
                devis_create_table();
            }
            
            // Map Devis-specific fields
            $lieu_projet = isset($fields['field_2effaf7']) ? $fields['field_2effaf7'] : '';
            $lieu_siege = isset($fields['field_4c127c0']) ? $fields['field_4c127c0'] : '';
            $services = isset($fields['field_18c6a0e']) ? $fields['field_18c6a0e'] : '';
            
            // Handle arrays (checkboxes, multi-select)
            if (is_array($services)) {
                $services = implode(', ', $services);
            }
            
            $insert_data = array(
                'full_name' => sanitize_text_field($full_name),
                'email' => sanitize_email($email),
                'phone' => sanitize_text_field($phone),
                'lieu_projet' => sanitize_text_field($lieu_projet),
                'lieu_siege' => sanitize_text_field($lieu_siege),
                'services' => sanitize_text_field($services),
                'plans_file' => is_string($file_url) ? esc_url_raw($file_url) : '',
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            );
            
            cvsm_log('Inserting DEVIS data', $insert_data);
            
            $result = $wpdb->insert(
                $table_name,
                $insert_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                cvsm_log('DEVIS INSERT FAILED! DB Error: ' . $wpdb->last_error);
            } else {
                cvsm_log('DEVIS INSERT SUCCESS! New ID: ' . $wpdb->insert_id);
            }
            
        } elseif ($is_offre_service_form) {
            // ============================================
            // OFFRE SERVICE FORM PROCESSING
            // ============================================
            $table_name = $wpdb->prefix . 'offre_service_submissions';
            
            // Ensure table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$table_exists) {
                cvsm_log('Offre Service Table not found, creating...');
                offre_service_create_table();
            }
            
            // Map Offre Service-specific fields
            $wilaya = isset($fields['field_f2f6b29']) ? $fields['field_f2f6b29'] : '';
            $domaine = isset($fields['field_4b4ee37']) ? $fields['field_4b4ee37'] : '';
            $specialite = isset($fields['field_5a7fc87']) ? $fields['field_5a7fc87'] : '';
            $experience = isset($fields['field_3b0b51f']) ? $fields['field_3b0b51f'] : '';
            $sexe = isset($fields['field_a216c21']) ? $fields['field_a216c21'] : '';
            $niveau_education = isset($fields['field_5a7fc87']) ? $fields['field_5a7fc87'] : '';
            $description_offre = isset($fields['field_ee8b0f8']) ? $fields['field_ee8b0f8'] : '';
            
            $insert_data = array(
                'full_name' => sanitize_text_field($full_name),
                'email' => sanitize_email($email),
                'phone' => sanitize_text_field($phone),
                'wilaya' => sanitize_text_field($wilaya),
                'domaine' => sanitize_text_field($domaine),
                'specialite' => sanitize_text_field($specialite),
                'experience' => sanitize_text_field($experience),
                'offre_file' => is_string($file_url) ? esc_url_raw($file_url) : '',
                'sexe' => sanitize_text_field($sexe),
                'niveau_education' => sanitize_text_field($niveau_education),
                'description_offre' => sanitize_textarea_field($description_offre),
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            );
            
            cvsm_log('Inserting OFFRE SERVICE data', $insert_data);
            
            $result = $wpdb->insert(
                $table_name,
                $insert_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                cvsm_log('OFFRE SERVICE INSERT FAILED! DB Error: ' . $wpdb->last_error);
            } else {
                cvsm_log('OFFRE SERVICE INSERT SUCCESS! New ID: ' . $wpdb->insert_id);
            }
            
        } else {
            // ============================================
            // CV/EMPLOI FORM PROCESSING
            // ============================================
            $table_name = $wpdb->prefix . 'cv_submissions';
            
            // Ensure table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$table_exists) {
                cvsm_log('CV Table not found, creating...');
                cvsm_create_table();
            }
            
            // Map CV-specific fields
            $wilaya = isset($fields['field_f2f6b29']) ? $fields['field_f2f6b29'] : '';
            $domaine = isset($fields['field_4b4ee37']) ? $fields['field_4b4ee37'] : '';
            $specialite = isset($fields['field_5a7fc87']) ? $fields['field_5a7fc87'] : '';
            $experience = isset($fields['field_3b0b51f']) ? $fields['field_3b0b51f'] : '';
            $profile_type = isset($fields['field_77273a6']) ? $fields['field_77273a6'] : '';
            
            // Handle arrays (checkboxes, multi-select)
            if (is_array($profile_type)) {
                $profile_type = implode(', ', $profile_type);
            }
            
            $insert_data = array(
                'full_name' => sanitize_text_field($full_name),
                'email' => sanitize_email($email),
                'phone' => sanitize_text_field($phone),
                'wilaya' => sanitize_text_field($wilaya),
                'domaine' => sanitize_text_field($domaine),
                'specialite' => sanitize_text_field($specialite),
                'experience' => sanitize_text_field($experience),
                'cv_file' => is_string($file_url) ? esc_url_raw($file_url) : '',
                'profile_type' => sanitize_text_field($profile_type),
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            );
            
            cvsm_log('Inserting CV data', $insert_data);
            
            $result = $wpdb->insert(
                $table_name,
                $insert_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                cvsm_log('CV INSERT FAILED! DB Error: ' . $wpdb->last_error);
            } else {
                cvsm_log('CV INSERT SUCCESS! New ID: ' . $wpdb->insert_id);
            }
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
 * Helper to clear caches from common plugins
 */
function cvsm_trigger_cache_clear() {
    // 1. WP Rocket
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }

    // 2. W3 Total Cache
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }

    // 3. WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }

    // 4. Autoptimize
    if (class_exists('autoptimizeCache')) {
        autoptimizeCache::clearall();
    }

    // 5. LiteSpeed Cache
    if (defined('LSCWP_V')) {
        do_action('litespeed_purge_all');
    }
}

/**
 * Get standard lists for Domain and Wilaya
 */
function cvsm_get_lists() {
    // Get categories from options or use default
    $saved_categories = get_option('cvsm_categories_list');
    
    // Migration: If no categories exist, create them from hardcoded list
    if ($saved_categories === false) {
        $default_domaines = array(
            'Architecture',
            'CES-CET',
            'Dissinateur projeteur',
            'Génie Civil',
            'Géotechnique',
            'Hydraulique',
            'Métreur',
            'Topographie',
            'VRD'
        );
        sort($default_domaines);
        
        $categories = array();
        
        // Prevent infinite loop if called during init
        remove_action('save_post', 'cvsm_save_post_hook'); 
        
        foreach ($default_domaines as $d) {
            $slug = sanitize_title($d);
            
            // Create Page
            $page_id = wp_insert_post(array(
                'post_title'    => $d,
                'post_content'  => '[cvsm_accepted_list category="' . $slug . '"]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_name'     => $slug
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                $categories[] = array(
                    'name' => $d,
                    'slug' => $slug,
                    'page_id' => $page_id
                );
            }
        }
        
        update_option('cvsm_categories_list', $categories);
        $domaines = array_column($categories, 'name');
    } else {
        // Just extract names for the simple list
        $domaines = array_column($saved_categories, 'name');
        sort($domaines);
    }
    
    // Get wilayas from options or use default
    $saved_wilayas = get_option('cvsm_wilayas_list');
    $wilayas_for_return = array();
    
    // Default Wilayas List
    $default_wilayas = array(
        'Adrar', 'Chlef', 'Laghouat', 'Oum El Bouaghi', 'Batna', 'Béjaïa', 'Biskra', 'Béchar', 
        'Blida', 'Bouira', 'Tamanrasset', 'Tébessa', 'Tlemcen', 'Tiaret', 'Tizi Ouzou', 'Alger', 
        'Djelfa', 'Jijel', 'Sétif', 'Saïda', 'Skikda', 'Sidi Bel Abbès', 'Annaba', 'Guelma', 
        'Constantine', 'Médéa', 'Mostaganem', 'M’Sila', 'Mascara', 'Ouargla', 'Oran', 'El Bayadh', 
        'Illizi', 'Bordj Bou Arréridj', 'Boumerdès', 'El Tarf', 'Tindouf', 'Tissemsilt', 'El Oued', 
        'Khenchela', 'Souk Ahras', 'Tipaza', 'Mila', 'Aïn Defla', 'Naâma', 'Aïn Témouchent', 
        'Ghardaïa', 'Relizane', 'Timimoun', 'Bordj Badji Mokhtar', 'Ouled Djellal', 'Béni Abbès', 
        'In Salah', 'In Guezzam', 'Touggourt', 'Djanet', 'El Meghaier', 'El Menia'
    );
    
    if ($saved_wilayas === false) {
        $saved_wilayas = $default_wilayas;
    }

    // CHECK MIGRATION: If the first item is a string, we need to migrate to Objects {name, slug, page_id}
    $needs_migration = false;
    if (!empty($saved_wilayas)) {
        $first_item = reset($saved_wilayas);
        if (is_string($first_item)) {
            $needs_migration = true;
        }
    } else {
        // Empty list, treat as migrated (empty)
        $saved_wilayas = array(); 
    }
    
    if ($needs_migration) {
        $new_wilayas_structure = array();
        
        // Prevent infinite loop hooks
        remove_action('save_post', 'cvsm_save_post_hook'); 
        
        foreach ($saved_wilayas as $w_name) {
            $slug = sanitize_title($w_name);
            
            // Create Page
            $page_id = wp_insert_post(array(
                'post_title'    => $w_name,
                'post_content'  => '[cvsm_accepted_list wilaya="' . $slug . '"]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_name'     => $slug
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                $new_wilayas_structure[] = array(
                    'name' => $w_name,
                    'slug' => $slug,
                    'page_id' => $page_id
                );
            } else {
                 // Fallback if page creation fails, still keep data
                 $new_wilayas_structure[] = array(
                    'name' => $w_name,
                    'slug' => $slug,
                    'page_id' => 0
                );
            }
        }
        
        update_option('cvsm_wilayas_list', $new_wilayas_structure);
        // For return value, we just want the names
        $wilayas_for_return = array_column($new_wilayas_structure, 'name');
    } else {
        // Already migrated (Array of Arrays)
        if (!empty($saved_wilayas)) {
             $wilayas_for_return = array_column($saved_wilayas, 'name');
        } else {
             $wilayas_for_return = array();
        }
    }
    
    $wilayas = $wilayas_for_return;

    
    // Get Tags (formerly profile types)
    $saved_tags = get_option('cvsm_tags_list');
    
    // Migration: If no tags exist, create from hardcoded defaults
    if ($saved_tags === false) {
        $default_types = array(
            'sous-traitance',
            'pour recrutement'
        );
        
        $tags = array();
        
        // Prevent infinite loop
        remove_action('save_post', 'cvsm_save_post_hook'); 
        
        foreach ($default_types as $t) {
            $slug = sanitize_title($t);
            
            // Create Page
            $page_id = wp_insert_post(array(
                'post_title'    => $t, // Title: "sous-traitance"
                'post_content'  => '[cvsm_accepted_list tag="' . $slug . '"]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_name'     => $slug
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                $tags[] = array(
                    'name' => $t,
                    'slug' => $slug,
                    'page_id' => $page_id
                );
            }
        }
        
        update_option('cvsm_tags_list', $tags);
        $profile_types = array_column($tags, 'name');
    } else {
        $profile_types = array_column($saved_tags, 'name');
    }
    
    return array('domaines' => $domaines, 'wilayas' => $wilayas, 'profile_types' => $profile_types);
}

/**
 * Manage Tags Page
 */
function cvsm_tags_page() {
    // Handle Add
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'add_tag' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_tags');
        
        $name = sanitize_text_field($_POST['new_tag_name']);
        $slug = sanitize_title($_POST['new_tag_slug']);
        
        if (empty($slug)) {
            $slug = sanitize_title($name);
        }
        
        if (!empty($name) && !empty($slug)) {
            $tags = get_option('cvsm_tags_list', array());
            
            // Check for duplicate slug
            $exists = false;
            foreach ($tags as $t) {
                if ($t['slug'] === $slug) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                // Create Page
                $page_id = wp_insert_post(array(
                    'post_title'    => $name,
                    'post_content'  => '[cvsm_accepted_list tag="' . $slug . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_name'     => $slug
                ));
                
                if ($page_id && !is_wp_error($page_id)) {
                    $tags[] = array(
                        'name' => $name,
                        'slug' => $slug,
                        'page_id' => $page_id
                    );
                    update_option('cvsm_tags_list', $tags);
                    echo '<div class="notice notice-success"><p>Type de profil ajouté et page créée: <a href="' . get_permalink($page_id) . '" target="_blank">' . esc_html($name) . '</a></p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Erreur lors de la création de la page.</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>Un type de profil avec ce slug existe déjà.</p></div>';
            }
        }
    }

    // Handle Delete
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'delete_tag' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_tags');
        $delete_index = intval($_POST['delete_index']);
        $tags = get_option('cvsm_tags_list', array());
        
        if (isset($tags[$delete_index])) {
            $deleted = $tags[$delete_index];
            
            // Delete Page
            if (!empty($deleted['page_id'])) {
                wp_delete_post($deleted['page_id'], true);
            }
            
            unset($tags[$delete_index]);
            $tags = array_values($tags); // Re-index
            update_option('cvsm_tags_list', $tags);
            echo '<div class="notice notice-success"><p>Type de profil et page associée supprimés: ' . esc_html($deleted['name']) . '</p></div>';
        }
    }

    $tags = get_option('cvsm_tags_list', array());
    // Fallback if empty (should be handled by get_lists migration, but just in case)
    if (empty($tags)) {
        cvsm_get_lists(); // Trigger migration
        $tags = get_option('cvsm_tags_list', array());
    }
    ?>
    <div class="wrap">
        <h1>Gestion des Types de Profil</h1>
        
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 20px;">
            <!-- Add New Form -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1; max-width: 400px;">
                <h2>Ajouter un Type de profil</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('cvsm_manage_tags'); ?>
                    <input type="hidden" name="cvsm_action" value="add_tag">
                    <p>
                        <label>Nom du Type de profil</label><br>
                        <input type="text" name="new_tag_name" class="regular-text" placeholder="Ex: Stagiaire" required style="width: 100%;">
                    </p>
                    <p>
                        <label>Slug (optionnel)</label><br>
                        <input type="text" name="new_tag_slug" class="regular-text" placeholder="Ex: stagiaire" style="width: 100%;">
                    </p>
                    <p class="description">Une page WordPress sera automatiquement créée pour ce type de profil.</p>
                    <p>
                        <input type="submit" class="button button-primary" value="Ajouter">
                    </p>
                </form>
            </div>

            <!-- List -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                <h2>Liste des Types de profil (<?php echo count($tags); ?>)</h2>
                <?php if (empty($tags)): ?>
                    <p>Aucun type de profil configuré.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Page</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tags as $index => $t): ?>
                            <tr>
                                <td><?php echo esc_html($t['name']); ?></td>
                                <td><code><?php echo esc_html($t['slug']); ?></code></td>
                                <td>
                                    <?php if (!empty($t['page_id']) && get_post($t['page_id'])): ?>
                                        <a href="<?php echo get_permalink($t['page_id']); ?>" target="_blank">Voir la page <span class="dashicons dashicons-external"></span></a>
                                    <?php else: ?>
                                        <span style="color: red;">Page introuvable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="" style="display:inline;">
                                        <?php wp_nonce_field('cvsm_manage_tags'); ?>
                                        <input type="hidden" name="cvsm_action" value="delete_tag">
                                        <input type="hidden" name="delete_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="button button-link-delete" onclick="return confirm('Attention: Cela supprimera définitivement la page associée ! Êtes-vous sûr ?');">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Manage Locations Page
 */
function cvsm_locations_page() {
    // Handle Add
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'add_wilaya' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_locations');
        
        $name = sanitize_text_field($_POST['new_wilaya']);
        $slug = sanitize_title($_POST['new_wilaya_slug']);
        
         if (empty($slug)) {
            $slug = sanitize_title($name);
        }
        
        if (!empty($name)) {
            $wilayas = get_option('cvsm_wilayas_list', array());
            
            // Check if legacy (strings) or new (arrays) - though get_lists should have migrated it by now
             $is_legacy = false;
             if (!empty($wilayas) && is_string($wilayas[0])) {
                 // Force Migration first just in case
                 cvsm_get_lists();
                 $wilayas = get_option('cvsm_wilayas_list', array());
             }
            
            // Check for duplicate slug
            $exists = false;
            foreach ($wilayas as $w) {
                if (is_array($w) && $w['slug'] === $slug) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                // Create Page
                $page_id = wp_insert_post(array(
                    'post_title'    => $name,
                    'post_content'  => '[cvsm_accepted_list wilaya="' . $slug . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_name'     => $slug
                ));
                
                 if ($page_id && !is_wp_error($page_id)) {
                    $wilayas[] = array(
                        'name' => $name,
                        'slug' => $slug,
                        'page_id' => $page_id
                    );
                    
                    // Sort alphabetically by name
                    usort($wilayas, function($a, $b) {
                         if (is_array($a) && is_array($b)) {
                            return strcmp($a['name'], $b['name']);
                         }
                         return 0;
                    });
                    
                    update_option('cvsm_wilayas_list', $wilayas);
                    echo '<div class="notice notice-success"><p>Wilaya ajoutée et page créée: <a href="' . get_permalink($page_id) . '" target="_blank">' . esc_html($name) . '</a></p></div>';
                } else {
                     echo '<div class="notice notice-error"><p>Erreur lors de la création de la page.</p></div>';
                }
            } else {
                 echo '<div class="notice notice-warning"><p>Une wilaya avec ce slug existe déjà.</p></div>';
            }
        }
    }

    // Handle Delete
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'delete_wilaya' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_locations');
        $delete_index = intval($_POST['delete_index']);
        $wilayas = get_option('cvsm_wilayas_list', array());
        
        if (isset($wilayas[$delete_index])) {
             $deleted = $wilayas[$delete_index];
             
             // Check stricture
             if (is_array($deleted) && isset($deleted['page_id'])) {
                 // Delete Page
                if (!empty($deleted['page_id'])) {
                    wp_delete_post($deleted['page_id'], true);
                }
                echo '<div class="notice notice-success"><p>Wilaya et page supprimées: ' . esc_html($deleted['name']) . '</p></div>';
             } else {
                 echo '<div class="notice notice-success"><p>Wilaya supprimée: ' . esc_html(is_string($deleted) ? $deleted : '') . '</p></div>';
             }

            unset($wilayas[$delete_index]);
            $wilayas = array_values($wilayas); // Re-index
            update_option('cvsm_wilayas_list', $wilayas);
        }
    }

    $wilayas = get_option('cvsm_wilayas_list', array());
    // Auto-migrate check
    if (!empty($wilayas) && is_string($wilayas[0])) {
         cvsm_get_lists();
         $wilayas = get_option('cvsm_wilayas_list', array());
    }
    
    ?>
    <div class="wrap">
        <h1>Gestion des Wilayas</h1>
        
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 20px;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1; max-width: 400px;">
                <h2>Ajouter une Wilaya</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('cvsm_manage_locations'); ?>
                    <input type="hidden" name="cvsm_action" value="add_wilaya">
                    <p>
                        <label>Nom de la Wilaya</label><br>
                        <input type="text" name="new_wilaya" class="regular-text" required style="width: 100%;">
                    </p>
                    <p>
                        <label>Slug (optionnel)</label><br>
                        <input type="text" name="new_wilaya_slug" class="regular-text" style="width: 100%;">
                    </p>
                     <p class="description">Une page WordPress sera automatiquement créée.</p>
                    <p>
                        <input type="submit" class="button button-primary" value="Ajouter">
                    </p>
                </form>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                <h2>Liste des Wilayas (<?php echo count($wilayas); ?>)</h2>
                <?php if (empty($wilayas)): ?>
                    <p>Aucune wilaya configurée.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Page</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wilayas as $index => $w): ?>
                            <tr>
                                <?php if (is_array($w)): ?>
                                    <td><?php echo esc_html($w['name']); ?></td>
                                    <td><code><?php echo esc_html($w['slug']); ?></code></td>
                                    <td>
                                        <?php if (!empty($w['page_id']) && get_post($w['page_id'])): ?>
                                            <a href="<?php echo get_permalink($w['page_id']); ?>" target="_blank">Voir la page <span class="dashicons dashicons-external"></span></a>
                                        <?php else: ?>
                                            <span style="color: red;">Page introuvable</span>
                                        <?php endif; ?>
                                    </td>
                                <?php else: ?>
                                    <!-- Fallback for legacy string data if migration failed -->
                                    <td><?php echo esc_html($w); ?></td>
                                    <td>-</td>
                                    <td>-</td>
                                <?php endif; ?>
                                
                                <td>
                                    <form method="post" action="" style="display:inline;">
                                        <?php wp_nonce_field('cvsm_manage_locations'); ?>
                                        <input type="hidden" name="cvsm_action" value="delete_wilaya">
                                        <input type="hidden" name="delete_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="button button-link-delete" onclick="return confirm('Êtes-vous sûr ? La page associée sera supprimée.');">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Manage Categories Page
 */
function cvsm_categories_page() {
    // Handle Add
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'add_category' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_categories');
        
        $name = sanitize_text_field($_POST['new_category_name']);
        $slug = sanitize_title($_POST['new_category_slug']);
        
        if (empty($slug)) {
            $slug = sanitize_title($name);
        }
        
        if (!empty($name) && !empty($slug)) {
            $categories = get_option('cvsm_categories_list', array());
            
            // Check for duplicate slug
            $exists = false;
            foreach ($categories as $cat) {
                if ($cat['slug'] === $slug) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                // Create Page
                $page_id = wp_insert_post(array(
                    'post_title'    => $name,
                    'post_content'  => '[cvsm_accepted_list category="' . $slug . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_name'     => $slug
                ));
                
                if ($page_id && !is_wp_error($page_id)) {
                    $categories[] = array(
                        'name' => $name,
                        'slug' => $slug,
                        'page_id' => $page_id
                    );
                    update_option('cvsm_categories_list', $categories);
                    echo '<div class="notice notice-success"><p>Métier ajouté et page créée: <a href="' . get_permalink($page_id) . '" target="_blank">' . esc_html($name) . '</a></p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Erreur lors de la création de la page.</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>Un métier avec ce slug existe déjà.</p></div>';
            }
        }
    }

    // Handle Delete
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'delete_category' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_categories');
        $delete_index = intval($_POST['delete_index']);
        $categories = get_option('cvsm_categories_list', array());
        
        if (isset($categories[$delete_index])) {
            $deleted = $categories[$delete_index];
            
            // Delete Page
            if (!empty($deleted['page_id'])) {
                wp_delete_post($deleted['page_id'], true);
            }
            
            unset($categories[$delete_index]);
            $categories = array_values($categories); // Re-index
            update_option('cvsm_categories_list', $categories);
            echo '<div class="notice notice-success"><p>Métier et page associée supprimés: ' . esc_html($deleted['name']) . '</p></div>';
        }
    }

    $categories = get_option('cvsm_categories_list', array());
    // Fallback if empty (should be handled by get_lists migration, but just in case)
    if (empty($categories)) {
        cvsm_get_lists(); // Trigger migration
        $categories = get_option('cvsm_categories_list', array());
    }
    ?>
    <div class="wrap">
        <h1>Gestion des Métiers</h1>
        
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 20px;">
            <!-- Add New Form -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1; max-width: 400px;">
                <h2>Ajouter un Métier</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('cvsm_manage_categories'); ?>
                    <input type="hidden" name="cvsm_action" value="add_category">
                    <p>
                        <label>Nom du Métier</label><br>
                        <input type="text" name="new_category_name" class="regular-text" placeholder="Ex: Informatique" required style="width: 100%;">
                    </p>
                    <p>
                        <label>Slug (optionnel, auto-généré si vide)</label><br>
                        <input type="text" name="new_category_slug" class="regular-text" placeholder="Ex: informatique" style="width: 100%;">
                    </p>
                    <p class="description">Une page WordPress sera automatiquement créée pour ce métier.</p>
                    <p>
                        <input type="submit" class="button button-primary" value="Ajouter">
                    </p>
                </form>
            </div>

            <!-- List -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                <h2>Liste des Métiers (<?php echo count($categories); ?>)</h2>
                <?php if (empty($categories)): ?>
                    <p>Aucun métier configuré.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Page</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td><?php echo esc_html($cat['name']); ?></td>
                                <td><code><?php echo esc_html($cat['slug']); ?></code></td>
                                <td>
                                    <?php if (!empty($cat['page_id']) && get_post($cat['page_id'])): ?>
                                        <a href="<?php echo get_permalink($cat['page_id']); ?>" target="_blank">Voir la page <span class="dashicons dashicons-external"></span></a>
                                    <?php else: ?>
                                        <span style="color: red;">Page introuvable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="" style="display:inline;">
                                        <?php wp_nonce_field('cvsm_manage_categories'); ?>
                                        <input type="hidden" name="cvsm_action" value="delete_category">
                                        <input type="hidden" name="delete_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="button button-link-delete" onclick="return confirm('Attention: Cela supprimera définitivement la page associée ! Êtes-vous sûr ?');">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Manage Service Categories Page (Métier Offre Service)
 */
function cvsm_service_categories_page() {
    // Handle Add
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'add_service_category' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_service_categories');
        
        $name = sanitize_text_field($_POST['new_service_category_name']);
        $slug = sanitize_title($_POST['new_service_category_slug']);
        
        if (empty($slug)) {
            $slug = sanitize_title($name);
        }
        
        if (!empty($name) && !empty($slug)) {
            $categories = get_option('cvsm_service_categories_list', array());
            
            // Check for duplicate slug
            $exists = false;
            foreach ($categories as $cat) {
                if ($cat['slug'] === $slug) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                // Create Page with offre_service_accepted_list shortcode
                $page_id = wp_insert_post(array(
                    'post_title'    => $name,
                    'post_content'  => '[offre_service_accepted_list category="' . $slug . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_name'     => 'service-' . $slug
                ));
                
                if ($page_id && !is_wp_error($page_id)) {
                    $categories[] = array(
                        'name' => $name,
                        'slug' => $slug,
                        'page_id' => $page_id
                    );
                    update_option('cvsm_service_categories_list', $categories);
                    echo '<div class="notice notice-success"><p>Service ajouté et page créée: <a href="' . get_permalink($page_id) . '" target="_blank">' . esc_html($name) . '</a></p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Erreur lors de la création de la page.</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>Un service avec ce slug existe déjà.</p></div>';
            }
        }
    }

    // Handle Delete
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'delete_service_category' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_service_categories');
        $delete_index = intval($_POST['delete_index']);
        $categories = get_option('cvsm_service_categories_list', array());
        
        if (isset($categories[$delete_index])) {
            $deleted = $categories[$delete_index];
            
            // Delete Page
            if (!empty($deleted['page_id'])) {
                wp_delete_post($deleted['page_id'], true);
            }
            
            unset($categories[$delete_index]);
            $categories = array_values($categories); // Re-index
            update_option('cvsm_service_categories_list', $categories);
            echo '<div class="notice notice-success"><p>Service et page associée supprimés: ' . esc_html($deleted['name']) . '</p></div>';
        }
    }

    // Handle Import from existing domaines
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'import_service_category' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_service_categories');
        
        $name = sanitize_text_field($_POST['import_service_name']);
        $slug = sanitize_title($name);
        
        if (!empty($name) && !empty($slug)) {
            $categories = get_option('cvsm_service_categories_list', array());
            
            $exists = false;
            foreach ($categories as $cat) {
                if ($cat['slug'] === $slug) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $page_id = wp_insert_post(array(
                    'post_title'    => $name,
                    'post_content'  => '[offre_service_accepted_list category="' . $slug . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_name'     => 'service-' . $slug
                ));
                
                if ($page_id && !is_wp_error($page_id)) {
                    $categories[] = array(
                        'name' => $name,
                        'slug' => $slug,
                        'page_id' => $page_id
                    );
                    update_option('cvsm_service_categories_list', $categories);
                    echo '<div class="notice notice-success"><p>Service "' . esc_html($name) . '" importé et page créée.</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>Ce service est déjà géré.</p></div>';
            }
        }
    }

    // Handle Import All
    if (isset($_POST['cvsm_action']) && $_POST['cvsm_action'] === 'import_all_services' && current_user_can('manage_options')) {
        check_admin_referer('cvsm_manage_service_categories');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'offre_service_submissions';
        $domaines = $wpdb->get_col("SELECT DISTINCT domaine FROM $table_name WHERE domaine != '' ORDER BY domaine ASC");
        $categories = get_option('cvsm_service_categories_list', array());
        $managed_slugs = array_map(function($c) { return $c['slug']; }, $categories);
        $imported = 0;
        
        foreach ($domaines as $domaine) {
            $slug = sanitize_title($domaine);
            if (!in_array($slug, $managed_slugs)) {
                $page_id = wp_insert_post(array(
                    'post_title'    => $domaine,
                    'post_content'  => '[offre_service_accepted_list category="' . $slug . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_name'     => 'service-' . $slug
                ));
                if ($page_id && !is_wp_error($page_id)) {
                    $categories[] = array('name' => $domaine, 'slug' => $slug, 'page_id' => $page_id);
                    $managed_slugs[] = $slug;
                    $imported++;
                }
            }
        }
        
        if ($imported > 0) {
            update_option('cvsm_service_categories_list', $categories);
            echo '<div class="notice notice-success"><p>' . $imported . ' service(s) importé(s) avec succès.</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>Tous les services sont déjà gérés.</p></div>';
        }
    }

    $categories = get_option('cvsm_service_categories_list', array());
    
    // Get existing domaines from database
    global $wpdb;
    $table_name = $wpdb->prefix . 'offre_service_submissions';
    $existing_domaines = array();
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if ($table_exists) {
        $existing_domaines = $wpdb->get_col("SELECT DISTINCT domaine FROM $table_name WHERE domaine != '' ORDER BY domaine ASC");
    }
    
    // Find unmanaged domaines (exist in DB but not yet as managed categories)
    $managed_slugs = array_map(function($c) { return $c['slug']; }, $categories);
    $unmanaged_domaines = array();
    foreach ($existing_domaines as $domaine) {
        $slug = sanitize_title($domaine);
        if (!in_array($slug, $managed_slugs)) {
            $unmanaged_domaines[] = $domaine;
        }
    }
    ?>
    <div class="wrap">
        <h1>Gestion des Services (Métier Offre Service)</h1>
        
        <?php if (!empty($unmanaged_domaines)): ?>
        <!-- Existing Services from Form/DB -->
        <div style="background: #fff3cd; padding: 15px 20px; border: 1px solid #ffc107; border-radius: 4px; margin-top: 20px;">
            <h2 style="margin-top: 0;">⚠️ Services existants non gérés (<?php echo count($unmanaged_domaines); ?>)</h2>
            <p>Ces services existent dans les soumissions "Offre Service" mais n'ont pas encore de page associée :</p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                <?php foreach ($unmanaged_domaines as $domaine): ?>
                <form method="post" action="" style="display:inline;">
                    <?php wp_nonce_field('cvsm_manage_service_categories'); ?>
                    <input type="hidden" name="cvsm_action" value="import_service_category">
                    <input type="hidden" name="import_service_name" value="<?php echo esc_attr($domaine); ?>">
                    <button type="submit" class="button" style="background: #fff; border-color: #ffc107;">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> <?php echo esc_html($domaine); ?>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
            <form method="post" action="" style="display:inline;">
                <?php wp_nonce_field('cvsm_manage_service_categories'); ?>
                <input type="hidden" name="cvsm_action" value="import_all_services">
                <button type="submit" class="button button-primary" onclick="return confirm('Importer tous les services et créer les pages associées ?');">
                    <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Importer tous les services (<?php echo count($unmanaged_domaines); ?>)
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 20px;">
            <!-- Add New Form -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1; max-width: 400px;">
                <h2>Ajouter un Service manuellement</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('cvsm_manage_service_categories'); ?>
                    <input type="hidden" name="cvsm_action" value="add_service_category">
                    <p>
                        <label>Nom du Service</label><br>
                        <input type="text" name="new_service_category_name" class="regular-text" placeholder="Ex: Plomberie" required style="width: 100%;">
                    </p>
                    <p>
                        <label>Slug (optionnel, auto-généré si vide)</label><br>
                        <input type="text" name="new_service_category_slug" class="regular-text" placeholder="Ex: plomberie" style="width: 100%;">
                    </p>
                    <p class="description">Une page WordPress sera automatiquement créée pour ce service.</p>
                    <p>
                        <input type="submit" class="button button-primary" value="Ajouter">
                    </p>
                </form>
            </div>

            <!-- Managed List -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                <h2>Services gérés (<?php echo count($categories); ?>)</h2>
                <?php if (empty($categories)): ?>
                    <p>Aucun service configuré. Importez les services existants ci-dessus ou ajoutez-en manuellement.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Page</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td><?php echo esc_html($cat['name']); ?></td>
                                <td><code><?php echo esc_html($cat['slug']); ?></code></td>
                                <td>
                                    <?php if (!empty($cat['page_id']) && get_post($cat['page_id'])): ?>
                                        <a href="<?php echo get_permalink($cat['page_id']); ?>" target="_blank">Voir la page <span class="dashicons dashicons-external"></span></a>
                                    <?php else: ?>
                                        <span style="color: red;">Page introuvable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="" style="display:inline;">
                                        <?php wp_nonce_field('cvsm_manage_service_categories'); ?>
                                        <input type="hidden" name="cvsm_action" value="delete_service_category">
                                        <input type="hidden" name="delete_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="button button-link-delete" onclick="return confirm('Attention: Cela supprimera définitivement la page associée ! Êtes-vous sûr ?');">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Frontend Shortcode: [cvsm_accepted_list]
 * Displays accepted candidates in a grid
 */
function cvsm_shortcode_accepted_list($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cv_submissions';
    
    // 1. Get Distinct Values for Filters
    $lists = cvsm_get_lists();
    $domaines = $lists['domaines'];
    $wilayas = $lists['wilayas'];
    $types = $lists['profile_types']; // Now dynamic from Tags
    
    // Check for category filter
    $category_slug = isset($atts['category']) ? sanitize_title($atts['category']) : '';
    $tag_slug = isset($atts['tag']) ? sanitize_title($atts['tag']) : '';
    $wilaya_slug = isset($atts['wilaya']) ? sanitize_title($atts['wilaya']) : '';
    
    $where_clause = "WHERE status = 'accepted'";
    
    if (!empty($category_slug)) {
        // Resolve slug to Name
        $categories = get_option('cvsm_categories_list', array());
        $category_name = '';
        foreach ($categories as $cat) {
            if ($cat['slug'] === $category_slug) {
                $category_name = $cat['name'];
                break;
            }
        }
        
        if (!empty($category_name)) {
            global $wpdb;
            $where_clause .= $wpdb->prepare(" AND domaine = %s", $category_name);
        }
    }
    
    // Check for Tag filter (profile_type)
    if (!empty($tag_slug)) {
        $tags = get_option('cvsm_tags_list', array());
        $tag_name = '';
        foreach ($tags as $t) {
            if ($t['slug'] === $tag_slug) {
                $tag_name = $t['name'];
                break;
            }
        }
        
        if (!empty($tag_name)) {
            global $wpdb;
            $where_clause .= $wpdb->prepare(" AND profile_type = %s", $tag_name);
        }
    }

    // Check for Wilaya filter
    if (!empty($wilaya_slug)) {
        $wilayas = get_option('cvsm_wilayas_list', array());
        $wilaya_name = '';
        // Need to check structure (could be string or array during migration, but shortcode should preferably run after migration)
        // If it's string array, we can't search by slug effectively unless slug==sanitize_title(name)
        
        foreach ($wilayas as $w) {
            if (is_array($w)) {
                if ($w['slug'] === $wilaya_slug) {
                    $wilaya_name = $w['name'];
                    break;
                }
            } else {
                 // Fallback: check if slug matches sanitized name
                 if (sanitize_title($w) === $wilaya_slug) {
                     $wilaya_name = $w;
                     break;
                 }
            }
        }
        
        if (!empty($wilaya_name)) {
            global $wpdb;
            $where_clause .= $wpdb->prepare(" AND wilaya = %s", $wilaya_name);
        }
    }
    
    // 2. Query accepted submissions
    $results = $wpdb->get_results("SELECT * FROM $table_name $where_clause ORDER BY processed_at DESC");
    
    ob_start();
    ?>
    <style>
        /* Container for Filters */
        .cvsm-filters-container {
            display: flex;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .cvsm-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 200px;
        }

        .cvsm-filter-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .cvsm-select {
            appearance: none;
            background-color: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 36px 10px 14px;
            font-size: 0.95rem;
            color: #0F172A;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s ease;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }

        .cvsm-select:hover {
            border-color: #94a3b8;
        }

        .cvsm-select:focus {
            outline: none;
            border-color: #0F172A;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }
        
        /* Grid Styles */
        .cvsm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin: 0; /* Margin handled by parent container spacing */
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; /* Improved Font Stack */
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
            /* Animation for filtering */
            animation: fadeIn 0.4s ease-out;
        }
        
        .cvsm-card.hidden {
            display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
            grid-column: 1 / -1;
        }
        
        /* Mobile adjustment */
        @media (max-width: 768px) {
            .cvsm-filters-container {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <div class="cvsm-wrapper">
        <!-- Filter Bar -->
        <div class="cvsm-filters-container">
            <div class="cvsm-filter-group">
                <label class="cvsm-filter-label">Catégorie</label>
                <select id="cvsm-filter-domaine" class="cvsm-select">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($domaines as $d): ?>
                        <option value="<?php echo esc_attr($d); ?>"><?php echo esc_html($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="cvsm-filter-group">
                <label class="cvsm-filter-label">Wilaya</label>
                <select id="cvsm-filter-wilaya" class="cvsm-select">
                    <option value="">Toutes les wilayas</option>
                    <?php foreach ($wilayas as $w): ?>
                        <option value="<?php echo esc_attr($w); ?>"><?php echo esc_html($w); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cvsm-filter-group">
                <label class="cvsm-filter-label">Type de Profil</label>
                <select id="cvsm-filter-type" class="cvsm-select">
                    <option value="">Tous les types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="cvsm-accepted-container">
            <?php if ($results): ?>
                <div class="cvsm-grid" id="cvsm-grid">
                    <?php foreach ($results as $profile): ?>
                        <!-- Added data-attributes for filtering -->
                        <div class="cvsm-card" 
                             data-domaine="<?php echo esc_attr($profile->domaine); ?>" 
                             data-wilaya="<?php echo esc_attr($profile->wilaya); ?>" 
                             data-type="<?php echo esc_attr($profile->profile_type); ?>">
                             
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
                    <div id="cvsm-no-results" class="cvsm-empty" style="display: none;">
                        <p>Aucun résultat ne correspond à vos critères.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="cvsm-empty">
                    <p>Aucun profil accepté pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements
        const filterDomaine = document.getElementById('cvsm-filter-domaine');
        const filterWilaya = document.getElementById('cvsm-filter-wilaya');
        const filterType = document.getElementById('cvsm-filter-type');
        const cards = document.querySelectorAll('.cvsm-card');
        const noResultsMsg = document.getElementById('cvsm-no-results');

        if (!filterDomaine || !cards.length) return;

        function filterCards() {
            const valDomaine = filterDomaine.value.toLowerCase();
            const valWilaya = filterWilaya.value.toLowerCase();
            const valType = filterType.value.toLowerCase();
            let visibleCount = 0;

            cards.forEach(card => {
                const cardDomaine = (card.getAttribute('data-domaine') || '').toLowerCase();
                const cardWilaya = (card.getAttribute('data-wilaya') || '').toLowerCase();
                const cardType = (card.getAttribute('data-type') || '').toLowerCase();

                // Logic: Show if matches ALL selected fitlers
                let matchDomaine = !valDomaine || cardDomaine === valDomaine;
                let matchWilaya = !valWilaya || cardWilaya === valWilaya;
                // Basic contains check for multi-select types stored as comma strings if necessary, 
                // but usually exact match is expected relative to the distinct dropdown.
                // Using .includes for Type to be safe if a card has "Type A, Type B"
                let matchType = !valType || cardType.includes(valType);

                if (matchDomaine && matchWilaya && matchType) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Show/Hide "No Results" message
            if (visibleCount === 0) {
                if (noResultsMsg) noResultsMsg.style.display = 'block';
            } else {
                if (noResultsMsg) noResultsMsg.style.display = 'none';
            }
        }

        // Event Listeners
        filterDomaine.addEventListener('change', filterCards);
        filterWilaya.addEventListener('change', filterCards);
        filterType.addEventListener('change', filterCards);
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('cvsm_accepted_list', 'cvsm_shortcode_accepted_list');

/**
 * Frontend Shortcode: [offre_service_accepted_list]
 * Displays accepted Offre Service submissions in a grid (same pattern as cvsm_accepted_list)
 */
function offre_service_shortcode_accepted_list($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'offre_service_submissions';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        return '<p>La table des offres de service n\'existe pas encore.</p>';
    }
    
    // 1. Get Distinct Values for Filters 
    // Wilayas: Get all 58 wilayas using the shared helper
    $lists = cvsm_get_lists();
    $wilayas = $lists['wilayas'];
    
    // Categories: Get all managed services from option
    $os_categories = get_option('cvsm_service_categories_list', array());
    if (!empty($os_categories) && is_array($os_categories)) {
        // Handle migration case where it might be array of strings vs array of arrays
        $first = reset($os_categories);
        if (is_array($first) || is_object($first)) {
             $domaines = array_column($os_categories, 'name');
        } else {
             $domaines = $os_categories;
        }
    } else {
        $domaines = array();
    }
    sort($domaines);
    
    // Specialities: Still fetch from DB as there is no managed list
    $specialites = $wpdb->get_col("SELECT DISTINCT specialite FROM $table_name WHERE specialite != '' ORDER BY specialite ASC");
    
    // Parse shortcode attributes
    $category_slug = isset($atts['category']) ? sanitize_title($atts['category']) : '';
    $wilaya_slug = isset($atts['wilaya']) ? sanitize_title($atts['wilaya']) : '';
    
    // Build WHERE clause
    $where_clause = "WHERE status = 'accepted'";
    
    // Resolve category slug to domaine name
    if (!empty($category_slug)) {
        // Re-fetch option to be sure (already fetched above but let's assume valid scope)
        $category_name = '';
        foreach ($os_categories as $cat) {
            if (is_array($cat) && isset($cat['slug']) && $cat['slug'] === $category_slug) {
                $category_name = $cat['name'];
                break;
            }
        }
        // Fallback: try matching slug against sanitized category names from the list
        if (empty($category_name)) {
            foreach ($domaines as $d) {
                if (sanitize_title($d) === $category_slug) {
                    $category_name = $d;
                    break;
                }
            }
        }
        if (!empty($category_name)) {
            $where_clause .= $wpdb->prepare(" AND domaine = %s", $category_name);
        }
    }
    
    // Resolve wilaya slug
    if (!empty($wilaya_slug)) {
        $wilaya_name = '';
        foreach ($wilayas as $w) {
            if (sanitize_title($w) === $wilaya_slug) {
                $wilaya_name = $w;
                break;
            }
        }
        if (!empty($wilaya_name)) {
            $where_clause .= $wpdb->prepare(" AND wilaya = %s", $wilaya_name);
        }
    }
    
    // 2. Query accepted submissions
    $results = $wpdb->get_results("SELECT * FROM $table_name $where_clause ORDER BY submitted_at DESC");
    
    ob_start();
    ?>
    <style>
        /* Container for Filters - Offre Service */
        .os-filters-container {
            display: flex;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .os-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 200px;
        }

        .os-filter-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .os-select {
            appearance: none;
            background-color: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 36px 10px 14px;
            font-size: 0.95rem;
            color: #0F172A;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s ease;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }

        .os-select:hover {
            border-color: #94a3b8;
        }

        .os-select:focus {
            outline: none;
            border-color: #0F172A;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }
        
        /* Grid Styles - Offre Service */
        .os-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
        }
        .os-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            animation: osFadeIn 0.4s ease-out;
        }
        
        .os-card.hidden {
            display: none;
        }

        @keyframes osFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .os-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
        .os-card-header {
            background-color: #0F172A;
            padding: 24px;
            color: white;
            position: relative;
        }
        .os-card-header h3 {
            margin: 0;
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .os-badges {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .os-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .os-badge.domaine {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .os-badge.specialite {
            background: #3b82f6;
            color: #fff;
        }
        .os-card-body {
            padding: 24px;
            flex-grow: 1;
            color: #334155;
        }
        .os-info-group {
            margin-bottom: 20px;
        }
        .os-info-group:last-child {
            margin-bottom: 0;
        }
        .os-info-row {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .os-info-row:last-child {
            margin-bottom: 0;
        }
        .os-label {
            min-width: 110px;
            font-weight: 600;
            color: #64748b;
            flex-shrink: 0;
        }
        .os-value {
            color: #1e293b;
            font-weight: 500;
            word-break: break-word;
        }
        .os-value a {
            color: #2563eb;
            text-decoration: none;
        }
        .os-value a:hover {
            text-decoration: underline;
        }
        .os-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 16px 0;
        }
        .os-card-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .os-date {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .os-btn-download {
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
        .os-btn-download:hover {
            background: #1e293b;
        }
        .os-empty {
            text-align: center;
            padding: 40px;
            background: #f8fafc;
            border-radius: 8px;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            grid-column: 1 / -1;
        }
        
        /* Mobile adjustment */
        @media (max-width: 768px) {
            .os-filters-container {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <div class="os-wrapper">
        <!-- Filter Bar -->
        <div class="os-filters-container">
            <div class="os-filter-group">
                <label class="os-filter-label">Catégorie</label>
                <select id="os-filter-domaine" class="os-select">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($domaines as $d): ?>
                        <option value="<?php echo esc_attr($d); ?>"><?php echo esc_html($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="os-filter-group">
                <label class="os-filter-label">Wilaya</label>
                <select id="os-filter-wilaya" class="os-select">
                    <option value="">Toutes les wilayas</option>
                    <?php foreach ($wilayas as $w): ?>
                        <option value="<?php echo esc_attr($w); ?>"><?php echo esc_html($w); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="os-filter-group">
                <label class="os-filter-label">Spécialité</label>
                <select id="os-filter-specialite" class="os-select">
                    <option value="">Toutes les spécialités</option>
                    <?php foreach ($specialites as $s): ?>
                        <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="os-accepted-container">
            <?php if ($results): ?>
                <div class="os-grid" id="os-grid">
                    <?php foreach ($results as $profile): ?>
                        <div class="os-card" 
                             data-domaine="<?php echo esc_attr($profile->domaine); ?>" 
                             data-wilaya="<?php echo esc_attr($profile->wilaya); ?>"
                             data-specialite="<?php echo esc_attr($profile->specialite); ?>">
                             
                            <div class="os-card-header">
                                <h3><?php echo esc_html($profile->full_name); ?></h3>
                                <div class="os-badges">
                                    <span class="os-badge domaine"><?php echo esc_html($profile->domaine); ?></span>
                                    <?php if (!empty($profile->specialite)): ?>
                                        <span class="os-badge specialite"><?php echo esc_html($profile->specialite); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="os-card-body">
                                <div class="os-info-group">
                                    <?php if (!empty($profile->specialite)): ?>
                                    <div class="os-info-row">
                                        <span class="os-label">Spécialité:</span>
                                        <span class="os-value"><?php echo esc_html($profile->specialite); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="os-info-row">
                                        <span class="os-label">Expérience:</span>
                                        <span class="os-value"><?php echo esc_html($profile->experience); ?> ans</span>
                                    </div>
                                    <?php if (!empty($profile->wilaya)): ?>
                                    <div class="os-info-row">
                                        <span class="os-label">Wilaya:</span>
                                        <span class="os-value"><?php echo esc_html($profile->wilaya); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile->sexe)): ?>
                                    <div class="os-info-row">
                                        <span class="os-label">Sexe:</span>
                                        <span class="os-value"><?php echo esc_html($profile->sexe); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile->niveau_education)): ?>
                                    <div class="os-info-row">
                                        <span class="os-label">Éducation:</span>
                                        <span class="os-value"><?php echo esc_html($profile->niveau_education); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($profile->description_offre)): ?>
                                <div class="os-divider"></div>
                                <div class="os-info-group">
                                    <div class="os-info-row">
                                        <span class="os-label">Description:</span>
                                        <span class="os-value"><?php echo esc_html($profile->description_offre); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="os-divider"></div>
                                
                                <div class="os-info-group">
                                    <?php if (!empty($profile->email)): ?>
                                    <div class="os-info-row">
                                        <span class="os-label">Email:</span>
                                        <span class="os-value"><a href="mailto:<?php echo esc_attr($profile->email); ?>"><?php echo esc_html($profile->email); ?></a></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($profile->phone)): ?>
                                    <div class="os-info-row">
                                        <span class="os-label">Tél:</span>
                                        <span class="os-value"><a href="tel:<?php echo esc_attr($profile->phone); ?>"><?php echo esc_html($profile->phone); ?></a></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="os-card-footer">
                                <span class="os-date">Soumis le <?php echo date_i18n('d/m/Y', strtotime($profile->submitted_at)); ?></span>
                                <?php if (!empty($profile->offre_file)): ?>
                                    <a href="<?php echo esc_url($profile->offre_file); ?>" target="_blank" class="os-btn-download">
                                        Voir Offre →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div id="os-no-results" class="os-empty" style="display: none;">
                        <p>Aucun résultat ne correspond à vos critères.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="os-empty">
                    <p>Aucune offre de service acceptée pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements
        const osFilterDomaine = document.getElementById('os-filter-domaine');
        const osFilterWilaya = document.getElementById('os-filter-wilaya');
        const osFilterSpecialite = document.getElementById('os-filter-specialite');
        const osCards = document.querySelectorAll('.os-card');
        const osNoResultsMsg = document.getElementById('os-no-results');

        if (!osFilterDomaine || !osCards.length) return;

        function osFilterCards() {
            const valDomaine = osFilterDomaine.value.toLowerCase();
            const valWilaya = osFilterWilaya.value.toLowerCase();
            const valSpecialite = osFilterSpecialite ? osFilterSpecialite.value.toLowerCase() : '';
            let visibleCount = 0;

            osCards.forEach(card => {
                const cardDomaine = (card.getAttribute('data-domaine') || '').toLowerCase();
                const cardWilaya = (card.getAttribute('data-wilaya') || '').toLowerCase();
                const cardSpecialite = (card.getAttribute('data-specialite') || '').toLowerCase();

                let matchDomaine = !valDomaine || cardDomaine === valDomaine;
                let matchWilaya = !valWilaya || cardWilaya === valWilaya;
                let matchSpecialite = !valSpecialite || cardSpecialite === valSpecialite;

                if (matchDomaine && matchWilaya && matchSpecialite) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Show/Hide "No Results" message
            if (visibleCount === 0) {
                if (osNoResultsMsg) osNoResultsMsg.style.display = 'block';
            } else {
                if (osNoResultsMsg) osNoResultsMsg.style.display = 'none';
            }
        }

        // Event Listeners
        osFilterDomaine.addEventListener('change', osFilterCards);
        osFilterWilaya.addEventListener('change', osFilterCards);
        if (osFilterSpecialite) osFilterSpecialite.addEventListener('change', osFilterCards);
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('offre_service_accepted_list', 'offre_service_shortcode_accepted_list');
