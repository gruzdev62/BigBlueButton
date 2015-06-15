<?php

namespace BigBlueButton;

use Exception;
use SimpleXMLElement;

class Api
{
    private $secretSalt;
    private $serverUrl;
    private $curlTimeout;

    public function __construct()
    {
        $this->secretSalt  = '';
        $this->serverUrl   = '';
        $this->curlTimeout = 10;
    }

    private function xmlResponse($url, $xml = '')
    {
        if (!extension_loaded('curl' && !empty($xml))) {
            throw new Exception('Curl does not installed.');
        }

        try {
            $ch = curl_init();

            if (!$ch || !is_resource($ch)) {
                throw new Exception(curl_error($ch));
            }

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->curlTimeout);
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
            echo $error;
        }

        return simplexml_load_file($url);
    }

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

    private function implodeParameters($parameters)
    {
        $result = '';
        foreach ($parameters as $name => $parameter) {
            $result .= $name . '=' . urlencode(trim($parameter)) . '&';
        }

        return substr($result, 0, strlen($result) - 1);
    }

    private function getChecksum($methodName, $parameters)
    {
        return sha1($methodName . $parameters . $this->secretSalt);
    }

    public function getCreateMeetingUrl($creationParameters)
    {
        $creationParameters['meetingId']   = $this->requiredParameters($creationParameters['meetingId'], 'meetingId');
        $creationParameters['meetingName'] = $this->requiredParameters($creationParameters['meetingName'],
            'meetingName');

        $parameters = $this->implodeParameters($creationParameters);

        return $this->serverUrl . 'api/create?' . $parameters . '&checksum=' . $this->getChecksum('create',
            $parameters);
    }

    public function createMeeting($creationParameters, $xml = '')
    {
        return $this->xmlResponse($this->getCreateMeetingUrl($creationParameters), $xml);
    }

    public function getJoinMeetingUrl($joinParameters)
    {
        $joinParameters['meetingId'] = $this->requiredParameters($joinParameters['meetingId'], 'meetingId');
        $joinParameters['username']  = $this->requiredParameters($joinParameters['username'], 'username');
        $joinParameters['password']  = $this->requiredParameters($joinParameters['password'], 'password');

        $parameters = $this->implodeParameters($joinParameters);

        return $this->serverUrl . 'api/join?' . $parameters . '&checksum=' . $this->getChecksum('join', $parameters);
    }

    public function getEndMeetingUrl($endParameters)
    {
        $endParameters['meetingId'] = $this->requiredParameters($endParameters['meetingId'], 'meetingId');
        $endParameters['password']  = $this->requiredParameters($endParameters['password'], 'password');

        $parameters = $this->implodeParameters($endParameters);

        return $this->serverUrl . 'api/end?' . $parameters . '&checksum=' . $this->getChecksum('join', $parameters);
    }
}
