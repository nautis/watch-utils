# Film Watch Database - WordPress Plugin

A WordPress plugin that integrates your Film Watch Database Flask API with WordPress. Search movies, actors, and watch brands directly from your WordPress site.

## Features

- **Search Functionality**: Search by actor, watch brand, or film title
- **Database Statistics**: Display film and watch statistics
- **Multiple Shortcodes**: Easy integration into any page or post
- **Admin Panel**: Configure Flask API connection and manage settings
- **Caching System**: Improve performance with built-in API response caching
- **Responsive Design**: Mobile-friendly interface
- **AJAX-Powered**: Smooth, no-refresh search experience

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Flask backend running (flask_backend.py)
- cURL extension enabled in PHP

## Installation

### 1. Install the Plugin

**Option A: Manual Installation**
1. Download or clone this repository
2. Copy the `film-watch-database` folder to your WordPress plugins directory:
   ```
   /wp-content/plugins/film-watch-database/
   ```
3. Go to WordPress Admin → Plugins
4. Activate "Film Watch Database"

**Option B: ZIP Installation**
1. Zip the `film-watch-database` folder
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin

### 2. Configure the Plugin

1. Go to **Settings → Film Watch DB**
2. Enter your Flask API URL (e.g., `http://127.0.0.1:5000` for local or `https://api.yourdomain.com` for remote)
3. Configure cache settings (optional)
4. Click "Save Changes"
5. Check the "Backend Status" indicator to ensure connection is successful

### 3. Start Your Flask Backend

Make sure your Flask backend is running:

```bash
cd /path/to/watch-utils
python flask_backend.py
```

The backend should be accessible at the URL you configured in step 2.

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

This shortcode is only visible to users with "manage_options" capability (administrators).

## Deployment Options

### Option 1: WordPress and Flask on Same Server

If both WordPress and Flask are on the same server:

1. Run Flask backend:
   ```bash
   python flask_backend.py
   ```

2. Configure plugin to use `http://127.0.0.1:5000`

### Option 2: WordPress and Flask on Different Servers

If Flask is hosted separately:

1. Deploy Flask to your server (DigitalOcean, AWS, etc.)
2. Use a production WSGI server like Gunicorn:
   ```bash
   pip install gunicorn
   gunicorn -w 4 -b 0.0.0.0:5000 flask_backend:app
   ```

3. Set up Nginx reverse proxy (recommended)
4. Configure plugin to use your Flask API URL (e.g., `https://api.yourdomain.com`)

### Option 3: Docker Deployment

Create a `Dockerfile` for your Flask backend:

```dockerfile
FROM python:3.9-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY . .
CMD ["gunicorn", "-w", "4", "-b", "0.0.0.0:5000", "flask_backend:app"]
```

Run with Docker:
```bash
docker build -t film-watch-api .
docker run -d -p 5000:5000 film-watch-api
```

## Configuration on Ubuntu 24.04 Server

Your DigitalOcean server setup:

### 1. Install Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Python and pip
sudo apt install python3 python3-pip python3-venv -y

# Install Nginx (optional, for production)
sudo apt install nginx -y
```

### 2. Set Up Flask Backend

```bash
# Navigate to your project
cd /home/user/watch-utils

# Create virtual environment
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install flask flask-cors gunicorn
```

### 3. Run Flask Backend

**Development:**
```bash
python flask_backend.py
```

**Production (with Gunicorn):**
```bash
gunicorn -w 2 -b 127.0.0.1:5000 flask_backend:app
```

**Production (as systemd service):**

Create `/etc/systemd/system/film-watch-api.service`:

```ini
[Unit]
Description=Film Watch Database API
After=network.target

[Service]
User=www-data
WorkingDirectory=/home/user/watch-utils
Environment="PATH=/home/user/watch-utils/venv/bin"
ExecStart=/home/user/watch-utils/venv/bin/gunicorn -w 2 -b 127.0.0.1:5000 flask_backend:app

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable film-watch-api
sudo systemctl start film-watch-api
sudo systemctl status film-watch-api
```

### 4. Configure Nginx (Optional)

Create `/etc/nginx/sites-available/film-watch-api`:

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;

    location / {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/film-watch-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Troubleshooting

### Backend Status Shows "Offline"

1. Check if Flask backend is running:
   ```bash
   curl http://127.0.0.1:5000
   ```

2. Check Flask logs for errors

3. Verify API URL in plugin settings matches your Flask backend URL

4. Check firewall settings if Flask is on a different server

### Search Returns No Results

1. Verify database has data
2. Check Flask API responses manually:
   ```bash
   curl http://127.0.0.1:5000/api/query/actor/Tom%20Cruise
   ```

3. Clear plugin cache (Settings → Film Watch DB → Tools → Clear Cache)

### Permission Denied Errors

1. Check file permissions:
   ```bash
   chmod 644 film_watches.db
   ```

2. Ensure PHP user (www-data) can read database files

## Security Recommendations

1. **Use HTTPS**: Always use HTTPS for production API endpoints
2. **Firewall**: Restrict Flask backend access if on same server
3. **Authentication**: Consider adding API key authentication to Flask
4. **Rate Limiting**: Implement rate limiting on Flask backend
5. **CORS**: Configure CORS properly in Flask for production

## Support

For issues or questions:
- GitHub: [https://github.com/nautis/watch-utils](https://github.com/nautis/watch-utils)
- Report bugs in the Issues section

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Search by actor, brand, and film
- Database statistics display
- Admin settings panel
- Caching system
- Multiple shortcodes
- AJAX-powered interface
