<?php
/**
 * Database Migration Tool
 * One-time migration from SQLite to MySQL
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add migration menu item
 */
function fwd_migration_menu() {
    // Only show if not already migrated
    if (!get_option('fwd_migration_completed')) {
        add_management_page(
            'Film Watch DB Migration',
            'Film Watch Migration',
            'manage_options',
            'fwd-migration',
            'fwd_migration_page'
        );
    }
}
add_action('admin_menu', 'fwd_migration_menu');

/**
 * Migration page
 */
function fwd_migration_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Handle migration form submission
    if (isset($_POST['fwd_migrate']) && check_admin_referer('fwd_migration_nonce')) {
        $db_path = sanitize_text_field($_POST['db_path']);
        $result = fwd_perform_migration($db_path);

        if ($result['success']) {
            update_option('fwd_migration_completed', true);
            echo '<div class="notice notice-success"><p><strong>Migration Successful!</strong></p>' . $result['message'] . '</div>';
        } else {
            echo '<div class="notice notice-error"><p><strong>Migration Failed:</strong> ' . esc_html($result['error']) . '</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1>Film Watch Database Migration</h1>

        <div class="notice notice-info">
            <p><strong>One-Time Migration Tool</strong></p>
            <p>This tool will import your existing SQLite database into the new MySQL database. After successful migration, this page will be hidden.</p>
        </div>

        <h2>Step 1: Check Requirements</h2>
        <table class="form-table">
            <tr>
                <th scope="row">PDO SQLite Extension</th>
                <td>
                    <?php if (extension_loaded('pdo_sqlite')): ?>
                        <span style="color: #46b450;">✓ Installed</span>
                    <?php else: ?>
                        <span style="color: #dc3232;">✗ Not Installed</span>
                        <p>You need to install PDO SQLite to read your old database:</p>
                        <code>sudo apt-get install php-sqlite3 && sudo systemctl restart apache2</code>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if (extension_loaded('pdo_sqlite')): ?>

        <h2>Step 2: Locate Your SQLite Database</h2>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin: 20px 0;">
            <p>Your SQLite database files are located at:</p>
            <ul>
                <?php
                $possible_paths = array(
                    '/home/user/watch-utils/film_watches.db',
                    '/home/user/watch-utils/film_watches_backup.db',
                    '/home/user/watch-utils/new-film-database.db'
                );

                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $size = size_format(filesize($path), 2);
                        echo '<li><code>' . esc_html($path) . '</code> (' . esc_html($size) . ') <span style="color: #46b450;">✓ Found</span></li>';
                    }
                }
                ?>
            </ul>
        </div>

        <h2>Step 3: Run Migration</h2>
        <form method="post" action="">
            <?php wp_nonce_field('fwd_migration_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="db_path">SQLite Database Path</label>
                    </th>
                    <td>
                        <input type="text"
                               id="db_path"
                               name="db_path"
                               value="/home/user/watch-utils/film_watches.db"
                               class="regular-text"
                               required>
                        <p class="description">Full path to your SQLite database file</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="fwd_migrate" class="button button-primary button-large">
                    🚀 Start Migration
                </button>
            </p>
        </form>

        <?php else: ?>

        <div class="notice notice-warning">
            <p>Please install the PDO SQLite extension first before proceeding with migration.</p>
        </div>

        <?php endif; ?>
    </div>
    <?php
}

/**
 * Perform the actual migration
 */
function fwd_perform_migration($sqlite_path) {
    // Check if file exists
    if (!file_exists($sqlite_path)) {
        return array(
            'success' => false,
            'error' => 'SQLite database file not found at: ' . $sqlite_path
        );
    }

    // Check if PDO SQLite is available
    if (!extension_loaded('pdo_sqlite')) {
        return array(
            'success' => false,
            'error' => 'PDO SQLite extension is not installed'
        );
    }

    try {
        // Connect to SQLite database
        $sqlite = new PDO('sqlite:' . $sqlite_path);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $fwd_db = fwd_db();
        $stats = array(
            'films' => 0,
            'actors' => 0,
            'brands' => 0,
            'watches' => 0,
            'characters' => 0,
            'relationships' => 0,
            'duplicates' => 0
        );

        // Import films
        $stmt = $sqlite->query("SELECT * FROM films");
        $films = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($films as $film) {
            global $wpdb;
            $table = $wpdb->prefix . 'fwd_films';
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (film_id, title, year) VALUES (%d, %s, %d)",
                $film['film_id'], $film['title'], $film['year']
            ));
            if ($wpdb->insert_id || $wpdb->rows_affected) {
                $stats['films']++;
            }
        }

        // Import brands
        $stmt = $sqlite->query("SELECT * FROM brands");
        $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($brands as $brand) {
            $table = $wpdb->prefix . 'fwd_brands';
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (brand_id, brand_name) VALUES (%d, %s)",
                $brand['brand_id'], $brand['brand_name']
            ));
            if ($wpdb->insert_id || $wpdb->rows_affected) {
                $stats['brands']++;
            }
        }

        // Import actors
        $stmt = $sqlite->query("SELECT * FROM actors");
        $actors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($actors as $actor) {
            $table = $wpdb->prefix . 'fwd_actors';
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (actor_id, actor_name) VALUES (%d, %s)",
                $actor['actor_id'], $actor['actor_name']
            ));
            if ($wpdb->insert_id || $wpdb->rows_affected) {
                $stats['actors']++;
            }
        }

        // Import watches
        $stmt = $sqlite->query("SELECT * FROM watches");
        $watches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($watches as $watch) {
            $table = $wpdb->prefix . 'fwd_watches';
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (watch_id, brand_id, model_reference, verification_level) VALUES (%d, %d, %s, %s)",
                $watch['watch_id'], $watch['brand_id'], $watch['model_reference'], $watch['verification_level']
            ));
            if ($wpdb->insert_id || $wpdb->rows_affected) {
                $stats['watches']++;
            }
        }

        // Import characters
        $stmt = $sqlite->query("SELECT * FROM characters");
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($characters as $character) {
            $table = $wpdb->prefix . 'fwd_characters';
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (character_id, character_name) VALUES (%d, %s)",
                $character['character_id'], $character['character_name']
            ));
            if ($wpdb->insert_id || $wpdb->rows_affected) {
                $stats['characters']++;
            }
        }

        // Import film_actor_watch relationships
        $stmt = $sqlite->query("SELECT * FROM film_actor_watch");
        $relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($relationships as $rel) {
            $table = $wpdb->prefix . 'fwd_film_actor_watch';

            // Check for duplicates
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT faw_id FROM {$table} WHERE film_id = %d AND actor_id = %d AND character_id = %d AND watch_id = %d",
                $rel['film_id'], $rel['actor_id'], $rel['character_id'], $rel['watch_id']
            ));

            if (!$existing) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$table} (faw_id, film_id, actor_id, character_id, watch_id, narrative_role) VALUES (%d, %d, %d, %d, %d, %s)",
                    $rel['faw_id'], $rel['film_id'], $rel['actor_id'], $rel['character_id'], $rel['watch_id'], $rel['narrative_role']
                ));
                if ($wpdb->insert_id || $wpdb->rows_affected) {
                    $stats['relationships']++;
                }
            } else {
                $stats['duplicates']++;
            }
        }

        // Build success message
        $message = '<ul>';
        $message .= '<li>✓ Imported ' . $stats['films'] . ' films</li>';
        $message .= '<li>✓ Imported ' . $stats['actors'] . ' actors</li>';
        $message .= '<li>✓ Imported ' . $stats['brands'] . ' watch brands</li>';
        $message .= '<li>✓ Imported ' . $stats['watches'] . ' watches</li>';
        $message .= '<li>✓ Imported ' . $stats['characters'] . ' characters</li>';
        $message .= '<li>✓ Imported ' . $stats['relationships'] . ' film-watch relationships</li>';
        if ($stats['duplicates'] > 0) {
            $message .= '<li>⚠ Skipped ' . $stats['duplicates'] . ' duplicate entries</li>';
        }
        $message .= '</ul>';
        $message .= '<p><strong>Migration completed successfully!</strong> You can now use the plugin with all your existing data in MySQL.</p>';

        return array(
            'success' => true,
            'message' => $message,
            'stats' => $stats
        );

    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}

/**
 * Add admin notice if migration not completed
 */
function fwd_migration_notice() {
    if (!get_option('fwd_migration_completed')) {
        $screen = get_current_screen();
        if ($screen && $screen->id !== 'tools_page_fwd-migration') {
            ?>
            <div class="notice notice-warning">
                <p><strong>Film Watch Database:</strong> You have an empty database.
                <a href="<?php echo admin_url('tools.php?page=fwd-migration'); ?>">Click here to migrate your existing data from SQLite</a> or start adding entries using the <code>[film_watch_add]</code> shortcode.</p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'fwd_migration_notice');
