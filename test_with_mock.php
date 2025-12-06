<?php

require_once 'schedule_vk.php';

echo "=== Тестирование с Mock Server ===\n\n";

// Включаем тестовый режим для 1С
$connection = new ConnectionTo1C();
$connection->set_test_mode(true);

// Используем mock сервер VK
$vk = new ScheduleVK(true); // true = использовать mock

echo "--- Тест 1: Отправка групп ---\n";
try {
    $vk->send_groups();
    echo "✓ Группы отправлены успешно\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}

echo "--- Тест 2: Отправка подгрупп ---\n";
try {
    $vk->send_sub_groups();
    echo "✓ Подгруппы отправлены успешно\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}

echo "--- Тест 3: Отправка расписания ---\n";
try {
    $vk->send_schedule('AcademicGroup', 'A-22101', '10.11.2025');
    echo "✓ Расписание отправлено успешно\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}

echo "=== Проверьте логи в test_data/logs/api_requests.log ===\n";
