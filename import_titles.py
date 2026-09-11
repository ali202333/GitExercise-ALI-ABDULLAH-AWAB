import json
from app import app
from models import db, Title

# Load the scraped data
with open('output.json', 'r', encoding='utf-8') as f:
    movies = json.load(f)

with app.app_context():
    added = 0
    skipped = 0

    for movie in movies:
        title_name = movie.get('title')

        if not title_name:
            skipped += 1
            continue

        # Avoid inserting the same movie twice if this script runs more than once
        existing = Title.query.filter_by(name=title_name).first()
        if existing:
            skipped += 1
            continue

        # Genres come as a list -> join into a comma-separated string
        genre_list = movie.get('genres', [])
        genres_str = ', '.join(genre_list) if genre_list else None

        # Rating comes as a string and I converted to float
        raw_rating = movie.get('rating')
        try:
            imdb_rating = float(raw_rating) if raw_rating else None
        except ValueError:
            imdb_rating = None

        new_title = Title(
            name=title_name,
            media_type='movie',
            genres=genres_str,
            imdb_rating=imdb_rating,
            imdb_url=movie.get('movie_url')
        )

        db.session.add(new_title)
        added += 1

    db.session.commit()
    print(f"Import complete. Added: {added}, Skipped (duplicates/missing title): {skipped}") 