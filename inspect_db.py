import sqlite3

db_path = "event56th.sqlite"
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# 「オムそば」で完全一致する全レコード
query = "SELECT event_name, circle_name, place_detail, event_date FROM event56th WHERE event_name = 'オムそば'"
cursor.execute(query)
rows = cursor.fetchall()

print("\nExact 'Omu-soba' Records:")
for row in rows:
    print(f"[{row[0]}] by [{row[1]}] at [{row[2]}] on [{row[3]}]")

conn.close()
