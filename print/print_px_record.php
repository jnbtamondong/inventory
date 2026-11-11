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

// create new PDF document
$pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('eKONSULTA System');
$pdf->SetTitle('eKONSULTA Client Record');
$pdf->SetSubject('eKONSULTA System - Client Record');

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, "", "eKONSULTA System - Client Record");

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

$pPIN = $_GET['pin'];

    $getPxPersonalDetails = getPxRecordEnlist($pPIN);
    $pDateEnlisted = $getPxPersonalDetails['ENLIST_DATE'];
    $pPxName = $getPxPersonalDetails['PX_LNAME'].', '.$getPxPersonalDetails['PX_FNAME'].' '.$getPxPersonalDetails['PX_MNAME'].' '.$getPxPersonalDetails['PX_EXTNAME'];
    $pPxSex = $getPxPersonalDetails['PX_SEX'];
    $pPxDoB = date('F d, Y', strtotime($getPxPersonalDetails['PX_DOB']));
    $pDateEnlisted = date('m/d/yy', strtotime($getPxPersonalDetails['ENLIST_DATE']));
    $pPxType = $getPxPersonalDetails['PX_TYPE'];
    $pCaseNo = $getPxPersonalDetails['CASE_NO'];
    $pMobileNo = $getPxPersonalDetails['PX_MOBILE_NO'];
    $pLandlineNo = $getPxPersonalDetails['PX_LANDLINE_NO'];
    switch($pPxType){
      case "MM": 
      $pxType =  "Member";
      break;
      case "DD": 
      $pxType =  "Dependent";
      break;
      default:
      $pxType =  "-";
    }
    switch($pPxSex){
      case "M": 
      $pxSex =  "Male";
      break;
      case "F": 
      $pxSex =  "Female";
      break;
      default:
      $pxSex =  "-";
    }
    //profiling
    $getPxRecordProfileDetails = getPxRecordProfile($pPIN);
    $getPXRecordFamHist = getProfilingFamhistPerIndividual($pCaseNo);
    $getPXRecordMedHist = getProfilingMedHistPerIndividual($pCaseNo);
    $getPXRecordSurgHist = getProfilingSurghistPerIndividual($pCaseNo);
    
    //consultation
    $getPXRecordConsultationDetails = getConsultationPerIndividual($pCaseNo);
    // $getPXRecordConsultationIcds = getConsultationIcdPerIndividual($pCaseNo);

    //medicine
    $getPXRecordMedicines = getMedicinePerIndividual($pCaseNo);

    //labresults
    $getPXRecordLabResults = getConsultationDiagnosticPerIndividual($pCaseNo);

    $getPXRecordCBCResults = getLabCbc($pCaseNo);
    $getPXRecordCreatineResults = getLabCreatinine($pCaseNo);
    $getPXRecordUrinalysisResults = getLabUrinalysis($pCaseNo);
    $getPXRecordFecalysisResults = getLabFecalysis($pCaseNo);
    $getPXRecordChestXrayResults = getLabChestXray($pCaseNo);
    $getPXRecordSputumResults = getLabSputum($pCaseNo);
    $getPXRecordLipidResults = getLabLipidProfile($pCaseNo);
    $getPXRecordFbsResults = getLabFbs($pCaseNo);
    $getPXRecordEcgResults = getLabEcg($pCaseNo);
    $getPXRecordOgttResults = getLabOgtt($pCaseNo);
    $getPXRecordPapSmearResults = getLabPapSmear($pCaseNo);
    $getPXRecordFOBResults = getLabFecalOccultBlood($pCaseNo);
    $getPXRecordHbA1cResults = getLabHba1c($pCaseNo);
    $getPXRecordPPDTestResults = getLabPPDTest($pCaseNo);
    $getPXRecordOthersResults = getLabOthers($pCaseNo);
    $getPXRecordRbsResults = getLabRbs($pCaseNo);

    //hci address
    $getProvince = describeProvinceAddress($_SESSION['pHospAddProv']);
    $province = $getProvince['PROV_NAME'];
    $getMunicipality = describeMunicipalityAddress($_SESSION['pHospAddMun'], $_SESSION['pHospAddProv']);
    $municipality  = $getMunicipality['MUN_NAME'];
    $getBarangay = describeBarangayAddress($_SESSION['pHospAddBrgy'],$_SESSION['pHospAddMun'], $_SESSION['pHospAddProv']);
    $barangay = $getBarangay['BRGY_NAME'];


    $hciAddress = $barangay.', '.$municipality.', '.$province;
    
    //konsulta services
    $getRecommendedDiagExams = getRecommendedDiagnosticExam($pCaseNo);


// create some HTML content
$report = '
           <table style="width: 100%;">
                <col width="70%">
                <col width="30%">
                 <tr>
                   <td>
                     <div style="text-align: left;font-size: 16px;font-weight: bold;">'.$hciname.'</div>
                     <div style="text-align: left;font-size: 10;">'.$hciAddress.'<br/>PAN: '.$_SESSION['pAccreNum'].'</div>
                   </td>
                   <td border="1">
                     <div style="text-align: center;font-size: 14px;font-weight: bold;">'.date('F d, Y').'<br/>'.date('h:i:sa').'</div>
                     <div style="text-align: left;">Logged User:  <u>&emsp;'.$_SESSION['pUserLname'].', '. $_SESSION['pUserFname'].' '.$_SESSION['pUserMname'].'&emsp;</u>  
                       <br/>Employee Number: <u>&emsp;'.$_SESSION['pEmdID'].'&emsp;</u> <br/> 
                     </div>
                   </td>
                 </tr>
           </table>
           <br/><br/>
           <div style="margin-top: 20px;font-size:12px;font-weight:bold;text-decoration:underline;">CLIENT RECORD</div>
              <div style="text-align: left;"><b>'.strReplaceEnye($pPxName).'</b> 
                <br/>
                '.$pPIN.'
                <br/>                        
                Contact No.: <u>&emsp;'.$pMobileNo.' '.$pLandlineNo.'&emsp;</u>                          
              </div>
              <table style="width: 50%;margin-top: 10px;">
                <col width="50%">
                <col width="50%">
                <tr>
                   <td>Birthdate: <u>&emsp;'.$pPxDoB.'&emsp;</u></td>
                   <td>Blood Type: <u>&emsp;'.$getPxRecordProfileDetails["BLOOD_TYPE"].'&emsp;</u></td>    
                 </tr>
                 <tr>
                  <td>Sex: <u>&emsp;'.$pxSex.'&emsp;</u></td>
                  <td>Age: <u>&emsp;';
                    $getAge = explode(' ', $getPxRecordProfileDetails["PX_AGE"]);
                    $report .= ''.$getAge[0].'&emsp;</u></td>
                </tr>
              </table>
                <br/>  <br/>  
              <table border="1" cellpadding="4" style="width: 100%;">
                <tr style="background-color:#E0DEDE;">
                  <td>Family History</td>
                  <td>Personal/ Social History</td>
                  <td>Past Medical History</td>
                  <td>Past Surgical History</td>
                  <td>OB-Gyne History <br/><i>(if female)</i></td>
                  <td>Drug Allergies</td>
                </tr>

                  <tr>
                    <td style="text-align: left;">';
                      foreach($getPXRecordFamHist as $getPXRecordFamHists){
                        $medDiseaseDesc = describeMedDisease($getPXRecordFamHists['MDISEASE_CODE']);
                        $report .= ''.$medDiseaseDesc['MDISEASE_DESC'].'<br/>';
                      }
                      $report .= '
                    </td>
                    <td style="text-align: left;">Smoking: <u>&emsp;'.$getPxRecordProfileDetails["IS_SMOKER"].'&emsp;</u>
                      <br/>
                      Alcohol: <u>&emsp;'.$getPxRecordProfileDetails["IS_ADRINKER"].'&emsp;</u>
                      <br/>
                      Illicit Drugs: <u>&emsp;'.$getPxRecordProfileDetails["ILL_DRUG_USER"].'&emsp;</u>
                      <br/>
                      Sexually Active: <u>&emsp;'.$getPxRecordProfileDetails["IS_SEXUALLY_ACTIVE"].'&emsp;</u>                              
                    </td>
                    <td style="text-align: left;">';
                      
                      foreach($getPXRecordMedHist as $getPXRecordMedHists){
                        $medDiseaseDesc = describeMedDisease($getPXRecordMedHists['MDISEASE_CODE']);
                         $report .= ''.$medDiseaseDesc['MDISEASE_DESC'].'<br/>';
                      }
                       $report .= '
                    </td>
                    <td style="text-align: left;">';
                      foreach($getPXRecordSurgHist as $getPXRecordSurgHists){
                        $surgDesc = $getPXRecordSurgHists['SURG_DESC'];
                        if($getPXRecordSurgHists['SURG_DATE'] == NULL || $getPXRecordSurgHists['SURG_DATE'] == "0000-00-00" || $getPXRecordSurgHists['SURG_DATE'] == ""){
                          $surgDate = "";
                        } else {
                          $surgDate = date('m/d/y', strtotime($getPXRecordSurgHists['SURG_DATE']));
                        }
                        $report .= ''.$surgDate.' '.$surgDesc.'<br/>';
                      }
                      $report .= '
                    </td>
                    <td style="text-align: left;">';
                        if($getPxRecordProfileDetails["IS_APPLICABLE"] == 'N') {
                          $report .= 'N/A<br/>';
                        } else {
                     $report .= 'Menarche: <u> '.$getPxRecordProfileDetails["MENARCHE_PERIOD"].'&emsp;</u>
                      <br/>
                      LMP: <u> '.$getPxRecordProfileDetails["LAST_MENS_PERIOD"].'&emsp;</u>
                      <br/>
                      Period Duration: <u> '.$getPxRecordProfileDetails["PERIOD_DURATION"].'&emsp;</u>
                      <br/>
                      G: <u> '.$getPxRecordProfileDetails["PREG_CNT"].' </u>&emsp;P: <u> '.$getPxRecordProfileDetails["DELIVERY_CNT"].' </u><br/>
                      (<u> '.$getPxRecordProfileDetails["FULL_TERM_CNT"].' </u> - <u> '.$getPxRecordProfileDetails["PREMATURE_CNT"].' </u> - <u> '.$getPxRecordProfileDetails["ABORTION_CNT"].' </u> - <u> '.$getPxRecordProfileDetails["LIV_CHILDREN_CNT"].' </u>)';
                        }
                      $report .= '
                    </td>
                    <td style="text-align: left;"> - </td>
                  </tr>
              </table>
                <br/>  <br/>  
              <div style="margin-top: 20px;font-size:12px;font-weight:bold;text-decoration:underline;">PHILHEALTH INFORMATION</div>
                      <div style="text-align: left;">Date Registered: <u>&emsp;'.$pDateEnlisted.'&emsp;</u>
                        <br/>
                        Classification: <u>&emsp;'.$pxType.'&emsp;</u>                         
                      </div>

                      <table border="1" cellpadding="4" style="width: 100%;">                      
                          <col width="70%">
                          <col width="30%">

                          <tr style="background-color:#D3CFCF;">
                            <td>Konsulta Service</td>
                            <td>Date Received</td>
                          </tr>
                          
                          <tr>
                            <td style="text-align: left;">History and Physical Examination (vitals, anthorpometrics...) </td>
                            <td>'.date('m/d/Y', strtotime($getPxRecordProfileDetails["PROF_DATE"])).'</td>
                          </tr>';
                        foreach($getRecommendedDiagExams as $getRecommendedDiagExam){
                              $getDiagCode = $getRecommendedDiagExam['DIAGNOSTIC_ID'];
                              $getDiagDesc = describeLabResults($getDiagCode);
                              $describeDiagExam = $getDiagDesc['DIAGNOSTIC_DESC'];
                              $getOthRemarks = $getRecommendedDiagExam['OTH_REMARKS'];

                         $report .= '  
                          <tr>
                            <td style="text-align: left;">'.$describeDiagExam.'';
                                if ($getDiagCode == '99') {
                                    $report .= ' - '.$getOthRemarks.'';
                                }
                            $report .= '  
                            </td>
                            <td>';
                             //cbc
                                if ($getDiagCode == '1') {
                                  if($getPXRecordCBCResults['LAB_DATE'] == NULL || $getPXRecordCBCResults['LAB_DATE'] == "0000-00-00"){
                                  $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordCBCResults['LAB_DATE']));
                                  }
                                }

                                //urinalysis
                                if ($getDiagCode == '2') {
                                   if($getPXRecordUrinalysisResults['LAB_DATE'] == NULL || $getPXRecordUrinalysisResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordUrinalysisResults['LAB_DATE']));
                                  }
                                }


                                //fecalysis
                                if ($getDiagCode == '3') {
                                  if($getPXRecordFecalysisResults['LAB_DATE'] == NULL || $getPXRecordFecalysisResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordFecalysisResults['LAB_DATE']));
                                  }
                                }

                                //chest x-ray
                                if ($getDiagCode == '4') {
                                  if($getPXRecordChestXrayResults['LAB_DATE'] == NULL || $getPXRecordChestXrayResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordChestXrayResults['LAB_DATE']));
                                  }
                                }

                                //sputum
                                if ($getDiagCode == '5') {
                                  if($getPXRecordSputumResults['LAB_DATE'] == NULL || $getPXRecordSputumResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordSputumResults['LAB_DATE']));
                                  }
                                }
                                

                                //lipid profile
                                if ($getDiagCode == '6') {
                                   if($getPXRecordLipidResults['LAB_DATE'] == NULL || $getPXRecordLipidResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordLipidResults['LAB_DATE']));
                                  }
                                }

                                //fbs
                                if ($getDiagCode == '7') {
                                   if($getPXRecordFbsResults['LAB_DATE'] == NULL || $getPXRecordFbsResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordFbsResults['LAB_DATE']));
                                  }
                                }

                                //rbs
                                if ($getDiagCode == '19') {
                                   if($getPXRecordRbsResults['LAB_DATE'] == NULL || $getPXRecordRbsResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordRbsResults['LAB_DATE']));
                                  }
                                }

                                //creatinine
                                if ($getDiagCode == '8') {
                                   if($getPXRecordCreatineResults['LAB_DATE'] == NULL || $getPXRecordCreatineResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordCreatineResults['LAB_DATE']));
                                    }
                                }

                                //ecg
                                if ($getDiagCode == '9') {
                                  if($getPXRecordEcgResults['LAB_DATE'] == NULL || $getPXRecordEcgResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordEcgResults['LAB_DATE']));
                                  }
                                }

                                //pap smear
                                if ($getDiagCode == '13') {
                                   if($getPXRecordPapSmearResults['LAB_DATE'] == NULL || $getPXRecordPapSmearResults['LAB_DATE'] == "0000-00-00" ){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordPapSmearResults['LAB_DATE']));
                                  }
                                }

                                //ogtt
                                if ($getDiagCode == '14') {
                                  if($getPXRecordOgttResults['LAB_DATE'] == NULL || $getPXRecordOgttResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordOgttResults['LAB_DATE']));
                                  }
                                }

                                //fobt
                                if ($getDiagCode == '15') {
                                   if($getPXRecordFOBResults['LAB_DATE'] == NULL || $getPXRecordFOBResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordFOBResults['LAB_DATE']));
                                  }
                                }

                                //pdd
                                if ($getDiagCode == '17') {
                                  if($getPXRecordPPDTestResults['LAB_DATE'] == NULL || $getPXRecordPPDTestResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordPPDTestResults['LAB_DATE']));
                                  }
                                }

                                //hb1ac
                                if ($getDiagCode == '18') {
                                  if($getPXRecordHbA1cResults['LAB_DATE'] == NULL || $getPXRecordHbA1cResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                  } else {
                                    $getLabDate = date('m/d/Y', strtotime($getPXRecordHbA1cResults['LAB_DATE']));
                                  }
                                }

                                //others
                                if ($getDiagCode == '99') {
                                   if($getPXRecordOthersResults['LAB_DATE'] == NULL || $getPXRecordOthersResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordOthersResults['LAB_DATE']));
                                    }
                                }
                         $report .= ''.$getLabDate.'</td>
                         </tr>';
                            }
                         $report .= '
                        </tbody>
                      </table>

                  <br/>  <br/>  

                  <div style="margin-top: 20px;font-size:12px;font-weight:bold;text-decoration:underline;">CONSULTATION RECORDS</div> <br/> 
                       <table border="1" cellpadding="4" style="width: 100%;">
                          <tr style="background-color:#D3CFCF;">
                            <td>Date</td>
                            <td>Consultation ID</td>
                            <td>Attending Physician</td>
                            <td>Complaint</td>
                            <td>Diagnosis</td>
                            <td>Doctors Orders</td>
                          </tr>';
                            if($getPXRecordConsultationDetails != null) {
                              foreach($getPXRecordConsultationDetails as $getPXRecordConsultationDetail){
                                  $complaints = $getPXRecordConsultationDetail['SIGNS_SYMPTOMS'];
                                  $getComplaints =  explode(';', $complaints);
                                  $soapDate = $getPXRecordConsultationDetail['SOAP_DATE'];
                                  $consultID = $getPXRecordConsultationDetail['TRANS_NO'];
                                  $attendingPhysician = $getPXRecordConsultationDetail['PRESC_PHYSICIAN'];

                                  $getPXRecordConsultationIcds = getConsultationIcdPerIndividualPxRecord($consultID);
                              $report .= '
                              <tr >
                                <td style="text-align: left;">'.date('m/d/Y', strtotime($soapDate)).'</td>
                                <td style="text-align: left;">'.$consultID.'</td>
                                <td style="text-align: left;">'.$attendingPhysician.' </td>
                                <td style="text-align: left;">';
                                  
                                  if($complaints != null){
                                    foreach($getComplaints as $getComplaint){
                                      $getDesc = describeSignsSymptoms($getComplaint);
                                      $descComplaint = $getDesc['SYMPTOMS_DESC'];
                                         $report .= ''.$descComplaint.'<br/>';
                                                                       
                                    }
                                  } else {
                                    $report .= ' - ';
                                  }
                                  $report .= '
                                </td>
                                <td style="text-align: left;">';
                                  
                                    foreach($getPXRecordConsultationIcds as $getPXRecordConsultationIcd){
                                      $report .= ''.$getPXRecordConsultationIcd['ICD_CODE'].'<br/>';
                                    }
                                  $report .= '
                                </td>
                                <td style="text-align: left;">'.$getPXRecordConsultationDetail['REMARKS'].'</td>
                              </tr>';
                              } 
                            } else { 
                              $report .= '<tr><td colspan="6">No record found yet.</td></tr>';
                            }
                            $report .= '
                        </table>
                <br/><br/>
                <div style="margin-top: 20px;font-size:12px;font-weight:bold;text-decoration:underline;">LABORATORY/ IMAGING PROCEDURES</div> <br/> 
                       <table border="1" cellpadding="4" style="width: 100%;">
                          <tr style="background-color:#D3CFCF;">
                            <td width="20%">Laboratory/ Imaging Procedure</td>
                            <td width="20%">Lab Date</td>
                            <td width="20%">Consultation ID</td>
                            <td width="40%">Results</td>
                          </tr>';
                            
                             foreach($getRecommendedDiagExams as $getRecommendedDiagExam){
                              $getDiagCode = $getRecommendedDiagExam['DIAGNOSTIC_ID'];
                              $getDiagDesc = describeLabResults($getDiagCode);
                              $describeDiagExam = $getDiagDesc['DIAGNOSTIC_DESC'];
                              $getOthRemarks = $getRecommendedDiagExam['OTH_REMARKS'];


                              $isDrRecommended = $getRecommendedDiagExam['IS_DR_RECOMMENDED'];
                              $pxRemarks = $getRecommendedDiagExam['PX_REMARKS'];

                                //cbc
                                if ($getDiagCode == '1') {
                                   if($getPXRecordCBCResults['IS_APPLICABLE'] == 'D') { 
                                      if($getPXRecordCBCResults['LAB_DATE'] == NULL || $getPXRecordCBCResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                      } else {
                                        $getLabDate = date('m/d/Y', strtotime($getPXRecordCBCResults['LAB_DATE']));
                                      }

                                      $getTransNo = $getPXRecordCBCResults['TRANS_NO'];
                                      $getDescFindings = 'Hematocrit: '.$getPXRecordCBCResults['HEMATOCRIT'].' %<br/>';
                                      $getDescFindings .= 'Hemoglobin: '.$getPXRecordCBCResults['HEMOGLOBIN_G'].' g/dL<br/>';
                                      $getDescFindings .= 'MHC: '.$getPXRecordCBCResults['MHC_PG'].' pg/cell<br/>';
                                      $getDescFindings .= 'MHCH: '.$getPXRecordCBCResults['MCHC_GHB'].' g Hb/dL<br/>';
                                      $getDescFindings .= 'MCV: '.$getPXRecordCBCResults['MCV_UM'].' um^3<br/>';
                                      $getDescFindings .= 'WBC: '.$getPXRecordCBCResults['WBC_1000'].' x1,000 cells/mm^3uL<br/>';
                                      $getDescFindings .= 'Platelet: '.$getPXRecordCBCResults['PLATELET'].' Platelets/mcL<br/>';
                                    } else if ($getPXRecordCBCResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordCBCResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }

                                //urinalysis
                                else if ($getDiagCode == '2') {        
                                  if($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'D') {
                                     if($getPXRecordUrinalysisResults['LAB_DATE'] == NULL || $getPXRecordUrinalysisResults['LAB_DATE'] == "0000-00-00"){
                                        $getLabDate = "Not yet done";
                                      } else {
                                        $getLabDate = date('m/d/Y', strtotime($getPXRecordUrinalysisResults['LAB_DATE']));
                                      } 

                                    $getTransNo = $getPXRecordUrinalysisResults['TRANS_NO'];

                                    $getDescFindings = '<table cellspacing="5" cellpadding="2">';
                                    $getDescFindings .= '<tr>';                                                                   
                                    $getDescFindings .= '<td style="text-align:left;">Specific gravity: '.$getPXRecordUrinalysisResults['GRAVITY'].' </td><td style="text-align:left;">Crystals: '.$getPXRecordUrinalysisResults['CRYSTALS'].' /hpf </td>';
                                    $getDescFindings .= '</tr>';   
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Appearance: '.$getPXRecordUrinalysisResults['APPEARANCE'].' </td><td style="text-align:left;">Bladder cells: '.$getPXRecordUrinalysisResults['BLADDER_CELL'].' /hpf </td>';
                                    $getDescFindings .= '</tr>';   
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Color: '.$getPXRecordUrinalysisResults['COLOR'].' </td><td style="text-align:left;">Squamous cells:'.$getPXRecordUrinalysisResults['SQUAMOUS_CELL'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';                                      
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Glucose: '.$getPXRecordUrinalysisResults['GLUCOSE'].' </td><td style="text-align:left;">Tubular cells:'.$getPXRecordUrinalysisResults['TUBULAR_CELL'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';                                     
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Proteins: '.$getPXRecordUrinalysisResults['PROTEINS'].' </td><td style="text-align:left;">Broad casts:'.$getPXRecordUrinalysisResults['BROAD_CASTS'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';                           
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Ketones: '.$getPXRecordUrinalysisResults['KETONES'].' </td><td style="text-align:left;">Epithelial cell casts:'.$getPXRecordUrinalysisResults['EPITHELIAL_CAST'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';                        
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">pH: '.$getPXRecordUrinalysisResults['PH'].'  </td><td style="text-align:left;">Granular casts:'.$getPXRecordUrinalysisResults['GRANULAR_CAST'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';           
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Pus cells: '.$getPXRecordUrinalysisResults['PUS_CELLS'].'  </td><td style="text-align:left;">Hyaline casts:'.$getPXRecordUrinalysisResults['HYALINE_CAST'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';         
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Albumin: '.$getPXRecordUrinalysisResults['ALBUMIN'].'  mg/dl</td><td style="text-align:left;">Red blood cell casts:'.$getPXRecordUrinalysisResults['RBC_CAST'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';         
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">Red blood cells: '.$getPXRecordUrinalysisResults['RB_CELLS'].'  /hpf</td><td style="text-align:left;">Waxy casts:'.$getPXRecordUrinalysisResults['WAXY_CAST'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';    
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;">White blood cells: '.$getPXRecordUrinalysisResults['WB_CELLS'].'  /hpf</td><td style="text-align:left;">White cell casts:'.$getPXRecordUrinalysisResults['WC_CAST'].' /hpf</td>';
                                    $getDescFindings .= '</tr>';  
                                    $getDescFindings .= '<tr>';   
                                    $getDescFindings .= '<td style="text-align:left;" colspan="2">Bacteria: '.$getPXRecordUrinalysisResults['BACTERIA'].'  /hpf</td>';
                                    $getDescFindings .= '</tr>';  
                                    
                                    $getDescFindings .= '</table>';  
                                    } else if ($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordUrinalysisResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }


                                //fecalysis
                                else if ($getDiagCode == '3') {
                                  if($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'D') {  
                                    if($getPXRecordFecalysisResults['LAB_DATE'] == NULL || $getPXRecordFecalysisResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFecalysisResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordFecalysisResults['TRANS_NO'];
                                    
                                    $getDescFindings =  'RBC: '.$getPXRecordFecalysisResults['RBC'].' /hpf<br/>';
                                    $getDescFindings .= 'WBC: '.$getPXRecordFecalysisResults['WBC'].' /hpf<br/>';
                                    $getDescFindings .= 'Ova: '.$getPXRecordFecalysisResults['OVA'].' =/-<br/>';
                                    $getDescFindings .= 'Parasite: '.$getPXRecordFecalysisResults['PARASITE'].' =/-<br/>';
                                    $getDescFindings .= 'Occult Blood: '.$getPXRecordFecalysisResults['OCCULT_BLOOD'].'<br/>';
                                  } else if ($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordFecalysisResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }

                                //chest x-ray
                                else if ($getDiagCode == '4') {
                                  if($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordChestXrayResults['LAB_DATE'] == NULL || $getPXRecordChestXrayResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordChestXrayResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordChestXrayResults['TRANS_NO'];
                                    $getDescFindingsId = descChestXrayFindings($getPXRecordChestXrayResults['FINDINGS']);
                                    $getDescFindings = $getDescFindingsId['FINDING_DESC'];
                                  } else if ($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordChestXrayResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }

                                }

                                //sputum
                                else if ($getDiagCode == '5') {
                                  if($getPXRecordSputumResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordSputumResults['LAB_DATE'] == NULL || $getPXRecordSputumResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordSputumResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordSputumResults['TRANS_NO'];
                                    if($getPXRecordSputumResults['FINDINGS'] == '1') {
                                      $getResultSputum = 'Essentially Normal';
                                      $getSputumRem = '';
                                    } else if($getPXRecordSputumResults['FINDINGS'] == '2'){
                                       $getResultSputum = 'With Findings';
                                       $getSputumRem = $getPXRecordSputumResults['REMARKS'];
                                    } else {
                                       $getResultSputum = '';
                                       $getSputumRem = '';
                                    } 
                                    $getDescFindings = ''.$getResultSputum.'  '.$getSputumRem;                                  
                                  } else if ($getPXRecordSputumResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordSputumResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }
                                

                                //lipid profile
                                else if ($getDiagCode == '6') {
                                  if($getPXRecordLipidResults['IS_APPLICABLE'] == 'D') { 
                                    if($getPXRecordLipidResults['LAB_DATE'] == NULL || $getPXRecordLipidResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordLipidResults['LAB_DATE']));
                                    }

                                     $getTransNo = $getPXRecordLipidResults['TRANS_NO'];
                                     $getDescFindings = 'Total Cholesterol: '.$getPXRecordLipidResults['TOTAL'].' mg/dL<br/>';
                                     $getDescFindings .= 'HDL Cholesterol: '.$getPXRecordLipidResults['HDL'].' mg/dL<br/>';
                                     $getDescFindings .= 'LDL Cholesterol: '.$getPXRecordLipidResults['LDL'].' mg/dL<br/>';
                                     $getDescFindings .= 'Triglycerides: '.$getPXRecordLipidResults['TRIGLYCERIDES'].' mg/dL<br/>';
                                  } else if ($getPXRecordLipidResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                  } else if ($getPXRecordLipidResults['IS_APPLICABLE'] == 'X') {
                                    $getLabDate = "-";
                                    $getTransNo = "-";
                                    $getDescFindings = "DEFERRED";
                                  } else {
                                    $getLabDate = "-";
                                    $getTransNo = "-";

                                    if ($pxRemarks == "RF") {
                                      $getDescFindings = "REFUSED";
                                    } else {
                                      $getDescFindings = "NOT YET DONE";
                                    }
                                  }
                                }

                                //fbs
                                else if ($getDiagCode == '7') {
                                  if($getPXRecordFbsResults['IS_APPLICABLE'] == 'D') {
                                     if($getPXRecordFbsResults['LAB_DATE'] == NULL || $getPXRecordFbsResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFbsResults['LAB_DATE']));
                                    }

                                     $getTransNo = $getPXRecordFbsResults['TRANS_NO'];
                                     $getDescFindings = 'Glucose: '.$getPXRecordFbsResults['GLUCOSE_MG'].' mg/dL '.$getPXRecordFbsResults['GLUCOSE_MMOL'].' mmol/L';
                                   } else if ($getPXRecordFbsResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordFbsResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }

                                //rbs
                                else if ($getDiagCode == '19') {
                                  if($getPXRecordRbsResults['IS_APPLICABLE'] == 'D') {
                                     if($getPXRecordFbsResults['LAB_DATE'] == NULL || $getPXRecordRbsResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordRbsResults['LAB_DATE']));
                                    }

                                     $getTransNo = $getPXRecordRbsResults['TRANS_NO'];
                                     $getDescFindings = 'Glucose: '.$getPXRecordRbsResults['GLUCOSE_MG'].' mg/dL '.$getPXRecordRbsResults['GLUCOSE_MMOL'].' mmol/L';
                                   } else if ($getPXRecordRbsResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordRbsResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }

                                //creatinine
                                else if ($getDiagCode == '8') {
                                   if($getPXRecordCreatineResults['IS_APPLICABLE'] == 'D') {
                                     if($getPXRecordCreatineResults['LAB_DATE'] == NULL || $getPXRecordCreatineResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                      } else {
                                        $getLabDate = date('m/d/Y', strtotime($getPXRecordCreatineResults['LAB_DATE']));
                                      }

                                      $getTransNo = $getPXRecordCreatineResults['TRANS_NO'];
                                      $getDescFindings = ''.$getPXRecordCreatineResults['FINDINGS'].' mg/dL';
                                    } else if ($getPXRecordCreatineResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordCreatineResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }

                                //ecg
                                else if ($getDiagCode == '9') {
                                   if($getPXRecordEcgResults['IS_APPLICABLE'] == 'D') {
                                      if($getPXRecordEcgResults['LAB_DATE'] == NULL || $getPXRecordEcgResults['LAB_DATE'] == "0000-00-00"){
                                        $getLabDate = "Not yet done";
                                      } else {
                                        $getLabDate = date('m/d/Y', strtotime($getPXRecordEcgResults['LAB_DATE']));
                                      }

                                      $getTransNo = $getPXRecordEcgResults['TRANS_NO'];
                                      if($getPXRecordEcgResults['FINDINGS'] == '1') {
                                        $getResultEcg = 'Essentially Normal';
                                        $getEcgRem = '';
                                      } else if($getPXRecordEcgResults['FINDINGS'] == '2'){
                                         $getResultEcg = 'With Findings';
                                         $getEcgRem = $getPXRecordEcgResults['REMARKS'];
                                      } else {
                                         $getResultEcg = '';
                                         $getEcgRem = '';
                                      } 
                                      $getDescFindings = ''.$getResultEcg.'  '.$getEcgRem;
                                    } else if ($getPXRecordEcgResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordEcgResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    }
                                }

                                //pap smear
                                else if ($getDiagCode == '13') {
                                  if($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'D') {      
                                   if($getPXRecordPapSmearResults['LAB_DATE'] == NULL || $getPXRecordPapSmearResults['LAB_DATE'] == "0000-00-00" ){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordPapSmearResults['LAB_DATE']));
                                    }

                                     $getTransNo = $getPXRecordPapSmearResults['TRANS_NO'];
                                      $getDescFindings = 'Findings: '.$getPXRecordPapSmearResults['FINDINGS'].' <br/>';
                                      $getDescFindings .= 'Impression: '.$getPXRecordPapSmearResults['IMPRESSION'].' <br/>';
                                  } else if ($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordPapSmearResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    } 
                                }

                                //ogtt
                                else if ($getDiagCode == '14') {
                                  if($getPXRecordOgttResults['IS_APPLICABLE'] == 'D') { 
                                    if($getPXRecordOgttResults['LAB_DATE'] == NULL || $getPXRecordOgttResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordOgttResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordOgttResults['TRANS_NO'];
                                    $getDescFindings = 'Fastingl: '.$getPXRecordOgttResults['EXAM_FASTING_MG'].' mg/dL  '.$getPXRecordOgttResults['EXAM_FASTING_MMOL'].' mmol/L <br/>';
                                    $getDescFindings .= 'OGTT (1 Hour): '.$getPXRecordOgttResults['EXAM_OGTT_ONE_MG'].' mg/dL  '.$getPXRecordOgttResults['EXAM_OGTT_ONE_MMOL'].' mmol/L <br/>';
                                    $getDescFindings .= 'OGTT (2 Hours): '.$getPXRecordOgttResults['EXAM_OGTT_TWO_MG'].' mg/dL  '.$getPXRecordOgttResults['EXAM_OGTT_TWO_MMOL'].' mmol/L <br/>';

                                  } else if ($getPXRecordOgttResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordOgttResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    } 
                                }

                                //fobt
                                else if ($getDiagCode == '15') {
                                  if($getPXRecordFOBResults['IS_APPLICABLE'] == 'D') {
                                     if($getPXRecordFOBResults['LAB_DATE'] == NULL || $getPXRecordFOBResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordFOBResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordFOBResults['TRANS_NO'];
                                    if($getPXRecordFOBResults['FINDINGS'] == "P"){
                                      $getResultFob = "Positive";
                                    } else if ($getPXRecordFOBResults['FINDINGS'] == "N") {
                                      $getResultFob = "Negative";
                                    } else {
                                       $getResultFob = "-";
                                    }
                                    $getDescFindings = $getResultFob;
                                  } else if ($getPXRecordFOBResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordFOBResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    } 
                                }

                                //pdd
                                else if ($getDiagCode == '17') {
                                  if($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'D') {
                                    if($getPXRecordPPDTestResults['LAB_DATE'] == NULL || $getPXRecordPPDTestResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordPPDTestResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordPPDTestResults['TRANS_NO'];

                                    if($getPXRecordPPDTestResults['FINDINGS'] == "P"){
                                    $getResultPdd = "Positive";
                                    } else if ($getPXRecordPPDTestResults['FINDINGS'] == "N") {
                                      $getResultPdd = "Negative";
                                    } else {
                                       $getResultPdd = "-";
                                    }
                                    $getDescFindings = $getResultPdd;
                                  } else if ($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordPPDTestResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    } 

                                }

                                //hb1ac
                               else if ($getDiagCode == '18') {
                                  if($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'D'){
                                    if($getPXRecordHbA1cResults['LAB_DATE'] == NULL || $getPXRecordHbA1cResults['LAB_DATE'] == "0000-00-00"){
                                      $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordHbA1cResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordHbA1cResults['TRANS_NO'];
                                    $getDescFindings = $getPXRecordHbA1cResults['FINDINGS'].' mmol/mol';
                                  } else if ($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'W') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "WAIVED";
                                    } else if ($getPXRecordHbA1cResults['IS_APPLICABLE'] == 'X') {
                                      $getLabDate = "-";
                                      $getTransNo = "-";
                                      $getDescFindings = "DEFERRED";
                                    } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    } 
                                }

                                //others
                                else if ($getDiagCode == '99') {
                                  if($getPXRecordOthersResults['IS_APPLICABLE'] == 'D') {
                                   if($getPXRecordOthersResults['LAB_DATE'] == NULL || $getPXRecordOthersResults['LAB_DATE'] == "0000-00-00"){
                                    $getLabDate = "Not yet done";
                                    } else {
                                      $getLabDate = date('m/d/Y', strtotime($getPXRecordOthersResults['LAB_DATE']));
                                    }

                                    $getTransNo = $getPXRecordOthersResults['TRANS_NO'];
                                    $getDescFindings = $getPXRecordOthersResults['FINDINGS'];
                                  } else {
                                      $getLabDate = "-";
                                      $getTransNo = "-";

                                      if ($pxRemarks == "RF") {
                                        $getDescFindings = "REFUSED";
                                      } else {
                                        $getDescFindings = "NOT YET DONE";
                                      }
                                    } 
                                }

                            $report .= '
                            <tr>
                              <td style="text-align: left;">'.$describeDiagExam.'';  
                                    if ($getDiagCode == 'X') {
                                     $report .= ' - '.$getOthRemarks.'';
                                    }
                            $report .= '                                 
                              </td>
                              <td>'.$getLabDate.'</td>
                              <td>'.$getTransNo.'</td>
                              <td style="text-align: left;">'.$getDescFindings.'</td>
                            </tr>';
                            }  
                            $report .= '                               
                        </table>
                <br/><br/>
                <div style="margin-top: 20px;font-size:12px;font-weight:bold;text-decoration:underline;">DISPENSING MEDICINE</div> <br/> 
                       <table border="1" cellpadding="4" style="width: 100%;">
                          <tr style="background-color:#D3CFCF;">
                            <td>Prescription Date</td>
                            <td>Prescription ID</td>
                            <td>Medicine/ Drug <br/>(dosage,form)</td>
                            <td>Provided by</td>
                            <td>Consultation ID</td>
                            <td>Date Dispensed</td>
                          </tr>';                             
                          foreach($getPXRecordMedicines as $getPXRecordMedicine) {
                            $datePrescribe = date('m/d/Y', strtotime($getPXRecordMedicine['DATE_ADDED']));
                            $meds = $getPXRecordMedicine['DRUG_CODE'];
                            $medsOthers = $getPXRecordMedicine['GENERIC_NAME'];
                            $providedBy = $getPXRecordMedicine['PRESC_PHYSICIAN'];
                            $transNo = $getPXRecordMedicine['TRANS_NO'];
                            $isApplicable = $getPXRecordMedicine['IS_APPLICABLE'];
                            $isDispensed = $getPXRecordMedicine['IS_DISPENSED'];
                            $dateDispensed = $getPXRecordMedicine['DISPENSED_DATE'];
                            $descMeds = descMedsDrugCode($meds);

                            if ($dateDispensed == NULL || $dateDispensed == '0000-00-00') {
                              $dispensedDateVal = ' - ';
                           } else {
                              $dispensedDateVal = date('m/d/Y', strtotime($dateDispensed));
                           }

                            $report .= '
                            <tr>
                              <td>';
                                if($isApplicable != 'N'){ 
                                $report .= ''.$datePrescribe.''; } 
                                else{ 
                                $report .= 'N/A'; }       
                                $report .= '                          
                              </td>
                              <td>-</td>
                              <td style="text-align: left;">';
                                // if($isApplicable == 'Y'){ 
                                $report .= ''.$descMeds['DRUG_DESC'].'<br/>'.$medsOthers.''; 
                                // else { 
                                // $report .= ''.$medsOthers.''; } 
                                $report .= '                               
                              </td>
                              <td style="text-align: left;">';
                                if($isApplicable != 'N'){ 
                                $report .= ''.$providedBy.''; } 
                                else{ 
                                $report .= 'N/A'; } 
                                $report .= '                                 
                              </td>
                              <td>';
                              $report .= ''.$transNo.'';
                              $report .= '</td>
                              <td>';
                                if($isApplicable != 'N'){ 
                                $report .= ''.$dispensedDateVal.''; } 
                                else{ 
                                $report .= 'N/A'; }
                                $report .= '                                
                              </td>
                            </tr>';
                            }
                          $report .= '
                        </table>
            <br/>
            ';

// output the HTML content
$pdf->writeHTML($report, true, false, true, false, '');

// reset pointer to the last page
$pdf->lastPage();
// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output('eKAS_'.$pPIN.'_'.$transNo.'.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+

