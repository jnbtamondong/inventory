<?php
/**
 * Created by PhpStorm.
 * User: llantoz
 * Date: 3/9/2018
 * Time: 9:00 AM
 */
?>
<?php
require_once('../res/tcpdf/tcpdf.php');
include('../function.php');

// create new PDF document
$pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('PhilHealth Expanded PCB System');
$pdf->SetTitle('eXPS: Report');
$pdf->SetSubject('eXPS: Report');

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// set font
$pdf->SetFont('dejavusans', '', 8);

// add a page
$pdf->AddPage();
session_start();
$pUserId = $_SESSION['pUserID'];
$pHospName = $_SESSION['pHospName'];
$pHciNo = $_SESSION['pHciNum'];
$pAccreNo = $_SESSION['pAccreNum'];

$pUserLname=$_SESSION['pUserLname'];
$pUserFname=$_SESSION['pUserFname'];
$pUserMname=$_SESSION['pUserMname'];
$pUserSuffix=$_SESSION['pUserSuffix'];
$pReportType=$_SESSION['pSessionReportType'];
$pPatientType=$_SESSION['pSessionPatientType'];
$pFromDate=$_SESSION['pSessionFromDate'];
$pToDate=$_SESSION['pSessionToDate'];

switch($pReportType){
    case '1':
        $pReportTypeDesc = 'Registered';
        break;
    case '2':
        $pReportTypeDesc = 'Screened and Assessed';
        break;
    case '3':
        $pReportTypeDesc = 'Consulted';
        break;
    case '4':
        $pReportTypeDesc = 'Services Provided';
        break;
    case '5':
        $pReportTypeDesc = 'Laboratories';
        break;
    case '6':
        $pReportTypeDesc = 'Medicine';
        break;
    default:
        $pReportTypeDesc = '---';
}

switch($pPatientType){
    case 'All':
        $pPxTypeDesc = 'All';
        break;
    case 'MM':
        $pPxTypeDesc = 'Member';
        break;
    case 'DD':
        $pPxTypeDesc = 'Dependent';
        break;
    default:
        $pPxTypeDesc = '---';
}

$displayResult=generateReport($pReportType,$pPatientType,$pFromDate,$pToDate);
// create some HTML content
$report = '
    <br/>
        <div style="text-align:  center;font-size: 14px;">'.$pHospName.'</div>
    <br/>
    <div style="text-align:  center;font-size: 14px;text-transform: uppercase;">'.$pReportTypeDesc.' REPORT</div>
    <br/>
    <div>Total Number of '.$pReportTypeDesc.': '.count($displayResult).'<br/>For Period of <u>'.$pFromDate.'</u> to <u>'.$pToDate.'</u><br/></div>
    <!-- Registered Results -->
    <table border="1" style="font-size: 11px; text-align: center; width: 100%;">
        <thead>
            <tr style="font-size:13px;font-weight: bold;">
                <th width="5%">No.</th>
                <th width="20%">PIN</th>
                <th width="45%">Name</th>
                <th width="15%">Date</th>
                <th width="15%">Type</th>
            </tr>
        </thead>';
        for ($i = 0; $i < count($displayResult); $i++) {
            $pxPin = $displayResult[$i]['PX_PIN'];
            $pxLname = $displayResult[$i]['PX_LNAME'];
            $pxFname = $displayResult[$i]['PX_FNAME'];
            $pxMname = $displayResult[$i]['PX_MNAME'];
            $pxExtName = $displayResult[$i]['PX_EXTNAME'];
            $pxType = $displayResult[$i]['PX_TYPE'];
            if($pReportType == '1'){
                $transDate = $displayResult[$i]['TRANS_DATE'];
            }
            if($pReportType == '2'){
                $transDate = $displayResult[$i]['PROF_DATE'];
            }
            if($pReportType == '3'){
                $transDate = $displayResult[$i]['SOAP_DATE'];
            }

            $count=$i+1;
            $report .= '<tbody>           
            <tr>
                <td style="width:5%;">'.$count.'</td>
                <td style="width:20%;text-align:left;">'.$pxPin.'</td>
                <td style="width:45%;text-align:left;">'.$pxLname.', '.$pxFname.' '.$pxMname.' '.$pxExtName.'</td>
                <td style="width:15%;">'.$transDate.'</td>
                <td style="width:15%;">'.$pxType.'</td>';
            $report .= '</tr>
        </tbody>';
        }
$report.='</table>  
    <br/><br/><br/>
    <table>
        <tr>
            <td width="20%"><b>Prepared By:</b></td>
            <td>'.$pUserFname.'&nbsp;'.$pUserMname.'&nbsp;'.$pUserLname.'&nbsp;'.$pUserSuffix.'</td>
        </tr>
        <tr>
            <td><b>Prepared Date:</b></td>
            <td>'.date("m/d/Y").'</td>
        </tr>
    </table>';

// output the HTML content
$pdf->writeHTML($report, true, false, true, false, '');

// reset pointer to the last page
$pdf->lastPage();
// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output('print_report.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+

