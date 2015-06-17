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
     * @param array $parameters [
     * ***************** name                    => Имя конференции,
     * ***************** meetingID               => уникальный ID конференции (обязательно),
     * ***************** attendeePW              => пароль участника, по умолчанию случайный
     * ***************** moderatorPW             => пароль модератора, по умолчанию случайный,
     * ***************** welcome                 => приветственное сообщение,
     * ***************** dialNumber              => внешний номер конференции,
     * ***************** webVoice                => ? (TODO: уточнить),
     * ***************** logoutURL               => URL, на который происходит редирект при выходе из конференции,
     * ***************** record                  => запись конференции, по умолчанию false,
     * ***************** duration                => продолжительность конференции в минутах,
     * ***************** meta                    => дополнительные параметры для getMeetingInfo и getRecordings,
     * ***************** moderatorOnlyMessage    => сообщение, которое увидят в чате только модераторы,
     * ***************** autoStartRecording      => автоматический старт записи конференции, по умолчанию false,
     * ***************** allowStartStopRecording => разрешить начинать и заканчивать запись конференции вручную, по умолчанию true
     * **************** ]
     * @param string $xml информация о прелзагруженных документах в виде xml
     * @return SimpleXMLElement информация о созданной конференции в виде объекта xml
     * @throws Exception
     */
    public function createMeeting($parameters, $xml = '')
    {
        $parameters['meetingID'] = $this->requiredParameters($parameters['meetingId'], 'meetingId');

        return $this->xmlResponse($this->getUrl('create', $parameters), $xml);
    }

    /**
     * Генерирует ссылку на присоединение к конференции
     * @param array $parameters [
     * ***************** fullName     => Имя присоединяющегося участника (обязательно),
     * ***************** meetingID    => уникальный ID конференции (обязательно),
     * ***************** password     => пароль участника или модератора (обязательно),
     * ***************** createTime   => ? (TODO: уточнить),
     * ***************** userID       => уникальный ID участника,
     * ***************** webVoiceConf => ? (TODO: уточнить),
     * ***************** configToken  => ? (TODO: уточнить),
     * ***************** avatarURL    => линк на аватарку,
     * ***************** redirect     => ? (TODO: уточнить),
     * ***************** clientURL    => URL собственного BigBlueButton-клиента
     * **************** ]
     * @return string ссылка на присоединение к конференции
     * @throws Exception
     */
    public function joinMeeting($parameters)
    {
        $parameters['fullName']  = $this->requiredParameters($parameters['fullName'], 'fullName');
        $parameters['meetingID'] = $this->requiredParameters($parameters['meetingID'], 'meetingId');
        $parameters['password']  = $this->requiredParameters($parameters['password'], 'password');

        $parameters = $this->implodeParameters($parameters);

        return $this->xmlResponse($this->getUrl('join', $parameters));
    }

    /**
     * Завершает конференцию с заданными параметрами и возвращает результат
     * @param array $parameters [
     * ***************** meetingID => уникальный ID конференции (обязательно),
     * ***************** password  => пароль модератора конференции (обязательно),
     * **************** ]
     * @return SimpleXMLElement объект xml с результатом завершения
     * @throws Exception
     */
    public function endMeeting($parameters)
    {
        $parameters['meetingID'] = $this->requiredParameters($parameters['meetingID'], 'meetingID');
        $parameters['password']  = $this->requiredParameters($parameters['password'], 'password');

        return $this->xmlResponse($this->getUrl('end', $parameters));
    }

    /**
     * Проверяет, запущена ли конференция с таким meetingID
     * @param string $meetingID уникальный ID конференции
     * @return SimpleXMLElement результат проверки в виде объекта xml
     * @throws Exception
     */
    public function isMeetingRunning($meetingID)
    {
        $parameters['meetingID'] = $this->requiredParameters($meetingID, 'meetingId');

        return $this->xmlResponse($this->getUrl('isMeetingRunning', $parameters));
    }

    /**
     * Возвращает информацию обо всех конференциях
     * @return SimpleXMLElement ответ api в виде объекта xml
     * @throws Exception
     */
    public function getMeetings()
    {
        return $this->xmlResponse($this->getUrl('getMeetings'));
    }

    /**
     * Получает информацию о конференции
     * @param array $parameters [
     * ***************** meetingID => уникальный ID конференции (обязательно),
     * ***************** password  => пароль модератора конференции (обязательно),
     * **************** ]
     * @return SimpleXMLElement информация о конференции в виде объекта xml
     * @throws Exception
     */
    public function getMeetingInfo($parameters)
    {
        $parameters['meetingID'] = $this->requiredParameters($parameters['meetingId'], 'meetingId');
        $parameters['password']  = $this->requiredParameters($parameters['password'], 'password');

        return $this->xmlResponse($this->getUrl('getMeetingInfo', $parameters));
    }
}