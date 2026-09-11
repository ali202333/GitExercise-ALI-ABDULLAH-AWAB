from flask import Flask
from models import db, User, Title, Bookmark, CriticLink, Rating, Review

app = Flask(__name__)

app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///scorebox.db'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

db.init_app(app)

with app.app_context():
    db.create_all()

@app.route('/')
def home():
    return "S-CoreBox database tables initialized successfully!"

if __name__ == '__main__':
    app.run(debug=True)