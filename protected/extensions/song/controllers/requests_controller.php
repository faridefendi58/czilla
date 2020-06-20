<?php

namespace Extensions\Controllers;

use Components\BaseController as BaseController;

class RequestsController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['GET'], '/search', [$this, 'search']);
        $app->map(['POST'], '/mark-as-checked/[{id}]', [$this, 'mark_as_checked']);
        $app->map(['POST'], '/delete-search/[{id}]', [$this, 'delete_search']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'update', 'delete', 'search', 'mark-as-checked', 'delete-search'],
                'users'=> ['@'],
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \ExtensionsModel\SongRequestModel();

        return $this->_container->module->render($response, 'songs/request_view.html', [
            'model' => $model,
            'rows' => $model->getRows(['status' => 'pending'])
        ]);
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (empty($args['id']))
            return false;

        $model = \ExtensionsModel\SongRequestModel::model()->findByPk($args['id']);

        if (isset($_POST['SongRequest'])){
            $model->name = $_POST['SongRequest']['artist_name'];
            $model->updated_at = date('Y-m-d H:i:s');
            $update = \ExtensionsModel\SongRequestModel::model()->update($model);
            if ($update) {
                $message = 'Your data is successfully updated.';
                $success = true;
            } else {
                $message = 'Failed to update your lyric.';
                $success = false;
            }
        }
    }

    public function delete($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\SongRequestModel::model()->findByPk($args['id']);
        $delete = \ExtensionsModel\SongRequestModel::model()->delete($model);
        if ($delete) {
            $message = 'Your data is successfully deleted.';
            echo true;
        }
    }

    public function search($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        //$model = new \Model\VisitorModel();
        $model = new \ExtensionsModel\SongSearchModel();
        $params = $request->getParams();

        $params['date_from'] = date("Y-m-01");
        if (isset($params['start'])) {
            $params['date_from'] = date("Y-m-d", $params['start']/1000);
        }

        $params['date_to'] = date("Y-m-d");
        if (isset($params['end'])) {
            $params['date_to'] = date("Y-m-d", $params['end']/1000);
        }

        /*$data = [
            'site_url' => $this->_settings['params']['site_url'],
            'date_from' => $params['date_from'],
            'date_to' => $params['date_to'],
            ];
        $rows = $model->getFrequentlySearch($data);*/
        if (!isset($params['status'])) {
            $params['status'] = 0;
        }
        $params['limit'] = 1000;
        $rows = $model->getItems($params);

        return $this->_container->module->render($response, 'songs/request_search.html', [
            'model' => $model,
            'rows' => $rows,
            'params' => $params
        ]);
    }

    public function mark_as_checked($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\SongSearchModel::model()->findByPk($args['id']);
        $model->status = 1;
        $model->checked_at = date('c');
        $update = \ExtensionsModel\SongSearchModel::model()->update($model);
        if ($update) {
            $message = 'Your data is successfully updated.';
            echo true;
        }
    }

    public function delete_search($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\SongSearchModel::model()->findByPk($args['id']);
        $delete = \ExtensionsModel\SongSearchModel::model()->delete($model);
        if ($delete) {
            $message = 'Your data is successfully deleted.';
            echo true;
        }
    }
}