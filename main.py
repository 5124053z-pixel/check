from fastapi import FastAPI, HTTPException, UploadFile, File
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import sudachipy
import os
os.environ["KMP_DUPLICATE_LIB_OK"] = "TRUE"
import json
import io
import numpy as np
from PIL import Image
import fitz  # PyMuPDF
import easyocr
from dotenv import load_dotenv

load_dotenv()

app = FastAPI(title="Hybrid Proofreader API")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Sudachi Init
tokenizer_obj = sudachipy.Dictionary().create()
mode = sudachipy.SplitMode.C

# EasyOCR は初回起動を高速化するため遅延読み込み（Lazy Load）にする
ocr_reader = None

def get_ocr_reader():
    global ocr_reader
    if ocr_reader is None:
        print("[INFO] Initializing EasyOCR Reader (Lazy Load)...")
        # CPUモードで安定動作。初回のみモデルDLが発生します。
        ocr_reader = easyocr.Reader(['ja', 'en'], gpu=False)
        print("[INFO] EasyOCR Reader Ready.")
    return ocr_reader
# Gemini Init
api_key = os.getenv("GEMINI_API_KEY")
gemini_client = None
if api_key:
    try:
        from google import genai
        gemini_client = genai.Client(api_key=api_key)
        print("[INFO] Gemini API initialized (google-genai)")
    except Exception as e:
        print(f"[WARN] Gemini init error: {e}")

class TextRequest(BaseModel):
    text: str
    ai_text: str = None
    use_ai: bool = True

def preprocess_image(content: bytes, max_size=1600) -> bytes:
    """画像を読み込み、長辺を max_size 程度にリサイズする"""
    try:
        img = Image.open(io.BytesIO(content))
        w, h = img.size
        if max(w, h) > max_size:
            scale = max_size / max(w, h)
            new_size = (int(w * scale), int(h * scale))
            img = img.resize(new_size, Image.LANCZOS)
            output = io.BytesIO()
            # フォーマットを維持 (ない場合はJPEG)
            fmt = img.format if img.format else "JPEG"
            img.save(output, format=fmt)
            return output.getvalue()
        return content
    except Exception as e:
        print(f"[WARN] Image resize error: {e}")
        return content

@app.post("/analyze")
def analyze_text(req: TextRequest):
    # 強制デバッグ：リクエストの中身を真っ先に表示
    print(f"[CHECK] Request received: text='{req.text[:20]}', use_ai={req.use_ai}")
    print(f"[CHECK] Gemini client status: {'Initialized' if gemini_client else 'NOT INITIALIZED'}")
    if not req.text:
        return {
            "tokens": [],
            "ai_suggestions": [],
            "ai_status": {
                "requested": bool(req.use_ai),
                "available": gemini_client is not None,
                "executed": False,
                "suggestions_count": 0,
                "error": None,
                "outcome": "disabled" if not req.use_ai else "empty_text"
            }
        }
    
    # 1. Sudachi Parse
    try:
        tokens = tokenizer_obj.tokenize(req.text, mode)
        token_result = []
        for t in tokens:
            token_result.append({
                "surface": t.surface(),
                "base_form": t.dictionary_form(),
                "pos": t.part_of_speech()
            })
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Sudachi error: {str(e)}")

    # 2. Gemini AI Analysis
    ai_suggestions = []
    ai_status = {
        "requested": bool(req.use_ai),
        "available": gemini_client is not None,
        "executed": False,
        "suggestions_count": 0,
        "error": None,
        "outcome": "disabled" if not req.use_ai else "pending"
    }

    if req.use_ai and gemini_client:
        ai_target = req.ai_text if req.ai_text is not None else req.text
        # 軽量で制限に引っかかりにくい gemini-2.5-flash を使用
        model_id = "gemini-2.5-flash"
        prompt = f"""あなたはプロの日本語校正者です。
入力テキストから以下の種類の間違いを見つけてください：

1. **打ち間違い（タイポ）**: キーの打ち間違い、文字の脱落や余分な文字
2. **変換ミス**: 同音異義語の誤変換、ひらがな/カタカナの変換ミス
   - 例: 「でた通信」→「データ通信」、「以外と」→「意外と」
3. **送り仮名の間違い**: 正しくない送り仮名
4. **カタカナ語の誤り**: 「シュミレーション」→「シミュレーション」

注意: 文体の好みやスタイルの違いは含めないでください。明らかな誤りのみ指摘してください。

返答は以下のJSON配列のみを出力してください。コードブロック（```）や余計な説明は不要です。
修正箇所がない場合は空の配列 [] だけを返してください。

[
  {{"word": "原文の間違い箇所（テキスト内の文字列と完全一致させること）", "replacement": "修正案", "reason": "簡潔な理由"}}
]

入力テキスト:
{ai_target}"""

        try:
            ai_status["executed"] = True
            print(f"[DEBUG] === Gemini Request ===")
            print(f"[DEBUG] Model: {model_id}")
            print(f"[DEBUG] Input text ({len(ai_target)} chars): {ai_target[:200]}{'...' if len(ai_target) > 200 else ''}")
            response = gemini_client.models.generate_content(
                model=model_id,
                contents=prompt
            )
            resp_txt = response.text.strip()
            print(f"[DEBUG] === Gemini Raw Response ===")
            print(f"[DEBUG] {resp_txt}")
            # マークダウンブロックの除去
            if "```json" in resp_txt:
                resp_txt = resp_txt.split("```json")[1].split("```")[0].strip()
            elif "```" in resp_txt:
                resp_txt = resp_txt.split("```")[1].split("```")[0].strip()
            
            if resp_txt and resp_txt != "[]":
                parsed = json.loads(resp_txt)
                ai_suggestions = parsed if isinstance(parsed, list) else []
                print(f"[INFO] AI suggestions: {len(ai_suggestions)}")
                for s in ai_suggestions:
                    print(f"[DEBUG]   → {s.get('word','')} => {s.get('replacement','')} ({s.get('reason','')})")
            else:
                print(f"[DEBUG] AI returned no suggestions (empty or [])")
        except Exception as e:
            msg = str(e)
            print(f"[ERROR] Gemini exception: {msg}")
            if "429" in msg:
                print("[WARN] Gemini API rate limited (429)")
                ai_status["error"] = "rate_limited"
                ai_status["outcome"] = "rate_limited"
            else:
                print(f"[WARN] Gemini API error: {e}")
                ai_status["error"] = "api_error"
                ai_status["outcome"] = "api_error"

    elif req.use_ai and not gemini_client:
        ai_status["outcome"] = "unavailable"

    ai_status["suggestions_count"] = len(ai_suggestions)
    if ai_status["requested"] and ai_status["executed"] and ai_status["error"] is None:
        ai_status["outcome"] = "suggestions_found" if ai_status["suggestions_count"] > 0 else "no_suggestions"
    return {"tokens": token_result, "ai_suggestions": ai_suggestions, "ai_status": ai_status}

def run_local_ocr(image_content: bytes) -> str:
    """EasyOCR を使用して画像からテキストを抽出する"""
    try:
        img = Image.open(io.BytesIO(image_content))
        img_np = np.array(img)
        # detail=0 で純粋なテキストリストのみ取得
        reader = get_ocr_reader()
        results = reader.readtext(img_np, detail=0)
        return "\n".join(results)
    except Exception as e:
        print(f"[WARN] EasyOCR Error: {e}")
        return ""

@app.post("/ocr")
async def ocr_file(file: UploadFile = File(...)):
    try:
        content = await file.read()
        print(f"[INFO] OCR request: {file.filename} ({file.content_type})")

        # --- PDF対応 (二段構え) ---
        if file.content_type == "application/pdf":
            try:
                # fitz (PyMuPDF) を優先使用
                doc = fitz.open(stream=content, filetype="pdf")
                total_text = ""
                for page in doc:
                    text = page.get_text()
                    if text.strip():
                        total_text += text + "\n"
                    else:
                        # テキストがないページは画像化してOCR
                        pix = page.get_pixmap()
                        img_data = pix.tobytes("png")
                        total_text += run_local_ocr(img_data) + "\n"
                
                if total_text.strip():
                    print("[INFO] PDF extraction complete (local/mixed)")
                    return {"text": total_text.strip()}
            except Exception as e:
                print(f"[WARN] PDF local error: {e}")

        # --- 画像解析 (EasyOCR) ---
        if file.content_type.startswith("image/"):
            processed_content = preprocess_image(content)
            extracted_text = run_local_ocr(processed_content)
            print("[INFO] Image OCR complete (local)")
            return {"text": extracted_text}

        return {"text": ""}
        
    except Exception as e:
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8000)
