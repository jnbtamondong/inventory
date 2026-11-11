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
include('../function_global.php');

 //error_reporting(E_ALL);

// create new PDF document
$pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('PhilHealth eKONSULTA System');
$pdf->SetTitle('eKonsulta Availment Slip');
$pdf->SetSubject('eKonsulta Availment Slip');

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, "", "ELECTRONIC KONSULTA AVAILMENT SLIP (eKAS)");

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
//hci details
$accreno = $_SESSION['pAccreNum'];
$hciname = $_SESSION['pHospName'];
$userid = $_SESSION['pUserID'];
$usename = $_SESSION['pUserLname'].' '.$_SESSION['pUserFname'].' '.$_SESSION['pUserMname'].' '.$_SESSION['pUserSuffix'];

//patients info
$transno=$_GET['transno'];

if($transno[0] == "P"){
  $getResult = getProfilingTransactionForSlip($transno); 
  $pxATC = $getResult['PROFILE_OTP']; 
}

if($transno[0] == "S") {
  $getResult = getConsultationTransactionForSlip($transno); 
  $pxATC = $getResult['SOAP_OTP'];  
  $caseNo = $getResult["CASE_NO"];  
  $transNo = $getResult["TRANS_NO"]; 
  $consultationDate = $getResult["SOAP_DATE"]; 

  //konsulta services
  $getRecommendedDiagExams = getRecommendedDiagnosticExam($caseNo);
  $getPXRecordCBCResults = getLabCbc($caseNo);
  $getPXRecordCreatineResults = getLabCreatinine($caseNo);
  $getPXRecordUrinalysisResults = getLabUrinalysis($caseNo);
  $getPXRecordFecalysisResults = getLabFecalysis($caseNo);
  $getPXRecordChestXrayResults = getLabChestXray($caseNo);
  $getPXRecordSputumResults = getLabSputum($caseNo);
  $getPXRecordLipidResults = getLabLipidProfile($caseNo);
  // $getPXRecordFbsResults = getLabFbs($caseNo);

    // $getPXRecordFbsResultsHSA = getLabFbs($pCaseNo);
    $getPXRecordFbsResultsSOAP = getLabFbsSOAP($caseNo);

    if ($getPXRecordFbsResultsSOAP != null) {
      $getPXRecordFbsResults = getLabFbsSOAP($caseNo);
    } else {
      $getPXRecordFbsResults = getLabFbs($caseNo);
    }

    // $getPXRecordRbsResultsHSA = getLabRbs($pCaseNo);
    $getPXRecordRbsResultsSOAP = getLabRbsSOAP($caseNo);

    if ($getPXRecordRbsResultsSOAP != null) {
      $getPXRecordRbsResults = getLabRbsSOAP($caseNo);
    } else {
      $getPXRecordRbsResults = getLabRbs($caseNo);
    }

  $getPXRecordEcgResults = getLabEcg($caseNo);
  $getPXRecordOgttResults = getLabOgtt($caseNo);
  $getPXRecordPapSmearResults = getLabPapSmear($caseNo);
  $getPXRecordFOBResults = getLabFecalOccultBlood($caseNo);
  $getPXRecordHbA1cResults = getLabHba1c($caseNo);
  $getPXRecordPPDTestResults = getLabPPDTest($caseNo);    
  $getPXRecordOthersResults = getLabOthers($caseNo);  
  $getEkasOthersDiagResults = getEKASLabOthers($transNo);

}
 


$caseNo = $getResult["CASE_NO"];
$transNo = $getResult["TRANS_NO"];
$patientName = $getResult['PX_LNAME'].', '.$getResult['PX_FNAME'].' '.$getResult['PX_MNAME'].' '.$getResult['PX_EXTNAME'];
$pxType = $getResult['PX_TYPE'];    
$pxPin = $getResult['PX_PIN'];    
if($getResult['PX_MOBILE_NO'] != ""){
  $pxContactNo = $getResult['PX_MOBILE_NO'];   
} 
else if ($getResult['PX_LANDLINE_NO'] != "") {
  $pxContactNo = $getResult['PX_LANDLINE_NO'];    
}
else {
  $pxContactNo = "-";
}
  
  
//age
$px_RegisteredDate = date("m/d/Y",strtotime($getResult["ENLIST_DATE"]));
$dateRegister = new DateTime($px_RegisteredDate, new DateTimeZone('Asia/Manila'));
$pat_birthday = date("m/d/Y",strtotime($getResult["PX_DOB"]));
$datePxDoB = new DateTime($pat_birthday, new DateTimeZone('Asia/Manila'));
$getAgeServ = date_diff($dateRegister,$datePxDoB);
$descAgeServ = $getAgeServ->y." yr(s), ".$getAgeServ->m." mo(s), ".$getAgeServ->d." day(s)";


$getPxRecordProfileDetails = getPxRecordProfile($pxPin);




// create some HTML content
$report = '
                <table border="0" style="width: 100%;margi-top:20px;">
                    <col width="25%">
                    <col width="25%">
                    <col width="25%">
                    <col width="25%">
                    <tr>
                        <td style="text-align: left;" colspan="2">HCI Name: <u>&emsp;'.$hciname.'&emsp;</u></td>
                        <td style="text-align: left;" colspan="2">Case No.: <u>&emsp;'.$caseNo.'&emsp;</u></td>
                    </tr>
                    <tr>
                        <td style="text-align: left;" colspan="2">HCI Accreditation No.: <u>&emsp;'.$accreno.'&emsp;</u></td>
                        <td style="text-align: left;" colspan="2">Transaction No.: <u>&emsp;'.$transNo.'&emsp;</u></td>
                    </tr>
                    <tr><td colspan="4">&nbsp;</td></tr>
                    <tr>
                        <td style="text-align: left;" colspan="4">Client Name <i>(Pangalan ng Kliyente)</i>: <u>&emsp;'.strReplaceEnye($patientName).'&emsp;</u>&emsp;Age <i>(Edad)</i>: <u>&emsp;'.$getAgeServ->y.' yr(s) old&emsp;</u>&emsp;Contact No.: <u>&emsp;'.$pxContactNo.'&emsp;</u></td>
                    </tr>
                    <tr>
                        <td style="text-align: left;" colspan="2">PIN (PhilHealth Identification Number): <u>&emsp;'.$pxPin.'&emsp;</u></td>
                        <td style="text-align: left;" colspan="2">Membership Category: _____________</td>
                    </tr>
                    <tr>
                        <td style="text-align: left;" colspan="2">Membership Type: ';
                                if($pxType == "MEMBER") { 
                                    $report .= '<u>&emsp;&#10004;</u> MEMBER __ DEPENDENT';
                                }
                                else { 
                                    $report .= '__ MEMBER <u> &#10004; </u> DEPENDENT';
                                }
                         $report .= '</td>
                        <td style="text-align: left;" colspan="3">Authorization Transaction Code (ATC):<u>&emsp;'.$pxATC.'&emsp;</u></td>
                    </tr>
                </table>
                <br/><br/><br/>
                <table cellpadding="2">
                    <tr>                        
                        <td style="background-color:#D4D0CF;">
                            To be filled out by the facility <i>(pupunuan ng pasilidad)</i>
                        </td>
                    </tr>
                    <tr>
                        <td>
                           
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <!--Essential Services-->
                            <table border="1" class="table" style="width: 100%;">
                                <tr style="text-align: center;vertical-align:middle;">
                                    <td><br/><br/><br/><br/>Konsulta Services</td>
                                    <td><br/><br/><br/> (&#10004;) Performed <i>(nagawa)</i><br/>(X) Not performed <i>(hindi nagawa)</i></td>
                                    <td><br/><br/><br/>Date Performed <br/><i>(Petsa kung kelan ginawa)</i></td>
                                    <td>Performed by <br/>(Ginawa ni) <br/>(Initial/Signature of Health care Provider/technician)<br/>(Initial o Lagda ng Health care Provider/technician)</td>
                                </tr>
                                <tr>
                                    <td style="text-align: left;">History and Physical Examination (vitals, anthorpometrics...)</td>
                                    <td style="text-align: center;">';
                                        if($getPxRecordProfileDetails["PROF_DATE"] != NULL) { 
                                            $report .= '&#10004;';
                                        }
                                        else { 
                                            $report .= 'X';
                                        }
                                     $report .= '
                                    </td>                    
                                    <td style="text-align: center;">'.date('m/d/Y', strtotime($getPxRecordProfileDetails["PROF_DATE"])).'</td>
                                    <td>&nbsp;</td>
                                </tr> ';
                                if($transno[0] == "S") {
                                  $report .= ' <tr>
                                      <td style="text-align: left;">Consultation</td>
                                      <td style="text-align: center;">';
                                          if($consultationDate != NULL) { 
                                              $report .= '&#10004;';
                                          }
                                          else { 
                                              $report .= 'X';
                                          }
                                       $report .= '
                                      </td>                    
                                      <td style="text-align: center;">'.date('m/d/Y', strtotime($consultationDate)).'</td>
                                      <td>&nbsp;</td>
                                  </tr> 
                                  ';
                                }

                        //konsulta services                        
                        foreach($getRecommendedDiagExams as $getRecommendedDiagExam){
                              $getDiagCode = $getRecommendedDiagExam['DIAGNOSTIC_ID'];
                              $getDiagDesc = describeLabResults($getDiagCode);
                              $describeDiagExam = $getDiagDesc['DIAGNOSTIC_DESC'];

                              $isDrRecommended = $getRecommendedDiagExam['IS_DR_RECOMMENDED'];
                              $pxRemarks = $getRecommendedDiagExam['PX_REMARKS'];

                                //cbc
                                if ($getDiagCode == '1') {
                                  if($getPXRecordCBCResults['IS_APPLICABLE'] == 'D') { 
                                    if($getPXRecordCBCResults['LAB_DATE'] == NULL || $getPXRecordCBCResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordCBCResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordCBCResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordCBCResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //urinalysis
                                if ($getDiagCode == '2') {
                                  if($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'D') {
                                   if($getPXRecordUrinalysisResults['LAB_DATE'] == NULL || $getPXRecordUrinalysisResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordUrinalysisResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }


                                //fecalysis
                                if ($getDiagCode == '3') {
                                  if($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'D') {  
                                    if($getPXRecordFecalysisResults['LAB_DATE'] == NULL || $getPXRecordFecalysisResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFecalysisResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //chest x-ray
                                if ($getDiagCode == '4') {
                                  if($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordChestXrayResults['LAB_DATE'] == NULL || $getPXRecordChestXrayResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordChestXrayResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //sputum
                                if ($getDiagCode == '5') {
                                  if($getPXRecordSputumResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordSputumResults['LAB_DATE'] == NULL || $getPXRecordSputumResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordSputumResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordSputumResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordSputumResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }
                                

                                //lipid profile
                                if ($getDiagCode == '6') {
                                  if($getPXRecordLipidResults['IS_APPLICABLE'] == 'D') { 
                                     if($getPXRecordLipidResults['LAB_DATE'] == NULL || $getPXRecordLipidResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordLipidResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordLipidResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordLipidResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //fbs
                                if ($getDiagCode == '7') {
                                  if($getPXRecordFbsResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordFbsResults['LAB_DATE'] == NULL || $getPXRecordFbsResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFbsResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    } 
                                  } else if ($getPXRecordFbsResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordFbsResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //rbs
                                if ($getDiagCode == '19') {
                                  if($getPXRecordRbsResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordRbsResults['LAB_DATE'] == NULL || $getPXRecordRbsResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordRbsResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    } 
                                  } else if ($getPXRecordRbsResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordRbsResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //creatinine
                                if ($getDiagCode == '8') {
                                  if($getPXRecordCreatineResults['IS_APPLICABLE'] == 'D') {
                                   if($getPXRecordCreatineResults['LAB_DATE'] == NULL || $getPXRecordCreatineResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordCreatineResults['LAB_DATE']));
                                     $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordCreatineResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordCreatineResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //ecg
                                if ($getDiagCode == '9') {
                                  if($getPXRecordEcgResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordEcgResults['LAB_DATE'] == NULL || $getPXRecordEcgResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordEcgResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordEcgResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordEcgResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //pap smear
                                if ($getDiagCode == '13') {
                                  if($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'D') {  
                                   if($getPXRecordPapSmearResults['LAB_DATE'] == NULL || $getPXRecordPapSmearResults['LAB_DATE'] == "0000-00-00" ){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordPapSmearResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //ogtt
                                if ($getDiagCode == '14') {
                                  if($getPXRecordOgttResults['IS_APPLICABLE'] == 'D') { 
                                    if($getPXRecordOgttResults['LAB_DATE'] == NULL || $getPXRecordOgttResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordOgttResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordOgttResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordOgttResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //fobt
                                if ($getDiagCode == '15') {
                                  if($getPXRecordFOBResults['IS_APPLICABLE'] == 'D') {
                                   if($getPXRecordFOBResults['LAB_DATE'] == NULL || $getPXRecordFOBResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFOBResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordFOBResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordFOBResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //pdd
                                if ($getDiagCode == '17') {
                                  if($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordPPDTestResults['LAB_DATE'] == NULL || $getPXRecordPPDTestResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordPPDTestResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //hb1ac
                                if ($getDiagCode == '18') {
                                  if($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'D'){
                                    if($getPXRecordHbA1cResults['LAB_DATE'] == NULL || $getPXRecordHbA1cResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordHbA1cResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //others
                                if ($getDiagCode == '99') {
                                  if($getEkasOthersDiagResults['IS_APPLICABLE'] == 'D') {
                                    if($getEkasOthersDiagResults['LAB_DATE'] == NULL || $getEkasOthersDiagResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getEkasOthersDiagResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getEkasOthersDiagResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getEkasOthersDiagResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  } 
                                }

                         $report .= '  
                          <tr>
                            <td style="text-align: left;">'.$describeDiagExam.'';
                              if ($getDiagCode == '99') {
                                $report .= ' - '.$getEkasOthersDiagResults["OTH_DIAG_EXAM"];
                              }
                        $report .='</td>
                            <td style="text-align: center;">'.$isPerformed.'</td>
                            <td style="text-align: center;">'.$getLabDate.'</td>
                            <td></td>
                         </tr>';

                                }       
                        $report .= '    </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#D4D0CF;">
                           To be filled out by the client <i>(pupunuan ng kliyente)</i>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td colspan="2">
                                        <div style="text-align: left;">
                                            Have you received the above-mentioned essential services? ____ YES ____ NO
                                            <br/>
                                            <i>   (Natanggap mo ba ang mga essential services na nabanggit?)</i>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="text-align: left;">
                                            How satisfied are you with the services provided?
                                            <br/>
                                            <i>   (Gaano ka nasiyahan sa natanggap mong serbisyo?)</i>                                
                                        </div>
                                    </td>
                                    <td rowspan="2"><img src="../res/images/emoticons.png" width="180px" height="35px"/></td>
                                </tr>
                                <tr><td></td><td></td></tr>
                                <tr>
                                    <td colspan="2">
                                        For your comment, suggestion or complaint:
                                        <br/>
                                        <i>   (Para sa iyong komento, mungkahi o reklamo)</i>
                                       <br/><br/>_________________________________________________________________________________________________________
                                       <br/><br/>_________________________________________________________________________________________________________  
                                    </td>
                                </tr>
                            </table>          
                            <br/><br/><br/>
                            <table style="margin-top:20px;">
                                 <tr>
                                    <td colspan="2">Under the penalty of law, I attest that the information I provided in this slip are true and accurate.
                                            <br/><i>(Sa ilalim ng batas, pinatutunayan ko na ang impormasyong ibinigay ko ay totoo at tama)</i>
                                    </td>
                                </tr>
                                <tr><td></td><td></td></tr>
                                <tr><td></td><td></td></tr>
                                <tr>
                                    <td style="width:60%;">_________________________________________________</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>';
                                    if($getAgeServ->y <= 18){ 
                                $report .= 'Parents/Guardians Signature over Printed Name
                                            <br/>
                                            <i>(Lagda ng magulang/Tagapangalaga sa itaas ng isinulat na pangalan)</i>
                                            ';
                                    } else {
                                $report .= 'Signature over printed name of client
                                            <br/>
                                            <i>(Lagda sa nakalimbag na pangalan ng kliyente)</i>
                                            ';
                                    } 
                                $report .= '
                                    </td>                        
                                    <td>Next Consultation Date:: _________________________
                                        <br/>
                                        <i>(Petsa ng susunod na konsultasyon)</i>
                                    </td>
                                </tr>
                                <tr><td></td></tr>
                                <tr>
                                    <td>Note:<br/>Accomplished form shall be submitted to PhilHealth.
                                        <br/>
                                        <i>(Ang kumpletong form ay dapat isumite sa PhilHealth)</i>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>     
                <div style="text-align:right;font-style: italic;font-size:9px;">Prepared Date:'.date('m/d/Y h:i:sa').' Prepared By: '.$usename.'<br/>[HCIs Copy]</div>
            ';

// output the HTML content
$pdf->writeHTML($report, true, false, true, false, '');


// add a page
$pdf->AddPage();
// create some HTML content
$report = '
       <table border="0" style="width: 100%;margi-top:20px;">
                    <col width="25%">
                    <col width="25%">
                    <col width="25%">
                    <col width="25%">
                    <tr>
                        <td style="text-align: left;" colspan="2">HCI Name: <u>&emsp;'.$hciname.'&emsp;</u></td>
                        <td style="text-align: left;" colspan="2">Case No.: <u>&emsp;'.$caseNo.'&emsp;</u></td>
                    </tr>
                    <tr>
                        <td style="text-align: left;" colspan="2">HCI Accreditation No.: <u>&emsp;'.$accreno.'&emsp;</u></td>
                        <td style="text-align: left;" colspan="2">Transaction No.: <u>&emsp;'.$transNo.'&emsp;</u></td>
                    </tr>
                    <tr><td colspan="4">&nbsp;</td></tr>
                    <tr>
                        <td style="text-align: left;" colspan="4">Client Name <i>(Pangalan ng Kliyente)</i>: <u>&emsp;'.$patientName.'&emsp;</u>&emsp;Age <i>(Edad)</i>: <u>&emsp;'.$getAgeServ->y.' yr(s) old&emsp;</u>&emsp;Contact No.:  <u>&emsp;'.$pxContactNo.'&emsp;</u></td>
                    </tr>
                    <tr>
                        <td style="text-align: left;" colspan="2">PIN (PhilHealth Identification Number): <u>&emsp;'.$pxPin.'&emsp;</u></td>
                        <td style="text-align: left;" colspan="2">Membership Category: _____________</td>
                    </tr>
                    <tr>
                       <td style="text-align: left;" colspan="2">Membership Type: ';
                                if($pxType == "MEMBER") { 
                                    $report .= '<u>&emsp;&#10004;</u> MEMBER __ DEPENDENT';
                                }
                                else { 
                                    $report .= '__ MEMBER <u> &#10004; </u> DEPENDENT';
                                }
                         $report .= '</td>
                        <td style="text-align: left;" colspan="3">Authorization Transaction Code (ATC):<u>&emsp;'.$pxATC.'&emsp;</u></td>
                    </tr>
                </table>
                <br/><br/><br/>
                <table cellpadding="2">
                    <tr>                        
                        <td style="background-color:#D4D0CF;">
                            To be filled out by the facility <i>(pupunuan ng pasilidad)</i>
                        </td>
                    </tr>
                    <tr>
                        <td>
                           
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <!--Essential Services-->
                            <table border="1" class="table" style="width: 100%;">
                                <tr style="text-align: center;vertical-align:middle;">
                                    <td><br/><br/><br/><br/>Konsulta Services</td>
                                    <td><br/><br/><br/> (&#10004;) Performed <i>(nagawa)</i><br/>(X) Not performed <i>(hindi nagawa)</i></td>
                                    <td><br/><br/><br/>Date Performed <br/><i>(Petsa kung kelan ginawa)</i></td>
                                    <td>Performed by <br/>(Ginawa ni) <br/>(Initial/Signature of Health care Provider/technician)<br/>(Initial o Lagda ng Health care Provider/technician)</td>
                                </tr>
                                <tr>
                                    <td style="text-align: left;">History and Physical Examination (vitals, anthorpometrics...)</td>
                                    <td style="text-align: center;">';
                                        if($getPxRecordProfileDetails["PROF_DATE"] != NULL) { 
                                            $report .= '&#10004;';
                                        }
                                        else { 
                                            $report .= 'X';
                                        }
                                     $report .= '
                                    </td>                    
                                    <td style="text-align: center;">'.date('m/d/Y', strtotime($getPxRecordProfileDetails["PROF_DATE"])).'</td>
                                    <td>&nbsp;</td>
                                </tr> ';
                                if($transno[0] == "S") {
                                  $report .= ' <tr>
                                      <td style="text-align: left;">Consultation</td>
                                      <td style="text-align: center;">';
                                          if($consultationDate != NULL) { 
                                              $report .= '&#10004;';
                                          }
                                          else { 
                                              $report .= 'X';
                                          }
                                     $report .= '
                                      </td>                    
                                      <td style="text-align: center;">'.date('m/d/Y', strtotime($consultationDate)).'</td>
                                      <td>&nbsp;</td>
                                  </tr> ';
                                }
                                
                         //konsulta services
                        foreach($getRecommendedDiagExams as $getRecommendedDiagExam){
                              $getDiagCode = $getRecommendedDiagExam['DIAGNOSTIC_ID'];
                              $getDiagDesc = describeLabResults($getDiagCode);
                              $describeDiagExam = $getDiagDesc['DIAGNOSTIC_DESC'];

                              $isDrRecommended = $getRecommendedDiagExam['IS_DR_RECOMMENDED'];
                              $pxRemarks = $getRecommendedDiagExam['PX_REMARKS'];

                                //cbc
                                if ($getDiagCode == '1') {
                                  if($getPXRecordCBCResults['IS_APPLICABLE'] == 'D') { 
                                    if($getPXRecordCBCResults['LAB_DATE'] == NULL || $getPXRecordCBCResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordCBCResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordCBCResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordCBCResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //urinalysis
                                if ($getDiagCode == '2') {
                                  if($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'D') {
                                   if($getPXRecordUrinalysisResults['LAB_DATE'] == NULL || $getPXRecordUrinalysisResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordUrinalysisResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }


                                //fecalysis
                                if ($getDiagCode == '3') {
                                  if($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'D') {  
                                    if($getPXRecordFecalysisResults['LAB_DATE'] == NULL || $getPXRecordFecalysisResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFecalysisResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //chest x-ray
                                if ($getDiagCode == '4') {
                                  if($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordChestXrayResults['LAB_DATE'] == NULL || $getPXRecordChestXrayResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordChestXrayResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //sputum
                                if ($getDiagCode == '5') {
                                  if($getPXRecordSputumResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordSputumResults['LAB_DATE'] == NULL || $getPXRecordSputumResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordSputumResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordSputumResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordSputumResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }
                                

                                //lipid profile
                                if ($getDiagCode == '6') {
                                  if($getPXRecordLipidResults['IS_APPLICABLE'] == 'D') { 
                                     if($getPXRecordLipidResults['LAB_DATE'] == NULL || $getPXRecordLipidResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordLipidResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordLipidResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordLipidResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //fbs
                                if ($getDiagCode == '7') {
                                  if($getPXRecordFbsResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordFbsResults['LAB_DATE'] == NULL || $getPXRecordFbsResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFbsResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    } 
                                  } else if ($getPXRecordFbsResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordFbsResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //rbs
                                if ($getDiagCode == '19') {
                                  if($getPXRecordRbsResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordRbsResults['LAB_DATE'] == NULL || $getPXRecordRbsResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordRbsResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    } 
                                  } else if ($getPXRecordRbsResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordRbsResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }


                                //creatinine
                                if ($getDiagCode == '8') {
                                  if($getPXRecordCreatineResults['IS_APPLICABLE'] == 'D') {
                                   if($getPXRecordCreatineResults['LAB_DATE'] == NULL || $getPXRecordCreatineResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordCreatineResults['LAB_DATE']));
                                     $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordCreatineResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordCreatineResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //ecg
                                if ($getDiagCode == '9') {
                                  if($getPXRecordEcgResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordEcgResults['LAB_DATE'] == NULL || $getPXRecordEcgResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordEcgResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordEcgResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordEcgResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //pap smear
                                if ($getDiagCode == '13') {
                                  if($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'D') {  
                                   if($getPXRecordPapSmearResults['LAB_DATE'] == NULL || $getPXRecordPapSmearResults['LAB_DATE'] == "0000-00-00" ){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordPapSmearResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //ogtt
                                if ($getDiagCode == '14') {
                                  if($getPXRecordOgttResults['IS_APPLICABLE'] == 'D') { 
                                    if($getPXRecordOgttResults['LAB_DATE'] == NULL || $getPXRecordOgttResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordOgttResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordOgttResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordOgttResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //fobt
                                if ($getDiagCode == '15') {
                                  if($getPXRecordFOBResults['IS_APPLICABLE'] == 'D') {
                                   if($getPXRecordFOBResults['LAB_DATE'] == NULL || $getPXRecordFOBResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFOBResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordFOBResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordFOBResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //pdd
                                if ($getDiagCode == '17') {
                                  if($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordPPDTestResults['LAB_DATE'] == NULL || $getPXRecordPPDTestResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordPPDTestResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //hb1ac
                                if ($getDiagCode == '18') {
                                  if($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'D'){
                                    if($getPXRecordHbA1cResults['LAB_DATE'] == NULL || $getPXRecordHbA1cResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordHbA1cResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  }
                                }

                                //others
                                if ($getDiagCode == '99') {
                                  if($getEkasOthersDiagResults['IS_APPLICABLE'] == 'D') {
                                    if($getEkasOthersDiagResults['LAB_DATE'] == NULL || $getEkasOthersDiagResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "";
                                      $isPerformed = 'X';
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getEkasOthersDiagResults['LAB_DATE']));
                                      $isPerformed = '&#10004;';
                                    }
                                  } else if ($getEkasOthersDiagResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $isPerformed = "WAIVED";
                                  } else if ($getEkasOthersDiagResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $isPerformed = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $isPerformed = "REFUSED";
                                    } else {
                                      $isPerformed = "X";
                                    }
                                  } 
                                }

                         $report .= '  
                          <tr>
                            <td style="text-align: left;">'.$describeDiagExam.'';
                              if ($getDiagCode == '99') {
                                $report .= ' - '.$getEkasOthersDiagResults["OTH_DIAG_EXAM"];
                              }
                        $report .='</td>
                            <td style="text-align: center;">'.$isPerformed.'</td>
                            <td style="text-align: center;">'.$getLabDate.'</td>
                            <td></td>
                         </tr>';
                                }       
                        $report .= '    </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#D4D0CF;">
                           To be filled out by the client <i>(pupunuan ng kliyente)</i>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td colspan="2">
                                        <div style="text-align: left;">
                                            Have you received the above-mentioned essential services? ____ YES ____ NO
                                            <br/>
                                            <i>   (Natanggap mo ba ang mga essential services na nabanggit?)</i>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="text-align: left;">
                                            How satisfied are you with the services provided?
                                            <br/>
                                            <i>   (Gaano ka nasiyahan sa natanggap mong serbisyo?)</i>                                
                                        </div>
                                    </td>
                                    <td rowspan="2"><img src="../res/images/emoticons.png" width="180px" height="35px"/></td>
                                </tr>
                                <tr><td></td><td></td></tr>
                                <tr>
                                    <td colspan="2">
                                    For your comment, suggestion or complaint:
                                    <br/>
                                    <i>   (Para sa iyong komento, mungkahi o reklamo)</i>
                                    <br/><br/>_________________________________________________________________________________________________________
                                    <br/><br/>_________________________________________________________________________________________________________
                                    
                                    </td>
                                </tr>
                            </table>          
                            <br/><br/><br/>
                            <table style="margin-top:20px;">
                                 <tr>
                                    <td colspan="2">Under the penalty of law, I attest that the information I provided in this slip are true and accurate.
                                            <br/><i>(Sa ilalim ng batas, pinatutunayan ko na ang impormasyong ibinigay ko ay totoo at tama)</i>
                                    </td>
                                </tr>
                                <tr><td></td><td></td></tr>
                                <tr><td></td><td></td></tr>
                                <tr>
                                    <td style="width:60%;">_________________________________________________</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>';
                                    if($getAgeServ->y <= 18){ 
                                $report .= 'Parents/Guardians Signature over Printed Name
                                            <br/>
                                            <i>(Lagda ng magulang/Tagapangalaga sa itaas ng isinulat na pangalan)</i>
                                            ';
                                    } else {
                                $report .= 'Signature over printed name of client
                                            <br/>
                                            <i>(Lagda sa nakalimbag na pangalan ng kliyente)</i>
                                            ';
                                    } 
                                $report .= '
                                    </td>                        
                                    <td>Next Consultation Date:: _________________________
                                        <br/>
                                        <i>(Petsa ng susunod na konsultasyon)</i>
                                    </td>
                                </tr>
                                <tr><td></td></tr>
                                <tr>
                                    <td>Note:<br/>Accomplished form shall be submitted to PhilHealth.
                                        <br/>
                                        <i>(Ang kumpletong form ay dapat isumite sa PhilHealth)</i>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table> 
                <div style="text-align:right;font-style: italic;font-size:9px;">Prepared Date:'.date('m/d/Y h:i:sa').' Prepared By: '.$usename.'<br/>[Patients Copy]</div>
            ';

ob_end_clean();
// output the HTML content
$pdf->writeHTML($report, true, false, true, false, '');

// reset pointer to the last page
$pdf->lastPage();
// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output('eKAS_'.$pxPin.'_'.$transNo.'.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+

