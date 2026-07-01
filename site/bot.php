<?php

$log_file = __DIR__ . '/bot_log.txt';
$users_file = __DIR__ . '/bot_users.json';
$state_file = __DIR__ . '/bot_states.json';
$books_dir = realpath(__DIR__ . '/../books/') . '/';
$content = file_get_contents("php://input");

$token = getenv('TELEGRAM_BOT_TOKEN');
$admin_id = getenv('TELEGRAM_ADMIN_ID');

$url = getenv('SITE_URL');

file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $content . PHP_EOL, FILE_APPEND);

$available_langs = ['ru', 'en'];
$default_lang = 'en';

$update = json_decode($content, true);
$lang_code = $update['message']['from']['language_code']
    ?? $update['callback_query']['from']['language_code']
    ?? $default_lang;
$lang = substr($lang_code, 0, 2);
if (!in_array($lang, $available_langs, true)) {
    $lang = $default_lang;
}

$messages = require __DIR__ . "/lang/$lang.php";

function t($key, $params = []) {
    global $messages;
    $text = $messages[$key] ?? $key;
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', $value, $text);
    }
    return $text;
}

function getAcceptedUsers() {
    global $users_file, $admin_id;
    $users = [$admin_id];
    if (file_exists($users_file)) {
        $saved = json_decode(file_get_contents($users_file), true);
        if (is_array($saved)) {
            $users = array_merge($users, $saved);
        }
    }
    return array_unique($users);
}

function approveUser($newUserId) {
    global $users_file;
    $saved = [];
    if (file_exists($users_file)) {
        $saved = json_decode(file_get_contents($users_file), true);
        if (!is_array($saved)) $saved = [];
    }
    
    $newUserId = (int)$newUserId;
    if (!in_array($newUserId, $saved)) {
        $saved[] = $newUserId;
        $result = file_put_contents($users_file, json_encode($saved));
        
        if ($result === false) {
            file_put_contents(__DIR__ . '/bot_log.txt', date('Y-m-d H:i:s') . " - " . t('log_write_error', ['file' => $users_file]) . "\n", FILE_APPEND);
        }
    }
}

$accepted_ids = getAcceptedUsers();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) exit;

function apiRequest($method, $data) {
    global $token;
    $url = "https://api.telegram.org/bot$token/$method";
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        ]
    ];
    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

function getState($userId) {
    global $state_file;
    if (!file_exists($state_file)) return null;
    $states = json_decode(file_get_contents($state_file), true);
    if (!is_array($states)) return null;
    return isset($states[$userId]) ? $states[$userId] : null;
}

function saveState($userId, $stateData) {
    global $state_file;
    $states = [];
    if (file_exists($state_file)) {
        $decoded = json_decode(file_get_contents($state_file), true);
        if (is_array($decoded)) {
            $states = $decoded;
        }
    }
    if ($stateData === null) {
        unset($states[$userId]);
    } else {
        $states[$userId] = $stateData;
    }
    file_put_contents($state_file, json_encode($states, JSON_UNESCAPED_UNICODE));
}

function convertWithCloudConvert($fileUrl, $targetFormat) {
    $apiKey = getenv('CONVERTER_API_KEY');
    if (!$apiKey) return false;

    $jobData = [
        'tasks' => [
            'import-it' => [
                'operation' => 'import/url',
                'url' => $fileUrl
            ],
            'convert-it' => [
                'operation' => 'convert',
                'input' => 'import-it',
                'output_format' => $targetFormat
            ],
            'export-it' => [
                'operation' => 'export/url',
                'input' => 'convert-it'
            ]
        ]
    ];

    $ch = curl_init('https://api.cloudconvert.com/v2/jobs');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jobData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 201) return false;

    $job = json_decode($response, true);
    $jobId = $job['data']['id'] ?? null;
    if (!$jobId) return false;

    $chWait = curl_init("https://api.cloudconvert.com/v2/jobs/$jobId/wait");
    curl_setopt($chWait, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chWait, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    
    curl_setopt($chWait, CURLOPT_TIMEOUT, 60); 
    $waitResponse = curl_exec($chWait);
    curl_close($chWait);

    $waitData = json_decode($waitResponse, true);
    if (($waitData['data']['status'] ?? '') !== 'finished') {
        return false;
    }

    $exportTask = null;
    foreach ($waitData['data']['tasks'] as $task) {
        if ($task['name'] === 'export-it') {
            $exportTask = $task;
            break;
        }
    }

    if ($exportTask && isset($exportTask['result']['files'][0]['url'])) {
        return $exportTask['result']['files'][0]['url'];
    }

    return false;
}

function downloadAndSaveFile($remotePath, $fileName, $userId) {
    global $token, $books_dir;
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    $url = "https://api.telegram.org/file/bot$token/$remotePath";
    
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $targetFormat = 'mobi';
    $supportedFormats = ['azw', 'prc', 'mobi', 'txt'];
    
    $filePath = $books_dir . $fileName;
    
    if (!in_array($ext, $supportedFormats)) {
        apiRequest('sendMessage', [
            'chat_id' => $userId,
            'text' => t('converting', ['ext' => $ext, 'format' => $targetFormat])
        ]);

        $convertedUrl = convertWithCloudConvert($url, $targetFormat);

        if ($convertedUrl) {
            $newFileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . $targetFormat;
            $newFilePath = $books_dir . $newFileName;


            file_put_contents($newFilePath, file_get_contents($convertedUrl));

            apiRequest('sendMessage', [
                'chat_id' => $userId,
                'text' => t('convert_success', ['file_name' => $newFileName])
            ]);
        } else {
            file_put_contents($filePath, file_get_contents($url));
            apiRequest('sendMessage', [
                'chat_id' => $userId,
                'text' => t('convert_error', ['file_name' => $fileName])
            ]);
        }
    } else {
        file_put_contents($filePath, file_get_contents($url));
        apiRequest('sendMessage', [
            'chat_id' => $userId,
            'text' => t('upload_success', ['file_name' => $fileName])
        ]);
    }
}

$messageUserId = $data['message']['from']['id'] ?? null;
if ($messageUserId && !in_array($messageUserId, $accepted_ids)) {
    $username = $data['message']['from']['username'] ?? t('fallback_no_username');
    $firstName = $data['message']['from']['first_name'] ?? t('fallback_no_name');
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => t('btn_approve'), 'callback_data' => "approve_$messageUserId"],
                ['text' => t('btn_reject'), 'callback_data' => "reject_$messageUserId"]
            ]
        ]
    ];
    apiRequest('sendMessage', [
        'chat_id' => $admin_id,
        'text' => t('new_user_request', ['first_name' => $firstName, 'username' => $username, 'user_id' => $messageUserId]),
        'reply_markup' => $keyboard
    ]);

    apiRequest('sendMessage', [
        'chat_id' => $messageUserId,
        'text' => t('access_denied_pending')
    ]);
    exit;
}

if (isset($data['message']['document'])) {
    $userId = $data['message']['from']['id'] ?? null;
    if (!$userId || !in_array($userId, $accepted_ids)) exit;

    $file = $data['message']['document'];
    $fileId = $file['file_id'];
    $fileName = $file['file_name'];

    $getFile = json_decode(file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$fileId"), true);
    
    if (isset($getFile['result']['file_path'])) {
        $remotePath = $getFile['result']['file_path'];
        
        saveState($userId, [
            'step' => 'WAITING_RENAME',
            'file_name' => $fileName,
            'remote_path' => $remotePath
        ]);
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => t('btn_yes'), 'callback_data' => 'rename_yes'],
                    ['text' => t('btn_no'), 'callback_data' => 'rename_no']
                ]
            ]
        ];

        apiRequest('sendMessage', [
            'chat_id' => $userId,
            'text' => t('rename_question', ['file_name' => $fileName]),
            'reply_markup' => $keyboard
        ]);
    }
    exit;
}
if (isset($data['callback_query'])) {
    $callback = $data['callback_query'];
    $userId = $callback['from']['id'] ?? null;
    if (!$userId) exit;

    $data_cb = $callback['data'];
    $messageId = $callback['message']['message_id'];
    
    global $admin_id;
    if ($userId == $admin_id && strpos($data_cb, 'approve_') === 0) {
        $targetId = str_replace('approve_', '', $data_cb);
        approveUser($targetId);
        
        apiRequest('editMessageText', [
            'chat_id' => $admin_id,
            'message_id' => $messageId,
            'text' => t('access_approved_admin', ['user_id' => $targetId])
        ]);

        apiRequest('sendMessage', [
            'chat_id' => $targetId,
            'text' => t('access_approved_user')
        ]);
        
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
        exit;
    }
    
    if ($userId == $admin_id && strpos($data_cb, 'reject_') === 0) {
        $targetId = str_replace('reject_', '', $data_cb);
        
        apiRequest('editMessageText', [
            'chat_id' => $admin_id,
            'message_id' => $messageId,
            'text' => t('access_rejected_admin', ['user_id' => $targetId])
        ]);

        apiRequest('sendMessage', [
            'chat_id' => $targetId,
            'text' => t('access_rejected_user')
        ]);
        
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
        exit;
    }

    if (!in_array($userId, $accepted_ids)) exit;

    $state = getState($userId);
    if (!$state || $state['step'] !== 'WAITING_RENAME') {
        apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => t('action_outdated'),
            'show_alert' => true
        ]);
        exit;
    }
    
    apiRequest('editMessageReplyMarkup', [
        'chat_id' => $userId,
        'message_id' => $messageId,
        'reply_markup' => ['inline_keyboard' => []]
    ]);
    
    if ($data_cb === 'rename_yes') {
        $state['step'] = 'WAITING_NEW_NAME';
        saveState($userId, $state);
        
        $oldNameWithoutExt = pathinfo($state['file_name'], PATHINFO_FILENAME);
        
        apiRequest('sendMessage', [
            'chat_id' => $userId,
            'text' => t('enter_new_name')
        ]);
    } elseif ($data_cb === 'rename_no') {
        downloadAndSaveFile($state['remote_path'], $state['file_name'], $userId);
        saveState($userId, null);
    }
    
    apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callback['id']
    ]);
    exit;
}


if (isset($data['message']['text'])) {
    $userId = $data['message']['from']['id'] ?? null;
    if (!$userId || !in_array($userId, $accepted_ids)) exit;

    $text = $data['message']['text'];
    $chatId = $data['message']['chat']['id'];
    $userId = $data['message']['from']['id'];

    if ($text === '/site' || $text === '/start') {
        sendSiteButton($chatId, t('library_available'), $token);
        exit;
    }
}

function sendSiteButton($chatId, $text, $token) {
    global $url;
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => t('btn_open_library'), 'url' => $url]
        ]]
    ];

    $postData = [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => json_encode($keyboard)
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($postData),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents("https://api.telegram.org/bot$token/sendMessage", false, $context);
}

if (isset($data['message']['text'])) {
    $userId = $data['message']['from']['id'] ?? null;
    if (!$userId || !in_array($userId, $accepted_ids)) exit;

    $text = trim($data['message']['text']);
    
    $state = getState($userId);
    if ($state && $state['step'] === 'WAITING_NEW_NAME') {
        $oldName = $state['file_name'];
        $ext = pathinfo($oldName, PATHINFO_EXTENSION);
        $newName = str_replace(['/', '\\'], '', $text);
        
        if (pathinfo($newName, PATHINFO_EXTENSION) !== $ext) {
            if ($ext) {
                $newName .= '.' . $ext;
            }
        }
        
        downloadAndSaveFile($state['remote_path'], $newName, $userId);
        
        saveState($userId, null);
    }
    exit;
}
?>