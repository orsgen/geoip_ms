<?php
namespace geoip_ms\models;

use yii\base\Model;
use yii\httpclient\Client;

class Main extends Model{

    /**
     * Проверка валидности полученных данных.
     * Спецификация предусматривает формат JSON {ip:ip-address},
     * которая средствами yii2 преобразована в массив аналогичной структуры.
     *
     * Возвращает true, если данные валидны или
     * собственное сообщение при нарушении имени ключа (ip) массива или
     * сообщение об ошибке от yii2 IpValidator() при несоответствии значения
     * синтаксису для ip-адресов
     *
     * @param array $data (
     * @return bool|string
     */
    public static function IsValid(array $data) {

        if(!array_key_exists('ip', $data)) {
            return "Error:: Data in post haven't key 'ip'!";
        }
        $validator = new \yii\validators\IpValidator();
        $error = '';
        if ($validator->validate($data['ip'], $error)) {
            return true;
        } else {
            return $error;
        }
    }

    /**
     * Для удобства получения напоминания о спецификации сервиса при получении запроса GET возвращает
     * текст для прочтения (например, в браузере). При этом не предполагается отработка запросов в этом режиме.
     *
     * При получении корректных JSON-данных $request->data методом POST вызывается проверка соответствия спецификации сервиса
     * self::IsValid(array). При их соответствии вызывается метод self::GetGeoIp(array), в котором выбирается значнеие из кэша,
     * а при его отстутствии запрашивается внешний сервис. Спецификации внешнего сервиса добавлена в config.php и подключение другого
     * сервиса потребует только замены спецификации в config, если принципы его работы близки к использованному.
     * Также проверяется соблюдение требований внешнего сервиса по частоте обращений на основании данных, учитываемых самим сервисом и
     * передаваемых им в заголовках X-.. Так как сервис может вызываться асинхронно, то фиксацию состояния "спим до времени t" сохраняем в кэше
     * и проверяем его актуальность до обращения к внешнему API.
     *
     *
     * @return \yii\console\Response|\yii\web\Response
     * @throws \yii\web\HttpException
     */
    public static function GetIP() {

        $request = \Yii::$app->request;
        $response = \Yii::$app->response;

        if ($request->isPost) { /* POST запрос */
            $post_data = $request->post();

            //Is it IP posted?
            $er_msg = self::IsValid($post_data);
            if($er_msg===true) {
                $region = self::GetGeoIp($post_data);
                if(is_array($region)) {
                    $response->format = \yii\web\Response::FORMAT_JSON;
                    $response->data = $region;
                } else
                    throw new \yii\web\HttpException(409, "Error: Can't get region value for IP: ". $region);
            } else {
                throw new \yii\web\HttpException(400, 'The requested item isn\'t IP address or command: '. $er_msg);
            }
        } elseif($request->isGet) { //GET pfghjc
            $response->format = \yii\web\Response::FORMAT_HTML;
            $response->data = "Usage : http://". \Yii::$app->id. ".loc"."<br><br>".
                "Service get IP-address by method POST and return country code."."<br><br>".
                "POST data must be JSON-format for example {\"ip\":\"8.8.8.9\"}"."<br>".
                "Returned response in JSON-format too for success or fail result. For success example: {\"country_code\":\"US\"} ". "<br>".
                " For error example (JSON): {\"code\":400, \"message\":\"The requested item isn't IP address or ..\"}";
        } else {
            $response->format = \yii\web\Response::FORMAT_HTML;
            $response->data = "ERROR: Received request in ". $request->method. ", but waited POST!";
        }
        return $response;
   }

   public static function GetGeoIP($data) {

       $cache = \Yii::$app->cache;
       //Firstly, look cache for IP
       $country_code = $cache->get($data['ip']);

       //Get keys for return value (while only one)
       $global_params = \Yii::$app->params;
       $country_code_name = $global_params['country_code_name'];

       //IP found in cache - return array for return it for user in json
       if ($country_code !== false) {
           return array($country_code_name=>$country_code);
       }

       //Prepare external API call
       $url = $global_params['ext_geoip_url']. $data['ip']. $global_params['ext_adding'];
       $method = $global_params['ext_geoip_method'];

       //Put semaphore for prevent another instant of service call to external API
       //  (only single process can get data from API).
       $added = $cache->add('busy', 1);
       $waiting = 0;
       while(!$added && $waiting<$global_params['max_wait_timeout']) {
           //$sleep_time (msec)
           $sleep_time = rand(3,75);
           usleep($sleep_time);
           //$waiting (seconds)
           $waiting += $sleep_time/1000;
           $added = $cache->add('busy', 1);
       }

       //Check for exceeding max_wait_time limit (config)
       if($waiting>=$global_params['max_wait_timeout']) {
            throw new \yii\web\HttpException(409, 'Error: Amount queries too much! Exceeded max_wait_timeout limit: ');
            return array();
       }

       //Look for timeout demanded by external API frequency limit
       //Get saved time for need waiting (unix_time)
       $wait_to = $cache->get('wait_to');
       if($wait_to !== false)
            sleep(floor(microtime(true) - $wait_to)+1);

       //use yii2 curl query
       $client = new Client();
       $request = $client->createRequest([
           'requestConfig' => [
               'format' => \yii\httpclient\Client::FORMAT_URLENCODED
           ],
           'responseConfig' => [
               'format' => \yii\httpclient\Client::FORMAT_JSON
           ],])
           ->setMethod($method)
           ->setUrl($url)
           ->setHeaders(array('Accept'=>'application/json'));

       $response = $request->send();

       //External API generate special headers to inform about frequency of queries
       //x-rl - amount queries more in this portion, x-ttl - rest time (sec) for next 45 queries
       if($response->headers['x-rl']==0) {
           $cache->set('wait_to', microtime(true)+$response->headers['x-ttl']);
           //Log event about exceed limit ability
           \Yii::warning("Warning: Exceeded limit of queries! Next call must sleep for ". $response->headers['x-ttl']. " seconds");
       }
       //Delete semaphore. Another instant of process can use external API now
       $cache->delete('busy');
       //Prepare result
       if ($response->isOk) {
           //API return some errors as ok value
           $stat_ar_er = $global_params['response_status']['error'];
           $stat_ar_ok = $global_params['response_status']['ok'];

           if($response->data[$stat_ar_ok['key']] == $stat_ar_ok['value']) {
               //Return result from external API & put it in cache
               $response_country_code_name = $global_params['response_ok']['country_code'];
               $result = array($country_code_name => $response->data[$response_country_code_name]);
                //Put result in cache
               $cache->set($data['ip'], $response->data[$response_country_code_name]);
               return $result;
           } elseif ($response->data[$stat_ar_er['key']] == $stat_ar_er['value']) {
               //Exaernal API return result error
               $er_msg = $global_params['response_error']['message'];
               throw new \yii\web\HttpException(409, 'Bad result of query to external geoip service: ' . $er_msg);
           } else {
               //Undocummented in external API status
               throw new \yii\web\HttpException(409, 'External API return undescribed status ' . $response->data[$stat_ar_er['key']]);
           }
       } else {
           //API result wrong http status code
           throw new \yii\web\HttpException($response->statusCode, 'External API return this error status!');
       }
       return array();
   }
}