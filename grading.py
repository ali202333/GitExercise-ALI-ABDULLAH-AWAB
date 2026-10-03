TIER_TO_SCORE = {
    'Z+': 10.0,
    'Z': 9.2,
    'S': 8.5,
    'A': 7.8,
    'B': 7.0,
    'C': 6.0,
    'D': 5.0,
    'E': 4.0,
    'F': 3.0
}

def score_to_grade(score):
    if score >= 9.7:
        return 'Z+'
    elif score >= 90:
        return 'Z'
    elif score >= 8.5:
        return 'S'
    elif score >= 8.0:
        return 'A'
    elif score >= 7.5:
        return 'B'
    elif score >= 7.0:
        return 'C'
    elif score >= 6.0:
        return 'D'
    elif score >= 50:
        return 'E'
    else:
        return 'F'