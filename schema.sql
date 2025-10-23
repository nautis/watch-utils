DROP TABLE IF EXISTS film_actor_watch;
DROP TABLE IF EXISTS actors;
DROP TABLE IF EXISTS characters;
DROP TABLE IF EXISTS watches;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS films;

CREATE TABLE films (
    film_id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    year INTEGER NOT NULL,
    UNIQUE(title, year)
);

CREATE TABLE brands (
    brand_id INTEGER PRIMARY KEY AUTOINCREMENT,
    brand_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE watches (
    watch_id INTEGER PRIMARY KEY AUTOINCREMENT,
    brand_id INTEGER NOT NULL,
    model_reference VARCHAR(255) NOT NULL,
    verification_level VARCHAR(50),
    FOREIGN KEY (brand_id) REFERENCES brands(brand_id),
    UNIQUE(brand_id, model_reference)
);

CREATE TABLE actors (
    actor_id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_name VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE characters (
    character_id INTEGER PRIMARY KEY AUTOINCREMENT,
    character_name VARCHAR(255) NOT NULL
);

CREATE TABLE film_actor_watch (
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