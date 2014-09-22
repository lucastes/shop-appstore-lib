<?php
/**
 * Created by PhpStorm.
 * User: eRIZ
 * Date: 16.09.14
 * Time: 10:43
 */

namespace Dreamcommerce;

use Dreamcommerce\Exceptions\HttpException;

/**
 * HTTP I/O abstraction
 * @package Dreamcommerce
 */
class Http
{

    /**
     * retry count before giving up on leaking bucket quota exceeding
     * @var int
     */
    public static $retryLimit = 5;

    /**
     * @return Http
     */
    public static function instance(){
        static $instance = null;

        if($instance===null){
            $instance = new Http();
        }

        return $instance;
    }

    /**
     * performs a GET request
     * @param string $url
     * @param array $query query string params
     * @param array $headers
     * @return string
     */
    public function get($url, $query = array(), $headers = array())
    {
        return $this->perform('get', $url, array(), $query, $headers);
    }

    /**
     * performs a POST request
     * @param $url
     * @param array $body form fields
     * @param array $query query string params
     * @param array $headers
     * @return string
     */
    public function post($url, $body = array(), $query = array(), $headers = array())
    {
        return $this->perform('post', $url, $body, $query, $headers);
    }

    /**
     * performs a PUT request
     * @param $url
     * @param array $body form fields
     * @param array $query query string params
     * @param array $headers
     * @return string
     */
    public function put($url, $body = array(), $query = array(), $headers = array())
    {
        return $this->perform('put', $url, $body, $query, $headers);
    }

    /**
     * performs a DELETE request
     * @param $url
     * @param array $query query string params
     * @param array $headers
     * @return string
     */
    public function delete($url, $query = array(), $headers = array())
    {
        return $this->perform('delete', $url, array(), $query, $headers);
    }

    /**
     * make a real request
     * @param $method HTTP method
     * @param $url
     * @param array $body
     * @param array $query
     * @param array $headers
     * @return array an array with two fields - "headers" and "data"
     * @throws Exceptions\HttpException
     */
    protected function perform($method, $url, $body = array(), $query = array(), $headers = array())
    {

        // determine allowed methods
        $methodName = strtoupper($method);

        if (!in_array($methodName, array(
            'GET', 'POST', 'PUT', 'DELETE'
        ))
        ) {
            throw new HttpException('', HttpException::METHOD_NOT_SUPPORTED);
        }

        // prepare request headers and fields
        $contextParams = array(
            'http' => array(
                'method' => $methodName
            ));

        // request body
        if ($methodName == 'POST' || $methodName == 'PUT') {
            $contextParams['http']['content'] = http_build_query($body);
        }

        // stringifying headers
        if ($headers) {
            $headersString = '';
            foreach ($headers as $k => $v) {
                $headersString .= $k . ': ' . $v . "\r\n";
            }
            $contextParams['http']['header'] = $headersString;
        }

        // make request stream context
        $ctx = stream_context_create($contextParams);

        // query string processing
        $processedUrl = $url;

        if ($query) {
            // URL has already query string, merge
            if (strpos($url, '?') !== false) {
                $components = parse_url($url);
                $params = array();
                parse_str($components['query'], $params);
                $params = $params + $query;
                $components['query'] = http_build_query($params);
                $processedUrl = http_build_url($components);
            } else {
                $processedUrl .= '?' . http_build_query($query);
            }
        }

        $lastRequestHeaders = array();

        // perform request
        $doRequest = function($url, $ctx) use(&$lastRequestHeaders) {
            $result = @file_get_contents($url, null, $ctx);
            if (!$result) {
                $lastRequestHeaders = $this->parseHeaders($http_response_header, true);

                throw new HttpException(
                    var_export($lastRequestHeaders),
                    HttpException::REQUEST_FAILED
                );
            }
            return $result;
        };

        // initialize requests counter
        $counter = self::$retryLimit;

        // perform request regarding counter limit and quotas
        while(true){
            try {
                $result = $doRequest($processedUrl, $ctx);
                break;
            }catch(HttpException $ex){
                // server pauses request for X seconds
                if(!empty($lastRequestHeaders['Retry-After'])){
                    if($counter<=0){
                        throw new HttpException(var_export($lastRequestHeaders, true), HttpException::QUOTA_EXCEEDED);
                    }
                    sleep($lastRequestHeaders['Retry-After']);
                }else{
                    // other error
                    throw $ex;
                }
            }

            $counter--;
        }

        // try to decode response
        $parsedPayload = @json_decode($result, true);
        if (!$parsedPayload) {
            throw new HttpException('', HttpException::MALFORMED_RESULT);
        }

        // process response headers
        $headers = $this->parseHeaders($http_response_header);

        return array(
            'data' => $parsedPayload,
            'headers' => $headers
        );
    }

    /**
     * transform headers from $http_response_header
     * @param string $src
     * @internal param $headers
     * @internal param $http_response_header
     * @return array
     */
    protected function parseHeaders($src)
    {
        $headers = array();
        foreach ($src as $i) {
            $row = explode(':', $i, 2);
            if(!isset($row[1])){
                $headers[] = $row[0];
                continue;
            }

            $key = trim($row[0]);
            $val = trim($row[1]);
            $headers[$key] = $val;
        }
        return $headers;
    }

} 