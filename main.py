import os
import io
import json
import logging
import numpy as np
from typing import List, Optional
from fastapi import FastAPI, HTTPException, UploadFile, File, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from PIL import Image
import fitz  # PyMuPDF
import easyocr
import sudachipy
from dotenv import load_dotenv
from google import genai

# ロギング設定
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)

load_dotenv()

app = FastAPI(title="Smart Proofreader API", version="2.0.0")

# CORS設定 (VPS環境では必要に応じて制限することを推奨)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- グローバルリソースの初期化 ---

# Sudachi
try:
    tokenizer_obj = sudachipy.Dictionary().create()
    tokenizer_mode = sudachipy.SplitMode.C
    logger.info("Sudachi Dictionary initialized.")
except Exception as e:
    logger.error(f"Failed to initialize Sudachi: {e}")
    raise

# EasyOCR (Lazy Load)
ocr_reader = None

def get_ocr_reader():
    global ocr_reader
    if ocr_reader is None:
        logger.info("Initializing EasyOCR Reader (CPU mode)...")
        ocr_reader = easyocr.Reader(['ja', 'en'], gpu=False)
        logger.info("EasyOCR Reader Ready.")
    return ocr_reader

# Gemini
api_key = os.getenv("GEMINI_API_KEY")
gemini_client = None
if api_key and api_key != "your_gemini_api_key_here":
    try:
        gemini_client = genai.Client(api_key=api_key)
        logger.info("Gemini API client initialized.")
    except Exception as e:
        logger.error(f"Gemini init error: {e}")
else:
    logger.warning("GEMINI_API_KEY is not set or invalid. AI features will be disabled.")

# --- モデル定義 ---

class TextRequest(BaseModel):
    text: str = Field(..., min_length=1, description="校正対象のテキスト")
    ai_text: Optional[str] = Field(None, description="AIに渡すテキスト（マスク済みなど）")
    use_ai: bool = Field(True, description="AI校正を使用するかどうか")

class TokenInfo(BaseModel):
    surface: str
    base_form: str
    pos: List[str]

class AISuggestion(BaseModel):
    word: str
    replacement: str
    reason: str

class AIStatus(BaseModel):
    requested: bool
    available: bool
    executed: bool
    suggestions_count: int
    error: Optional[str] = None
    outcome: str

class AnalyzeResponse(BaseModel):
    tokens: List[TokenInfo]
    ai_suggestions: List[AISuggestion]
    ai_status: AIStatus

# --- ユーティリティ ---

def preprocess_image(content: bytes, max_size=1600) -> bytes:
    try:
        img = Image.open(io.BytesIO(content))
        w, h = img.size
        if max(w, h) > max_size:
            scale = max_size / max(w, h)
            new_size = (int(w * scale), int(h * scale))
            img = img.resize(new_size, Image.LANCZOS)
            output = io.BytesIO()
            fmt = img.format if img.format else "JPEG"
            img.save(output, format=fmt)
            return output.getvalue()
        return content
    except Exception as e:
        logger.warning(f"Image resize error: {e}")
        return content

def run_local_ocr(image_content: bytes) -> str:
    try:
        img = Image.open(io.BytesIO(image_content))
        img_np = np.array(img)
        reader = get_ocr_reader()
        results = reader.readtext(img_np, detail=0)
        return "\n".join(results)
    except Exception as e:
        logger.error(f"EasyOCR Error: {e}")
        return ""

# --- エンドポイント ---

@app.get("/health")
def health_check():
    return {"status": "healthy", "gemini_available": gemini_client is not None}

@app.post("/analyze", response_model=AnalyzeResponse)
async def analyze_text(req: TextRequest):
    logger.info(f"Analyze request received: {len(req.text)} chars")
    
    # 1. Sudachi Tokenization
    try:
        tokens = tokenizer_obj.tokenize(req.text, tokenizer_mode)
        token_result = [
            TokenInfo(surface=t.surface(), base_form=t.dictionary_form(), pos=list(t.part_of_speech()))
            for t in tokens
        ]
    except Exception as e:
        logger.error(f"Sudachi error: {e}")
        raise HTTPException(status_code=500, detail="Morphological analysis failed")

    # 2. AI Analysis
    ai_suggestions = []
    ai_status = {
        "requested": req.use_ai,
        "available": gemini_client is not None,
        "executed": False,
        "suggestions_count": 0,
        "error": None,
        "outcome": "disabled" if not req.use_ai else "pending"
    }

    if req.use_ai and gemini_client:
        ai_target = req.ai_text if req.ai_text is not None else req.text
        model_id = "gemini-2.0-flash-exp" # 最新の高速モデルに更新
        prompt = f"""あなたはプロの日本語校正者です。
入力テキストから、明らかな「打ち間違い」「変換ミス」「送り仮名の誤り」「カタカナ語の誤り」を見つけてください。
文体の好みやスタイルの違いは指摘しないでください。

返答は以下のJSON配列のみを出力してください。
[
  {{"word": "原文の間違い箇所", "replacement": "修正案", "reason": "簡潔な理由"}}
]

入力テキスト:
{ai_target}"""

        try:
            ai_status["executed"] = True
            response = gemini_client.models.generate_content(
                model=model_id,
                contents=prompt
            )
            resp_txt = response.text.strip()
            
            # JSON抽出
            if "```" in resp_txt:
                resp_txt = resp_txt.split("```")[1]
                if resp_txt.startswith("json"):
                    resp_txt = resp_txt[4:]
                resp_txt = resp_txt.split("```")[0].strip()
            
            if resp_txt and resp_txt != "[]":
                parsed = json.loads(resp_txt)
                if isinstance(parsed, list):
                    ai_suggestions = [AISuggestion(**s) for s in parsed if isinstance(s, dict) and "word" in s]
            
            ai_status["outcome"] = "suggestions_found" if ai_suggestions else "no_suggestions"
        except Exception as e:
            logger.error(f"Gemini API error: {e}")
            ai_status["error"] = str(e)
            ai_status["outcome"] = "api_error"

    ai_status["suggestions_count"] = len(ai_suggestions)
    return {
        "tokens": token_result,
        "ai_suggestions": ai_suggestions,
        "ai_status": ai_status
    }

@app.post("/ocr")
async def ocr_file(file: UploadFile = File(...)):
    logger.info(f"OCR request: {file.filename} ({file.content_type})")
    try:
        content = await file.read()
        
        if file.content_type == "application/pdf":
            doc = fitz.open(stream=content, filetype="pdf")
            total_text = ""
            for page in doc:
                text = page.get_text()
                if text.strip():
                    total_text += text + "\n"
                else:
                    pix = page.get_pixmap()
                    img_data = pix.tobytes("png")
                    total_text += run_local_ocr(img_data) + "\n"
            return {"text": total_text.strip()}

        if file.content_type.startswith("image/"):
            processed_content = preprocess_image(content)
            extracted_text = run_local_ocr(processed_content)
            return {"text": extracted_text}

        raise HTTPException(status_code=400, detail="Unsupported file type")
        
    except Exception as e:
        logger.error(f"OCR process error: {e}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    # 開発用。本番は uvicorn main:app --host 0.0.0.0 --port 8000 を推奨
    uvicorn.run(app, host="0.0.0.0", port=8000)
