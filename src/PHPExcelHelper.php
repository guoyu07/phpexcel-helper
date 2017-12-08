<?php

/**
 * PHPExcel Helper
 * 
 * @author      Nick Tsai <myintaer@gmail.com>
 * @version     1.3.0
 * @filesource 	PHPExcel <https://github.com/PHPOffice/PHPExcel>
 * @see         https://github.com/yidas/phpexcel-helper
 * @example
 *  \PHPExcelHelper::newExcel()
 *      ->addRow(['ID', 'Name', 'Email'])
 *      ->addRows([
 *          ['1', 'Nick','myintaer@gmail.com'],
 *          ['2', 'Eric','eric@.....'],
 *      ])
 *      ->output('My Excel');
 */
class PHPExcelHelper
{
    /**
     * @var object Cached PHPExcel object
     */
    private static $_objExcel;
    
    /**
     * @var object Cached PHPExcel Sheet object
     */
    private static $_objSheet;

    /**
     * @var int Current row offset for the actived sheet
     */
    private static $_offsetRow;

    /**
     * @var int Current column offset for the actived sheet
     */
    private static $_offsetCol;

    /**
     * @var array Map of coordinates by keys
     */
    private static $_keyCoordinateMap;

    /**
     * @var array Map of column alpha by keys
     */
    private static $_keyColumnMap;

    /**
     * @var array Map of row number by keys
     */
    private static $_keyRowMap;

    /**
     * @var int Map of ranges by keys
     */
    private static $_keyRangeMap;

    /** 
     * New or set an PHPExcel object
     * 
     * @param object $phpexcelObj PHPExcel object
     * @return self
     */
    public static function newExcel($phpexcelObj=NULL)
    {
        self::$_objExcel = (is_object($phpexcelObj))
            ? $phpexcelObj
            : new \PHPExcel();
        
        return new static();
    }

    /** 
     * Get PHPExcel object from cache
     * 
     * @return object PHPExcel object
     */
    public static function getExcel()
    {
        return self::$_objExcel;
    }
    
    /** 
     * Reset cached PHPExcel sheet object and helper data
     * 
     * @return self
     */
    public static function resetSheet()
    {
        self::$_objSheet = NULL;
        self::$_offsetRow = 0;
        self::$_offsetCol = 0;

        return new static();
    }

    /** 
     * Set an active PHPExcel Sheet
     * 
     * @param object|int $sheet PHPExcel sheet object or index number
     * @param string $title Sheet title
     * @return self
     */
    public static function setSheet($sheet=0, $title=NULL)
    {
        self::resetSheet();

        if (is_object($sheet)) {
            
            self::$_objSheet = &$sheet;
        } 
        elseif (is_numeric($sheet) && $sheet>=0 && self::$_objExcel) {

            /* Sheets Check */
            $sheetCount = self::$_objExcel->getSheetCount();
            if ($sheet >= $sheetCount) {
                for ($i=$sheetCount; $i <= $sheet; $i++) { 
                    self::$_objExcel->createSheet($i);
                }
            }
            // Select sheet
            self::$_objSheet = self::$_objExcel->setActiveSheetIndex($sheet);
        }
        else {
            throw new Exception("Invalid or empty PHPExcel Object for setting sheet", 400);
        }

        // Sheet Title
        if ($title) {
            self::$_objSheet->setTitle($title);
        }

        return new static();
    }

    /** 
     * Get PHPExcel Sheet object from cache
     * 
     * @return object PHPExcel Sheet object
     */
    public static function getSheet()
    {
        return self::$_objSheet;
    }

    /** 
     * Set the offset of rows for the actived PHPExcel Sheet
     * 
     * @param int $var The offset number
     * @return self
     */
    public static function setRowOffset($var=0)
    {
        self::$_offsetRow = (int)$var;
        
        return new static();
    }

    /** 
     * Get the offset of rows for the actived PHPExcel Sheet
     * 
     * @return int The offset number
     */
    public static function getRowOffset()
    {
        return self::$_offsetCol;
    }

    /** 
     * Set the offset of columns for the actived PHPExcel Sheet
     * 
     * @param int $var The offset number
     * @return self
     */
    public static function setColumnOffset($var=0)
    {
        self::$_offsetCol = (int)$var;
        
        return new static();
    }

    /**
     * Add a row to the actived sheet of PHPExcel
     * 
     * @param array $rowData 
     *  @param mixed|array Cell value | Data set 
     *   Data set key-value:
     *   @param int 'col' Column span for mergence
     *   @param int 'row' Row span for mergence
     *   @param int 'skip' Column skip counter
     *   @param string|int 'key' Cell key for index
     *   @param mixed 'value' Cell value
     * @return self
     */
    public static function addRow($rowData)
    {
        $sheetObj = self::validSheetObj();
        
        // Column pointer
        $posCol = self::$_offsetCol;

        // Next row
        self::$_offsetRow++;
        
        foreach ($rowData as $key => $value) {
            
            // Optional Cell
            if (is_array($value)) {
                
                // Options
                $colspan = isset($value['col']) ? $value['col'] : 1;
                $rowspan = isset($value['row']) ? $value['row'] : 1;
                $skip = isset($value['skip']) ? $value['skip'] : 1;
                $key = isset($value['key']) ? $value['key'] : NULL;
                $value = isset($value['value']) ? $value['value'] : NULL;

                $sheetObj->setCellValueByColumnAndRow($posCol, self::$_offsetRow, $value);

                // Merge handler
                if ($colspan>1 || $rowspan>1) {
                    $posColLast = $posCol;
                    $posCol = $posCol + $colspan - 1;
                    $posRow = self::$_offsetRow + $rowspan - 1;
                    $mergeVal = self::num2alpha($posColLast).self::$_offsetRow
                        . ':'
                        . self::num2alpha($posCol).$posRow;
                    $sheetObj->mergeCells($mergeVal);
                }

                // Save key Map
                if ($key) {
                    $startColumn = self::num2alpha($posCol);
                    $startCoordinate = $startColumn. self::$_offsetRow;
                    // Range Map
                    if (isset($mergeVal)) {
                        self::$_keyRangeMap[$key] = $mergeVal;
                        // Reset column coordinate
                        $startColumn = self::num2alpha($posColLast);
                        $startCoordinate = $startColumn. self::$_offsetRow;
                    } 
                    elseif ($skip > 1) {
                        self::$_keyRangeMap[$key] = $startCoordinate
                            . ':'
                            . self::num2alpha($posCol+($skip-1)) . self::$_offsetRow;
                    } 
                    else {
                        self::$_keyRangeMap[$key] = "{$startCoordinate}:{$startCoordinate}";
                    }
                    // Coordinate & col-row Map
                    self::$_keyCoordinateMap[$key] = $startCoordinate;
                    self::$_keyColumnMap[$key] = $startColumn;
                    self::$_keyRowMap[$key] = self::$_offsetRow;
                }

                // Skip option
                $posCol += $skip;

            } else {

                $sheetObj->setCellValueByColumnAndRow($posCol, self::$_offsetRow, $value);
                
                $posCol++;
            }
        }

        return new static();
    }

    /**
     * Add rows to the actived sheet of PHPExcel
     * 
     * @param array array of rowData for addRow()
     * @return self
     */
    public static function addRows($data)
    {
         foreach ($data as $key => $row) {

            self::addRow($row);
        }

        return new static();
    }

    /** 
     * Output an Excel file
     * 
     * @param string $filename
     * @param string $excelType
     */
    public static function output($filename='excel', $excelType='Excel2007')
    {
        $objPHPExcel = self::validExcelObj();
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, $excelType);
        $objWriter->save('php://output');
        exit;
    }

    /**
     * Get Coordinate Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return string|array Coordinate string | Key-Coordinate array
     */
    public static function getCoordinateMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyCoordinateMap[$key]) ? self::$_keyCoordinateMap[$key] : NULL;
        } else {
            return self::$_keyCoordinateMap;
        }
    }

    /**
     * Get Column Alpha Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return string|array Column alpha string | Key-Coordinate array
     */
    public static function getColumnMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyColumnMap[$key]) ? self::$_keyColumnMap[$key] : NULL;
        } else {
            return self::$_keyColumnMap;
        }
    }

    /**
     * Get Row Number Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return int|array Row number | Key-Coordinate array
     */
    public static function getRowMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyRowMap[$key]) ? self::$_keyRowMap[$key] : NULL;
        } else {
            return self::$_keyRowMap;
        }
    }

    /**
     * Get Range Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return string|array Range string | Key-Range array
     */
    public static function getRangeMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyRangeMap[$key]) ? self::$_keyRangeMap[$key] : NULL;
        } else {
            return self::$_keyRangeMap;
        }
    }

    /**
     * Get Range of all actived cells from the actived sheet
     * 
     * @return string Range string
     */
    public static function getRangeAll()
    {
        $sheetObj = self::validSheetObj();
        
        return self::num2alpha(self::$_offsetCol). '1:'. $sheetObj->getHighestColumn(). $sheetObj->getHighestRow();
    }

    /**
     * Set WrapText for all cells or set by giving range to the actived sheet
     * 
     * @param string $range Cells range format
     * @param bool $value PHPExcel setWrapText() argument
     * @return self
     */
    public static function setWrapText($range=NULL, $value=true)
    {
        $sheetObj = self::validSheetObj();

        $range = ($range) ? $range : self::getRangeAll();

        $sheetObj->getStyle($range)
            ->getAlignment()
            ->setWrapText($value); 
        
        return new static();
    }

    /**
     * Set AutoSize for all cells or set by giving column range to the actived sheet
     * 
     * @param string $colAlphaStart Column Alpah of start
     * @param string $colAlphaEnd Column Alpah of end
     * @param bool $value PHPExcel AutoSize() argument
     * @return self
     */
    public static function setAutoSize($colAlphaStart=NULL, $colAlphaEnd=NULL, $value=true)
    {
        $sheetObj = self::validSheetObj();

        $colStart = ($colAlphaStart) ? self::alpha2num($colAlphaStart) : self::$_offsetCol;
        $colEnd = ($colAlphaEnd) 
            ? self::alpha2num($colAlphaEnd) 
            : self::alpha2num($sheetObj->getHighestColumn());

        foreach (range($colStart,$colEnd ) as $key => $colNum) {
            $sheetObj->getColumnDimension(self::num2alpha($colNum))->setAutoSize($value);
        }

        return new static();
    }

    /**
     * Number to Alpha
     * 
     * @example
     *  0 => A, 26 => AA
     * @param int $n column number
     * @return string Excel column alpha
     */
    public static function num2alpha($n)
    {
        $r = '';
        for ($i = 1; $n >= 0 && $i < 10; $i++) {
            $r = chr(0x41 + ($n % pow(26, $i) / pow(26, $i - 1))) . $r;
            $n -= pow(26, $i);
        }
        return $r;
    }

    /**
     * Alpha to Number
     * 
     * @example
     *  A => 0, AA => 26 
     * @param int $n Excel column alpha
     * @return string column number
     */
    public static function alpha2num($a)
    {
        $r = 0;
        $l = strlen($a);
        for ($i = 0; $i < $l; $i++) {
            $r += pow(26, $i) * (ord($a[$l - $i - 1]) - 0x40);
        }
        return $r - 1;
    }

    /**
     * Validate and return the selected PHPExcel Object
     * 
     * @param object $excelObj PHPExcel Object
     * @return object Cached object or given object
     */
    private static function validExcelObj($excelObj=NULL)
    {
        if (is_object($excelObj)) {

            return $excelObj;
        } 
        elseif (is_object(self::$_objExcel)) {

            return self::$_objExcel;
        } 
        else {
            
            throw new Exception("Invalid or empty PHPExcel Object", 400);
        }
    }

    /**
     * Validate and return the selected PHPExcel Sheet Object
     * 
     * @param object $excelObj PHPExcel Sheet Object
     * @return object Cached object or given object
     */
    private static function validSheetObj($sheetObj=NULL)
    {
        if (is_object($sheetObj)) {

            return $sheetObj;
        } 
        elseif (is_object(self::$_objSheet)) {

            return self::$_objSheet;
        } 
        elseif (is_object(self::$_objExcel)) {

            // Set to default sheet if is unset
            return self::setSheet()->getSheet();
        }
        else {
            
            throw new Exception("Invalid or empty PHPExcel Sheet Object", 400);
        }
    }
}
