# Film Watch Database - WordPress Plugin

A **100% WordPress-native plugin** for managing and displaying a searchable database of watches worn by actors in films. No external servers or dependencies required!

## Features

- **Pure PHP/WordPress**: All Python logic converted to native PHP - no Flask backend needed
- **SQLite Database**: Lightweight, file-based database stored in WordPress uploads directory
- **Search Functionality**: Search by actor, watch brand, or film title
- **Database Statistics**: Display film and watch statistics
- **Multiple Shortcodes**: Easy integration into any page or post
- **Natural Language Parsing**: Add entries using plain English (e.g., "Tom Cruise wears Rolex in Top Gun")
- **Admin Panel**: Manage database and view statistics
- **Responsive Design**: Mobile-friendly interface
- **AJAX-Powered**: Smooth, no-refresh search experience

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- PDO extension with SQLite driver (standard in most PHP installations)
- Writable uploads directory

## Installation

### Method 1: Manual Installation

1. Download or clone this repository
2. Copy the `film-watch-database` folder to your WordPress plugins directory:
   ```
   /wp-content/plugins/film-watch-database/
   ```
3. Go to WordPress Admin → Plugins
4. Activate "Film Watch Database"

### Method 2: ZIP Installation

1. Zip the `film-watch-database` folder
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin

### Method 3: Direct from GitHub

```bash
cd /var/www/html/wp-content/plugins/
git clone https://github.com/nautis/watch-utils.git temp
mv temp/wordpress-plugin/film-watch-database ./
rm -rf temp
```

## Quick Start

1. **Activate the plugin** in WordPress Admin → Plugins
2. Go to **Settings → Film Watch DB** to verify setup
3. Add a shortcode to any page: `[film_watch_search]`
4. Start adding entries using the `[film_watch_add]` shortcode (admin only)

## Usage

### Available Shortcodes

#### 1. Search Form
Display a search form for the database:

```
[film_watch_search]
```

**Parameters:**
- `type` - Search type: "all", "actor", "brand", or "film" (default: "all")
- `placeholder` - Custom placeholder text

**Examples:**
```
[film_watch_search]
[film_watch_search type="actor" placeholder="Search for an actor..."]
[film_watch_search type="brand"]
```

#### 2. Database Statistics
Display database statistics with top brands:

```
[film_watch_stats]
```

**Parameters:**
- `show_top_brands` - Show top brands list: "yes" or "no" (default: "yes")

**Examples:**
```
[film_watch_stats]
[film_watch_stats show_top_brands="no"]
```

#### 3. Actor's Watches
Display watches worn by a specific actor:

```
[film_watch_actor name="Tom Cruise"]
```

**Example:**
```
[film_watch_actor name="Daniel Craig"]
```

#### 4. Watch Brand in Films
Display films featuring a specific watch brand:

```
[film_watch_brand name="Rolex"]
```

**Example:**
```
[film_watch_brand name="Omega"]
```

#### 5. Watches in a Film
Display watches featured in a specific film:

```
[film_watch_film title="Casino Royale"]
```

**Example:**
```
[film_watch_film title="Skyfall"]
```

#### 6. Add Entry Form (Admin Only)
Display a form to add new entries to the database:

```
[film_watch_add]
```

This shortcode is only visible to administrators. Use natural language to add entries:

**Examples:**
- "Tom Cruise wears Breitling Navitimer in Top Gun: Maverick (2022)"
- "Daniel Craig wears Omega Seamaster in Casino Royale (2006)"
- "In Interstellar (2014), Matthew McConaughey as Cooper wears Hamilton Khaki Pilot"

## Importing Existing Data

If you have an existing `film_watches.db` from the Flask backend:

1. Go to **Settings → Film Watch DB** to find your database path
2. Copy your existing database to that location
3. Refresh the settings page to see your data

Typical database location: `/wp-content/uploads/film-watch-database/film_watches.db`

## Database Schema

The plugin creates 6 tables:

- **films**: Film titles and years
- **actors**: Actor names
- **characters**: Character names
- **brands**: Watch brand names
- **watches**: Watch models and references
- **film_actor_watch**: Relationships between films, actors, and watches

## Technical Details

### Why WordPress-Native?

This plugin was converted from a Flask (Python) backend to pure PHP for several reasons:

✅ **No External Dependencies**: Everything runs in WordPress
✅ **Simpler Deployment**: Install like any WordPress plugin
✅ **Better Performance**: No HTTP API calls needed
✅ **Easier Maintenance**: One codebase, one language
✅ **Lower Server Requirements**: No Python runtime needed

### Natural Language Parsing

The plugin uses regex patterns to parse natural language entries:

- "Actor wears Brand Model in Year Film"
- "Actor wears Brand Model in Film (Year)"
- "In Film (Year), Actor as Character wears Brand Model"

### Database Storage

- Uses SQLite for lightweight, file-based storage
- Database stored in WordPress uploads directory
- Automatic table creation on plugin activation
- Supports import of existing databases

## Configuration on Ubuntu 24.04

Your server should already have everything needed:

### 1. Check PHP Version

```bash
php -v
```

Should show PHP 7.4 or higher (Ubuntu 24.04 has PHP 8.3 by default).

### 2. Verify SQLite Support

```bash
php -m | grep -i pdo
php -m | grep -i sqlite
```

Should show `PDO` and `pdo_sqlite`.

### 3. Check Permissions

```bash
# WordPress uploads directory should be writable
ls -la /var/www/html/wp-content/uploads/
```

## Troubleshooting

### Database Not Created

1. Go to **Settings → Film Watch DB**
2. Check "System Requirements" section
3. Ensure PDO SQLite driver is enabled
4. Verify uploads directory is writable

### Search Returns No Results

1. Verify database has data (check stats in admin)
2. Try adding a test entry using `[film_watch_add]`
3. Check browser console for JavaScript errors

### Permission Errors

Ensure WordPress uploads directory is writable:

```bash
sudo chown -R www-data:www-data /var/www/html/wp-content/uploads/
sudo chmod -R 755 /var/www/html/wp-content/uploads/
```

### PHP Extension Missing

If PDO SQLite is not available:

```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3
sudo systemctl restart apache2   # or nginx/php-fpm
```

## Advantages Over Flask Backend

| Feature | Flask Backend | WordPress-Native |
|---------|---------------|------------------|
| Installation | Complex (Python + WSGI) | Simple (WordPress plugin) |
| Dependencies | Flask, Python, Gunicorn | None (built-in PHP) |
| Server Requirements | 2 services (WP + Flask) | 1 service (WordPress) |
| Configuration | API URLs, CORS, ports | None |
| Deployment | systemd, Nginx proxy | Upload plugin |
| Maintenance | Update both systems | Update plugin only |
| Performance | HTTP API calls | Direct database access |

## Support

For issues or questions:
- GitHub: [https://github.com/nautis/watch-utils](https://github.com/nautis/watch-utils)
- Report bugs in the Issues section

## License

GPL v2 or later

## Changelog

### 2.0.0
- **MAJOR UPDATE**: Converted to WordPress-native PHP implementation
- Removed Flask backend dependency
- Pure PHP natural language parsing
- Direct SQLite database access via PDO
- Simpler installation and deployment
- Better performance (no HTTP API calls)
- Import existing Flask database files
- System requirements checker in admin

### 1.0.0
- Initial release with Flask backend integration
- Search by actor, brand, and film
- Database statistics display
- Admin settings panel
- Multiple shortcodes
- AJAX-powered interface

## Credits

Converted from Flask/Python backend to WordPress-native PHP implementation for simplified deployment and better WordPress integration.
