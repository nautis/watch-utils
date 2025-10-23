"""
Flask Backend for Film Watch Database
Install required packages: pip install flask flask-cors
Run: python flask_backend.py
"""

from flask import Flask, request, jsonify, send_from_directory
from flask_cors import CORS
import sqlite3
import re

app = Flask(__name__)
CORS(app)

DB_PATH = 'film_watches.db'

def parse_entry(text):
    """Parse natural language entry into structured data."""
    
    # Remove trailing periods but preserve them in abbreviations like "Ref."
    text = text.rstrip('.')
    
    # More robust patterns that better handle "a" and "an"
    pattern1 = r'(.+?)\s+(?:wears?|wearing)\s+(?:a|an)\s+(.+?)\s+(?:watch\s+)?in\s+(?:the\s+)?(\d{4})\s+(?:\w+\s+)?(.+?)$'
  
    pattern2 = r'(.+?)\s+(?:wears?|wearing)\s+(?:a|an)\s+(.+?)\s+in\s+(.+?)\s+\((\d{4})\)$'
  
    pattern3 = r'In\s+(.+?)\s+\((\d{4})\),\s+(.+?)\s+(?:as|plays)\s+(.+?)\s+(?:wears?|wearing)\s+(?:a|an)\s+(.+?)$'
    
    match = re.match(pattern1, text, re.IGNORECASE)
    if match:
        actor = match.group(1).strip()
        watch_full = match.group(2).strip()
        year = int(match.group(3))
        title = match.group(4).strip()
        character = None
    else:
        match = re.match(pattern2, text, re.IGNORECASE)
        if match:
            actor = match.group(1).strip()
            watch_full = match.group(2).strip()
            title = match.group(3).strip()
            year = int(match.group(4))
            character = None
        else:
            match = re.match(pattern3, text, re.IGNORECASE)
            if match:
                title = match.group(1).strip()
                year = int(match.group(2))
                actor = match.group(3).strip()
                character = match.group(4).strip()
                watch_full = match.group(5).strip()
            else:
                raise ValueError("Could not parse entry")
    
    # IMPORTANT: List longer brand names first
    brands = [
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
    ]
    
    brand = None
    model = watch_full
    
    # First, check for "by [Brand]" or "from [Brand]" patterns
    for b in brands:
        by_pattern = f" by {b}"
        from_pattern = f" from {b}"
        
        if by_pattern.lower() in watch_full.lower():
            brand = b
            # Remove the "by Brand" part from the model
            model = re.sub(f' by {re.escape(b)}', '', watch_full, flags=re.IGNORECASE).strip()
            break
        elif from_pattern.lower() in watch_full.lower():
            brand = b
            # Remove the "from Brand" part from the model
            model = re.sub(f' from {re.escape(b)}', '', watch_full, flags=re.IGNORECASE).strip()
            break
    
    # If no "by/from" pattern found, check if it starts with a brand name
    if not brand:
        for b in brands:
            if watch_full.lower().startswith(b.lower()):
                brand = b
                model = watch_full[len(b):].strip()
                break
    
    # Fallback: use first word as brand (but this might catch descriptors)
    if not brand:
        parts = watch_full.split(maxsplit=1)
        if len(parts) == 2:
            brand = parts[0]
            model = parts[1]
        else:
            brand = watch_full
            model = watch_full
    
    if not character:
        character = f"{actor.split()[-1]}"
    
    return {
        'actor': actor,
        'character': character,
        'brand': brand,
        'model': model,
        'title': title,
        'year': year,
        'verification': 'Confirmed',
        'narrative': 'Watch worn in film.'
    }


def execute_insert(conn, data):
    """Execute INSERT using parameterized queries with duplicate detection."""
    cursor = conn.cursor()
    
    try:
        # Insert film
        cursor.execute("INSERT OR IGNORE INTO films (title, year) VALUES (?, ?)",
                      (data['title'], data['year']))
        
        # Insert brand
        cursor.execute("INSERT OR IGNORE INTO brands (brand_name) VALUES (?)",
                      (data['brand'],))
        
        # Get brand_id
        cursor.execute("SELECT brand_id FROM brands WHERE brand_name = ?",
                      (data['brand'],))
        brand_id = cursor.fetchone()[0]
        
        # Insert watch
        cursor.execute("INSERT OR IGNORE INTO watches (brand_id, model_reference, verification_level) VALUES (?, ?, ?)",
                      (brand_id, data['model'], data['verification']))
        
        # Insert actor
        cursor.execute("INSERT OR IGNORE INTO actors (actor_name) VALUES (?)",
                      (data['actor'],))
        
        # Get IDs
        cursor.execute("SELECT film_id FROM films WHERE title = ? AND year = ?",
                      (data['title'], data['year']))
        film_id = cursor.fetchone()[0]
        
        cursor.execute("SELECT actor_id FROM actors WHERE actor_name = ?",
                      (data['actor'],))
        actor_id = cursor.fetchone()[0]
        
        cursor.execute("""SELECT w.watch_id FROM watches w 
                         JOIN brands b ON w.brand_id = b.brand_id 
                         WHERE b.brand_name = ? AND w.model_reference = ?""",
                      (data['brand'], data['model']))
        watch_id = cursor.fetchone()[0]
        
        # CHECK FOR DUPLICATE: Does this film-actor-watch combination already exist?
        cursor.execute("""
            SELECT faw_id FROM film_actor_watch 
            WHERE film_id = ? AND actor_id = ? AND watch_id = ?
        """, (film_id, actor_id, watch_id))
        
        existing = cursor.fetchone()
        if existing:
            conn.rollback()
            raise Exception(f"Duplicate entry: {data['actor']} wearing {data['brand']} {data['model']} in {data['title']} already exists in the database.")
        
        # Check if character name already exists
        cursor.execute("SELECT character_id FROM characters WHERE character_name = ? LIMIT 1", 
                      (data['character'],))
        existing_char = cursor.fetchone()
      
        if existing_char:
            character_id = existing_char[0]
        else:
            cursor.execute("INSERT INTO characters (character_name) VALUES (?)",
                          (data['character'],))
            cursor.execute("SELECT last_insert_rowid()")
            character_id = cursor.fetchone()[0]
        
        # Insert relationship
        cursor.execute("""INSERT INTO film_actor_watch 
                         (film_id, actor_id, character_id, watch_id, narrative_role) 
                         VALUES (?, ?, ?, ?, ?)""",
                      (film_id, actor_id, character_id, watch_id, data['narrative']))
        
        conn.commit()
        return True
        
    except Exception as e:
        conn.rollback()
        raise Exception(f"{str(e)}")


@app.route('/api/add', methods=['POST'])
def add_entry():
    """Add a new entry to the database."""
    try:
        data = request.json
        entry_text = data.get('entry', '')
        narrative = data.get('narrative', 'Watch worn in film.')
        
        if not entry_text:
            return jsonify({'error': 'Entry text is required'}), 400
        
        parsed = parse_entry(entry_text)
        parsed['narrative'] = narrative
        
        conn = sqlite3.connect(DB_PATH)
        execute_insert(conn, parsed)
        conn.close()
        
        return jsonify({
            'success': True,
            'message': f"Successfully added: {parsed['actor']} wearing {parsed['brand']} {parsed['model']} in {parsed['title']} ({parsed['year']})",
            'data': parsed
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/query/actor/<actor_name>', methods=['GET'])
def query_actor(actor_name):
    """Query all watches worn by an actor."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("""
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
        """, (f'%{actor_name}%',))
        
        results = cursor.fetchall()
        conn.close()
        
        films = []
        for row in results:
            films.append({
                'title': row[0],
                'year': row[1],
                'brand': row[2],
                'model': row[3],
                'character': row[4],
                'narrative': row[5]
            })
        
        return jsonify({
            'success': True,
            'actor': actor_name,
            'count': len(films),
            'films': films
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/query/brand/<brand_name>', methods=['GET'])
def query_brand(brand_name):
    """Query all films featuring a brand."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("""
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
        """, (f'%{brand_name}%',))
        
        results = cursor.fetchall()
        conn.close()
        
        films = []
        for row in results:
            films.append({
                'title': row[0],
                'year': row[1],
                'actor': row[2],
                'model': row[3],
                'character': row[4],
                'narrative': row[5]
            })
        
        return jsonify({
            'success': True,
            'brand': brand_name,
            'count': len(films),
            'films': films
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/query/film/<film_title>', methods=['GET'])
def query_film(film_title):
    """Query all watches in a film."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("""
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
        """, (f'%{film_title}%',))
        
        results = cursor.fetchall()
        conn.close()
        
        watches = []
        for row in results:
            watches.append({
                'title': row[0],
                'year': row[1],
                'actor': row[2],
                'brand': row[3],
                'model': row[4],
                'character': row[5],
                'narrative': row[6]
            })
        
        return jsonify({
            'success': True,
            'film': film_title,
            'count': len(watches),
            'watches': watches
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/stats', methods=['GET'])
def get_stats():
    """Get database statistics."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("SELECT COUNT(*) FROM films")
        film_count = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM actors")
        actor_count = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM brands")
        brand_count = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM film_actor_watch")
        entry_count = cursor.fetchone()[0]
        
        cursor.execute("""
            SELECT b.brand_name, COUNT(*) as count
            FROM film_actor_watch faw
            JOIN watches w ON faw.watch_id = w.watch_id
            JOIN brands b ON w.brand_id = b.brand_id
            GROUP BY b.brand_name
            ORDER BY count DESC
            LIMIT 10
        """)
        top_brands = [{'brand': row[0], 'count': row[1]} for row in cursor.fetchall()]
        
        conn.close()
        
        return jsonify({
            'success': True,
            'stats': {
                'films': film_count,
                'actors': actor_count,
                'brands': brand_count,
                'entries': entry_count,
                'top_brands': top_brands
            }
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/')
def index():
    """Health check endpoint."""
    return jsonify({
        'status': 'running',
        'message': 'Film Watch Database API',
        'endpoints': [
            'POST /api/add',
            'GET /api/query/actor/<name>',
            'GET /api/query/brand/<name>',
            'GET /api/query/film/<title>',
            'GET /api/stats',
            'POST /api/cleanup-bad-brands',
            'POST /api/cleanup-duplicate-characters',
            'POST /api/cleanup-duplicate-actors',
            'DELETE /api/delete-brand/<id>'
        ]
    })


@app.route('/ui')
def serve_ui():
    return send_from_directory('.', 'web_interface.html')


@app.route('/api/find-similar/<actor_name>/<film_title>', methods=['GET'])
def find_similar(actor_name, film_title):
    """Find potentially duplicate entries for the same actor in the same film."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("""
            SELECT faw.faw_id, f.title, f.year, a.actor_name, 
                   c.character_name, b.brand_name, w.model_reference
            FROM film_actor_watch faw
            JOIN films f ON faw.film_id = f.film_id
            JOIN actors a ON faw.actor_id = a.actor_id
            JOIN characters c ON faw.character_id = c.character_id
            JOIN watches w ON faw.watch_id = w.watch_id
            JOIN brands b ON w.brand_id = b.brand_id
            WHERE a.actor_name LIKE ? AND f.title LIKE ?
            ORDER BY faw.faw_id
        """, (f'%{actor_name}%', f'%{film_title}%'))
        
        results = cursor.fetchall()
        conn.close()
        
        entries = []
        for row in results:
            entries.append({
                'id': row[0],
                'film': f"{row[1]} ({row[2]})",
                'actor': row[3],
                'character': row[4],
                'watch': f"{row[5]} {row[6]}"
            })
        
        return jsonify({
            'success': True,
            'count': len(entries),
            'entries': entries
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/delete-entry/<int:entry_id>', methods=['DELETE'])
def delete_entry(entry_id):
    """Delete a specific entry by ID."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("DELETE FROM film_actor_watch WHERE faw_id = ?", (entry_id,))
        
        if cursor.rowcount == 0:
            return jsonify({'error': 'Entry not found'}), 404
        
        conn.commit()
        conn.close()
        
        return jsonify({
            'success': True,
            'message': f'Deleted entry {entry_id}'
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/cleanup-duplicate-characters', methods=['POST'])
def cleanup_duplicate_characters():
    """Merge duplicate character records, keeping the oldest one."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        # Find all duplicate character names
        cursor.execute("""
            SELECT character_name, GROUP_CONCAT(character_id) as ids, COUNT(*) as count
            FROM characters
            GROUP BY character_name
            HAVING count > 1
        """)
        
        duplicates = cursor.fetchall()
        total_merged = 0
        
        for char_name, ids_str, count in duplicates:
            ids = [int(x) for x in ids_str.split(',')]
            keep_id = min(ids)  # Keep the oldest (lowest ID)
            delete_ids = [x for x in ids if x != keep_id]
            
            # Update all references to point to the kept ID
            for old_id in delete_ids:
                cursor.execute("""
                    UPDATE film_actor_watch 
                    SET character_id = ? 
                    WHERE character_id = ?
                """, (keep_id, old_id))
                
                # Delete the duplicate character record
                cursor.execute("DELETE FROM characters WHERE character_id = ?", (old_id,))
                
            total_merged += len(delete_ids)
            
        conn.commit()
        conn.close()
        
        return jsonify({
            'success': True,
            'message': f'Merged {total_merged} duplicate characters into {len(duplicates)} unique characters'
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/cleanup-duplicate-actors', methods=['POST'])
def cleanup_duplicate_actors():
    """Merge duplicate actor records, keeping the oldest one."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        # Find all duplicate actor names
        cursor.execute("""
            SELECT actor_name, GROUP_CONCAT(actor_id) as ids, COUNT(*) as count
            FROM actors
            GROUP BY actor_name
            HAVING count > 1
        """)
        
        duplicates = cursor.fetchall()
        total_merged = 0
        
        for actor_name, ids_str, count in duplicates:
            ids = [int(x) for x in ids_str.split(',')]
            keep_id = min(ids)  # Keep the oldest (lowest ID)
            delete_ids = [x for x in ids if x != keep_id]
            
            # Update all references to point to the kept ID
            for old_id in delete_ids:
                cursor.execute("""
                    UPDATE film_actor_watch 
                    SET actor_id = ? 
                    WHERE actor_id = ?
                """, (keep_id, old_id))
                
                # Delete the duplicate actor record
                cursor.execute("DELETE FROM actors WHERE actor_id = ?", (old_id,))
                
            total_merged += len(delete_ids)
            
        conn.commit()
        conn.close()
        
        return jsonify({
            'success': True,
            'message': f'Merged {total_merged} duplicate actors into {len(duplicates)} unique actors'
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/cleanup-bad-brands', methods=['POST'])
def cleanup_bad_brands():
    """Fix entries where brand is incorrectly set to 'a', 'an', 'A', or 'An'."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        # Find all watches with bad brand names
        cursor.execute("""
            SELECT w.watch_id, w.brand_id, w.model_reference, b.brand_name
            FROM watches w
            JOIN brands b ON w.brand_id = b.brand_id
            WHERE b.brand_name IN ('a', 'an', 'A', 'An')
        """)
        
        bad_watches = cursor.fetchall()
        fixed_count = 0
        
        for watch_id, old_brand_id, model_ref, old_brand in bad_watches:
            # Try to extract the real brand from the model reference
            # The model_ref should start with the actual brand name
            model_parts = model_ref.split(maxsplit=1)
            
            if len(model_parts) >= 1:
                # First word of model is likely the brand
                new_brand = model_parts[0]
                new_model = model_parts[1] if len(model_parts) > 1 else model_parts[0]
                
                # Insert or get the correct brand
                cursor.execute("INSERT OR IGNORE INTO brands (brand_name) VALUES (?)", (new_brand,))
                cursor.execute("SELECT brand_id FROM brands WHERE brand_name = ?", (new_brand,))
                new_brand_id = cursor.fetchone()[0]
                
                # Update the watch with correct brand and model
                cursor.execute("""
                    UPDATE watches 
                    SET brand_id = ?, model_reference = ?
                    WHERE watch_id = ?
                """, (new_brand_id, new_model, watch_id))
                
                fixed_count += 1
        
        # Clean up orphaned 'a'/'an' brands if they have no watches
        cursor.execute("""
            DELETE FROM brands 
            WHERE brand_name IN ('a', 'an', 'A', 'An')
            AND brand_id NOT IN (SELECT DISTINCT brand_id FROM watches)
        """)
        
        conn.commit()
        conn.close()
        
        return jsonify({
            'success': True,
            'message': f'Fixed {fixed_count} watches with bad brand names',
            'fixed_count': fixed_count
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/delete-brand/<int:brand_id>', methods=['DELETE'])
def delete_brand(brand_id):
    """Delete a brand if it has no associated watches."""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        # Check if any watches use this brand
        cursor.execute("SELECT COUNT(*) FROM watches WHERE brand_id = ?", (brand_id,))
        watch_count = cursor.fetchone()[0]
        
        if watch_count > 0:
            return jsonify({
                'error': f'Cannot delete brand - {watch_count} watches are using it'
            }), 400
        
        cursor.execute("DELETE FROM brands WHERE brand_id = ?", (brand_id,))
        conn.commit()
        conn.close()
        
        return jsonify({
            'success': True,
            'message': f'Deleted brand {brand_id}'
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 400


if __name__ == '__main__':
    print("=" * 60)
    print("🎬 Film Watch Database API Server")
    print("=" * 60)
    print("Server starting on http://127.0.0.1:5000")
    print("\nAvailable endpoints:")
    print("  POST   /api/add                        - Add new entry")
    print("  GET    /api/query/actor/NAME           - Query by actor")
    print("  GET    /api/query/brand/NAME           - Query by brand")
    print("  GET    /api/query/film/TITLE           - Query by film")
    print("  GET    /api/stats                      - Get statistics")
    print("  POST   /api/cleanup-bad-brands         - Fix 'a'/'an' brand entries")
    print("  POST   /api/cleanup-duplicate-actors   - Merge duplicate actors")
    print("  POST   /api/cleanup-duplicate-characters - Merge duplicate characters")
    print("  DELETE /api/delete-brand/<id>          - Delete unused brand")
    print("  DELETE /api/delete-entry/<id>          - Delete entry")
    print("  GET    /ui                             - Web interface")
    print("\nPress CTRL+C to stop the server")
    print("=" * 60)
    
    app.run(debug=True, port=5000, host='127.0.0.1')