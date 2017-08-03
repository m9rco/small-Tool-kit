<?php

/**
 * Excel处理
 * @author wlw <wangliwei@eventmosh.com> 2015-05-08
 * @modify jingwentian
 */

namespace library\Excel;

\Yaf\Loader::import(SITEBASE . 'library/Excel/Classes/PHPExcel.php');

class Excel
{
    private $phpExcel = null;
    private $rawExcelData  = '';
    private $fields        = '';
    private $fieldsKey     = '';

    public function __construct()
    {
        $this->phpExcel = new \PHPExcel();
    }

    public function setExcelFiled($count)
    {
        $letter = 'A';
        for($i = 1; $i <= $count; $i++) $letter++;
        return $letter;
    }

    /**
     * [exportToCondition Excel导出]
     * @DateTime 2016-10-19T14:58:50+0800
     * @param    [type]
     *                        [
     *                             $data =  [
     *                                 'rows'    => '您需要放入列中的多维关联数组',
     *                                 'fileds'  => '表头部分字段输出',
     *                                 'sheet'   => '标签名'
     *                              ];
     *                        ]
     * @param    string                   $file_name [输出文件名 || 建议-->：'人工验票-'.date('Y-m-d-H-i-s',time()) ]
     * @return   [type]                              [Excel]
     *
     */
    public function exportToCondition(array $data,$file_name = ''){
        if( empty($data) || !is_array($data) ) return false;
        $this->phpExcel->getProperties()->setCreator("Sporte")
            ->setLastModifiedBy("Sporte")
            ->setTitle("Powered by sporte")
            ->setSubject("Powered by sporte")
            ->setDescription("Powered by sporte")
            ->setKeywords("Powered by sporte")
            ->setCategory("Powered by sporte");

        $excelSheet = $this->phpExcel->getActiveSheet();
        $this->phpExcel->setactivesheetindex(0);//设置当前的sheet索引，用于后续的内容操作。
        $this->phpExcel->getActiveSheet()->setTitle($data['sheet']);//索引标题
        $row_conf = [];     //配置容器
        $increasing  = 0;   //标题栏自增
        $content_inc = 2;   //内容栏自增
        $excelSheet->getRowDimension(1)->setRowHeight(25);//设置标题行高
        //标题输出
        foreach($data['fileds'] as $fields_key =>$fileds_value ){
            $excelfiled = $this->setExcelFiled($increasing++);
            $row_conf[$fields_key]   = $excelfiled;
            $excelSheet->setcellvalue($excelfiled.'1', $fileds_value);
            $excelSheet->getColumnDimension($excelfiled)->setWidth(strlen($fileds_value)); //设置宽度
            $excelSheet->getStyle($excelfiled.'1')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);//让其表格线消失
            $excelSheet->getStyle($excelfiled.'1')->getFill()->getStartColor()->setARGB('#66CD00');//设置背景色
            $excelSheet->getStyle($excelfiled.'1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
            $excelSheet->getStyle($excelfiled.'1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //垂直居中
        }
        if(!empty($data['rows'])){
            //内容输出
            foreach($data['rows'] as $rowsValues){
                foreach ($rowsValues as $rows_key => $rows_values){
                    if(isset($row_conf[$rows_key])){
                        $line   =  $row_conf[$rows_key].$content_inc;
                        $excelSheet->setcellvalue($line, $rows_values);//输出其内容
                        $excelSheet->getStyle($line)->getAlignment()->setWrapText(true);//自动换行
                        $excelSheet->getStyle($line)->getAlignment()->setShrinkToFit(true);//字体变小以适应宽
                        $excelSheet->getStyle($line)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
                        $excelSheet->getStyle($line)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中
                    }
                }
                $content_inc+=1;
            }
        }else{
            $excelSheet->setcellvalue('A2', '暂无数据~');
            $excelSheet->getStyle('A2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //水平居中
            $excelSheet->mergeCells("A2:{$excelfiled}22");
        }
        $excelWriter = \PHPExcel_IOFactory::createWriter($this->phpExcel, 'Excel5');
        $excelWriter->setPreCalculateFormulas(false);
        ob_end_clean();//清除缓冲区,避免乱码

        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Content-Type:application/force-download');
        header('Content-Type:application/vnd.ms-execl');
        header('Content-Type:application/octet-stream');
        header('Content-Type:application/download');
        header("Content-Disposition:attachment;filename={$file_name}.xls");
        header('Content-Transfer-Encoding:binary');
        $excelWriter->save('php://output');
    }
}





