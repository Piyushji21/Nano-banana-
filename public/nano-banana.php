<?php
define('DarkAI', '8476699783:AAEaJDsDyXSLtYG95Wa2sW5eWd6x748ZgvE'); // Token
define('NanoBanana', 'https://sii3.moayman.top/api/nano-banana.php'); // API
define('love', 'https://sii3.moayman.top/DarkAI.jpg'); // iMae Test (Test)
define('sTicker', 'CAACAgIAAxkBAAIMcmjDndyMvCb2OBQhIGobGVZU4f6JAAK0IwACmEspSN65vs0qW-TZNgQ'); // sTicker

$raw = file_get_contents('php://input');
file_put_contents('nb_debug.log', "[".date('Y-m-d H:i:s')."] RAW_UPDATE:\n".$raw."\n\n", FILE_APPEND);

$update = json_decode($raw, true);
$message = $update['message'] ?? null;
$callback_query = $update['callback_query'] ?? null;

$chat_id = $message['chat']['id'] ?? $callback_query['message']['chat']['id'] ?? null;
$message_id = $message['message_id'] ?? $callback_query['message']['message_id'] ?? null;
$text = $message['text'] ?? $callback_query['data'] ?? null;
$user_id = $message['from']['id'] ?? $callback_query['from']['id'] ?? null;
$data = $callback_query['data'] ?? null;

$user_state_file = 'user_state_' . $user_id . '.txt';
$user_state = file_exists($user_state_file) ? file_get_contents($user_state_file) : '';

function telegram_api($method, $parameters = []) {
    $url = 'https://api.telegram.org/bot' . DarkAI . '/' . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($parameters)) curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    $response = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $log = "[".date('Y-m-d H:i:s')."] TELEGRAM_API {$method} HTTP_CODE:{$http_code} CURL_ERR:{$curl_err} RESPONSE: {$response}\n";
    file_put_contents('nb_debug.log', $log, FILE_APPEND);
    $decoded = json_decode($response, true);
    return $decoded;
}

function send_spoiler_photo($chat_id, $photo_url, $caption = '') {
    return telegram_api('sendPhoto', [
        'chat_id' => $chat_id,
        'photo' => $photo_url,
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'has_spoiler' => true
    ]);
}

function update_user_state($user_id, $state) {
    file_put_contents('user_state_' . $user_id . '.txt', $state);
}

function clear_user_state($user_id) {
    @unlink('user_state_' . $user_id . '.txt');
}

function pending_images_file($user_id) {
    return 'user_images_' . $user_id . '.json';
}

function add_pending_images($user_id, $links) {
    $file = pending_images_file($user_id);
    $current = [];
    if (file_exists($file)) {
        $current = json_decode(file_get_contents($file), true) ?: [];
    }
    $merged = array_values(array_unique(array_merge($current, $links)));
    if (count($merged) > 10) $merged = array_slice($merged, 0, 10);
    file_put_contents($file, json_encode($merged));
}

function get_pending_images($user_id) {
    $file = pending_images_file($user_id);
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return $data ?: [];
}

function clear_pending_images($user_id) {
    @unlink(pending_images_file($user_id));
}

function file_id_to_link($file_id) {
    $info = telegram_api('getFile', ['file_id' => $file_id]);
    if (!isset($info['result']['file_path'])) return null;
    return 'https://api.telegram.org/file/bot' . DarkAI . '/' . $info['result']['file_path'];
}

function get_bot_profile_photo_id() {
    $me = telegram_api('getMe', []);
    if (!isset($me['result']['id'])) return null;
    $bot_id = $me['result']['id'];
    $photos = telegram_api('getUserProfilePhotos', ['user_id' => $bot_id, 'limit' => 1]);
    if (!isset($photos['result']['total_count']) || $photos['result']['total_count'] <= 0) return null;
    $sizes = $photos['result']['photos'][0];
    $best = end($sizes);
    return $best['file_id'] ?? null;
}

if (!$chat_id) {
    file_put_contents('nb_debug.log', "[".date('Y-m-d H:i:s')."] NO_CHAT_ID, UPDATE KEPT\n\n", FILE_APPEND);
    http_response_code(200);
    exit;
}

if ($data === '/start' || $text === '/start') {
    $reply_markup = [
        'inline_keyboard' => [
            [
                ['text' => 'Create Image', 'callback_data' => 'create_image'],
                ['text' => 'Edit Image', 'callback_data' => 'edit_image']
            ]
        ]
    ];
    $caption_text = "<b>Hi im NanoBanana 👋</b>\n\n<i>CREATE IMAGE</i> - <i>EDIT IMAGE</i>\n\n<blockquote>i Know the game + world + famous people etc ..  \ni Have knowledge of the internet 😊</blockquote>\n\n<b>API</b> <a href=\"http://t.me/DarkAIx/495\">Click here</a> 💖\nPrompt NanoBanana: <a href=\"https://t.me/siib0\">Click here</a> 🍌";
    $bot_photo_file_id = get_bot_profile_photo_id();
    if ($bot_photo_file_id) {
        $res = telegram_api('sendPhoto', [
            'chat_id' => $chat_id,
            'photo' => $bot_photo_file_id,
            'caption' => $caption_text,
            'parse_mode' => 'HTML',
            'has_spoiler' => true,
            'reply_markup' => json_encode($reply_markup)
        ]);
        if (!$res || !isset($res['ok']) || $res['ok'] !== true) {
            telegram_api('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $caption_text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($reply_markup)
            ]);
        }
    } else {
        $res = telegram_api('sendPhoto', [
            'chat_id' => $chat_id,
            'photo' => love,
            'caption' => $caption_text,
            'parse_mode' => 'HTML',
            'has_spoiler' => true,
            'reply_markup' => json_encode($reply_markup)
        ]);
        if (!$res || !isset($res['ok']) || $res['ok'] !== true) {
            telegram_api('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $caption_text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($reply_markup)
            ]);
        }
    }
    clear_user_state($user_id);
    clear_pending_images($user_id);
    http_response_code(200);
    exit;
}

if ($data === 'create_image' || $data === 'edit_image') {
    $mode = ($data === 'create_image') ? 'create' : 'edit';
    $new_text = ($mode === 'create') ? 'Send Text' : 'Send iMage "MAX 10"';
    telegram_api('editMessageCaption', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'caption' => $new_text,
    ]);
    telegram_api('editMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => null,
    ]);
    update_user_state($user_id, $mode);
    clear_pending_images($user_id);
    http_response_code(200);
    exit;
}

if ($message) {
    if ($text === '/start') {
        $reply_markup = [
            'inline_keyboard' => [
                [
                    ['text' => 'Create Image', 'callback_data' => 'create_image'],
                    ['text' => 'Edit Image', 'callback_data' => 'edit_image']
                ]
            ]
        ];
        $caption_text = "<b>Hi im NanoBanana 👋</b>\n\n<i>CREATE IMAGE</i> - <i>EDIT IMAGE</i>\n\n<blockquote>i Know the game + world + famous people etc ..  \ni Have knowledge of the internet 😊</blockquote>\n\n<b>API</b> <a href=\"http://t.me/DarkAIx/495\">Click here</a> 💖\nPrompt NanoBanana: <a href=\"https://t.me/siib0\">Click here</a> 🍌";
        $bot_photo_file_id = get_bot_profile_photo_id();
        if ($bot_photo_file_id) {
            $res = telegram_api('sendPhoto', [
                'chat_id' => $chat_id,
                'photo' => $bot_photo_file_id,
                'caption' => $caption_text,
                'parse_mode' => 'HTML',
                'has_spoiler' => true,
                'reply_markup' => json_encode($reply_markup)
            ]);
            if (!$res || !isset($res['ok']) || $res['ok'] !== true) {
                telegram_api('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => $caption_text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($reply_markup)
                ]);
            }
        } else {
            $res = telegram_api('sendPhoto', [
                'chat_id' => $chat_id,
                'photo' => love,
                'caption' => $caption_text,
                'parse_mode' => 'HTML',
                'has_spoiler' => true,
                'reply_markup' => json_encode($reply_markup)
            ]);
            if (!$res || !isset($res['ok']) || $res['ok'] !== true) {
                telegram_api('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => $caption_text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($reply_markup)
                ]);
            }
        }
        clear_user_state($user_id);
        clear_pending_images($user_id);
        http_response_code(200);
        exit;
    }

    if ($user_state === 'create' && isset($message['text']) && $message['text'] !== '') {
        $text_for_api = $message['text'];
        $sticker_message = telegram_api('sendSticker', ['chat_id' => $chat_id, 'sticker' => sTicker]);
        $sticker_message_id = $sticker_message['result']['message_id'] ?? null;

        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, NanoBanana);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, ['text' => $text_for_api]);

        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, NanoBanana);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, ['text' => $text_for_api]);

        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch1);
        curl_multi_add_handle($mh, $ch2);
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        $response1 = json_decode(curl_multi_getcontent($ch1), true);
        $response2 = json_decode(curl_multi_getcontent($ch2), true);
        curl_multi_remove_handle($mh, $ch1);
        curl_multi_remove_handle($mh, $ch2);
        curl_multi_close($mh);

        if ($sticker_message_id) {
            telegram_api('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $sticker_message_id]);
        }

        $caption_text2 = (strlen($text_for_api) > 1024) ? substr($text_for_api, 0, 1021) . '...' : $text_for_api;
        $caption_html = "<b><blockquote>" . htmlspecialchars($caption_text2) . "</blockquote></b>";

        $media = [];
        if (isset($response1['image'])) {
            $media[] = [
                'type' => 'photo',
                'media' => $response1['image'],
                'caption' => $caption_html,
                'parse_mode' => 'HTML',
                'has_spoiler' => true
            ];
        }
        if (isset($response2['image'])) {
            $media[] = [
                'type' => 'photo',
                'media' => $response2['image'],
                'has_spoiler' => true
            ];
        }
        if (!empty($media)) {
            telegram_api('sendMediaGroup', ['chat_id' => $chat_id, 'media' => json_encode($media)]);
        } else {
            telegram_api('sendMessage', ['chat_id' => $chat_id, 'text' => 'Sorry The Pictures Are Pornographic - Prohibited .. cant Help You With That']);
        }

        clear_user_state($user_id);
        clear_pending_images($user_id);
        http_response_code(200);
        exit;
    }

    if ($user_state === 'edit' && isset($message['photo'])) {
        $new_links = [];
        $largest_photo = end($message['photo']);
        $file_id = $largest_photo['file_id'];
        $link = file_id_to_link($file_id);
        if ($link) {
            $new_links[] = $link;
        }

        if (!empty($new_links)) {
            $prev_pending_count = count(get_pending_images($user_id));
            add_pending_images($user_id, $new_links);
            $current_pending_count = count(get_pending_images($user_id));

            if ($prev_pending_count === 0 && $current_pending_count > 0) {
                 telegram_api('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "Send Text"
                ]);
            }
        }
        http_response_code(200);
        exit;
    }

    if ($user_state === 'edit' && isset($message['text'])) {
        $text_to_edit = $message['text'];
        $links_to_process = get_pending_images($user_id);

        if (!empty($links_to_process) && !empty($text_to_edit)) {
            $sticker_message = telegram_api('sendSticker', ['chat_id' => $chat_id, 'sticker' => sTicker]);
            $sticker_message_id = $sticker_message['result']['message_id'] ?? null;

            $links_string = implode(',', $links_to_process);

            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, NanoBanana);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, ['text' => $text_to_edit, 'links' => $links_string]);

            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, NanoBanana);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, ['text' => $text_to_edit, 'links' => $links_string]);

            $mh = curl_multi_init();
            curl_multi_add_handle($mh, $ch1);
            curl_multi_add_handle($mh, $ch2);
            $running = null;
            do {
                curl_multi_exec($mh, $running);
            } while ($running > 0);

            $response1 = json_decode(curl_multi_getcontent($ch1), true);
            $response2 = json_decode(curl_multi_getcontent($ch2), true);
            curl_multi_remove_handle($mh, $ch1);
            curl_multi_remove_handle($mh, $ch2);
            curl_multi_close($mh);

            if ($sticker_message_id) {
                telegram_api('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $sticker_message_id]);
            }

            $caption_text3 = (strlen($text_to_edit) > 1024) ? substr($text_to_edit, 0, 1021) . '...' : $text_to_edit;
            $caption_html = "<b><blockquote>" . htmlspecialchars($caption_text3) . "</blockquote></b>";

            $media = [];
            if (isset($response1['image'])) {
                $media[] = [
                    'type' => 'photo',
                    'media' => $response1['image'],
                    'caption' => $caption_html,
                    'parse_mode' => 'HTML',
                    'has_spoiler' => true
                ];
            }
            if (isset($response2['image'])) {
                $media[] = [
                    'type' => 'photo',
                    'media' => $response2['image'],
                    'has_spoiler' => true
                ];
            }
            if (!empty($media)) {
                telegram_api('sendMediaGroup', ['chat_id' => $chat_id, 'media' => json_encode($media)]);
            } else {
                telegram_api('sendMessage', ['chat_id' => $chat_id, 'text' => 'Sorry The Pictures Are Pornographic - Prohibited .. cant Help You With That']);
            }

            clear_user_state($user_id);
            clear_pending_images($user_id);
            http_response_code(200);
            exit;
        } else {
            telegram_api('sendMessage', ['chat_id' => $chat_id, 'text' => 'Please send one or more images first, then send your edit description.']);
            clear_user_state($user_id);
            update_user_state($user_id, 'edit');
        }
    }
}
http_response_code(200);
?>
