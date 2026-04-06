let history = [];
let pendingMatches = [];

const editor = document.getElementById('editor');
const charCount = document.getElementById('char-count');
const charWarning = document.getElementById('char-warning');
const suggestionsContainer = document.getElementById('suggestions-container');
const analyzeBtn = document.getElementById('analyze-btn');
const analyzeLoader = document.getElementById('analyze-loader');
const uiLockOverlay = document.getElementById('ui-lock-overlay');
const aiStatusEl = document.getElementById('ai-runtime-status');
const aiModeRadios = document.querySelectorAll('input[name="ai-mode"]');

let isBusy = false;

editor.addEventListener('input', updateCharacterCount);

// Text Normalizer (similar to old HTML)
function getNormalizedText() {
    let html = editor.innerHTML.trim();
    if (html === '<div><br></div>') return '';
    html = html.replace(/<div><br><\/div>/g, '\n');
    html = html.replace(/<div>(\s*|&nbsp;)*<\/div>/g, '');
    html = html.replace(/<div>/g, '\n').replace(/<\/div>/g, '');
    html = html.replace(/<br\s*\/?>/gi, '\n');
    
    const temp = document.createElement("div");
    temp.innerHTML = html;
    return temp.textContent || '';
}

function countCharacters(text) {
    const urlRegex = /https?:\/\/[^\s]+/g;
    let count = 0;
    let lastIndex = 0;

    text.replace(urlRegex, (url, index) => {
        const before = text.slice(lastIndex, index);
        for (const char of before) {
            count += char === '\n' || char.match(/[ -~]/) ? 0.5 : 1;
        }
        count += 11.5;
        lastIndex = index + url.length;
        return url;
    });

    const remaining = text.slice(lastIndex);
    for (const char of remaining) {
        count += char === '\n' || char.match(/[ -~]/) ? 0.5 : 1;
    }

    return count;
}

function updateCharacterCount() {
    const normalizedText = getNormalizedText();
    const count = countCharacters(normalizedText);
    charCount.textContent = `${count} / 140文字`;
    
    if (count > 140) {
        charCount.style.color = 'var(--danger)';
        charWarning.style.display = 'block';
    } else {
        charCount.style.color = 'var(--text-muted)';
        charWarning.style.display = 'none';
    }
}

function escapeHTML(str) {
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function setUiLocked(locked, message = "処理中です。しばらくお待ちください...") {
    isBusy = locked;
    const msgEl = uiLockOverlay?.querySelector('.lock-message');
    if (msgEl) msgEl.textContent = message;
    if (uiLockOverlay) uiLockOverlay.classList.toggle('hidden', !locked);

    analyzeBtn.disabled = locked;
    ocrBtn.disabled = locked || !selectedFile;
    editor.contentEditable = locked ? 'false' : 'true';
    aiModeRadios.forEach((radio) => { radio.disabled = locked; });
}

function isAiEnabled() {
    const selected = document.querySelector('input[name="ai-mode"]:checked');
    return !selected || selected.value === 'on';
}

function updateAiRuntimeStatus(status) {
    if (!aiStatusEl) return;

    aiStatusEl.classList.remove('ai-status-idle', 'ai-status-on', 'ai-status-off', 'ai-status-error');

    if (!status) {
        aiStatusEl.textContent = 'AI状態: 未実行';
        aiStatusEl.classList.add('ai-status-idle');
        return;
    }

    if (status.error && status.error !== 'rate_limited') {
        aiStatusEl.textContent = `AI状態: エラー (${status.error})`;
        aiStatusEl.classList.add('ai-status-error');
        return;
    }

    if (!status.requested || status.outcome === 'disabled') {
        aiStatusEl.textContent = 'AI状態: OFF（未使用）';
        aiStatusEl.classList.add('ai-status-off');
        return;
    }

    if (status.outcome === 'rate_limited') {
        aiStatusEl.textContent = 'AI状態: 実行失敗（API制限 429）';
        aiStatusEl.classList.add('ai-status-error');
        return;
    }

    if (status.outcome === 'python_api_unavailable') {
        aiStatusEl.textContent = 'AI状態: 実行失敗（Python API未起動）';
        aiStatusEl.classList.add('ai-status-error');
        return;
    }

    if (!status.available) {
        aiStatusEl.textContent = 'AI状態: 利用不可';
        aiStatusEl.classList.add('ai-status-error');
        return;
    }

    if (status.outcome === 'suggestions_found') {
        aiStatusEl.textContent = `AI状態: 実行済み（提案 ${status.suggestions_count ?? 0} 件）`;
        aiStatusEl.classList.add('ai-status-on');
        return;
    }

    if (status.outcome === 'no_suggestions') {
        aiStatusEl.textContent = 'AI状態: ✅ 実行完了（AI指摘なし — 問題を見逃している可能性あり）';
        aiStatusEl.classList.add('ai-status-on');
        return;
    }

    aiStatusEl.textContent = 'AI状態: ON（未実行）';
    aiStatusEl.classList.add('ai-status-on');
}

function copyToClipboard() {
    const text = getNormalizedText();
    if(!text) {
        showToast("テキストがありません");
        return;
    }
    navigator.clipboard.writeText(text).then(() => {
        showToast("📋 コピーしました！");
    }).catch(err => {
        const tempTextarea = document.createElement("textarea");
        tempTextarea.value = text;
        document.body.appendChild(tempTextarea);
        tempTextarea.select();
        document.execCommand("copy");
        document.body.removeChild(tempTextarea);
        showToast("📋 コピーしました！");
    });
}

// Logic: Fetch to Backend
async function analyzeText() {
    if (isBusy) return;

    const text = getNormalizedText();
    if (!text) {
        showToast("文章を入力してください");
        return;
    }

    // UI Loading
    setUiLocked(true, "テキストを校正しています...");
    analyzeBtn.querySelector('span').style.opacity = '0.5';
    analyzeLoader.style.display = 'block';
    suggestionsContainer.innerHTML = '';

    const useAi = isAiEnabled();

    try {
        const response = await fetch('analyze.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text, use_ai: useAi })
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        renderSuggestions(text, data.matches);
        updateAiRuntimeStatus(data.ai_status);

    } catch (err) {
        console.error(err);
        updateAiRuntimeStatus({ error: 'request_failed' });
        showToast("❌ エラーが発生しました");
        suggestionsContainer.innerHTML = `
            <div class="empty-state">
                <span class="emoji">⚠️</span>
                <p>サーバーとの通信に失敗しました。Python APIが起動しているか確認してください。</p>
            </div>
        `;
    } finally {
        analyzeBtn.querySelector('span').style.opacity = '1';
        analyzeLoader.style.display = 'none';
        setUiLocked(false);
    }
}

function renderSuggestions(originalText, matches) {
    pendingMatches = matches;
    
    if (matches.length === 0) {
        suggestionsContainer.innerHTML = `
            <div class="empty-state">
                <span class="emoji">✨</span>
                <p>修正の必要な箇所は見つかりませんでした！完璧です。</p>
            </div>
        `;
        editor.innerHTML = escapeHTML(originalText).replace(/\n/g, '<br>');
        return;
    }

    let html = '';
    let lastIndex = 0;

    // Render Editor text with highlights
    for (const match of matches) {
        html += escapeHTML(originalText.slice(lastIndex, match.start));
        html += `<span class="highlight" id="${match.id}">${escapeHTML(match.word)}</span>`;
        lastIndex = match.end;
    }
    html += escapeHTML(originalText.slice(lastIndex));
    editor.innerHTML = html.replace(/\n/g, '<br>');

    // Render Suggestion Boxes
    matches.forEach((m) => {
        const box = document.createElement('div');
        box.className = 'suggestion-box';
        box.id = `box-${m.id}`;
        box.innerHTML = `
            <div class="suggestion-content">
                <span class="suggestion-word">${escapeHTML(m.word)}</span>
                <span class="suggestion-arrow">→</span>
                <span class="suggestion-replacement">${escapeHTML(m.replacement)}</span>
            </div>
            ${m.reason ? `<div class="suggestion-reason">${escapeHTML(m.reason)}</div>` : ''}
            <div class="suggestion-actions">
                <button class="btn btn-sm btn-success" onclick="applyConversion('${m.id}', '${m.word}', '${m.replacement}')">✅ 変換</button>
                <button class="btn btn-sm btn-danger" onclick="rejectSuggestion('${m.id}', '${m.word}')">❌ 拒否</button>
            </div>
        `;

        box.addEventListener('mouseenter', () => {
            const span = document.getElementById(m.id);
            if (span && !span.classList.contains('converted')) {
                span.classList.add('hovered');
            }
            box.classList.add('active');
        });
        box.addEventListener('mouseleave', () => {
            const span = document.getElementById(m.id);
            if (span) span.classList.remove('hovered');
            box.classList.remove('active');
        });

        // Click or Hover on span in editor triggers box highlight
        const spanEl = document.getElementById(m.id);
        if (spanEl) {
            spanEl.addEventListener('mouseenter', () => {
                box.classList.add('active');
            });
            spanEl.addEventListener('mouseleave', () => box.classList.remove('active'));
        }

        suggestionsContainer.appendChild(box);
    });

    updateCharacterCount();
}

function applyConversion(spanId, before, after) {
    if (isBusy) return;
    const span = document.getElementById(spanId);
    if (span) {
        span.textContent = after;
        span.classList.remove('highlight', 'hovered');
        span.classList.add('converted');

        history.unshift({ spanId, before, after, type: '変換' });
        updateHistoryDisplay();
    }

    removeBox(spanId);
    updateCharacterCount();
}

function rejectSuggestion(spanId, before) {
    if (isBusy) return;
    const span = document.getElementById(spanId);
    if (span) {
        span.classList.remove('highlight', 'hovered');
        
        // Remove the span tag itself but keep the inner text
        const textNode = document.createTextNode(span.textContent);
        if(span.parentNode) {
            span.parentNode.replaceChild(textNode, span);
        }

        history.unshift({ spanId, before, after: null, type: '拒否' });
        updateHistoryDisplay();
    }

    removeBox(spanId);
}

function removeBox(spanId) {
    const box = document.getElementById(`box-${spanId}`);
    if (box) {
        box.style.transform = 'scale(0.9)';
        box.style.opacity = '0';
        setTimeout(() => box.remove(), 250);
    }
    
    // Check if empty
    setTimeout(() => {
        if (suggestionsContainer.children.length === 0) {
            suggestionsContainer.innerHTML = `
                <div class="empty-state">
                    <span class="emoji">🎉</span>
                    <p>すべての提案を確認しました。</p>
                </div>
            `;
        }
    }, 300);
}

function toggleHistory() {
    if (isBusy) return;
    const panel = document.getElementById('history-panel');
    panel.classList.toggle('hidden');
}

function updateHistoryDisplay() {
    const list = document.getElementById('history-list');
    list.innerHTML = '';
    
    if (history.length === 0) {
        list.innerHTML = '<li class="empty-history">履歴はありません</li>';
        return;
    }

    history.forEach((entry, i) => {
        const li = document.createElement('li');
        if (entry.type === '変換') {
            li.innerHTML = `
                <span><del style="color:var(--text-muted)">${escapeHTML(entry.before)}</del> → <strong>${escapeHTML(entry.after)}</strong></span>
                <button class="btn btn-sm btn-secondary" onclick="undoConversion(${i})" style="padding:0.2rem 0.5rem;font-size:0.8rem">↩ Undo</button>
            `;
        } else {
            li.innerHTML = `<span><strong>${escapeHTML(entry.before)}</strong> <span style="font-size:0.8rem;color:var(--text-muted)">(拒否)</span></span>`;
        }
        list.appendChild(li);
    });
}

function undoConversion(index) {
    if (isBusy) return;
    const entry = history[index];
    const span = document.getElementById(entry.spanId);
    if (span) {
        span.textContent = entry.before;
        span.classList.remove('converted', 'hovered');
        span.classList.add('highlight'); // Re-add highlight class
    }
    history.splice(index, 1);
    updateHistoryDisplay();
    updateCharacterCount();
    showToast("変換を元に戻しました");
}

function clearHistory() {
    if (isBusy) return;
    history = [];
    updateHistoryDisplay();
}

// OCR & Tab Logic
const textSection = document.getElementById('text-section');
const imageSection = document.getElementById('image-section');
const dropZone = document.getElementById('drop-zone');
const imageInput = document.getElementById('image-input');
const imagePreviewContainer = document.getElementById('image-preview-container');
const imagePreview = document.getElementById('image-preview');
const ocrBtn = document.getElementById('ocr-btn');
const ocrLoader = document.getElementById('ocr-loader');

let selectedFile = null;

function switchTab(tab) {
    if (isBusy) return;
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    if (tab === 'text') {
        document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
        textSection.classList.remove('hidden');
        imageSection.classList.add('hidden');
    } else {
        document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
        textSection.classList.add('hidden');
        imageSection.classList.remove('hidden');
    }
}

const pdfIcon = document.getElementById('pdf-icon');
const fileNameDisplay = document.getElementById('file-name-display');

function handleImageSelect(event) {
    if (isBusy) return;
    const file = event.target.files[0] || event.dataTransfer.files[0];
    if (file && (file.type.startsWith('image/') || file.type === 'application/pdf')) {
        selectedFile = file;
        fileNameDisplay.textContent = file.name;
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                imagePreview.classList.remove('hidden');
                pdfIcon.classList.add('hidden');
                dropZone.classList.add('hidden');
                imagePreviewContainer.classList.remove('hidden');
                ocrBtn.disabled = false;
            };
            reader.readAsDataURL(file);
        } else {
            // PDF の場合
            imagePreview.classList.add('hidden');
            pdfIcon.classList.remove('hidden');
            dropZone.classList.add('hidden');
            imagePreviewContainer.classList.remove('hidden');
            ocrBtn.disabled = false;
        }
    }
}

function resetImage() {
    if (isBusy) return;
    selectedFile = null;
    imageInput.value = '';
    imagePreview.src = '';
    fileNameDisplay.textContent = '';
    pdfIcon.classList.add('hidden');
    dropZone.classList.remove('hidden');
    imagePreviewContainer.classList.add('hidden');
    ocrBtn.disabled = true;
}

// Drag & Drop
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, e => {
        e.preventDefault();
        e.stopPropagation();
    }, false);
});

dropZone.addEventListener('dragover', () => dropZone.classList.add('dragover'));
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', (e) => {
    dropZone.classList.remove('dragover');
    handleImageSelect(e);
});

async function analyzeImage() {
    if (isBusy || !selectedFile) return;

    setUiLocked(true, "画像を解析しています...");
    ocrBtn.querySelector('span').style.opacity = '0.5';
    ocrLoader.style.display = 'block';
    ocrBtn.disabled = true;
    suggestionsContainer.innerHTML = '';

    const formData = new FormData();
    formData.append('image', selectedFile);
    formData.append('use_ai', isAiEnabled() ? '1' : '0');

    try {
        const response = await fetch('analyze_ocr.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.error) throw new Error(data.error);

        // OCRで抽出されたテキストをエディタにセットして表示
        showToast("✅ 画像の解析が完了しました");
        switchTab('text'); // テキストタブに戻して結果を表示
        renderSuggestions(data.text, data.matches);
        updateAiRuntimeStatus(data.ai_status);

    } catch (err) {
        console.error(err);
        updateAiRuntimeStatus({ error: 'request_failed' });
        showToast("❌ OCR解析に失敗しました");
    } finally {
        ocrBtn.querySelector('span').style.opacity = '1';
        ocrLoader.style.display = 'none';
        setUiLocked(false);
    }
}

// Init
updateCharacterCount();
updateAiRuntimeStatus(null);
