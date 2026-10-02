from pathlib import Path

from flask import Flask, jsonify, render_template, request, redirect, url_for, session, send_from_directory
from werkzeug.security import generate_password_hash, check_password_hash
from models import db, User, Title, Bookmark, CriticLink, Rating, Review

app = Flask(__name__)

app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///scorebox.db'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
app.config['SECRET_KEY'] = 'dev-secret-key-change-later'  # needed for sessions to work

db.init_app(app)

with app.app_context():
    db.create_all()


@app.route('/')
def home():
    return render_template('proto2.html')


@app.route('/signup', methods=['GET', 'POST'])
def signup():
    if request.method == 'POST':
        name = request.form.get('name')
        email = request.form.get('email')
        password = request.form.get('password')

        if not name or not email or not password:
            return redirect(url_for('signup', error='required'))

        if password != request.form.get('confirm_password'):
            return redirect(url_for('signup', error='mismatch'))

        existing = User.query.filter(
            (User.username == name) | (User.email == email)
        ).first()
        if existing:
            return redirect(url_for('signup', error='exists'))

        new_user = User(
            username=name,
            email=email,
            password_hash=generate_password_hash(password)
        )
        db.session.add(new_user)
        db.session.commit()

        return redirect(url_for('login', registered=1))

    return render_template('signup.html')


@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        email = request.form.get('email')
        password = request.form.get('password')

        user = User.query.filter_by(email=email).first()

        if not user or not check_password_hash(user.password_hash, password):
            return redirect(url_for('login', error='credentials'))

        session['user_id'] = user.id
        session['username'] = user.username
        return redirect(url_for('home'))

    return render_template('signin.html')


@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('login'))


# API ROUTE
@app.route('/api/movies')
def api_movies():
    titles = Title.query.all()
    result = []
    for t in titles:
        latest_rating = t.ratings[0] if t.ratings else None
        result.append({
            'id': t.id,
            'title': t.name,
            'genres': t.genres.split(', ') if t.genres else [],
            'imdb_rating': t.imdb_rating,
            'movie_url': t.imdb_url,
            'final_grade': latest_rating.final_grade if latest_rating else None
        })
    return {'movies': result}


@app.route('/movie')
def movie():
    return render_template('movie.html')


@app.route('/privacy')
def privacy():
    return render_template('privacy.html')


@app.route('/terms')
def terms():
    return render_template('terms.html')


@app.route('/data-attribution')
def data_attribution():
    return render_template('data_attribution.html')


@app.route('/cookies')
def cookies():
    return render_template('cookies.html')


@app.route('/api/session')
def api_session():
    if 'user_id' not in session:
        return jsonify(user=None)
    return jsonify(user={'id': session['user_id'], 'username': session['username']})


@app.route('/api/catalog')
def api_catalog():
    return send_from_directory(app.root_path, 'output_updated_reviews_filled.json')


@app.route('/assets/<path:filename>')
def assets(filename):
    if Path(filename).suffix.lower() not in {'.png', '.jpg', '.jpeg', '.gif', '.ogg'}:
        return '', 404
    return send_from_directory(app.root_path, filename)


if __name__ == '__main__':
    app.run(debug=True)
