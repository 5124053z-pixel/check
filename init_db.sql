CREATE TABLE IF NOT EXISTS grammar_cache (
    hash_key TEXT PRIMARY KEY,
    json_result TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
