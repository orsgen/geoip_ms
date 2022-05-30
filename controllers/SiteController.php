<?php

namespace geoip_ms\controllers;

use geoip_ms\models\Main;
use geoip_ms\models\MyCache;
//use yii\base\ErrorException;
use yii\web\Controller;
//use yii\web\ErrorHandler;

class SiteController extends Controller
{
    /**
     * Microservice controller.
     *
     * Method of model return yii::Responce() object
     *
     * @return \yii\console\Response|\yii\web\Response
     * @throws \yii\web\HttpException
     */
    public function actionIndex()
    {
        //В методе модели получаем ip-address и подготавливаем ответ
        $response = Main::GetIP();
        return $response;

    }

    /**
     * Error controller.
     *
     * Exceptions catched here)
     *
     * Return error message for ms-caller.
     * Put it in log-file.
     * Logger has feature: It put in log not only code & message error, but stack trace too.
     *
     * Return object for transform by yii into JSON as {code:httpcode, message:errormessage}
     *
     * @return object|void
     * @throws \yii\base\InvalidConfigException
     */
    public function actionError()
    {
        $e = \Yii::$app->errorHandler->exception;

        if ($e !== null) {
            \Yii::error("Error: (". $e->statusCode. ") :: ". $e->getMessage());
            return
                \Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'code' => $e->statusCode,
                        'message' => $e->getMessage(),
                    ],
                ]);
        }
    }

}

