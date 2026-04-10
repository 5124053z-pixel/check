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
            $this->pythonPath = getenv('PYTHON_API_PYTHON') ?: 'C:\\Users\\milky\\anaconda3\\python.exe';
        }
        $this->scriptPath = getenv('PYTHON_API_SCRIPT') ?: (__DIR__ . '\\main.py');
    }

    public function ensureRunning($waitSeconds = 30) {
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

        // WindowsでXAMPP(PHP)から非同期起動
        $cmd = 'cmd /c cd /d "' . __DIR__ . '" && start "" /B ' . $python . ' ' . $script . ' > "' . __DIR__ . '\python.log" 2>&1';
        @pclose(@popen($cmd, 'r'));
    }
}
