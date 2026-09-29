TIER_TO_SCORE = {
    'S': 100,
    'A+': 92,
    'A': 85,
    'B+': 78,
    'B': 70,
    'C': 60,
    'D': 50,
    'F': 30,
}

def score_to_grade(score):
    if score >= 97:
        return 'Z+'
    elif score >= 90:
        return 'Z'
    elif score >= 85:
        return 'A+'
    elif score >= 80:
        return 'A'
    elif score >= 75:
        return 'B+'
    elif score >= 70:
        return 'B'
    elif score >= 60:
        return 'C'
    elif score >= 50:
        return 'D'
    else:
        return 'F'