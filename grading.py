TIER_TO_SCORE = {
    'Z+': 100,
    'Z': 92,
    'S': 85,
    'A': 78,
    'B': 70,
    'C': 60,
    'D': 50,
    'E': 40,
    'F': 30
}

def score_to_grade(score):
    if score >= 97:
        return 'Z+'
    elif score >= 90:
        return 'Z'
    elif score >= 85:
        return 'S'
    elif score >= 80:
        return 'A'
    elif score >= 75:
        return 'B'
    elif score >= 70:
        return 'C'
    elif score >= 60:
        return 'D'
    elif score >= 50:
        return 'E'
    else:
        return 'F'