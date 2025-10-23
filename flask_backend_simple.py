from flask import Flask, request, jsonify
import sqlite3

app = Flask(__name__)

DB_PATH = 'film_watches.db'

@app.after_request
def after_request(response):
    response.headers.add('Access-Control-Allow-Origin', '*')
    response.headers.add('Access-Control-Allow-Headers', 'Content-Type')
    response.headers.add('Access-Control-Allow-Methods', 'GET,POST,OPTIONS')
    return response

@app.route('/')
def home():
    return '''
    <html>
    <body>
        <h1>Film Watch Database</h1>
        <p>Server is running!</p>
        <form action="/add" method="post">
            <input type="text" name="entry" placeholder="Enter film-watch data" style="width:500px"><br>
            <button type="submit">Add Entry</button>
        </form>
    </body>
    </html>
    '''

@app.route('/add', methods=['POST'])
def add_simple():
    entry = request.form.get('entry')
    return f'<h1>Received: {entry}</h1><a href="/">Back</a>'

if __name__ == '__main__':
    app.run(debug=True, port=5000, host='127.0.0.1')