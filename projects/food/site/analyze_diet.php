<?php
//require_once 'llm.php'; // where callLlmApi lives
$timelim = 1200;
set_time_limit($timelim);

header('Content-Type: text/plain');
header('Cache-Control: no-cache');

// error_reporting(E_ALL);
// ini_set('display_errors', '1');        // show errors in the browser
// ini_set('display_startup_errors', '1'); // show startup errors
// ini_set('log_errors', '1');            // enable logging
// ini_set('error_log', '/tmp/php_errors.log'); // log to a file you control


//apache_setenv('no-gzip', '1');
ini_set('zlib.output_compression', 'Off');
ini_set('implicit_flush', '1');
ob_implicit_flush(true);

// echo "foo";

function callLlmApi(string $prompt, string $system): string {
    $CANCEL_FILE = 'cancel.file';
    global $timelim;
    $url = "http://silicis:11434/api/chat";
    $payload = json_encode([
        'model' => 'llama3',
        'messages' => [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>$prompt]
        ],
        'options'=>['temperature'=>0.2,'num_ctx'=>8192],
        'stream'=>true
    ]);

    $maxRetries = 3;
    $attempt = 0;
    do {
        $attempt++;
        $ch = curl_init($url);
        // echo "Curling...";
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_TIMEOUT=>$timelim,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) {
                // echo $data;
                // flush();
                // return strlen($data);
            // Split on newlines in case multiple JSON objects are sent together
                foreach (explode("\n", $data) as $line) {
                    $line = trim($line);
                    if (!$line) continue;
                    $json = json_decode($line, true);
                    if ($json && isset($json['message']['content'])) {
                        echo $json['message']['content'];
                        flush();
                    }
                }
                return strlen($data);
            }    
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        // if (file_exists($CANCEL_FILE)) {
            // @unlink($CANCEL_FILE);
            // //notifyUser('LLM inference cancelled', 'WARN');
            // return '[CANCELLED]';
        // }

        if ($resp!==false && $http===200) break;

        if ($attempt >= $maxRetries) return "Network Error: $resp $attempt/$maxRetries $err";
        sleep(min(pow(2,$attempt),10));
    } while(true);

    // $data = json_decode($resp,true);
    // if (json_last_error()!==JSON_ERROR_NONE) return "Invalid JSON from LLM";

    return $data['message']['content']??'';
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['foods'])) {
    http_response_code(400);
    echo "No food data received";
    exit;
}
// echo "bar";

$system = <<<SYS
You are a nutrition assistant.
Analyze diet data for trends, balance, and risks.
Be concise, factual, and practical.
Respond in under 200 words.
Do not list individual foods.
Summarize patterns only.
SYS;

$prompt = "Here is the user's food consumption data:\n\n";
$prompt .= json_encode($input['foods']);

$response = callLlmApi($prompt, $system);
echo $response;

