from datetime import datetime
from requests.auth import HTTPBasicAuth
import requests
from zeep import Client
from zeep.transports import Transport
import json
from zeep.helpers import serialize_object
import logging
import configparser, os


def get_group():
    config = configparser.ConfigParser()
    config.read('/home/service/bot/config.ini')
    username = config.get('Settings', 'username')
    password = config.get('Settings', 'password')
    srv-ip = config.get('Settings', 'srv-ip')
    logging.basicConfig(level=logging.INFO, filename="py_log.log", filemode="w",
                        format="%(asctime)s %(levelname)s %(message)s")
    try:
        url = f"http://{srv-ip}/portal/ws/Study.1cws?wsdl"
        session = requests.session()
        session.auth = HTTPBasicAuth(username, password)
        transport = Transport(session=session)
        client = Client(url, transport=transport)
        response = client.service.bma_GetGroupList()
        data = serialize_object(response)
        group = ""
        firstkurs = 0
        with open('group.json', 'w', encoding= "utf-8") as json_file:
            json.dump(data, json_file, ensure_ascii=False, indent=4)
        for week_item in data['bma_GroupList']:
            group += week_item['bma_GroupName'].split("-")[1][:2]
            if int(week_item['bma_GroupName'].split("-")[1][:2])>firstkurs:
                firstkurs = int(week_item['bma_GroupName'].split("-")[1][:2])
            group += ":"
        secondkurs = firstkurs - 1
        thirdkurs = secondkurs - 1
        fourthkurs = thirdkurs - 1
        all_kurs = str(firstkurs) + ":" + str(secondkurs) + ":" + str(thirdkurs) + ":" + str(fourthkurs)
        config.set('Settings', 'kurs', all_kurs)
        with open('config.ini', 'w') as config_file:
            config.write(config_file)
        logging.info("Файл успешно записан")
    except Exception as e:
        logging.error(f"An ERROR {e}")


def get_scheldule(schedule_object_type, schedule_object_id, date_begin):
    logging.basicConfig(level=logging.INFO, filename="py_log.log", filemode="w",
                        format="%(asctime)s %(levelname)s %(message)s")
    try:
        username = config.get('Settings', 'username')
        password = config.get('Settings', 'password')
        url = f"http://{srv-ip}/portal/ws/Study.1cws?wsdl"
        session = requests.session()
        session.auth = HTTPBasicAuth(username, password)
        transport = Transport(session=session)
        client = Client(url, transport=transport)
        date_begin_value = datetime.strptime(date_begin, '%d.%m.%Y')
        #response = client.service.GetSchedule(schedule_object_type, schedule_object_id, schedule_type, date_begin_value, date_end_value)
        response = str(client.service.bma_GetOpenSchedule(schedule_object_id, date_begin_value, schedule_object_type))
        print(response)
        response = response.replace("\"", '?')
        response = response.replace("'", '"')
        response = response.replace("?", '\'')
        response = response.replace("datetime.datetime", "")
        response = response.replace("datetime.date", "")
        response = response.replace("None", '"None"')
        response = response.replace("(", "[")
        response = response.replace(")", "]")
        with open(f"json/{schedule_object_id}.json", "w", encoding="utf-8") as txt_file:
            txt_file.write(response)
            print(f"{schedule_object_id}.json")
    except Exception as e:
        logging.error(f"Ошибка при выполнении запроса: {e}")


get_group()
get_scheldule(schedule_object_type='AcademicGroup', schedule_object_id="А-22101", date_begin="17.04.2024")