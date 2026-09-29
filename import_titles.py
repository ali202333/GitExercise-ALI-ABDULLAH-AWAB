import json
from app import app
from models import db, Title, Rating, CriticLink, User
from grading import TIER_TO_SCORE, score_to_grade
from werkzeug.security import generate_password_hash

DATA_FILE = 'output_updated_reviews_filled.json'

with open(DATA_FILE, 'r', encoding='utf-8') as f:
    movies = json.load(f)

with app.app_context():

    # Ensure a reserved system account exists to own seeded ratings
    system_user = User.query.filter_by(username='scorebox_system').first()
    if not system_user:
        system_user = User(
            username='scorebox_system',
            email='system@scorebox.internal',
            password_hash=generate_password_hash('not-a-real-login')
        )
        db.session.add(system_user)
        db.session.commit()

    added, skipped = 0, 0

    for movie in movies:
        title_name = movie.get('title')
        if not title_name:
            skipped += 1
            continue

        existing = Title.query.filter_by(name=title_name).first()
        if existing:
            skipped += 1
            continue

        genre_list = movie.get('genres', [])
        genres_str = ', '.join(genre_list) if genre_list else None

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
        db.session.flush()  # so new_title.id is available immediately below

        reviews_block = movie.get('reviews')
        if reviews_block:
            tier_scores = reviews_block.get('ratings', {})

            def score_for(key):
                letter = tier_scores.get(key)
                return TIER_TO_SCORE.get(letter, 0.0)

            mise = score_for('mise-en-scene')
            cine = score_for('cinematography')
            sound = score_for('sound')
            narrative = score_for('narrative')
            editing = score_for('editing')

            avg_score = (mise + cine + sound + narrative + editing) / 5

            new_rating = Rating(
                user_id=system_user.id,
                title_id=new_title.id,
                mise_en_scene=mise,
                cinematography=cine,
                sound_design=sound,
                narrative=narrative,
                editing=editing,
                final_grade=score_to_grade(avg_score)
            )
            db.session.add(new_rating)

            new_critic_link = CriticLink(
                title_id=new_title.id,
                source_name='Aggregated Critic Consensus',
                url=movie.get('movie_url'),
                excerpt=reviews_block.get('final_review'),
                review_count=reviews_block.get('review_count')
            )
            db.session.add(new_critic_link)

        added += 1

    db.session.commit()
    print(f"Import complete. Added: {added}, Skipped: {skipped}")