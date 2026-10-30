#!/usr/bin/env python3
"""Compute System Usability Scale scores from a CSV of real responses.

CSV header: q1,q2,...,q10 (values 1 to 5), one row per participant.
Usage: python3 sus_score.py responses.csv
"""
import csv
import sys


def score(row):
    total = 0
    for i in range(1, 11):
        v = int(row[f"q{i}"])
        if not 1 <= v <= 5:
            raise ValueError(f"q{i} must be 1 to 5, got {v}")
        total += (v - 1) if i % 2 == 1 else (5 - v)
    return total * 2.5


def main(path):
    with open(path, newline="", encoding="utf-8") as fh:
        rows = list(csv.DictReader(fh))
    if not rows:
        print("No responses yet. Add one row per participant.")
        return 1
    scores = []
    for n, row in enumerate(rows, 1):
        s = score(row)
        scores.append(s)
        print(f"Participant {n}: {s:.1f}")
    mean = sum(scores) / len(scores)
    print(f"\nParticipants: {len(scores)}")
    print(f"Mean SUS score: {mean:.1f} (68 is the commonly cited average)")
    print(f"Lowest: {min(scores):.1f}   Highest: {max(scores):.1f}")
    return 0


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(sys.argv[1]))
