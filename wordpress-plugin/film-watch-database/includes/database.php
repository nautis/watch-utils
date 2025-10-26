<?php
/**
 * Database Handler - WordPress MySQL Implementation
 * Uses WordPress's native database connection (MySQL/MariaDB)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Database {

    private $wpdb;
    private $table_prefix;

    // Table names
    private $films_table;
    private $brands_table;
    private $watches_table;
    private $actors_table;
    private $characters_table;
    private $film_actor_watch_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'fwd_';

        // Define table names
        $this->films_table = $this->table_prefix . 'films';
        $this->brands_table = $this->table_prefix . 'brands';
        $this->watches_table = $this->table_prefix . 'watches';
        $this->actors_table = $this->table_prefix . 'actors';
        $this->characters_table = $this->table_prefix . 'characters';
        $this->film_actor_watch_table = $this->table_prefix . 'film_actor_watch';

        $this->create_tables();
    }

    /**
     * Create database tables if they don't exist
     */
    private function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = array();

        // Films table
        $sql[] = "CREATE TABLE {$this->films_table} (
            film_id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            year int(11) NOT NULL,
            PRIMARY KEY (film_id),
            UNIQUE KEY title_year (title(191), year)
        ) $charset_collate;";

        // Brands table
        $sql[] = "CREATE TABLE {$this->brands_table} (
            brand_id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_name varchar(100) NOT NULL,
            PRIMARY KEY (brand_id),
            UNIQUE KEY brand_name (brand_name)
        ) $charset_collate;";

        // Watches table
        $sql[] = "CREATE TABLE {$this->watches_table} (
            watch_id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id bigint(20) NOT NULL,
            model_reference varchar(255) NOT NULL,
            verification_level varchar(50) DEFAULT NULL,
            PRIMARY KEY (watch_id),
            UNIQUE KEY brand_model (brand_id, model_reference(191)),
            KEY brand_id (brand_id)
        ) $charset_collate;";

        // Actors table
        $sql[] = "CREATE TABLE {$this->actors_table} (
            actor_id bigint(20) NOT NULL AUTO_INCREMENT,
            actor_name varchar(255) NOT NULL,
            PRIMARY KEY (actor_id),
            UNIQUE KEY actor_name (actor_name(191))
        ) $charset_collate;";

        // Characters table
        $sql[] = "CREATE TABLE {$this->characters_table} (
            character_id bigint(20) NOT NULL AUTO_INCREMENT,
            character_name varchar(255) NOT NULL,
            PRIMARY KEY (character_id),
            KEY character_name (character_name(191))
        ) $charset_collate;";

        // Film-Actor-Watch relationship table
        $sql[] = "CREATE TABLE {$this->film_actor_watch_table} (
            faw_id bigint(20) NOT NULL AUTO_INCREMENT,
            film_id bigint(20) NOT NULL,
            actor_id bigint(20) NOT NULL,
            character_id bigint(20) NOT NULL,
            watch_id bigint(20) NOT NULL,
            narrative_role text,
            source_url varchar(500) DEFAULT NULL,
            PRIMARY KEY (faw_id),
            UNIQUE KEY film_actor (film_id, actor_id),
            KEY film_id (film_id),
            KEY actor_id (actor_id),
            KEY character_id (character_id),
            KEY watch_id (watch_id)
        ) $charset_collate;";

        foreach ($sql as $query) {
            dbDelta($query);
        }

        // Check and upgrade schema for existing installations
        $this->upgrade_schema();
    }

    /**
     * Upgrade schema for existing installations
     */
    private function upgrade_schema() {
        // Check if source_url column exists
        $column_exists = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$this->film_actor_watch_table} LIKE 'source_url'"
        );

        // Add source_url column if it doesn't exist
        if (empty($column_exists)) {
            $this->wpdb->query(
                "ALTER TABLE {$this->film_actor_watch_table}
                 ADD COLUMN source_url varchar(500) DEFAULT NULL AFTER narrative_role"
            );
        }

        // Update unique constraint from film_actor_char_watch to film_actor
        // This restricts to one watch per actor per film
        $old_constraint = $this->wpdb->get_results(
            "SHOW KEYS FROM {$this->film_actor_watch_table} WHERE Key_name = 'film_actor_char_watch'"
        );

        $new_constraint = $this->wpdb->get_results(
            "SHOW KEYS FROM {$this->film_actor_watch_table} WHERE Key_name = 'film_actor'"
        );

        // If old constraint exists and new one doesn't, migrate
        if (!empty($old_constraint) && empty($new_constraint)) {
            // Drop the old constraint
            $this->wpdb->query(
                "ALTER TABLE {$this->film_actor_watch_table}
                 DROP INDEX film_actor_char_watch"
            );

            // Add the new constraint
            $this->wpdb->query(
                "ALTER TABLE {$this->film_actor_watch_table}
                 ADD UNIQUE KEY film_actor (film_id, actor_id)"
            );
        }
    }

    /**
     * Parse natural language entry into structured data
     * Converted from Python parse_entry() function
     */
    public function parse_entry($text) {
        // Remove trailing periods but preserve them in abbreviations
        $text = rtrim($text, '.');

        // Pattern 1: "Actor wears/wore Brand Model in Year Film"
        $pattern1 = '/(.+?)\s+(?:wears?|wearing|wore)\s+(?:an?\s+)?(.+?)\s+(?:watch\s+)?in\s+(?:the\s+)?(?:movie\s+)?(\d{4})\s+(?:\w+\s+)?(.+?)$/i';

        // Pattern 2: "Actor wears/wore Brand Model in Film (Year)"
        $pattern2 = '/(.+?)\s+(?:wears?|wearing|wore)\s+(?:an?\s+)?(.+?)\s+in\s+(?:the\s+)?(?:movie\s+)?(.+?)\s+\((\d{4})\)$/i';

        // Pattern 3: "In Film (Year), Actor as Character wears/wore Brand Model"
        $pattern3 = '/In\s+(.+?)\s+\((\d{4})\),\s+(.+?)\s+(?:as|plays)\s+(.+?)\s+(?:wears?|wearing|wore)\s+(?:an?\s+)?(.+?)$/i';

        $actor = null;
        $watch_full = null;
        $year = null;
        $title = null;
        $character = null;

        if (preg_match($pattern1, $text, $match)) {
            $actor = trim($match[1]);
            $watch_full = trim($match[2]);
            $year = intval($match[3]);
            $title = trim($match[4]);
        } elseif (preg_match($pattern2, $text, $match)) {
            $actor = trim($match[1]);
            $watch_full = trim($match[2]);
            $title = trim($match[3]);
            $year = intval($match[4]);
        } elseif (preg_match($pattern3, $text, $match)) {
            $title = trim($match[1]);
            $year = intval($match[2]);
            $actor = trim($match[3]);
            $character = trim($match[4]);
            $watch_full = trim($match[5]);
        } else {
            throw new Exception("Could not parse entry");
        }

        // Brand list - IMPORTANT: List longer brand names first
        $brands = array(
            'Audemars Piguet', 'Patek Philippe', 'Vacheron Constantin',
            'Jaeger-LeCoultre', 'A. Lange & Söhne', 'Frederique Constant',
            'Ulysse Nardin', 'Girard-Perregaux', 'Glashutte Original',
            'Universal Genève', 'Richard Mille', 'Bell & Ross', 'Maurice Lacroix',
            'Carl F. Bucherer', 'Raymond Weil', 'TAG Heuer',
            'IWC Schaffhausen', 'Franck Muller',
            'Rolex', 'Omega', 'Heuer', 'Hamilton', 'Panerai', 'Breitling',
            'IWC', 'Cartier', 'Zenith', 'Breguet', 'Longines', 'Seiko',
            'Citizen', 'Casio', 'Timex', 'Doxa', 'Hublot', 'Tudor',
            'Bulgari', 'Chopard', 'Oris', 'Tissot', 'Rado', 'Mido',
            'Certina', 'Swatch', 'Luminox', 'Fortis', 'Glycine', 'Stowa',
            'Nomos', 'Junghans', 'Sinn', 'Hanhart', 'Laco', 'Damasko',
            'Ball', 'Alpina', 'Movado', 'Ebel', 'Concord', 'Corum',
            'Parmigiani', 'Piaget', 'Blancpain', 'Bremont', 'Christopher Ward',
            'Squale', 'Steinhart', 'Halios', 'Monta', 'Farer', 'Lorier',
            'G-Shock', 'Victorinox', 'Bulova', 'Gruen', 'Elgin', 'Waltham'
        );

        $brand = null;
        $model = $watch_full;

        // First, check for "by [Brand]" or "from [Brand]" patterns
        foreach ($brands as $b) {
            $by_pattern = ' by ' . $b;
            $from_pattern = ' from ' . $b;

            if (stripos($watch_full, $by_pattern) !== false) {
                $brand = $b;
                $model = trim(preg_replace('/' . preg_quote($by_pattern, '/') . '/i', '', $watch_full));
                break;
            } elseif (stripos($watch_full, $from_pattern) !== false) {
                $brand = $b;
                $model = trim(preg_replace('/' . preg_quote($from_pattern, '/') . '/i', '', $watch_full));
                break;
            }
        }

        // If no "by/from" pattern found, check if it starts with a brand name
        if (!$brand) {
            foreach ($brands as $b) {
                if (stripos($watch_full, $b) === 0) {
                    $brand = $b;
                    $model = trim(substr($watch_full, strlen($b)));
                    break;
                }
            }
        }

        // Fallback: use first word as brand
        if (!$brand) {
            $parts = explode(' ', $watch_full, 2);
            if (count($parts) === 2) {
                $brand = $parts[0];
                $model = $parts[1];
            } else {
                $brand = $watch_full;
                $model = $watch_full;
            }
        }

        // Extract character from "Actor as Character" pattern if not already set
        if (!$character) {
            // Check if actor string contains "as" or "plays"
            if (preg_match('/(.+?)\s+(?:as|plays)\s+(.+)$/i', $actor, $char_match)) {
                $actor = trim($char_match[1]);
                $character = trim($char_match[2]);
            } else {
                // Default character to actor's last name if not provided
                $name_parts = explode(' ', $actor);
                $character = end($name_parts);
            }
        }

        return array(
            'actor' => $actor,
            'character' => $character,
            'brand' => $brand,
            'model' => $model,
            'title' => $title,
            'year' => $year,
            'verification' => 'Confirmed',
            'narrative' => 'Watch worn in film.'
        );
    }

    /**
     * Insert entry into database
     */
    public function insert_entry($data) {
        // Insert film
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->films_table} (title, year) VALUES (%s, %d)",
            $data['title'], $data['year']
        ));

        // Insert brand
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->brands_table} (brand_name) VALUES (%s)",
            $data['brand']
        ));

        // Get brand_id
        $brand_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT brand_id FROM {$this->brands_table} WHERE brand_name = %s",
            $data['brand']
        ));

        // Insert watch
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->watches_table} (brand_id, model_reference, verification_level) VALUES (%d, %s, %s)",
            $brand_id, $data['model'], $data['verification']
        ));

        // Insert actor
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->actors_table} (actor_name) VALUES (%s)",
            $data['actor']
        ));

        // Get IDs
        $film_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT film_id FROM {$this->films_table} WHERE title = %s AND year = %d",
            $data['title'], $data['year']
        ));

        $actor_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT actor_id FROM {$this->actors_table} WHERE actor_name = %s",
            $data['actor']
        ));

        $watch_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT w.watch_id FROM {$this->watches_table} w
             JOIN {$this->brands_table} b ON w.brand_id = b.brand_id
             WHERE b.brand_name = %s AND w.model_reference = %s",
            $data['brand'], $data['model']
        ));

        // Check for duplicates (one watch per actor per film)
        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT faw.*, f.title, f.year, a.actor_name, b.brand_name, w.model_reference, c.character_name
             FROM {$this->film_actor_watch_table} faw
             JOIN {$this->films_table} f ON faw.film_id = f.film_id
             JOIN {$this->actors_table} a ON faw.actor_id = a.actor_id
             JOIN {$this->brands_table} b ON b.brand_id = (SELECT brand_id FROM {$this->watches_table} WHERE watch_id = faw.watch_id)
             JOIN {$this->watches_table} w ON faw.watch_id = w.watch_id
             JOIN {$this->characters_table} c ON faw.character_id = c.character_id
             WHERE faw.film_id = %d AND faw.actor_id = %d",
            $film_id, $actor_id
        ), ARRAY_A);

        if ($existing) {
            // Return the existing entry for potential update (strip slashes from text fields)
            $exception = new Exception("duplicate");
            $exception->existing_data = array(
                'faw_id' => $existing['faw_id'],
                'actor' => stripslashes($existing['actor_name']),
                'title' => stripslashes($existing['title']),
                'year' => $existing['year'],
                'brand' => stripslashes($existing['brand_name']),
                'model' => stripslashes($existing['model_reference']),
                'character' => stripslashes($existing['character_name']),
                'narrative' => stripslashes($existing['narrative_role']),
                'source_url' => $existing['source_url']
            );
            throw $exception;
        }

        // Check if character exists
        $character_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT character_id FROM {$this->characters_table} WHERE character_name = %s LIMIT 1",
            $data['character']
        ));

        if (!$character_id) {
            $this->wpdb->insert(
                $this->characters_table,
                array('character_name' => $data['character']),
                array('%s')
            );
            $character_id = $this->wpdb->insert_id;
        }

        // Insert relationship
        $insert_data = array(
            'film_id' => $film_id,
            'actor_id' => $actor_id,
            'character_id' => $character_id,
            'watch_id' => $watch_id,
            'narrative_role' => $data['narrative']
        );
        $format = array('%d', '%d', '%d', '%d', '%s');

        // Add source URL if provided
        if (!empty($data['source_url'])) {
            $insert_data['source_url'] = $data['source_url'];
            $format[] = '%s';
        }

        $result = $this->wpdb->insert(
            $this->film_actor_watch_table,
            $insert_data,
            $format
        );

        if ($result === false) {
            // Check if it's a duplicate key error
            if (strpos($this->wpdb->last_error, 'Duplicate entry') !== false) {
                // Fetch the existing entry to return for comparison
                $existing = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT faw.*, f.title, f.year, a.actor_name, b.brand_name, w.model_reference, c.character_name
                     FROM {$this->film_actor_watch_table} faw
                     JOIN {$this->films_table} f ON faw.film_id = f.film_id
                     JOIN {$this->actors_table} a ON faw.actor_id = a.actor_id
                     JOIN {$this->brands_table} b ON b.brand_id = (SELECT brand_id FROM {$this->watches_table} WHERE watch_id = faw.watch_id)
                     JOIN {$this->watches_table} w ON faw.watch_id = w.watch_id
                     JOIN {$this->characters_table} c ON faw.character_id = c.character_id
                     WHERE faw.film_id = %d AND faw.actor_id = %d",
                    $film_id, $actor_id
                ), ARRAY_A);

                $exception = new Exception("duplicate");
                $exception->existing_data = array(
                    'faw_id' => $existing['faw_id'],
                    'actor' => stripslashes($existing['actor_name']),
                    'title' => stripslashes($existing['title']),
                    'year' => $existing['year'],
                    'brand' => stripslashes($existing['brand_name']),
                    'model' => stripslashes($existing['model_reference']),
                    'character' => stripslashes($existing['character_name']),
                    'narrative' => stripslashes($existing['narrative_role']),
                    'source_url' => $existing['source_url']
                );
                throw $exception;
            }
            throw new Exception("Database error: " . $this->wpdb->last_error);
        }

        return true;
    }

    /**
     * Update existing entry
     */
    public function update_entry($faw_id, $data) {
        // Get or create brand
        $brand_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT brand_id FROM {$this->brands_table} WHERE brand_name = %s LIMIT 1",
            $data['brand']
        ));

        if (!$brand_id) {
            $this->wpdb->insert(
                $this->brands_table,
                array('brand_name' => $data['brand']),
                array('%s')
            );
            $brand_id = $this->wpdb->insert_id;
        }

        // Get or create watch
        $watch_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT watch_id FROM {$this->watches_table}
             WHERE brand_id = %d AND model_reference = %s LIMIT 1",
            $brand_id, $data['model']
        ));

        if (!$watch_id) {
            $this->wpdb->insert(
                $this->watches_table,
                array('brand_id' => $brand_id, 'model_reference' => $data['model']),
                array('%d', '%s')
            );
            $watch_id = $this->wpdb->insert_id;
        }

        // Get or create character
        $character_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT character_id FROM {$this->characters_table} WHERE character_name = %s LIMIT 1",
            $data['character']
        ));

        if (!$character_id) {
            $this->wpdb->insert(
                $this->characters_table,
                array('character_name' => $data['character']),
                array('%s')
            );
            $character_id = $this->wpdb->insert_id;
        }

        // Update the relationship
        $update_data = array(
            'character_id' => $character_id,
            'watch_id' => $watch_id,
            'narrative_role' => $data['narrative']
        );
        $format = array('%d', '%d', '%s');

        // Add source URL if provided
        if (!empty($data['source_url'])) {
            $update_data['source_url'] = $data['source_url'];
            $format[] = '%s';
        }

        $result = $this->wpdb->update(
            $this->film_actor_watch_table,
            $update_data,
            array('faw_id' => $faw_id),
            $format,
            array('%d')
        );

        if ($result === false) {
            throw new Exception("Database error: " . $this->wpdb->last_error);
        }

        return true;
    }

    /**
     * Query watches by actor
     */
    public function query_actor($actor_name) {
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT f.title, f.year, b.brand_name, w.model_reference,
                    c.character_name, faw.narrative_role, faw.source_url
             FROM {$this->film_actor_watch_table} faw
             JOIN {$this->films_table} f ON faw.film_id = f.film_id
             JOIN {$this->actors_table} a ON faw.actor_id = a.actor_id
             JOIN {$this->characters_table} c ON faw.character_id = c.character_id
             JOIN {$this->watches_table} w ON faw.watch_id = w.watch_id
             JOIN {$this->brands_table} b ON w.brand_id = b.brand_id
             WHERE a.actor_name LIKE %s
             ORDER BY f.year DESC",
            '%' . $this->wpdb->esc_like($actor_name) . '%'
        ), ARRAY_A);

        $films = array();
        foreach ($results as $row) {
            $films[] = array(
                'title' => stripslashes($row['title']),
                'year' => $row['year'],
                'brand' => stripslashes($row['brand_name']),
                'model' => stripslashes($row['model_reference']),
                'character' => stripslashes($row['character_name']),
                'narrative' => stripslashes($row['narrative_role']),
                'source_url' => $row['source_url']
            );
        }

        return array(
            'success' => true,
            'actor' => $actor_name,
            'count' => count($films),
            'films' => $films
        );
    }

    /**
     * Query films by brand
     */
    public function query_brand($brand_name) {
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT f.title, f.year, a.actor_name, w.model_reference,
                    c.character_name, faw.narrative_role, faw.source_url
             FROM {$this->film_actor_watch_table} faw
             JOIN {$this->films_table} f ON faw.film_id = f.film_id
             JOIN {$this->actors_table} a ON faw.actor_id = a.actor_id
             JOIN {$this->characters_table} c ON faw.character_id = c.character_id
             JOIN {$this->watches_table} w ON faw.watch_id = w.watch_id
             JOIN {$this->brands_table} b ON w.brand_id = b.brand_id
             WHERE b.brand_name LIKE %s
             ORDER BY f.year DESC",
            '%' . $this->wpdb->esc_like($brand_name) . '%'
        ), ARRAY_A);

        $films = array();
        foreach ($results as $row) {
            $films[] = array(
                'title' => stripslashes($row['title']),
                'year' => $row['year'],
                'actor' => stripslashes($row['actor_name']),
                'model' => stripslashes($row['model_reference']),
                'character' => stripslashes($row['character_name']),
                'narrative' => stripslashes($row['narrative_role']),
                'source_url' => $row['source_url']
            );
        }

        return array(
            'success' => true,
            'brand' => $brand_name,
            'count' => count($films),
            'films' => $films
        );
    }

    /**
     * Query watches by film
     */
    public function query_film($film_title) {
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT f.title, f.year, a.actor_name, b.brand_name,
                    w.model_reference, c.character_name, faw.narrative_role, faw.source_url
             FROM {$this->film_actor_watch_table} faw
             JOIN {$this->films_table} f ON faw.film_id = f.film_id
             JOIN {$this->actors_table} a ON faw.actor_id = a.actor_id
             JOIN {$this->characters_table} c ON faw.character_id = c.character_id
             JOIN {$this->watches_table} w ON faw.watch_id = w.watch_id
             JOIN {$this->brands_table} b ON w.brand_id = b.brand_id
             WHERE f.title LIKE %s
             ORDER BY a.actor_name",
            '%' . $this->wpdb->esc_like($film_title) . '%'
        ), ARRAY_A);

        $watches = array();
        foreach ($results as $row) {
            $watches[] = array(
                'title' => stripslashes($row['title']),
                'year' => $row['year'],
                'actor' => stripslashes($row['actor_name']),
                'brand' => stripslashes($row['brand_name']),
                'model' => stripslashes($row['model_reference']),
                'character' => stripslashes($row['character_name']),
                'narrative' => stripslashes($row['narrative_role']),
                'source_url' => $row['source_url']
            );
        }

        return array(
            'success' => true,
            'film' => $film_title,
            'count' => count($watches),
            'watches' => $watches
        );
    }

    /**
     * Get database statistics
     */
    public function get_stats() {
        $film_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->films_table}");
        $actor_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->actors_table}");
        $brand_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->brands_table}");
        $entry_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->film_actor_watch_table}");

        $top_brands_results = $this->wpdb->get_results(
            "SELECT b.brand_name, COUNT(*) as count
             FROM {$this->film_actor_watch_table} faw
             JOIN {$this->watches_table} w ON faw.watch_id = w.watch_id
             JOIN {$this->brands_table} b ON w.brand_id = b.brand_id
             GROUP BY b.brand_name
             ORDER BY count DESC
             LIMIT 10",
            ARRAY_A
        );

        $top_brands = array();
        foreach ($top_brands_results as $row) {
            $top_brands[] = array(
                'brand' => $row['brand_name'],
                'count' => $row['count']
            );
        }

        return array(
            'success' => true,
            'stats' => array(
                'films' => $film_count,
                'actors' => $actor_count,
                'brands' => $brand_count,
                'entries' => $entry_count,
                'top_brands' => $top_brands
            )
        );
    }

    /**
     * Get database info for admin display
     */
    public function get_db_info() {
        return array(
            'type' => 'MySQL',
            'host' => DB_HOST,
            'name' => DB_NAME,
            'prefix' => $this->table_prefix,
            'tables' => array(
                $this->films_table,
                $this->brands_table,
                $this->watches_table,
                $this->actors_table,
                $this->characters_table,
                $this->film_actor_watch_table
            )
        );
    }
}

/**
 * Get global database instance
 */
function fwd_db() {
    static $instance = null;
    if ($instance === null) {
        $instance = new FWD_Database();
    }
    return $instance;
}
