// JavaScript Document

function validateChecksChiefComplaint() {
    var chksChief = document.getElementsByName('complaint[]');
    var checkCountChief = 0;

    for (var i = 0; i < chksChief.length; i++) {
        if (chksChief[i].checked) {
            checkCountChief++;
        }
    }
    if ( checkCountChief < 1) {
        return false;
    }
    return true;
}


/*20191212 for Next Button*/
function showTabHSA(id){
    if(id == 'tab1') {
         /*Individual Health Profile*/
        var txtProfileOTP = $("#txtPerHistOTP").val();
        var cntProfileOTP = $("#txtPerHistOTP").val().length;
        var txtProfileDate = $("#txtPerHistProfDate").val();

        var chkWalkedIn = $("#walkedInChecker_true").is(":checked");
       
        /*Start Get date today*/
        var dateToday = new Date();
       
        var compareProfDate = compareDates(dateToday,txtProfileDate);
        /*End Get date today*/


        if(chkWalkedIn == true && (txtProfileOTP == "" || cntProfileOTP < 4)) {
            alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
            $("#txtPerHistOTP").focus();
            return false;
        }  
        else if (txtProfileDate == "") {
            alert("Screening & Assessment Date is required");
            $("#txtPerHistProfDate").focus();
            return false;
        }      
        else if (compareProfDate == "0") {
            alert("Screening & Assessment Date under CLIENT PROFILE menu is invalid! It should be less than or equal to current day.");
            $("#txtPerHistProfDate").focus();
            return false;
        }    
        else {
            $("#list1").removeClass("active");
            $("#tab1").removeClass("active");
            $("#tab2").addClass("active in");
            $("#list2").addClass("active");
        }
    }

    else if(id == 'tab2') {
        /*Past Medical History*/
        var chkAllergy = $("#chkMedHistDiseases_001").is(":checked");
        var chkCancer = $("#chkMedHistDiseases_003").is(":checked");
        var chkHepatitis = $("#chkMedHistDiseases_009").is(":checked");
        var chkHypertension = $("#chkMedHistDiseases_011").is(":checked");
        var chkPTB = $("#chkMedHistDiseases_015").is(":checked");
        var chkExPTB = $("#chkMedHistDiseases_016").is(":checked");
        var chkOthers = $("#chkMedHistDiseases_998").is(":checked");

        var txtAllergy = $("#txtMedHistAllergy").val();
        var txtCancer = $("#txtMedHistCancer").val();
        var txtHepatitis = $("#txtMedHistHepatitis").val();
        var txtDiastolic = $("#txtMedHistBPDiastolic").val();
        var txtSystolic = $("#txtMedHistBPSystolic").val();
        var txtPTB = $("#txtMedHistPTB").val();
        var txtExPTB = $("#txtMedHistExPTB").val();
        var txaOthers = $("#txaMedHistOthers").val();

        // if(validateChecksMedsHist() == false){
        //     alert("Choose at least one Past Medical History in MEDICAL & SURGICAL HISTORY menu");
        //     return false;
        // }
        /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN MEDICAL & SURGICAL HISTORY*/
        if(chkAllergy == true && txtAllergy == "") {
            alert("Please specify allergy under MEDICAL & SURGICAL HISTORY menu.");
            return false;
        }
        else if(chkCancer == true && txtCancer == "") {
            alert("Please specify organ with cancer under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistCancer").focus();
            return false;
        }
        else if(chkHepatitis == true && txtHepatitis == "") {
            alert("Please specify hepatitis type under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistHepatitis").focus();
            return false;
        }
        else if(chkHypertension == true && (txtSystolic == "" || txtDiastolic == "")) {
            alert("Please specify highest blood pressure under MEDICAL & SURGICAL HISTORY menu.");
            if(txtSystolic == "") {
                $("#txtMedHistBPSystolic").focus();
            }
            else {
                $("#txtMedHistBPDiastolic").focus();
            }
            return false;
        }
        else if(chkPTB == true && txtPTB == "") {
            alert("Please specify Pulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistPTB").focus();
            return false;
        }
        else if(chkExPTB == true && txtExPTB == "") {
            alert("Please specify Extrapulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistExPTB").focus();
            return false;
        }
        else if(chkOthers == true && txaOthers == "") {
            alert("Please specify others under MEDICAL & SURGICAL HISTORY menu.");
            $("#txaMedHistOthers").focus();
            return false;
        }
        else {
            $("#list2").removeClass("active");
            $("#tab2").removeClass("active");
            $("#tab3").addClass("active in");
            $("#list3").addClass("active");
        }
    }

    else if(id == 'tab3') {
        /*Family & Personal History*/
        // var chkAllergyFam = $("#chkFamHistDiseases_001").is(":checked");
        // var chkCancerFam = $("#chkFamHistDiseases_003").is(":checked");
        // var chkHepatitisFam = $("#chkFamHistDiseases_009").is(":checked");
        // var chkHypertensionFam = $("#chkFamHistDiseases_011").is(":checked");
        // var chkPTBfam = $("#chkFamHistDiseases_015").is(":checked");
        // var chkExPTBfam = $("#chkFamHistDiseases_016").is(":checked");
        // var chkOthersFam = $("#chkFamHistDiseases_998").is(":checked");

        // var txtAllergyFam = $("#txtFamHistAllergy").val();
        // var txtCancerFam = $("#txtFamHistCancer").val();
        // var txtHepatitisFam = $("#txtFamHistHepatitis").val();
        // var txtDiastolicFam = $("#txtFamHistBPDiastolic").val();
        // var txtSystolicFam = $("#txtFamHistBPSystolic").val();
        // var txtPTBfam = $("#txtFamHistPTB").val();
        // var txtExPTBfam = $("#txtFamHistExPTB").val();
        // var txaOthersFam = $("#txaFamHistOthers").val();

        /*Personal/Social History*/
        var chkFamHistSmokeY = $("#radFamHistSmokeY").is(":checked");
        var chkFamHistSmokeN = $("#radFamHistSmokeN").is(":checked");
        var chkFamHistSmokeX = $("#radFamHistSmokeX").is(":checked");
        var chkFamHistAlcoholY = $("#radFamHistAlcoholY").is(":checked");
        var chkFamHistAlcoholN = $("#radFamHistAlcoholN").is(":checked");
        var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
        var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
        var chkFamHistDrugsY = $("#radFamHistDrugsY").is(":checked");
        var chkFamHistDrugsN = $("#radFamHistDrugsN").is(":checked");
        var chkFamHistSexualHistY = $("#radFamHistSexualHistY").is(":checked");
        var chkFamHistSexualHistN = $("#radFamHistSexualHistN").is(":checked");

        /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN FAMILY & PERSONAL HISTORY*/
        // if(chkAllergyFam == true && txtAllergyFam == "") {
        //     alert("Please specify allergy under FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkCancerFam == true && txtCancerFam == "") {
        //     alert("Please specify organ with cancer under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistCancer").focus();
        //     return false;
        // }
        // else if(chkHepatitisFam == true && txtHepatitisFam == "") {
        //     alert("Please specify hepatitis type under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistHepatitis").focus();
        //     return false;
        // }
        // else if(validateChecksFamHist() == false){
        //     alert("Choose at least one Family History in FAMILY & PERSONAL HISTORY menu");
        //     return false;
        // }
        // else if(chkHypertensionFam == true && (txtSystolicFam == "" || txtDiastolicFam == "")) {
        //     alert("Please specify highest blood pressure under FAMILY & PERSONAL HISTORY menu.");
        //     if(txtSystolic == "") {
        //         $("#txtFamHistBPSystolic").focus();
        //     }
        //     else {
        //         $("#txtFamHistBPDiastolic").focus();
        //     }
        //     return false;
        // }
        // else if(chkPTBfam == true && txtPTBfam == "") {
        //     alert("Please specify Pulmonary Tuberculosis category under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistPTB").focus();
        //     return false;
        // }
        // else if(chkExPTBfam == true && txtExPTBfam == "") {
        //     alert("Please specify Extrapulmonary Tuberculosis category under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistExPTB").focus();
        //     return false;
        // }
        // else if(chkOthersFam == true && txaOthersFam == "") {
        //     alert("Please specify others.");
        //     $("#txaFamHistOthers").focus();
        //     return false;
        // }
        // if(chkFamHistSmokeY == false && chkFamHistSmokeN == false && chkFamHistSmokeX == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkFamHistAlcoholY == false && chkFamHistAlcoholN == false && chkFamHistAlcoholX == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkFamHistDrugsY == false && chkFamHistDrugsN == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkFamHistSexualHistY == false && chkFamHistSexualHistN == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else {
            if ($("#chkFamHistDiseases_006").is(":checked")) {
                $("#list3").removeClass("active");
                $("#tab3").removeClass("active");
                $("#tab3_1").addClass("active in");
                $("#list3_1").addClass("active");
            } else {
                $("#list3").removeClass("active");
                $("#tab3").removeClass("active");
                $("#tab4").addClass("active in");
                $("#list4").addClass("active");
            }
        // }
    }

    else if(id == 'tab3_1') {
            $("#list3_1").removeClass("active");
            $("#tab3_1").removeClass("active");

            $("#tab4").addClass("active in");
            $("#list4").addClass("active");
    }
    
    else if(id == 'tab4') {
        /*Immmunizations*/
        // if (validateChecksImmune() == false){
        //     alert("Choose at least one in each category of IMMUNIZATION");
        //     return false;   
        // } 
        // else {
            $("#list4").removeClass("active");
            $("#tab4").removeClass("active");
            $("#tab5").addClass("active in");
            $("#list5").addClass("active");
        //}
    }

    else if(id == 'tab5') {
        /*OB-Gyne History*/
        /*Menstrual History*/
        var txtMenarche = $("#txtOBHistMenarche").val();
        var txtLastMens = $("#txtOBHistLastMens").val();
        var dateLastMens = new Date(txtLastMens);
        var compareLastMensDate = compareDates(dateToday,dateLastMens);
        var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
        /*Pregnancy History*/
        var txtGravity = $("#txtOBHistGravity").val();
        var txtParity = $("#txtOBHistParity").val();

        var whatSex = $("#txtPerHistPatSex").val();
        var whatAge = $("#valtxtPerHistPatAge").val();
        var whatMonths = $("#valtxtPerHistPatMonths").val();

        var chkMHdone = $("#mhDone_Y").is(":checked");
        var chkPREGdone = $("#pregDone_Y").is(":checked");

        
        if(chkMHdone == true && whatSex == "FEMALE" && (txtMenarche == "" || txtLastMens == "" || txtPeriodDuration == "") && whatAge >= 10){
            alert("Menarche, Last Menstrual Period and Period Duration are REQUIRED in MENSTRUAL HISTORY under OB-GYNE HISTORY menu!");
            return false;
        }
        else if(compareLastMensDate == "0"){
            alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkPREGdone == true && whatSex == "FEMALE" && (txtGravity == "" || txtParity == "")){
            alert("Gravity and Parity are REQUIRED in PREGNANCY HISTORY under OB-GYNE HISTORY menu!");
            return false;
        }
        else {
            $("#list5").removeClass("active");
            $("#tab5").removeClass("active");
            $("#tab6").addClass("active in");
            $("#list6").addClass("active");
        }
    }
    else if(id == 'tab6') {
        /*Pertinent Physical Examination Findings*/
        // var txtPhExSystolic = $("#txtPhExSystolic").val();
        // var txtPhExBPDiastolic = $("#txtPhExBPDiastolic").val();
        // var txtPhExHeartRate = $("#txtPhExHeartRate").val();
        // var txtPhExRespiratoryRate = $("#txtPhExRespiratoryRate").val();
        // var txtPhExHeightCm = $("#txtPhExHeightCm").val();
        // var txtPhExWeightKg = $("#txtPhExWeightKg").val();
        // var txtPhExTemp = $("#txtPhExTemp").val();

        // var txtPhEXBMIResult = $("#txtPhExBMI").val();

        // /*General Survey*/
        // var chkGenSurvey1 = $("#pGenSurvey_1").is(":checked");
        // var chkGenSurvey2 = $("#pGenSurvey_2").is(":checked");
        // var txtGenSurveyRem = $("#pGenSurveyRem").val();

        // if(txtPhExSystolic == "" || txtPhExBPDiastolic == "" || txtPhExHeartRate == "" || txtPhExRespiratoryRate == "" || txtPhExHeightCm == "" || txtPhExWeightKg == "" || txtPhExTemp == ""){
        //     alert("Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        //     return false;
        // }
        // else if(txtPhEXBMIResult == ""){
        //     alert("BMI is required. Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        //     return false;
        // }
        // else if(chkGenSurvey1 == false && chkGenSurvey2 == false){
        //     alert("Please specify General Survey under PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        //     $("#pGenSurvey_1").focus();
        //     return false;
        // }
        // else if(chkGenSurvey2 == true && txtGenSurveyRem == ""){
        //     alert("Please specify Altered Sensorium in General Survey under PHYSICAL EXAMINATION ON ADMISSION!");
        //     $("#pGenSurveyRem").focus();
        //     return false;
        // }
        // else if(validateChecksHeent() == false){
        //     alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksChest() == false){
        //     alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksHeart() == false){
        //     alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksAbdomen() == false){
        //     alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksGenitoUrinary() == false){
        //     alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksRectal() == false){
        //     alert("Choose at least one DIGITAL RECTAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksSkin() == false){
        //     alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksNeuro() == false){
        //     alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else {
            $("#list6").removeClass("active");
            $("#tab6").removeClass("active");
            $("#tab7").addClass("active in");
            $("#list7").addClass("active");
        //}
    }
   

}




/*20191210 eKONSULTA enhancement*/
/*pertinent findings per system*/
function checkHeent() {
    if(isChecked('heent_99')) {
        enableID('heent_remarks');
        setFocus('heent_remarks');
    }
    else {
        disableID('heent_remarks');
    }

    if(isChecked('heent_11')){
        disableID('heent_12');
        disableID('heent_13');
        disableID('heent_14');
        disableID('heent_15');
        disableID('heent_16');
        disableID('heent_17');
        disableID('heent_18');
        disableID('heent_99');
        disableID('heent_remarks');
    } else{
        enableID('heent_12');
        enableID('heent_13');
        enableID('heent_14');
        enableID('heent_15');
        enableID('heent_16');
        enableID('heent_17');
        enableID('heent_18');
        enableID('heent_99');
    }
}

function checkChestLungs(){
    if(isChecked('chest_99')) {
        enableID('chest_lungs_remarks');
        setFocus('chest_lungs_remarks');
    }
    else {
        disableID('chest_lungs_remarks');
    }

    if(isChecked('chest_6')){        
        disableID('chest_7');
        disableID('chest_8');
        disableID('chest_5');
        disableID('chest_10');
        disableID('chest_4');
        disableID('chest_3');
        // disableID('chest_9');
        disableID('chest_99');
        disableID('chest_lungs_remarks');
    } else{
        enableID('chest_7');
        enableID('chest_8');
        // enableID('chest_9');
        enableID('chest_5');
        enableID('chest_10');
        enableID('chest_4');
        enableID('chest_3');
        enableID('chest_99');
    }
}

function checkHeart(){
    if(isChecked('heart_99')) {
        enableID('heart_remarks');
        setFocus('heart_remarks');
    }
    else {
        disableID('heart_remarks');
    }

    if(isChecked('heart_5')){
        disableID('heart_6');
        disableID('heart_3');
        disableID('heart_7');
        disableID('heart_8');
        disableID('heart_4');
        disableID('heart_9');
        disableID('heart_99');
        disableID('heart_remarks');
    } else{
        enableID('heart_6');
        enableID('heart_3');
        enableID('heart_7');
        enableID('heart_8');
        enableID('heart_4');
        enableID('heart_9');
        enableID('heart_99');
    }
}

function checkAbdomen(){
    if(isChecked('abdomen_99')) {
        enableID('abdomen_remarks');
        setFocus('abdomen_remarks');
    }
    else {
        disableID('abdomen_remarks');
    }

    if(isChecked('abdomen_7')){
        disableID('abdomen_8');
        disableID('abdomen_9');
        disableID('abdomen_10');
        disableID('abdomen_11');
        disableID('abdomen_12');
        disableID('abdomen_13');
        disableID('abdomen_99');
        disableID('abdomen_remarks');
    } else{
        enableID('abdomen_8');
        enableID('abdomen_9');
        enableID('abdomen_10');
        enableID('abdomen_11');
        enableID('abdomen_12');
        enableID('abdomen_13');
        enableID('abdomen_99');
    }
}

function checkGU() {
    if(isChecked('gu_99')) {
        enableID('gu_remarks');
        setFocus('gu_remarks');
    }
    else {
        disableID('gu_remarks');
    }

    if(isChecked('gu_1')){
        disableID('gu_2');
        disableID('gu_3');
        disableID('gu_4');
        disableID('gu_99');
        disableID('gu_remarks');
    } else{
        enableID('gu_2');
        enableID('gu_3');
        enableID('gu_4');
        enableID('gu_99');
    }
}

function checkSkinExtrem(){
    if(isChecked('extremities_99')) {
        enableID('extremities_remarks');
        setFocus('extremities_remarks');
    }
    else {
        disableID('extremities_remarks');
    }

    if(isChecked('extremities_1')){
        disableID('extremities_2');
        disableID('extremities_3');
        disableID('extremities_4');
        disableID('extremities_5');
        disableID('extremities_6');
        disableID('extremities_7');
        disableID('extremities_8');
        disableID('extremities_9');
        disableID('extremities_10');
        disableID('extremities_99');
        disableID('extremities_remarks');
    } else{
        enableID('extremities_2');
        enableID('extremities_3');
        enableID('extremities_4');
        enableID('extremities_5');
        enableID('extremities_6');
        enableID('extremities_7');
        enableID('extremities_8');
        enableID('extremities_9');
        enableID('extremities_10');
        enableID('extremities_99');
    }
}

function checkNeuro(){
    if(isChecked('neuro_99')) {
        enableID('neuro_remarks');
        setFocus('neuro_remarks');
    }
    else {
        disableID('neuro_remarks');
    }

    if(isChecked('neuro_6')){
        disableID('neuro_7');
        disableID('neuro_8');
        disableID('neuro_9');
        disableID('neuro_10');
        disableID('neuro_11');
        disableID('neuro_12');
        disableID('neuro_13');
        disableID('neuro_99');
        disableID('neuro_remarks');
    } else{
        enableID('neuro_7');
        enableID('neuro_8');
        enableID('neuro_9');
        enableID('neuro_10');
        enableID('neuro_11');
        enableID('neuro_12');
        enableID('neuro_13');
        enableID('neuro_99');
    }
}

function checkRectal(){
    if(isChecked('rectal_99')) {
        enableID('rectal_remarks');
        setFocus('rectal_remarks');
    }
    else {
        disableID('rectal_remarks');
    }

    if(isChecked('rectal_1')){
        disableID('rectal_2');
        disableID('rectal_3');
        disableID('rectal_4');
        disableID('rectal_5');
        disableID('rectal_0');
        disableID('rectal_99');
        disableID('rectal_remarks');
    } else{
        enableID('rectal_2');
        enableID('rectal_3');
        enableID('rectal_4');
        enableID('rectal_5');
        enableID('rectal_0');
        enableID('rectal_99');
    }
}

/** getXmlHttpObject **/
function GetXmlHttpObject(sender) {
    var xmlHttp=null;
    // Firefox, Opera 8.0+, Safari
    try {
        xmlHttp=new XMLHttpRequest();
    }
    catch (e) {
        // Internet Explorer
        try {
            xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
        }
        catch (e) {
            try {
                xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
            }
            catch (e) {
                alert("Your browser does not support AJAX! Please update your browser.");
                return null;
            }
        }
    }
    return xmlHttp;
}

/** Checking of Dates Version 1 **/
function check_date(pAdmissionDate,pDischargeDate) {
    var fromdate = pAdmissionDate.split('/');
    pAdmissionDate = new Date();
    pAdmissionDate.setFullYear(fromdate[2],fromdate[0]-1,fromdate[1]); //setFullYear(year,month,day)

    var todate = pDischargeDate.split('/');
    pDischargeDate = new Date();
    pDischargeDate.setFullYear(todate[2],todate[0]-1,todate[1]);

    if (pAdmissionDate > pDischargeDate ) {
        return false;
    }
    else {
        return true;
    }
}

/** Checking of Dates Version 3 **/
function checkDateValue(dateValue) {
    var error = 0;
    var now = new Date();
    var day = now.getDate();
    var mon = now.getMonth()+1;
    var year = now.getFullYear();
    var selectedDate = dateValue;
    selectedDate = selectedDate.split("/");

    if (ValidateDate(selectedDate[2], selectedDate[0], selectedDate[1]) === false) { error = 1; }

    if ( selectedDate[2] > year ) { error = 1; }
    else if ( selectedDate[2] < 1000 ) { error = 1; }
    else if (selectedDate[2] == year) {
        if ( selectedDate[0] > mon ) { error = 1; }
        else if ( selectedDate[0] == mon ) {
            if ( selectedDate[1] > day ) { error = 1; }
        }
    }

    if (error == 1) { return false; }
    else { return true; }
}

/** Checking of Dates Version 4 **/
function ValidateDate(y, mo, d) {
    var date = new Date(y, mo - 1, d);
    var ny = date.getFullYear();
    var nmo = date.getMonth() + 1;
    var nd = date.getDate();
    return ny == y && nmo == mo && nd == d;
}

/** is Number Key **/
function isNumberKey(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57))
        return false;

    return true;

}

/** is Number Key **/
function isNumberWithDecimalKey(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode;
    if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    } else {
        // If the number field already has . then don't allow to enter . again.
        if (evt.target.value.search(/\./) > -1 && charCode == 46) {
            return false;
        }
        return true;
    }
}

function isNumberKeyWithTwoDecimalKey(evt, id) {
     var charCode = (evt.which) ? evt.which : event.keyCode
     if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode != 46)
         return false;
     else {
         var len = document.getElementById(id).value.length;
         var index = document.getElementById(id).value.indexOf('.');
         
         if (index > 0 && charCode == 46) {
             return false;
         }
         if (index > 0) {
             var CharAfterdot = (len + 1) - index;
             if (CharAfterdot > 3) {
                 return false;
             }
         }

     }
     return true;
}

/** Validate Alpha Characters  **/
function ValidateAlpha(evt) {
    var keyCode = (evt.which) ? evt.which : evt.keyCode;
    if ((keyCode < 65 || keyCode > 90) && (keyCode < 97 || keyCode > 123) && keyCode != 32 &&
        keyCode != 45 && keyCode != 209 && keyCode != 241 && keyCode != 13 && keyCode != 37) //45 = '-', 209 = 'Ñ', 241 = 'ñ', 13 = 'Enter Key', 37 '%' //savr20151026
        return false;

    return true;
}

/** Set Focus **/
function setFocus(id) {
    document.getElementById(id).focus();
}

/** Set Display **/
function setDisplay(id, dis) {
    document.getElementById(id).style.display = dis;
}

/** Set Disabled **/
function setDisabled(id, dis) {
    document.getElementById(id).disabled = dis;
}

/** Set Value **/
function setValue(id, val) {
    document.getElementById(id).value = val;
}

/** Get Value **/
function getValue(id) {
    return document.getElementById(id).value;
}

/** Is Checked **/
function isChecked(id) {
    if (document.getElementById(id).checked == true) { return true; }
    else { return false; }
}

/** Disable ID **/
function disableID(id) {
    document.getElementById(id).disabled = true;
}

/** Enabled ID **/
function enableID(id) {
    document.getElementById(id).disabled = false;
}

/** Check ID **/
function checkID(id) {
    document.getElementById(id).checked = true;
}

/** UnCheck ID **/
function uncheckID(id) {
    document.getElementById(id).checked = false;
}

/* Print Report */
function printReport(action, title) {
    document.getElementById('statsForm').action = action + '/print_report/' + title;
    document.getElementById('statsForm').target = '_blank';
    document.getElementById('statsForm').submit();
}

/* Show/Hide ID */
function showHideID(id, task) {
    if (task == 'show') {
        document.getElementById(id).style.display = '';
    } else {
        document.getElementById(id).style.display = 'none';
    }
}

/* URL redirection */
function urlRedirection(url) {
    window.location = url;
}

/* URL Window Open */
function urlWindowOpen(url) {
    params  = 'width='+screen.width;
    params += ', height='+screen.height;
    params += ', top=0, left=0'
    params += ', fullscreen=yes';

    newwin=window.open(url,'windowname4', params);
    if (window.focus) {newwin.focus()}
    return false;
}

/* Ask Confirmation Before Saving */
function confirmSave(message, form_id) {
    var response = confirm(message + "Continue to save?");
    if (response == true) {
        document.getElementById(form_id).submit();
    }
}

/* Validate Search PhilHealth Records */
function validateSearch() {
    var pin = $("#pPIN").val();
    var lastname = $("#pLastName").val();
    var firstname = $("#pFirstName").val();
    var birthday = $("#pDateOfBirth").val();

    if(pin == "" && lastname == "" && firstname == "" && birthday == "") {
        alert("Please input any of the following: \n\n-PhilHealth Identification Number.\n-Name and Birthday.");
        $("#pPIN").focus();
        return false;
    }
    else {
        if(pin == "" && lastname == "") {
            alert("Please input Last Name.");
            $("#pLastName").focus();
            return false;
        }
        else if(pin == "" && firstname == "") {
            alert("Please input First Name.");
            $("#pFirstName").focus();
            return false;
        }
        else if(pin == "" && birthday == "") {
            alert("Please input Date of Birth.");
            $("#pDateOfBirth").focus();
            return false;
        }
    }

    $("#wait_image").show();
}
function validateEnlistmentSearch() {
    var pPIN = getValue('pPIN');
    var pLastName = getValue('pLastName');
    var pFirstName = getValue('pFirstName');
    var pDateOfBirth = getValue('pDateOfBirth');

    if ((pPIN == '') && (pLastName == '') && (pFirstName == '') && (pDateOfBirth == '')) {
        alert('Please Indicate PIN');
        setFocus('pPIN');
    }
    else if ((pPIN == '') && pLastName == '') {
        alert('Last Name is required');
        setFocus('pLastName');
    }
    else if ((pPIN == '') && pFirstName == '') {
        alert('First Name is required');
        setFocus('pFirstName');
    }
    else {
        setDisplay('content_div', 'none');
        setDisplay('result', 'none');
        setDisplay('wait_image', '');
        document.getElementById('search_enlistment_form').submit();
    }
}

function validateConsultationSearch() {
    var pPIN = getValue('pPIN');
    var pLastName = getValue('pLastName');
    var pFirstName = getValue('pFirstName');
    var pDateOfBirth = getValue('pDateOfBirth');

    if ((pPIN == '') && (pLastName == '') && (pFirstName == '') && (pDateOfBirth == '')) {
        alert('Please Indicate PIN');
        setFocus('pPIN');
    }
    else if ((pPIN == '') && pLastName == '') {
        alert('Last Name is required');
        setFocus('pLastName');
    }
    else if ((pPIN == '') && pFirstName == '') {
        alert('First Name is required');
        setFocus('pFirstName');
    }
    else {
        setDisplay('content_div', 'none');
        setDisplay('result', 'none');
        setDisplay('wait_image', '');
        document.getElementById('search_profile_form').submit();
    }
}

function validateLabResultsSearch() {
    var pPIN = getValue('pPIN');
    var pLastName = getValue('pLastName');
    var pFirstName = getValue('pFirstName');
    var pDateOfBirth = getValue('pDateOfBirth');

    if ((pPIN == '') && (pLastName == '') && (pFirstName == '') && (pDateOfBirth == '')) {
        alert('Please Indicate PIN');
        setFocus('pPIN');
    }
    else if ((pPIN == '') && pLastName == '') {
        alert('Last Name is required');
        setFocus('pLastName');
    }
    else if ((pPIN == '') && pFirstName == '') {
        alert('First Name is required');
        setFocus('pFirstName');
    }
    else if ((pPIN == '') && (pDateOfBirth != '')) {
        alert('Please input a valid value for Date of Birth');
        setFocus('pDateOfBirth');
    }
    else {
        setDisplay('content_div', 'none');
        setDisplay('result', 'none');
        setDisplay('wait_image', '');
        document.getElementById('search_lab_results_form').submit();
    }
}
/* Validate Data Entry of Enlistment */
function validatePatientForEnlistment() {
    var pCaseNo = getValue('pCaseNo');
    var pEnlistmentDate = getValue('pEnlistmentDate');
    var pPatientType = getValue('pPatientType');
    var pWithConsent = getValue('pWithConsent');
    var pIsEligible = getValue('pIsEligible');
    if ((pPatientType == 'DD') && (pIsEligible != 'NOT ELIGIBLE')) { var pWithLOA = getValue('pWithLOA'); }
    var pPatientLastName = getValue('pPatientLastName');
    var pPatientFirstName = getValue('pPatientFirstName');
    var pPatientDateOfBirth = getValue('pPatientDateOfBirth');
    var pPatientContactNo = getValue('pPatientContactNo');
    var pPatientSexX = getValue('pPatientSexX');
    var pPatientCivilStatusX = getValue('pPatientCivilStatusX');
    var pProvinceX = getValue('pProvinceX');
    var pMunicipalityX = getValue('pMunicipalityX');
    var pBarangayX = getValue('pBarangayX');
    var pTaggingForEnrollment = getValue('pTaggingForEnrollment');
    //var pPatientFamilyPlanningCounselling = document.getElementById('pPatientFamilyPlanningCounselling').value;
    //var pDateToday = getValue('pDateToday'); c

    // alert(pPatientSexX);

    if (pEnlistmentDate == '' || !checkDateValue(pEnlistmentDate)) {
        alert('Encounter Date is invalid.');
        setFocus('pEnlistmentDate');
    }
    else
    if ((pCaseNo == '') && (!isDateWithinRange(pEnlistmentDate))) { //savr 2016-01-21
        alert('Encounter Date must be within this quarter.');
        setFocus('pEnlistmentDate');
    }
    else
    if (pWithConsent == '') {
        alert('With Consent is required.');
        setFocus('pWithConsent');
    }
    else
    if ((pCaseNo == '') && (pPatientType == 'DD') && (pWithLOA == '')) {
        alert('With Letter of Authorization is required.');
        setFocus('pWithLOA');
    }
    else
    if (pPatientLastName == '') {
        alert('Last Name is required.');
        setFocus('pPatientLastName');
    }
    else
    if (pPatientFirstName == '') {
        alert('First Name is required.');
        setFocus('pPatientFirstName');
    }
    else
    if (pPatientDateOfBirth == '') {
        alert('Date of Birth is required.');
        setFocus('pPatientDateOfBirth');
    }
    else
    if (pPatientContactNo == '') {
        alert('Contact No. is required.');
        setFocus('pPatientContactNo');
    }
    else
    if ((pPatientSexX == '-') || (pPatientSexX == '')) { //savr 2016-04-08: update validation of Sex Field
        alert('Sex is required.');
        setFocus('pPatientSexX');
    }
    else
    if ((pPatientCivilStatusX == '-') || (pPatientCivilStatusX == '')) { //savr 2016-04-08: update validation of Civil Status Field
        alert('Civil Status is required.');
        setFocus('pPatientCivilStatusX');
    }
    else
    if (pProvinceX == '') {
        alert('Province is required.');
        setFocus('pProvinceX');
    }
    else
    if (pMunicipalityX == '') {
        alert('Municipality is required.');
        setFocus('pMunicipalityX');
    }
    else
    if ((pBarangayX == '') && (pPatientType == 'NM')) {
        alert('Barangay is required.');
        setFocus('pBarangayX');
    }
    else
    if ((pTaggingForEnrollment == '') && ((pPatientType == 'NM') || ((pPatientType == 'DD') && (pIsEligible == 'NOT ELIGIBLE')))) {
        alert('Tagging For Enrollment is required.');
        setFocus('pTaggingForEnrollment');
    }
    /*else
    if (pPatientFamilyPlanningCounselling == '') {
        alert('Choose One for Family Planning Counselling');
        setFocus('pPatientFamilyPlanningCounselling');
    }*/
    else {
        setDisplay('content_div', 'none');
        setDisplay('wait_image', '');
        document.getElementById('data_entry_enlistment_form').submit();
    }
}


/* Treatment Check */
function checkTreatment(id, div_result, pxRq, pxRf) {
    if(isChecked(id) && !isChecked(pxRf) ) {
        setDisplay(div_result + '_header', '');
        setDisplay(div_result + '_form', '');
        
    }
    else if(isChecked(pxRq)) {
        setDisplay(div_result + '_header', '');
        setDisplay(div_result + '_form', '');
        
    }
    else if(!isChecked(id) && isChecked(pxRq)) {
        setDisplay(div_result + '_header', '');
        setDisplay(div_result + '_form', '');
        
    }
    else if(isChecked(id) && isChecked(pxRf)) {
       setDisplay(div_result + '_header', 'none');
       setDisplay(div_result + '_form', 'none');
    }
    else if(isChecked(id)) {
        setDisplay(div_result + '_header', '');
        setDisplay(div_result + '_form', '');        
    }    
    else {
        setDisplay(div_result + '_header', 'none');
        setDisplay(div_result + '_form', 'none');
    }
    
}

/* Check Other Diagnostic Examination */
function checkOtherDiagExam() {
    if(isChecked('diagnostic_99_doctorYes') || isChecked('diagnostic_99_patientRQ')) {
        enableID('diagnostic_oth_remarks1');
    }
    else {
        disableID('diagnostic_oth_remarks1');
    }
}

/* Check Other Management */
function checkOtherManagement() {
    if(isChecked('management_oth')) {
        enableID('management_oth_remarks');
        setFocus('management_oth_remarks');
    }
    else {
        disableID('management_oth_remarks');
    }
}

/* Check Pain in Chief Complaint */
function checkPainChiefComplaint() {
    
    if(isChecked('symptom_38')) {
        document.getElementById("pPainSite").style.display = '';
        enableID('pPainSite');
        setFocus('pPainSite');
    } else {
        document.getElementById("pPainSite").style.display = 'none';
        disableID('pPainSite');
    }
}


/* Check Other Chief Complaint */
function checkOtherChiefComplaint() {
    if(isChecked('symptom_X')) {
        enableID('pOtherChiefComplaint');
        setFocus('pOtherChiefComplaint');
    } else {
        disableID('pOtherChiefComplaint');
    }
}


/* Exam Results Check */
function checkExamResults(id) {
    if(isChecked(id)) {
        enableID(id+'_given');
        enableID(id+'_referred');
        enableID(id+'_remarks');
    }
    else {
        disableID(id+'_given');
        disableID(id+'_referred');
        disableID(id+'_remarks');
        uncheckID(id+'_given');
        uncheckID(id+'_referred');
    }
}

/* Add Diagnosis */
function addDiagnosis(imageURL) {
    var pICD = document.getElementById('pICD');

    if (pICD.value == '') {
        alert('Please select a diagnosis');
        setFocus('pICD');
    }
    else {
        var fieldDesc = pICD.options[pICD.selectedIndex].text;
        if (getRowID('diagnosis_table', '1', fieldDesc) == 0) {
            addDiagnosisRow('diagnosis_table', pICD.value, fieldDesc, imageURL);
            designTable('diagnosis_table');
        } else {
            alert('Diagnosis already in the list.');
        }
    }
}

/* Add Diagnosis Row */
function addDiagnosisRow(tableID, fieldValue, fieldDesc, imageURL) {
    var table = document.getElementById(tableID);
    var length = table.rows.length;
    var row = table.insertRow(length);
    var cell1 = row.insertCell(0);
    cell1.innerHTML = length;

    var cell2 = row.insertCell(1);
    cell2.innerHTML = fieldDesc;

    var cell3 = row.insertCell(2);
    cell3.innerHTML = '<input type="hidden" value="' + fieldValue + '" id="diagnosis_' + fieldValue + '" name="diagnosis[]">' +
        '<img src="' + imageURL + '" onClick="removeDiagnosisRow(\''+ tableID +'\', \''+ fieldDesc +'\');" alt="Remove Diagnosis" title="Remove Diagnosis" style="width: 20px; height: 20px; cursor: pointer;">';
}

/* Remove Diagnosis Row */
function removeDiagnosisRow(tableID, fieldDesc) {
    var rowID = getRowID(tableID, '1', fieldDesc);
    document.getElementById(tableID).deleteRow(rowID);
    renameNumberID(tableID);
    designTable('diagnosis_table');
}

/* Get ROW ID */
function getRowID(tableID, columnID, fieldDesc) {
    var table = document.getElementById(tableID);
    var length = table.rows.length;
    var val = 0;

    for (i = 0; i < length; i++) {
        var x = table.rows[i].cells;
        if (x[columnID].innerHTML == fieldDesc) {
            val = i;
        }
    }
    return val;
}

/* Rename Number ID */
function renameNumberID(tableID) {
    var table = document.getElementById(tableID);
    var length = table.rows.length;

    for (i = 1; i < length; i++) {
        var x = table.rows[i].cells;
        x[0].innerHTML = i;
    }
}

/* Validate SOAP */
function validateSOAP(obligated_services) {
    var message = '';
    var focusID = '';
    var BPMeasurements = false;
    //var obligated_services = Array('', 'BP Measurements', 'Periodic clinical breast cancer examination', 'Visual inspection with acetic acid', 'Digital Rectal Examination');

    // Obligated Services Checking
    var obligated_error = false;
    for (i = 1; i < 5; i++) {
        if (i == 1) {
            if ((!isChecked('obligated_service_' + i + '_yes')) && (!isChecked('obligated_service_' + i + '_no'))) {
                obligated_error = true;
                message = 'Select one for \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                break;
            }
            else
            if (isChecked('obligated_service_' + i + '_yes') && getValue('obligated_service_' + i + '_type') == '') {
                obligated_error = true;
                message = 'Select one type in \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                focusID = 'obligated_service_' + i + '_type';
                break;
            }
            else {
                BPMeasurements = isChecked('obligated_service_' + i + '_yes');
            }
        }
        else {
            if ((!isChecked('obligated_service_' + i + '_yes')) && (!isChecked('obligated_service_' + i + '_no'))  && (!isChecked('obligated_service_' + i + '_waived'))) {
                obligated_error = true;
                message = 'Select one for \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                break;
            }
            else
            if (isChecked('obligated_service_' + i + '_waived') && getValue('obligated_service_' + i + '_waived_reason') == '') {
                obligated_error = true;
                message = 'Select one reason in \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                focusID = 'obligated_service_' + i + '_waived_reason';
                break;
            }
        }
    }

    // Subjective/History of Illnees Checking
    var sujective_error = false;
    if (!obligated_error) {
        var pSOAPDate = getValue('pSOAPDate');
        if (!checkDateValue(pSOAPDate)) {
            sujective_error = true;
            focusID = 'pSOAPDate';
            message = 'Invalid value for consultation date under Subjective/History of Illness Tab.';
        }
        else
        if ((!isDateWithinRange(pSOAPDate))) {//savr 2016-06-06 #v1.1.2: added checking of consultation date
            sujective_error = true;
            focusID = 'pSOAPDate';
            message = 'Consultation Date must be within this quarter.';
        }
        else
        if (getValue('pChiefComplaint') == '') {
            sujective_error = true;
            focusID = 'pChiefComplaint';
            message = 'Enter a valid chief complaint under Subjective/History of Illness Tab.';
        }
    }

    // Objective/Physical Examination Checking
    var objective_error = false;
    if (!sujective_error && !obligated_error) {
        if (BPMeasurements && (getValue('pe_bp_u') == '')) {
            objective_error = true;
            focusID = 'pe_bp_u';
            message = 'Enter systolic value in BP under Objective/Physical Examination Tab.';
        }
        else
        if (BPMeasurements && (getValue('pe_bp_l') == '')) {
            objective_error = true;
            focusID = 'pe_bp_l';
            message = 'Enter diastolic value in BP under Objective/Physical Examination Tab.';
        }
    }

    // Assessment/Diagnosis Checking
    var assessment_error = false;
    if (!sujective_error && !obligated_error && !objective_error) {
        var table = document.getElementById('diagnosis_table');
        var rowCount = table.rows.length;  //It will return the last Index of the row and its row count
        var actualRowCount = parseInt(rowCount) -1 ;

        if (actualRowCount == 0) {
            assessment_error = true;
            message = 'Please add at least one diagnosis in the Assessment/Diagnosis Tab.';
            focusID = 'pICD';
        }
    }

    // Plan/Management Checking
    var plan_error = false;
    var pDiagnostic = false;
    var pManagement = false;
    if (!sujective_error && !obligated_error && !objective_error && !assessment_error) {
        for (i = 1; i < 13; i++) {
            if (isChecked('diagnostic_' + i)) { pDiagnostic = true; break; }
        }
        if (isChecked('diagnostic_oth')) { pDiagnostic = true; }

        for (i = 1; i < 5; i++) {
            if (isChecked('management_' + i)) { pManagement = true; break; }
        }

        if (!pDiagnostic && !isChecked('diagnostic_NA')) {
            plan_error = true;
            message = 'Please select at least one in Diagnostic Examination or tick Not Applicable.'
        }
        else
        if (isChecked('diagnostic_oth') && getValue('diagnostic_oth_remarks') == '') {
            plan_error = true;
            message = 'Please specify the other diagnostic examination.'
            focusID = 'diagnostic_oth_remarks';
        }
        else
        if (!pManagement && !isChecked('management_NA')) {
            plan_error = true;
            message = 'Please select at least one in Management or tick Not Applicable.'
        }

    }

    if (obligated_error) {
        alert(message);
        document.getElementById('obliSerTabClick').click();
        if (focusID != '') { setFocus(focusID); }
    }
    else
    if (sujective_error) {
        alert(message);
        document.getElementById('subjectiveTabClick').click();
        setFocus(focusID);
    }
    else
    if (objective_error) {
        alert(message);
        document.getElementById('objectiveTabClick').click();
        setFocus(focusID);
    }
    else
    if (assessment_error) {
        alert(message);
        document.getElementById('assessmentTabClick').click();
        setFocus(focusID);
    }
    else
    if (plan_error) {
        alert(message);
        document.getElementById('planTabClick').click();
        if (focusID != '') { setFocus(focusID); }
    }
    else {
        setDisplay('content_div_body', 'none');
        setDisplay('wait_image_outside', '');
        document.getElementById('data_entry_soap_form').submit();
        //alert('Patient Record has been saved');
    }
}

/* Conversion */
function roundit(num) {
    return Math.round(num * 100) / 100;
}

function CmtoInch(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value / 2.54);
}

function InchToCm(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value * 2.54);
}

function KgToLb(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value * 2.20462);
}

function LbToKg(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value / 2.20462);
}

function ChkWholeNum(x) {
    if (x.value.match(/[^\d]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return x.value;
}

/* Blood Sugar */
function mmollTomgdl(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value * 18.02);
}

function mgdlTommoll(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value / 18.02);
}

/* CBC */
/*Hemoglobin*/
function gdlTommoll(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value * 0.6206).toFixed(4));
}

function mmolTogdl(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value / 0.6206).toFixed(4));
}

/*MCH*/
function pgcellTofmolcell(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value / 16.114).toFixed(4));
}

function fmolcellTopgcell(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value * 16.114).toFixed(4));
}

/*MCV*/
function umTofl(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value * 1).toFixed(4));
}

function flToum(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value / 1).toFixed(4));
}

/*WBC*/
function cell1000UlTocel10lL(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value * 1).toFixed(4));
}

function cell10LTocell1000Ul(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return parseFloat((x.value / 1).toFixed(4));
}



/* GET BMI */
function ClearForm(form){

    form.txtPhExWeightKg.value = "";
    form.txtPhExHeightCm.value = "";
    form.txtPhExBMI.value = "";
    // form.bmiDescription.value = "";

    form.txtPhExWeightLb.value = "";
    form.txtPhExHeightIn.value = "";

}

function bmi(weight, height) {

          bmindx=weight/eval(height*height);
          return bmindx;
}

function checkform(form) {

       if (form.txtPhExWeightKg.value==null||form.txtPhExWeightKg.value.length==0 || form.txtPhExHeightCm.value==null||form.txtPhExHeightCm.value.length==0){
            alert("\nPlease input value on Height (cm) and Weight (kg)");
            return false;
       }

       else if (parseFloat(form.txtPhExHeightCm.value) <= 0||
                parseFloat(form.txtPhExHeightCm.value) >=500||
                parseFloat(form.txtPhExWeightKg.value) <= 0||
                parseFloat(form.txtPhExWeightKg.value) >=500){
                alert("\nPlease enter values again. \nHeight in cm and \nWeight in kilos ");
                ClearForm(form);
                return false;
       }
       return true;

}


function computeBMI(form) {

       if (checkform(form)) {
       // clientBMI=Math.round(bmi(form.txtPhExWeightKg.value, form.txtPhExHeightCm.value/100));
       clientBMI=bmi(form.txtPhExWeightKg.value, form.txtPhExHeightCm.value/100);
       form.txtPhExBMI.value=clientBMI.toFixed(2);
          
           if (clientBMI >= 30) {
              // form.bmiDescription.value="Obesity";
               $('#bmiDescription').text(' Result Description: Obesity');
           }

           else if (clientBMI >= 25 && clientBMI <=30) {
              // form.bmiDescription.value="Overweight";
              $('#bmiDescription').text(' Result Description: Overweight');
           }

           else if (clientBMI >= 18.6 && clientBMI <= 24.9) {
              // form.bmiDescription.value="Normal weight";
              $('#bmiDescription').text(' Result Description: Normal weight');
           }

           else if (clientBMI <= 18.5) {
              // form.bmiDescription.value="Underweight!";
              $('#bmiDescription').text(' Result Description: Underweight');
           }
    
       }
       return;
}

/* Validate Report List */
function validateReportList(form_id) {
    var pStartDate = getValue('pStartDate');
    var pEndDate = getValue('pEndDate');

    if (!checkDateValue(pStartDate)) { alert('Invalid value for start date.'); setFocus('pStartDate'); }
    else if (!checkDateValue(pEndDate)) { alert('Invalid value for end date.'); setFocus('pEndDate'); }
    else if (check_date(pStartDate, pEndDate) == false) {
        alert('End Date must not be earlier than the Start Date');
        setFocus('pStartDate');
    }
    else {
        setDisplay('results_list_tbl', 'none');
        setDisplay('no_record_tbl', 'none');
        setDisplay('wait_image', '');
        document.getElementById(form_id).submit();
        setDisabled('pReportType', true);
        setDisabled('pStartDate', true);
        setDisabled('pEndDate', true);
        setDisabled('pGenerate', true);
        //setDisabled('pPrint', true);
    }
}

/* Checked Waived Reason if OTHER is selected */
function onChangeWaivedReason(reasonID, remarkID) {
    var reasonVal = getValue(reasonID);
    if (reasonVal == 'X') { setDisabled(remarkID, false); setFocus(remarkID); }
    else { setDisabled(remarkID, true); }
}

/* for table display */
function designTable(tableID) {
    var table = document.getElementById(tableID);
    var rowCount = table.rows.length;  //It will return the last Index of the row and its row count
    var actualRowCount = parseInt(rowCount)-1;

    for (i = 1; i <= actualRowCount; i++) {
        if (i % 2 == 1) {
            table.rows[i].style.backgroundColor = '#FBFCC7';
        }
        else {
            table.rows[i].style.backgroundColor = '';
        }
    }
}

/* Disable Diagnostic Examinations */
function enableDisableDiagnosticExaminations() {
    if (isChecked('diagnostic_NA')) {
        for (i = 1; i < 18; i++) {
            disableID('diagnostic_' + i + '_doctorYes');
            disableID('diagnostic_' + i + '_doctorNo');
            disableID('diagnostic_oth');
            disableID('diagnostic_13_doctorYes');
            disableID('diagnostic_14_doctorYes');
            disableID('diagnostic_9_doctorYes');
            disableID('diagnostic_15_doctorYes');
            disableID('diagnostic_17_doctorYes');
            disableID('diagnostic_18_doctorYes');
            disableID('diagnostic_oth_remarks1');
            disableID('diagnostic_oth_remarks2');
            disableID('diagnostic_oth_remarks3');

            disableID('diagnostic_13_doctorNo');
            disableID('diagnostic_14_doctorNo');
            disableID('diagnostic_9_doctorNo');
            disableID('diagnostic_15_doctorNo');
            disableID('diagnostic_17_doctorNo');
            disableID('diagnostic_18_doctorNo');


            disableID('diagnostic_' + i + '_patientRQ');
            disableID('diagnostic_' + i + '_patientRF');
            disableID('diagnostic_13_patientRQ');
            disableID('diagnostic_14_patientRQ');
            disableID('diagnostic_9_patientRQ');
            disableID('diagnostic_15_patientRQ');
            disableID('diagnostic_17_patientRQ');
            disableID('diagnostic_18_patientRQ');

            disableID('diagnostic_13_patientRF');
            disableID('diagnostic_14_patientRF');
            disableID('diagnostic_9_patientRF');
            disableID('diagnostic_15_patientRF');
            disableID('diagnostic_17_patientRF');
            disableID('diagnostic_18_patientRF');
        }

    } else {
        for (i = 1; i < 18; i++) {
            //enableID('diagnostic_' + i);
            enableID('diagnostic_' + i + '_doctorYes');
            enableID('diagnostic_' + i + '_doctorNo');
            enableID('diagnostic_oth');
            enableID('diagnostic_13_doctorYes');
            enableID('diagnostic_14_doctorYes');
            enableID('diagnostic_9_doctorYes');
            enableID('diagnostic_15_doctorYes');
            enableID('diagnostic_17_doctorYes');
            enableID('diagnostic_18_doctorYes');

            enableID('diagnostic_13_doctorNo');
            enableID('diagnostic_14_doctorNo');
            enableID('diagnostic_9_doctorNo');
            enableID('diagnostic_15_doctorNo');
            enableID('diagnostic_17_doctorNo');
            enableID('diagnostic_18_doctorNo');


            enableID('diagnostic_' + i + '_patientRQ');
            enableID('diagnostic_' + i + '_patientRF');
            enableID('diagnostic_13_patientRQ');
            enableID('diagnostic_14_patientRQ');
            enableID('diagnostic_9_patientRQ');
            enableID('diagnostic_15_patientRQ');
            enableID('diagnostic_17_patientRQ');
            enableID('diagnostic_18_patientRQ');

            enableID('diagnostic_13_patientRF');
            enableID('diagnostic_14_patientRF');
            enableID('diagnostic_9_patientRF');
            enableID('diagnostic_15_patientRF');
            enableID('diagnostic_17_patientRF');
            enableID('diagnostic_18_patientRF');
        }

    }
}

/* Disable Management */
function enableDisableManagement() {
    if (isChecked('management_NA')) {
        for (i = 1; i < 5; i++) {
            disableID('management_' + i);
        }
        disableID('management_oth');
        disableID('management_oth_remarks');
    } else {
        for (i = 1; i < 5; i++) {
            enableID('management_' + i);
        }
        enableID('management_oth');
    }
}


/* SCRIPTS ADDED BY ZIA*/
function validateEmail(emailField){
    var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

    if (reg.test(emailField.value) == false)
    {
        // alert('Invalid Email Address');
        $("#errmsg1").html("Invalid email address").show().fadeOut("slow");
        $("input[id='pHospEmailAdd']").val("");
        return false;
    }
    return true;
}
function validateEmailPx(emailField){
    var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

    if (reg.test(emailField.value) == false)
    {
        // alert('Invalid Email Address');
        $("#errmsg1").html("Invalid email address").show().fadeOut("slow");
        $("input[id='pPatientEmailAdd']").val("");
        return false;
    }
    return true;
}

function loadMunicipality(pProvCode) {
    /*Hospital Registration*/
    $("#pHospAddMun").load("loadMunicipality.php?pProvCode=" + pProvCode);
    document.getElementById("pHospAddBrgy").options.length = 0;
    document.getElementById("pHospZIPCode").options.length = 0;
}

function loadBarangay() {
    /*Hospital Registration*/
    var pProvCodeHosp = $("#pHospAddProv option:selected").val();
    var pMunCodeHosp = $("#pHospAddMun option:selected").val();
    $("#pHospAddBrgy").load("loadBarangay.php?pMunCode=" + pMunCodeHosp + "&pProvCode=" + pProvCodeHosp);
    document.getElementById("pHospZIPCode").value = "";
}

function loadZipCode() {
    /*Hospital Registration*/
    var pProvCodeHosp = $("#pHospAddProv option:selected").val();
    var pMunCodeHosp = $("#pHospAddMun option:selected").val();
    $("#pHospZIPCode").load("loadZipCode.php?pMunCode="+ pMunCodeHosp +"&pProvCode=" + pProvCodeHosp);
}


function loadMunicipalityPx(pProvCode){
    /*Client Registration*/
    $("#pPatientAddMun").load("loadMunicipality.php?pProvCode="+pProvCode);
    document.getElementById("pPatientAddBrgy").options.length = 0;
    document.getElementById("pPatientZIPCode").value = "";
    document.getElementById("pHospZIPCode").value = "";
}
function loadBarangayPx() {
    /*Client Registration*/
    var pProvCode = $("#pPatientAddProv option:selected").val();
    var pMunCode = $("#pPatientAddMun option:selected").val();
    $("#pPatientAddBrgy").load("loadBarangay.php?pMunCode=" + pMunCode+"&pProvCode=" + pProvCode);

    document.getElementById("pPatientZIPCode").value = "";
}

function loadZipCodePx() {
    /*Client Registration*/
    var pProvCode = $("#pPatientAddProv option:selected").val();
    var pMunCode = $("#pPatientAddMun option:selected").val();
    $("#pPatientZIPCode").load("loadZipCode.php?pMunCode="+ pMunCode +"&pProvCode=" + pProvCode);
}

function computeAge(dateString) {
    var today = new Date();
    var birthDate = new Date(dateString);
    var age = today.getFullYear() - birthDate.getFullYear();
    var m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}
/*Load Data to Prescribed Medicine*/
function loadMedsGeneric(pMeds){
    $("#pGeneric").load("loadMedsGeneric.php?pMeds="+pMeds);
}
function loadMedsStrength(pMeds){
    $("#pStrength").load("loadMedsStrength.php?pMeds="+pMeds);
}
function loadMedsForm(pMeds){
    $("#pForm").load("loadMedsForm.php?pMeds="+pMeds);
}
function loadMedsPackage(pMeds){
    $("#pPackage").load("loadMedsPackage.php?pMeds="+pMeds);
}
function loadMedsSalt(pMeds){
    $("#pSalt").load("loadMedsSalt.php?pMeds="+pMeds);
}
function loadMedsUnit(pMeds){
    $("#pUnit").load("loadMedsUnit.php?pMeds="+pMeds);
}
function loadMedsInsStrength(pMeds){
    $("#pStrengthInstruction").load("loadMedsStrength.php?pMeds="+pMeds);
}
function loadMedsCopay(){
        var drugCode = $("#pDrugCode").val();

        if(drugCode != ""){
            $("#pCoPayment").load("loadMedsCopay.php?mDrugCode=" + drugCode);
        }
}
function loadMedsCategory(pMeds){
    $("#pCategory").load("loadMedsCategory.php?pMeds="+pMeds);
}
/*End load data to Prescribed Medicine*/

/*Functions fo Lab Results Module*/
function checkHct(value){
    var pSex = $("#pxSex").val();
    var pAgeBracket = $("#pxAgeBracket").val();

    if(pSex == 'M' && pAgeBracket == 'adult'){
        if(value >=39 && value <=54 && value > 0){
            var pValue = 'normal';
        } else if(value < 39 && value > 0){
            var pValue = 'below';
        } else if(value >54 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHct(pValue);
    } else if (pSex == 'F' && pAgeBracket == 'adult'){
        if (value >= 34 && value <= 47 && value > 0) {
            var pValue = 'normal';
        } else if(value < 34 && value > 0){
            var pValue = 'below';
        } else if(value > 47 && value > 0) {
            var pValue = 'above';
        }
        showHideSpanHct(pValue);
    } else if((pSex == 'F' || pSex == 'M') && (pAgeBracket == 'child')){
        if (value >= 30 && value <= 42 && value > 0) {
            var pValue = 'normal';
        } else if(value < 30 && value > 0){
            var pValue = 'below';
        } else if(value > 42 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHct(pValue);
    }
}

function showHideSpanHct(status){
    if (status == 'normal') {
        document.getElementById("normalHct").style.display = '';
        document.getElementById("belowHct").style.display = 'none';
        document.getElementById("aboveHct").style.display = 'none';
    } else if(status == 'above') {
        document.getElementById("aboveHct").style.display = '';
        document.getElementById("normalHct").style.display = 'none';
        document.getElementById("belowHct").style.display = 'none';
    } else if(status == 'below') {
        document.getElementById("normalHct").style.display = 'none';
        document.getElementById("aboveHct").style.display = 'none';
        document.getElementById("belowHct").style.display = '';
    } else{
        document.getElementById("normalHct").style.display = 'none';
        document.getElementById("aboveHct").style.display = 'none';
        document.getElementById("belowHct").style.display = 'none';
    }
}

function checkHgb(value){
    var pSex = $("#pxSex").val();
    var pAgeBracket = $("#pxAgeBracket").val();

    if(pSex == 'M' && pAgeBracket == 'adult'){
        if(value >=14 && value <=18 && value > 0){
            var pValue = 'normal';
        } else if(value < 14 && value > 0){
            var pValue = 'below';
        } else if(value > 18 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    } else if (pSex == 'F' && pAgeBracket == 'adult'){
        if (value >= 11 && value <= 16 && value > 0) {
            var pValue = 'normal';
        } else if(value < 11 && value > 0){
            var pValue = 'below';
        } else if(value > 16 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    } else if((pSex == 'F' || pSex == 'M') && (pAgeBracket == 'child')){
        if (value >= 10 && value <= 14 && value > 0) {
            var pValue = 'normal';
        } else if(value < 30 && value > 0){
            var pValue = 'below';
        } else if(value > 14 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    }else if((pSex == 'F' || pSex == 'M') && (pAgeBracket == 'newborn')){
        if (value >= 15 && value <= 25 && value > 0) {
            var pValue = 'normal';
        } else if(value < 30 && value > 0){
            var pValue = 'below';
        } else if(value > 25 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    }

}

function showHideSpanHgb(status){
    if (status == 'normal') {
        document.getElementById("normalHgb").style.display = '';
        document.getElementById("belowHgb").style.display = 'none';
        document.getElementById("aboveHgb").style.display = 'none';
    } else if(status == 'above') {
        document.getElementById("aboveHgb").style.display = '';
        document.getElementById("normalHgb").style.display = 'none';
        document.getElementById("belowHgb").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalHgb").style.display = 'none';
        document.getElementById("aboveHgb").style.display = 'none';
        document.getElementById("belowHgb").style.display = '';
    } else{
        document.getElementById("normalHgb").style.display = 'none';
        document.getElementById("aboveHgb").style.display = 'none';
        document.getElementById("belowHgb").style.display = 'none';
    }
}

function checkLymphocytes(value){
    if(value >=14 && value <=44 && value > 0){
        var pValue = 'normal';
    } else if(value < 14 && value > 0){
        var pValue = 'below';
    } else if(value > 44 && value > 0){
        var pValue = 'above';
    }
    showHideSpanLymp(pValue);
}

function showHideSpanLymp(status){
    if (status == 'normal'){
        document.getElementById("normalLymp").style.display = '';
        document.getElementById("belowLymp").style.display = 'none';
        document.getElementById("aboveLymp").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveLymp").style.display = '';
        document.getElementById("normalLymp").style.display = 'none';
        document.getElementById("belowLymp").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalLymp").style.display = 'none';
        document.getElementById("aboveLymp").style.display = 'none';
        document.getElementById("belowLymp").style.display = '';
    } else{
        document.getElementById("normalLymp").style.display = 'none';
        document.getElementById("aboveLymp").style.display = 'none';
        document.getElementById("belowLymp").style.display = 'none';
    }
}

function checkMonocytes(value){
    if(value >=2 && value <=6 && value > 0){
        var pValue = 'normal';
    } else if(value < 2 && value > 0){
        var pValue = 'below';
    } else if(value > 6 && value > 0){
        var pValue = 'above';
    }
    showHideSpanMono(pValue);
}

function showHideSpanMono(status){
    if (status == 'normal'){
        document.getElementById("normalMono").style.display = '';
        document.getElementById("belowMono").style.display = 'none';
        document.getElementById("aboveMono").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveMono").style.display = '';
        document.getElementById("normalMono").style.display = 'none';
        document.getElementById("belowMono").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalMono").style.display = 'none';
        document.getElementById("aboveMono").style.display = 'none';
        document.getElementById("belowMono").style.display = '';
    } else{
        document.getElementById("normalMono").style.display = 'none';
        document.getElementById("aboveMono").style.display = 'none';
        document.getElementById("belowMono").style.display = 'none';
    }
}

function checkEosinophils(value){
    if(value >=1 && value <=5 && value > 0){
        var pValue = 'normal';
    } else if(value < 1 && value > 0){
        var pValue = 'below';
    } else if(value > 5 && value > 0){
        var pValue = 'above';
    }
    showHideSpanEosi(pValue);
}
function showHideSpanEosi(status){
    if (status == 'normal'){
        document.getElementById("normalEosi").style.display = '';
        document.getElementById("belowEosi").style.display = 'none';
        document.getElementById("aboveEosi").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveEosi").style.display = '';
        document.getElementById("normalEosi").style.display = 'none';
        document.getElementById("belowEosi").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalEosi").style.display = 'none';
        document.getElementById("aboveEosi").style.display = 'none';
        document.getElementById("belowEosi").style.display = '';
    } else{
        document.getElementById("normalEosi").style.display = 'none';
        document.getElementById("aboveEosi").style.display = 'none';
        document.getElementById("belowEosi").style.display = 'none';
    }
}

function checkUrinalysisPus(value){
    if(value >=0 && value <=3){
        var pValue = 'normal';
    } else if(value > 3 && value > 0){
        var pValue = 'above';
    }
    showHideSpanUrinePus(pValue);
}
function showHideSpanUrinePus(status){
    if (status == 'normal'){
        document.getElementById("normalUrinePus").style.display = '';
        document.getElementById("aboveUrinePus").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveUrinePus").style.display = '';
        document.getElementById("normalUrinePus").style.display = 'none';
    } else{
        document.getElementById("normalUrinePus").style.display = 'none';
        document.getElementById("aboveUrinePus").style.display = 'none';
    }
}

function checkUrineRbc(value){
    if(value >=0 && value <=2){
        var pValue = 'normal';
    } else if(value > 2 && value < 0){
        var pValue = 'above';
    }
    showHideSpanUrineRbc(pValue);
}

function showHideSpanUrineRbc(status){
    if (status == 'normal'){
        document.getElementById("normalUrineRbc").style.display = '';
        document.getElementById("aboveUrineRbc").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveUrineRbc").style.display = '';
        document.getElementById("normalUrineRbc").style.display = 'none';
    } else{
        document.getElementById("normalUrineRbc").style.display = 'none';
        document.getElementById("aboveUrineRbc").style.display = 'none';
    }
}

function checkAlbumin(value){
    if(value >=0 && value <=8){
        var pValue = 'normal';
    } else if(value > 8 && value < 0){
        var pValue = 'above';
    }
    showHideSpanUrineAlb(pValue);
}

function showHideSpanUrineAlb(status){
    if (status == 'normal'){
        document.getElementById("normalUrineAlb").style.display = '';
        document.getElementById("aboveUrineAlb").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveUrineAlb").style.display = '';
        document.getElementById("normalUrineAlb").style.display = 'none';
    } else{
        document.getElementById("normalUrineAlb").style.display = 'none';
        document.getElementById("aboveUrineAlb").style.display = 'none';
    }
}

/*Check value of LDL Cholesterol under Lipid Profile*/
function checkLipidLdl(value){
    if(value >=60 && value <=130){
        var pValue = 'normal';
    } else if(value > 130 && value > 0){
        var pValue = 'above';
    } else if(value < 60 && value > 0){
        var pValue = 'below';
    }
    showHideSpanLipidLdl(pValue);
}

/*Show Hide span notification for Lipid Profile - LDL*/
function showHideSpanLipidLdl(status){
    if (status == 'normal'){
        document.getElementById("normalLdl").style.display = '';
        document.getElementById("aboveLdl").style.display = 'none';
        document.getElementById("belowLdl").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalLdl").style.display = 'none';
        document.getElementById("aboveLdl").style.display = '';
        document.getElementById("belowLdl").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalLdl").style.display = 'none';
        document.getElementById("aboveLdl").style.display = 'none';
        document.getElementById("belowLdl").style.display = '';
    } else{
        document.getElementById("normalLdl").style.display = 'none';
        document.getElementById("aboveLdl").style.display = 'none';
        document.getElementById("belowLdl").style.display = 'none';
    }
}

/*Check value of HDL Cholesterol under Lipid Profile*/
function checkLipidHdl(value){
    if(value == 60){
        var pValue = 'normal';
    } else if(value > 60 && value > 0){
        var pValue = 'above';
    } else if(value < 60 && value > 0){
        var pValue = 'below';
    }
    showHideSpanLipidHdl(pValue);
}

/*Show Hide span notification for Lipid Profile - HDL*/
function showHideSpanLipidHdl(status){
    if (status == 'normal'){
        document.getElementById("normalHdl").style.display = '';
        document.getElementById("aboveHdl").style.display = 'none';
        document.getElementById("belowHdl").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalHdl").style.display = 'none';
        document.getElementById("aboveHdl").style.display = '';
        document.getElementById("belowHdl").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalHdl").style.display = 'none';
        document.getElementById("aboveHdl").style.display = 'none';
        document.getElementById("belowHdl").style.display = '';
    } else{
        document.getElementById("normalHdl").style.display = 'none';
        document.getElementById("aboveHdl").style.display = 'none';
        document.getElementById("belowHdl").style.display = 'none';
    }
}

/*Check value of Cholesterol under Lipid Profile*/
function checkLipidChol(value){
    if(value < 200 && value >= 0){
        var pValue = 'normal';
    } else if(value >= 200 && value > 0){
        var pValue = 'above';
    }
    showHideSpanLipidChol(pValue);
}

/*Show Hide span notification for Lipid Profile - Cholesterol*/
function showHideSpanLipidChol(status){
    if (status == 'normal'){
        document.getElementById("normalChol").style.display = '';
        document.getElementById("aboveChol").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalChol").style.display = 'none';
        document.getElementById("aboveChol").style.display = '';
    } else{
        document.getElementById("normalChol").style.display = 'none';
        document.getElementById("aboveChol").style.display = 'none';
    }
}

/*Check value of Triglycerides under Lipid Profile*/
function checkLipidTrigly(value){
    if(value < 150 && value > 0){
        var pValue = 'normal';
    } else if(value >= 150 && value > 0){
        var pValue = 'above';
    }
    showHideSpanLipidTrigly(pValue);
}

/*Show Hide span notification for Lipid Profile - Triglycerides*/
function showHideSpanLipidTrigly(status){
    if (status == 'normal'){
        document.getElementById("normalTrigly").style.display = '';
        document.getElementById("aboveTrigly").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalTrigly").style.display = 'none';
        document.getElementById("aboveTrigly").style.display = '';
    } else{
        document.getElementById("normalTrigly").style.display = 'none';
        document.getElementById("aboveTrigly").style.display = 'none';
    }
}

/*Check value of Glucose under Lipid Profile*/
function checkLipidGlucose(value){
    if(value >=70 && value <= 100){
        var pValue = 'normal';
    } else if(value > 100 && value > 0){
        var pValue = 'above';
    } else if(value < 70 && value > 0){
        var pValue = 'below';
    }
    showHideSpanLipidGlucose(pValue);
}

/*Show Hide span notification for Lipid Profile - Glucose*/
function showHideSpanLipidGlucose(status){
    if (status == 'normal'){
        document.getElementById("normalGlucose").style.display = '';
        document.getElementById("aboveGlucose").style.display = 'none';
        document.getElementById("belowGlucose").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalGlucose").style.display = 'none';
        document.getElementById("aboveGlucose").style.display = '';
        document.getElementById("belowGlucose").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalGlucose").style.display = 'none';
        document.getElementById("aboveGlucose").style.display = 'none';
        document.getElementById("belowGlucose").style.display = '';
    } else{
        document.getElementById("normalGlucose").style.display = 'none';
        document.getElementById("aboveGlucose").style.display = 'none';
        document.getElementById("belowGlucose").style.display = 'none';
    }
}

/* Checked Observation n if OTHER is selected */
function onChangeObservation(reasonID, remarkID) {
    var reasonVal = getValue(reasonID);
    if (reasonVal == '99') { setDisabled(remarkID, false); setFocus(remarkID); }
    else { setDisabled(remarkID, true); }
}
/* Checked Findings n if OTHER is selected */
function onChangeFindings(reasonID, remarkID) {
    var reasonVal = getValue(reasonID);
    if (reasonVal == '99') { setDisabled(remarkID, false); setFocus(remarkID); }
    else { setDisabled(remarkID, true); }
}

/*Add Observation List - Chest X-ray Results*/
function addXrayObservation() {
    var observation = $("#diagnostic_4_chest_observe");
    var remarks = $("#diagnostic_4_chest_observe_remarks");
    var observationTxt = $("#diagnostic_4_chest_observe option:selected").text();
    var already_in_row = $("#tblChestObservation tr > td:contains('"+observation.val()+"')").length;

    if(observation.val() != "") {
        if(already_in_row == 0) {
            $("#tblChestObservation tr:last").before("<tr> \
                                                             <td style='vertical-align: middle; text-align: left;font-size:11px;font-weight: normal;'><input type='hidden' name='observation[]' value='"+observation.val()+"'>"+observationTxt+"</td> \
                                                             <td style='vertical-align: middle;font-size:11px;font-weight: normal;'><input type='hidden' name='observationRemarks[]' value='"+remarks.val()+"'>"+remarks.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");
            observation.val("");
            observation.prop("rows","1");
            remarks.val("");
            observation.focus();
        }
        else {
            alert("Observation already added in list.");
            observation.val("");
            observation.prop("rows","1");
            remarks.val("");
            observation.focus();
        }
    }
    else {
        alert("Please input observation details");
        observation.focus();
    }
}

/*Disable medicine fields*/
function disableMedicine(){
    disableID('radDispenseY');
    disableID('radDispenseN');
    disableID('pDispensedDate');
    disableID('pDrugCode');
    disableID('pGenericFreeText');
    disableID('pGeneric');
    disableID('pSalt');
    disableID('pStrength');
    disableID('pForm');
    disableID('pUnit');
    disableID('pPackage');
    disableID('pQuantity');
    disableID('pUnitPrice');
    disableID('pQtyInstruction');
    disableID('pStrengthInstruction');
    disableID('pFrequencyInstruction');
    disableID('pPrescDoctor');
    disableID('pDispensingPersonnel');
}

function enableMedicine(){
    enableID('radDispenseY');
    enableID('radDispenseN');
    enableID('pDispensedDate');
    enableID('pDrugCode');
    enableID('pGenericFreeText');
    enableID('pGeneric');
    enableID('pSalt');
    enableID('pStrength');
    enableID('pForm');
    enableID('pUnit');
    enableID('pPackage');
    enableID('pQuantity');
    enableID('pUnitPrice');
    enableID('pQtyInstruction');
    enableID('pStrengthInstruction');
    enableID('pFrequencyInstruction');
    enableID('pPrescDoctor');
    enableID('pDispensingPersonnel');
}
/*Add Medicine in the table*/
function addMedicine() {
    var drugCode = $("#pDrugCode");
    var drugCompleteDesc = $("#pDrugCode option:selected").text();
    var genCode = $("#pGeneric");
    var genDesc = $("#pGeneric option:selected").text();
    var genName = $("#pGenericFreeText");
    var salt = $("#pSalt");
    var saltDesc = $("#pSalt option:selected").text();
    var strength = $("#pStrength");
    var strengthDesc = $("#pStrength option:selected").text();
    var form = $("#pForm");
    var formDesc = $("#pForm option:selected").text();
    var unit = $("#pUnit");
    var unitDesc = $("#pUnit option:selected").text();
    var package = $("#pPackage");
    var packageDesc = $("#pPackage option:selected").text();
    var qty = $("#pQuantity");
    var unitPrice = $("#pUnitPrice");

    var category = $("#pCategory");
    // var coPayment = $("#pCoPayment");
    var totalPrice = qty.val() * unitPrice.val();
    var qtyIns = $("#pQtyInstruction");
    var strengthIns = $("#pStrengthInstruction");
    var frequency = $("#pFrequencyInstruction");
    var prescibingDoctor = $("#pPrescDoctor");
    var already_in_row = $("#tblResultsMeds tr > td:contains('"+genCode.val()+"')").length;

    //dispense     
     var dispensedDate = $("#pDispensedDate");
     var dispensensingPersonnel = $("#pDispensingPersonnel");
     var chkDispensedY = $("#radDispenseY").is(":checked");
     var chkDispensedN = $("#radDispenseN").is(":checked");

     var chkPrescribeYes= $("#medsStatusYes").is(":checked");

     if(chkDispensedY == true){
        var dispensedValueResult = "Y";
        var dispensedResult = "YES";
     } else if(chkDispensedN == true){
        var dispensedValueResult = "N";
        var dispensedResult = "NO";
     }

     var chkOthMeds = $("#chkOthMeds").is(":checked");
     var drugGrouping = $("#pOthMedDrugGrouping");
     var drugGroupingDesc = $("#pOthMedDrugGrouping option:selected").text();

    var count = $('#tblBodyMeds').children('tr').length;

    if(chkDispensedY == true && dispensedDate.val() == ""){
        alert("Dispense Date is required if Drug/Medicine is dispensed.");
    } 

    else if (chkOthMeds == true && genName.val() == "") {
        alert("Input Other Drug Medicine");
    }

    else if (chkOthMeds == true && drugGrouping.val() == "") {
        alert("Drug Grouping for other drug/medicine is required");
    }

    else if (chkPrescribeYes == true) {       

            //if(genCode.val() != "" && strength.val() != "" && form.val() != "" && package.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" &&  frequency.val() != "") {          
            if(drugCode.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" &&  frequency.val() != "") {          

                if(already_in_row == 0) {
                    //Prescribe Medicine
                    $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                            <td>"+drugCompleteDesc+"</td> \
                                                             <td>"+drugGrouping.val()+"</td> \
                                                             <td>"+qty.val()+"</td> \
                                                             <td>"+unitPrice.val()+"</td> \
                                                             <td>"+totalPrice+"</td> \
                                                             <td>"+qtyIns.val()+"</td> \
                                                             <td>"+strengthIns.val()+"</td> \
                                                             <td>"+frequency.val()+"</td> \
                                                             <td>"+dispensedResult+"</td> \
                                                             <td>"+dispensedDate.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");


                   
                    //Dispense Medicine
                    $("#tblResultsDispensingMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                            <td><input type='hidden' name='pDrugCategory[]' value='"+category.val()+"'><input type='hidden' name='pDrugCodeMeds[]' value='"+drugCode.val()+"'><input type='hidden' name='pGenCodeMeds[]' value='"+genCode.val()+"'><input type='hidden' name='pSaltCodeMeds[]' value='"+salt.val()+"'><input type='hidden' name='pStrengthCodeMeds[]' value='"+strength.val()+"'><input type='hidden' name='pFormCodeMeds[]' value='"+form.val()+"'><input type='hidden' name='pUnitCodeMeds[]' value='"+unit.val()+"'><input type='hidden' name='pPackageCodeMeds[]' value='"+package.val()+"'><input type='hidden' name='pOtherMeds[]' value=''>"+drugCompleteDesc+"</td> \
                                                             <td><input type='hidden' name='pOthMedDrugGrouping[]' value='"+drugGrouping.val()+"'>"+drugGrouping.val()+"</td> \
                                                             <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
                                                             <td><input type='hidden' name='pUnitPriceMeds[]' value='"+unitPrice.val()+"'>"+unitPrice.val()+"</td> \
                                                             <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice+"'>"+totalPrice+"</td> \
                                                             <td><input type='hidden' name='pQtyInsMeds[]' value='"+qtyIns.val()+"'>"+qtyIns.val()+"</td> \
                                                             <td><input type='hidden' name='pStrengthInsMeds[]' value='"+strengthIns.val()+"'>"+strengthIns.val()+"</td> \
                                                             <td><input type='hidden' name='pFrequencyInsMeds[]' value='"+frequency.val()+"'>"+frequency.val()+"</td> \
                                                             <td><input type='hidden' name='pIsDispensed[]' value='"+dispensedValueResult+"'>"+dispensedResult+"</td> \
                                                             <td><input type='hidden' name='pDispensedDate[]' value='"+dispensedDate.val()+"'>"+dispensedDate.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");


                    drugCode.val("");
                    category.empty();
                    genCode.empty();                
                    qty.val("");
                    unitPrice.val("");
                    strength.empty();
                    form.empty();
                    package.empty();
                    qtyIns.val("");
                    strengthIns.empty();
                    strengthIns.val("");
                    frequency.val("");
                    genName.val("");
                    salt.empty();
                    unit.empty();
                    dispensedDate.val("");
                    drugGrouping.val("");
                    
                }
                else {
                    alert("Medicine already added in list.");
                    drugCode.val("");
                    genCode.empty();
                    category.empty();
                    qty.val("");
                    unitPrice.val("");
                    strength.empty();
                    form.empty();
                    package.empty();
                    qtyIns.val("");
                    strengthIns.empty();
                    strengthIns.val("");
                    frequency.val("");
                    genName.val("");
                    salt.empty();
                    unit.empty();
                    dispensedDate.val("");
                    drugGrouping.val("");
                }
            }
            else if(genName.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" && frequency.val() != "") {
                    $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                             <td>"+genName.val()+"</td> \
                                                             <td>"+drugGrouping.val()+"</td> \
                                                             <td>"+qty.val()+"</td> \
                                                             <td>"+unitPrice.val()+"</td> \
                                                             <td>"+totalPrice+"</td> \
                                                             <td>"+qtyIns.val()+"</td> \
                                                             <td>"+strengthIns.val()+"</td> \
                                                             <td>"+frequency.val()+"</td> \
                                                             <td>"+dispensedResult+"</td> \
                                                             <td>"+dispensedDate.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");
                    
                    //Dispense Medicine
                    $("#tblResultsDispensingMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                            <td><input type='hidden' name='pDrugCategory[]' value='"+category.val()+"'><input type='hidden' name='pDrugCodeMeds[]' value=''><input type='hidden' name='pGenCodeMeds[]' value=''><input type='hidden' name='pSaltCodeMeds[]' value=''><input type='hidden' name='pStrengthCodeMeds[]' value=''><input type='hidden' name='pFormCodeMeds[]' value=''><input type='hidden' name='pUnitCodeMeds[]' value=''><input type='hidden' name='pPackageCodeMeds[]' value=''><input type='hidden' name='pOtherMeds[]' value='"+genName.val()+"'>"+genName.val()+"</td> \
                                                             <td><input type='hidden' name='pOthMedDrugGrouping[]' value='"+drugGrouping.val()+"'>"+drugGrouping.val()+"</td> \
                                                             <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
                                                             <td><input type='hidden' name='pUnitPriceMeds[]' value='"+unitPrice.val()+"'>"+unitPrice.val()+"</td> \
                                                             <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice+"'>"+totalPrice+"</td> \
                                                             <td><input type='hidden' name='pQtyInsMeds[]' value='"+qtyIns.val()+"'>"+qtyIns.val()+"</td> \
                                                             <td><input type='hidden' name='pStrengthInsMeds[]' value='"+strengthIns.val()+"'>"+strengthIns.val()+"</td> \
                                                             <td><input type='hidden' name='pFrequencyInsMeds[]' value='"+frequency.val()+"'>"+frequency.val()+"</td> \
                                                             <td><input type='hidden' name='pIsDispensed[]' value='"+dispensedValueResult+"'>"+dispensedResult+"</td> \
                                                             <td><input type='hidden' name='pDispensedDate[]' value='"+dispensedDate.val()+"'>"+dispensedDate.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");

                    drugCode.val("");
                    genCode.empty();
                    category.empty();
                    qty.val("");
                    unitPrice.val("");
                    strength.empty();
                    form.empty();
                    package.empty();
                    qtyIns.val("");
                    strengthIns.empty();
                    strengthIns.val("");
                    frequency.val("");
                    genName.val("");
                    salt.empty();
                    unit.empty();
                    dispensedDate.val("");
                    drugGrouping.val("");
            }
            else {
                alert("Please fill up the ff.:\n(1) Prescribing Physician. (2) If dispensed, Dispensed Date.\n(3) Complete Drug Description, Quantity, Actual Unit Price, Instruction: Quantity, Strength and Frequency; \n\nOR if not available in the list, Input the following:\n\n" +
                    "(1) Prescribing Physician. (2) If dispensed, Dispensed Date.\n(3)Other Drug/Medicine in format of [Generic Name/Salt/Strength/Form/Unit/Package], Quantity, Actual Unit Price, Instruction: Quantity, Strength and Frequency to add on the list of Medicine!");
                drugCode.focus();
            }
        
    } else {
        alert("Choose With prescribe drug/medicine button to Add the Medicine on the list.");
            drugCode.focus();
    }

}

/*Add Medicine in the table*/
function addMedicineFollowups() {
    var drugCode = $("#pDrugCode");
    var drugCompleteDesc = $("#pDrugCode option:selected").text();
    var genCode = $("#pGeneric");
    var genDesc = $("#pGeneric option:selected").text();
    var genName = $("#pGenericFreeText");
    var salt = $("#pSalt");
    var saltDesc = $("#pSalt option:selected").text();
    var strength = $("#pStrength");
    var strengthDesc = $("#pStrength option:selected").text();
    var form = $("#pForm");
    var formDesc = $("#pForm option:selected").text();
    var unit = $("#pUnit");
    var unitDesc = $("#pUnit option:selected").text();
    var package = $("#pPackage");
    var packageDesc = $("#pPackage option:selected").text();
    var qty = $("#pQuantity");
    var unitPrice = $("#pUnitPrice");
    // var coPayment = $("#pCoPayment");
    var totalPrice = qty.val() * unitPrice.val();
    var qtyIns = $("#pQtyInstruction");
    var strengthIns = $("#pStrengthInstruction");
    var frequency = $("#pFrequencyInstruction");
    var prescibingDoctor = $("#pPrescDoctor");
    var already_in_row = $("#tblResultsMeds tr > td:contains('"+genCode.val()+"')").length;

    //dispense     
     var dispensedDate = $("#pDispensedDate");
     var dispensensingPersonnel = $("#pDispensingPersonnel");
     var chkDispensedY = $("#radDispenseY").is(":checked");
     var chkDispensedN = $("#radDispenseN").is(":checked");

     var chkPrescribeYes= $("#medsStatusYes").is(":checked");

     if(chkDispensedY == true){
        var dispensedValueResult = "Y";
        var dispensedResult = "YES";
     } else if(chkDispensedN == true){
        var dispensedValueResult = "N";
        var dispensedResult = "NO";
     }

     var chkOthMeds = $("#chkOthMeds").is(":checked");
     var drugGrouping = $("#pOthMedDrugGrouping");
     var drugGroupingDesc = $("#pOthMedDrugGrouping option:selected").text();


    var count = $('#tblBodyMeds').children('tr').length;

    if(chkDispensedY == true && dispensedDate.val() == ""){
        alert("Dispense Date is required if Drug/Medicine is dispensed.");
    }
    else if (chkOthMeds == true && drugGrouping.val() == "") {
        alert("Drug Grouping for other drug/medicine is required");
    }
    else if (chkPrescribeYes == true) {       

            //if(genCode.val() != "" && strength.val() != "" && form.val() != "" && package.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" &&  frequency.val() != "") {          
            if(drugCode.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" &&  frequency.val() != "") {          

                if(already_in_row == 0) {
                    //Prescribe Medicine
                   
                    //Dispense Medicine
                    $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                            <td><input type='hidden' name='pDrugCodeMeds[]' value='"+drugCode.val()+"'><input type='hidden' name='pGenCodeMeds[]' value='"+genCode.val()+"'><input type='hidden' name='pSaltCodeMeds[]' value='"+salt.val()+"'><input type='hidden' name='pStrengthCodeMeds[]' value='"+strength.val()+"'><input type='hidden' name='pFormCodeMeds[]' value='"+form.val()+"'><input type='hidden' name='pUnitCodeMeds[]' value='"+unit.val()+"'><input type='hidden' name='pPackageCodeMeds[]' value='"+package.val()+"'><input type='hidden' name='pOtherMeds[]' value=''>"+drugCompleteDesc+"</td> \
                                                             <td><input type='hidden' name='pOthMedDrugGrouping[]' value='"+drugGrouping.val()+"'>"+drugGrouping.val()+"</td> \
                                                             <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
                                                             <td><input type='hidden' name='pUnitPriceMeds[]' value='"+unitPrice.val()+"'>"+unitPrice.val()+"</td> \
                                                             <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice+"'>"+totalPrice+"</td> \
                                                             <td><input type='hidden' name='pQtyInsMeds[]' value='"+qtyIns.val()+"'>"+qtyIns.val()+"</td> \
                                                             <td><input type='hidden' name='pStrengthInsMeds[]' value='"+strengthIns.val()+"'>"+strengthIns.val()+"</td> \
                                                             <td><input type='hidden' name='pFrequencyInsMeds[]' value='"+frequency.val()+"'>"+frequency.val()+"</td> \
                                                             <td><input type='hidden' name='pIsDispensed[]' value='"+dispensedValueResult+"'>"+dispensedResult+"</td> \
                                                             <td><input type='hidden' name='pDispensedDate[]' value='"+dispensedDate.val()+"'>"+dispensedDate.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");


                    drugCode.val("");
                    genCode.empty();                
                    qty.val("");
                    unitPrice.val("");
                    strength.empty();
                    form.empty();
                    package.empty();
                    qtyIns.val("");
                    strengthIns.empty();
                    strengthIns.val("");
                    frequency.val("");
                    genName.val("");
                    salt.empty();
                    unit.empty();
                    dispensedDate.val("");
                    drugGrouping.val("");
                    
                }
                else {
                    alert("Medicine already added in list.");
                    drugCode.val("");
                    genCode.empty();
                    qty.val("");
                    unitPrice.val("");
                    strength.empty();
                    form.empty();
                    package.empty();
                    qtyIns.val("");
                    strengthIns.empty();
                    strengthIns.val("");
                    frequency.val("");
                    genName.val("");
                    salt.empty();
                    unit.empty();
                    dispensedDate.val("");
                    drugGrouping.val("");
                }
            }
            else if(genName.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" && frequency.val() != "") {
                   
                    //Dispense Medicine
                    $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                            <td><input type='hidden' name='pDrugCodeMeds[]' value=''><input type='hidden' name='pGenCodeMeds[]' value=''><input type='hidden' name='pSaltCodeMeds[]' value=''><input type='hidden' name='pStrengthCodeMeds[]' value=''><input type='hidden' name='pFormCodeMeds[]' value=''><input type='hidden' name='pUnitCodeMeds[]' value=''><input type='hidden' name='pPackageCodeMeds[]' value=''><input type='hidden' name='pOtherMeds[]' value='"+genName.val()+"'>"+genName.val()+"</td> \
                                                             <td><input type='hidden' name='pOthMedDrugGrouping[]' value='"+drugGrouping.val()+"'>"+drugGrouping.val()+"</td> \
                                                             <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
                                                             <td><input type='hidden' name='pUnitPriceMeds[]' value='"+unitPrice.val()+"'>"+unitPrice.val()+"</td> \
                                                             <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice+"'>"+totalPrice+"</td> \
                                                             <td><input type='hidden' name='pQtyInsMeds[]' value='"+qtyIns.val()+"'>"+qtyIns.val()+"</td> \
                                                             <td><input type='hidden' name='pStrengthInsMeds[]' value='"+strengthIns.val()+"'>"+strengthIns.val()+"</td> \
                                                             <td><input type='hidden' name='pFrequencyInsMeds[]' value='"+frequency.val()+"'>"+frequency.val()+"</td> \
                                                             <td><input type='hidden' name='pIsDispensed[]' value='"+dispensedValueResult+"'>"+dispensedResult+"</td> \
                                                             <td><input type='hidden' name='pDispensedDate[]' value='"+dispensedDate.val()+"'>"+dispensedDate.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");

                    drugCode.val("");
                    genCode.empty();
                    qty.val("");
                    unitPrice.val("");
                    strength.empty();
                    form.empty();
                    package.empty();
                    qtyIns.val("");
                    strengthIns.empty();
                    strengthIns.val("");
                    frequency.val("");
                    genName.val("");
                    salt.empty();
                    unit.empty();
                    dispensedDate.val("");
            }
            else {
                alert("Please fill up the ff.:\n(1) Prescribing Physician. (2) If dispensed, Dispensed Date.\n(3) Complete Drug Description, Quantity, Actual Unit Price, Instruction: Quantity, Strength and Frequency; \n\nOR if not available in the list, Input the following:\n\n" +
                    "(1) Prescribing Physician. (2) If dispensed, Dispensed Date.\n(3)Other Drug/Medicine in format of [Generic Name/Salt/Strength/Form/Unit/Package], Quantity, Actual Unit Price, Instruction: Quantity, Strength and Frequency to add on the list of Medicine!");
                drugCode.focus();
            }
        
    } else {
        alert("Choose With prescribe drug/medicine button to Add the Medicine on the list.");
            drugCode.focus();
    }

}

/*Add Medicine in the table of CF4 Module*/
function addMedicineCf4() {
    var drugCode = $("#pDrugCode");
    var drugCompleteDesc = $("#pDrugCode option:selected").text();
    var genCode = $("#pGeneric");
    var genDesc = $("#pGeneric option:selected").text();
    var genName = $("#pGenericFreeText");
    var strength = $("#pStrength");
    var strengthDesc = $("#pStrength option:selected").text();
    var form = $("#pForm");
    var formDesc = $("#pForm option:selected").text();
    var salt = $("#pSalt");
    var saltDesc = $("#pSalt option:selected").text();
    var unit = $("#pUnit");
    var unitDesc = $("#pUnit option:selected").text();
    var package = $("#pPackage");
    var packageDesc = $("#pPackage option:selected").text();

    var route = $("#pRoute");
    var qty = $("#pQuantity");
    var totalPrice = $("#pTotalPrice");
    var insFrequency = $("#pFrequencyInstruction");
    var already_in_row = $("#tblResultsMeds tr > td:contains('"+genCode.val()+"')").length;

    var count = $('#tblBodyMeds').children('tr').length;

    if(drugCode.val() != "" && qty.val() != "" && totalPrice.val() != "" && route.val() != ""  && insFrequency.val() != "") {
        $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                 <td><input type='hidden' name='pDrugCodeMeds[]' value='"+drugCode.val()+"'><input type='hidden' name='pGenCodeMeds[]' value='"+genCode.val()+"'><input type='hidden' name='pSaltCodeMeds[]' value='"+salt.val()+"'><input type='hidden' name='pStrengthCodeMeds[]' value='"+strength.val()+"'><input type='hidden' name='pFormCodeMeds[]' value='"+form.val()+"'><input type='hidden' name='pUnitCodeMeds[]' value='"+unit.val()+"'><input type='hidden' name='pPackageCodeMeds[]' value='"+package.val()+"'><input type='hidden' name='pGenericNameMeds[]' value=''>"+drugCompleteDesc+"</td> \
                                                 <td><input type='hidden' name='pRouteMeds[]' value='"+route.val()+"'>"+route.val()+"</td> \
                                                 <td><input type='hidden' name='pFrequencyMeds[]' value='"+insFrequency.val()+"'>"+insFrequency.val()+"</td> \
                                                 <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
                                                 <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice.val()+"'>"+totalPrice.val()+"</td> \
                                                 <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                           </tr>");

        if(already_in_row == 0) {
            drugCode.val("");
            genName.val("");
            genCode.empty();
            salt.empty();
            strength.empty();
            form.empty();
            unit.empty();
            package.empty();
            route.val("");
            insFrequency.val("");
            qty.val("");
            totalPrice.val("");
        }
        else {
            alert("Medicine already added in list.");
            drugCode.val("");
            genName.val("");
            genCode.empty();
            salt.empty();
            strength.empty();
            form.empty();
            unit.empty();
            package.empty();
            route.val("");
            insFrequency.val("");
            qty.val("");
            totalPrice.val("");
        }
    }
    else if(genName.val() != "" && qty.val() != "" && totalPrice.val() != "" && route.val() != "" && insFrequency.val() != "") {
        $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
                                                 <td><input type='hidden' name='pDrugCodeMeds[]' value=''><input type='hidden' name='pGenCodeMeds[]' value=''><input type='hidden' name='pSaltCodeMeds[]' value=''><input type='hidden' name='pStrengthCodeMeds[]' value=''><input type='hidden' name='pFormCodeMeds[]' value=''><input type='hidden' name='pUnitCodeMeds[]' value=''><input type='hidden' name='pPackageCodeMeds[]' value=''><input type='hidden' name='pGenericNameMeds[]' value='"+genName.val()+"'>"+genName.val()+"</td> \
                                                 <td><input type='hidden' name='pRouteMeds[]' value='"+route.val()+"'>"+route.val()+"</td> \
                                                 <td><input type='hidden' name='pFrequencyMeds[]' value='"+insFrequency.val()+"'>"+insFrequency.val()+"</td> \
                                                 <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
                                                 <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice.val()+"'>"+totalPrice.val()+"</td> \
                                                 <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                           </tr>");

            drugCode.val("");
            genName.val("");
            genCode.empty();
            salt.empty();
            strength.empty();
            form.empty();
            unit.empty();
            package.empty();
            route.val("");
            insFrequency.val("");
            qty.val("");
            totalPrice.val("");
    }
    else {
        alert("Please fill up the ff.:\nComplete Drug Description, Route, Frequency, Quantity, and Total Amount Price; \n\nOR if not available in the list, Input the following:\n\n" +
            "Generic Name/Salt/Strength/Form/Unit/Package, Route, Frequency, Quantity, and Total Amount Price to add on the list of Medicine!");
        drugCode.focus();
    }
}
/*START HSA MODULE*/
function addOperationHist() {
    var operation = $("#txaMedHistOpHist");
    var op_date = $("#txtMedHistOpDate");
    var already_in_row = $("#tblMedHistOpHist tr > td:contains('"+operation.val()+"') + td:contains('"+op_date.val()+"')").length;

    if(operation.val() != "" && op_date.val() != "") {
        if(already_in_row == 0) {
            $("#tblMedHistOpHist tr:last").before("<tr> \
                                                     <td style='vertical-align: middle; text-align: left;'><input type='hidden' name='operation[]' value='"+operation.val()+"'>"+operation.val()+"</td> \
                                                     <td style='vertical-align: middle;'><input type='hidden' name='operationDate[]' value='"+op_date.val()+"'>"+op_date.val()+"</td> \
                                                     <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#txaMedHistOpHist\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                   </tr>");
            operation.val("");
            operation.prop("rows","1");
            op_date.val("");
            operation.focus();
        }
        else {
            alert("Operation already added in list.");
            operation.val("");
            operation.prop("rows","1");
            op_date.val("");
            operation.focus();
        }
    }
    else {
        alert("Please input operation details and date of operation.");
        operation.focus();
    }
}

/*CF4 MODULE - ADD LIST IN COURSE IN THE WARD SUB-MODULE*/
function addCourseInTheWard() {
    var docAction = $("#txtWardDocAction");
    var action_date = $("#txtWardDateOrder");
    var already_in_row = $("#tblCourseWard tr > td:contains('"+action_date.val()+"') + td:contains('"+docAction.val()+"')").length;

    if(docAction.val() != "" && action_date.val() != "") {
        if(already_in_row == 0) {
            $("#tblCourseWard tr:last").before("<tr> \
                                                 <td style='vertical-align: middle;'><input type='hidden' name='pDateActionWard[]' value='"+action_date.val()+"'>"+action_date.val()+"</td> \
                                                 <td style='vertical-align: middle; text-align: left;text-transform: uppercase;'><input type='hidden' name='pActionWard[]' value='"+docAction.val()+"'>"+docAction.val()+"</td> \
                                                 <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#txtWardDocAction\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                               </tr>");
            docAction.val("");
            docAction.prop("rows","1");
            action_date.val("");
            docAction.focus();
        }
        else {
            alert("Action already added in list.");
            docAction.val("");
            docAction.prop("rows","1");
            action_date.val("");
            docAction.focus();
        }
    }
    else {
        alert("Please input Doctor's Action and Date of Action.");
        docAction.focus();
    }
}

function enDisSpecificMedHist(m_disease_code) {
    var checkbox = $("#chkMedHistDiseases_"+m_disease_code);
    var boolChecked = (checkbox.is(":checked")) ? false : true;
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if(m_disease_code == "001") {
        $("#txtMedHistAllergy").attr("disabled",boolChecked);
        $("#txtMedHistAllergy").val("");
    }
    else if (m_disease_code == "003") {
        $("#txtMedHistCancer").attr("disabled",boolChecked);
        $("#txtMedHistCancer").val("");
    }
    else if (m_disease_code == "009") {
        $("#txtMedHistHepatitis").attr("disabled",boolChecked);
        $("#txtMedHistHepatitis").val("");
    }
    else if (m_disease_code == "011") {
        $("#txtMedHistBPDiastolic").attr("disabled",boolChecked);
        $("#txtMedHistBPSystolic").attr("disabled",boolChecked);

        $("#txtMedHistBPDiastolic").val("");
        $("#txtMedHistBPSystolic").val("");
    }
    else if (m_disease_code == "015") {
        $("#txtMedHistPTB").attr("disabled",boolChecked);
        $("#txtMedHistPTB").val("");
    }
    else if (m_disease_code == "016") {
        $("#txtMedHistExPTB").attr("disabled",boolChecked);
        $("#txtMedHistExPTB").val("");
    }
    else if (m_disease_code == "998") {
        $("#txaMedHistOthers").attr("disabled",boolChecked);
        $("#txaMedHistOthers").val("");
    }
    else if (m_disease_code == "999") {
        for(x=1;x<=9;x++){
            $("#chkMedHistDiseases_00"+x).attr("disabled",boolCheckedNone);
        }
        for(x=10;x<=18;x++){
            $("#chkMedHistDiseases_0"+x).attr("disabled",boolCheckedNone);
            $("#chkMedHistDiseases_998").attr("disabled",boolCheckedNone);
        }

        $("#txtMedHistAllergy").attr("disabled",true);
        $("#txtMedHistCancer").attr("disabled",true);
        $("#txtMedHistHepatitis").attr("disabled",true);
        $("#txtMedHistBPDiastolic").attr("disabled",true);
        $("#txtMedHistBPSystolic").attr("disabled",true);
        $("#txtMedHistPTB").attr("disabled",true);
        $("#txtMedHistExPTB").attr("disabled",true);
        $("#txaMedHistOthers").attr("disabled",true);
    }
}

function enDisSpecificFamHist(m_disease_code) {
    var checkbox = $("#chkFamHistDiseases_"+m_disease_code);
    var boolChecked = (checkbox.is(":checked")) ? false : true;
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if(m_disease_code == "001") {
        $("#txtFamHistAllergy").attr("disabled",boolChecked);
        $("#txtFamHistAllergy").val("");
    }
    else if (m_disease_code == "003") {
        $("#txtFamHistCancer").attr("disabled",boolChecked);
        $("#txtFamHistCancer").val("");
    }
    else if (m_disease_code == "009") {
        $("#txtFamHistHepatitis").attr("disabled",boolChecked);
        $("#txtFamHistHepatitis").val("");
    }
    else if (m_disease_code == "011") {
        $("#txtFamHistBPDiastolic").attr("disabled",boolChecked);
        $("#txtFamHistBPSystolic").attr("disabled",boolChecked);

        $("#txtFamHistBPDiastolic").val("");
        $("#txtFamHistBPSystolic").val("");
    }
    else if (m_disease_code == "015") {
        $("#txtFamHistPTB").attr("disabled",boolChecked);
        $("#txtFamHistPTB").val("");
    }
    else if (m_disease_code == "016") {
        $("#txtFamHistExPTB").attr("disabled",boolChecked);
        $("#txtFamHistExPTB").val("");
    }
    else if (m_disease_code == "998") {
        $("#txaFamHistOthers").attr("disabled",boolChecked);
        $("#txaFamHistOthers").val("");
    }

    else if (m_disease_code == "006") {
        if(!boolChecked){  
            // $("#list3_1").addClass("active"); 
            // $("#tab3_1").addClass("active in");
            $("#list3_1").show();
            // $("#tab3_1").show();
        } else {
            $("#list3_1").hide();
            // $("#tab3_1").removeClass("active");
            // $("#list3_1").removeClass("active"); 
        }
    } 

    else if (m_disease_code == "999") {
        for(x=1;x<=9;x++){
            $("#chkFamHistDiseases_00"+x).attr("disabled",boolCheckedNone);
        }
        for(x=10;x<=18;x++){
            $("#chkFamHistDiseases_0"+x).attr("disabled",boolCheckedNone);
            $("#chkFamHistDiseases_998").attr("disabled",boolCheckedNone);
        }

        $("#txtFamHistAllergy").attr("disabled",true);
        $("#txtFamHistCancer").attr("disabled",true);
        $("#txtFamHistHepatitis").attr("disabled",true);
        $("#txtFamHistBPSystolic").attr("disabled",true);
        $("#txtFamHistBPDiastolic").attr("disabled",true);
        $("#txtFamHistPTB").attr("disabled",true);
        $("#txtFamHistExPTB").attr("disabled",true);
        $("#txaFamHistOthers").attr("disabled",true);

    }
}

function enDisImmuneChild(m_immune_code) {
    var checkbox = $("#chkImmChild_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=9;x++){
            $("#chkImmChild_C0"+x).attr("disabled", boolCheckedNone);
        }
        for(x=10;x<=13;x++){
            $("#chkImmChild_C"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function enDisImmuneAdult(m_immune_code) {
    var checkbox = $("#chkImmAdult_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=2;x++){
            $("#chkImmAdult_Y0"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function enDisImmunePreg(m_immune_code) {
    var checkbox = $("#chkImmPregnant_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=2;x++){
            $("#chkImmPregnant_P0"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function enDisImmuneElder(m_immune_code) {
    var checkbox = $("#chkImmElderly_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=2;x++){
            $("#chkImmElderly_E0"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function disDigitalRectal(rectal_code) {
    var checkbox = $("#rectal_" + rectal_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (rectal_code == "0") {
        for(x=1;x<=5;x++){
            $("#rectal_"+x).attr("disabled",boolCheckedNone);
            $("#rectal_99").attr("disabled",boolCheckedNone);
        }
    }
}

function resizeTextArea() {
    var textarea = $("#txaMedHistOpHist").val();

    if(textarea != "") {
        var rows = textarea.split("\n");
        $("#txaMedHistOpHist").prop("rows",rows.length+1);
    }
    else {
        $("#txaMedHistOpHist").prop("rows","1");
    }
};

function resizeTextAreaCf4() {
    var textarea = $("#txtWardDocAction").val();

    if(textarea != "") {
        var rows = textarea.split("\n");
        $("#txtWardDocAction").prop("rows",rows.length+1);
    }
    else {
        $("#txtWardDocAction").prop("rows","1");
    }
};

function loadMunicipalities(prov_code) {
    $("#optPerHistPobMun").load("loadMunicipality.php?pProvCode=" + prov_code);
}

/*START SAVE HSA TRANSACTION*/
function acceptNumOnly(evt) {
    evt = (evt) ? evt : window.event
    var charCode = (evt.which) ? evt.which : evt.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false
    }
}

/*Validate Final Health Screening & Assessment Fields*/
function saveFinalHsaTransaction() {
    /*Client Profile*/
    var txtProfileOTP = $("#txtPerHistOTP").val();
    var cntProfileOTP = $("#txtPerHistOTP").val().length;
    var txtProfileDate = $("#txtPerHistProfDate").val();

    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");
   
    /*Start Get date today*/
    var dateToday = new Date();
    
    var compareProfDate = compareDates(dateToday,txtProfileDate);
    /*End Get date today*/

    /*Past Medical History*/
    var chkAllergy = $("#chkMedHistDiseases_001").is(":checked");
    var chkCancer = $("#chkMedHistDiseases_003").is(":checked");
    var chkHepatitis = $("#chkMedHistDiseases_009").is(":checked");
    var chkHypertension = $("#chkMedHistDiseases_011").is(":checked");
    var chkPTB = $("#chkMedHistDiseases_015").is(":checked");
    var chkExPTB = $("#chkMedHistDiseases_016").is(":checked");
    var chkOthers = $("#chkMedHistDiseases_998").is(":checked");

    var txtAllergy = $("#txtMedHistAllergy").val();
    var txtCancer = $("#txtMedHistCancer").val();
    var txtHepatitis = $("#txtMedHistHepatitis").val();
    var txtDiastolic = $("#txtMedHistBPDiastolic").val();
    var txtSystolic = $("#txtMedHistBPSystolic").val();
    var txtPTB = $("#txtMedHistPTB").val();
    var txtExPTB = $("#txtMedHistExPTB").val();
    var txaOthers = $("#txaMedHistOthers").val();

    /*Family & Personal History*/
    var chkAllergyFam = $("#chkFamHistDiseases_001").is(":checked");
    var chkCancerFam = $("#chkFamHistDiseases_003").is(":checked");
    var chkHepatitisFam = $("#chkFamHistDiseases_009").is(":checked");
    var chkHypertensionFam = $("#chkFamHistDiseases_011").is(":checked");
    var chkPTBfam = $("#chkFamHistDiseases_015").is(":checked");
    var chkExPTBfam = $("#chkFamHistDiseases_016").is(":checked");
    var chkOthersFam = $("#chkFamHistDiseases_998").is(":checked");
    var chkDiabetesFam = $("#chkFamHistDiseases_006").is(":checked"); //v1.2

    var txtAllergyFam = $("#txtFamHistAllergy").val();
    var txtCancerFam = $("#txtFamHistCancer").val();
    var txtHepatitisFam = $("#txtFamHistHepatitis").val();
    var txtDiastolicFam = $("#txtFamHistBPDiastolic").val();
    var txtSystolicFam = $("#txtFamHistBPSystolic").val();
    var txtPTBfam = $("#txtFamHistPTB").val();
    var txtExPTBfam = $("#txtFamHistExPTB").val();
    var txaOthersFam = $("#txaFamHistOthers").val();

    /*Personal/Social History*/
    var chkFamHistSmokeY = $("#radFamHistSmokeY").is(":checked");
    var chkFamHistSmokeN = $("#radFamHistSmokeN").is(":checked");
    var chkFamHistSmokeX = $("#radFamHistSmokeX").is(":checked");
    var chkFamHistAlcoholY = $("#radFamHistAlcoholY").is(":checked");
    var chkFamHistAlcoholN = $("#radFamHistAlcoholN").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistDrugsY = $("#radFamHistDrugsY").is(":checked");
    var chkFamHistDrugsN = $("#radFamHistDrugsN").is(":checked");
    var chkFamHistSexualHistY = $("#radFamHistSexualHistY").is(":checked");
    var chkFamHistSexualHistN = $("#radFamHistSexualHistN").is(":checked");

    /*Pertinent Physical Examination Findings*/
    var txtPhExSystolic = $("#txtPhExSystolic").val();
    var txtPhExBPDiastolic = $("#txtPhExBPDiastolic").val();
    var txtPhExHeartRate = $("#txtPhExHeartRate").val();
    var txtPhExRespiratoryRate = $("#txtPhExRespiratoryRate").val();
    var txtPhExHeightCm = $("#txtPhExHeightCm").val();
    var txtPhExWeightKg = $("#txtPhExWeightKg").val();
    var txtPhExTemp = $("#txtPhExTemp").val();
    var txtPhEXBMIResult = $("#txtPhExBMI").val();
    //0-60months
    var txtPhEXZScore = $("#txtPhExZscoreCm").val();
    //0-24months
    var txtPhExLengthCm = $("#txtPhExLengthCm").val();
    // var txtPhExHeadCircCm = $("#txtPhExHeadCircCm").val();
    // var txtPhExBodyCircWaistCm = $("#txtPhExBodyCircWaistCm").val();
    // var txtPhExBodyCircHipsCm = $("#txtPhExBodyCircHipsCm").val();
    // var txtPhExBodyCircLimbsCm = $("#txtPhExBodyCircLimbsCm").val();
    var txtPhExMidUpperArmCirc = $("#txtPhExMidUpperArmCirc").val();

    /*General Survey*/
    var chkGenSurvey1 = $("#pGenSurvey_1").is(":checked");
    var chkGenSurvey2 = $("#pGenSurvey_2").is(":checked");
    var txtGenSurveyRem = $("#pGenSurveyRem").val();

    /*Menstrual History*/
    var txtMenarche = $("#txtOBHistMenarche").val();
    var txtLastMens = $("#txtOBHistLastMens").val();
    var dateLastMens = new Date(txtLastMens);
    var compareLastMensDate = compareDates(dateToday,dateLastMens);
    var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
    /*Pregnancy History*/
    var txtGravity = $("#txtOBHistGravity").val();
    var txtParity = $("#txtOBHistParity").val();

    var whatSex = $("#txtPerHistPatSex").val();
    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();

    var chkMHdone = $("#mhDone_Y").is(":checked");
    var chkPREGdone = $("#pregDone_Y").is(":checked");
  
    if(chkWalkedIn == true && (txtProfileOTP == "" || cntProfileOTP < 4)) {
        alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
        // $("#txtPerHistOTP").focus();
        return false;
    }  
    else if (txtProfileDate == "") {
        alert("Screening & Assessment Date is required");
        // $("#txtPerHistProfDate").focus();
        return false;
    }      
    else if (compareProfDate == "0") {
        alert("Screening & Assessment Date under CLIENT PROFILE menu is invalid! It should be less than or equal to current day.");
        // $("#txtPerHistProfDate").focus();
        return false;
    }    
    else if(validateChecksMedsHist() == false){
        alert("Choose at least one Past Medical History in MEDICAL & SURGICAL HISTORY menu");
        return false;
    }
    /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN MEDICAL & SURGICAL HISTORY*/
    else if(chkAllergy == true && txtAllergy == "") {
        alert("Please specify allergy under MEDICAL & SURGICAL HISTORY menu.");
        return false;
    }
    else if(chkCancer == true && txtCancer == "") {
        alert("Please specify organ with cancer under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistCancer").focus();
        return false;
    }
    else if(chkHepatitis == true && txtHepatitis == "") {
        alert("Please specify hepatitis type under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistHepatitis").focus();
        return false;
    }
    else if(chkHypertension == true && (txtSystolic == "" || txtDiastolic == "")) {
        alert("Please specify highest blood pressure under MEDICAL & SURGICAL HISTORY menu.");
        if(txtSystolic == "") {
            $("#txtMedHistBPSystolic").focus();
        }
        else {
            $("#txtMedHistBPDiastolic").focus();
        }
        return false;
    }
    else if(chkPTB == true && txtPTB == "") {
        alert("Please specify Pulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistPTB").focus();
        return false;
    }
    else if(chkExPTB == true && txtExPTB == "") {
        alert("Please specify Extrapulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistExPTB").focus();
        return false;
    }
    else if(chkOthers == true && txaOthers == "") {
        alert("Please specify others under MEDICAL & SURGICAL HISTORY menu.");
        $("#txaMedHistOthers").focus();
        return false;
    }
    /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN FAMILY & PERSONAL HISTORY*/
    else if(chkAllergyFam == true && txtAllergyFam == "") {
        alert("Please specify allergy under FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkCancerFam == true && txtCancerFam == "") {
        alert("Please specify organ with cancer under FAMILY & PERSONAL HISTORY menu.");
        $("#txtFamHistCancer").focus();
        return false;
    }
    else if(chkHepatitisFam == true && txtHepatitisFam == "") {
        alert("Please specify hepatitis type under FAMILY & PERSONAL HISTORY menu.");
        $("#txtFamHistHepatitis").focus();
        return false;
    }
    else if(validateChecksFamHist() == false){
        alert("Choose at least one Family History in FAMILY & PERSONAL HISTORY menu");
        return false;
    }
    else if(chkHypertensionFam == true && (txtSystolicFam == "" || txtDiastolicFam == "")) {
        alert("Please specify highest blood pressure under FAMILY & PERSONAL HISTORY menu.");
        if(txtSystolic == "") {
            $("#txtFamHistBPSystolic").focus();
        }
        else {
            $("#txtFamHistBPDiastolic").focus();
        }
        return false;
    }
    else if(chkPTBfam == true && txtPTBfam == "") {
        alert("Please specify Pulmonary Tuberculosis category under FAMILY & PERSONAL HISTORY menu.");
        $("#txtFamHistPTB").focus();
        return false;
    }
    else if(chkExPTBfam == true && txtExPTBfam == "") {
        alert("Please specify Extrapulmonary Tuberculosis category under FAMILY & PERSONAL HISTORY menu.");
        $("#txtFamHistExPTB").focus();
        return false;
    }
    else if(chkOthersFam == true && txaOthersFam == "") {
        alert("Please specify others.");
        $("#txaFamHistOthers").focus();
        return false;
    }
    else if(checkFamHistFBS() == true && checkFamHistRBS() == true) {       
        alert("Client has a family history of Diabetes Mellitus. Please fill out all fields in FBS or RBS (which is applicable).");
        return false;           
    }
    else if(chkFamHistSmokeY == false && chkFamHistSmokeN == false && chkFamHistSmokeX == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistAlcoholY == false && chkFamHistAlcoholN == false && chkFamHistAlcoholX == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistDrugsY == false && chkFamHistDrugsN == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistSexualHistY == false && chkFamHistSexualHistN == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkMHdone == true && whatSex == "FEMALE" && (txtMenarche == "" || txtLastMens == "" || txtPeriodDuration == "") && whatAge >= 10){
        alert("Menarche, Last Menstrual Period and Period Duration are REQUIRED if Applicable is selected in MENSTRUAL HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else if(compareLastMensDate == "0"){
        alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkPREGdone == true && whatSex == "FEMALE" && (txtGravity == "" || txtParity == "")){
        alert("Gravity and Parity are REQUIRED if Applicable is selected in PREGNANCY HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else if(txtPhExSystolic == "" || txtPhExBPDiastolic == "" || txtPhExHeartRate == "" || txtPhExRespiratoryRate == "" || txtPhExHeightCm == "" || txtPhExWeightKg == "" || txtPhExTemp == ""){
        alert("Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        return false;
    }
    else if(whatAge > 4 && txtPhEXBMIResult == ""){
        alert("BMI is required. Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        return false;
    }
    else if(checkAge0to24() == true){
        alert("Please fill up all required fields for 0-24 months old in PERTINENT PHYSICAL EXAMINATION FINDINGS.");
        return false;
    }
    else if(chkGenSurvey2 == true && txtGenSurveyRem == ""){
        alert("Please specify Altered Sensorium in General Survey under PHYSICAL EXAMINATION ON ADMISSION!");
        $("#pGenSurveyRem").focus();
        return false;
    }  
    else {
        //TO DO SAVING
        return confirm('Are all information encoded correctly? Click OK to Submit now.');
    }
} /*END SAVE FINAL HSA TRANSACTION*/

function checkFamHistFBS(){
    //v1.2
    var chkDiabetesFam = $("#chkFamHistDiseases_006").is(":checked"); //v1.2
    //fbs
    var txtFbsLabDate = $("#diagnostic_7_lab_exam_date").val();
    var txtFbsLabFee = $("#diagnostic_7_lab_fee").val();
    var txtFbsGlucoseMgdl = $("#diagnostic_7_glucose_mgdL").val();
    var txtFbsGlucoseMmol = $("#diagnostic_7_glucose_mmolL").val();

    if (chkDiabetesFam == true) {
        if (txtFbsLabDate == "" || txtFbsLabFee == "" || txtFbsGlucoseMgdl == "" || txtFbsGlucoseMmol == "") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }

}

function checkFamHistRBS(){
    //v1.2
    var chkDiabetesFam = $("#chkFamHistDiseases_006").is(":checked"); //v1.2

    //rbs
    var txtRbsLabDate = $("#diagnostic_19_lab_exam_date").val();
    var txtRbsLabFee = $("#diagnostic_19_lab_fee").val();
    var txtRbsGlucoseMgdl = $("#diagnostic_19_glucose_mgdL").val();
    var txtRbsGlucoseMmol = $("#diagnostic_19_glucose_mmolL").val();

    if (chkDiabetesFam == true) {
        if (txtRbsLabDate == "" || txtRbsLabFee == "" || txtRbsGlucoseMgdl == "" || txtRbsGlucoseMmol == "") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }

}

function checkAge0to24(){
    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();
    var whatDays = $("#valtxtPerHistPatDays").val();

    //0-60months
    var txtPhEXZScore = $("#txtPhExZscoreCm").val();
    //0-24months
    var txtPhExLengthCm = $("#txtPhExLengthCm").val();
    var txtPhExMidUpperArmCirc = $("#txtPhExMidUpperArmCirc").val();

    if ((whatAge <=1 && whatMonths <= 11) || (whatAge ==2 && whatMonths == 0 && whatDays == 0)){
        if (txtPhExLengthCm == "" || txtPhExMidUpperArmCirc == "" || txtPhEXZScore == "") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function checkAge0to60(){
    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();
    var whatDays = $("#valtxtPerHistPatDays").val();

    //0-60months
    var txtPhEXZScore = $("#txtPhExZscoreCm").val();
    //0-24months
    var txtPhExLengthCm = $("#txtPhExLengthCm").val();

    if ((whatAge <=4 && whatMonths <= 11) || (whatAge == 5 && whatMonths == 0 && whatDays == 0)){
        if (txtPhEXZScore == "") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}


function saveHSAFirstEncounterValidation() {
    /*Individual Health Profile*/
    var txtProfileOTP = $("#txtPerHistOTP").val();
    var cntProfileOTP = $("#txtPerHistOTP").val().length;
    var txtProfileDate = $("#txtPerHistProfDate").val();

    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");
   
    /*Start Get date today*/
    var dateToday = new Date();
   
    var compareProfDate = compareDates(dateToday,txtProfileDate);
    /*End Get date today*/

    /*Past Medical History*/
    var chkAllergy = $("#chkMedHistDiseases_001").is(":checked");
    var chkCancer = $("#chkMedHistDiseases_003").is(":checked");
    var chkHepatitis = $("#chkMedHistDiseases_009").is(":checked");
    var chkHypertension = $("#chkMedHistDiseases_011").is(":checked");
    var chkPTB = $("#chkMedHistDiseases_015").is(":checked");
    var chkExPTB = $("#chkMedHistDiseases_016").is(":checked");
    var chkOthers = $("#chkMedHistDiseases_998").is(":checked");

    var txtAllergy = $("#txtMedHistAllergy").val();
    var txtCancer = $("#txtMedHistCancer").val();
    var txtHepatitis = $("#txtMedHistHepatitis").val();
    var txtDiastolic = $("#txtMedHistBPDiastolic").val();
    var txtSystolic = $("#txtMedHistBPSystolic").val();
    var txtPTB = $("#txtMedHistPTB").val();
    var txtExPTB = $("#txtMedHistExPTB").val();
    var txaOthers = $("#txaMedHistOthers").val();

    /*Personal/Social History*/
    var chkFamHistSmokeY = $("#radFamHistSmokeY").is(":checked");
    var chkFamHistSmokeN = $("#radFamHistSmokeN").is(":checked");
    var chkFamHistSmokeX = $("#radFamHistSmokeX").is(":checked");
    var chkFamHistAlcoholY = $("#radFamHistAlcoholY").is(":checked");
    var chkFamHistAlcoholN = $("#radFamHistAlcoholN").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistDrugsY = $("#radFamHistDrugsY").is(":checked");
    var chkFamHistDrugsN = $("#radFamHistDrugsN").is(":checked");
    var chkFamHistSexualHistY = $("#radFamHistSexualHistY").is(":checked");
    var chkFamHistSexualHistN = $("#radFamHistSexualHistN").is(":checked");


    /*OB-Gyne History*/
    /*Menstrual History*/
    var txtMenarche = $("#txtOBHistMenarche").val();
    var txtLastMens = $("#txtOBHistLastMens").val();
    var dateLastMens = new Date(txtLastMens);
    var compareLastMensDate = compareDates(dateToday,dateLastMens);
    var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
    /*Pregnancy History*/
    var txtGravity = $("#txtOBHistGravity").val();
    var txtParity = $("#txtOBHistParity").val();

    var whatSex = $("#txtPerHistPatSex").val();
    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();

    var chkMHdone = $("#mhDone_Y").is(":checked");
    var chkPREGdone = $("#pregDone_Y").is(":checked");


    if(chkWalkedIn == true && (txtProfileOTP == "" || cntProfileOTP < 4)) {
        alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
        $("#txtPerHistOTP").focus();
        return false;
    }  
    else if (txtProfileDate == "") {
        alert("Screening & Assessment Date is required");
        $("#txtPerHistProfDate").focus();
        return false;
    }      
    else if (compareProfDate == "0") {
        alert("Screening & Assessment Date under CLIENT PROFILE menu is invalid! It should be less than or equal to current day.");
        $("#txtPerHistProfDate").focus();
        return false;
    }  

    else if (validateChecksMedsHist() == false){
        alert("Choose at least one Past Medical History in MEDICAL & SURGICAL HISTORY menu");
        return false;
    }
    /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN MEDICAL & SURGICAL HISTORY*/
    else if (chkAllergy == true && txtAllergy == "") {
        alert("Please specify allergy under MEDICAL & SURGICAL HISTORY menu.");
        return false;
    }
    else if(chkCancer == true && txtCancer == "") {
        alert("Please specify organ with cancer under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistCancer").focus();
        return false;
    }
    else if(chkHepatitis == true && txtHepatitis == "") {
        alert("Please specify hepatitis type under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistHepatitis").focus();
        return false;
    }
    else if(chkHypertension == true && (txtSystolic == "" || txtDiastolic == "")) {
        alert("Please specify highest blood pressure under MEDICAL & SURGICAL HISTORY menu.");
        if(txtSystolic == "") {
            $("#txtMedHistBPSystolic").focus();
        }
        else {
            $("#txtMedHistBPDiastolic").focus();
        }
        return false;
    }
    else if(chkPTB == true && txtPTB == "") {
        alert("Please specify Pulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistPTB").focus();
        return false;
    }
    else if(chkExPTB == true && txtExPTB == "") {
        alert("Please specify Extrapulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistExPTB").focus();
        return false;
    }
    else if(chkOthers == true && txaOthers == "") {
        alert("Please specify others under MEDICAL & SURGICAL HISTORY menu.");
        $("#txaMedHistOthers").focus();
        return false;
    }   
    else if(chkFamHistSmokeY == false && chkFamHistSmokeN == false && chkFamHistSmokeX == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistAlcoholY == false && chkFamHistAlcoholN == false && chkFamHistAlcoholX == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistDrugsY == false && chkFamHistDrugsN == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistSexualHistY == false && chkFamHistSexualHistN == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }        
    else if(chkMHdone == true && whatSex == "FEMALE" && (txtMenarche == "" || txtLastMens == "" || txtPeriodDuration == "") && whatAge >= 10){
        alert("Menarche, Last Menstrual Period and Period Duration are REQUIRED if Applicable is selected in MENSTRUAL HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else if(compareLastMensDate == "0"){
        alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkPREGdone == true && whatSex == "FEMALE" && (txtGravity == "" || txtParity == "")){
        alert("Gravity and Parity are REQUIRED if Applicable is selected in PREGNANCY HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else {
        //TO DO SAVING
        return confirm('Save Record?');
    }
} /*END SAVE HSA FIRST ENCOUNTER*/



function validateFollowupMeds() {

    var pPrescDoctor = $("#pPrescDoctor").val();
    var pSoapOTP = $("#pSoapOTP").val();
    var cntSoapOTP = $("#pSoapOTP").val().length;
    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");


    var pSoapdate = $("#pSOAPDate").val();

    var dateToday = new Date();
    var dateSoapDate = new Date(pSoapdate);
    var compareSoapDate = compareDates(dateToday,dateSoapDate);


    if(chkWalkedIn == true && (pSoapOTP == "" || cntSoapOTP < 4)) {
        alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
        $("#pSoapOTP").focus();
        return false;
    }  
   
    else if (pSoapdate == "") {
        alert("Consultation Date is required");
        $("#pSOAPDate").focus();
        return false;
    }        
    else if (compareSoapDate == "0") {
        alert("Consultation Date is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(pPrescDoctor == ""){
        alert("Please input Prescribing Physician and at least one medicine for follow-up.");
        return false;
    }
    else{
        return confirm('Do you want to submit it now?');
    }
}

function validateChecksSignsSymptomsCf4() {
    var chksChief = document.getElementsByName('pCf4Symptoms[]');
    var checkCountChief = 0;

    for (var i = 0; i < chksChief.length; i++) {
        if (chksChief[i].checked) {
            checkCountChief++;
        }
    }
    if ( checkCountChief < 1) {
        return false;
    }
    return true;
}

function validateChecksHeent() {
    var chksValue = document.getElementsByName('heent[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksChest() {
    var chksValue = document.getElementsByName('chest[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksHeart() {
    var chksValue = document.getElementsByName('heart[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksAbdomen() {
    var chksValue = document.getElementsByName('abdomen[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksGenitoUrinary() {
    var chksValue = document.getElementsByName('genitourinary[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksRectal() {
    var chksValue = document.getElementsByName('rectal[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksSkin() {
    var chksValue = document.getElementsByName('skinExtremities[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksNeuro() {
    var chksValue = document.getElementsByName('neuro[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksMedsHist() {
    var chksMedsHist = document.getElementsByName('chkMedHistDiseases[]');
    var checkCountMedsHist = 0;

    for (var i = 0; i < chksMedsHist.length; i++) {
        if (chksMedsHist[i].checked) {
            checkCountMedsHist++;
        }
    }

    if (checkCountMedsHist < 1) {
        return false;
    }
    return true;
}

function validateChecksFamHist() {
    var chksFamHist = document.getElementsByName('chkFamHistDiseases[]');
    var checkCountFamHist = 0;

    for (var i = 0; i < chksFamHist.length; i++) {
        if (chksFamHist[i].checked) {
            checkCountFamHist++;
        }
    }

    if (checkCountFamHist < 1) {
        return false;
    }
    return true;
}

function validateChecksImmune() {
    var chksImmChild = document.getElementsByName('chkImmChild[]');
    var checkCountImmChild = 0;
    var chksImmAdult = document.getElementsByName('chkImmAdult[]');
    var checkCountImmAdult = 0;
    var chksImmPreg = document.getElementsByName('chkImmPregnant[]');
    var checkCountImmPreg = 0;
    var chksImmElder = document.getElementsByName('chkImmElderly[]');
    var checkCountImmElder = 0;

    for (var i = 0; i < chksImmChild.length; i++) {
        if (chksImmChild[i].checked) {
            checkCountImmChild++;
        }
    }
    for (var i = 0; i < chksImmAdult.length; i++) {
        if (chksImmAdult[i].checked) {
            checkCountImmAdult++;
        }
    }
    for (var i = 0; i < chksImmElder.length; i++) {
        if (chksImmElder[i].checked) {
            checkCountImmElder++;
        }
    }
    for (var i = 0; i < chksImmPreg.length; i++) {
        if (chksImmPreg[i].checked) {
            checkCountImmPreg++;
        }
    }
    if (checkCountImmChild < 1) {
        return false;
    }
    if (checkCountImmAdult < 1) {
        return false;
    }
    if (checkCountImmElder < 1) {
        return false;
    }
    if (checkCountImmPreg < 1) {
        return false;
    }
    return true;
}

function validateChecksPlan() {
     
    var chksMgmt = document.getElementsByName('management[]');
    var checkCountMgmt = 0;

    for (var i = 0; i < chksMgmt.length; i++) {
        if (chksMgmt[i].checked) {
            checkCountMgmt++;
        }
    }

    if (checkCountMgmt < 1 && document.getElementById('management_NA').checked == false) {
        return false;
    } 
        
    return true;
    
}

function enDisFamHistSmoking(selected_val) {
    if(selected_val == "Y" || selected_val == "X") {
        $("#txtFamHistCigPk").attr("disabled",false);
        $("#txtFamHistCigPk").val("");
    }
    else {
        $("#txtFamHistCigPk").attr("disabled",true);
        $("#txtFamHistCigPk").val("");
    }
}

function enDisFamHistAlcohol(selected_val) {
    if(selected_val == "Y" || selected_val == "X") {
        $("#txtFamHistBottles").attr("disabled",false);
        $("#txtFamHistBottles").val("");
    }
    else {
        $("#txtFamHistBottles").attr("disabled",true);
        $("#txtFamHistBottles").val("");
    }
}
/*Disabled fields in Menstrual History*/
function disMenstrualHist(){
    $("#txtOBHistMenarche").attr("disabled",true);
    $("#txtOBHistLastMens").attr("disabled",true);
    $("#txtOBHistPeriodDuration").attr("disabled",true);
    $("#txtOBHistPadsPerDay").attr("disabled",true);
    $("#txtOBHistOnsetSexInt").attr("disabled",true);
    $("#txtOBHistBirthControl").attr("disabled",true);
    $("#txtOBHistInterval").attr("disabled",true);
    $("#radOBHistMenopauseN").attr("disabled",true);
    $("#txtOBHistMenopauseAge").attr("disabled",true);
}
/*Enabled fields in Menstrual History*/
function enMenstrualHist(){
    $("#txtOBHistMenarche").attr("disabled",false);
    $("#txtOBHistLastMens").attr("disabled",false);
    $("#txtOBHistPeriodDuration").attr("disabled",false);
    $("#txtOBHistPadsPerDay").attr("disabled",false);
    $("#txtOBHistOnsetSexInt").attr("disabled",false);
    $("#txtOBHistBirthControl").attr("disabled",false);
    $("#txtOBHistInterval").attr("disabled",false);
    $("#radOBHistMenopauseN").attr("disabled",false);

    var chkMenoY = $("#radOBHistMenopauseY").is(":checked");
    if(chkMenoY == true){
        $("#txtOBHistMenopauseAge").attr("disabled",false);
    }else{
        $("#txtOBHistMenopauseAge").attr("disabled",true);
    }
}

/*Disabled fields in Pregnancy History*/
function disPregHist(){
    $("#txtOBHistGravity").attr("disabled",true);
    $("#txtOBHistParity").attr("disabled",true);
    $("#optOBHistDelivery").attr("disabled",true);
    $("#txtOBHistFullTerm").attr("disabled",true);
    $("#txtOBHistPremature").attr("disabled",true);
    $("#txtOBHistAbortion").attr("disabled",true);
    $("#txtOBHistLivingChildren").attr("disabled",true);
}

/*Enabled fields in Pregnancy History*/
function enPregHist(){
    $("#txtOBHistGravity").attr("disabled",false);
    $("#txtOBHistParity").attr("disabled",false);
    $("#optOBHistDelivery").attr("disabled",false);
    $("#txtOBHistFullTerm").attr("disabled",false);
    $("#txtOBHistPremature").attr("disabled",false);
    $("#txtOBHistAbortion").attr("disabled",false);
    $("#txtOBHistLivingChildren").attr("disabled",false);
}

/*Disabled fields in Paps Smear Labs*/
function disLabsPapsSmear(){
    $("#diagnostic_13_lab_exam_date").attr("disabled",true);
    $("#diagnostic_13_lab_fee").attr("disabled",true);
    $("#diagnostic_13_papsSmearFindings").attr("disabled",true);
    $("#diagnostic_13_papsSmearImpression").attr("disabled",true);
    $("#diagnostic_13_copay").attr("disabled",true);
}

/*Enabled fields in Paps Smear Labs*/
function enLabsPapsSmear(){
    $("#diagnostic_13_lab_exam_date").attr("disabled",false);
    $("#diagnostic_13_lab_fee").attr("disabled",false);
    $("#diagnostic_13_papsSmearFindings").attr("disabled",false);
    $("#diagnostic_13_papsSmearImpression").attr("disabled",false);
    $("#diagnostic_13_copay").attr("readonly",true);
    $("#diagnostic_13_copay").attr("disabled",false);
}

/*Disabled fields in OGTT Labs*/
function disLabsOgtt(){
    $("#diagnostic_14_lab_exam_date").attr("disabled",true);
    $("#diagnostic_14_lab_fee").attr("disabled",true);
    $("#diagnostic_14_fasting_mg").attr("disabled",true);
    $("#diagnostic_14_fasting_mmol").attr("disabled",true);
    $("#diagnostic_14_oneHr_mg").attr("disabled",true);
    $("#diagnostic_14_oneHr_mmol").attr("disabled",true);
    $("#diagnostic_14_twoHr_mg").attr("disabled",true);
    $("#diagnostic_14_twoHr_mmol").attr("disabled",true);
    $("#diagnostic_14_copay").attr("disabled",true);
}
function enLabsOgtt(){
    $("#diagnostic_14_lab_exam_date").attr("disabled",false);
    $("#diagnostic_14_lab_fee").attr("disabled",false);
    $("#diagnostic_14_fasting_mg").attr("disabled",false);
    $("#diagnostic_14_fasting_mmol").attr("disabled",false);
    $("#diagnostic_14_oneHr_mg").attr("disabled",false);
    $("#diagnostic_14_oneHr_mmol").attr("disabled",false);
    $("#diagnostic_14_twoHr_mg").attr("disabled",false);
    $("#diagnostic_14_twoHr_mmol").attr("disabled",false);
    $("#diagnostic_14_copay").attr("disabled",false);
    $("#diagnostic_14_copay").attr("readonly",true);
}


/*Disabled fields in Sputum Microscopy (1) Labs*/
function disLabsSputum_1(){
    $("#diagnostic_5_lab_exam_date").attr("disabled",true);
    $("#diagnostic_5_lab_fee").attr("disabled",true);
    $("#diagnostic_5_copay").attr("disabled",true);
    $("#diagnostic_5_no").attr("disabled",true);
    $("#diagnostic_5_yes").attr("disabled",true);
    $("#diagnostic_5_sputum_remarks").attr("disabled",true);
    $("#diagnostic_5_plusses").attr("disabled",true);
}

/*Enabled fields in Sputum Microscopy (1) Labs*/
function enLabsSputum_1(){
    $("#diagnostic_5_lab_exam_date").attr("disabled",false);
    $("#diagnostic_5_lab_fee").attr("disabled",false);
    $("#diagnostic_5_no").attr("disabled",false);
    $("#diagnostic_5_yes").attr("disabled",false);
    $("#diagnostic_5_sputum_remarks").attr("disabled",false);
    $("#diagnostic_5_plusses").attr("disabled",false);
    $("#diagnostic_5_copay").attr("readonly",true);
    $("#diagnostic_5_copay").attr("disabled",false);
}

function enDisOBHistMenopause(selected_val) {
    if(selected_val == "Y" || selected_val == "X") {
        $("#txtOBHistMenopauseAge").attr("disabled",false);
        $("#txtOBHistMenopauseAge").val("");
    }
    else {
        $("#txtOBHistMenopauseAge").attr("disabled",true);
        $("#txtOBHistMenopauseAge").val("");
    }
}

function chkNA(){
    if($('[name="Q17"]').checked == '3'){
        alert('not applicable');
        var $success=false;
    }
    else{
        var $success=true;
    }
    return $success;
}
/*END HSA MODULE*/

function enableDependentTypeMemInfo() {
    value = $( "#pPatientType" ).val();
    if(value == 'DD'){
        $("#pDependentType").attr("disabled",false);
        $("#pPatientPIN").attr("readonly",false);
        $("#pWithDisability").attr("disabled",false);
    } else{
        $("#pDependentType").attr("disabled",true);
        $("#pPatientPIN").attr("readonly",false);
        $("#pWithDisability").attr("disabled",true);
    }

    if(value == 'NM') {
        $("#pPatientPIN").attr("readonly",true);
    } else{
        $("#pPatientPIN").attr("readonly",false);
    }

    if(value == 'MM'){
        $("#pMemberPIN").attr("readonly",true);
        $("#pMemberLastName").attr("readonly",true);
        $("#pMemberFirstName").attr("readonly",true);
        $("#pMemberMiddleName").attr("readonly",true);
        $("#pMemberSuffix").attr("readonly",true);
        $("#pMemberDateOfBirth").attr("disabled",true);
        $("#pMemberSex").attr("disabled",true);
    }
    else if(value == 'NM'){
        $("#pMemberPIN").attr("readonly",true);
        $("#pMemberLastName").attr("readonly",true);
        $("#pMemberFirstName").attr("readonly",true);
        $("#pMemberMiddleName").attr("readonly",true);
        $("#pMemberSuffix").attr("readonly",true);
        $("#pMemberDateOfBirth").attr("disabled",true);
        $("#pMemberSex").attr("disabled",true);
    }
    else{
        $("#pMemberPIN").attr("readonly",false);
        $("#pMemberLastName").attr("readonly",false);
        $("#pMemberFirstName").attr("readonly",false);
        $("#pMemberMiddleName").attr("readonly",false);
        $("#pMemberSuffix").attr("readonly",false);
        $("#pMemberDateOfBirth").attr("disabled",false);
        $("#pMemberSex").attr("disabled",false);
    }
}

function selectCivilStatus(value){
    if(value == 'S'){
        $("#pPatientCivilStatusX option:selected").val('M');
        $("#pPatientCivilStatusX option:selected").text('MARRIED');
        $("#pPatientCivilStatusX option:disabled").removeAttr('disabled');
        $("#pPatientCivilStatusX option[value='S']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='W']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='X']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='A']").attr('disabled', true);
    }
    else if(value == 'C'){
        $("#pPatientCivilStatusX option:selected").val('S');
        $("#pPatientCivilStatusX option:selected").text('SINGLE');
        $("#pPatientCivilStatusX option:disabled").removeAttr('disabled');
        $("#pPatientCivilStatusX option[value='M']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='W']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='X']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='A']").attr('disabled', true);
    }
    else{
        $("#pPatientCivilStatusX option:selected").val('');
        $("#pPatientCivilStatusX option:selected").text('');
        $("#pPatientCivilStatusX option[value='S']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='M']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='W']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='X']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='A']").attr('disabled', false);
    }
}


function saveTransRegistration() {
    var txtPxSex = $("#pPatientSexX option:selected").val();
    var txtMmSex = $("#pMemberSex option:selected").val();
    var txtPxDoB = $("#pPatientDateOfBirth").val();
    var txtEnlistDate = $("#pEnlistmentDate").val();
    var txtMmDoB = $("#pMemberDateOfBirth").val();

    var dateToday = new Date();
    var datePxDoB = new Date(txtPxDoB);
    var dateRegDate = new Date(txtEnlistDate);
    var dateMemDoB = new Date(txtMmDoB);

    var regDateYear = dateRegDate.getYear();

    var comparePxDoB = compareDates(dateToday,datePxDoB);
    var compareRegDate = compareDates(dateToday,dateRegDate);
    var compareMemDoB = compareDates(dateToday,dateMemDoB);

    if (txtPxSex == "") {
        alert("Patient's Sex is required!");
        $("#pPatientSexX").focus();
        return false;
    }
    else if(regDateYear <= 116){
        alert("Date of Encounter is invalid! Year should be greater than or equal to year 2017");
        return false;
    }
    else if(compareRegDate == "0"){
        alert("Date of Encounter is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(comparePxDoB == "0"){
        alert("Patient's Date of Birth is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(compareMemDoB == "0"){
        alert("Member's Date of Birth is invalid! It should be less than or equal to current day.");
        return false;
    }
    else {
        //TO DO SAVING
        return confirm('Do you want to save the record?');
    }

}

 function compareDates(dateToday,date2){
    if (dateToday>date2) return ("1");
    else if (dateToday<date2) return ("0");
    else return ("-");
}

function validateHciForm() {
    var pUserPass = $("#pUserPassword").val();
    var pUserConfirmPass = $("#pUserConfirmPassword").val();
    var pAccreNo = $("#pAccreNo").val();
    var pUserId = $("#pUserId").val();
    var pCKey = $("#pHciKey").val();
    var pUserDoB = $("#pUserDoB").val();

    var dateToday = new Date();
    var dateUserDoB = new Date(pUserDoB);
    var compareUserDoB = compareDates(dateToday,dateUserDoB);


    if (pUserPass != pUserConfirmPass) {
        alert("Password do not match!");
        return false;
    }
    else if(pAccreNo == ""){
        alert("Accreditation Number required!");
        return false;
    }
    else if(pUserId.length() > 5){
        alert("User ID must be minimum of 6 characters!");
        return false;
    }
    else if(pUserPass.length() > 5){
        alert("User Password must be minimum of 6 characters!");
        return false;
    }
    else if(pAccreNo.length() > 8){
        alert("Accreditation Number must be minimum of 9 characters!");
        return false;
    }
    else if(pCKey.length() > 9){
        alert("Accreditation Number must be minimum of 10 characters!");
        return false;
    }
    else if(compareUserDoB == "0"){
        alert("Date of Birth is invalid! It should be less than or equal to current day.");
        return false;
    }
    else{
        return confirm('Are all information encoded correctly? Click OK to Submit now.');
    }
}


/*CF4*/
function showTab(id){
    if(id == 'tab9') {
        var dateToday = new Date();

        var txtCF4ClaimId = $("#txtPerClaimId").val();
        /*Individual Health Profile*/
        var txtPxPin = $("#txtPerPatPIN").val();
        var txtMemLname = $("#txtPerPatLname").val();
        var txtMemFname = $("#txtPerPatFname").val();
        var txtPxSex = $("#txtPerPatSex option:selected").val();
        var txtPxCivilStatus = $("#txtPerPatStatus option:selected").val();
        var txtPxType = $("#txtPerPatType option:selected").val();
        var txtPxDoB = $("#txtPerPatBirthday").val();
        var datePxDoB= new Date(txtPxDoB);
        var comparePxDoB = compareDates(dateToday,datePxDoB);

        if (txtCF4ClaimId == "" || txtPxPin == "" || txtMemLname == ""  || txtMemFname == "" || txtPxSex == "" || txtPxType == "") {
            alert("Please fill up all required fields in PATIENT PROFILE");
            return false;
        }
        else if (txtPxPin.length < 12) {
            alert("Input 12 numbers of Patient's PIN");
            return false;
        }
        else if(comparePxDoB == "0"){
            alert("Date of Birth in PATIENT PROFILE is invalid! It should be less than or equal to current day.");
            return false;
        }
        else {
            $("#list1").removeClass("active");
            $("#tab1").removeClass("active");
            $("#tab9").addClass("active in");
            $("#list9").addClass("active");
        }
    }
    if(id == 'tab2') {
        var txtComplaint = $("#pChiefComplaint").val();
        if(txtComplaint == "") {
            alert("Please specify Chief Complaint");
            $("#pChiefComplaint").focus();
            return false;
        }
        else {
            $("#list9").removeClass("active");
            $("#tab9").removeClass("active");
            $("#tab2").addClass("active in");
            $("#list2").addClass("active");
        }
    }
    if(id == 'tab3') {
        var txtHistIllness = $("#pHistPresentIllness").val();
        if(txtHistIllness == "") {
            alert("Please specify History of Present Illness");
            $("#pHistPresentIllness").focus();
            return false;
        }
        else {
            $("#list2").removeClass("active");
            $("#tab2").removeClass("active");
            $("#tab3").addClass("active in");
            $("#list3").addClass("active");
        }
    }
    if(id == 'tab4') {
        var txtPastMedsHist = $("#txaMedHistOthers").val();
        if(txtPastMedsHist == "") {
            alert("Please specify Pertinent Past Medical History");
            $("#txaMedHistOthers").focus();
            return false;
        }
        else {
            $("#list3").removeClass("active");
            $("#tab3").removeClass("active");
            $("#tab4").addClass("active in");
            $("#list4").addClass("active");
        }
    }
    if(id == 'tab5') {
        var obgyne = $("#mhDone_2").is(":checked");
        var txtLastMens = $("#txtOBHistLastMens").val();
        var dateLastMens = new Date(txtLastMens);
        var compareLastMensDate = compareDates(dateToday,dateLastMens);

        var txtGravity = $("#txtOBHistGravity").val();
        var txtParity = $("#txtOBHistParity").val();
        var txtFullTerm = $("#txtOBHistFullTerm").val();
        var txtPremature = $("#txtOBHistPremature").val();
        var txtAbortion = $("#txtOBHistAbortion").val();
        var txtLivingChildren = $("#txtOBHistLivingChildren").val();

        if(txtLastMens != "" && compareLastMensDate == "0"){
            alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(obgyne == true && (txtLastMens == "" || txtGravity == "" || txtParity == "" || txtFullTerm == "" || txtPremature == "" || txtAbortion == "" || txtLivingChildren == "")){
            alert("Please fill up all the fields in OB-Gyne History!");
            return false;
        }
        else {
            $("#list4").removeClass("active");
            $("#tab4").removeClass("active");
            $("#tab5").addClass("active in");
            $("#list5").addClass("active");
        }
    }
    if(id == 'tab6') {
        if(validateChecksSignsSymptomsCf4() == false){
            alert("Choose at least one PERTINENT SIGNS & SYMPTOMS ON ADMISSION!");
            return false;
        }
        else {
            $("#list5").removeClass("active");
            $("#tab5").removeClass("active");
            $("#tab6").addClass("active in");
            $("#list6").addClass("active");
        }
    }
    if(id == 'tab7') {
        /*Pertinent Physical Examination Findings*/
        var txtPhExSystolic = $("#txtPhExSystolic").val();
        var txtPhExBPDiastolic = $("#txtPhExBPDiastolic").val();
        var txtPhExHeartRate = $("#txtPhExHeartRate").val();
        var txtPhExRespiratoryRate = $("#txtPhExRespiratoryRate").val();

        /*General Survey*/
        var chkGenSurvey1 = $("#pGenSurvey_1").is(":checked");
        var chkGenSurvey2 = $("#pGenSurvey_2").is(":checked");
        var txtGenSurveyRem = $("#pGenSurveyRem").val();

        if(txtPhExSystolic == "" || txtPhExBPDiastolic == "" || txtPhExHeartRate == "" || txtPhExRespiratoryRate == "" || txtPhExTemp == ""){
            alert("Please fill up all required fields in PHYSICAL EXAMINATION ON ADMISSION!");
            return false;
        }
        else if(chkGenSurvey1 == false && chkGenSurvey2 == false){
            alert("Please specify General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
            $("#pGenSurvey_1").focus();
            return false;
        }
        else if(chkGenSurvey2 == true && txtGenSurveyRem == ""){
            alert("Please specify Altered Sensorium in General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
            $("#pGenSurveyRem").focus();
            return false;
        }
        else if(validateChecksHeent() == false){
            alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksChest() == false){
            alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksHeart() == false){
            alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksAbdomen() == false){
            alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksGenitoUrinary() == false){
            alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksSkin() == false){
            alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksNeuro() == false){
            alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else {
            $("#list6").removeClass("active");
            $("#tab6").removeClass("active");
            $("#tab7").addClass("active in");
            $("#list7").addClass("active");
        }
    }
    if(id == 'tab8') {
        var txtDateActionWard = document.getElementsByName('pDateActionWard[]');
        var dateActionWard = new Date(txtDateActionWard);
        var compareDateActionWard = compareDates(dateToday,dateActionWard);
        var txtActionWard = document.getElementsByName('pActionWard[]');


        if(txtActionWard.length == 0){
            alert("Please input at least one DOCTOR'S ORDER/ACTION in COURSE IN THE WARD");
            $("#txtWardDocAction").focus();
            return false;
        }
        else if(compareDateActionWard == "0"){
            alert("Date of Doctor's Order/Action in COURSE IN THE WARD is invalid! It should be less than or equal to current day.");
            return false;
        }
        else {
            $("#list7").removeClass("active");
            $("#tab7").removeClass("active");
            $("#tab8").addClass("active in");
            $("#list8").addClass("active");
        }
    }
}

function saveCF4Transaction() {
    var dateToday = new Date();

    var txtCF4ClaimId = $("#txtPerClaimId").val();
    /*Individual Health Profile*/
    var txtPxPin = $("#txtPerPatPIN").val();
    var txtMemLname = $("#txtPerPatLname").val();
    var txtMemFname = $("#txtPerPatFname").val();
    var txtPxSex = $("#txtPerPatSex option:selected").val();

    var txtPxType = $("#txtPerPatType option:selected").val();
    var txtPxDoB = $("#txtPerPatBirthday").val();
    var datePxDoB= new Date(txtPxDoB);
    var comparePxDoB = compareDates(dateToday,datePxDoB);

    /*Chief Complaint*/
    var txtComplaint = $("#pChiefComplaint").val();

    /*History of Present Illness*/
    var txtHistIllness = $("#pHistPresentIllness").val();

    /*Past Medical History*/
    var txtPastMedsHist = $("#txaMedHistOthers").val();

    /*Menstrual History*/
    var obgyne = $("#mhDone_2").is(":checked");
    var txtMenarche = $("#txtOBHistMenarche").val();
    var txtLastMens = $("#txtOBHistLastMens").val();
    var dateLastMens = new Date(txtLastMens);
    var compareLastMensDate = compareDates(dateToday,dateLastMens);
    var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
    /*Pregnancy History*/
    var txtGravity = $("#txtOBHistGravity").val();
    var txtParity = $("#txtOBHistParity").val();

    var whatSex = $("#txtPerPatSex").val();

    /*Pertinent Physical Examination Findings*/
    var txtPhExSystolic = $("#txtPhExSystolic").val();
    var txtPhExBPDiastolic = $("#txtPhExBPDiastolic").val();
    var txtPhExHeartRate = $("#txtPhExHeartRate").val();
    var txtPhExRespiratoryRate = $("#txtPhExRespiratoryRate").val();

    /*General Survey*/
    var chkGenSurvey1 = $("#pGenSurvey_1").is(":checked");
    var chkGenSurvey2 = $("#pGenSurvey_2").is(":checked");
    var txtGenSurveyRem = $("#pGenSurveyRem").val();

    /*Course in the Ward*/
    var txtDateActionWard = document.getElementsByName('pDateActionWard[]');
    var dateActionWard = new Date(txtDateActionWard);
    var compareDateActionWard = compareDates(dateToday,dateActionWard);
    var txtActionWard = document.getElementsByName('pActionWard[]');

    if (txtCF4ClaimId == "" || txtPxPin == "" || txtMemLname == ""  || txtMemFname == "" || txtPxSex == "" || txtPxType == "") {
        alert("Please fill up all required fields in PATIENT PROFILE");
        return false;
    }
    else if (txtPxPin.length < 12) {
        alert("Input 12 numbers of Patient's PIN");
        return false;
    }
    else if(comparePxDoB == "0"){
        alert("Date of Birth in PATIENT PROFILE is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(txtComplaint == "") {
        alert("Please specify Chief Complaint");
        $("#pChiefComplaint").focus();
        return false;
    }
    else if(txtHistIllness == "") {
        alert("Please specify History of Present Illness");
        $("#pHistPresentIllness").focus();
        return false;
    }
    else if(txtPastMedsHist == "") {
        alert("Please specify Pertinent Past Medical History");
        $("#txaMedHistOthers").focus();
        return false;
    }
    else if(txtLastMens != "" && compareLastMensDate == "0"){
        alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(obgyne == true && txtLastMens == ""){
        alert("Please fill up the Last Menstrual Period in MENSTRUAL HISTORY of OB-GYNE HISTORY menu!");
        return false;
    }
    else if(obgyne == true && (txtGravity == "" || txtParity == "")){
        alert("Please fill up the Gravity and Parity in PREGNANCY HISTORY of OB-GYNE HISTORY!");
        return false;
    }
    else if(validateChecksSignsSymptomsCf4() == false){
        alert("Choose at least one PERTINENT SIGNS & SYMPTOMS ON ADMISSION!");
        return false;
    }
    else if(txtPhExSystolic == "" || txtPhExBPDiastolic == "" || txtPhExHeartRate == "" || txtPhExRespiratoryRate == "" || txtPhExTemp == ""){
        alert("Please fill up all required fields in PHYSICAL EXAMINATION ON ADMISSION!");
        return false;
    }
    else if(chkGenSurvey1 == false && chkGenSurvey2 == false){
        alert("Please specify General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
        $("#pGenSurvey_1").focus();
        return false;
    }
    else if(chkGenSurvey2 == true && txtGenSurveyRem == ""){
        alert("Please specify Altered Sensorium in General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
        $("#pGenSurveyRem").focus();
        return false;
    }
    else if(validateChecksHeent() == false){
        alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksChest() == false){
        alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksHeart() == false){
        alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksAbdomen() == false){
        alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksGenitoUrinary() == false){
        alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksSkin() == false){
        alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksNeuro() == false){
        alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(txtActionWard.length == 0){
        alert("Please input at least one DOCTOR'S ORDER/ACTION in COURSE IN THE WARD");
        $("#txtWardDocAction").focus();
        return false;
    }
    else if(compareDateActionWard == "0"){
        alert("Date of Doctor's Order/Action in COURSE IN THE WARD is invalid! It should be less than or equal to current day.");
        return false;
    }
    else {
        //TO DO SAVING
        return confirm('Do you want to submit it now?');
    }
} /*END SAVE CF4 TRANSACTION*/


// GET ZSCORE
function getZScoreF023(form) {

       if (form.txtPhExWeightKg.value==null||form.txtPhExWeightKg.value.length==0 || form.txtPhExHeightCm.value==null||form.txtPhExHeightCm.value.length==0){
            alert("\nPlease input value on Height (cm) and Weight (kg)");
            return false;
       }

       else if (parseFloat(form.txtPhExHeightCm.value) <= 45||
                parseFloat(form.txtPhExHeightCm.value) >=500||
                parseFloat(form.txtPhExWeightKg.value) <= 0||
                parseFloat(form.txtPhExWeightKg.value) >=500){
                alert("\nPlease enter values again. \nHeight in cm and \nWeight in kilos ");
                ClearForm(form);
                return false;
       }
       return true;

}

function loadZScore() {
    //    
    var pHeight = $("#txtPhExHeightCm").val();
    var pLength = $("#txtPhExLengthCm").val();
    var pWeigth = $("#txtPhExWeightKg").val();

    //age    
    var pYearAge = $("#valtxtPerHistPatAge").val();
    var pMonthAge = $("#valtxtPerHistPatMonths").val();

    //sex
    var pSex = $("#txtPerHistPatSexValue").val();

    // var length = Math.round(pLength);
    // var length = Math.floor(pLength / 0.5) * 0.5;
    // var vlength = Math.round(length);
    

    

    if((pYearAge >=0 && pYearAge <=1) && (pMonthAge >=0 && pMonthAge <= 11)){

        if (pLength != "" && pWeigth != ""){

            var decPart1= (pLength + "").split(".")[1];
            var decPart0 = (pLength + "").split(".")[0];


            var re = /\d\.(\d)/; 
            var m;


            if ((m = re.exec(pLength)) !== null ) {
                decPart1 = m[1];
            }
        }
        else {
            alert("\nPlease enter values again. \nLength in cm and \nWeight in kilos ");
        }
    }

    if((pYearAge >= 2 && pYearAge <= 4) && (pMonthAge >=0 && pMonthAge <= 11)){        

        if (pHeight != "" && pWeigth != ""){
            var decPart1= (pHeight + "").split(".")[1];
            var decPart0 = (pHeight + "").split(".")[0];


            var re = /\d\.(\d)/; 
            var m;
        
            if ((m = re.exec(pHeight)) !== null ) {
                decPart1 = m[1];
            }
        }
        else {
            alert("\nPlease enter values again. \nHeight in cm and \nWeight in kilos ");
        }
    }
   
        
    if(decPart1 == 3 || decPart1 == 4 || decPart1 == 5){
        var vlengthHeight = decPart0 + ".5";
    } else if(decPart1 == 0 || decPart1 == 1 || decPart1 == 2){
        //var vlengthHeight = decPart0 + ".0";
        var vlengthHeight = decPart0;
    } else if (decPart1 == null){
        //var vlengthHeight = decPart0 + ".0";
        var vlengthHeight = decPart0;
    }

    $("#txtPhExZscoreCm").load("loadZScore.php?length="+ vlengthHeight +"&weight=" + pWeigth +"&height=" + vlengthHeight +"&sex=" + pSex +"&year=" + pYearAge +"&month=" + pMonthAge);
}

function showHideBtn() {
  var client = document.getElementById("fsClientInfo");
  var btnValueFf = document.getElementById("hideShowBtnATCInfo");
  if (client.style.display === "none") {
    client.style.display = "block";
    btnValueFf.value = "- Hide Details";
  } else {
    client.style.display = "none";
    btnValueFf.value = "+ Show Details";
  }
}// JavaScript Document

/*20191213 Consulatation Next button validation*/
function showTabConsultation(id){
    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();

    var pSoapOTP = $("#pSoapOTP").val();
    var cntSoapOTP = $("#pSoapOTP").val().length;
    var pSoapCoPayment = $("#pCoPayment").val();
    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");

    var pSoapdate = $("#pSOAPDate").val();
    var pChiefComplaint = $("#pChiefComplaint").text();
    // var pIcd = $("#pICD").val();
    var chksDiagnosis = $('input[name="diagnosis[]"]').val();

    /*Start Get date today*/
    var dateToday = new Date();
    var dateSoapDate = new Date(pSoapdate);
    var compareSoapDate = compareDates(dateToday,dateSoapDate);
    /*End Get date today*/

    /*Objective/Physical Examination*/
    var txtPhExSystolic = $("#pe_bp_u").val();
    var txtPhExBPDiastolic = $("#pe_bp_l").val();
    var txtPhExHeartRate = $("#pe_hr").val();
    var txtPhExRespiratoryRate = $("#pe_rr").val();
    var txtPhExHeightCm = $("#txtPhExHeightCm").val();
    var txtPhExWeightKg = $("#txtPhExWeightKg").val();
    var txtPhExTemp = $("#pe_temp").val();
    
    var txtPhEXBMIResult = $("#txtPhExBMI").val();

    /*Labs*/
    var chkCBCdone = $("#diagnostic_1_done").is(":checked"); //done
    var chkUrinalysisDone = $("#diagnostic_2_done").is(":checked");//done
    var chkFecalysisDone = $("#diagnostic_3_done").is(":checked");//done
    var chkXrayDone = $("#diagnostic_4_done").is(":checked");//done
    var chkSputumDone = $("#diagnostic_5_done").is(":checked");//done
    var chkLipidDone = $("#diagnostic_6_done").is(":checked");//done
    var chkECGDone = $("#diagnostic_9_done").is(":checked");//done
    var chkPapsSmearDone = $("#diagnostic_13_done").is(":checked");//done
    var chkOGTTDone = $("#diagnostic_14_done").is(":checked");//done
    var chkFbsDone = $("#diagnostic_7_done").is(":checked");//done
    var chkRbsDone = $("#diagnostic_19_done").is(":checked");//done

    /*CBC*/
    var txtCbcLabDate = $("#diagnostic_1_lab_exam_date").val();
    var dateCbcDate = new Date(txtCbcLabDate);
    var compareCbcDate = compareDates(dateToday,dateCbcDate);
    var txtCbcLabFee = $("#diagnostic_1_lab_fee").val();
    var txtCbcHema = $("#diagnostic_1_hematocrit").val();
    var txtCbchemo = $("#diagnostic_1_hemoglobin_gdL").val();
    var txtCbcMhc = $("#diagnostic_1_mhc_pgcell").val();
    var txtCbcMchc= $("#diagnostic_1_mchc_gHbdL").val();
    var txtCbcMcv = $("#diagnostic_1_mcv_um").val();
    var txtCbcWbc = $("#diagnostic_1_wbc_cellsmmuL").val();
    var txtCbcMyelocyte = $("#diagnostic_1_myelocyte").val();
    var txtCbcNeutroBand = $("#diagnostic_1_neutrophils_bands").val();
    var txtCbcNeutroSeg = $("#diagnostic_1_neutrophils_segmenters").val();
    var txtCbcLymph = $("#diagnostic_1_lymphocytes").val();
    var txtCbcMono = $("#diagnostic_1_monocytes").val();
    var txtCbcEosi = $("#diagnostic_1_eosinophils").val();
    var txtCbcBaso = $("#diagnostic_1_basophils").val();
    var txtCbcPlatelet = $("#diagnostic_1_platelet").val();

    /*PAPS SMEAR*/
    var txtPapsLabDate = $("#diagnostic_13_lab_exam_date").val();
    var datePaps = new Date(txtPapsLabDate);
    var comparePapsDate = compareDates(dateToday,datePaps);
    var txtPapsLabFee = $("#diagnostic_13_lab_fee").val();
    var txtPapsFind = $("#diagnostic_13_papsSmearFindings").val();
    var txtPapsImpre = $("#diagnostic_13_papsSmearImpression").val();

    /*OGTT*/
    var txtOgttLabDate = $("#diagnostic_14_lab_exam_date").val();
    var dateOgtt = new Date(txtOgttLabDate);
    var compareOgttDate = compareDates(dateToday,dateOgtt);
    var txtOgttLabFee = $("#diagnostic_14_lab_fee").val();
    var txtOgttFastMg = $("#diagnostic_14_fasting_mg").val();
    var txtOgttFastMmol = $("#diagnostic_14_fasting_mmol").val();
    var txtOgttOneMg = $("#diagnostic_14_oneHr_mg").val();
    var txtOgttOneMmol = $("#diagnostic_14_oneHr_mmol").val();
    var txtOgttTwoMg = $("#diagnostic_14_twoHr_mg").val();
    var txtOgttTwoMmol = $("#diagnostic_14_twoHr_mmol").val();

    /*URINALYSIS*/
    var txtUrineLabDate = $("#diagnostic_2_lab_exam_date").val();
    var dateUrine = new Date(txtUrineLabDate);
    var compareUrineDate = compareDates(dateToday,dateUrine);
    var txtUrineLabFee = $("#diagnostic_2_lab_fee").val();
    var txtUrineSg = $("#diagnostic_2_sg").val();
    var txtUrineAppear = $("#diagnostic_2_appearance").val();
    var txtUrineColor = $("#diagnostic_2_color").val();
    var txtUrineGlucose = $("#diagnostic_2_glucose").val();
    var txtUrineProtein = $("#diagnostic_2_proteins").val();
    var txtUrineKetones = $("#diagnostic_2_ketones").val();
    var txtUrinePh = $("#diagnostic_2_pH").val();
    var txtUrinePus = $("#diagnostic_2_pus").val();
    var txtUrineAlb = $("#diagnostic_2_alb").val();
    var txtUrineRbc = $("#diagnostic_2_rbc").val();
    var txtUrineWbc = $("#diagnostic_2_wbc").val();
    var txtUrineBact = $("#diagnostic_2_bacteria").val();
    var txtUrineCryst = $("#diagnostic_2_crystals").val();
    var txtUrineBlad = $("#diagnostic_2_bladder_cells").val();
    var txtUrineSqCell= $("#diagnostic_2_squamous_cells").val();
    var txtUrineTubCell = $("#diagnostic_2_tubular_cells").val();
    var txtUrineBrCast = $("#diagnostic_2_broad_casts").val();
    var txtUrineCellCast = $("#diagnostic_2_epithelial_cell_casts").val();
    var txtUrineGranCast = $("#diagnostic_2_granular_casts").val();
    var txtUrineHyaCast = $("#diagnostic_2_hyaline_casts").val();
    var txtUrineRbcCast = $("#diagnostic_2_rbc_casts").val();
    var txtUrineWaxyCast = $("#diagnostic_2_waxy_casts").val();
    var txtUrineWcCast = $("#diagnostic_2_wc_casts").val();

    /*FECALYSIS*/
    var txtFecaLabDate = $("#diagnostic_3_lab_exam_date").val();
    var dateFeca = new Date(txtFecaLabDate);
    var compareFecaDate = compareDates(dateToday,dateFeca);
    var txtFecaLabFee = $("#diagnostic_3_lab_fee").val();
    var txtFecaPus = $("#diagnostic_3_pus").val();
    var txtFecaRbc = $("#diagnostic_3_rbc").val();
    var txtFecaWbc = $("#diagnostic_3_wbc").val();
    var txtFecaOva = $("#diagnostic_3_ova").val();
    var txtFecaPara = $("#diagnostic_3_parasite").val();
    var txtFecaOccult = $("#diagnostic_3_occult_blood").val();

    /*CHEST X-RAY*/
    var txtXrayLabDate = $("#diagnostic_4_lab_exam_date").val();
    var dateXray = new Date(txtXrayLabDate);
    var compareXrayDate = compareDates(dateToday,dateXray);
    var txtXrayLabFee = $("#diagnostic_4_lab_fee").val();
    var txtXrayFindings = $("#diagnostic_4_chest_findings option:selected").val();

    /*SPUTUM MICROSCOPY*/
    var txtSputumLabDate = $("#diagnostic_5_lab_exam_date").val();
    var dateSputum = new Date(txtSputumLabDate);
    var compareSputumDate = compareDates(dateToday,dateSputum);
    var txtSputumLabFee = $("#diagnostic_5_lab_fee").val();
    var txtSputumPlusses = $("#diagnostic_5_plusses").val();

    /*LIPID PROFILE*/
    var txtLipidLabDate = $("#diagnostic_6_lab_exam_date").val();
    var dateLipid = new Date(txtLipidLabDate);
    var compareLipidDate = compareDates(dateToday,dateLipid);
    var txtLipidLabFee = $("#diagnostic_6_lab_fee").val();
    var txtLipidTotal = $("#diagnostic_6_total").val();
    var txtLipidLdl = $("#diagnostic_6_ldl").val();
    var txtLipidHdl = $("#diagnostic_6_hdl").val();
    var txtLipidChol = $("#diagnostic_6_cholesterol").val();
    var txtLipidTrigy = $("#diagnostic_6_triglycerides").val();

    /*ECG*/
    var txtEcgLabDate = $("#diagnostic_9_lab_exam_date").val();
    var dateEcg = new Date(txtEcgLabDate);
    var compareEcgDate = compareDates(dateToday,dateEcg);
    var txtEcgLabFee = $("#diagnostic_9_lab_fee").val();
    var chkEcgNormal = $("#diagnostic_9_no").is(":checked");
    var chkEcgNotnNormal = $("#diagnostic_9_yes").is(":checked");
    var remEcgFindings = $("#diagnostic_9_ecg_remarks").val();

    /*START CONSULTATION ONLY*/
    /*FBS*/
    var txtFbsLabDate = $("#diagnostic_7_lab_exam_date").val();
    var dateFbs = new Date(txtFbsLabDate);
    var compareFbsDate = compareDates(dateToday,dateFbs);
    var txtFbsLabFee = $("#diagnostic_7_lab_fee").val();
    var txtFbsGlucoseMgdl = $("#diagnostic_7_glucose_mgdL").val();
    var txtFbsGlucoseMmol = $("#diagnostic_7_glucose_mmolL").val();

    /*RBS*/
    var txtRbsLabDate = $("#diagnostic_19_lab_exam_date").val();
    var dateRbs = new Date(txtRbsLabDate);
    var compareRbsDate = compareDates(dateToday,dateRbs);
    var txtRbsLabFee = $("#diagnostic_19_lab_fee").val();
    var txtRbsGlucoseMgdl = $("#diagnostic_19_glucose_mgdL").val();
    var txtRbsGlucoseMmol = $("#diagnostic_19_glucose_mmolL").val();
    /*END CONSULTATION ONLY*/

    if(id == 'tab2') {
        if(chkWalkedIn == true && (pSoapOTP == "" || cntSoapOTP < 4)) {
                alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
                $("#pSoapOTP").focus();
                return false;
            }  
        else if (pSoapCoPayment == "") {
            alert("Co-payment is required.");
            $("#pCoPayment").focus();
            return false;
        }
        else if (pSoapdate == "") {
            alert("Consultation Date is required");
            $("#pSOAPDate").focus();
            return false;
        }        
        else if (compareSoapDate == "0") {
            alert("Consultation Date is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(validateChecksChiefComplaint() == false){
            alert("Chief Complaint is required. Choose at least one CHIEF COMPLAINT in SUBJECTIVE/HISTORY OF ILLNESS");
            return false;
        }
        else {
            $("#list1").removeClass("active");
            $("#tab1").removeClass("active");
            $("#tab2").addClass("active in");
            $("#list2").addClass("active");
        }
    }

    if(id == 'tab3') {
        if(txtPhExSystolic == "" && txtPhExBPDiastolic == "" && txtPhExHeartRate == "" && txtPhExRespiratoryRate == "" && txtPhExHeightCm == "" && txtPhExWeightKg == "" && txtPhExTemp == ""){
            alert("Fill up all the required fields in OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(whatAge > 4 && txtPhEXBMIResult == ""){
            alert("BMI is required. Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
            return false;
        }
        else if(checkAge0to24() == true){
            alert("Please fill up all required fields for 0-24 months old in PERTINENT PHYSICAL EXAMINATION FINDINGS.");
            return false;
        }
        else if(checkAge0to60() == true){
            alert("Please fill up all required fields for 0-60 months old in PERTINENT PHYSICAL EXAMINATION FINDINGS.");
            return false;
        }
        else if(validateChecksHeent() == false){
            alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(validateChecksChest() == false){
            alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(validateChecksHeart() == false){
            alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(validateChecksAbdomen() == false){
            alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(validateChecksGenitoUrinary() == false){
            alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(validateChecksRectal() == false){
            alert("Choose at least one DIGITAL RECTAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(validateChecksSkin() == false){
            alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else if(validateChecksNeuro() == false){
            alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
            return false;
        }
        else {
            $("#list2").removeClass("active");
            $("#tab2").removeClass("active");
            $("#tab3").addClass("active in");
            $("#list3").addClass("active");
        }
    }

    if(id == 'tab4') {
        if(chksDiagnosis == "" || chksDiagnosis == null){
            alert("Fill up all the required fields in ASSESSMENT/DIAGNOSIS");
            return false;
        }
        else {
            $("#list3").removeClass("active");
            $("#tab3").removeClass("active");
            $("#tab4").addClass("active in");
            $("#list4").addClass("active");
        }
    }

    if(id == 'tab5') {
        var chkPrescribeYes= $("#medsStatusYes").is(":checked");
        var prescibingDoctor = $("#pPrescDoctor");

        if(validateChecksPlan() == false){            
                alert("Fill up all the required fields in PLAN/MANAGEMENT");
                return false;
        } else if(chkPrescribeYes == true && prescibingDoctor.val() == ""){
                alert("Fill up all fields in Prescribe Medicine");
        }
        else {
            $("#list4").removeClass("active");
            $("#tab4").removeClass("active");
            $("#tab5").addClass("active in");
            $("#list5").addClass("active");
        }
    }

    if(id == 'tab6') {
        if(chkCBCdone == true && txtCbcLabDate == "" && txtCbcLabFee == "" && txtCbcHema == "" && txtCbchemo == "" && txtCbcMhc == "" && txtCbcMchc == "" && txtCbcMcv == "" && txtCbcWbc == "" && txtCbcMyelocyte == "" && txtCbcNeutroBand == "" && txtCbcNeutroSeg == "" && txtCbcLymph == "" && txtCbcMono == "" && txtCbcEosi == "" && txtCbcBaso == "" && txtCbcPlatelet == ""){
            alert("Fill up all fields of CBC in LABORATORY RESULTS!");
            return false;
        }
        else if(compareCbcDate == "0"){
            alert("Laboratory Date of CBC is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkPapsSmearDone == true && txtPapsLabDate == "" && txtPapsLabFee == "" && txtPapsFind == "" && txtPapsImpre == ""){
            alert("Fill up all fields of Paps Smear in LABORATORY RESULTS!");
            return false;
        }
        else if(comparePapsDate == "0"){
            alert("Laboratory Date of Paps Smear is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkOGTTDone == true && txtOgttLabDate == "" && txtOgttLabFee == "" && txtOgttFastMg == "" && txtOgttFastMmol == "" && txtOgttOneMg == "" && txtOgttOneMmol == "" && txtOgttTwoMg == ""  && txtOgttTwoMmol == ""){
            alert("Fill up all fields of Oral Glucose Tolerance Test (OGTT) in LABORATORY RESULTS!");
            return false;
        }
        else if(compareOgttDate == "0"){
            alert("Laboratory Date of OGTT is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkUrinalysisDone == true && txtUrineLabDate == "" && txtUrineLabFee == "" && txtUrineSg == "" && txtUrineAppear == "" && txtUrineColor == "" && txtUrineGlucose == "" && txtUrineProtein == "" &&
            txtUrineKetones == "" && txtUrinePh == "" && txtUrinePus == "" && txtUrineAlb == "" && txtUrineRbc == "" && txtUrineWbc == "" && txtUrineBact == "" && txtUrineCryst == "" && txtUrineBlad == "" &&
            txtUrineSqCell == "" && txtUrineTubCell == "" && txtUrineBrCast == "" && txtUrineCellCast == "" && txtUrineGranCast == "" && txtUrineHyaCast == "" && txtUrineRbcCast == "" && txtUrineWaxyCast == "" && txtUrineWcCast == ""){
            alert("Fill up all fields of Urinalysis in LABORATORY RESULTS!");
            return false;
        }
        else if(compareUrineDate == "0"){
            alert("Laboratory Date of Urinalysis is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkFecalysisDone == true && txtFecaLabDate == "" && txtFecaLabFee == "" && txtFecaPus == "" && txtFecaRbc == "" && txtFecaWbc == "" && txtFecaOva == "" && txtFecaPara == "" && txtFecaOccult == ""){
            alert("Fill up all fields of Fecalysis in LABORATORY RESULTS!");
            return false;
        }
        else if(compareFecaDate == "0"){
            alert("Laboratory Date of Fecalysis is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkXrayDone == true && txtXrayLabDate == "" && txtXrayLabFee == "" && txtXrayFindings == ""){
            alert("Fill up all fields of Chest X-ray in LABORATORY RESULTS!");
            return false;
        }
        else if(compareXrayDate == "0"){
            alert("Laboratory Date of Chest X-ray is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkSputumDone == true && txtSputumLabDate == "" && txtSputumLabFee == "" && txtSputumPlusses == ""){
            alert("Fill up all fields of Sputum Microscopy in LABORATORY RESULTS!");
            return false;
        }
        else if(compareSputumDate == "0"){
            alert("Laboratory Date of Sputum Microscopy is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkLipidDone == true && txtLipidLabDate == "" && txtLipidLabFee == "" && txtLipidTotal == "" && txtLipidLdl == "" && txtLipidHdl == "" && txtLipidChol == "" && txtLipidTrigy == ""){
            alert("Fill up all fields of Lipid Profile in LABORATORY RESULTS!");
            return false;
        }
        else if(compareLipidDate == "0"){
            alert("Laboratory Date of Lipid Profile is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkECGDone == true && txtEcgLabDate == "" && txtEcgLabFee == "" && chkEcgNormal == false && chkEcgNotnNormal == false){
            alert("Fill up all fields of Electrocardiogram (ECG) in LABORATORY RESULTS!");
            return false;
        }
        else if(compareEcgDate == "0"){
            alert("Laboratory Date of ECG is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkFbsDone == true && txtFbsLabDate == "" && txtFbsLabFee == "" && txtFbsGlucoseMgdl == "" && txtFbsGlucoseMmol == ""){
            alert("Fill up all fields of Fasting Blood Sugar (FBS) in LABORATORY RESULTS!");
            return false;
        }      
        else if(compareFbsDate == "0"){
            alert("Laboratory Date of FBS is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkRbsDone == true && txtRbsLabDate == "" && txtRbsLabFee == "" && txtRbsGlucoseMgdl == "" && txtRbsGlucoseMmol == ""){
            alert("Fill up all fields of Random Blood Sugar (RBS) in LABORATORY RESULTS!");
            return false;
        }     
        else if(compareRbsDate == "0"){
            alert("Laboratory Date of RBS is invalid! It should be less than or equal to current day.");
            return false;
        }
        else {            
            $("#list5").removeClass("active");
            $("#tab5").removeClass("active");
            $("#tab6").addClass("active in");
            $("#list6").addClass("active");
        }
    }

    if(id == 'tab7') {
        $("#list6").removeClass("active");
        $("#tab6").removeClass("active");
        $("#tab7").addClass("active in");
        $("#list7").addClass("active");
    }
  

}      

function validateChecksChiefComplaint() {
    var chksChief = document.getElementsByName('complaint[]');
    var checkCountChief = 0;

    for (var i = 0; i < chksChief.length; i++) {
        if (chksChief[i].checked) {
            checkCountChief++;
        }
    }
    if ( checkCountChief < 1) {
        return false;
    }
    return true;
}


/*20191212 for Next Button*/
function showTabHSA(id){
    if(id == 'tab1') {
         /*Individual Health Profile*/
        var txtProfileOTP = $("#txtPerHistOTP").val();
        var cntProfileOTP = $("#txtPerHistOTP").val().length;
        var txtProfileDate = $("#txtPerHistProfDate").val();

        var chkWalkedIn = $("#walkedInChecker_true").is(":checked");
       
        /*Start Get date today*/
        var dateToday = new Date();
       
        var compareProfDate = compareDates(dateToday,txtProfileDate);
        /*End Get date today*/


        if(chkWalkedIn == true && (txtProfileOTP == "" || cntProfileOTP < 4)) {
            alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
            $("#txtPerHistOTP").focus();
            return false;
        }  
        else if (txtProfileDate == "") {
            alert("Screening & Assessment Date is required");
            $("#txtPerHistProfDate").focus();
            return false;
        }      
        else if (compareProfDate == "0") {
            alert("Screening & Assessment Date under CLIENT PROFILE menu is invalid! It should be less than or equal to current day.");
            $("#txtPerHistProfDate").focus();
            return false;
        }    
        else {
            $("#list1").removeClass("active");
            $("#tab1").removeClass("active");
            $("#tab2").addClass("active in");
            $("#list2").addClass("active");
        }
    }

    else if(id == 'tab2') {
        /*Past Medical History*/
        var chkAllergy = $("#chkMedHistDiseases_001").is(":checked");
        var chkCancer = $("#chkMedHistDiseases_003").is(":checked");
        var chkHepatitis = $("#chkMedHistDiseases_009").is(":checked");
        var chkHypertension = $("#chkMedHistDiseases_011").is(":checked");
        var chkPTB = $("#chkMedHistDiseases_015").is(":checked");
        var chkExPTB = $("#chkMedHistDiseases_016").is(":checked");
        var chkOthers = $("#chkMedHistDiseases_998").is(":checked");

        var txtAllergy = $("#txtMedHistAllergy").val();
        var txtCancer = $("#txtMedHistCancer").val();
        var txtHepatitis = $("#txtMedHistHepatitis").val();
        var txtDiastolic = $("#txtMedHistBPDiastolic").val();
        var txtSystolic = $("#txtMedHistBPSystolic").val();
        var txtPTB = $("#txtMedHistPTB").val();
        var txtExPTB = $("#txtMedHistExPTB").val();
        var txaOthers = $("#txaMedHistOthers").val();

        // if(validateChecksMedsHist() == false){
        //     alert("Choose at least one Past Medical History in MEDICAL & SURGICAL HISTORY menu");
        //     return false;
        // }
        /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN MEDICAL & SURGICAL HISTORY*/
        if(chkAllergy == true && txtAllergy == "") {
            alert("Please specify allergy under MEDICAL & SURGICAL HISTORY menu.");
            return false;
        }
        else if(chkCancer == true && txtCancer == "") {
            alert("Please specify organ with cancer under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistCancer").focus();
            return false;
        }
        else if(chkHepatitis == true && txtHepatitis == "") {
            alert("Please specify hepatitis type under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistHepatitis").focus();
            return false;
        }
        else if(chkHypertension == true && (txtSystolic == "" || txtDiastolic == "")) {
            alert("Please specify highest blood pressure under MEDICAL & SURGICAL HISTORY menu.");
            if(txtSystolic == "") {
                $("#txtMedHistBPSystolic").focus();
            }
            else {
                $("#txtMedHistBPDiastolic").focus();
            }
            return false;
        }
        else if(chkPTB == true && txtPTB == "") {
            alert("Please specify Pulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistPTB").focus();
            return false;
        }
        else if(chkExPTB == true && txtExPTB == "") {
            alert("Please specify Extrapulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
            $("#txtMedHistExPTB").focus();
            return false;
        }
        else if(chkOthers == true && txaOthers == "") {
            alert("Please specify others under MEDICAL & SURGICAL HISTORY menu.");
            $("#txaMedHistOthers").focus();
            return false;
        }
        else {
            $("#list2").removeClass("active");
            $("#tab2").removeClass("active");
            $("#tab3").addClass("active in");
            $("#list3").addClass("active");
        }
    }

    else if(id == 'tab3') {
        /*Family & Personal History*/
        // var chkAllergyFam = $("#chkFamHistDiseases_001").is(":checked");
        // var chkCancerFam = $("#chkFamHistDiseases_003").is(":checked");
        // var chkHepatitisFam = $("#chkFamHistDiseases_009").is(":checked");
        // var chkHypertensionFam = $("#chkFamHistDiseases_011").is(":checked");
        // var chkPTBfam = $("#chkFamHistDiseases_015").is(":checked");
        // var chkExPTBfam = $("#chkFamHistDiseases_016").is(":checked");
        // var chkOthersFam = $("#chkFamHistDiseases_998").is(":checked");

        // var txtAllergyFam = $("#txtFamHistAllergy").val();
        // var txtCancerFam = $("#txtFamHistCancer").val();
        // var txtHepatitisFam = $("#txtFamHistHepatitis").val();
        // var txtDiastolicFam = $("#txtFamHistBPDiastolic").val();
        // var txtSystolicFam = $("#txtFamHistBPSystolic").val();
        // var txtPTBfam = $("#txtFamHistPTB").val();
        // var txtExPTBfam = $("#txtFamHistExPTB").val();
        // var txaOthersFam = $("#txaFamHistOthers").val();

        /*Personal/Social History*/
        var chkFamHistSmokeY = $("#radFamHistSmokeY").is(":checked");
        var chkFamHistSmokeN = $("#radFamHistSmokeN").is(":checked");
        var chkFamHistSmokeX = $("#radFamHistSmokeX").is(":checked");
        var chkFamHistAlcoholY = $("#radFamHistAlcoholY").is(":checked");
        var chkFamHistAlcoholN = $("#radFamHistAlcoholN").is(":checked");
        var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
        var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
        var chkFamHistDrugsY = $("#radFamHistDrugsY").is(":checked");
        var chkFamHistDrugsN = $("#radFamHistDrugsN").is(":checked");
        var chkFamHistSexualHistY = $("#radFamHistSexualHistY").is(":checked");
        var chkFamHistSexualHistN = $("#radFamHistSexualHistN").is(":checked");

        /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN FAMILY & PERSONAL HISTORY*/
        // if(chkAllergyFam == true && txtAllergyFam == "") {
        //     alert("Please specify allergy under FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkCancerFam == true && txtCancerFam == "") {
        //     alert("Please specify organ with cancer under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistCancer").focus();
        //     return false;
        // }
        // else if(chkHepatitisFam == true && txtHepatitisFam == "") {
        //     alert("Please specify hepatitis type under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistHepatitis").focus();
        //     return false;
        // }
        // else if(validateChecksFamHist() == false){
        //     alert("Choose at least one Family History in FAMILY & PERSONAL HISTORY menu");
        //     return false;
        // }
        // else if(chkHypertensionFam == true && (txtSystolicFam == "" || txtDiastolicFam == "")) {
        //     alert("Please specify highest blood pressure under FAMILY & PERSONAL HISTORY menu.");
        //     if(txtSystolic == "") {
        //         $("#txtFamHistBPSystolic").focus();
        //     }
        //     else {
        //         $("#txtFamHistBPDiastolic").focus();
        //     }
        //     return false;
        // }
        // else if(chkPTBfam == true && txtPTBfam == "") {
        //     alert("Please specify Pulmonary Tuberculosis category under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistPTB").focus();
        //     return false;
        // }
        // else if(chkExPTBfam == true && txtExPTBfam == "") {
        //     alert("Please specify Extrapulmonary Tuberculosis category under FAMILY & PERSONAL HISTORY menu.");
        //     $("#txtFamHistExPTB").focus();
        //     return false;
        // }
        // else if(chkOthersFam == true && txaOthersFam == "") {
        //     alert("Please specify others.");
        //     $("#txaFamHistOthers").focus();
        //     return false;
        // }
        // if(chkFamHistSmokeY == false && chkFamHistSmokeN == false && chkFamHistSmokeX == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkFamHistAlcoholY == false && chkFamHistAlcoholN == false && chkFamHistAlcoholX == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkFamHistDrugsY == false && chkFamHistDrugsN == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else if(chkFamHistSexualHistY == false && chkFamHistSexualHistN == false) {
        //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        //     return false;
        // }
        // else {
            if ($("#chkFamHistDiseases_006").is(":checked")) {
                $("#list3").removeClass("active");
                $("#tab3").removeClass("active");
                $("#tab3_1").addClass("active in");
                $("#list3_1").addClass("active");
            } else {
                $("#list3").removeClass("active");
                $("#tab3").removeClass("active");
                $("#tab4").addClass("active in");
                $("#list4").addClass("active");
            }
        // }
    }

    else if(id == 'tab3_1') {
            $("#list3_1").removeClass("active");
            $("#tab3_1").removeClass("active");

            $("#tab4").addClass("active in");
            $("#list4").addClass("active");
    }
    
    else if(id == 'tab4') {
        /*Immmunizations*/
        // if (validateChecksImmune() == false){
        //     alert("Choose at least one in each category of IMMUNIZATION");
        //     return false;   
        // } 
        // else {
            $("#list4").removeClass("active");
            $("#tab4").removeClass("active");
            $("#tab5").addClass("active in");
            $("#list5").addClass("active");
        //}
    }

    else if(id == 'tab5') {
        /*OB-Gyne History*/
        /*Menstrual History*/
        var txtMenarche = $("#txtOBHistMenarche").val();
        var txtLastMens = $("#txtOBHistLastMens").val();
        var dateLastMens = new Date(txtLastMens);
        var compareLastMensDate = compareDates(dateToday,dateLastMens);
        var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
        /*Pregnancy History*/
        var txtGravity = $("#txtOBHistGravity").val();
        var txtParity = $("#txtOBHistParity").val();

        var whatSex = $("#txtPerHistPatSex").val();
        var whatAge = $("#valtxtPerHistPatAge").val();
        var whatMonths = $("#valtxtPerHistPatMonths").val();

        var chkMHdone = $("#mhDone_Y").is(":checked");
        var chkPREGdone = $("#pregDone_Y").is(":checked");

        
        if(chkMHdone == true && whatSex == "FEMALE" && (txtMenarche == "" || txtLastMens == "" || txtPeriodDuration == "") && whatAge >= 10){
            alert("Menarche, Last Menstrual Period and Period Duration are REQUIRED in MENSTRUAL HISTORY under OB-GYNE HISTORY menu!");
            return false;
        }
        else if(compareLastMensDate == "0"){
            alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(chkPREGdone == true && whatSex == "FEMALE" && (txtGravity == "" || txtParity == "")){
            alert("Gravity and Parity are REQUIRED in PREGNANCY HISTORY under OB-GYNE HISTORY menu!");
            return false;
        }
        else {
            $("#list5").removeClass("active");
            $("#tab5").removeClass("active");
            $("#tab6").addClass("active in");
            $("#list6").addClass("active");
        }
    }
    else if(id == 'tab6') {
        /*Pertinent Physical Examination Findings*/
        // var txtPhExSystolic = $("#txtPhExSystolic").val();
        // var txtPhExBPDiastolic = $("#txtPhExBPDiastolic").val();
        // var txtPhExHeartRate = $("#txtPhExHeartRate").val();
        // var txtPhExRespiratoryRate = $("#txtPhExRespiratoryRate").val();
        // var txtPhExHeightCm = $("#txtPhExHeightCm").val();
        // var txtPhExWeightKg = $("#txtPhExWeightKg").val();
        // var txtPhExTemp = $("#txtPhExTemp").val();

        // var txtPhEXBMIResult = $("#txtPhExBMI").val();

        // /*General Survey*/
        // var chkGenSurvey1 = $("#pGenSurvey_1").is(":checked");
        // var chkGenSurvey2 = $("#pGenSurvey_2").is(":checked");
        // var txtGenSurveyRem = $("#pGenSurveyRem").val();

        // if(txtPhExSystolic == "" || txtPhExBPDiastolic == "" || txtPhExHeartRate == "" || txtPhExRespiratoryRate == "" || txtPhExHeightCm == "" || txtPhExWeightKg == "" || txtPhExTemp == ""){
        //     alert("Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        //     return false;
        // }
        // else if(txtPhEXBMIResult == ""){
        //     alert("BMI is required. Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        //     return false;
        // }
        // else if(chkGenSurvey1 == false && chkGenSurvey2 == false){
        //     alert("Please specify General Survey under PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        //     $("#pGenSurvey_1").focus();
        //     return false;
        // }
        // else if(chkGenSurvey2 == true && txtGenSurveyRem == ""){
        //     alert("Please specify Altered Sensorium in General Survey under PHYSICAL EXAMINATION ON ADMISSION!");
        //     $("#pGenSurveyRem").focus();
        //     return false;
        // }
        // else if(validateChecksHeent() == false){
        //     alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksChest() == false){
        //     alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksHeart() == false){
        //     alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksAbdomen() == false){
        //     alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksGenitoUrinary() == false){
        //     alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksRectal() == false){
        //     alert("Choose at least one DIGITAL RECTAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksSkin() == false){
        //     alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else if(validateChecksNeuro() == false){
        //     alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PERTINENT PHYSICAL EXAMINATION FINDINGS");
        //     return false;
        // }
        // else {
            $("#list6").removeClass("active");
            $("#tab6").removeClass("active");
            $("#tab7").addClass("active in");
            $("#list7").addClass("active");
        //}
    }
   

}




/*20191210 eKONSULTA enhancement*/
/*pertinent findings per system*/
function checkHeent() {
    if(isChecked('heent_99')) {
        enableID('heent_remarks');
        setFocus('heent_remarks');
    }
    else {
        disableID('heent_remarks');
    }

    if(isChecked('heent_11')){
        disableID('heent_12');
        disableID('heent_13');
        disableID('heent_14');
        disableID('heent_15');
        disableID('heent_16');
        disableID('heent_17');
        disableID('heent_18');
        disableID('heent_99');
        disableID('heent_remarks');
    } else{
        enableID('heent_12');
        enableID('heent_13');
        enableID('heent_14');
        enableID('heent_15');
        enableID('heent_16');
        enableID('heent_17');
        enableID('heent_18');
        enableID('heent_99');
    }
}

function checkHeart(){
    if(isChecked('heart_99')) {
        enableID('heart_remarks');
        setFocus('heart_remarks');
    }
    else {
        disableID('heart_remarks');
    }

    if(isChecked('heart_5')){
        disableID('heart_6');
        disableID('heart_3');
        disableID('heart_7');
        disableID('heart_8');
        disableID('heart_4');
        disableID('heart_9');
        disableID('heart_99');
        disableID('heart_remarks');
    } else{
        enableID('heart_6');
        enableID('heart_3');
        enableID('heart_7');
        enableID('heart_8');
        enableID('heart_4');
        enableID('heart_9');
        enableID('heart_99');
    }
}

function checkAbdomen(){
    if(isChecked('abdomen_99')) {
        enableID('abdomen_remarks');
        setFocus('abdomen_remarks');
    }
    else {
        disableID('abdomen_remarks');
    }

    if(isChecked('abdomen_7')){
        disableID('abdomen_8');
        disableID('abdomen_9');
        disableID('abdomen_10');
        disableID('abdomen_11');
        disableID('abdomen_12');
        disableID('abdomen_13');
        disableID('abdomen_99');
        disableID('abdomen_remarks');
    } else{
        enableID('abdomen_8');
        enableID('abdomen_9');
        enableID('abdomen_10');
        enableID('abdomen_11');
        enableID('abdomen_12');
        enableID('abdomen_13');
        enableID('abdomen_99');
    }
}

function checkGU() {
    if(isChecked('gu_99')) {
        enableID('gu_remarks');
        setFocus('gu_remarks');
    }
    else {
        disableID('gu_remarks');
    }

    if(isChecked('gu_1')){
        disableID('gu_2');
        disableID('gu_3');
        disableID('gu_4');
        disableID('gu_99');
        disableID('gu_remarks');
    } else{
        enableID('gu_2');
        enableID('gu_3');
        enableID('gu_4');
        enableID('gu_99');
    }
}

function checkSkinExtrem(){
    if(isChecked('extremities_99')) {
        enableID('extremities_remarks');
        setFocus('extremities_remarks');
    }
    else {
        disableID('extremities_remarks');
    }

    if(isChecked('extremities_1')){
        disableID('extremities_2');
        disableID('extremities_3');
        disableID('extremities_4');
        disableID('extremities_5');
        disableID('extremities_6');
        disableID('extremities_7');
        disableID('extremities_8');
        disableID('extremities_9');
        disableID('extremities_10');
        disableID('extremities_99');
        disableID('extremities_remarks');
    } else{
        enableID('extremities_2');
        enableID('extremities_3');
        enableID('extremities_4');
        enableID('extremities_5');
        enableID('extremities_6');
        enableID('extremities_7');
        enableID('extremities_8');
        enableID('extremities_9');
        enableID('extremities_10');
        enableID('extremities_99');
    }
}

function checkNeuro(){
    if(isChecked('neuro_99')) {
        enableID('neuro_remarks');
        setFocus('neuro_remarks');
    }
    else {
        disableID('neuro_remarks');
    }

    if(isChecked('neuro_6')){
        disableID('neuro_7');
        disableID('neuro_8');
        disableID('neuro_9');
        disableID('neuro_10');
        disableID('neuro_11');
        disableID('neuro_12');
        disableID('neuro_13');
        disableID('neuro_99');
        disableID('neuro_remarks');
    } else{
        enableID('neuro_7');
        enableID('neuro_8');
        enableID('neuro_9');
        enableID('neuro_10');
        enableID('neuro_11');
        enableID('neuro_12');
        enableID('neuro_13');
        enableID('neuro_99');
    }
}

function checkRectal(){
    if(isChecked('rectal_99')) {
        enableID('rectal_remarks');
        setFocus('rectal_remarks');
    }
    else {
        disableID('rectal_remarks');
    }

    if(isChecked('rectal_1')){
        disableID('rectal_2');
        disableID('rectal_3');
        disableID('rectal_4');
        disableID('rectal_5');
        disableID('rectal_0');
        disableID('rectal_99');
        disableID('rectal_remarks');
    } else{
        enableID('rectal_2');
        enableID('rectal_3');
        enableID('rectal_4');
        enableID('rectal_5');
        enableID('rectal_0');
        enableID('rectal_99');
    }
}

/** getXmlHttpObject **/
function GetXmlHttpObject(sender) {
    var xmlHttp=null;
    // Firefox, Opera 8.0+, Safari
    try {
        xmlHttp=new XMLHttpRequest();
    }
    catch (e) {
        // Internet Explorer
        try {
            xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
        }
        catch (e) {
            try {
                xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
            }
            catch (e) {
                alert("Your browser does not support AJAX! Please update your browser.");
                return null;
            }
        }
    }
    return xmlHttp;
}

/** Checking of Dates Version 1 **/
function check_date(pAdmissionDate,pDischargeDate) {
    var fromdate = pAdmissionDate.split('/');
    pAdmissionDate = new Date();
    pAdmissionDate.setFullYear(fromdate[2],fromdate[0]-1,fromdate[1]); //setFullYear(year,month,day)

    var todate = pDischargeDate.split('/');
    pDischargeDate = new Date();
    pDischargeDate.setFullYear(todate[2],todate[0]-1,todate[1]);

    if (pAdmissionDate > pDischargeDate ) {
        return false;
    }
    else {
        return true;
    }
}

/** Checking of Dates Version 3 **/
function checkDateValue(dateValue) {
    var error = 0;
    var now = new Date();
    var day = now.getDate();
    var mon = now.getMonth()+1;
    var year = now.getFullYear();
    var selectedDate = dateValue;
    selectedDate = selectedDate.split("/");

    if (ValidateDate(selectedDate[2], selectedDate[0], selectedDate[1]) === false) { error = 1; }

    if ( selectedDate[2] > year ) { error = 1; }
    else if ( selectedDate[2] < 1000 ) { error = 1; }
    else if (selectedDate[2] == year) {
        if ( selectedDate[0] > mon ) { error = 1; }
        else if ( selectedDate[0] == mon ) {
            if ( selectedDate[1] > day ) { error = 1; }
        }
    }

    if (error == 1) { return false; }
    else { return true; }
}

/** Checking of Dates Version 4 **/
function ValidateDate(y, mo, d) {
    var date = new Date(y, mo - 1, d);
    var ny = date.getFullYear();
    var nmo = date.getMonth() + 1;
    var nd = date.getDate();
    return ny == y && nmo == mo && nd == d;
}

/** is Number Key **/
function isNumberKey(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57))
        return false;

    return true;

}

/** is Number Key **/
function isNumberWithDecimalKey(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode;
    if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    } else {
        // If the number field already has . then don't allow to enter . again.
        if (evt.target.value.search(/\./) > -1 && charCode == 46) {
            return false;
        }
        return true;
    }
}

/** Validate Alpha Characters  **/
function ValidateAlpha(evt) {
    var keyCode = (evt.which) ? evt.which : evt.keyCode;
    if ((keyCode < 65 || keyCode > 90) && (keyCode < 97 || keyCode > 123) && keyCode != 32 &&
        keyCode != 45 && keyCode != 209 && keyCode != 241 && keyCode != 13 && keyCode != 37) //45 = '-', 209 = 'Ñ', 241 = 'ñ', 13 = 'Enter Key', 37 '%' //savr20151026
        return false;

    return true;
}

/** Set Focus **/
function setFocus(id) {
    document.getElementById(id).focus();
}

/** Set Display **/
function setDisplay(id, dis) {
    document.getElementById(id).style.display = dis;
}

/** Set Disabled **/
function setDisabled(id, dis) {
    document.getElementById(id).disabled = dis;
}

/** Set Value **/
function setValue(id, val) {
    document.getElementById(id).value = val;
}

/** Get Value **/
function getValue(id) {
    return document.getElementById(id).value;
}

/** Is Checked **/
function isChecked(id) {
    if (document.getElementById(id).checked == true) { return true; }
    else { return false; }
}

/** Disable ID **/
function disableID(id) {
    document.getElementById(id).disabled = true;
}

/** Enabled ID **/
function enableID(id) {
    document.getElementById(id).disabled = false;
}

/** Check ID **/
function checkID(id) {
    document.getElementById(id).checked = true;
}

/** UnCheck ID **/
function uncheckID(id) {
    document.getElementById(id).checked = false;
}

/* Print Report */
function printReport(action, title) {
    document.getElementById('statsForm').action = action + '/print_report/' + title;
    document.getElementById('statsForm').target = '_blank';
    document.getElementById('statsForm').submit();
}

/* Show/Hide ID */
function showHideID(id, task) {
    if (task == 'show') {
        document.getElementById(id).style.display = '';
    } else {
        document.getElementById(id).style.display = 'none';
    }
}

/* URL redirection */
function urlRedirection(url) {
    window.location = url;
}

/* URL Window Open */
function urlWindowOpen(url) {
    params  = 'width='+screen.width;
    params += ', height='+screen.height;
    params += ', top=0, left=0'
    params += ', fullscreen=yes';

    newwin=window.open(url,'windowname4', params);
    if (window.focus) {newwin.focus()}
    return false;
}

/* Ask Confirmation Before Saving */
function confirmSave(message, form_id) {
    var response = confirm(message + "Continue to save?");
    if (response == true) {
        document.getElementById(form_id).submit();
    }
}

/* Validate Search PhilHealth Records */
function validateSearch() {
    var pin = $("#pPIN").val();
    var lastname = $("#pLastName").val();
    var firstname = $("#pFirstName").val();
    var birthday = $("#pDateOfBirth").val();

    if(pin == "" && lastname == "" && firstname == "" && birthday == "") {
        alert("Please input any of the following: \n\n-PhilHealth Identification Number.\n-Name and Birthday.");
        $("#pPIN").focus();
        return false;
    }
    else {
        if(pin == "" && lastname == "") {
            alert("Please input Last Name.");
            $("#pLastName").focus();
            return false;
        }
        else if(pin == "" && firstname == "") {
            alert("Please input First Name.");
            $("#pFirstName").focus();
            return false;
        }
        else if(pin == "" && birthday == "") {
            alert("Please input Date of Birth.");
            $("#pDateOfBirth").focus();
            return false;
        }
    }

    $("#wait_image").show();
}
function validateEnlistmentSearch() {
    var pPIN = getValue('pPIN');
    var pLastName = getValue('pLastName');
    var pFirstName = getValue('pFirstName');
    var pDateOfBirth = getValue('pDateOfBirth');

    if ((pPIN == '') && (pLastName == '') && (pFirstName == '') && (pDateOfBirth == '')) {
        alert('Please Indicate PIN');
        setFocus('pPIN');
    }
    else if ((pPIN == '') && pLastName == '') {
        alert('Last Name is required');
        setFocus('pLastName');
    }
    else if ((pPIN == '') && pFirstName == '') {
        alert('First Name is required');
        setFocus('pFirstName');
    }
    else {
        setDisplay('content_div', 'none');
        setDisplay('result', 'none');
        setDisplay('wait_image', '');
        document.getElementById('search_enlistment_form').submit();
    }
}

function validateConsultationSearch() {
    var pPIN = getValue('pPIN');
    var pLastName = getValue('pLastName');
    var pFirstName = getValue('pFirstName');
    var pDateOfBirth = getValue('pDateOfBirth');

    if ((pPIN == '') && (pLastName == '') && (pFirstName == '') && (pDateOfBirth == '')) {
        alert('Please Indicate PIN');
        setFocus('pPIN');
    }
    else if ((pPIN == '') && pLastName == '') {
        alert('Last Name is required');
        setFocus('pLastName');
    }
    else if ((pPIN == '') && pFirstName == '') {
        alert('First Name is required');
        setFocus('pFirstName');
    }
    else {
        setDisplay('content_div', 'none');
        setDisplay('result', 'none');
        setDisplay('wait_image', '');
        document.getElementById('search_profile_form').submit();
    }
}

function validateLabResultsSearch() {
    var pPIN = getValue('pPIN');
    var pLastName = getValue('pLastName');
    var pFirstName = getValue('pFirstName');
    var pDateOfBirth = getValue('pDateOfBirth');

    if ((pPIN == '') && (pLastName == '') && (pFirstName == '') && (pDateOfBirth == '')) {
        alert('Please Indicate PIN');
        setFocus('pPIN');
    }
    else if ((pPIN == '') && pLastName == '') {
        alert('Last Name is required');
        setFocus('pLastName');
    }
    else if ((pPIN == '') && pFirstName == '') {
        alert('First Name is required');
        setFocus('pFirstName');
    }
    else if ((pPIN == '') && (pDateOfBirth != '')) {
        alert('Please input a valid value for Date of Birth');
        setFocus('pDateOfBirth');
    }
    else {
        setDisplay('content_div', 'none');
        setDisplay('result', 'none');
        setDisplay('wait_image', '');
        document.getElementById('search_lab_results_form').submit();
    }
}
/* Validate Data Entry of Enlistment */
function validatePatientForEnlistment() {
    var pCaseNo = getValue('pCaseNo');
    var pEnlistmentDate = getValue('pEnlistmentDate');
    var pPatientType = getValue('pPatientType');
    var pWithConsent = getValue('pWithConsent');
    var pIsEligible = getValue('pIsEligible');
    if ((pPatientType == 'DD') && (pIsEligible != 'NOT ELIGIBLE')) { var pWithLOA = getValue('pWithLOA'); }
    var pPatientLastName = getValue('pPatientLastName');
    var pPatientFirstName = getValue('pPatientFirstName');
    var pPatientDateOfBirth = getValue('pPatientDateOfBirth');
    var pPatientContactNo = getValue('pPatientContactNo');
    var pPatientSexX = getValue('pPatientSexX');
    var pPatientCivilStatusX = getValue('pPatientCivilStatusX');
    var pProvinceX = getValue('pProvinceX');
    var pMunicipalityX = getValue('pMunicipalityX');
    var pBarangayX = getValue('pBarangayX');
    var pTaggingForEnrollment = getValue('pTaggingForEnrollment');
    //var pPatientFamilyPlanningCounselling = document.getElementById('pPatientFamilyPlanningCounselling').value;
    //var pDateToday = getValue('pDateToday'); c

    // alert(pPatientSexX);

    if (pEnlistmentDate == '' || !checkDateValue(pEnlistmentDate)) {
        alert('Encounter Date is invalid.');
        setFocus('pEnlistmentDate');
    }
    else
    if ((pCaseNo == '') && (!isDateWithinRange(pEnlistmentDate))) { //savr 2016-01-21
        alert('Encounter Date must be within this quarter.');
        setFocus('pEnlistmentDate');
    }
    else
    if (pWithConsent == '') {
        alert('With Consent is required.');
        setFocus('pWithConsent');
    }
    else
    if ((pCaseNo == '') && (pPatientType == 'DD') && (pWithLOA == '')) {
        alert('With Letter of Authorization is required.');
        setFocus('pWithLOA');
    }
    else
    if (pPatientLastName == '') {
        alert('Last Name is required.');
        setFocus('pPatientLastName');
    }
    else
    if (pPatientFirstName == '') {
        alert('First Name is required.');
        setFocus('pPatientFirstName');
    }
    else
    if (pPatientDateOfBirth == '') {
        alert('Date of Birth is required.');
        setFocus('pPatientDateOfBirth');
    }
    else
    if (pPatientContactNo == '') {
        alert('Contact No. is required.');
        setFocus('pPatientContactNo');
    }
    else
    if ((pPatientSexX == '-') || (pPatientSexX == '')) { //savr 2016-04-08: update validation of Sex Field
        alert('Sex is required.');
        setFocus('pPatientSexX');
    }
    else
    if ((pPatientCivilStatusX == '-') || (pPatientCivilStatusX == '')) { //savr 2016-04-08: update validation of Civil Status Field
        alert('Civil Status is required.');
        setFocus('pPatientCivilStatusX');
    }
    else
    if (pProvinceX == '') {
        alert('Province is required.');
        setFocus('pProvinceX');
    }
    else
    if (pMunicipalityX == '') {
        alert('Municipality is required.');
        setFocus('pMunicipalityX');
    }
    else
    if ((pBarangayX == '') && (pPatientType == 'NM')) {
        alert('Barangay is required.');
        setFocus('pBarangayX');
    }
    else
    if ((pTaggingForEnrollment == '') && ((pPatientType == 'NM') || ((pPatientType == 'DD') && (pIsEligible == 'NOT ELIGIBLE')))) {
        alert('Tagging For Enrollment is required.');
        setFocus('pTaggingForEnrollment');
    }
    /*else
    if (pPatientFamilyPlanningCounselling == '') {
        alert('Choose One for Family Planning Counselling');
        setFocus('pPatientFamilyPlanningCounselling');
    }*/
    else {
        setDisplay('content_div', 'none');
        setDisplay('wait_image', '');
        document.getElementById('data_entry_enlistment_form').submit();
    }
}


/* Validate SOAP */
function validateSOAP(obligated_services) {
    var message = '';
    var focusID = '';
    var BPMeasurements = false;
    //var obligated_services = Array('', 'BP Measurements', 'Periodic clinical breast cancer examination', 'Visual inspection with acetic acid', 'Digital Rectal Examination');

    // Obligated Services Checking
    var obligated_error = false;
    for (i = 1; i < 5; i++) {
        if (i == 1) {
            if ((!isChecked('obligated_service_' + i + '_yes')) && (!isChecked('obligated_service_' + i + '_no'))) {
                obligated_error = true;
                message = 'Select one for \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                break;
            }
            else
            if (isChecked('obligated_service_' + i + '_yes') && getValue('obligated_service_' + i + '_type') == '') {
                obligated_error = true;
                message = 'Select one type in \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                focusID = 'obligated_service_' + i + '_type';
                break;
            }
            else {
                BPMeasurements = isChecked('obligated_service_' + i + '_yes');
            }
        }
        else {
            if ((!isChecked('obligated_service_' + i + '_yes')) && (!isChecked('obligated_service_' + i + '_no'))  && (!isChecked('obligated_service_' + i + '_waived'))) {
                obligated_error = true;
                message = 'Select one for \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                break;
            }
            else
            if (isChecked('obligated_service_' + i + '_waived') && getValue('obligated_service_' + i + '_waived_reason') == '') {
                obligated_error = true;
                message = 'Select one reason in \'' + obligated_services[i] + '\' under Obligated Service Tab.';
                focusID = 'obligated_service_' + i + '_waived_reason';
                break;
            }
        }
    }

    // Subjective/History of Illnees Checking
    var sujective_error = false;
    if (!obligated_error) {
        var pSOAPDate = getValue('pSOAPDate');
        if (!checkDateValue(pSOAPDate)) {
            sujective_error = true;
            focusID = 'pSOAPDate';
            message = 'Invalid value for consultation date under Subjective/History of Illness Tab.';
        }
        else
        if ((!isDateWithinRange(pSOAPDate))) {//savr 2016-06-06 #v1.1.2: added checking of consultation date
            sujective_error = true;
            focusID = 'pSOAPDate';
            message = 'Consultation Date must be within this quarter.';
        }
        else
        if (getValue('pChiefComplaint') == '') {
            sujective_error = true;
            focusID = 'pChiefComplaint';
            message = 'Enter a valid chief complaint under Subjective/History of Illness Tab.';
        }
    }

    // Objective/Physical Examination Checking
    var objective_error = false;
    if (!sujective_error && !obligated_error) {
        if (BPMeasurements && (getValue('pe_bp_u') == '')) {
            objective_error = true;
            focusID = 'pe_bp_u';
            message = 'Enter systolic value in BP under Objective/Physical Examination Tab.';
        }
        else
        if (BPMeasurements && (getValue('pe_bp_l') == '')) {
            objective_error = true;
            focusID = 'pe_bp_l';
            message = 'Enter diastolic value in BP under Objective/Physical Examination Tab.';
        }
    }

    // Assessment/Diagnosis Checking
    var assessment_error = false;
    if (!sujective_error && !obligated_error && !objective_error) {
        var table = document.getElementById('diagnosis_table');
        var rowCount = table.rows.length;  //It will return the last Index of the row and its row count
        var actualRowCount = parseInt(rowCount) -1 ;

        if (actualRowCount == 0) {
            assessment_error = true;
            message = 'Please add at least one diagnosis in the Assessment/Diagnosis Tab.';
            focusID = 'pICD';
        }
    }

    // Plan/Management Checking
    var plan_error = false;
    var pDiagnostic = false;
    var pManagement = false;
    if (!sujective_error && !obligated_error && !objective_error && !assessment_error) {
        for (i = 1; i < 13; i++) {
            if (isChecked('diagnostic_' + i)) { pDiagnostic = true; break; }
        }
        if (isChecked('diagnostic_oth')) { pDiagnostic = true; }

        for (i = 1; i < 5; i++) {
            if (isChecked('management_' + i)) { pManagement = true; break; }
        }

        if (!pDiagnostic && !isChecked('diagnostic_NA')) {
            plan_error = true;
            message = 'Please select at least one in Diagnostic Examination or tick Not Applicable.'
        }
        else
        if (isChecked('diagnostic_oth') && getValue('diagnostic_oth_remarks') == '') {
            plan_error = true;
            message = 'Please specify the other diagnostic examination.'
            focusID = 'diagnostic_oth_remarks';
        }
        else
        if (!pManagement && !isChecked('management_NA')) {
            plan_error = true;
            message = 'Please select at least one in Management or tick Not Applicable.'
        }

    }

    if (obligated_error) {
        alert(message);
        document.getElementById('obliSerTabClick').click();
        if (focusID != '') { setFocus(focusID); }
    }
    else
    if (sujective_error) {
        alert(message);
        document.getElementById('subjectiveTabClick').click();
        setFocus(focusID);
    }
    else
    if (objective_error) {
        alert(message);
        document.getElementById('objectiveTabClick').click();
        setFocus(focusID);
    }
    else
    if (assessment_error) {
        alert(message);
        document.getElementById('assessmentTabClick').click();
        setFocus(focusID);
    }
    else
    if (plan_error) {
        alert(message);
        document.getElementById('planTabClick').click();
        if (focusID != '') { setFocus(focusID); }
    }
    else {
        setDisplay('content_div_body', 'none');
        setDisplay('wait_image_outside', '');
        document.getElementById('data_entry_soap_form').submit();
        //alert('Patient Record has been saved');
    }
}

/* Conversion */
function roundit(num) {
    return Math.round(num * 100) / 100;
}

function CmtoInch(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value / 2.54);
}

function InchToCm(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value * 2.54);
}

function KgToLb(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value * 2.20462);
}

function LbToKg(x) {
    if (x.value.match(/[^\d.]/)) {
        x.value = x.value.replace(/[^\d.]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return roundit(x.value / 2.20462);
}

function ChkWholeNum(x) {
    if (x.value.match(/[^\d]/)) {
        x.value = x.value.replace(/[^\d]/g, '');
    }
    if (isNaN(x.value)) {
        x.value = x.value.substring(0, x.value.length - 1);
    }

    return x.value;
}


/* GET BMI */
function ClearForm(form){

    form.txtPhExWeightKg.value = "";
    form.txtPhExHeightCm.value = "";
    form.txtPhExBMI.value = "";
    // form.bmiDescription.value = "";

    form.txtPhExWeightLb.value = "";
    form.txtPhExHeightIn.value = "";

}

function bmi(weight, height) {

          bmindx=weight/eval(height*height);
          return bmindx;
}

function checkform(form) {

       if (form.txtPhExWeightKg.value==null||form.txtPhExWeightKg.value.length==0 || form.txtPhExHeightCm.value==null||form.txtPhExHeightCm.value.length==0){
            alert("\nPlease input value on Height (cm) and Weight (kg)");
            return false;
       }

       else if (parseFloat(form.txtPhExHeightCm.value) <= 0||
                parseFloat(form.txtPhExHeightCm.value) >=500||
                parseFloat(form.txtPhExWeightKg.value) <= 0||
                parseFloat(form.txtPhExWeightKg.value) >=500){
                alert("\nPlease enter values again. \nHeight in cm and \nWeight in kilos ");
                ClearForm(form);
                return false;
       }
       return true;

}


function computeBMI(form) {

       if (checkform(form)) {
       // clientBMI=Math.round(bmi(form.txtPhExWeightKg.value, form.txtPhExHeightCm.value/100));
       clientBMI=bmi(form.txtPhExWeightKg.value, form.txtPhExHeightCm.value/100);
       form.txtPhExBMI.value=clientBMI.toFixed(2);
          
           if (clientBMI >= 30) {
              // form.bmiDescription.value="Obesity";
               $('#bmiDescription').text(' Result Description: Obesity');
           }

           else if (clientBMI >= 25 && clientBMI <=30) {
              // form.bmiDescription.value="Overweight";
              $('#bmiDescription').text(' Result Description: Overweight');
           }

           else if (clientBMI >= 18.6 && clientBMI <= 24.9) {
              // form.bmiDescription.value="Normal weight";
              $('#bmiDescription').text(' Result Description: Normal weight');
           }

           else if (clientBMI <= 18.5) {
              // form.bmiDescription.value="Underweight!";
              $('#bmiDescription').text(' Result Description: Underweight');
           }
    
       }
       return;
}

/* Validate Report List */
function validateReportList(form_id) {
    var pStartDate = getValue('pStartDate');
    var pEndDate = getValue('pEndDate');

    if (!checkDateValue(pStartDate)) { alert('Invalid value for start date.'); setFocus('pStartDate'); }
    else if (!checkDateValue(pEndDate)) { alert('Invalid value for end date.'); setFocus('pEndDate'); }
    else if (check_date(pStartDate, pEndDate) == false) {
        alert('End Date must not be earlier than the Start Date');
        setFocus('pStartDate');
    }
    else {
        setDisplay('results_list_tbl', 'none');
        setDisplay('no_record_tbl', 'none');
        setDisplay('wait_image', '');
        document.getElementById(form_id).submit();
        setDisabled('pReportType', true);
        setDisabled('pStartDate', true);
        setDisabled('pEndDate', true);
        setDisabled('pGenerate', true);
        //setDisabled('pPrint', true);
    }
}

/* Checked Waived Reason if OTHER is selected */
function onChangeWaivedReason(reasonID, remarkID) {
    var reasonVal = getValue(reasonID);
    if (reasonVal == 'X') { setDisabled(remarkID, false); setFocus(remarkID); }
    else { setDisabled(remarkID, true); }
}

/* for table display */
function designTable(tableID) {
    var table = document.getElementById(tableID);
    var rowCount = table.rows.length;  //It will return the last Index of the row and its row count
    var actualRowCount = parseInt(rowCount)-1;

    for (i = 1; i <= actualRowCount; i++) {
        if (i % 2 == 1) {
            table.rows[i].style.backgroundColor = '#FBFCC7';
        }
        else {
            table.rows[i].style.backgroundColor = '';
        }
    }
}

/* Disable Diagnostic Examinations */
function enableDisableDiagnosticExaminations() {
    if (isChecked('diagnostic_NA')) {
        for (i = 1; i < 18; i++) {
            disableID('diagnostic_' + i + '_doctorYes');
            disableID('diagnostic_' + i + '_doctorNo');
            disableID('diagnostic_oth');
            disableID('diagnostic_13_doctorYes');
            disableID('diagnostic_14_doctorYes');
            disableID('diagnostic_9_doctorYes');
            disableID('diagnostic_15_doctorYes');
            disableID('diagnostic_17_doctorYes');
            disableID('diagnostic_18_doctorYes');
            disableID('diagnostic_oth_remarks1');
            disableID('diagnostic_oth_remarks2');
            disableID('diagnostic_oth_remarks3');

            disableID('diagnostic_13_doctorNo');
            disableID('diagnostic_14_doctorNo');
            disableID('diagnostic_9_doctorNo');
            disableID('diagnostic_15_doctorNo');
            disableID('diagnostic_17_doctorNo');
            disableID('diagnostic_18_doctorNo');


            disableID('diagnostic_' + i + '_patientRQ');
            disableID('diagnostic_' + i + '_patientRF');
            disableID('diagnostic_13_patientRQ');
            disableID('diagnostic_14_patientRQ');
            disableID('diagnostic_9_patientRQ');
            disableID('diagnostic_15_patientRQ');
            disableID('diagnostic_17_patientRQ');
            disableID('diagnostic_18_patientRQ');

            disableID('diagnostic_13_patientRF');
            disableID('diagnostic_14_patientRF');
            disableID('diagnostic_9_patientRF');
            disableID('diagnostic_15_patientRF');
            disableID('diagnostic_17_patientRF');
            disableID('diagnostic_18_patientRF');
        }

    } else {
        for (i = 1; i < 18; i++) {
            //enableID('diagnostic_' + i);
            enableID('diagnostic_' + i + '_doctorYes');
            enableID('diagnostic_' + i + '_doctorNo');
            enableID('diagnostic_oth');
            enableID('diagnostic_13_doctorYes');
            enableID('diagnostic_14_doctorYes');
            enableID('diagnostic_9_doctorYes');
            enableID('diagnostic_15_doctorYes');
            enableID('diagnostic_17_doctorYes');
            enableID('diagnostic_18_doctorYes');

            enableID('diagnostic_13_doctorNo');
            enableID('diagnostic_14_doctorNo');
            enableID('diagnostic_9_doctorNo');
            enableID('diagnostic_15_doctorNo');
            enableID('diagnostic_17_doctorNo');
            enableID('diagnostic_18_doctorNo');


            enableID('diagnostic_' + i + '_patientRQ');
            enableID('diagnostic_' + i + '_patientRF');
            enableID('diagnostic_13_patientRQ');
            enableID('diagnostic_14_patientRQ');
            enableID('diagnostic_9_patientRQ');
            enableID('diagnostic_15_patientRQ');
            enableID('diagnostic_17_patientRQ');
            enableID('diagnostic_18_patientRQ');

            enableID('diagnostic_13_patientRF');
            enableID('diagnostic_14_patientRF');
            enableID('diagnostic_9_patientRF');
            enableID('diagnostic_15_patientRF');
            enableID('diagnostic_17_patientRF');
            enableID('diagnostic_18_patientRF');
        }

    }
}

/* Disable Management */
function enableDisableManagement() {
    if (isChecked('management_NA')) {
        for (i = 1; i < 5; i++) {
            disableID('management_' + i);
        }
        disableID('management_oth');
        disableID('management_oth_remarks');
    } else {
        for (i = 1; i < 5; i++) {
            enableID('management_' + i);
        }
        enableID('management_oth');
    }
}


/* SCRIPTS ADDED BY ZIA*/
function validateEmail(emailField){
    var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

    if (reg.test(emailField.value) == false)
    {
        // alert('Invalid Email Address');
        $("#errmsg1").html("Invalid email address").show().fadeOut("slow");
        $("input[id='pHospEmailAdd']").val("");
        return false;
    }
    return true;
}
function validateEmailPx(emailField){
    var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

    if (reg.test(emailField.value) == false)
    {
        // alert('Invalid Email Address');
        $("#errmsg1").html("Invalid email address").show().fadeOut("slow");
        $("input[id='pPatientEmailAdd']").val("");
        return false;
    }
    return true;
}

function loadMunicipality(pProvCode) {
    /*Hospital Registration*/
    $("#pHospAddMun").load("loadMunicipality.php?pProvCode=" + pProvCode);
    document.getElementById("pHospAddBrgy").options.length = 0;
    document.getElementById("pHospZIPCode").options.length = 0;
}

function loadBarangay() {
    /*Hospital Registration*/
    var pProvCodeHosp = $("#pHospAddProv option:selected").val();
    var pMunCodeHosp = $("#pHospAddMun option:selected").val();
    $("#pHospAddBrgy").load("loadBarangay.php?pMunCode=" + pMunCodeHosp + "&pProvCode=" + pProvCodeHosp);
    document.getElementById("pHospZIPCode").value = "";
}

function loadZipCode() {
    /*Hospital Registration*/
    var pProvCodeHosp = $("#pHospAddProv option:selected").val();
    var pMunCodeHosp = $("#pHospAddMun option:selected").val();
    $("#pHospZIPCode").load("loadZipCode.php?pMunCode="+ pMunCodeHosp +"&pProvCode=" + pProvCodeHosp);
}


function loadMunicipalityPx(pProvCode){
    /*Client Registration*/
    $("#pPatientAddMun").load("loadMunicipality.php?pProvCode="+pProvCode);
    document.getElementById("pPatientAddBrgy").options.length = 0;
    document.getElementById("pPatientZIPCode").value = "";
    document.getElementById("pHospZIPCode").value = "";
}
function loadBarangayPx() {
    /*Client Registration*/
    var pProvCode = $("#pPatientAddProv option:selected").val();
    var pMunCode = $("#pPatientAddMun option:selected").val();
    $("#pPatientAddBrgy").load("loadBarangay.php?pMunCode=" + pMunCode+"&pProvCode=" + pProvCode);

    document.getElementById("pPatientZIPCode").value = "";
}

function loadZipCodePx() {
    /*Client Registration*/
    var pProvCode = $("#pPatientAddProv option:selected").val();
    var pMunCode = $("#pPatientAddMun option:selected").val();
    $("#pPatientZIPCode").load("loadZipCode.php?pMunCode="+ pMunCode +"&pProvCode=" + pProvCode);
}

function computeAge(dateString) {
    var today = new Date();
    var birthDate = new Date(dateString);
    var age = today.getFullYear() - birthDate.getFullYear();
    var m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}
/*Load Data to Prescribed Medicine*/
function loadMedsGeneric(pMeds){
    $("#pGeneric").load("loadMedsGeneric.php?pMeds="+pMeds);
}
function loadMedsStrength(pMeds){
    $("#pStrength").load("loadMedsStrength.php?pMeds="+pMeds);
}
function loadMedsForm(pMeds){
    $("#pForm").load("loadMedsForm.php?pMeds="+pMeds);
}
function loadMedsPackage(pMeds){
    $("#pPackage").load("loadMedsPackage.php?pMeds="+pMeds);
}
function loadMedsSalt(pMeds){
    $("#pSalt").load("loadMedsSalt.php?pMeds="+pMeds);
}
function loadMedsUnit(pMeds){
    $("#pUnit").load("loadMedsUnit.php?pMeds="+pMeds);
}
function loadMedsInsStrength(pMeds){
    $("#pStrengthInstruction").load("loadMedsStrength.php?pMeds="+pMeds);
}
function loadMedsCopay(){
        var drugCode = $("#pDrugCode").val();

        if(drugCode != ""){
            $("#pCoPayment").load("loadMedsCopay.php?mDrugCode=" + drugCode);
        }
}
/*End load data to Prescribed Medicine*/

/*Functions fo Lab Results Module*/
function checkHct(value){
    var pSex = $("#pxSex").val();
    var pAgeBracket = $("#pxAgeBracket").val();

    if(pSex == 'M' && pAgeBracket == 'adult'){
        if(value >=39 && value <=54 && value > 0){
            var pValue = 'normal';
        } else if(value < 39 && value > 0){
            var pValue = 'below';
        } else if(value >54 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHct(pValue);
    } else if (pSex == 'F' && pAgeBracket == 'adult'){
        if (value >= 34 && value <= 47 && value > 0) {
            var pValue = 'normal';
        } else if(value < 34 && value > 0){
            var pValue = 'below';
        } else if(value > 47 && value > 0) {
            var pValue = 'above';
        }
        showHideSpanHct(pValue);
    } else if((pSex == 'F' || pSex == 'M') && (pAgeBracket == 'child')){
        if (value >= 30 && value <= 42 && value > 0) {
            var pValue = 'normal';
        } else if(value < 30 && value > 0){
            var pValue = 'below';
        } else if(value > 42 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHct(pValue);
    }
}

function showHideSpanHct(status){
    if (status == 'normal') {
        document.getElementById("normalHct").style.display = '';
        document.getElementById("belowHct").style.display = 'none';
        document.getElementById("aboveHct").style.display = 'none';
    } else if(status == 'above') {
        document.getElementById("aboveHct").style.display = '';
        document.getElementById("normalHct").style.display = 'none';
        document.getElementById("belowHct").style.display = 'none';
    } else if(status == 'below') {
        document.getElementById("normalHct").style.display = 'none';
        document.getElementById("aboveHct").style.display = 'none';
        document.getElementById("belowHct").style.display = '';
    } else{
        document.getElementById("normalHct").style.display = 'none';
        document.getElementById("aboveHct").style.display = 'none';
        document.getElementById("belowHct").style.display = 'none';
    }
}

function checkHgb(value){
    var pSex = $("#pxSex").val();
    var pAgeBracket = $("#pxAgeBracket").val();

    if(pSex == 'M' && pAgeBracket == 'adult'){
        if(value >=14 && value <=18 && value > 0){
            var pValue = 'normal';
        } else if(value < 14 && value > 0){
            var pValue = 'below';
        } else if(value > 18 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    } else if (pSex == 'F' && pAgeBracket == 'adult'){
        if (value >= 11 && value <= 16 && value > 0) {
            var pValue = 'normal';
        } else if(value < 11 && value > 0){
            var pValue = 'below';
        } else if(value > 16 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    } else if((pSex == 'F' || pSex == 'M') && (pAgeBracket == 'child')){
        if (value >= 10 && value <= 14 && value > 0) {
            var pValue = 'normal';
        } else if(value < 30 && value > 0){
            var pValue = 'below';
        } else if(value > 14 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    }else if((pSex == 'F' || pSex == 'M') && (pAgeBracket == 'newborn')){
        if (value >= 15 && value <= 25 && value > 0) {
            var pValue = 'normal';
        } else if(value < 30 && value > 0){
            var pValue = 'below';
        } else if(value > 25 && value > 0){
            var pValue = 'above';
        }
        showHideSpanHgb(pValue);
    }

}

function showHideSpanHgb(status){
    if (status == 'normal') {
        document.getElementById("normalHgb").style.display = '';
        document.getElementById("belowHgb").style.display = 'none';
        document.getElementById("aboveHgb").style.display = 'none';
    } else if(status == 'above') {
        document.getElementById("aboveHgb").style.display = '';
        document.getElementById("normalHgb").style.display = 'none';
        document.getElementById("belowHgb").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalHgb").style.display = 'none';
        document.getElementById("aboveHgb").style.display = 'none';
        document.getElementById("belowHgb").style.display = '';
    } else{
        document.getElementById("normalHgb").style.display = 'none';
        document.getElementById("aboveHgb").style.display = 'none';
        document.getElementById("belowHgb").style.display = 'none';
    }
}

function checkLymphocytes(value){
    if(value >=14 && value <=44 && value > 0){
        var pValue = 'normal';
    } else if(value < 14 && value > 0){
        var pValue = 'below';
    } else if(value > 44 && value > 0){
        var pValue = 'above';
    }
    showHideSpanLymp(pValue);
}

function showHideSpanLymp(status){
    if (status == 'normal'){
        document.getElementById("normalLymp").style.display = '';
        document.getElementById("belowLymp").style.display = 'none';
        document.getElementById("aboveLymp").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveLymp").style.display = '';
        document.getElementById("normalLymp").style.display = 'none';
        document.getElementById("belowLymp").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalLymp").style.display = 'none';
        document.getElementById("aboveLymp").style.display = 'none';
        document.getElementById("belowLymp").style.display = '';
    } else{
        document.getElementById("normalLymp").style.display = 'none';
        document.getElementById("aboveLymp").style.display = 'none';
        document.getElementById("belowLymp").style.display = 'none';
    }
}

function checkMonocytes(value){
    if(value >=2 && value <=6 && value > 0){
        var pValue = 'normal';
    } else if(value < 2 && value > 0){
        var pValue = 'below';
    } else if(value > 6 && value > 0){
        var pValue = 'above';
    }
    showHideSpanMono(pValue);
}

function showHideSpanMono(status){
    if (status == 'normal'){
        document.getElementById("normalMono").style.display = '';
        document.getElementById("belowMono").style.display = 'none';
        document.getElementById("aboveMono").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveMono").style.display = '';
        document.getElementById("normalMono").style.display = 'none';
        document.getElementById("belowMono").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalMono").style.display = 'none';
        document.getElementById("aboveMono").style.display = 'none';
        document.getElementById("belowMono").style.display = '';
    } else{
        document.getElementById("normalMono").style.display = 'none';
        document.getElementById("aboveMono").style.display = 'none';
        document.getElementById("belowMono").style.display = 'none';
    }
}

function checkEosinophils(value){
    if(value >=1 && value <=5 && value > 0){
        var pValue = 'normal';
    } else if(value < 1 && value > 0){
        var pValue = 'below';
    } else if(value > 5 && value > 0){
        var pValue = 'above';
    }
    showHideSpanEosi(pValue);
}
function showHideSpanEosi(status){
    if (status == 'normal'){
        document.getElementById("normalEosi").style.display = '';
        document.getElementById("belowEosi").style.display = 'none';
        document.getElementById("aboveEosi").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveEosi").style.display = '';
        document.getElementById("normalEosi").style.display = 'none';
        document.getElementById("belowEosi").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalEosi").style.display = 'none';
        document.getElementById("aboveEosi").style.display = 'none';
        document.getElementById("belowEosi").style.display = '';
    } else{
        document.getElementById("normalEosi").style.display = 'none';
        document.getElementById("aboveEosi").style.display = 'none';
        document.getElementById("belowEosi").style.display = 'none';
    }
}

function checkUrinalysisPus(value){
    if(value >=0 && value <=3){
        var pValue = 'normal';
    } else if(value > 3 && value > 0){
        var pValue = 'above';
    }
    showHideSpanUrinePus(pValue);
}
function showHideSpanUrinePus(status){
    if (status == 'normal'){
        document.getElementById("normalUrinePus").style.display = '';
        document.getElementById("aboveUrinePus").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveUrinePus").style.display = '';
        document.getElementById("normalUrinePus").style.display = 'none';
    } else{
        document.getElementById("normalUrinePus").style.display = 'none';
        document.getElementById("aboveUrinePus").style.display = 'none';
    }
}

function checkUrineRbc(value){
    if(value >=0 && value <=2){
        var pValue = 'normal';
    } else if(value > 2 && value < 0){
        var pValue = 'above';
    }
    showHideSpanUrineRbc(pValue);
}

function showHideSpanUrineRbc(status){
    if (status == 'normal'){
        document.getElementById("normalUrineRbc").style.display = '';
        document.getElementById("aboveUrineRbc").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveUrineRbc").style.display = '';
        document.getElementById("normalUrineRbc").style.display = 'none';
    } else{
        document.getElementById("normalUrineRbc").style.display = 'none';
        document.getElementById("aboveUrineRbc").style.display = 'none';
    }
}

function checkAlbumin(value){
    if(value >=0 && value <=8){
        var pValue = 'normal';
    } else if(value > 8 && value < 0){
        var pValue = 'above';
    }
    showHideSpanUrineAlb(pValue);
}

function showHideSpanUrineAlb(status){
    if (status == 'normal'){
        document.getElementById("normalUrineAlb").style.display = '';
        document.getElementById("aboveUrineAlb").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("aboveUrineAlb").style.display = '';
        document.getElementById("normalUrineAlb").style.display = 'none';
    } else{
        document.getElementById("normalUrineAlb").style.display = 'none';
        document.getElementById("aboveUrineAlb").style.display = 'none';
    }
}

/*Check value of LDL Cholesterol under Lipid Profile*/
function checkLipidLdl(value){
    if(value >=60 && value <=130){
        var pValue = 'normal';
    } else if(value > 130 && value > 0){
        var pValue = 'above';
    } else if(value < 60 && value > 0){
        var pValue = 'below';
    }
    showHideSpanLipidLdl(pValue);
}

/*Show Hide span notification for Lipid Profile - LDL*/
function showHideSpanLipidLdl(status){
    if (status == 'normal'){
        document.getElementById("normalLdl").style.display = '';
        document.getElementById("aboveLdl").style.display = 'none';
        document.getElementById("belowLdl").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalLdl").style.display = 'none';
        document.getElementById("aboveLdl").style.display = '';
        document.getElementById("belowLdl").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalLdl").style.display = 'none';
        document.getElementById("aboveLdl").style.display = 'none';
        document.getElementById("belowLdl").style.display = '';
    } else{
        document.getElementById("normalLdl").style.display = 'none';
        document.getElementById("aboveLdl").style.display = 'none';
        document.getElementById("belowLdl").style.display = 'none';
    }
}

/*Check value of HDL Cholesterol under Lipid Profile*/
function checkLipidHdl(value){
    if(value == 60){
        var pValue = 'normal';
    } else if(value > 60 && value > 0){
        var pValue = 'above';
    } else if(value < 60 && value > 0){
        var pValue = 'below';
    }
    showHideSpanLipidHdl(pValue);
}

/*Show Hide span notification for Lipid Profile - HDL*/
function showHideSpanLipidHdl(status){
    if (status == 'normal'){
        document.getElementById("normalHdl").style.display = '';
        document.getElementById("aboveHdl").style.display = 'none';
        document.getElementById("belowHdl").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalHdl").style.display = 'none';
        document.getElementById("aboveHdl").style.display = '';
        document.getElementById("belowHdl").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalHdl").style.display = 'none';
        document.getElementById("aboveHdl").style.display = 'none';
        document.getElementById("belowHdl").style.display = '';
    } else{
        document.getElementById("normalHdl").style.display = 'none';
        document.getElementById("aboveHdl").style.display = 'none';
        document.getElementById("belowHdl").style.display = 'none';
    }
}

/*Check value of Cholesterol under Lipid Profile*/
function checkLipidChol(value){
    if(value < 200 && value >= 0){
        var pValue = 'normal';
    } else if(value >= 200 && value > 0){
        var pValue = 'above';
    }
    showHideSpanLipidChol(pValue);
}

/*Show Hide span notification for Lipid Profile - Cholesterol*/
function showHideSpanLipidChol(status){
    if (status == 'normal'){
        document.getElementById("normalChol").style.display = '';
        document.getElementById("aboveChol").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalChol").style.display = 'none';
        document.getElementById("aboveChol").style.display = '';
    } else{
        document.getElementById("normalChol").style.display = 'none';
        document.getElementById("aboveChol").style.display = 'none';
    }
}

/*Check value of Triglycerides under Lipid Profile*/
function checkLipidTrigly(value){
    if(value < 150 && value > 0){
        var pValue = 'normal';
    } else if(value >= 150 && value > 0){
        var pValue = 'above';
    }
    showHideSpanLipidTrigly(pValue);
}

/*Show Hide span notification for Lipid Profile - Triglycerides*/
function showHideSpanLipidTrigly(status){
    if (status == 'normal'){
        document.getElementById("normalTrigly").style.display = '';
        document.getElementById("aboveTrigly").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalTrigly").style.display = 'none';
        document.getElementById("aboveTrigly").style.display = '';
    } else{
        document.getElementById("normalTrigly").style.display = 'none';
        document.getElementById("aboveTrigly").style.display = 'none';
    }
}

/*Check value of Glucose under Lipid Profile*/
function checkLipidGlucose(value){
    if(value >=70 && value <= 100){
        var pValue = 'normal';
    } else if(value > 100 && value > 0){
        var pValue = 'above';
    } else if(value < 70 && value > 0){
        var pValue = 'below';
    }
    showHideSpanLipidGlucose(pValue);
}

/*Show Hide span notification for Lipid Profile - Glucose*/
function showHideSpanLipidGlucose(status){
    if (status == 'normal'){
        document.getElementById("normalGlucose").style.display = '';
        document.getElementById("aboveGlucose").style.display = 'none';
        document.getElementById("belowGlucose").style.display = 'none';
    } else if(status == 'above'){
        document.getElementById("normalGlucose").style.display = 'none';
        document.getElementById("aboveGlucose").style.display = '';
        document.getElementById("belowGlucose").style.display = 'none';
    } else if(status == 'below'){
        document.getElementById("normalGlucose").style.display = 'none';
        document.getElementById("aboveGlucose").style.display = 'none';
        document.getElementById("belowGlucose").style.display = '';
    } else{
        document.getElementById("normalGlucose").style.display = 'none';
        document.getElementById("aboveGlucose").style.display = 'none';
        document.getElementById("belowGlucose").style.display = 'none';
    }
}

/* Checked Observation n if OTHER is selected */
function onChangeObservation(reasonID, remarkID) {
    var reasonVal = getValue(reasonID);
    if (reasonVal == '99') { setDisabled(remarkID, false); setFocus(remarkID); }
    else { setDisabled(remarkID, true); }
}
/* Checked Findings n if OTHER is selected */
function onChangeFindings(reasonID, remarkID) {
    var reasonVal = getValue(reasonID);
    if (reasonVal == '99') { setDisabled(remarkID, false); setFocus(remarkID); }
    else { setDisabled(remarkID, true); }
}

/*Add Observation List - Chest X-ray Results*/
function addXrayObservation() {
    var observation = $("#diagnostic_4_chest_observe");
    var remarks = $("#diagnostic_4_chest_observe_remarks");
    var observationTxt = $("#diagnostic_4_chest_observe option:selected").text();
    var already_in_row = $("#tblChestObservation tr > td:contains('"+observation.val()+"')").length;

    if(observation.val() != "") {
        if(already_in_row == 0) {
            $("#tblChestObservation tr:last").before("<tr> \
                                                             <td style='vertical-align: middle; text-align: left;font-size:11px;font-weight: normal;'><input type='hidden' name='observation[]' value='"+observation.val()+"'>"+observationTxt+"</td> \
                                                             <td style='vertical-align: middle;font-size:11px;font-weight: normal;'><input type='hidden' name='observationRemarks[]' value='"+remarks.val()+"'>"+remarks.val()+"</td> \
                                                             <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                           </tr>");
            observation.val("");
            observation.prop("rows","1");
            remarks.val("");
            observation.focus();
        }
        else {
            alert("Observation already added in list.");
            observation.val("");
            observation.prop("rows","1");
            remarks.val("");
            observation.focus();
        }
    }
    else {
        alert("Please input observation details");
        observation.focus();
    }
}

/*Disable medicine fields*/
function disableMedicine(){
    disableID('radDispenseY');
    disableID('radDispenseN');
    disableID('pDispensedDate');
    disableID('pDrugCode');
    disableID('pGenericFreeText');
    disableID('pGeneric');
    disableID('pSalt');
    disableID('pStrength');
    disableID('pForm');
    disableID('pUnit');
    disableID('pPackage');
    disableID('pQuantity');
    disableID('pUnitPrice');
    disableID('pQtyInstruction');
    disableID('pStrengthInstruction');
    disableID('pFrequencyInstruction');
    disableID('pPrescDoctor');
    disableID('pDispensingPersonnel');
}

function enableMedicine(){
    enableID('radDispenseY');
    enableID('radDispenseN');
    enableID('pDispensedDate');
    enableID('pDrugCode');
    enableID('pGenericFreeText');
    enableID('pGeneric');
    enableID('pSalt');
    enableID('pStrength');
    enableID('pForm');
    enableID('pUnit');
    enableID('pPackage');
    enableID('pQuantity');
    enableID('pUnitPrice');
    enableID('pQtyInstruction');
    enableID('pStrengthInstruction');
    enableID('pFrequencyInstruction');
    enableID('pPrescDoctor');
    enableID('pDispensingPersonnel');
}

/*Add Medicine in the table*/
// function addMedicineFollowups() {
//     var drugCode = $("#pDrugCode");
//     var drugCompleteDesc = $("#pDrugCode option:selected").text();
//     var genCode = $("#pGeneric");
//     var genDesc = $("#pGeneric option:selected").text();
//     var genName = $("#pGenericFreeText");
//     var salt = $("#pSalt");
//     var saltDesc = $("#pSalt option:selected").text();
//     var strength = $("#pStrength");
//     var strengthDesc = $("#pStrength option:selected").text();
//     var form = $("#pForm");
//     var formDesc = $("#pForm option:selected").text();
//     var unit = $("#pUnit");
//     var unitDesc = $("#pUnit option:selected").text();
//     var package = $("#pPackage");
//     var packageDesc = $("#pPackage option:selected").text();
//     var qty = $("#pQuantity");
//     var unitPrice = $("#pUnitPrice");
//     // var coPayment = $("#pCoPayment");
//     var totalPrice = qty.val() * unitPrice.val();
//     var qtyIns = $("#pQtyInstruction");
//     var strengthIns = $("#pStrengthInstruction");
//     var frequency = $("#pFrequencyInstruction");
//     var prescibingDoctor = $("#pPrescDoctor");
//     var already_in_row = $("#tblResultsMeds tr > td:contains('"+genCode.val()+"')").length;

//     //dispense     
//      var dispensedDate = $("#pDispensedDate");
//      var dispensensingPersonnel = $("#pDispensingPersonnel");
//      var chkDispensedY = $("#radDispenseY").is(":checked");
//      var chkDispensedN = $("#radDispenseN").is(":checked");

//      var chkPrescribeYes= $("#medsStatusYes").is(":checked");

//      if(chkDispensedY == true){
//         var dispensedValueResult = "Y";
//         var dispensedResult = "YES";
//      } else if(chkDispensedN == true){
//         var dispensedValueResult = "N";
//         var dispensedResult = "NO";
//      }


//     var count = $('#tblBodyMeds').children('tr').length;

//     if(chkDispensedY == true && dispensedDate.val() == ""){
//         alert("Dispense Date is required if Drug/Medicine is dispensed.");
//     }

//     else if (chkPrescribeYes == true) {       

//             //if(genCode.val() != "" && strength.val() != "" && form.val() != "" && package.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" &&  frequency.val() != "") {          
//             if(drugCode.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" &&  frequency.val() != "") {          

//                 if(already_in_row == 0) {
//                     //Prescribe Medicine
                   
//                     //Dispense Medicine
//                     $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
//                                                             <td><input type='hidden' name='pDrugCodeMeds[]' value='"+drugCode.val()+"'><input type='hidden' name='pGenCodeMeds[]' value='"+genCode.val()+"'><input type='hidden' name='pSaltCodeMeds[]' value='"+salt.val()+"'><input type='hidden' name='pStrengthCodeMeds[]' value='"+strength.val()+"'><input type='hidden' name='pFormCodeMeds[]' value='"+form.val()+"'><input type='hidden' name='pUnitCodeMeds[]' value='"+unit.val()+"'><input type='hidden' name='pPackageCodeMeds[]' value='"+package.val()+"'><input type='hidden' name='pOtherMeds[]' value=''>"+drugCompleteDesc+"</td> \
//                                                              <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
//                                                              <td><input type='hidden' name='pUnitPriceMeds[]' value='"+unitPrice.val()+"'>"+unitPrice.val()+"</td> \
//                                                              <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice+"'>"+totalPrice+"</td> \
//                                                              <td><input type='hidden' name='pQtyInsMeds[]' value='"+qtyIns.val()+"'>"+qtyIns.val()+"</td> \
//                                                              <td><input type='hidden' name='pStrengthInsMeds[]' value='"+strengthIns.val()+"'>"+strengthIns.val()+"</td> \
//                                                              <td><input type='hidden' name='pFrequencyInsMeds[]' value='"+frequency.val()+"'>"+frequency.val()+"</td> \
//                                                              <td><input type='hidden' name='pIsDispensed[]' value='"+dispensedValueResult+"'>"+dispensedResult+"</td> \
//                                                              <td><input type='hidden' name='pDispensedDate[]' value='"+dispensedDate.val()+"'>"+dispensedDate.val()+"</td> \
//                                                              <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
//                                                            </tr>");


//                     drugCode.val("");
//                     genCode.empty();                
//                     qty.val("");
//                     unitPrice.val("");
//                     strength.empty();
//                     form.empty();
//                     package.empty();
//                     qtyIns.val("");
//                     strengthIns.empty();
//                     strengthIns.val("");
//                     frequency.val("");
//                     genName.val("");
//                     salt.empty();
//                     unit.empty();
//                     dispensedDate.val("");
                    
//                 }
//                 else {
//                     alert("Medicine already added in list.");
//                     drugCode.val("");
//                     genCode.empty();
//                     qty.val("");
//                     unitPrice.val("");
//                     strength.empty();
//                     form.empty();
//                     package.empty();
//                     qtyIns.val("");
//                     strengthIns.empty();
//                     strengthIns.val("");
//                     frequency.val("");
//                     genName.val("");
//                     salt.empty();
//                     unit.empty();
//                     dispensedDate.val("");
//                 }
//             }
//             else if(genName.val() != "" && qty.val() != "" && unitPrice.val() != "" && qtyIns.val() != "" && strengthIns.val() != "" && frequency.val() != "") {
                   
//                     //Dispense Medicine
//                     $("#tblResultsMeds tr:last").after("<tr style='background-color: #FBFCC7'> \
//                                                             <td><input type='hidden' name='pDrugCodeMeds[]' value=''><input type='hidden' name='pGenCodeMeds[]' value=''><input type='hidden' name='pSaltCodeMeds[]' value=''><input type='hidden' name='pStrengthCodeMeds[]' value=''><input type='hidden' name='pFormCodeMeds[]' value=''><input type='hidden' name='pUnitCodeMeds[]' value=''><input type='hidden' name='pPackageCodeMeds[]' value=''><input type='hidden' name='pOtherMeds[]' value='"+genName.val()+"'>"+genName.val()+"</td> \
//                                                              <td><input type='hidden' name='pQtyMeds[]' value='"+qty.val()+"'>"+qty.val()+"</td> \
//                                                              <td><input type='hidden' name='pUnitPriceMeds[]' value='"+unitPrice.val()+"'>"+unitPrice.val()+"</td> \
//                                                              <td><input type='hidden' name='pTotalPriceMeds[]' value='"+totalPrice+"'>"+totalPrice+"</td> \
//                                                              <td><input type='hidden' name='pQtyInsMeds[]' value='"+qtyIns.val()+"'>"+qtyIns.val()+"</td> \
//                                                              <td><input type='hidden' name='pStrengthInsMeds[]' value='"+strengthIns.val()+"'>"+strengthIns.val()+"</td> \
//                                                              <td><input type='hidden' name='pFrequencyInsMeds[]' value='"+frequency.val()+"'>"+frequency.val()+"</td> \
//                                                              <td><input type='hidden' name='pIsDispensed[]' value='"+dispensedValueResult+"'>"+dispensedResult+"</td> \
//                                                              <td><input type='hidden' name='pDispensedDate[]' value='"+dispensedDate.val()+"'>"+dispensedDate.val()+"</td> \
//                                                              <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#diagnostic_4_chest_observe\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
//                                                            </tr>");

//                     drugCode.val("");
//                     genCode.empty();
//                     qty.val("");
//                     unitPrice.val("");
//                     strength.empty();
//                     form.empty();
//                     package.empty();
//                     qtyIns.val("");
//                     strengthIns.empty();
//                     strengthIns.val("");
//                     frequency.val("");
//                     genName.val("");
//                     salt.empty();
//                     unit.empty();
//                     dispensedDate.val("");
//             }
//             else {
//                 alert("Please fill up the ff.:\n(1) Prescribing Physician. (2) If dispensed, Dispensed Date.\n(3) Complete Drug Description, Quantity, Actual Unit Price, Instruction: Quantity, Strength and Frequency; \n\nOR if not available in the list, Input the following:\n\n" +
//                     "(1) Prescribing Physician. (2) If dispensed, Dispensed Date.\n(3)Other Drug/Medicine in format of [Generic Name/Salt/Strength/Form/Unit/Package], Quantity, Actual Unit Price, Instruction: Quantity, Strength and Frequency to add on the list of Medicine!");
//                 drugCode.focus();
//             }
        
//     } else {
//         alert("Choose With prescribe drug/medicine button to Add the Medicine on the list.");
//             drugCode.focus();
//     }

// }

/*START HSA MODULE*/
function addOperationHist() {
    var operation = $("#txaMedHistOpHist");
    var op_date = $("#txtMedHistOpDate");
    var already_in_row = $("#tblMedHistOpHist tr > td:contains('"+operation.val()+"') + td:contains('"+op_date.val()+"')").length;

    if(operation.val() != "" && op_date.val() != "") {
        if(already_in_row == 0) {
            $("#tblMedHistOpHist tr:last").before("<tr> \
                                                     <td style='vertical-align: middle; text-align: left;'><input type='hidden' name='operation[]' value='"+operation.val()+"'>"+operation.val()+"</td> \
                                                     <td style='vertical-align: middle;'><input type='hidden' name='operationDate[]' value='"+op_date.val()+"'>"+op_date.val()+"</td> \
                                                     <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#txaMedHistOpHist\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                                   </tr>");
            operation.val("");
            operation.prop("rows","1");
            op_date.val("");
            operation.focus();
        }
        else {
            alert("Operation already added in list.");
            operation.val("");
            operation.prop("rows","1");
            op_date.val("");
            operation.focus();
        }
    }
    else {
        alert("Please input operation details and date of operation.");
        operation.focus();
    }
}

/*CF4 MODULE - ADD LIST IN COURSE IN THE WARD SUB-MODULE*/
function addCourseInTheWard() {
    var docAction = $("#txtWardDocAction");
    var action_date = $("#txtWardDateOrder");
    var already_in_row = $("#tblCourseWard tr > td:contains('"+action_date.val()+"') + td:contains('"+docAction.val()+"')").length;

    if(docAction.val() != "" && action_date.val() != "") {
        if(already_in_row == 0) {
            $("#tblCourseWard tr:last").before("<tr> \
                                                 <td style='vertical-align: middle;'><input type='hidden' name='pDateActionWard[]' value='"+action_date.val()+"'>"+action_date.val()+"</td> \
                                                 <td style='vertical-align: middle; text-align: left;text-transform: uppercase;'><input type='hidden' name='pActionWard[]' value='"+docAction.val()+"'>"+docAction.val()+"</td> \
                                                 <td><button onclick='if(confirm(\"Do you want to remove this item?\")) $(this).closest(\"tr\").remove(); $(\"#txtWardDocAction\").focus();' style='width: 100%' class='btn btn-danger'>Remove</button></td> \
                                               </tr>");
            docAction.val("");
            docAction.prop("rows","1");
            action_date.val("");
            docAction.focus();
        }
        else {
            alert("Action already added in list.");
            docAction.val("");
            docAction.prop("rows","1");
            action_date.val("");
            docAction.focus();
        }
    }
    else {
        alert("Please input Doctor's Action and Date of Action.");
        docAction.focus();
    }
}

function enDisSpecificMedHist(m_disease_code) {
    var checkbox = $("#chkMedHistDiseases_"+m_disease_code);
    var boolChecked = (checkbox.is(":checked")) ? false : true;
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if(m_disease_code == "001") {
        $("#txtMedHistAllergy").attr("disabled",boolChecked);
        $("#txtMedHistAllergy").val("");
    }
    else if (m_disease_code == "003") {
        $("#txtMedHistCancer").attr("disabled",boolChecked);
        $("#txtMedHistCancer").val("");
    }
    else if (m_disease_code == "009") {
        $("#txtMedHistHepatitis").attr("disabled",boolChecked);
        $("#txtMedHistHepatitis").val("");
    }
    else if (m_disease_code == "011") {
        $("#txtMedHistBPDiastolic").attr("disabled",boolChecked);
        $("#txtMedHistBPSystolic").attr("disabled",boolChecked);

        $("#txtMedHistBPDiastolic").val("");
        $("#txtMedHistBPSystolic").val("");
    }
    else if (m_disease_code == "015") {
        $("#txtMedHistPTB").attr("disabled",boolChecked);
        $("#txtMedHistPTB").val("");
    }
    else if (m_disease_code == "016") {
        $("#txtMedHistExPTB").attr("disabled",boolChecked);
        $("#txtMedHistExPTB").val("");
    }
    else if (m_disease_code == "998") {
        $("#txaMedHistOthers").attr("disabled",boolChecked);
        $("#txaMedHistOthers").val("");
    }
    else if (m_disease_code == "999") {
        for(x=1;x<=9;x++){
            $("#chkMedHistDiseases_00"+x).attr("disabled",boolCheckedNone);
        }
        for(x=10;x<=18;x++){
            $("#chkMedHistDiseases_0"+x).attr("disabled",boolCheckedNone);
            $("#chkMedHistDiseases_998").attr("disabled",boolCheckedNone);
        }

        $("#txtMedHistAllergy").attr("disabled",true);
        $("#txtMedHistCancer").attr("disabled",true);
        $("#txtMedHistHepatitis").attr("disabled",true);
        $("#txtMedHistBPDiastolic").attr("disabled",true);
        $("#txtMedHistBPSystolic").attr("disabled",true);
        $("#txtMedHistPTB").attr("disabled",true);
        $("#txtMedHistExPTB").attr("disabled",true);
        $("#txaMedHistOthers").attr("disabled",true);
    }
}

function enDisSpecificFamHist(m_disease_code) {
    var checkbox = $("#chkFamHistDiseases_"+m_disease_code);
    var boolChecked = (checkbox.is(":checked")) ? false : true;
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if(m_disease_code == "001") {
        $("#txtFamHistAllergy").attr("disabled",boolChecked);
        $("#txtFamHistAllergy").val("");
    }
    else if (m_disease_code == "003") {
        $("#txtFamHistCancer").attr("disabled",boolChecked);
        $("#txtFamHistCancer").val("");
    }
    else if (m_disease_code == "009") {
        $("#txtFamHistHepatitis").attr("disabled",boolChecked);
        $("#txtFamHistHepatitis").val("");
    }
    else if (m_disease_code == "011") {
        $("#txtFamHistBPDiastolic").attr("disabled",boolChecked);
        $("#txtFamHistBPSystolic").attr("disabled",boolChecked);

        $("#txtFamHistBPDiastolic").val("");
        $("#txtFamHistBPSystolic").val("");
    }
    else if (m_disease_code == "015") {
        $("#txtFamHistPTB").attr("disabled",boolChecked);
        $("#txtFamHistPTB").val("");
    }
    else if (m_disease_code == "016") {
        $("#txtFamHistExPTB").attr("disabled",boolChecked);
        $("#txtFamHistExPTB").val("");
    }
    else if (m_disease_code == "998") {
        $("#txaFamHistOthers").attr("disabled",boolChecked);
        $("#txaFamHistOthers").val("");
    }

    else if (m_disease_code == "006") {
        if(!boolChecked){  
            // $("#list3_1").addClass("active"); 
            // $("#tab3_1").addClass("active in");
            $("#list3_1").show();
            // $("#tab3_1").show();
        } else {
            $("#list3_1").hide();
            // $("#tab3_1").removeClass("active");
            // $("#list3_1").removeClass("active"); 
        }
    } 

    else if (m_disease_code == "999") {
        for(x=1;x<=9;x++){
            $("#chkFamHistDiseases_00"+x).attr("disabled",boolCheckedNone);
        }
        for(x=10;x<=18;x++){
            $("#chkFamHistDiseases_0"+x).attr("disabled",boolCheckedNone);
            $("#chkFamHistDiseases_998").attr("disabled",boolCheckedNone);
        }

        $("#txtFamHistAllergy").attr("disabled",true);
        $("#txtFamHistCancer").attr("disabled",true);
        $("#txtFamHistHepatitis").attr("disabled",true);
        $("#txtFamHistBPSystolic").attr("disabled",true);
        $("#txtFamHistBPDiastolic").attr("disabled",true);
        $("#txtFamHistPTB").attr("disabled",true);
        $("#txtFamHistExPTB").attr("disabled",true);
        $("#txaFamHistOthers").attr("disabled",true);

    }
}

function enDisImmuneChild(m_immune_code) {
    var checkbox = $("#chkImmChild_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=9;x++){
            $("#chkImmChild_C0"+x).attr("disabled", boolCheckedNone);
        }
        for(x=10;x<=13;x++){
            $("#chkImmChild_C"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function enDisImmuneAdult(m_immune_code) {
    var checkbox = $("#chkImmAdult_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=2;x++){
            $("#chkImmAdult_Y0"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function enDisImmunePreg(m_immune_code) {
    var checkbox = $("#chkImmPregnant_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=2;x++){
            $("#chkImmPregnant_P0"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function enDisImmuneElder(m_immune_code) {
    var checkbox = $("#chkImmElderly_" + m_immune_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (m_immune_code == "999") {
        for(x=1;x<=2;x++){
            $("#chkImmElderly_E0"+x).attr("disabled",boolCheckedNone);
        }
    }
}

function disDigitalRectal(rectal_code) {
    var checkbox = $("#rectal_" + rectal_code);
    var boolCheckedNone = (checkbox.is(":checked")) ? true : false;

    if (rectal_code == "0") {
        for(x=1;x<=5;x++){
            $("#rectal_"+x).attr("disabled",boolCheckedNone);
            $("#rectal_99").attr("disabled",boolCheckedNone);
        }
    }
}

function resizeTextArea() {
    var textarea = $("#txaMedHistOpHist").val();

    if(textarea != "") {
        var rows = textarea.split("\n");
        $("#txaMedHistOpHist").prop("rows",rows.length+1);
    }
    else {
        $("#txaMedHistOpHist").prop("rows","1");
    }
};

function resizeTextAreaCf4() {
    var textarea = $("#txtWardDocAction").val();

    if(textarea != "") {
        var rows = textarea.split("\n");
        $("#txtWardDocAction").prop("rows",rows.length+1);
    }
    else {
        $("#txtWardDocAction").prop("rows","1");
    }
};

function loadMunicipalities(prov_code) {
    $("#optPerHistPobMun").load("loadMunicipality.php?pProvCode=" + prov_code);
}

/*START SAVE HSA TRANSACTION*/
function acceptNumOnly(evt) {
    evt = (evt) ? evt : window.event
    var charCode = (evt.which) ? evt.which : evt.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false
    }
}


function saveHSAFirstEncounterValidation() {
    /*Individual Health Profile*/
    var txtProfileOTP = $("#txtPerHistOTP").val();
    var cntProfileOTP = $("#txtPerHistOTP").val().length;
    var txtProfileDate = $("#txtPerHistProfDate").val();

    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");
   
    /*Start Get date today*/
    var dateToday = new Date();
   
    var compareProfDate = compareDates(dateToday,txtProfileDate);
    /*End Get date today*/

    /*Past Medical History*/
    var chkAllergy = $("#chkMedHistDiseases_001").is(":checked");
    var chkCancer = $("#chkMedHistDiseases_003").is(":checked");
    var chkHepatitis = $("#chkMedHistDiseases_009").is(":checked");
    var chkHypertension = $("#chkMedHistDiseases_011").is(":checked");
    var chkPTB = $("#chkMedHistDiseases_015").is(":checked");
    var chkExPTB = $("#chkMedHistDiseases_016").is(":checked");
    var chkOthers = $("#chkMedHistDiseases_998").is(":checked");

    var txtAllergy = $("#txtMedHistAllergy").val();
    var txtCancer = $("#txtMedHistCancer").val();
    var txtHepatitis = $("#txtMedHistHepatitis").val();
    var txtDiastolic = $("#txtMedHistBPDiastolic").val();
    var txtSystolic = $("#txtMedHistBPSystolic").val();
    var txtPTB = $("#txtMedHistPTB").val();
    var txtExPTB = $("#txtMedHistExPTB").val();
    var txaOthers = $("#txaMedHistOthers").val();

    /*Personal/Social History*/
    var chkFamHistSmokeY = $("#radFamHistSmokeY").is(":checked");
    var chkFamHistSmokeN = $("#radFamHistSmokeN").is(":checked");
    var chkFamHistSmokeX = $("#radFamHistSmokeX").is(":checked");
    var chkFamHistAlcoholY = $("#radFamHistAlcoholY").is(":checked");
    var chkFamHistAlcoholN = $("#radFamHistAlcoholN").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistDrugsY = $("#radFamHistDrugsY").is(":checked");
    var chkFamHistDrugsN = $("#radFamHistDrugsN").is(":checked");
    var chkFamHistSexualHistY = $("#radFamHistSexualHistY").is(":checked");
    var chkFamHistSexualHistN = $("#radFamHistSexualHistN").is(":checked");


    /*OB-Gyne History*/
    /*Menstrual History*/
    var txtMenarche = $("#txtOBHistMenarche").val();
    var txtLastMens = $("#txtOBHistLastMens").val();
    var dateLastMens = new Date(txtLastMens);
    var compareLastMensDate = compareDates(dateToday,dateLastMens);
    var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
    /*Pregnancy History*/
    var txtGravity = $("#txtOBHistGravity").val();
    var txtParity = $("#txtOBHistParity").val();

    var whatSex = $("#txtPerHistPatSex").val();
    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();

    var chkMHdone = $("#mhDone_Y").is(":checked");
    var chkPREGdone = $("#pregDone_Y").is(":checked");


    if(chkWalkedIn == true && (txtProfileOTP == "" || cntProfileOTP < 4)) {
        alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
        $("#txtPerHistOTP").focus();
        return false;
    }  
    else if (txtProfileDate == "") {
        alert("Screening & Assessment Date is required");
        $("#txtPerHistProfDate").focus();
        return false;
    }      
    else if (compareProfDate == "0") {
        alert("Screening & Assessment Date under CLIENT PROFILE menu is invalid! It should be less than or equal to current day.");
        $("#txtPerHistProfDate").focus();
        return false;
    }  

    else if (validateChecksMedsHist() == false){
        alert("Choose at least one Past Medical History in MEDICAL & SURGICAL HISTORY menu");
        return false;
    }
    /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN MEDICAL & SURGICAL HISTORY*/
    else if (chkAllergy == true && txtAllergy == "") {
        alert("Please specify allergy under MEDICAL & SURGICAL HISTORY menu.");
        return false;
    }
    else if(chkCancer == true && txtCancer == "") {
        alert("Please specify organ with cancer under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistCancer").focus();
        return false;
    }
    else if(chkHepatitis == true && txtHepatitis == "") {
        alert("Please specify hepatitis type under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistHepatitis").focus();
        return false;
    }
    else if(chkHypertension == true && (txtSystolic == "" || txtDiastolic == "")) {
        alert("Please specify highest blood pressure under MEDICAL & SURGICAL HISTORY menu.");
        if(txtSystolic == "") {
            $("#txtMedHistBPSystolic").focus();
        }
        else {
            $("#txtMedHistBPDiastolic").focus();
        }
        return false;
    }
    else if(chkPTB == true && txtPTB == "") {
        alert("Please specify Pulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistPTB").focus();
        return false;
    }
    else if(chkExPTB == true && txtExPTB == "") {
        alert("Please specify Extrapulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistExPTB").focus();
        return false;
    }
    else if(chkOthers == true && txaOthers == "") {
        alert("Please specify others under MEDICAL & SURGICAL HISTORY menu.");
        $("#txaMedHistOthers").focus();
        return false;
    }   
    else if(chkFamHistSmokeY == false && chkFamHistSmokeN == false && chkFamHistSmokeX == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistAlcoholY == false && chkFamHistAlcoholN == false && chkFamHistAlcoholX == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistDrugsY == false && chkFamHistDrugsN == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }
    else if(chkFamHistSexualHistY == false && chkFamHistSexualHistN == false) {
        alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
        return false;
    }        
    else if(chkMHdone == true && whatSex == "FEMALE" && (txtMenarche == "" || txtLastMens == "" || txtPeriodDuration == "") && whatAge >= 10){
        alert("Menarche, Last Menstrual Period and Period Duration are REQUIRED if Applicable is selected in MENSTRUAL HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else if(compareLastMensDate == "0"){
        alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkPREGdone == true && whatSex == "FEMALE" && (txtGravity == "" || txtParity == "")){
        alert("Gravity and Parity are REQUIRED if Applicable is selected in PREGNANCY HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else {
        //TO DO SAVING
        return confirm('Save Record?');
    }
} /*END SAVE HSA FIRST ENCOUNTER*/

function saveHSAWithOutValidation() {
    /*Individual Health Profile*/
    var txtProfileOTP = $("#txtPerHistOTP").val();
    var cntProfileOTP = $("#txtPerHistOTP").val().length;
    var txtProfileDate = $("#txtPerHistProfDate").val();

    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");
   
    /*Start Get date today*/
    var dateToday = new Date();
   
    var compareProfDate = compareDates(dateToday,txtProfileDate);
    /*End Get date today*/

    /*Past Medical History*/
    var chkAllergy = $("#chkMedHistDiseases_001").is(":checked");
    var chkCancer = $("#chkMedHistDiseases_003").is(":checked");
    var chkHepatitis = $("#chkMedHistDiseases_009").is(":checked");
    var chkHypertension = $("#chkMedHistDiseases_011").is(":checked");
    var chkPTB = $("#chkMedHistDiseases_015").is(":checked");
    var chkExPTB = $("#chkMedHistDiseases_016").is(":checked");
    var chkOthers = $("#chkMedHistDiseases_998").is(":checked");

    var txtAllergy = $("#txtMedHistAllergy").val();
    var txtCancer = $("#txtMedHistCancer").val();
    var txtHepatitis = $("#txtMedHistHepatitis").val();
    var txtDiastolic = $("#txtMedHistBPDiastolic").val();
    var txtSystolic = $("#txtMedHistBPSystolic").val();
    var txtPTB = $("#txtMedHistPTB").val();
    var txtExPTB = $("#txtMedHistExPTB").val();
    var txaOthers = $("#txaMedHistOthers").val();

    /*Personal/Social History*/
    var chkFamHistSmokeY = $("#radFamHistSmokeY").is(":checked");
    var chkFamHistSmokeN = $("#radFamHistSmokeN").is(":checked");
    var chkFamHistSmokeX = $("#radFamHistSmokeX").is(":checked");
    var chkFamHistAlcoholY = $("#radFamHistAlcoholY").is(":checked");
    var chkFamHistAlcoholN = $("#radFamHistAlcoholN").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistAlcoholX = $("#radFamHistAlcoholX").is(":checked");
    var chkFamHistDrugsY = $("#radFamHistDrugsY").is(":checked");
    var chkFamHistDrugsN = $("#radFamHistDrugsN").is(":checked");
    var chkFamHistSexualHistY = $("#radFamHistSexualHistY").is(":checked");
    var chkFamHistSexualHistN = $("#radFamHistSexualHistN").is(":checked");


    /*OB-Gyne History*/
    /*Menstrual History*/
    var txtMenarche = $("#txtOBHistMenarche").val();
    var txtLastMens = $("#txtOBHistLastMens").val();
    var dateLastMens = new Date(txtLastMens);
    var compareLastMensDate = compareDates(dateToday,dateLastMens);
    var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
    /*Pregnancy History*/
    var txtGravity = $("#txtOBHistGravity").val();
    var txtParity = $("#txtOBHistParity").val();

    var whatSex = $("#txtPerHistPatSex").val();
    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();

    var chkMHdone = $("#mhDone_Y").is(":checked");
    var chkPREGdone = $("#pregDone_Y").is(":checked");


    if(chkWalkedIn == true && (txtProfileOTP == "" || cntProfileOTP < 4)) {
        alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
        // $("#txtPerHistOTP").focus();
        return false;
    }  
    else if (txtProfileDate == "") {
        alert("Screening & Assessment Date is required");
        // $("#txtPerHistProfDate").focus();
        return false;
    }      
    else if (compareProfDate == "0") {
        alert("Screening & Assessment Date under CLIENT PROFILE menu is invalid! It should be less than or equal to current day.");
        // $("#txtPerHistProfDate").focus();
        return false;
    }  
    /*CHECK IF TEXTBOXES HAS VALUE IF CHECKED IN MEDICAL & SURGICAL HISTORY*/
    else if (chkAllergy == true && txtAllergy == "") {
        alert("Please specify allergy under MEDICAL & SURGICAL HISTORY menu.");
        return false;
    }
    else if(chkCancer == true && txtCancer == "") {
        alert("Please specify organ with cancer under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistCancer").focus();
        return false;
    }
    else if(chkHepatitis == true && txtHepatitis == "") {
        alert("Please specify hepatitis type under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistHepatitis").focus();
        return false;
    }
    else if(chkHypertension == true && (txtSystolic == "" || txtDiastolic == "")) {
        alert("Please specify highest blood pressure under MEDICAL & SURGICAL HISTORY menu.");
        if(txtSystolic == "") {
            $("#txtMedHistBPSystolic").focus();
        }
        else {
            $("#txtMedHistBPDiastolic").focus();
        }
        return false;
    }
    else if(chkPTB == true && txtPTB == "") {
        alert("Please specify Pulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistPTB").focus();
        return false;
    }
    else if(chkExPTB == true && txtExPTB == "") {
        alert("Please specify Extrapulmonary Tuberculosis category under MEDICAL & SURGICAL HISTORY menu.");
        $("#txtMedHistExPTB").focus();
        return false;
    }
    else if(chkOthers == true && txaOthers == "") {
        alert("Please specify others under MEDICAL & SURGICAL HISTORY menu.");
        $("#txaMedHistOthers").focus();
        return false;
    }   
    // else if(chkFamHistSmokeY == false && chkFamHistSmokeN == false && chkFamHistSmokeX == false) {
    //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
    //     return false;
    // }
    // else if(chkFamHistAlcoholY == false && chkFamHistAlcoholN == false && chkFamHistAlcoholX == false) {
    //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
    //     return false;
    // }
    // else if(chkFamHistDrugsY == false && chkFamHistDrugsN == false) {
    //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
    //     return false;
    // }
    // else if(chkFamHistSexualHistY == false && chkFamHistSexualHistN == false) {
    //     alert("Fill up all the required fields of Personal/Social History in FAMILY & PERSONAL HISTORY menu.");
    //     return false;
    // }        
    else if(chkMHdone == true && whatSex == "FEMALE" && (txtMenarche == "" || txtLastMens == "" || txtPeriodDuration == "") && whatAge >= 10){
        alert("Menarche, Last Menstrual Period and Period Duration are REQUIRED if Applicable is selected in MENSTRUAL HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else if(compareLastMensDate == "0"){
        alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkPREGdone == true && whatSex == "FEMALE" && (txtGravity == "" || txtParity == "")){
        alert("Gravity and Parity are REQUIRED if Applicable is selected in PREGNANCY HISTORY under OB-GYNE HISTORY menu!");
        return false;
    }
    else {
        //TO DO SAVING
        return confirm('Would you like to save the record and finalize later?');
    }
} /*END SAVE HSA FIRST ENCOUNTER WOUT COMPLETE VALIDATION*/

function validateSoapForm() {
    var pSoapOTP = $("#pSoapOTP").val();
    var cntSoapOTP = $("#pSoapOTP").val().length;
    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");

    var pSoapCoPayment = $("#pCoPayment").val();
    var pSoapdate = $("#pSOAPDate").val();
    var pChiefComplaint = $("#pChiefComplaint").text();
    // var pIcd = $("#pICD").val();
    var chksDiagnosis = $('input[name="diagnosis[]"]').val();

    /*Start Get date today*/
    var dateToday = new Date();
    var dateSoapDate = new Date(pSoapdate);
    var compareSoapDate = compareDates(dateToday,dateSoapDate);
    /*End Get date today*/

    /*Objective/Physical Examination*/
    var txtPhExSystolic = $("#pe_bp_u").val();
    var txtPhExBPDiastolic = $("#pe_bp_l").val();
    var txtPhExHeartRate = $("#pe_hr").val();
    var txtPhExRespiratoryRate = $("#pe_rr").val();
    var txtPhExHeightCm = $("#txtPhExHeightCm").val();
    var txtPhExWeightKg = $("#txtPhExWeightKg").val();
    var txtPhExTemp = $("#pe_temp").val();
    var txtPhEXBMIResult = $("#txtPhExBMI").val();    

    /*Subjective*/
    var chkPainSite = $("#symptom_38").is(":checked"); //with pain
    var checkOtherChiefComplaint = $("#symptom_X").is(":checked"); //with other complaint
    var txtPainSite = $("#pPainSite").val();    
    var txtOtherChiefComplaint = $("#pOtherChiefComplaint").val();    


    /*Labs*/
    // var chkCBCdone = $("#diagnostic_1_done").is(":checked"); //done
    // var chkUrinalysisDone = $("#diagnostic_2_done").is(":checked");//done
    // var chkFecalysisDone = $("#diagnostic_3_done").is(":checked");//done
    // var chkXrayDone = $("#diagnostic_4_done").is(":checked");//done
    // var chkSputumDone = $("#diagnostic_5_done").is(":checked");//done
    // var chkLipidDone = $("#diagnostic_6_done").is(":checked");//done
    // var chkECGDone = $("#diagnostic_9_done").is(":checked");//done
    // var chkPapsSmearDone = $("#diagnostic_13_done").is(":checked");//done
    // var chkOGTTDone = $("#diagnostic_14_done").is(":checked");//done
    // var chkFbsDone = $("#diagnostic_7_done").is(":checked");//done
    // var chkRbsDone = $("#diagnostic_19_done").is(":checked");//done

    /*CBC*/
    // var txtCbcLabDate = $("#diagnostic_1_lab_exam_date").val();
    // var dateCbcDate = new Date(txtCbcLabDate);
    // var compareCbcDate = compareDates(dateToday,dateCbcDate);
    // var txtCbcLabFee = $("#diagnostic_1_lab_fee").val();
    // var txtCbcHema = $("#diagnostic_1_hematocrit").val();
    // var txtCbchemo = $("#diagnostic_1_hemoglobin_gdL").val();
    // var txtCbcMhc = $("#diagnostic_1_mhc_pgcell").val();
    // var txtCbcMchc= $("#diagnostic_1_mchc_gHbdL").val();
    // var txtCbcMcv = $("#diagnostic_1_mcv_um").val();
    // var txtCbcWbc = $("#diagnostic_1_wbc_cellsmmuL").val();
    // var txtCbcMyelocyte = $("#diagnostic_1_myelocyte").val();
    // var txtCbcNeutroBand = $("#diagnostic_1_neutrophils_bands").val();
    // var txtCbcNeutroSeg = $("#diagnostic_1_neutrophils_segmenters").val();
    // var txtCbcLymph = $("#diagnostic_1_lymphocytes").val();
    // var txtCbcMono = $("#diagnostic_1_monocytes").val();
    // var txtCbcEosi = $("#diagnostic_1_eosinophils").val();
    // var txtCbcBaso = $("#diagnostic_1_basophils").val();
    // var txtCbcPlatelet = $("#diagnostic_1_platelet").val();

    /*PAPS SMEAR*/
    // var txtPapsLabDate = $("#diagnostic_13_lab_exam_date").val();
    // var datePaps = new Date(txtPapsLabDate);
    // var comparePapsDate = compareDates(dateToday,datePaps);
    // var txtPapsLabFee = $("#diagnostic_13_lab_fee").val();
    // var txtPapsFind = $("#diagnostic_13_papsSmearFindings").val();
    // var txtPapsImpre = $("#diagnostic_13_papsSmearImpression").val();

    /*OGTT*/
    // var txtOgttLabDate = $("#diagnostic_14_lab_exam_date").val();
    // var dateOgtt = new Date(txtOgttLabDate);
    // var compareOgttDate = compareDates(dateToday,dateOgtt);
    // var txtOgttLabFee = $("#diagnostic_14_lab_fee").val();
    // var txtOgttFastMg = $("#diagnostic_14_fasting_mg").val();
    // var txtOgttFastMmol = $("#diagnostic_14_fasting_mmol").val();
    // var txtOgttOneMg = $("#diagnostic_14_oneHr_mg").val();
    // var txtOgttOneMmol = $("#diagnostic_14_oneHr_mmol").val();
    // var txtOgttTwoMg = $("#diagnostic_14_twoHr_mg").val();
    // var txtOgttTwoMmol = $("#diagnostic_14_twoHr_mmol").val();

    /*URINALYSIS*/
    // var txtUrineLabDate = $("#diagnostic_2_lab_exam_date").val();
    // var dateUrine = new Date(txtUrineLabDate);
    // var compareUrineDate = compareDates(dateToday,dateUrine);
    // var txtUrineLabFee = $("#diagnostic_2_lab_fee").val();
    // var txtUrineSg = $("#diagnostic_2_sg").val();
    // var txtUrineAppear = $("#diagnostic_2_appearance").val();
    // var txtUrineColor = $("#diagnostic_2_color").val();
    // var txtUrineGlucose = $("#diagnostic_2_glucose").val();
    // var txtUrineProtein = $("#diagnostic_2_proteins").val();
    // var txtUrineKetones = $("#diagnostic_2_ketones").val();
    // var txtUrinePh = $("#diagnostic_2_pH").val();
    // var txtUrinePus = $("#diagnostic_2_pus").val();
    // var txtUrineAlb = $("#diagnostic_2_alb").val();
    // var txtUrineRbc = $("#diagnostic_2_rbc").val();
    // var txtUrineWbc = $("#diagnostic_2_wbc").val();
    // var txtUrineBact = $("#diagnostic_2_bacteria").val();
    // var txtUrineCryst = $("#diagnostic_2_crystals").val();
    // var txtUrineBlad = $("#diagnostic_2_bladder_cells").val();
    // var txtUrineSqCell= $("#diagnostic_2_squamous_cells").val();
    // var txtUrineTubCell = $("#diagnostic_2_tubular_cells").val();
    // var txtUrineBrCast = $("#diagnostic_2_broad_casts").val();
    // var txtUrineCellCast = $("#diagnostic_2_epithelial_cell_casts").val();
    // var txtUrineGranCast = $("#diagnostic_2_granular_casts").val();
    // var txtUrineHyaCast = $("#diagnostic_2_hyaline_casts").val();
    // var txtUrineRbcCast = $("#diagnostic_2_rbc_casts").val();
    // var txtUrineWaxyCast = $("#diagnostic_2_waxy_casts").val();
    // var txtUrineWcCast = $("#diagnostic_2_wc_casts").val();

    /*FECALYSIS*/
    // var txtFecaLabDate = $("#diagnostic_3_lab_exam_date").val();
    // var dateFeca = new Date(txtFecaLabDate);
    // var compareFecaDate = compareDates(dateToday,dateFeca);
    // var txtFecaLabFee = $("#diagnostic_3_lab_fee").val();
    // var txtFecaPus = $("#diagnostic_3_pus").val();
    // var txtFecaRbc = $("#diagnostic_3_rbc").val();
    // var txtFecaWbc = $("#diagnostic_3_wbc").val();
    // var txtFecaOva = $("#diagnostic_3_ova").val();
    // var txtFecaPara = $("#diagnostic_3_parasite").val();
    // var txtFecaOccult = $("#diagnostic_3_occult_blood").val();

    /*CHEST X-RAY*/
    // var txtXrayLabDate = $("#diagnostic_4_lab_exam_date").val();
    // var dateXray = new Date(txtXrayLabDate);
    // var compareXrayDate = compareDates(dateToday,dateXray);
    // var txtXrayLabFee = $("#diagnostic_4_lab_fee").val();
    // var txtXrayFindings = $("#diagnostic_4_chest_findings option:selected").val();

    /*SPUTUM MICROSCOPY*/
    // var txtSputumLabDate = $("#diagnostic_5_lab_exam_date").val();
    // var dateSputum = new Date(txtSputumLabDate);
    // var compareSputumDate = compareDates(dateToday,dateSputum);
    // var txtSputumLabFee = $("#diagnostic_5_lab_fee").val();
    // var txtSputumPlusses = $("#diagnostic_5_plusses").val();

    /*LIPID PROFILE*/
    // var txtLipidLabDate = $("#diagnostic_6_lab_exam_date").val();
    // var dateLipid = new Date(txtLipidLabDate);
    // var compareLipidDate = compareDates(dateToday,dateLipid);
    // var txtLipidLabFee = $("#diagnostic_6_lab_fee").val();
    // var txtLipidTotal = $("#diagnostic_6_total").val();
    // var txtLipidLdl = $("#diagnostic_6_ldl").val();
    // var txtLipidHdl = $("#diagnostic_6_hdl").val();
    // var txtLipidChol = $("#diagnostic_6_cholesterol").val();
    // var txtLipidTrigy = $("#diagnostic_6_triglycerides").val();

    /*ECG*/
    // var txtEcgLabDate = $("#diagnostic_9_lab_exam_date").val();
    // var dateEcg = new Date(txtEcgLabDate);
    // var compareEcgDate = compareDates(dateToday,dateEcg);
    // var txtEcgLabFee = $("#diagnostic_9_lab_fee").val();
    // var chkEcgNormal = $("#diagnostic_9_no").is(":checked");
    // var chkEcgNotnNormal = $("#diagnostic_9_yes").is(":checked");
    // var remEcgFindings = $("#diagnostic_9_ecg_remarks").val();

    /*START CONSULTATION ONLY*/
    /*FBS*/
    // var txtFbsLabDate = $("#diagnostic_7_lab_exam_date").val();
    // var dateFbs = new Date(txtFbsLabDate);
    // var compareFbsDate = compareDates(dateToday,dateFbs);
    // var txtFbsLabFee = $("#diagnostic_7_lab_fee").val();
    // var txtFbsGlucoseMgdl = $("#diagnostic_7_glucose_mgdL").val();
    // var txtFbsGlucoseMmol = $("#diagnostic_7_glucose_mmolL").val();

    /*RBS*/
    // var txtRbsLabDate = $("#diagnostic_19_lab_exam_date").val();
    // var dateRbs = new Date(txtRbsLabDate);
    // var compareRbsDate = compareDates(dateToday,dateRbs);
    // var txtRbsLabFee = $("#diagnostic_19_lab_fee").val();
    // var txtRbsGlucoseMgdl = $("#diagnostic_19_glucose_mgdL").val();
    // var txtRbsGlucoseMmol = $("#diagnostic_19_glucose_mmolL").val();

    /*END CONSULTATION ONLY*/

    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();

    if(chkWalkedIn == true && (pSoapOTP == "" || cntSoapOTP < 4)) {
        alert("Authorization Transaction Code is required.");
        $("#pSoapOTP").focus();
        return false;
    }  
    else if (pSoapCoPayment == "") {
        alert("Co-payment is required.");
        $("#pCoPayment").focus();
        return false;
    }
    else if (pSoapdate == "") {
        alert("Consultation Date is required");
        $("#pSOAPDate").focus();
        return false;
    }        
    else if (compareSoapDate == "0") {
        alert("Consultation Date is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(validateChecksChiefComplaint() == false){
        alert("Chief Complaint is required. Choose at least one CHIEF COMPLAINT in SUBJECTIVE/HISTORY OF ILLNESS");
        return false;
    }
    else if(chkPainSite == true && txtPainSite == ""){
        alert("Pain Site is required");
        return false;
    }
    else if(checkOtherChiefComplaint == true && txtOtherChiefComplaint == ""){
        alert("Other Chief Complaint is required");
        return false;
    }
    else if(chksDiagnosis == "" || chksDiagnosis == null){
        alert("Fill up all the required fields in ASSESSMENT/DIAGNOSIS");
        return false;
    }
    else if(txtPhExSystolic == "" && txtPhExBPDiastolic == "" && txtPhExHeartRate == "" && txtPhExRespiratoryRate == "" && txtPhExHeightCm == "" && txtPhExWeightKg == "" && txtPhExTemp == ""){
        alert("Fill up all the required fields in OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }

    else if(whatAge > 4 && txtPhEXBMIResult == ""){
        alert("BMI is required. Please fill up all required fields in PERTINENT PHYSICAL EXAMINATION FINDINGS!");
        return false;
    }
    else if(checkAge0to24() == true){
        //v1.2
        alert("Please fill up all required fields for 0-24 months old in PERTINENT PHYSICAL EXAMINATION FINDINGS.");
        return false;
    }
    else if(validateChecksHeent() == false){
        alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksChest() == false){
        alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksHeart() == false){
        alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksAbdomen() == false){
        alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksGenitoUrinary() == false){
        alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksRectal() == false){
        alert("Choose at least one DIGITAL RECTAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksSkin() == false){
        alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksNeuro() == false){
        alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of OBJECTIVE/PHYSICAL EXAMINATION");
        return false;
    }
    else if(validateChecksPlan() == false){            
            alert("Fill up all the required fields in PLAN/MANAGEMENT");
            return false;
    }
    // else if(chkCBCdone == true && txtCbcLabDate == "" && txtCbcLabFee == "" && txtCbcHema == "" && txtCbchemo == "" && txtCbcMhc == "" && txtCbcMchc == "" && txtCbcMcv == "" && txtCbcWbc == "" && txtCbcMyelocyte == "" && txtCbcNeutroBand == "" && txtCbcNeutroSeg == "" && txtCbcLymph == "" && txtCbcMono == "" && txtCbcEosi == "" && txtCbcBaso == "" && txtCbcPlatelet == ""){
    //     alert("Fill up all fields of CBC in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareCbcDate == "0"){
    //     alert("Laboratory Date of CBC is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkPapsSmearDone == true && txtPapsLabDate == "" && txtPapsLabFee == "" && txtPapsFind == "" && txtPapsImpre == ""){
    //     alert("Fill up all fields of Paps Smear in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(comparePapsDate == "0"){
    //     alert("Laboratory Date of Paps Smear is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkOGTTDone == true && txtOgttLabDate == "" && txtOgttLabFee == "" && txtOgttFastMg == "" && txtOgttFastMmol == "" && txtOgttOneMg == "" && txtOgttOneMmol == "" && txtOgttTwoMg == ""  && txtOgttTwoMmol == ""){
    //     alert("Fill up all fields of Oral Glucose Tolerance Test (OGTT) in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareOgttDate == "0"){
    //     alert("Laboratory Date of OGTT is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkUrinalysisDone == true && txtUrineLabDate == "" && txtUrineLabFee == "" && txtUrineSg == "" && txtUrineAppear == "" && txtUrineColor == "" && txtUrineGlucose == "" && txtUrineProtein == "" &&
    //     txtUrineKetones == "" && txtUrinePh == "" && txtUrinePus == "" && txtUrineAlb == "" && txtUrineRbc == "" && txtUrineWbc == "" && txtUrineBact == "" && txtUrineCryst == "" && txtUrineBlad == "" &&
    //     txtUrineSqCell == "" && txtUrineTubCell == "" && txtUrineBrCast == "" && txtUrineCellCast == "" && txtUrineGranCast == "" && txtUrineHyaCast == "" && txtUrineRbcCast == "" && txtUrineWaxyCast == "" && txtUrineWcCast == ""){
    //     alert("Fill up all fields of Urinalysis in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareUrineDate == "0"){
    //     alert("Laboratory Date of Urinalysis is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkFecalysisDone == true && txtFecaLabDate == "" && txtFecaLabFee == "" && txtFecaPus == "" && txtFecaRbc == "" && txtFecaWbc == "" && txtFecaOva == "" && txtFecaPara == "" && txtFecaOccult == ""){
    //     alert("Fill up all fields of Fecalysis in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareFecaDate == "0"){
    //     alert("Laboratory Date of Fecalysis is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkXrayDone == true && txtXrayLabDate == "" && txtXrayLabFee == "" && txtXrayFindings == ""){
    //     alert("Fill up all fields of Chest X-ray in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareXrayDate == "0"){
    //     alert("Laboratory Date of Chest X-ray is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkSputumDone == true && txtSputumLabDate == "" && txtSputumLabFee == "" && txtSputumPlusses == ""){
    //     alert("Fill up all fields of Sputum Microscopy in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareSputumDate == "0"){
    //     alert("Laboratory Date of Sputum Microscopy is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkLipidDone == true && txtLipidLabDate == "" && txtLipidLabFee == "" && txtLipidTotal == "" && txtLipidLdl == "" && txtLipidHdl == "" && txtLipidChol == "" && txtLipidTrigy == ""){
    //     alert("Fill up all fields of Lipid Profile in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareLipidDate == "0"){
    //     alert("Laboratory Date of Lipid Profile is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkECGDone == true && txtEcgLabDate == "" && txtEcgLabFee == "" && chkEcgNormal == false && chkEcgNotnNormal == false){
    //     alert("Fill up all fields of Electrocardiogram (ECG) in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareEcgDate == "0"){
    //     alert("Laboratory Date of ECG is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkFbsDone == true && txtFbsLabDate == "" && txtFbsLabFee == "" && txtFbsGlucoseMgdl == "" && txtFbsGlucoseMmol == ""){
    //     alert("Fill up all fields of Fasting Blood Sugar (FBS) in LABORATORY RESULTS!");
    //     return false;
    // }
    // else if(compareFbsDate == "0"){
    //     alert("Laboratory Date of FBS is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    // else if(chkRbsDone == true && txtRbsLabDate == "" && txtRbsLabFee == "" && txtRbsGlucoseMgdl == "" && txtRbsGlucoseMmol == ""){
    //     alert("Fill up all fields of Random Blood Sugar (RBS) in LABORATORY RESULTS!");
    //     return false;
    // }     
    // else if(compareRbsDate == "0"){
    //     alert("Laboratory Date of RBS is invalid! It should be less than or equal to current day.");
    //     return false;
    // }
    else{
        return confirm('Are all information encoded correctly? Click OK to Submit now.');
    }
}

function validateLabResultsForm() {
    var pSoapOTP = $("#pSoapOTP").val();
    var cntSoapOTP = $("#pSoapOTP").val().length;
    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");

    var pSoapCoPayment = $("#pCoPayment").val();
    var pSoapdate = $("#pSOAPDate").val();

    /*Start Get date today*/
    var dateToday = new Date();
    var dateSoapDate = new Date(pSoapdate);
    var compareSoapDate = compareDates(dateToday,dateSoapDate);
    /*End Get date today*/

    /*Labs*/
    var chkCBCdone = $("#diagnostic_1_done").is(":checked"); //done
    var chkUrinalysisDone = $("#diagnostic_2_done").is(":checked");//done
    var chkFecalysisDone = $("#diagnostic_3_done").is(":checked");//done
    var chkXrayDone = $("#diagnostic_4_done").is(":checked");//done
    var chkSputumDone = $("#diagnostic_5_done").is(":checked");//done
    var chkLipidDone = $("#diagnostic_6_done").is(":checked");//done
    var chkECGDone = $("#diagnostic_9_done").is(":checked");//done
    var chkPapsSmearDone = $("#diagnostic_13_done").is(":checked");//done
    var chkOGTTDone = $("#diagnostic_14_done").is(":checked");//done
    var chkFbsDone = $("#diagnostic_7_done").is(":checked");//done
    var chkRbsDone = $("#diagnostic_19_done").is(":checked");//done

    /*CBC*/
    var txtCbcLabDate = $("#diagnostic_1_lab_exam_date").val();
    var dateCbcDate = new Date(txtCbcLabDate);
    var compareCbcDate = compareDates(dateToday,dateCbcDate);
    var txtCbcLabFee = $("#diagnostic_1_lab_fee").val();
    var txtCbcHema = $("#diagnostic_1_hematocrit").val();
    var txtCbchemo = $("#diagnostic_1_hemoglobin_gdL").val();
    var txtCbcMhc = $("#diagnostic_1_mhc_pgcell").val();
    var txtCbcMchc= $("#diagnostic_1_mchc_gHbdL").val();
    var txtCbcMcv = $("#diagnostic_1_mcv_um").val();
    var txtCbcWbc = $("#diagnostic_1_wbc_cellsmmuL").val();
    var txtCbcMyelocyte = $("#diagnostic_1_myelocyte").val();
    var txtCbcNeutroBand = $("#diagnostic_1_neutrophils_bands").val();
    var txtCbcNeutroSeg = $("#diagnostic_1_neutrophils_segmenters").val();
    var txtCbcLymph = $("#diagnostic_1_lymphocytes").val();
    var txtCbcMono = $("#diagnostic_1_monocytes").val();
    var txtCbcEosi = $("#diagnostic_1_eosinophils").val();
    var txtCbcBaso = $("#diagnostic_1_basophils").val();
    var txtCbcPlatelet = $("#diagnostic_1_platelet").val();

    /*PAPS SMEAR*/
    var txtPapsLabDate = $("#diagnostic_13_lab_exam_date").val();
    var datePaps = new Date(txtPapsLabDate);
    var comparePapsDate = compareDates(dateToday,datePaps);
    var txtPapsLabFee = $("#diagnostic_13_lab_fee").val();
    var txtPapsFind = $("#diagnostic_13_papsSmearFindings").val();
    var txtPapsImpre = $("#diagnostic_13_papsSmearImpression").val();

    /*OGTT*/
    var txtOgttLabDate = $("#diagnostic_14_lab_exam_date").val();
    var dateOgtt = new Date(txtOgttLabDate);
    var compareOgttDate = compareDates(dateToday,dateOgtt);
    var txtOgttLabFee = $("#diagnostic_14_lab_fee").val();
    var txtOgttFastMg = $("#diagnostic_14_fasting_mg").val();
    var txtOgttFastMmol = $("#diagnostic_14_fasting_mmol").val();
    var txtOgttOneMg = $("#diagnostic_14_oneHr_mg").val();
    var txtOgttOneMmol = $("#diagnostic_14_oneHr_mmol").val();
    var txtOgttTwoMg = $("#diagnostic_14_twoHr_mg").val();
    var txtOgttTwoMmol = $("#diagnostic_14_twoHr_mmol").val();

    /*URINALYSIS*/
    var txtUrineLabDate = $("#diagnostic_2_lab_exam_date").val();
    var dateUrine = new Date(txtUrineLabDate);
    var compareUrineDate = compareDates(dateToday,dateUrine);
    var txtUrineLabFee = $("#diagnostic_2_lab_fee").val();
    var txtUrineSg = $("#diagnostic_2_sg").val();
    var txtUrineAppear = $("#diagnostic_2_appearance").val();
    var txtUrineColor = $("#diagnostic_2_color").val();
    var txtUrineGlucose = $("#diagnostic_2_glucose").val();
    var txtUrineProtein = $("#diagnostic_2_proteins").val();
    var txtUrineKetones = $("#diagnostic_2_ketones").val();
    var txtUrinePh = $("#diagnostic_2_pH").val();
    var txtUrinePus = $("#diagnostic_2_pus").val();
    var txtUrineAlb = $("#diagnostic_2_alb").val();
    var txtUrineRbc = $("#diagnostic_2_rbc").val();
    var txtUrineWbc = $("#diagnostic_2_wbc").val();
    var txtUrineBact = $("#diagnostic_2_bacteria").val();
    var txtUrineCryst = $("#diagnostic_2_crystals").val();
    var txtUrineBlad = $("#diagnostic_2_bladder_cells").val();
    var txtUrineSqCell= $("#diagnostic_2_squamous_cells").val();
    var txtUrineTubCell = $("#diagnostic_2_tubular_cells").val();
    var txtUrineBrCast = $("#diagnostic_2_broad_casts").val();
    var txtUrineCellCast = $("#diagnostic_2_epithelial_cell_casts").val();
    var txtUrineGranCast = $("#diagnostic_2_granular_casts").val();
    var txtUrineHyaCast = $("#diagnostic_2_hyaline_casts").val();
    var txtUrineRbcCast = $("#diagnostic_2_rbc_casts").val();
    var txtUrineWaxyCast = $("#diagnostic_2_waxy_casts").val();
    var txtUrineWcCast = $("#diagnostic_2_wc_casts").val();

    /*FECALYSIS*/
    var txtFecaLabDate = $("#diagnostic_3_lab_exam_date").val();
    var dateFeca = new Date(txtFecaLabDate);
    var compareFecaDate = compareDates(dateToday,dateFeca);
    var txtFecaLabFee = $("#diagnostic_3_lab_fee").val();
    var txtFecaPus = $("#diagnostic_3_pus").val();
    var txtFecaRbc = $("#diagnostic_3_rbc").val();
    var txtFecaWbc = $("#diagnostic_3_wbc").val();
    var txtFecaOva = $("#diagnostic_3_ova").val();
    var txtFecaPara = $("#diagnostic_3_parasite").val();
    var txtFecaOccult = $("#diagnostic_3_occult_blood").val();

    /*CHEST X-RAY*/
    var txtXrayLabDate = $("#diagnostic_4_lab_exam_date").val();
    var dateXray = new Date(txtXrayLabDate);
    var compareXrayDate = compareDates(dateToday,dateXray);
    var txtXrayLabFee = $("#diagnostic_4_lab_fee").val();
    var txtXrayFindings = $("#diagnostic_4_chest_findings option:selected").val();

    /*SPUTUM MICROSCOPY*/
    var txtSputumLabDate = $("#diagnostic_5_lab_exam_date").val();
    var dateSputum = new Date(txtSputumLabDate);
    var compareSputumDate = compareDates(dateToday,dateSputum);
    var txtSputumLabFee = $("#diagnostic_5_lab_fee").val();
    var txtSputumPlusses = $("#diagnostic_5_plusses").val();

    /*LIPID PROFILE*/
    var txtLipidLabDate = $("#diagnostic_6_lab_exam_date").val();
    var dateLipid = new Date(txtLipidLabDate);
    var compareLipidDate = compareDates(dateToday,dateLipid);
    var txtLipidLabFee = $("#diagnostic_6_lab_fee").val();
    var txtLipidTotal = $("#diagnostic_6_total").val();
    var txtLipidLdl = $("#diagnostic_6_ldl").val();
    var txtLipidHdl = $("#diagnostic_6_hdl").val();
    var txtLipidChol = $("#diagnostic_6_cholesterol").val();
    var txtLipidTrigy = $("#diagnostic_6_triglycerides").val();

    /*ECG*/
    var txtEcgLabDate = $("#diagnostic_9_lab_exam_date").val();
    var dateEcg = new Date(txtEcgLabDate);
    var compareEcgDate = compareDates(dateToday,dateEcg);
    var txtEcgLabFee = $("#diagnostic_9_lab_fee").val();
    var chkEcgNormal = $("#diagnostic_9_no").is(":checked");
    var chkEcgNotnNormal = $("#diagnostic_9_yes").is(":checked");
    var remEcgFindings = $("#diagnostic_9_ecg_remarks").val();

    /*FBS*/
    var txtFbsLabDate = $("#diagnostic_7_lab_exam_date").val();
    var dateFbs = new Date(txtFbsLabDate);
    var compareFbsDate = compareDates(dateToday,dateFbs);
    var txtFbsLabFee = $("#diagnostic_7_lab_fee").val();
    var txtFbsGlucoseMgdl = $("#diagnostic_7_glucose_mgdL").val();
    var txtFbsGlucoseMmol = $("#diagnostic_7_glucose_mmolL").val();

    /*RBS*/
    var txtRbsLabDate = $("#diagnostic_19_lab_exam_date").val();
    var dateRbs = new Date(txtRbsLabDate);
    var compareRbsDate = compareDates(dateToday,dateRbs);
    var txtRbsLabFee = $("#diagnostic_19_lab_fee").val();
    var txtRbsGlucoseMgdl = $("#diagnostic_19_glucose_mgdL").val();
    var txtRbsGlucoseMmol = $("#diagnostic_19_glucose_mmolL").val();

    var whatAge = $("#valtxtPerHistPatAge").val();
    var whatMonths = $("#valtxtPerHistPatMonths").val();

    if(chkCBCdone == true && txtCbcLabDate == "" && txtCbcLabFee == "" && txtCbcHema == "" && txtCbchemo == "" && txtCbcMhc == "" && txtCbcMchc == "" && txtCbcMcv == "" && txtCbcWbc == "" && txtCbcMyelocyte == "" && txtCbcNeutroBand == "" && txtCbcNeutroSeg == "" && txtCbcLymph == "" && txtCbcMono == "" && txtCbcEosi == "" && txtCbcBaso == "" && txtCbcPlatelet == ""){
        alert("Fill up all fields of CBC in LABORATORY RESULTS!");
        return false;
    }
    else if(compareCbcDate == "0"){
        alert("Laboratory Date of CBC is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkPapsSmearDone == true && txtPapsLabDate == "" && txtPapsLabFee == "" && txtPapsFind == "" && txtPapsImpre == ""){
        alert("Fill up all fields of Paps Smear in LABORATORY RESULTS!");
        return false;
    }
    else if(comparePapsDate == "0"){
        alert("Laboratory Date of Paps Smear is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkOGTTDone == true && txtOgttLabDate == "" && txtOgttLabFee == "" && txtOgttFastMg == "" && txtOgttFastMmol == "" && txtOgttOneMg == "" && txtOgttOneMmol == "" && txtOgttTwoMg == ""  && txtOgttTwoMmol == ""){
        alert("Fill up all fields of Oral Glucose Tolerance Test (OGTT) in LABORATORY RESULTS!");
        return false;
    }
    else if(compareOgttDate == "0"){
        alert("Laboratory Date of OGTT is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkUrinalysisDone == true && txtUrineLabDate == "" && txtUrineLabFee == "" && txtUrineSg == "" && txtUrineAppear == "" && txtUrineColor == "" && txtUrineGlucose == "" && txtUrineProtein == "" &&
        txtUrineKetones == "" && txtUrinePh == "" && txtUrinePus == "" && txtUrineAlb == "" && txtUrineRbc == "" && txtUrineWbc == "" && txtUrineBact == "" && txtUrineCryst == "" && txtUrineBlad == "" &&
        txtUrineSqCell == "" && txtUrineTubCell == "" && txtUrineBrCast == "" && txtUrineCellCast == "" && txtUrineGranCast == "" && txtUrineHyaCast == "" && txtUrineRbcCast == "" && txtUrineWaxyCast == "" && txtUrineWcCast == ""){
        alert("Fill up all fields of Urinalysis in LABORATORY RESULTS!");
        return false;
    }
    else if(compareUrineDate == "0"){
        alert("Laboratory Date of Urinalysis is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkFecalysisDone == true && txtFecaLabDate == "" && txtFecaLabFee == "" && txtFecaPus == "" && txtFecaRbc == "" && txtFecaWbc == "" && txtFecaOva == "" && txtFecaPara == "" && txtFecaOccult == ""){
        alert("Fill up all fields of Fecalysis in LABORATORY RESULTS!");
        return false;
    }
    else if(compareFecaDate == "0"){
        alert("Laboratory Date of Fecalysis is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkXrayDone == true && txtXrayLabDate == "" && txtXrayLabFee == "" && txtXrayFindings == ""){
        alert("Fill up all fields of Chest X-ray in LABORATORY RESULTS!");
        return false;
    }
    else if(compareXrayDate == "0"){
        alert("Laboratory Date of Chest X-ray is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkSputumDone == true && txtSputumLabDate == "" && txtSputumLabFee == "" && txtSputumPlusses == ""){
        alert("Fill up all fields of Sputum Microscopy in LABORATORY RESULTS!");
        return false;
    }
    else if(compareSputumDate == "0"){
        alert("Laboratory Date of Sputum Microscopy is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkLipidDone == true && txtLipidLabDate == "" && txtLipidLabFee == "" && txtLipidTotal == "" && txtLipidLdl == "" && txtLipidHdl == "" && txtLipidChol == "" && txtLipidTrigy == ""){
        alert("Fill up all fields of Lipid Profile in LABORATORY RESULTS!");
        return false;
    }
    else if(compareLipidDate == "0"){
        alert("Laboratory Date of Lipid Profile is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkECGDone == true && txtEcgLabDate == "" && txtEcgLabFee == "" && chkEcgNormal == false && chkEcgNotnNormal == false){
        alert("Fill up all fields of Electrocardiogram (ECG) in LABORATORY RESULTS!");
        return false;
    }
    else if(compareEcgDate == "0"){
        alert("Laboratory Date of ECG is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkFbsDone == true && txtFbsLabDate == "" && txtFbsLabFee == "" && txtFbsGlucoseMgdl == "" && txtFbsGlucoseMmol == ""){
        alert("Fill up all fields of Fasting Blood Sugar (FBS) in LABORATORY RESULTS!");
        return false;
    }
    else if(compareFbsDate == "0"){
        alert("Laboratory Date of FBS is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(chkRbsDone == true && txtRbsLabDate == "" && txtRbsLabFee == "" && txtRbsGlucoseMgdl == "" && txtRbsGlucoseMmol == ""){
        alert("Fill up all fields of Random Blood Sugar (RBS) in LABORATORY RESULTS!");
        return false;
    }     
    else if(compareRbsDate == "0"){
        alert("Laboratory Date of RBS is invalid! It should be less than or equal to current day.");
        return false;
    }
    else{
        return confirm('Are all information encoded correctly? Click OK to Submit now.');
    }
}


function validateFollowupMeds() {

    var pPrescDoctor = $("#pPrescDoctor").val();
    var pSoapOTP = $("#pSoapOTP").val();
    var cntSoapOTP = $("#pSoapOTP").val().length;
    var chkWalkedIn = $("#walkedInChecker_true").is(":checked");


    var pSoapdate = $("#pSOAPDate").val();

    var dateToday = new Date();
    var dateSoapDate = new Date(pSoapdate);
    var compareSoapDate = compareDates(dateToday,dateSoapDate);


    if(chkWalkedIn == true && (pSoapOTP == "" || cntSoapOTP < 4)) {
        alert("Authorization Transaction Code is required. It must be greater than or equal to 4 alphanumeric characters");
        $("#pSoapOTP").focus();
        return false;
    }  
   
    else if (pSoapdate == "") {
        alert("Consultation Date is required");
        $("#pSOAPDate").focus();
        return false;
    }        
    else if (compareSoapDate == "0") {
        alert("Consultation Date is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(pPrescDoctor == ""){
        alert("Please input Prescribing Physician and at least one medicine for follow-up.");
        return false;
    }
    else{
        return confirm('Do you want to submit it now?');
    }
}

function validateChecksSignsSymptomsCf4() {
    var chksChief = document.getElementsByName('pCf4Symptoms[]');
    var checkCountChief = 0;

    for (var i = 0; i < chksChief.length; i++) {
        if (chksChief[i].checked) {
            checkCountChief++;
        }
    }
    if ( checkCountChief < 1) {
        return false;
    }
    return true;
}

function validateChecksHeent() {
    var chksValue = document.getElementsByName('heent[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksChest() {
    var chksValue = document.getElementsByName('chest[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksHeart() {
    var chksValue = document.getElementsByName('heart[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksAbdomen() {
    var chksValue = document.getElementsByName('abdomen[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksGenitoUrinary() {
    var chksValue = document.getElementsByName('genitourinary[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksRectal() {
    var chksValue = document.getElementsByName('rectal[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksSkin() {
    var chksValue = document.getElementsByName('skinExtremities[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksNeuro() {
    var chksValue = document.getElementsByName('neuro[]');
    var checkCountValue = 0;

    for (var i = 0; i < chksValue.length; i++) {
        if (chksValue[i].checked) {
            checkCountValue++;
        }
    }
    if ( checkCountValue < 1) {
        return false;
    }
    return true;
}

function validateChecksMedsHist() {
    var chksMedsHist = document.getElementsByName('chkMedHistDiseases[]');
    var checkCountMedsHist = 0;

    for (var i = 0; i < chksMedsHist.length; i++) {
        if (chksMedsHist[i].checked) {
            checkCountMedsHist++;
        }
    }

    if (checkCountMedsHist < 1) {
        return false;
    }
    return true;
}

function validateChecksFamHist() {
    var chksFamHist = document.getElementsByName('chkFamHistDiseases[]');
    var checkCountFamHist = 0;

    for (var i = 0; i < chksFamHist.length; i++) {
        if (chksFamHist[i].checked) {
            checkCountFamHist++;
        }
    }

    if (checkCountFamHist < 1) {
        return false;
    }
    return true;
}

function validateChecksImmune() {
    var chksImmChild = document.getElementsByName('chkImmChild[]');
    var checkCountImmChild = 0;
    var chksImmAdult = document.getElementsByName('chkImmAdult[]');
    var checkCountImmAdult = 0;
    var chksImmPreg = document.getElementsByName('chkImmPregnant[]');
    var checkCountImmPreg = 0;
    var chksImmElder = document.getElementsByName('chkImmElderly[]');
    var checkCountImmElder = 0;

    for (var i = 0; i < chksImmChild.length; i++) {
        if (chksImmChild[i].checked) {
            checkCountImmChild++;
        }
    }
    for (var i = 0; i < chksImmAdult.length; i++) {
        if (chksImmAdult[i].checked) {
            checkCountImmAdult++;
        }
    }
    for (var i = 0; i < chksImmElder.length; i++) {
        if (chksImmElder[i].checked) {
            checkCountImmElder++;
        }
    }
    for (var i = 0; i < chksImmPreg.length; i++) {
        if (chksImmPreg[i].checked) {
            checkCountImmPreg++;
        }
    }
    if (checkCountImmChild < 1) {
        return false;
    }
    if (checkCountImmAdult < 1) {
        return false;
    }
    if (checkCountImmElder < 1) {
        return false;
    }
    if (checkCountImmPreg < 1) {
        return false;
    }
    return true;
}

function validateChecksPlan() {
     
    // var chksDiag = document.getElementsByName('diagnostic[]');
    // var checkCountDiag = 0;
    var chksMgmt = document.getElementsByName('management[]');
    var checkCountMgmt = 0;

    // for (var i = 0; i < chksDiag.length; i++) {
    //     if (chksDiag[i].checked) {
    //         checkCountDiag++;
    //     }
    // }
    for (var i = 0; i < chksMgmt.length; i++) {
        if (chksMgmt[i].checked) {
            checkCountMgmt++;
        }
    }
    // if (checkCountDiag < 1 && document.getElementById('diagnostic_NA').checked == false) {
    //     return false;
    // }
    if (checkCountMgmt < 1 && document.getElementById('management_NA').checked == false) {
        return false;
    } 
        
    return true;
    
}

function enDisFamHistSmoking(selected_val) {
    if(selected_val == "Y" || selected_val == "X") {
        $("#txtFamHistCigPk").attr("disabled",false);
        $("#txtFamHistCigPk").val("");
    }
    else {
        $("#txtFamHistCigPk").attr("disabled",true);
        $("#txtFamHistCigPk").val("");
    }
}

function enDisFamHistAlcohol(selected_val) {
    if(selected_val == "Y" || selected_val == "X") {
        $("#txtFamHistBottles").attr("disabled",false);
        $("#txtFamHistBottles").val("");
    }
    else {
        $("#txtFamHistBottles").attr("disabled",true);
        $("#txtFamHistBottles").val("");
    }
}
/*Disabled fields in Menstrual History*/
function disMenstrualHist(){
    $("#txtOBHistMenarche").attr("disabled",true);
    $("#txtOBHistLastMens").attr("disabled",true);
    $("#txtOBHistPeriodDuration").attr("disabled",true);
    $("#txtOBHistPadsPerDay").attr("disabled",true);
    $("#txtOBHistOnsetSexInt").attr("disabled",true);
    $("#txtOBHistBirthControl").attr("disabled",true);
    $("#txtOBHistInterval").attr("disabled",true);
    $("#radOBHistMenopauseN").attr("disabled",true);
    $("#txtOBHistMenopauseAge").attr("disabled",true);
}
/*Enabled fields in Menstrual History*/
function enMenstrualHist(){
    $("#txtOBHistMenarche").attr("disabled",false);
    $("#txtOBHistLastMens").attr("disabled",false);
    $("#txtOBHistPeriodDuration").attr("disabled",false);
    $("#txtOBHistPadsPerDay").attr("disabled",false);
    $("#txtOBHistOnsetSexInt").attr("disabled",false);
    $("#txtOBHistBirthControl").attr("disabled",false);
    $("#txtOBHistInterval").attr("disabled",false);
    $("#radOBHistMenopauseN").attr("disabled",false);

    var chkMenoY = $("#radOBHistMenopauseY").is(":checked");
    if(chkMenoY == true){
        $("#txtOBHistMenopauseAge").attr("disabled",false);
    }else{
        $("#txtOBHistMenopauseAge").attr("disabled",true);
    }
}

/*Disabled fields in Pregnancy History*/
function disPregHist(){
    $("#txtOBHistGravity").attr("disabled",true);
    $("#txtOBHistParity").attr("disabled",true);
    $("#optOBHistDelivery").attr("disabled",true);
    $("#txtOBHistFullTerm").attr("disabled",true);
    $("#txtOBHistPremature").attr("disabled",true);
    $("#txtOBHistAbortion").attr("disabled",true);
    $("#txtOBHistLivingChildren").attr("disabled",true);
}

/*Enabled fields in Pregnancy History*/
function enPregHist(){
    $("#txtOBHistGravity").attr("disabled",false);
    $("#txtOBHistParity").attr("disabled",false);
    $("#optOBHistDelivery").attr("disabled",false);
    $("#txtOBHistFullTerm").attr("disabled",false);
    $("#txtOBHistPremature").attr("disabled",false);
    $("#txtOBHistAbortion").attr("disabled",false);
    $("#txtOBHistLivingChildren").attr("disabled",false);
}

/*Disabled fields in Paps Smear Labs*/
function disLabsPapsSmear(){
    $("#diagnostic_13_lab_exam_date").attr("disabled",true);
    $("#diagnostic_13_lab_fee").attr("disabled",true);
    $("#diagnostic_13_papsSmearFindings").attr("disabled",true);
    $("#diagnostic_13_papsSmearImpression").attr("disabled",true);
    $("#diagnostic_13_copay").attr("disabled",true);
}

/*Enabled fields in Paps Smear Labs*/
function enLabsPapsSmear(){
    $("#diagnostic_13_lab_exam_date").attr("disabled",false);
    $("#diagnostic_13_lab_fee").attr("disabled",false);
    $("#diagnostic_13_papsSmearFindings").attr("disabled",false);
    $("#diagnostic_13_papsSmearImpression").attr("disabled",false);
    $("#diagnostic_13_copay").attr("readonly",true);
    $("#diagnostic_13_copay").attr("disabled",false);
}

/*Disabled fields in OGTT Labs*/
function disLabsOgtt(){
    $("#diagnostic_14_lab_exam_date").attr("disabled",true);
    $("#diagnostic_14_lab_fee").attr("disabled",true);
    $("#diagnostic_14_fasting_mg").attr("disabled",true);
    $("#diagnostic_14_fasting_mmol").attr("disabled",true);
    $("#diagnostic_14_oneHr_mg").attr("disabled",true);
    $("#diagnostic_14_oneHr_mmol").attr("disabled",true);
    $("#diagnostic_14_twoHr_mg").attr("disabled",true);
    $("#diagnostic_14_twoHr_mmol").attr("disabled",true);
    $("#diagnostic_14_copay").attr("disabled",true);
}
function enLabsOgtt(){
    $("#diagnostic_14_lab_exam_date").attr("disabled",false);
    $("#diagnostic_14_lab_fee").attr("disabled",false);
    $("#diagnostic_14_fasting_mg").attr("disabled",false);
    $("#diagnostic_14_fasting_mmol").attr("disabled",false);
    $("#diagnostic_14_oneHr_mg").attr("disabled",false);
    $("#diagnostic_14_oneHr_mmol").attr("disabled",false);
    $("#diagnostic_14_twoHr_mg").attr("disabled",false);
    $("#diagnostic_14_twoHr_mmol").attr("disabled",false);
    $("#diagnostic_14_copay").attr("disabled",false);
    $("#diagnostic_14_copay").attr("readonly",true);
}


/*Disabled fields in Sputum Microscopy (1) Labs*/
function disLabsSputum_1(){
    $("#diagnostic_5_lab_exam_date").attr("disabled",true);
    $("#diagnostic_5_lab_fee").attr("disabled",true);
    $("#diagnostic_5_copay").attr("disabled",true);
    $("#diagnostic_5_no").attr("disabled",true);
    $("#diagnostic_5_yes").attr("disabled",true);
    $("#diagnostic_5_sputum_remarks").attr("disabled",true);
    $("#diagnostic_5_plusses").attr("disabled",true);
}

/*Enabled fields in Sputum Microscopy (1) Labs*/
function enLabsSputum_1(){
    $("#diagnostic_5_lab_exam_date").attr("disabled",false);
    $("#diagnostic_5_lab_fee").attr("disabled",false);
    $("#diagnostic_5_no").attr("disabled",false);
    $("#diagnostic_5_yes").attr("disabled",false);
    $("#diagnostic_5_sputum_remarks").attr("disabled",false);
    $("#diagnostic_5_plusses").attr("disabled",false);
    $("#diagnostic_5_copay").attr("readonly",true);
    $("#diagnostic_5_copay").attr("disabled",false);
}

function enDisOBHistMenopause(selected_val) {
    if(selected_val == "Y" || selected_val == "X") {
        $("#txtOBHistMenopauseAge").attr("disabled",false);
        $("#txtOBHistMenopauseAge").val("");
    }
    else {
        $("#txtOBHistMenopauseAge").attr("disabled",true);
        $("#txtOBHistMenopauseAge").val("");
    }
}

function chkNA(){
    if($('[name="Q17"]').checked == '3'){
        alert('not applicable');
        var $success=false;
    }
    else{
        var $success=true;
    }
    return $success;
}
/*END HSA MODULE*/

function enableDependentTypeMemInfo() {
    value = $( "#pPatientType" ).val();
    if(value == 'DD'){
        $("#pDependentType").attr("disabled",false);
        $("#pPatientPIN").attr("readonly",false);
        $("#pWithDisability").attr("disabled",false);
    } else{
        $("#pDependentType").attr("disabled",true);
        $("#pPatientPIN").attr("readonly",false);
        $("#pWithDisability").attr("disabled",true);
    }

    if(value == 'NM') {
        $("#pPatientPIN").attr("readonly",true);
    } else{
        $("#pPatientPIN").attr("readonly",false);
    }

    if(value == 'MM'){
        $("#pMemberPIN").attr("readonly",true);
        $("#pMemberLastName").attr("readonly",true);
        $("#pMemberFirstName").attr("readonly",true);
        $("#pMemberMiddleName").attr("readonly",true);
        $("#pMemberSuffix").attr("readonly",true);
        $("#pMemberDateOfBirth").attr("disabled",true);
        $("#pMemberSex").attr("disabled",true);
    }
    else if(value == 'NM'){
        $("#pMemberPIN").attr("readonly",true);
        $("#pMemberLastName").attr("readonly",true);
        $("#pMemberFirstName").attr("readonly",true);
        $("#pMemberMiddleName").attr("readonly",true);
        $("#pMemberSuffix").attr("readonly",true);
        $("#pMemberDateOfBirth").attr("disabled",true);
        $("#pMemberSex").attr("disabled",true);
    }
    else{
        $("#pMemberPIN").attr("readonly",false);
        $("#pMemberLastName").attr("readonly",false);
        $("#pMemberFirstName").attr("readonly",false);
        $("#pMemberMiddleName").attr("readonly",false);
        $("#pMemberSuffix").attr("readonly",false);
        $("#pMemberDateOfBirth").attr("disabled",false);
        $("#pMemberSex").attr("disabled",false);
    }
}

function selectCivilStatus(value){
    if(value == 'S'){
        $("#pPatientCivilStatusX option:selected").val('M');
        $("#pPatientCivilStatusX option:selected").text('MARRIED');
        $("#pPatientCivilStatusX option:disabled").removeAttr('disabled');
        $("#pPatientCivilStatusX option[value='S']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='W']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='X']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='A']").attr('disabled', true);
    }
    else if(value == 'C'){
        $("#pPatientCivilStatusX option:selected").val('S');
        $("#pPatientCivilStatusX option:selected").text('SINGLE');
        $("#pPatientCivilStatusX option:disabled").removeAttr('disabled');
        $("#pPatientCivilStatusX option[value='M']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='W']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='X']").attr('disabled', true);
        $("#pPatientCivilStatusX option[value='A']").attr('disabled', true);
    }
    else{
        $("#pPatientCivilStatusX option:selected").val('');
        $("#pPatientCivilStatusX option:selected").text('');
        $("#pPatientCivilStatusX option[value='S']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='M']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='W']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='X']").attr('disabled', false);
        $("#pPatientCivilStatusX option[value='A']").attr('disabled', false);
    }
}


function saveTransRegistration() {
    var txtPxSex = $("#pPatientSexX option:selected").val();
    var txtMmSex = $("#pMemberSex option:selected").val();
    var txtPxDoB = $("#pPatientDateOfBirth").val();
    var txtEnlistDate = $("#pEnlistmentDate").val();
    var txtMmDoB = $("#pMemberDateOfBirth").val();

    var dateToday = new Date();
    var datePxDoB = new Date(txtPxDoB);
    var dateRegDate = new Date(txtEnlistDate);
    var dateMemDoB = new Date(txtMmDoB);

    var regDateYear = dateRegDate.getYear();

    var comparePxDoB = compareDates(dateToday,datePxDoB);
    var compareRegDate = compareDates(dateToday,dateRegDate);
    var compareMemDoB = compareDates(dateToday,dateMemDoB);

    if (txtPxSex == "") {
        alert("Patient's Sex is required!");
        $("#pPatientSexX").focus();
        return false;
    }
    else if(regDateYear <= 116){
        alert("Date of Encounter is invalid! Year should be greater than or equal to year 2017");
        return false;
    }
    else if(compareRegDate == "0"){
        alert("Date of Encounter is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(comparePxDoB == "0"){
        alert("Patient's Date of Birth is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(compareMemDoB == "0"){
        alert("Member's Date of Birth is invalid! It should be less than or equal to current day.");
        return false;
    }
    else {
        //TO DO SAVING
        return confirm('Do you want to register this patient to the system?');
    }

}

 function compareDates(dateToday,date2){
    if (dateToday>date2) return ("1");
    else if (dateToday<date2) return ("0");
    else return ("-");
}

function validateHciForm() {
    var pUserPass = $("#pUserPassword").val();
    var pUserConfirmPass = $("#pUserConfirmPassword").val();
    var pAccreNo = $("#pAccreNo").val();
    var pUserId = $("#pUserId").val();
    var pCKey = $("#pHciKey").val();
    var pUserDoB = $("#pUserDoB").val();

    var dateToday = new Date();
    var dateUserDoB = new Date(pUserDoB);
    var compareUserDoB = compareDates(dateToday,dateUserDoB);


    if (pUserPass != pUserConfirmPass) {
        alert("Password do not match!");
        return false;
    }
    else if(pAccreNo == ""){
        alert("Accreditation Number required!");
        return false;
    }
    else if(pUserId.length() > 5){
        alert("User ID must be minimum of 6 characters!");
        return false;
    }
    else if(pUserPass.length() > 5){
        alert("User Password must be minimum of 6 characters!");
        return false;
    }
    else if(pAccreNo.length() > 8){
        alert("Accreditation Number must be minimum of 9 characters!");
        return false;
    }
    else if(pCKey.length() > 9){
        alert("Accreditation Number must be minimum of 10 characters!");
        return false;
    }
    else if(compareUserDoB == "0"){
        alert("Date of Birth is invalid! It should be less than or equal to current day.");
        return false;
    }
    else{
        return confirm('Are all information encoded correctly? Click OK to Submit now.');
    }
}


/*CF4*/
function showTab(id){
    if(id == 'tab9') {
        var dateToday = new Date();

        var txtCF4ClaimId = $("#txtPerClaimId").val();
        /*Individual Health Profile*/
        var txtPxPin = $("#txtPerPatPIN").val();
        var txtMemLname = $("#txtPerPatLname").val();
        var txtMemFname = $("#txtPerPatFname").val();
        var txtPxSex = $("#txtPerPatSex option:selected").val();
        var txtPxCivilStatus = $("#txtPerPatStatus option:selected").val();
        var txtPxType = $("#txtPerPatType option:selected").val();
        var txtPxDoB = $("#txtPerPatBirthday").val();
        var datePxDoB= new Date(txtPxDoB);
        var comparePxDoB = compareDates(dateToday,datePxDoB);

        if (txtCF4ClaimId == "" || txtPxPin == "" || txtMemLname == ""  || txtMemFname == "" || txtPxSex == "" || txtPxType == "") {
            alert("Please fill up all required fields in PATIENT PROFILE");
            return false;
        }
        else if (txtPxPin.length < 12) {
            alert("Input 12 numbers of Patient's PIN");
            return false;
        }
        else if(comparePxDoB == "0"){
            alert("Date of Birth in PATIENT PROFILE is invalid! It should be less than or equal to current day.");
            return false;
        }
        else {
            $("#list1").removeClass("active");
            $("#tab1").removeClass("active");
            $("#tab9").addClass("active in");
            $("#list9").addClass("active");
        }
    }
    if(id == 'tab2') {
        var txtComplaint = $("#pChiefComplaint").val();
        if(txtComplaint == "") {
            alert("Please specify Chief Complaint");
            $("#pChiefComplaint").focus();
            return false;
        }
        else {
            $("#list9").removeClass("active");
            $("#tab9").removeClass("active");
            $("#tab2").addClass("active in");
            $("#list2").addClass("active");
        }
    }
    if(id == 'tab3') {
        var txtHistIllness = $("#pHistPresentIllness").val();
        if(txtHistIllness == "") {
            alert("Please specify History of Present Illness");
            $("#pHistPresentIllness").focus();
            return false;
        }
        else {
            $("#list2").removeClass("active");
            $("#tab2").removeClass("active");
            $("#tab3").addClass("active in");
            $("#list3").addClass("active");
        }
    }
    if(id == 'tab4') {
        var txtPastMedsHist = $("#txaMedHistOthers").val();
        if(txtPastMedsHist == "") {
            alert("Please specify Pertinent Past Medical History");
            $("#txaMedHistOthers").focus();
            return false;
        }
        else {
            $("#list3").removeClass("active");
            $("#tab3").removeClass("active");
            $("#tab4").addClass("active in");
            $("#list4").addClass("active");
        }
    }
    if(id == 'tab5') {
        var obgyne = $("#mhDone_2").is(":checked");
        var txtLastMens = $("#txtOBHistLastMens").val();
        var dateLastMens = new Date(txtLastMens);
        var compareLastMensDate = compareDates(dateToday,dateLastMens);

        var txtGravity = $("#txtOBHistGravity").val();
        var txtParity = $("#txtOBHistParity").val();
        var txtFullTerm = $("#txtOBHistFullTerm").val();
        var txtPremature = $("#txtOBHistPremature").val();
        var txtAbortion = $("#txtOBHistAbortion").val();
        var txtLivingChildren = $("#txtOBHistLivingChildren").val();

        if(txtLastMens != "" && compareLastMensDate == "0"){
            alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
            return false;
        }
        else if(obgyne == true && (txtLastMens == "" || txtGravity == "" || txtParity == "" || txtFullTerm == "" || txtPremature == "" || txtAbortion == "" || txtLivingChildren == "")){
            alert("Please fill up all the fields in OB-Gyne History!");
            return false;
        }
        else {
            $("#list4").removeClass("active");
            $("#tab4").removeClass("active");
            $("#tab5").addClass("active in");
            $("#list5").addClass("active");
        }
    }
    if(id == 'tab6') {
        if(validateChecksSignsSymptomsCf4() == false){
            alert("Choose at least one PERTINENT SIGNS & SYMPTOMS ON ADMISSION!");
            return false;
        }
        else {
            $("#list5").removeClass("active");
            $("#tab5").removeClass("active");
            $("#tab6").addClass("active in");
            $("#list6").addClass("active");
        }
    }
    if(id == 'tab7') {
        /*Pertinent Physical Examination Findings*/
        var txtPhExSystolic = $("#txtPhExSystolic").val();
        var txtPhExBPDiastolic = $("#txtPhExBPDiastolic").val();
        var txtPhExHeartRate = $("#txtPhExHeartRate").val();
        var txtPhExRespiratoryRate = $("#txtPhExRespiratoryRate").val();

        /*General Survey*/
        var chkGenSurvey1 = $("#pGenSurvey_1").is(":checked");
        var chkGenSurvey2 = $("#pGenSurvey_2").is(":checked");
        var txtGenSurveyRem = $("#pGenSurveyRem").val();

        if(txtPhExSystolic == "" || txtPhExBPDiastolic == "" || txtPhExHeartRate == "" || txtPhExRespiratoryRate == "" || txtPhExTemp == ""){
            alert("Please fill up all required fields in PHYSICAL EXAMINATION ON ADMISSION!");
            return false;
        }
        else if(chkGenSurvey1 == false && chkGenSurvey2 == false){
            alert("Please specify General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
            $("#pGenSurvey_1").focus();
            return false;
        }
        else if(chkGenSurvey2 == true && txtGenSurveyRem == ""){
            alert("Please specify Altered Sensorium in General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
            $("#pGenSurveyRem").focus();
            return false;
        }
        else if(validateChecksHeent() == false){
            alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksChest() == false){
            alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksHeart() == false){
            alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksAbdomen() == false){
            alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksGenitoUrinary() == false){
            alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksSkin() == false){
            alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else if(validateChecksNeuro() == false){
            alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
            return false;
        }
        else {
            $("#list6").removeClass("active");
            $("#tab6").removeClass("active");
            $("#tab7").addClass("active in");
            $("#list7").addClass("active");
        }
    }
    if(id == 'tab8') {
        var txtDateActionWard = document.getElementsByName('pDateActionWard[]');
        var dateActionWard = new Date(txtDateActionWard);
        var compareDateActionWard = compareDates(dateToday,dateActionWard);
        var txtActionWard = document.getElementsByName('pActionWard[]');


        if(txtActionWard.length == 0){
            alert("Please input at least one DOCTOR'S ORDER/ACTION in COURSE IN THE WARD");
            $("#txtWardDocAction").focus();
            return false;
        }
        else if(compareDateActionWard == "0"){
            alert("Date of Doctor's Order/Action in COURSE IN THE WARD is invalid! It should be less than or equal to current day.");
            return false;
        }
        else {
            $("#list7").removeClass("active");
            $("#tab7").removeClass("active");
            $("#tab8").addClass("active in");
            $("#list8").addClass("active");
        }
    }
}

function saveCF4Transaction() {
    var dateToday = new Date();

    var txtCF4ClaimId = $("#txtPerClaimId").val();
    /*Individual Health Profile*/
    var txtPxPin = $("#txtPerPatPIN").val();
    var txtMemLname = $("#txtPerPatLname").val();
    var txtMemFname = $("#txtPerPatFname").val();
    var txtPxSex = $("#txtPerPatSex option:selected").val();

    var txtPxType = $("#txtPerPatType option:selected").val();
    var txtPxDoB = $("#txtPerPatBirthday").val();
    var datePxDoB= new Date(txtPxDoB);
    var comparePxDoB = compareDates(dateToday,datePxDoB);

    /*Chief Complaint*/
    var txtComplaint = $("#pChiefComplaint").val();

    /*History of Present Illness*/
    var txtHistIllness = $("#pHistPresentIllness").val();

    /*Past Medical History*/
    var txtPastMedsHist = $("#txaMedHistOthers").val();

    /*Menstrual History*/
    var obgyne = $("#mhDone_2").is(":checked");
    var txtMenarche = $("#txtOBHistMenarche").val();
    var txtLastMens = $("#txtOBHistLastMens").val();
    var dateLastMens = new Date(txtLastMens);
    var compareLastMensDate = compareDates(dateToday,dateLastMens);
    var txtPeriodDuration = $("#txtOBHistPeriodDuration").val();
    /*Pregnancy History*/
    var txtGravity = $("#txtOBHistGravity").val();
    var txtParity = $("#txtOBHistParity").val();

    var whatSex = $("#txtPerPatSex").val();

    /*Pertinent Physical Examination Findings*/
    var txtPhExSystolic = $("#txtPhExSystolic").val();
    var txtPhExBPDiastolic = $("#txtPhExBPDiastolic").val();
    var txtPhExHeartRate = $("#txtPhExHeartRate").val();
    var txtPhExRespiratoryRate = $("#txtPhExRespiratoryRate").val();

    /*General Survey*/
    var chkGenSurvey1 = $("#pGenSurvey_1").is(":checked");
    var chkGenSurvey2 = $("#pGenSurvey_2").is(":checked");
    var txtGenSurveyRem = $("#pGenSurveyRem").val();

    /*Course in the Ward*/
    var txtDateActionWard = document.getElementsByName('pDateActionWard[]');
    var dateActionWard = new Date(txtDateActionWard);
    var compareDateActionWard = compareDates(dateToday,dateActionWard);
    var txtActionWard = document.getElementsByName('pActionWard[]');

    if (txtCF4ClaimId == "" || txtPxPin == "" || txtMemLname == ""  || txtMemFname == "" || txtPxSex == "" || txtPxType == "") {
        alert("Please fill up all required fields in PATIENT PROFILE");
        return false;
    }
    else if (txtPxPin.length < 12) {
        alert("Input 12 numbers of Patient's PIN");
        return false;
    }
    else if(comparePxDoB == "0"){
        alert("Date of Birth in PATIENT PROFILE is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(txtComplaint == "") {
        alert("Please specify Chief Complaint");
        $("#pChiefComplaint").focus();
        return false;
    }
    else if(txtHistIllness == "") {
        alert("Please specify History of Present Illness");
        $("#pHistPresentIllness").focus();
        return false;
    }
    else if(txtPastMedsHist == "") {
        alert("Please specify Pertinent Past Medical History");
        $("#txaMedHistOthers").focus();
        return false;
    }
    else if(txtLastMens != "" && compareLastMensDate == "0"){
        alert("Date of Last Menstrual Period is invalid! It should be less than or equal to current day.");
        return false;
    }
    else if(obgyne == true && txtLastMens == ""){
        alert("Please fill up the Last Menstrual Period in MENSTRUAL HISTORY of OB-GYNE HISTORY menu!");
        return false;
    }
    else if(obgyne == true && (txtGravity == "" || txtParity == "")){
        alert("Please fill up the Gravity and Parity in PREGNANCY HISTORY of OB-GYNE HISTORY!");
        return false;
    }
    else if(validateChecksSignsSymptomsCf4() == false){
        alert("Choose at least one PERTINENT SIGNS & SYMPTOMS ON ADMISSION!");
        return false;
    }
    else if(txtPhExSystolic == "" || txtPhExBPDiastolic == "" || txtPhExHeartRate == "" || txtPhExRespiratoryRate == "" || txtPhExTemp == ""){
        alert("Please fill up all required fields in PHYSICAL EXAMINATION ON ADMISSION!");
        return false;
    }
    else if(chkGenSurvey1 == false && chkGenSurvey2 == false){
        alert("Please specify General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
        $("#pGenSurvey_1").focus();
        return false;
    }
    else if(chkGenSurvey2 == true && txtGenSurveyRem == ""){
        alert("Please specify Altered Sensorium in General Survey of PHYSICAL EXAMINATION ON ADMISSION!");
        $("#pGenSurveyRem").focus();
        return false;
    }
    else if(validateChecksHeent() == false){
        alert("Choose at least one HEENT in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksChest() == false){
        alert("Choose at least one CHEST/BREAST/LUNGS in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksHeart() == false){
        alert("Choose at least one HEART in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksAbdomen() == false){
        alert("Choose at least one ABDOMEN in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksGenitoUrinary() == false){
        alert("Choose at least one GENITOURINARY in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksSkin() == false){
        alert("Choose at least one SKIN/EXTREMITIES in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(validateChecksNeuro() == false){
        alert("Choose at least one NEUROLOGICAL EXAMINATION in PERTINENT FINDINGS PER SYSTEM of PHYSICAL EXAMINATION ON ADMISSION");
        return false;
    }
    else if(txtActionWard.length == 0){
        alert("Please input at least one DOCTOR'S ORDER/ACTION in COURSE IN THE WARD");
        $("#txtWardDocAction").focus();
        return false;
    }
    else if(compareDateActionWard == "0"){
        alert("Date of Doctor's Order/Action in COURSE IN THE WARD is invalid! It should be less than or equal to current day.");
        return false;
    }
    else {
        //TO DO SAVING
        return confirm('Do you want to submit it now?');
    }
} /*END SAVE CF4 TRANSACTION*/


// GET ZSCORE
function getZScoreF023(form) {

       if (form.txtPhExWeightKg.value==null||form.txtPhExWeightKg.value.length==0 || form.txtPhExHeightCm.value==null||form.txtPhExHeightCm.value.length==0){
            alert("\nPlease input value on Height (cm) and Weight (kg)");
            return false;
       }

       else if (parseFloat(form.txtPhExHeightCm.value) <= 45||
                parseFloat(form.txtPhExHeightCm.value) >=500||
                parseFloat(form.txtPhExWeightKg.value) <= 0||
                parseFloat(form.txtPhExWeightKg.value) >=500){
                alert("\nPlease enter values again. \nHeight in cm and \nWeight in kilos ");
                ClearForm(form);
                return false;
       }
       return true;

}

function loadZScore() {
    //    
    var pHeight = $("#txtPhExHeightCm").val();
    var pLength = $("#txtPhExLengthCm").val();
    var pWeigth = $("#txtPhExWeightKg").val();

    //age    
    var pYearAge = $("#valtxtPerHistPatAge").val();
    var pMonthAge = $("#valtxtPerHistPatMonths").val();

    //sex
    var pSex = $("#txtPerHistPatSexValue").val();

    // var length = Math.round(pLength);
    // var length = Math.floor(pLength / 0.5) * 0.5;
    // var vlength = Math.round(length);
    

    

    if((pYearAge >=0 && pYearAge <=1) && (pMonthAge >=0 && pMonthAge <= 11)){

        if (pLength != "" && pWeigth != ""){

            var decPart1= (pLength + "").split(".")[1];
            var decPart0 = (pLength + "").split(".")[0];


            var re = /\d\.(\d)/; 
            var m;


            if ((m = re.exec(pLength)) !== null ) {
                decPart1 = m[1];
            }
        }
        else {
            alert("\nPlease enter values again. \nLength in cm and \nWeight in kilos ");
        }
    }

    if((pYearAge >= 2 && pYearAge <= 4) && (pMonthAge >=0 && pMonthAge <= 11)){        

        if (pHeight != "" && pWeigth != ""){
            var decPart1= (pHeight + "").split(".")[1];
            var decPart0 = (pHeight + "").split(".")[0];


            var re = /\d\.(\d)/; 
            var m;
        
            if ((m = re.exec(pHeight)) !== null ) {
                decPart1 = m[1];
            }
        }
        else {
            alert("\nPlease enter values again. \nHeight in cm and \nWeight in kilos ");
        }
    }
   
        
    if(decPart1 == 3 || decPart1 == 4 || decPart1 == 5){
        var vlengthHeight = decPart0 + ".5";
    } else if(decPart1 == 0 || decPart1 == 1 || decPart1 == 2){
        //var vlengthHeight = decPart0 + ".0";
        var vlengthHeight = decPart0;
    } else if (decPart1 == null){
        //var vlengthHeight = decPart0 + ".0";
        var vlengthHeight = decPart0;
    }

    $("#txtPhExZscoreCm").load("loadZScore.php?length="+ vlengthHeight +"&weight=" + pWeigth +"&height=" + vlengthHeight +"&sex=" + pSex +"&year=" + pYearAge +"&month=" + pMonthAge);
}

function showHideBtn() {
  var atc = document.getElementById("fsATCinfo");
  var client = document.getElementById("fsClientInfo");
  var btnValueFf = document.getElementById("hideShowBtnATCInfo");
  if (atc.style.display === "none") {
    atc.style.display = "block";
    client.style.display = "block";
    btnValueFf.value = "- Hide Details";
  } else {
    atc.style.display = "none";
    client.style.display = "none";
    btnValueFf.value = "+ Show Details";
  }
}


/* Enable Medicine */
function enableDisableOthMeds() {
    var drugCode = $("#pDrugCode");
    var othDrug = $("#pGenericFreeText");
    var drugGrouping = $("#pOthMedDrugGrouping");
    if (isChecked('chkOthMeds')) {
        drugCode.val("");
        enableID('pGenericFreeText');
        enableID('pOthMedDrugGrouping');

    } else {        
        disableID('pGenericFreeText');
        disableID('pOthMedDrugGrouping');
        othDrug.val("");
        drugGrouping.val("");
    }
}