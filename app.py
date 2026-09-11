from flask import Flask, render_template, request, redirect, url_for, session
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
            return render_template('signup.html', error='All fields are required.')

        existing = User.query.filter(
            (User.username == name) | (User.email == email)
        ).first()
        if existing:
            return render_template('signup.html', error='Username or email already taken.')

        new_user = User(
            username=name,
            email=email,
            password_hash=generate_password_hash(password)
        )
        db.session.add(new_user)
        db.session.commit()

        return redirect(url_for('login'))

    return render_template('signup.html')


@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        email = request.form.get('email')
        password = request.form.get('password')

        user = User.query.filter_by(email=email).first()

        if not user or not check_password_hash(user.password_hash, password):
            return render_template('signin.html', error='Invalid email or password.')

        session['user_id'] = user.id
        session['username'] = user.username
        return redirect(url_for('home'))

    return render_template('signin.html')


@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('login'))


if __name__ == '__main__':
    app.run(debug=True)
