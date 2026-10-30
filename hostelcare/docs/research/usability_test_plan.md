# Usability test plan and recording sheet

Run the real system (not slides) with real users. Use a throwaway database so test complaints do not mix with real ones: start the app with `HC_SQLITE_PATH=/tmp/usability.sqlite`.

## Participants
Test with at least 5 students, 1 or more wardens and 1 admin or senior person. Record role only, never names.

## Tasks (read each aloud; do not help unless they are stuck for over a minute)
| ID | Role | Task |
|---|---|---|
| T1 | Student | Create an account and raise a complaint about a leaking tap, with a photo |
| T2 | Student | Find out the current status of that complaint |
| T3 | Warden | Find the most urgent open complaint |
| T4 | Warden | Assign a staff member and mark the complaint in progress |
| T5 | Warden | Resolve the complaint with a note |
| T6 | Student | Confirm the fix and rate it |
| T7 | Admin | Find which category has the most complaints |
| T8 | Admin | Download the complaint list as CSV |

## Recording sheet (one row per participant per task)
| Participant | Task | Completed without help? (Y/N) | Seconds | Errors or wrong clicks | What they said or where they hesitated |
|---|---|---|---|---|---|

Time with a phone stopwatch from when they start to when they say they are finished.

## System Usability Scale (give right after the tasks)
Each statement is rated 1 (strongly disagree) to 5 (strongly agree).
1. I think I would like to use this system frequently.
2. I found the system unnecessarily complex.
3. I thought the system was easy to use.
4. I think I would need the support of a technical person to use this system.
5. I found the various functions well integrated.
6. I thought there was too much inconsistency in this system.
7. I imagine most people would learn to use this system very quickly.
8. I found the system very cumbersome to use.
9. I felt very confident using the system.
10. I needed to learn a lot of things before I could get going.

Enter each participant's ten answers as one line in `docs/research/sus_responses.csv` (columns q1..q10) and run:

    python3 docs/research/sus_score.py docs/research/sus_responses.csv

The script prints each participant's score and the mean. Report that output as-is.

## After testing
List every problem you saw, how many participants hit it, and what you changed. Only report changes you actually made.
