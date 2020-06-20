<?php
namespace ExtensionsModel;

use Model\R;

require_once __DIR__ . '/../../../models/base.php';

class SongSearchModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_song_searchs';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['created_at', 'required', 'on'=>'create'],
        ];
    }

    public function getItems($data = array())
    {
        $sql = "SELECT t.*     
        FROM {tablePrefix}ext_song_searchs t 
        WHERE 1";

        $params = [];
        if (is_array($data) && isset($data['status'])) {
            $sql .= " AND t.status =:status";
            $params['status'] = $data['status'];
        }

        $date_from = date("Y-m-01");
        if (isset($data['date_from'])) {
            $date_from = date("Y-m-d", strtotime($data['date_from']));
        }

        $date_to = date("Y-m-d");
        if (isset($data['date_to'])) {
            $date_to = date("Y-m-d", strtotime($data['date_to']));
        }

        $sql .= " AND DATE_FORMAT(created_at, '%Y-%m-%d') BETWEEN :date_from AND :date_to";

        $params['date_from'] = $date_from;
        $params['date_to'] = $date_to;

        $sql .= " ORDER BY t.frequency DESC";

        if (isset($data['limit']))
            $sql .= ' LIMIT '.$data['limit'];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = \Model\R::getAll( $sql, $params );

        return $rows;
    }
}
