<?php

class PythonApiManager {
    private $host = '127.0.0.1';
    private $port = 8000;
    private $pythonPath;
    private $scriptPath;

    public function __construct() {
        // 必要なら .env / サーバー環境変数で上書き可能
        $venvPath = __DIR__ . '\\.venv\\Scripts\\python.exe';
        if (file_exists($venvPath)) {
            $this->pythonPath = $venvPath;
        } else {
            // 環境変数で指定がなければ PATH 上の python3/python を使用
            $this->pythonPath = getenv('PYTHON_API_PYTHON') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
        }
        $this->scriptPath = getenv('PYTHON_API_SCRIPT') ?: (__DIR__ . '\\main.py');
    }

    public function ensureRunning($waitSeconds = 30) {
        // Docker環境の場合はOSに依存する自動起動をスキップし、別コンテナが稼働していると見なす
        if (getenv('IS_DOCKER')) {
            return true;
        }

        if ($this->isPortOpen()) {
            return true;
        }

        $this->startDetached();
        $deadline = time() + $waitSeconds;
        while (time() < $deadline) {
            usleep(300000); // 300ms
            if ($this->isPortOpen()) {
                return true;
            }
        }

        return false;
    }

    private function isPortOpen() {
        $conn = @fsockopen($this->host, $this->port, $errno, $errstr, 0.5);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

    private function startDetached() {
        $python = escapeshellarg($this->pythonPath);
        $script = escapeshellarg($this->scriptPath);

        // ローカル開発環境でのみ使用：PHPから Python API をバックグラウンド起動
        $cmd = 'cmd /c cd /d "' . __DIR__ . '" && start "" /B ' . $python . ' ' . $script . ' > "' . __DIR__ . '\python.log" 2>&1';
        @pclose(@popen($cmd, 'r'));
    }
}
