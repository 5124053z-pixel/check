from google import genai
import os, json
from dotenv import load_dotenv

load_dotenv()
client = genai.Client(api_key=os.getenv('GEMINI_API_KEY'))

text = 'こんにちわ！シュミレーションの結果です。'
prompt = """あなたはプロの日本語校正者です。
以下テキストから明らかなタイポと変換ミスのみを抽出しJSONで返してください。
バックティックは不要です。
形式: [{"word":"間違い","replacement":"修正","reason":"理由"}]

テキスト: """ + text

try:
    resp = client.models.generate_content(model='gemini-2.5-flash', contents=prompt)
    print('SUCCESS:', resp.text)
except Exception as e:
    print('ERROR:', type(e).__name__, str(e)[:300])
