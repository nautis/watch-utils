<?php
/**
 * Database Handler - Native PHP SQLite Implementation
 * Replaces Flask backend with WordPress-native PHP code
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Database {

    private $db = null;
    private $db_path = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Store database in WordPress uploads directory
        $upload_dir = wp_upload_dir();
        $this->db_path = $upload_dir['basedir'] . '/film-watch-database/film_watches.db';

        // Ensure directory exists
        $db_dir = dirname($this->db_path);
        if (!file_exists($db_dir)) {
            wp_mkdir_p($db_dir);
        }

        $this->connect();
        $this->create_tables();
    }

    /**
     * Connect to SQLite database
     */
    private function connect() {
        try {
            $this->db = new PDO('sqlite:' . $this->db_path);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Enable foreign keys
            $this->db->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            error_log('FWD Database Connection Error: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Create database tables if they don't exist
     */
    private function create_tables() {
        $schema = "
        CREATE TABLE IF NOT EXISTS films (
            film_id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            year INTEGER NOT NULL,
            UNIQUE(title, year)
        );

        CREATE TABLE IF NOT EXISTS brands (
            brand_id INTEGER PRIMARY KEY AUTOINCREMENT,
            brand_name VARCHAR(100) NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS watches (
            watch_id INTEGER PRIMARY KEY AUTOINCREMENT,
            brand_id INTEGER NOT NULL,
            model_reference VARCHAR(255) NOT NULL,
            verification_level VARCHAR(50),
            FOREIGN KEY (brand_id) REFERENCES brands(brand_id),
            UNIQUE(brand_id, model_reference)
        );

        CREATE TABLE IF NOT EXISTS actors (
            actor_id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor_name VARCHAR(255) NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS characters (
            character_id INTEGER PRIMARY KEY AUTOINCREMENT,
            character_name VARCHAR(255) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS film_actor_watch (
            faw_id INTEGER PRIMARY KEY AUTOINCREMENT,
            film_id INTEGER NOT NULL,
            actor_id INTEGER NOT NULL,
            character_id INTEGER NOT NULL,
            watch_id INTEGER NOT NULL,
            narrative_role TEXT,
            FOREIGN KEY (film_id) REFERENCES films(film_id),
            FOREIGN KEY (actor_id) REFERENCES actors(actor_id),
            FOREIGN KEY (character_id) REFERENCES characters(character_id),
            FOREIGN KEY (watch_id) REFERENCES watches(watch_id),
            UNIQUE(film_id, actor_id, character_id, watch_id)
        );
        ";

        try {
            $this->db->exec($schema);
        } catch (PDOException $e) {
            error_log('FWD Database Schema Error: ' . $e->getMessage());
        }
    }

    /**
     * Parse natural language entry into structured data
     * Converted from Python parse_entry() function
     */
    public function parse_entry($text) {
        // Remove trailing periods but preserve them in abbreviations
        $text = rtrim($text, '.');

        // Pattern 1: "Actor wears Brand Model in Year Film"
        $pattern1 = '/(.+?)\s+(?:wears?|wearing)\s+(?:a|an)\s+(.+?)\s+(?:watch\s+)?in\s+(?:the\s+)?(\d{4})\s+(?:\w+\s+)?(.+?)$/i';

        // Pattern 2: "Actor wears Brand Model in Film (Year)"
        $pattern2 = '/(.+?)\s+(?:wears?|wearing)\s+(?:a|an)\s+(.+?)\s+in\s+(.+?)\s+\((\d{4})\)$/i';

        // Pattern 3: "In Film (Year), Actor as Character wears Brand Model"
        $pattern3 = '/In\s+(.+?)\s+\((\d{4})\),\s+(.+?)\s+(?:as|plays)\s+(.+?)\s+(?:wears?|wearing)\s+(?:a|an)\s+(.+?)$/i';

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

        // Default character to actor's last name if not provided
        if (!$character) {
            $name_parts = explode(' ', $actor);
            $character = end($name_parts);
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
     * Converted from Python execute_insert() function
     */
    public function insert_entry($data) {
        try {
            $this->db->beginTransaction();

            // Insert film
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO films (title, year) VALUES (?, ?)");
            $stmt->execute(array($data['title'], $data['year']));

            // Insert brand
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO brands (brand_name) VALUES (?)");
            $stmt->execute(array($data['brand']));

            // Get brand_id
            $stmt = $this->db->prepare("SELECT brand_id FROM brands WHERE brand_name = ?");
            $stmt->execute(array($data['brand']));
            $brand_id = $stmt->fetchColumn();

            // Insert watch
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO watches (brand_id, model_reference, verification_level) VALUES (?, ?, ?)");
            $stmt->execute(array($brand_id, $data['model'], $data['verification']));

            // Insert actor
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO actors (actor_name) VALUES (?)");
            $stmt->execute(array($data['actor']));

            // Get IDs
            $stmt = $this->db->prepare("SELECT film_id FROM films WHERE title = ? AND year = ?");
            $stmt->execute(array($data['title'], $data['year']));
            $film_id = $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT actor_id FROM actors WHERE actor_name = ?");
            $stmt->execute(array($data['actor']));
            $actor_id = $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT w.watch_id FROM watches w
                                        JOIN brands b ON w.brand_id = b.brand_id
                                        WHERE b.brand_name = ? AND w.model_reference = ?");
            $stmt->execute(array($data['brand'], $data['model']));
            $watch_id = $stmt->fetchColumn();

            // Check for duplicates
            $stmt = $this->db->prepare("SELECT faw_id FROM film_actor_watch
                                       WHERE film_id = ? AND actor_id = ? AND watch_id = ?");
            $stmt->execute(array($film_id, $actor_id, $watch_id));
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $this->db->rollBack();
                throw new Exception("Duplicate entry: {$data['actor']} wearing {$data['brand']} {$data['model']} in {$data['title']} already exists in the database.");
            }

            // Check if character exists
            $stmt = $this->db->prepare("SELECT character_id FROM characters WHERE character_name = ? LIMIT 1");
            $stmt->execute(array($data['character']));
            $character_id = $stmt->fetchColumn();

            if (!$character_id) {
                $stmt = $this->db->prepare("INSERT INTO characters (character_name) VALUES (?)");
                $stmt->execute(array($data['character']));
                $character_id = $this->db->lastInsertId();
            }

            // Insert relationship
            $stmt = $this->db->prepare("INSERT INTO film_actor_watch
                                       (film_id, actor_id, character_id, watch_id, narrative_role)
                                       VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(array($film_id, $actor_id, $character_id, $watch_id, $data['narrative']));

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Query watches by actor
     */
    public function query_actor($actor_name) {
        $stmt = $this->db->prepare("
            SELECT f.title, f.year, b.brand_name, w.model_reference,
                   c.character_name, faw.narrative_role
            FROM film_actor_watch faw
            JOIN films f ON faw.film_id = f.film_id
            JOIN actors a ON faw.actor_id = a.actor_id
            JOIN characters c ON faw.character_id = c.character_id
            JOIN watches w ON faw.watch_id = w.watch_id
            JOIN brands b ON w.brand_id = b.brand_id
            WHERE a.actor_name LIKE ?
            ORDER BY f.year DESC
        ");

        $stmt->execute(array('%' . $actor_name . '%'));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $films = array();
        foreach ($results as $row) {
            $films[] = array(
                'title' => $row['title'],
                'year' => $row['year'],
                'brand' => $row['brand_name'],
                'model' => $row['model_reference'],
                'character' => $row['character_name'],
                'narrative' => $row['narrative_role']
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
        $stmt = $this->db->prepare("
            SELECT f.title, f.year, a.actor_name, w.model_reference,
                   c.character_name, faw.narrative_role
            FROM film_actor_watch faw
            JOIN films f ON faw.film_id = f.film_id
            JOIN actors a ON faw.actor_id = a.actor_id
            JOIN characters c ON faw.character_id = c.character_id
            JOIN watches w ON faw.watch_id = w.watch_id
            JOIN brands b ON w.brand_id = b.brand_id
            WHERE b.brand_name LIKE ?
            ORDER BY f.year DESC
        ");

        $stmt->execute(array('%' . $brand_name . '%'));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $films = array();
        foreach ($results as $row) {
            $films[] = array(
                'title' => $row['title'],
                'year' => $row['year'],
                'actor' => $row['actor_name'],
                'model' => $row['model_reference'],
                'character' => $row['character_name'],
                'narrative' => $row['narrative_role']
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
        $stmt = $this->db->prepare("
            SELECT f.title, f.year, a.actor_name, b.brand_name,
                   w.model_reference, c.character_name, faw.narrative_role
            FROM film_actor_watch faw
            JOIN films f ON faw.film_id = f.film_id
            JOIN actors a ON faw.actor_id = a.actor_id
            JOIN characters c ON faw.character_id = c.character_id
            JOIN watches w ON faw.watch_id = w.watch_id
            JOIN brands b ON w.brand_id = b.brand_id
            WHERE f.title LIKE ?
            ORDER BY a.actor_name
        ");

        $stmt->execute(array('%' . $film_title . '%'));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $watches = array();
        foreach ($results as $row) {
            $watches[] = array(
                'title' => $row['title'],
                'year' => $row['year'],
                'actor' => $row['actor_name'],
                'brand' => $row['brand_name'],
                'model' => $row['model_reference'],
                'character' => $row['character_name'],
                'narrative' => $row['narrative_role']
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
        $stmt = $this->db->query("SELECT COUNT(*) FROM films");
        $film_count = $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM actors");
        $actor_count = $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM brands");
        $brand_count = $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM film_actor_watch");
        $entry_count = $stmt->fetchColumn();

        $stmt = $this->db->query("
            SELECT b.brand_name, COUNT(*) as count
            FROM film_actor_watch faw
            JOIN watches w ON faw.watch_id = w.watch_id
            JOIN brands b ON w.brand_id = b.brand_id
            GROUP BY b.brand_name
            ORDER BY count DESC
            LIMIT 10
        ");

        $top_brands = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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
     * Get database path for admin display
     */
    public function get_db_path() {
        return $this->db_path;
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
