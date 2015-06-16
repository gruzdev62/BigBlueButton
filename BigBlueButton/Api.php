<?php

namespace BigBlueButton;

use Exception;
use SimpleXMLElement;

/**
 * Class Api
 * @package BigBlueButton
 * @author fkulakov
 * @email fkulakov@gmail.com
 * TODO: ссылки для всех методов api формируются похожим образом. Стоит подумать об одном методе для их получения.
 */
class Api
{
    /**
     * @var string секретный ключ, узнать который можно по bbb-conf --secret
     */
    private $secretSalt;


    /**
     * @var string адрес, по которому установлен BigBlueButton.
     */
    private $serverUrl;

    /**
     * Просто конструктор. Задает значения полям $secretSalt и $serverUrl.
     */
    public function __construct()
    {
        $this->secretSalt = '8cd8ef52e8e101574e400365b55e11a6';
        $this->serverUrl  = 'http://test-install.blindsidenetworks.com/bigbluebutton/';
    }


    /**
     * Выполняет запрос к api
     * @param string $url адрес, к которому будем обращаться.
     * @param string $xml дополнительные необязательные параметры в формате xml.
     * @return SimpleXMLElement ответ сервера в виде объекта xml.
     * @throws Exception
     */
    private function xmlResponse($url, $xml = '')
    {
        if (!extension_loaded('curl') && !empty($xml)) {
            throw new Exception('Curl does not installed.');
        }

        try {
            $ch = curl_init();

            if (!$ch || !is_resource($ch)) {
                throw new Exception(curl_error($ch));
            }

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            if (!empty($xml)) {
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-type: application/xml',
                    'Content-length: ' . strlen($xml)
                ]);

                $response = curl_exec($ch);
                curl_close($ch);

                if (!$response) {
                    throw new Exception('Response is not available.');
                }

                return new SimpleXMLElement($response);
            }

        } catch (Exception $error) {
            // echo $error->getMessage();
        }

        return simplexml_load_file($url);
    }

    /**
     * Для разных методов api перечень обязательных параметров различен. Здесь проверяется,
     * задан ли каждый из них и выбрасывается исключение, если нет.
     * @param mixed $parameter значение массива, передаваемое для проверки на существование.
     * @param string $name название пераметра
     * @return mixed возвращает переданное значение массива в случае его существования.
     * @throws Exception
     */
    private function requiredParameters($parameter, $name = '')
    {
        if (isset($parameter) && !empty($parameter)) {

            return $parameter;
        } elseif (!isset($parameter)) {
            throw new Exception('Missing parameter');
        } else {
            throw new Exception($name . ' is required.');
        }
    }

    /**
     * Преобразует массив с параметрами в строку запроса.
     * @param array $parameters массив с параметрами
     * @return string строка запроса
     */
    private function implodeParameters($parameters)
    {
        $result = '';
        foreach ($parameters as $name => $parameter) {
            $result .= $name . '=' . urlencode(trim($parameter)) . '&';
        }

        return substr($result, 0, strlen($result) - 1);
    }

    /**
     * Генерирует секретный ключ, защищающий запрос к api.
     * @param string $methodName метод, для которого генерируется ключ
     * @param string $parameters передаваемые параметры
     * @return string секретный ключ
     */
    private function getChecksum($methodName, $parameters)
    {
        return sha1($methodName . $parameters . $this->secretSalt);
    }

    /**
     * Возвращает ссылку на создание конференции с заданными параметрами.
     * @param array $creationParameters параметры конференции
     * @return string ссылка на создание конференции
     * @throws Exception
     */
    public function getCreateMeetingUrl($creationParameters)
    {
        $creationParameters['meetingId']   = $this->requiredParameters($creationParameters['meetingId'], 'meetingId');
        $creationParameters['meetingName'] = $this->requiredParameters($creationParameters['meetingName'],
            'meetingName');

        $parameters = $this->implodeParameters($creationParameters);

        return $this->serverUrl . 'api/create?' . $parameters . '&checksum=' . $this->getChecksum('create',
            $parameters);
    }

    /**
     * Создает конференцию с заданными и возвращает информацию о ней
     * @param array $creationParameters параметры создаваемой конференции (TODO: уточнить формат массива)
     * @param string $xml дополнительные параметры в виде xml
     * @return SimpleXMLElement информация о созданной конференции в виде объекта xml
     * @throws Exception
     */
    public function createMeeting($creationParameters, $xml = '')
    {
        return $this->xmlResponse($this->getCreateMeetingUrl($creationParameters), $xml);
    }

    /**
     * Генерирует ссылку на присоединение к конференции
     * @param array $joinParameters параметры конференции (TODO: уточнить формат массива)
     * @return string ссылка на присоединение к конференции
     * @throws Exception
     */
    public function getJoinMeetingUrl($joinParameters)
    {
        $joinParameters['meetingId'] = $this->requiredParameters($joinParameters['meetingId'], 'meetingId');
        $joinParameters['username']  = $this->requiredParameters($joinParameters['username'], 'username');
        $joinParameters['password']  = $this->requiredParameters($joinParameters['password'], 'password');

        $parameters = $this->implodeParameters($joinParameters);

        return $this->serverUrl . 'api/join?' . $parameters . '&checksum=' . $this->getChecksum('join', $parameters);
    }

    /**
     * Генерирует ссылку на завершение конференции
     * @param array $endParameters параметры завершаемой конференции (TODO: уточнить формат массива)
     * @return string ссылка на завершение конференции
     * @throws Exception
     */
    public function getEndMeetingUrl($endParameters)
    {
        $endParameters['meetingId'] = $this->requiredParameters($endParameters['meetingId'], 'meetingId');
        $endParameters['password']  = $this->requiredParameters($endParameters['password'], 'password');

        $parameters = $this->implodeParameters($endParameters);

        return $this->serverUrl . 'api/end?' . $parameters . '&checksum=' . $this->getChecksum('end', $parameters);
    }

    /**
     * Завершает конференцию с заданными параметрами и возвращает результат
     * @param array $endParameters параметры завершаемой конференции (TODO: уточнить формат массива)
     * @return SimpleXMLElement объект xml с результатом завершения
     * @throws Exception
     */
    public function endMeeting($endParameters)
    {
        return $this->xmlResponse($this->getEndMeetingUrl($endParameters));
    }

    /**
     * Ссылка, по которой можно проверить, запущена ли конференция с таким meetingId
     * @param array $parameters массив с одним элементом: meetingId => value
     * @return string ссылка на проверку
     * @throws Exception
     */
    public function getIsMeetingRunningUrl($parameters)
    {
        $parameters['meetingId'] = $this->requiredParameters($parameters['meetingId'], 'meetingId');
        $parameters              = $this->implodeParameters($parameters);

        return $this->serverUrl . 'api/isMeetingRunning?' . $parameters . '&checksum=' . $this->getChecksum('isMeetingRunning',
            $parameters);
    }

    /**
     * Проверяет, запущена ли конференция с таким meetingId
     * @param array $parameters массив с одним элементом: meetingId => value
     * @return SimpleXMLElement результат проверки в виде объекта xml
     * @throws Exception
     */
    public function isMeetingRunning($parameters)
    {
        return $this->xmlResponse($this->getIsMeetingRunningUrl($parameters));
    }

    /**
     * Возвращает ссылку для получения всех запущенных (TODO: или не только запущенных?)
     * конференций
     * @return string ссылка для получения всех запущенных конференций
     */
    public function getMeetingsUrl()
    {
        return $this->serverUrl . 'api/getMeetings?checksum=' . $this->getChecksum('getMeetings', '');
    }

    /**
     * Возвращает информацию обо всех запущенных (TODO: или не только запущенных?)
     * конференциях
     * @return SimpleXMLElement ответ api в виде объекта xml
     * @throws Exception
     */
    public function getMeetings()
    {
        return $this->xmlResponse($this->getMeetingsUrl());
    }

    /**
     * Возвращает ссылку на получение информации о конференции
     * @param array $infoParameters параметры конференции (TODO: уточнить формат массива)
     * @return string ссылка на получение информации о конференции
     * @throws Exception
     */
    public function getMeetingInfoUrl($infoParameters)
    {
        $infoParameters['meetingId'] = $this->requiredParameters($infoParameters['meetingId'], 'meetingId');
        $infoParameters['password']  = $this->requiredParameters($infoParameters['password'], 'password');

        $parameters = $this->implodeParameters($infoParameters);

        return $this->serverUrl . 'api/getMeetingInfo?' . $parameters . '&checksum=' . $this->getChecksum('getMeetingInfo',
            $parameters);
    }

    /**
     * Получает информацию о конференции
     * @param array $infoParameters параметры конференции (TODO: уточнить формат массива)
     * @return SimpleXMLElement информация о конференции в виде объекта xml
     * @throws Exception
     */
    public function getMeetingInfo($infoParameters)
    {
        return $this->xmlResponse($this->getMeetingInfoUrl($infoParameters));
    }
}