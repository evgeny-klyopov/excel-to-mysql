<?php
namespace app\models;

use Yii;

class Helper {
    public function MultiInsert(
        $rows
        , $dbTableName
        , $insertFields = []
        , $updateFields = []
        , $type = 'update'
        , $sizePacketInsert = 300
        , $onDuplicateKeyUpdate = []
    ) {
        if(!$dbTableName) {
            throw new \yii\base\Exception('Не указана таблица для вставки данных');
        }

        if(count($rows) == 0) {
            throw new \yii\base\Exception('Данные для вставки не найдены');
        }

        if(!in_array($type, ['update', 'ignore'])) {
            throw new \yii\base\Exception('Не разрешённый тип вставки');
        }

        $packet = [];
        $packets = [];
        $i = 0;

        foreach($rows as $row) {
            if($i!=0 && $i%$sizePacketInsert == 0) {
                $packets[] = $packet;
                $packet = [];
            }

            if(!$insertFields) {
                $insertFields = array_keys($row);
            }

            $insert = [];
            foreach($insertFields as $field) {
                $insert[$field] = $row[$field];
            }

            $packet[] = $insert;

            $i++;
        }


        if(count($packet) > 0) {
            $packets[] = $packet;
        }

        if(!$updateFields) {
            $updateFields = $insertFields;
        }

        $affectedRows = 0;
        if(count($packets) > 0) {
            if($type == 'update') {
                if(count($onDuplicateKeyUpdate) == 0) {
                    foreach($updateFields as $field) {
                        $onDuplicateKeyUpdate[] = $field.' = VALUES('.$field.')';
                    }
                }
                $onDuplicateKeyUpdate = ' ON DUPLICATE KEY UPDATE '.implode(', ', $onDuplicateKeyUpdate);
            }

            foreach($packets as $packet) {
                $query = Yii::$app->db->createCommand()->batchInsert($dbTableName, $insertFields, $packet)->getRawSql();

                if($type == 'update') {
                    $query = $query.$onDuplicateKeyUpdate;
                } else if($type == 'ignore') {
                    $query = 'INSERT IGNORE'.mb_substr($query, strlen('INSERT'));
                }

                $affectedRows += Yii::$app->db->createCommand($query)->execute();
            }
        }

        return $affectedRows;
    }

    public function getDsnAttribute($name, $dsn) {
        if(preg_match('/' . $name . '=([^;]*)/', $dsn, $match)) {
            return $match[1];
        } else {
            return null;
        }
    }

    public function setId($model, $rows, $field = 'id') {
        $nextId = $model->find()->max($field);
        foreach($rows as &$row) {
            if(!$row[$field]) {
                $row[$field] = ++$nextId;
            }
        }
        unset($row);

        return $rows;
    }
}