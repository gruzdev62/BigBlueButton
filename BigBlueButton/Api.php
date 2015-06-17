<?php

namespace BigBlueButton;

use Exception;
use SimpleXMLElement;

/**
 * Class Api
 * @package BigBlueButton
 * @author fkulakov
 * @email fkulakov@gmail.com
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
     * @param string $methodName метод, к которому формируется запрос
     * @param mixed $parameters массив c параметрами запроса или пустая строка
     * @return string
     */
    private function getUrl($methodName, $parameters = '')
    {
        if ($parameters != '' && is_array($parameters)) {
            $parameters = $this->implodeParameters($parameters);

            return $this->serverUrl . 'api/' . $methodName . '?' . $parameters . '&checksum=' . $this->getChecksum($methodName, $parameters);
        }

        return $this->serverUrl . 'api/' . $methodName . '?checksum=' . $this->getChecksum($methodName, $parameters);
    }

    /**
     * Создает конференцию с заданными и возвращает информацию о ней
     * @param array $parameters параметры создаваемой конференции (TODO: уточнить формат массива)
     * @param string $xml дополнительные параметры в виде xml
     * @return SimpleXMLElement информация о созданной конференции в виде объекта xml
     * @throws Exception
     */
    public function createMeeting($parameters, $xml = '')
    {
        $parameters['meetingId']   = $this->requiredParameters($parameters['meetingId'], 'meetingId');
        $parameters['meetingName'] = $this->requiredParameters($parameters['meetingName'], 'meetingName');

        return $this->xmlResponse($this->getUrl('create', $parameters), $xml);
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
     * Завершает конференцию с заданными параметрами и возвращает результат
     * @param array $endParameters параметры завершаемой конференции (TODO: уточнить формат массива)
     * @return SimpleXMLElement объект xml с результатом завершения
     * @throws Exception
     */
    public function endMeeting($parameters)
    {
        $parameters['meetingId'] = $this->requiredParameters($parameters['meetingId'], 'meetingId');
        $parameters['password']  = $this->requiredParameters($parameters['password'], 'password');

        return $this->xmlResponse($this->getUrl('end', $parameters));
    }

    /**
     * Проверяет, запущена ли конференция с таким meetingId
     * @param array $parameters массив с одним элементом: meetingId => value
     * @return SimpleXMLElement результат проверки в виде объекта xml
     * @throws Exception
     */
    public function isMeetingRunning($parameters)
    {
        $parameters['meetingId'] = $this->requiredParameters($parameters['meetingId'], 'meetingId');

        return $this->xmlResponse($this->getUrl('isMeetingRunning', $parameters));
    }

    /**
     * Возвращает информацию обо всех запущенных (TODO: или не только запущенных?)
     * конференциях
     * @return SimpleXMLElement ответ api в виде объекта xml
     * @throws Exception
     */
    public function getMeetings()
    {
        return $this->xmlResponse($this->getUrl('getMeetings'));
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
    public function getMeetingInfo($parameters)
    {
        $parameters['meetingId'] = $this->requiredParameters($parameters['meetingId'], 'meetingId');
        $parameters['password']  = $this->requiredParameters($parameters['password'], 'password');

        return $this->xmlResponse($this->getUrl('getMeetingInfo', $parameters));
    }


}