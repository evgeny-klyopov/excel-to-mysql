<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;
use app\models\Helper;
//use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


/**
 * Import
 */
class ImportController extends Controller
{
    private $defaultTable = 'import_excel_to_mysql';
    private $tableNumber = 1;
    private $fieldType = [];
    private $needCreateTable = false;

    /**
     * Import Excel to Mysql
     * @param string $fullFilePath  - FullPath to file
     * @param string $tableDb       - Table name in database
     * @param bool $allWorkSheets   - Import all worksheets, default false
     * @example ./yii import/excel-to-mysql 'S:\\test.xlsx'
     */
    public function actionExcelToMysql($fullFilePath, $tableDb = '', $allWorkSheets = false)
    {
        $this->stdout('File: ' . $fullFilePath . "\n", Console::FG_GREEN);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullFilePath);
        $worksheetsTitle = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $worksheets[$worksheet->getTitle()] = $worksheet->toArray();
        }

        $this->stdout('Worksheets title: ' . implode(', ', array_keys($worksheets)) . "\n", Console::FG_GREEN);

        $headers = [];
        foreach ($worksheets as $sheet => $rows) {
            if (empty($headers)) {
                $headers = array_shift($rows);
                $headers = array_diff($headers, ['']);
            }

            $this->stdout('Write worksheets - "' . $sheet . '"' . "\n", Console::FG_GREEN);
            $tableDb = $this->getTableName($tableDb);
            $this->stdout('Table in database - "' . $tableDb . '"' . "\n", Console::FG_GREEN);


            foreach ($rows as $numberRow => $row) {
                $newRow = [];

                foreach ($row as $key => $value) {
                    if (isset($headers[$key])) {
                        if (empty($this->fieldType[$headers[$key]]) || $this->fieldType[$headers[$key]] != 'text') {
                            $this->fieldType[$headers[$key]] = $this->getTypeValue($value, $this->fieldType[$headers[$key]]);
                        }

                        $newRow[$headers[$key]] = $value;
                    }
                }

                $rows[$numberRow] = $newRow;
            }

            if (true == $this->needCreateTable) {
                $this->stdout('Create table in database - "' . $tableDb . '"' . "\n", Console::FG_GREEN);
                $this->createTable($tableDb);
            } else {
                $this->stdout('Table "' . $tableDb . '" found' . "\n", Console::FG_GREEN);
            }

            $helpers = new Helper();
            $insert = $helpers->MultiInsert(
                $rows
                , $tableDb
                , $headers
                , []
                , 'ignore'
                , '5000'
            );

            $this->stdout('Insert in database - "' . $insert . '"' . "\n", Console::FG_GREEN);

            if (true == $allWorkSheets) {
                break;
            }
        }
    }

    private function getTableName($tableDb)
    {
        $tables = Yii::$app->db->createCommand('SHOW TABLES')->queryColumn();

        if (empty($tableDb)) {
            $tableDb = $this->defaultTable;

            if (in_array($tableDb, $tables)) {
                do {
                    $newTableDb = $tableDb . '_' . $this->tableNumber++;
                } while (in_array($newTableDb, $tables));
                $tableDb = $newTableDb;
            }
        }

        if (false == in_array($tableDb, $tables)) {
            $this->needCreateTable = true;
        }

        return $tableDb;
    }
    private function getTypeValue($value, $nowTypeColumn)
    {
        $value = trim($value);

        if (is_numeric($value)) {
            if (empty($nowTypeColumn) || $nowTypeColumn == 'integer') {
                return 'integer';
            }
        }

        try {
            $date = new \DateTime($value);
            if (empty($nowTypeColumn) || $nowTypeColumn == 'datetime') {
                return 'datetime';
            }
        } catch (\Exception $e) {

        }

        if (strlen($value) <= 255) {
            return 'string';
        }

        return 'text';
    }
    private function createTable($tableDb)
    {
        $migrate = new \yii\db\Migration();

        $fields = [];
        foreach ($this->fieldType as $field => $type) {
            $fields[$field] = $migrate->$type();
        }

        $migrate->createTable($tableDb, $fields);
    }
}