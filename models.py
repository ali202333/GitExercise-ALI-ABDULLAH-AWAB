from datetime import datetime
from flask_sqlalchemy import SQLAlchemy

db = SQLAlchemy()

# Users Table
class User(db.Model):
    __tablename__ = 'users'

    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(50), unique=True, nullable=False)
    email = db.Column(db.String(100), unique=True, nullable=False)
    password_hash = db.Column(db.String(255), nullable=False)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    bookmarks = db.relationship('Bookmark', backref='user', cascade='all, delete-orphan', lazy=True)
    ratings = db.relationship('Rating', backref='user', cascade='all, delete-orphan', lazy=True)
    reviews = db.relationship('Review', backref='user', cascade='all, delete-orphan', lazy=True)

    def __repr__(self):
        return f'<User {self.username}>'


# Titles Table (Movies, Shows, Miscellaneous Works)
class Title(db.Model):
    __tablename__ = 'titles'

    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(150), nullable=False)
    media_type = db.Column(db.String(50), default='movie')
    director = db.Column(db.String(100), nullable=True)
    release_year = db.Column(db.Integer, nullable=True)
    poster_url = db.Column(db.String(255), nullable=True)
    synopsis = db.Column(db.Text, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    bookmarks = db.relationship('Bookmark', backref='title', cascade='all, delete-orphan', lazy=True)
    critic_links = db.relationship('CriticLink', backref='title', cascade='all, delete-orphan', lazy=True)
    ratings = db.relationship('Rating', backref='title', cascade='all, delete-orphan', lazy=True)
    reviews = db.relationship('Review', backref='title', cascade='all, delete-orphan', lazy=True)

    def __repr__(self):
        return f'<Title {self.name} ({self.release_year})>'


# Bookmarks Table
class Bookmark(db.Model):
    __tablename__ = 'bookmarks'

    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id', ondelete='CASCADE'), nullable=False)
    title_id = db.Column(db.Integer, db.ForeignKey('titles.id', ondelete='CASCADE'), nullable=False)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    __table_args__ = (
        db.UniqueConstraint('user_id', 'title_id', name='unique_user_title_bookmark'),
    )

    def __repr__(self):
        return f'<Bookmark User:{self.user_id} Title:{self.title_id}>'


# Critic Links Table
class CriticLink(db.Model):
    __tablename__ = 'critic_links'

    id = db.Column(db.Integer, primary_key=True)
    title_id = db.Column(db.Integer, db.ForeignKey('titles.id', ondelete='CASCADE'), nullable=False)
    source_name = db.Column(db.String(100), nullable=False)
    url = db.Column(db.String(500), nullable=False)
    excerpt = db.Column(db.Text, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def __repr__(self):
        return f'<CriticLink {self.source_name} -> Title:{self.title_id}>'


# Ratings Table
class Rating(db.Model):
    __tablename__ = 'ratings'

    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id', ondelete='CASCADE'), nullable=False)
    title_id = db.Column(db.Integer, db.ForeignKey('titles.id', ondelete='CASCADE'), nullable=False)

    mise_en_scene = db.Column(db.Float, default=0.0)
    cinematography = db.Column(db.Float, default=0.0)
    sound_design = db.Column(db.Float, default=0.0)
    narrative_editing = db.Column(db.Float, default=0.0)

    final_grade = db.Column(db.String(5), nullable=True)

    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    __table_args__ = (
        db.UniqueConstraint('user_id', 'title_id', name='unique_user_title_rating'),
    )

    def __repr__(self):
        return f'<Rating User:{self.user_id} Title:{self.title_id} Grade:{self.final_grade}>'


# Reviews Table
class Review(db.Model):
    __tablename__ = 'reviews'

    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id', ondelete='CASCADE'), nullable=False)
    title_id = db.Column(db.Integer, db.ForeignKey('titles.id', ondelete='CASCADE'), nullable=False)
    headline = db.Column(db.String(200), nullable=True)
    body = db.Column(db.Text, nullable=False)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    def __repr__(self):
        return f'<Review User:{self.user_id} Title:{self.title_id}>'