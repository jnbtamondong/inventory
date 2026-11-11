<?php
require_once __DIR__.'/../includes/submission.php';
$_SESSION=[];$_POST=['submission_key'=>str_repeat('a',32)];$payload=['request'=>1,'item'=>575,'qty'=>2];$checks=0;
function submissionCheck($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
submissionCheck(cssrSubmissionReplay('add',$payload)===null,'First submission allowed');
cssrRememberSubmission('add',$payload,['ok'=>true]);
submissionCheck(cssrSubmissionReplay('add',$payload)===['ok'=>true],'Network retry returns original result');
submissionCheck(cssrSubmissionReplay('storage',$payload)===null,'Scope separated');
try{cssrSubmissionReplay('add',['qty'=>3]);throw new Exception('Different payload accepted');}catch(DomainException $e){$checks++;}
$_POST['submission_key']='invalid';try{cssrSubmissionReplay('add',$payload);throw new Exception('Invalid key accepted');}catch(DomainException $e){$checks++;}
for($i=0;$i<110;$i++){$_POST['submission_key']=str_pad(dechex($i),32,'0',STR_PAD_LEFT);cssrRememberSubmission('add',$payload,['ok'=>true]);}
submissionCheck(count($_SESSION['cssr_submissions'])===100,'Bounded session storage');
echo "Passed $checks duplicate-submission checks without database changes.\n";
