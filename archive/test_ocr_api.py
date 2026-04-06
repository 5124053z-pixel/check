import requests
import os

url = "http://127.0.0.1:8000/ocr"
# ダミーの画像ファイルを作成（1x1の極小画像）
dummy_image = b'\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc\xf8\xff\xff?\x00\x05\xfe\x02\xfe\x0dcG\x04\x00\x00\x00\x00IEND\xaeB`\x82'
with open("test.png", "wb") as f:
    f.write(dummy_image)

files = {"file": ("test.png", open("test.png", "rb"), "image/png")}
try:
    response = requests.post(url, files=files)
    print(f"Status Code: {response.status_code}")
    print(f"Response: {response.text}")
except Exception as e:
    print(f"Connection Error: {e}")
finally:
    if os.path.exists("test.png"):
        os.remove("test.png")
