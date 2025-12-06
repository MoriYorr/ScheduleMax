<?php

declare(strict_types=1);

/**
 * Класс для подключения и получения данных из 1С
 */
class ConnectionTo1C
{
    private bool $test_mode = true;
    /**
     * Устанавливает соединение с 1С
     *
     * @return array Массив с URL и параметрами подключения
     */
    public function connect_to_1c(): array
    {
        $config = parse_ini_file('config.ini');
        $username = $config['username'];
        $password = $config['password'];
        $srv_ip = $config['srv_ip'];
        
        $url = "http://" . $srv_ip . "/portal/ws/Study.1cws?wsdl";
        
        $options = [
            'login' => $username,
            'password' => $password,
            'trace' => 1,
            'exceptions' => true
        ];

        return ['url' => $url, 'options' => $options];
    }

    /**
     * Получает список групп из 1С
     *
     * @return object|null Объект со списком групп или null при ошибке
     */
    public function get_group(): ?object
    {
        if ($this->test_mode) {
            // Читаем JSON напрямую
            $json_data = file_get_contents('test_data/groups_real.json');
            return json_decode($json_data);
        }
        
        $connection = $this->connect_to_1c();
        
        try {
            $client = new SoapClient($connection['url'], $connection['options']);
            $response = $client->bma_GetGroupList();
        } catch (SoapFault $e) {
            echo "Ошибка SOAP: " . $e->getMessage() . "\n";
            return null;
        }
        
        return $response;
    }

    /**
     * Получает расписание из 1С для указанного объекта
     *
     * @param string $schedule_object_type Тип объекта расписания
     * @param string $schedule_object_id ID объекта расписания
     * @param string $date_begin Дата начала в формате d.m.Y
     * @return object|null Объект с расписанием или null при ошибке
     */
    public function get_schedule(
        string $schedule_object_type,
        string $schedule_object_id,
        string $date_begin
    ): ?object {
        if ($this->test_mode) {
            // Читаем JSON напрямую
            $json_data = file_get_contents('test_data/schedule_real.json');
            return json_decode($json_data);
        }
        
        $connection = $this->connect_to_1c();
        
        try {
            $client = new SoapClient($connection['url'], $connection['options']);
            $date_begin_value = DateTime::createFromFormat('d.m.Y', $date_begin);
            
            $response = $client->bma_GetOpenSchedule(
                $schedule_object_id,
                $date_begin_value->format('Y-m-d'),
                $schedule_object_type
            );
        } catch (SoapFault $e) {
            echo "Ошибка: " . $e->getMessage() . "\n";
            return null;
        }
        
        return $response;
    }

    public function set_test_mode(bool $mode): void
    {
        $this->test_mode = $mode;
    }
}

/**
 * Класс для отправки данных расписания в VK Schedule API
 */
class ScheduleVK
{
    /**
     * @var ConnectionTo1C Экземпляр класса для работы с 1С
     */
    private ConnectionTo1C $one_c;
    
    /**
     * @var array Токен авторизации VK API
     */
    private array $token;
    
    private string $base_url;

    /**
     * Конструктор класса
     */
    public function __construct(bool $use_mock = false)
    {
        $this->one_c = new ConnectionTo1C();
        $this->token = json_decode(file_get_contents('token.json'), true);

        $this->base_url = $use_mock 
            ? 'http://localhost:8000' 
            : 'https://schedule.vk-apps.com';
    }

    /**
     * Отправляет POST запрос к VK Schedule API
     *
     * @param string $end_point Эндпоинт API
     * @param array $schedule_data Данные для отправки
     * @return void
     * @throws Exception При ошибке запроса
     */
    private function send_request(string $end_point, array $schedule_data): void
    {
        $curlOptions = [
            CURLOPT_URL => $this->base_url . $end_point,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $this->token['token']
            ]
        ];
            
        $json_req = json_encode(
            $schedule_data, 
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        
        $curlOptions[CURLOPT_POSTFIELDS] = $json_req;
            
        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $data = curl_exec($ch);
            
        $info = curl_getinfo($ch);
            
        if (curl_errno($ch) || substr((string)$info['http_code'], 0, 1) !== '2') {
            $error_message = curl_error($ch);
            curl_close($ch);
            throw new Exception($error_message . " | Response: " . $data);
        }
        
        curl_close($ch);
    }

    /**
     * Отправляет список групп в VK API
     *
     * @return void
     */
    public function send_groups(): void
    {
        $response = $this->one_c->get_group();
        
        if ($response === null) {
            echo "Не удалось получить группы из 1С\n";
            return;
        }

        $groups = [];
        
        foreach ($response->bma_GroupList as $group) {
            $group_name = $group->bma_GroupName;
            $course_number = intval(substr(explode("-", $group_name)[1], 0, 2));
            $current_year = intval(substr(date("Y"), 2, 2));
            
            // Определяем номер курса
            $courseNumber = $current_year - $course_number + 1;

            // Ограничиваем диапазон 1-4
            $courseNumber = max(1, min(4, $courseNumber));

            $groups[] = [
                'courseNumber' => $courseNumber,
                'educationForm' => "FULLTIME", // TODO. Неизвестный параметр
                'educationLevel' => "BACHELOR", // TODO. Неизвестный параметр
                'facultyId' => "2129439801", // TODO. Неизвестный параметр
                'groupCode' => "883АСП",  // TODO. Неизвестный параметр
                'groupId' => "45235678956",  // TODO. Неизвестный параметр
                'groupName' => $group_name,
                'specialityCode' => "53.09.05"  // TODO. Неизвестный параметр
            ];
        }

        $this->send_request("/v1/bulk/groups", $groups);
    }

    /**
     * Отправляет список подгрупп в VK API
     *
     * @return void
     */
    public function send_sub_groups(): void
    {
        $response = $this->one_c->get_group();
        
        if ($response === null) {
            echo "Не удалось получить группы из 1С\n";
            return;
        }

        $sub_groups = [];
        
        foreach ($response->bma_GroupList as $group) {
            $group_name = $group->bma_GroupName;
            $parts = explode(' ', $group_name);

            // Проверяем наличие подгруппы (есть что-то после основной группы)
            if (count($parts) > 1) {
                $sub_group_suffix = implode(' ', array_slice($parts, 1));
                
                $sub_groups[] = [
                    'groupIds' => ["53453453454353"], // TODO. Неизвестный параметр
                    'subGroupCode' => $sub_group_suffix,
                    'subGroupId' => "873642346873", // TODO. Неизвестный параметр
                    'subGroupName' => $group_name
                ];
            }
        }

        // Отправляем только если есть подгруппы
        if (!empty($sub_groups)) {
            $this->send_request("/v1/bulk/sub_groups", $sub_groups);
        } else {
            echo "Подгруппы не найдены\n";
        }
    }

    /**
     * Отправляет расписание занятий в VK API
     *
     * @param string $schedule_object_type Тип объекта расписания
     * @param string $schedule_object_id ID объекта расписания
     * @param string $date_begin Дата начала
     * @return void
     */
    public function send_schedule(
        string $schedule_object_type,
        string $schedule_object_id,
        string $date_begin
    ): void {
        $response = $this->one_c->get_schedule(
            $schedule_object_type,
            $schedule_object_id,
            $date_begin
        );

        if ($response === null) {
            echo "Не удалось получить расписание группы {$schedule_object_id} " .
                 "{$date_begin} числа из 1С\n";
            return;
        }

        $schedule = [];
        
        foreach ($response->Week as $week) {
            foreach ($week->ProjectSchedule as $project) {
                foreach ($project->Day as $day) {
                    foreach ($day->ScheduleCell as $cell) {
                        // Пропускаем пустые слоты
                        if (empty($cell->Lesson)) {
                            continue;
                        }
                        
                        foreach ($cell->Lesson as $lesson) {
                            $date = new DateTime();
                            
                            // Преобразуем DateEndReal в timestamp
                            $date_array = $lesson->DateEndReal;
                            $date->setDate($date_array[0], $date_array[1], $date_array[2]);
                            $date->setTime($date_array[3], $date_array[4], 0);
                            $event_date_end = $date->getTimestamp();

                            // Преобразуем DateBeginReal в timestamp
                            $date_array = $lesson->DateBeginReal;
                            $date->setDate($date_array[0], $date_array[1], $date_array[2]);
                            $date->setTime($date_array[3], $date_array[4], 0);
                            $event_date_start = $date->getTimestamp();

                            // Собираем ID преподавателей
                            $teacherIds = [];
                            foreach ($lesson->Teacher as $teacher) {
                                $teacherIds[] = $teacher->EmployerRef->ReferenceUID;
                            }

                            // Проверяем и очищаем ссылку на звонок
                            $eventLinkCall = $lesson->Classroom[0]->bma_Link ?? '';
                            if ($eventLinkCall === 'None') {
                                $eventLinkCall = '';
                            }

                            $schedule[] = [
                                'eventDateEnd' => $event_date_end,
                                'eventDateStart' => $event_date_start,
                                'eventId' => $lesson->LessonCompoundKey,
                                'eventLinkCall' => $eventLinkCall,
                                'eventLinkMaterials' => '',
                                'eventName' => $lesson->Subject,
                                'eventType' => $lesson->LessonType,
                                'groupId' => [$lesson->AcademicGroup[0]->AcademicGroupCompoundKey],
                                'recurrenceDateEnd' => $event_date_end,
                                'roomIds' => [$lesson->Classroom[0]->ClassroomUID],
                                'subGroupId' => '', // TODO. Неизвестный параметр
                                'teacherIds' => $teacherIds,
                                'weeklyRecurrence' => 1
                            ];
                        }
                    }
                }
            }
        }

        $this->send_request("/v1/bulk/events", $schedule);
    }
}