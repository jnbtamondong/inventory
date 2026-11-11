<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/er-record-pdf.php';
try{$pdf=erRecordPdf('<!doctype html><html><head><title>Synthetic test</title></head><body>Synthetic renderer check</body></html>');echo 'PASS PDF renderer '.strlen($pdf)." bytes\n";}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
