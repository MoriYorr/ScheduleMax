<?php

header('Content-Type: application/json');

// Получаем данные запроса
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$request_body = file_get_contents('php://input');
$request_data = json_decode($request_body, true);

// Логируем запрос
$log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $request_method,
    'uri' => $request_uri,
    'body' => $request_data
];
file_put_contents('test_data/logs/api_requests.log', json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);

// Валидация токена
$headers = getallheaders();
if (!isset($headers['Authorization']) || !str_starts_with($headers['Authorization'], 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Роутинг по эндпоинтам
switch ($request_uri) {
    case '/v1/bulk/faculties':
        validate_faculties($request_data);
        break;
        
    case '/v1/bulk/groups':
        validate_groups($request_data);
        break;
        
    case '/v1/bulk/sub_groups':
        validate_sub_groups($request_data);
        break;
        
    case '/v1/bulk/events':
        validate_events($request_data);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        exit;
}

/**
 * Валидация факультетов
 */
function validate_faculties($data) {
    if (!is_array($data)) {
        send_error(400, 'Data must be an array');
    }
    
    foreach ($data as $faculty) {
        if (!isset($faculty['facultyCode']) || !isset($faculty['facultyId']) || !isset($faculty['facultyName'])) {
            send_error(422, 'Missing required fields: facultyCode, facultyId, facultyName');
        }
    }
    
    send_success(count($data));
}

/**
 * Валидация групп
 */
function validate_groups($data) {
    if (!is_array($data)) {
        send_error(400, 'Data must be an array');
    }
    
    $required_fields = ['courseNumber', 'educationForm', 'educationLevel', 'facultyId', 
                        'groupCode', 'groupId', 'groupName', 'specialityCode'];
    
    foreach ($data as $group) {
        foreach ($required_fields as $field) {
            if (!isset($group[$field])) {
                send_error(422, "Missing required field: {$field}");
            }
        }
        
        // Проверка типов
        if (!is_int($group['courseNumber']) || $group['courseNumber'] < 1 || $group['courseNumber'] > 4) {
            send_error(422, 'courseNumber must be integer between 1 and 4');
        }
        
        if (!in_array($group['educationForm'], ['FULLTIME', 'PARTTIME', 'DISTANCE'])) {
            send_error(422, 'Invalid educationForm');
        }
    }
    
    send_success(count($data));
}

/**
 * Валидация подгрупп
 */
function validate_sub_groups($data) {
    if (!is_array($data)) {
        send_error(400, 'Data must be an array');
    }
    
    foreach ($data as $subgroup) {
        if (!isset($subgroup['groupIds']) || !is_array($subgroup['groupIds'])) {
            send_error(422, 'groupIds must be an array');
        }
        
        if (!isset($subgroup['subGroupCode']) || !isset($subgroup['subGroupId']) || !isset($subgroup['subGroupName'])) {
            send_error(422, 'Missing required fields');
        }
    }
    
    send_success(count($data));
}

/**
 * Валидация событий (расписания)
 */
function validate_events($data) {
    if (!is_array($data)) {
        send_error(400, 'Data must be an array');
    }
    
    $required_fields = ['eventDateEnd', 'eventDateStart', 'eventId', 'eventName', 
                        'eventType', 'groupId', 'roomIds', 'teacherIds'];
    
    foreach ($data as $event) {
        foreach ($required_fields as $field) {
            if (!isset($event[$field])) {
                send_error(422, "Missing required field: {$field}");
            }
        }
        
        // Проверка типов
        if (!is_int($event['eventDateStart']) || !is_int($event['eventDateEnd'])) {
            send_error(422, 'eventDateStart and eventDateEnd must be timestamps (integers)');
        }
        
        if ($event['eventDateStart'] >= $event['eventDateEnd']) {
            send_error(422, 'eventDateStart must be before eventDateEnd');
        }
        
        if (!is_array($event['groupId']) || empty($event['groupId'])) {
            send_error(422, 'groupId must be non-empty array');
        }
        
        if (!is_array($event['teacherIds'])) {
            send_error(422, 'teacherIds must be an array');
        }
    }
    
    send_success(count($data));
}

/**
 * Отправка успешного ответа
 */
function send_success($count) {
    http_response_code(200);
    echo json_encode([
        'acceptedItemsCount' => $count,
        'createdCount' => $count,
        'updatedCount' => 0,
        'deletedCount' => 0
    ]);
    exit;
}

/**
 * Отправка ошибки
 */
function send_error($code, $message) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
