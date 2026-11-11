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
$pdf->SetAuthor('PhilHealth eKONSULTA Stand-alone System');
$pdf->SetTitle('ePrescription Slip');
$pdf->SetSubject('ePrescription Slip');

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, "", "ELECTRONIC PRESCRIPTION SLIP (ePresS)");

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

    $getResult = getConsultationTransactionForSlip($transno); 
    $pxATC = $getResult['SOAP_OTP'];    
    $getMeds = getConsultationTransactionForMedicine($transno);

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

//medicine


// create some HTML content
$report = '       
                <table border="0" style="width: 100%;margi-top:20px;">
                    <col width="10%">
                    <col width="40%">
                    <col width="10%">
                    <col width="40%">
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
                        <td style="text-align: left;" colspan="4">PIN (PhilHealth Identification Number): <u>&emsp;'.$pxPin.'&emsp;</u>&emsp;Membership Category: ________ &emsp;</td>
                    </tr>
                    <tr>
                        <td style="text-align: left;" colspan="4">Membership Type: ';
                                if($pxType == "MEMBER") { 
                                    $report .= '<u>&emsp;&#10004;</u> MEMBER __ DEPENDENT';
                                }
                                else { 
                                    $report .= '__ MEMBER <u> &#10004; </u> DEPENDENT';
                                }
                         $report .= '</td>
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
                            <!--Drug/Medicine List-->
                            <table border="1" class="table" style="width: 100%;">
                                <tr style="text-align: center;">
                                    <td><br/><br/><br/>Category<br><i>(Kategorya)</i></td>
                                    <td>Medicine<br/>Strength/ Form/ Volume<br/><i>(Gamot/Anyo/ Dami)</i></td>
                                    <td><br/><br/><br/>Quantity<br/><i>(bilang)</i></td>
                                    <td>Name of the Prescribing Physician<br/><i>(Pangalan ng nagresetang doktor)</i></td>
                                    <td style="text-align:left;"><br/><br/> (&#10004;) Dispensed<br/>(naibigay)<br/>(X) Not dispensed<br/><i>(hindi naibigay)</i></td>
                                    <td><br/><br/>Date Dispensed<br/><i>(Petsa kung kelan naibigay)</i></td>
                                    <td>Name of the Dispensing Personnel<br/><i>(Pangalan ng nagbigay)</i></td>
                                </tr>';
                                foreach($getMeds as $getMedicine) {
                                     if($getMedicine['GENERIC_NAME'] != null) {
                                        $medicineDesc = $getMedicine['GENERIC_NAME']; 
                                     }
                                     else if ($getMedicine['GEN_CODE'] != null) {
                                        $medicineCode = $getMedicine['GEN_CODE'];
                                        $medicine = descMedsGeneric($medicineCode);
                                        $medicineDesc = $medicine['GEN_DESC'];
                                     } else {
                                        $medicineDesc = 'NONE';
                                     }                  
                                     
                                     $category = $getMedicine['CATEGORY'];
                                     $strengthCode = $getMedicine['STRENGTH_CODE'];
                                     $strength = descMedsStrength($strengthCode);
                                     $strengthDesc = $strength['STRENGTH_DESC'];

                                     $formCode = $getMedicine['FORM_CODE'];
                                     $form = descMedsForm($formCode);
                                     $formDesc = $form['FORM_DESC'];

                                     $unitCode = $getMedicine['UNIT_CODE'];
                                     $unit = descMedsUnit($unitCode);
                                     $unitDesc = $unit['UNIT_DESC'];

                                     $prescDoctor = $getMedicine['PRESC_PHYSICIAN'];

                                     $qty = $getMedicine['QUANTITY'];

                                     $isDispensed = $getMedicine['IS_DISPENSED'];
                                     if($isDispensed == 'Y'){
                                        $isDispensedStatus = '&#10004;';
                                     } else {
                                        $isDispensedStatus = 'X';
                                     }

                                     $dispensedDate = $getMedicine['DISPENSED_DATE'];
                                     if ($dispensedDate == NULL || $dispensedDate == '0000-00-00') {
                                        $dispensedDateVal = ' - ';
                                     } else {
                                        $dispensedDateVal = date('m/d/Y', strtotime($dispensedDate));
                                     }

                                     $dispensingPersonnel = $getMedicine['DISPENSING_PERSONNEL'];
                                     $medsCategory = $getMedicine['CATEGORY'];

                                     $report .=  '<tr>';
                                     $report .=  '<td style="text-align: left;"> '.$category.' </td>';
                                     $report .=  '<td style="text-align: left;">';
                                     $report .=  $medicineDesc.' '.$strengthDesc.' '.$formDesc.' '.$unitDesc;
                                     $report .=  '</td>';
                                     $report .=  '<td style="text-align:center;">';
                                     $report .=  round($qty);
                                     $report .=  '</td>';
                                     $report .=  '<td>';
                                     $report .=  $prescDoctor;
                                     $report .=  '</td>';
                                     $report .=  '<td style="text-align:center;">'.$isDispensedStatus.'</td>';
                                     $report .=  '<td style="text-align:center;">'.$dispensedDateVal.'</td>';
                                     $report .=  '<td>'.$dispensingPersonnel.'</td>';
                                     $report .=  '</tr>'; 
                                }
                $report .= '</table>
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
                                            Did you receive the above mentioned medicines? ____ YES ____ NO
                                            <br/>
                                            <i>   (Natanggap mo ba ang mga gamot na nabanggit?)</i>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="text-align: left;">
                                            Are you satisfied with the medicines you received?
                                            <br/>
                                            <i>   (Nasiyahan ka ba sa mga gamot na natanggap mo?)</i>                                
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
                                    <td>Next Dispensing Date: _________________________
                                        <br/>
                                        <i>(Petsa ng susunod na bigay ng gamot)</i>
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
                
                <br/><br/><br/>
                
                <div style="text-align:right;font-style: italic;font-size:9px;">Prepared Date:'.date('m/d/Y h:i:sa').' Prepared By: '.$usename.'</div>
                <div style="text-align:right;">[HCIs Copy]</div>
            ';

// output the HTML content
$pdf->writeHTML($report, true, false, true, false, '');


// add a page
$pdf->AddPage();
// create some HTML content
$report = '
        <table border="0" style="width: 100%;margi-top:20px;">
                    <col width="10%">
                    <col width="40%">
                    <col width="10%">
                    <col width="40%">
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
                        <td style="text-align: left;" colspan="4">Client Name <i>(Pangalan ng Kliyente)</i>: <u>&emsp;'.$patientName.'&emsp;</u>&emsp;Age <i>(Edad)</i>: <u>&emsp;'.$getAgeServ->y.' yr(s) old&emsp;</u>&emsp;Contact No.: <u>&emsp;'.$pxContactNo.'&emsp;</u></td>
                    </tr>
                   <tr>
                        <td style="text-align: left;" colspan="4">PIN (PhilHealth Identification Number): <u>&emsp;'.$pxPin.'&emsp;</u>&emsp;Membership Category: ________ &emsp;</td>
                    </tr>
                    <tr>
                        <td style="text-align: left;" colspan="4">Membership Type: ';
                                if($pxType == "MEMBER") { 
                                    $report .= '<u>&emsp;&#10004;</u> MEMBER __ DEPENDENT';
                                }
                                else { 
                                    $report .= '__ MEMBER <u> &#10004; </u> DEPENDENT';
                                }
                         $report .= '</td>
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
                            <!--Drug/Medicine List-->
                            <table border="1" class="table" style="width: 100%;">
                                <tr style="text-align: center;">
                                    <td><br/><br/><br/>Category<br><i>(Kategorya)</i></td>
                                    <td>Medicine<br/>Strength/ Form/ Volume<br/><i>(Gamot/Anyo/ Dami)</i></td>
                                    <td><br/><br/><br/>Quantity<br/><i>(bilang)</i></td>
                                    <td>Name of the Prescribing Physician<br/><i>(Pangalan ng nagresetang doktor)</i></td>
                                    <td style="text-align:left;"><br/><br/> (&#10004;) Dispensed<br/>(naibigay)<br/>(X) Not dispensed<br/><i>(hindi naibigay)</i></td>
                                    <td><br/><br/>Date Dispensed<br/><i>(Petsa kung kelan naibigay)</i></td>
                                    <td>Name of the Dispensing Personnel<br/><i>(Pangalan ng nagbigay)</i></td>
                                </tr>';
                               foreach($getMeds as $getMedicine) {
                                     if($getMedicine['GENERIC_NAME'] != null) {
                                        $medicineDesc = $getMedicine['GENERIC_NAME']; 
                                     }
                                     else if ($getMedicine['GEN_CODE'] != null) {
                                        $medicineCode = $getMedicine['GEN_CODE'];
                                        $medicine = descMedsGeneric($medicineCode);
                                        $medicineDesc = $medicine['GEN_DESC'];
                                     } else {
                                        $medicineDesc = 'NONE';
                                     }             

                                     $category = $getMedicine['CATEGORY'];     
                                     
                                     $strengthCode = $getMedicine['STRENGTH_CODE'];
                                     $strength = descMedsStrength($strengthCode);
                                     $strengthDesc = $strength['STRENGTH_DESC'];

                                     $formCode = $getMedicine['FORM_CODE'];
                                     $form = descMedsForm($formCode);
                                     $formDesc = $form['FORM_DESC'];

                                     $unitCode = $getMedicine['UNIT_CODE'];
                                     $unit = descMedsUnit($unitCode);
                                     $unitDesc = $unit['UNIT_DESC'];

                                     $prescDoctor = $getMedicine['PRESC_PHYSICIAN'];

                                     $qty = $getMedicine['QUANTITY'];

                                     $isDispensed = $getMedicine['IS_DISPENSED'];
                                     if($isDispensed == 'Y'){
                                        $isDispensedStatus = '&#10004;';
                                     } else {
                                        $isDispensedStatus = 'X';
                                     }

                                     $dispensedDate = $getMedicine['DISPENSED_DATE'];
                                     if ($dispensedDate == NULL || $dispensedDate == '0000-00-00') {
                                        $dispensedDateVal = ' - ';
                                     } else {
                                        $dispensedDateVal = date('m/d/Y', strtotime($dispensedDate));
                                     }

                                     $dispensingPersonnel = $getMedicine['DISPENSING_PERSONNEL'];
                                     $medsCategory = $getMedicine['CATEGORY'];

                                     $report .=  '<tr>';
                                     $report .=  '<td style="text-align: left;"> '.$category.' </td>';
                                     $report .=  '<td style="text-align: left;">';
                                     $report .=  $medicineDesc.' '.$strengthDesc.' '.$formDesc.' '.$unitDesc;
                                     $report .=  '</td>';
                                     $report .=  '<td style="text-align:center;">';
                                     $report .=  round($qty);
                                     $report .=  '</td>';
                                     $report .=  '<td>';
                                     $report .=  $prescDoctor;
                                     $report .=  '</td>';
                                     $report .=  '<td style="text-align:center;">'.$isDispensedStatus.'</td>';
                                     $report .=  '<td style="text-align:center;">'.$dispensedDateVal.'</td>';
                                     $report .=  '<td>'.$dispensingPersonnel.'</td>';
                                     $report .=  '</tr>'; 
                                }
                $report .= '</table>
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
                                            Did you receive the above mentioned medicines? ____ YES ____ NO
                                            <br/>
                                            <i>   (Natanggap mo ba ang mga gamot na nabanggit?)</i>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="text-align: left;">
                                            Are you satisfied with the medicines you received?
                                            <br/>
                                            <i>   (Nasiyahan ka ba sa mga gamot na natanggap mo?)</i>                                
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
                                    <td>Next Dispensing Date: _________________________
                                        <br/>
                                        <i>(Petsa ng susunod na bigay ng gamot)</i>
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
                
                <br/><br/><br/>
                
                <div style="text-align:right;font-style: italic;font-size:9px;">Prepared Date:'.date('m/d/Y h:i:sa').' Prepared By: '.$usename.'</div>
                <div style="text-align:right;">[Patients Copy]</div>
            ';

// output the HTML content
$pdf->writeHTML($report, true, false, true, false, '');

// reset pointer to the last page
$pdf->lastPage();
// ---------------------------------------------------------

//Close and output PDF document
ob_end_clean();
$pdf->Output('ePresS_'.$pxPin.'_'.$transNo.'.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+

