import sqlite3, re, os, sys, argparse

# \u30d1\u30b9\u306f\u30b3\u30de\u30f3\u30c9\u30e9\u30a4\u30f3\u5f15\u6570\u3067\u6307\u5b9a\u3059\u308b\uff08\u30c7\u30d5\u30a9\u30eb\u30c8\uff1a\u30b9\u30af\u30ea\u30d7\u30c8\u3068\u540c\u3058\u30c7\u30a3\u30ec\u30af\u30c8\u30ea\uff09
_here = os.path.dirname(os.path.abspath(__file__))
parser = argparse.ArgumentParser(description="MySQL dump \u2192 SQLite \u5909\u63db")
parser.add_argument("sql_file", nargs="?", help="\u5165\u529b SQL \u30d5\u30a1\u30a4\u30eb\u30d1\u30b9")
parser.add_argument("--db",    default=os.path.join(_here, "event56th.sqlite"), help="SQLite \u51fa\u529b\u30d1\u30b9")
args = parser.parse_args()

if not args.sql_file:
    parser.print_help()
    sys.exit(1)

SQL_FILE = args.sql_file
DB_FILE  = args.db

# --- SQLite DB を作成 ---
if os.path.exists(DB_FILE):
    os.remove(DB_FILE)

conn = sqlite3.connect(DB_FILE)
cur  = conn.cursor()

# --- テーブル作成（必要カラムのみ抽出） ---
cur.execute("""
CREATE TABLE event56th (
    id           INTEGER,
    event_id     INTEGER,
    cancel       TEXT,
    event_name   TEXT,
    circle_name  TEXT,
    place_rough  TEXT,
    place_detail TEXT,
    event_date   TEXT,
    event_style  TEXT,
    genre_first_main  TEXT,
    genre_first_sub   TEXT
)
""")

# --- INSERT 文を正規表現で抽出 ---
with open(SQL_FILE, encoding="utf-8") as f:
    content = f.read()

# カラム順（INSERT INTO の列順に合わせる）
col_order = [
    "event_style","style_number","id","cancel","event_id","event_name",
    "circle_name","genre_first_main","genre_first_sub","genre_second_main",
    "genre_second_sub","event_word1","event_word2","place_rough","place_detail",
    "rain_support","event_date","day1_time","day1_start1","day1_end1","day1_place1",
    "day1_start2","day1_end2","day1_place2","day2_time","day2_start1","day2_end1",
    "day2_place1","day2_start2","day2_end2","day2_place2","day3_time","day3_start1",
    "day3_end1","day3_place1","day3_start2","day3_end2","day3_place2",
    "introduction_panph","introduction_web","introduction_circle",
    "link_x","link_instagram","link_youtube","link_facebook","link_web",
    "considerations","isset_other",
    "event_relate1_id","event_relate1_name","event_relate2_id","event_relate2_name",
    "event_relate3_id","event_relate3_name",
    "guest","food_item1","food_item2","search_key","lat","lng","map_no","map_area"
]

needed = {"event_style","id","cancel","event_id","event_name","circle_name",
          "genre_first_main","genre_first_sub","place_rough","place_detail","event_date"}

# VALUES ブロックを抽出
rows_inserted = 0
pattern = re.compile(r"\((?:[^()]*|\(.*?\))*\)", re.DOTALL)
values_section = re.findall(r"VALUES\s*(.*?)(?:;\s*$|(?=INSERT))", content, re.DOTALL | re.MULTILINE)

for block in values_section:
    # 各行タプルを分割（先頭・末尾のカッコを除外）
    rows = re.findall(r"\(([^()]*(?:\([^()]*\)[^()]*)*)\)", block, re.DOTALL)
    for row in rows:
        # CSV 風に分割（クォート内のカンマを無視）
        vals = []
        current = ""
        in_quote = False
        for ch in row:
            if ch == "'" and not in_quote:
                in_quote = True
            elif ch == "'" and in_quote:
                in_quote = False
            elif ch == "," and not in_quote:
                vals.append(current.strip().strip("'"))
                current = ""
                continue
            current += ch
        vals.append(current.strip().strip("'"))

        if len(vals) < len(col_order):
            continue

        d = dict(zip(col_order, vals))
        try:
            cur.execute("""
                INSERT INTO event56th
                (id, event_id, cancel, event_name, circle_name,
                 place_rough, place_detail, event_date, event_style,
                 genre_first_main, genre_first_sub)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            """, (
                int(d["id"]) if d["id"].isdigit() else 0,
                int(d["event_id"]) if d["event_id"].isdigit() else 0,
                d["cancel"],
                d["event_name"],
                d["circle_name"],
                d["place_rough"],
                d["place_detail"],
                d["event_date"],
                d["event_style"],
                d["genre_first_main"],
                d["genre_first_sub"],
            ))
            rows_inserted += 1
        except Exception as e:
            print(f"Skip row: {e}")

conn.commit()
conn.close()
print(f"✅ Done: {rows_inserted} 件のイベントを {DB_FILE} に保存しました")
