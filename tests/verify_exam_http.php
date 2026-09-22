<?php
/** HTTP integration checks against local PHP server and isolated audit database only. */
require_once __DIR__.'/../app/bootstrap.php';
$db=getDBConnection();
if (!str_starts_with($db->query('SELECT DATABASE()')->fetchColumn(),'questbank_audit_')) throw new RuntimeException('Isolated audit database required.');
$base=getenv('QUESTBANK_TEST_URL') ?: 'http://127.0.0.1:8097';
if (!preg_match('#^http://127\.0\.0\.1:\d+$#',$base)) throw new RuntimeException('Loopback server required.');
$fixture=json_decode(file_get_contents(sys_get_temp_dir().'/questbank-browser-fixture.json'),true);
$checks=0;
function checkHttp($ok,$label) {global $checks; if(!$ok) throw new RuntimeException('FAIL: '.$label);++$checks;echo "PASS: $label\n";}
function request($cookie,$path,$data=null) {global $base;$ch=curl_init($base.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>120]);if($data!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$data);$body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);if($body===false)throw new RuntimeException($error);return [$code,$body];}
function token($html) {if(preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$html,$m))return $m[1];if(preg_match('/append\(\x27csrf_token\x27, "([a-f0-9]+)"\)/',$html,$m))return $m[1];throw new RuntimeException('No CSRF token');}
function login($role) {global $fixture;$cookie=tempnam(sys_get_temp_dir(),'qb_cookie_');[, $html]=request($cookie,'/index.php');[$code]=request($cookie,'/index.php',['csrf_token'=>token($html),'action_login'=>1,'email'=>$fixture[$role.'_login'],'password'=>'Audit-Only-2026!']);checkHttp($code===302,$role.' normal login');return $cookie;}
$teacher=$fixture['teacher'];$student=$fixture['student'];
$exam=ExamService::createExam($teacher,'HTTP protected exam','Audit Security','Structural Engineering',30,[['question_text'=>'Identify the value','question_type'=>'identification','correct_answer'=>'UNIQUE_PRIVATE_KEY','points'=>1],['question_text'=>'Match prompts','question_type'=>'matching','correct_answer'=>'{"Prompt A":"Zebra","Prompt B":"Apple"}','points'=>1]]);
$examId=(int)$exam['exam_id'];
$db->prepare("UPDATE exam_questions SET explanation='UNIQUE_PRIVATE_SOLUTION',formula_latex='UNIQUE_PRIVATE_FORMULA' WHERE exam_id=?")->execute([$examId]);
$db->prepare("UPDATE exam_questions SET matching_pairs=? WHERE exam_id=? AND question_type='matching'")->execute(['{"Prompt A":"Zebra","Prompt B":"Apple"}',$examId]);
$db->prepare('INSERT INTO exam_assignments (exam_id,student_id,assigned_by) VALUES (?,?,?)')->execute([$examId,$student,$teacher]);
$studentCookie=login('student');
[$code,$html]=request($studentCookie,'/student/dashboard.php');checkHttp($code===200,'Student dashboard renders');$csrf=token($html);
checkHttp(!str_contains($html,'UNIQUE_PRIVATE_'),'Private keys and explanations absent from student HTML');
checkHttp(str_contains($html,'76.0%') && str_contains($html,'60.0%') && str_contains($html,'80.0%') && str_contains($html,'5 Papers'),'Rendered average, passing rate, completed count and class average match mixed fixtures');
[$code,$json]=request($studentCookie,'/student/dashboard.php',['action'=>'get_exam_questions','exam_id'=>$examId]);$payload=json_decode($json,true);
checkHttp($code===200 && !empty($payload['success']),'Authorized question AJAX succeeds');
checkHttp(!str_contains($json,'UNIQUE_PRIVATE_') && !str_contains($json,'correct_answer') && !str_contains($json,'matching_pairs') && !str_contains($json,'formula_latex'),'Question AJAX excludes keys, solutions, formulas and pair associations');
checkHttp($payload['questions'][1]['matching_options']===['Apple','Zebra'] && $payload['questions'][1]['matching_prompts']===['Prompt A','Prompt B'],'Matching options independently ordered from prompts');
[$code]=request($studentCookie,'/student/dashboard.php',['action'=>'submit_online_exam','exam_id'=>$examId,'answers'=>'{}']);checkHttp($code===403,'Submission without CSRF rejected');
[$code,$submittedJson]=request($studentCookie,'/student/dashboard.php',['action'=>'submit_online_exam','exam_id'=>$examId,'answers'=>'{}','csrf_token'=>$csrf]);
$submitted=json_decode($submittedJson,true);
checkHttp(!empty($submitted['success']) && !isset($submitted['percentage']) && !isset($submitted['total_score']) && !isset($submitted['status']),'Successful submission does not disclose an unpublished grade');
[, $privateJson]=request($studentCookie,'/student/dashboard.php',['action'=>'get_submission_breakdown','submission_id'=>$submitted['submission_id']]);
checkHttp(empty(json_decode($privateJson,true)['success']),'Unpublished answer breakdown remains inaccessible');
[$code]=request($studentCookie,'/teacher/reports.php');checkHttp($code===302,'Student cannot enter teacher review');
[, $json]=request($studentCookie,'/student/dashboard.php',['action'=>'get_exam_questions','exam_id'=>$fixture['exam']]);checkHttp(empty(json_decode($json,true)['success']),'Archived question fetch rejected');
[, $json]=request($studentCookie,'/student/dashboard.php',['action'=>'submit_online_exam','exam_id'=>$fixture['exam'],'answers'=>'{}','csrf_token'=>$csrf]);checkHttp(empty(json_decode($json,true)['success']),'Archived submission rejected');
foreach(['All','Finals','Prelim','Midterm'] as $term) {[$code,$html]=request($studentCookie,'/student/dashboard.php?term='.$term);checkHttp($code===200,'Term page '.$term.' renders');[$code,$csv]=request($studentCookie,'/student/export_csv.php?term='.$term);checkHttp($code===200 && str_contains($csv,'Submission ID'),'CSV export '.$term.' executes');[$code,$pdf]=request($studentCookie,'/student/export_pdf.php?term='.$term);checkHttp($code===200 && str_starts_with($pdf,'%PDF-'),'PDF transcript '.$term.' executes');if($term==='Finals')file_put_contents(sys_get_temp_dir().'/questbank-finals-transcript.pdf',$pdf);}
$teacherCookie=login('teacher');
[$code,$midtermPdf]=request($studentCookie,'/student/export_pdf.php?term=Midterm');
preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s',$midtermPdf,$streams);
$pdfText='';foreach($streams[1] as $stream) $pdfText .= @gzuncompress($stream) ?: $stream;
checkHttp(str_contains($pdfText,'(FAILED)') && !str_contains($pdfText,'(PASSED)'),'PDF respects 90 percent threshold for the 80 percent result');
file_put_contents(sys_get_temp_dir().'/questbank-midterm-transcript.pdf',$midtermPdf);
[$code,$teacherDashboard]=request($teacherCookie,'/teacher/dashboard.php');
checkHttp($code===200 && preg_match('/80(?:\.0)?%/', $teacherDashboard),'Teacher published average agrees with finalized class records');
foreach(['/teacher/reports.php','/teacher/reports.php?academic_period=finals','/teacher/reports.php?subject=Audit%20Mathematics','/teacher/reports.php?semester=1st%20Semester','/teacher/reports.php?school_year=2026-2027'] as $path) {[$code,$body]=request($teacherCookie,$path);checkHttp($code===200 && str_contains($body,'Class Performance'),'Teacher report query '.$path);}
// Upload a real generated answer-sheet image through the existing multipart handler.
$scan=ExamService::createExam($teacher,'HTTP scanned numeric exam','Audit OCR','Structural Engineering',30,[['question_text'=>'Enter numeric result','question_type'=>'identification','correct_answer'=>'4.5','points'=>1]]);
$scanExam=(int)$scan['exam_id'];
$imagePath=sys_get_temp_dir().'/questbank-real-ocr.png';
$small=imagecreatetruecolor(200,80);$white=imagecolorallocate($small,255,255,255);$black=imagecolorallocate($small,0,0,0);imagefill($small,0,0,$white);imagestring($small,5,12,12,'Answer Sheet',$black);imagestring($small,5,12,42,'1. 4.5',$black);
$img=imagescale($small,1000,400,IMG_NEAREST_NEIGHBOUR);imagepng($img,$imagePath);
[, $html]=request($teacherCookie,'/teacher/upload_check.php');
[$code,$html]=request($teacherCookie,'/teacher/upload_check.php',['csrf_token'=>token($html),'process_ocr_grading'=>1,'exam_id'=>$scanExam,'student_id'=>$student,'exam_file'=>new CURLFile($imagePath,'image/png','answer-sheet.png')]);
checkHttp($code===200 && str_contains($html,'saved as Pending Review'),'Real image upload handled');
$stmt=$db->prepare('SELECT * FROM exam_submissions WHERE exam_id=? ORDER BY id DESC LIMIT 1');$stmt->execute([$scanExam]);$sub=$stmt->fetch();
checkHttp($sub && trim($sub['ocr_text']??'')!=='','Real OCR raw text persisted');
$item=ResultWorkflowService::itemResults((int)$sub['id'])[0];
checkHttp($item['student_answer']==='4.5' && (float)$item['awarded_points']===1.0,'Real OCR parsed answer stored and graded');
checkHttp($sub['ocr_confidence']===null && $sub['review_status']==='pending_review','Vision OCR confidence stays unavailable and needs review');
$db->prepare("UPDATE exams SET exam_category='qualifying', qualifying_passing_percentage=80 WHERE id=?")->execute([$scanExam]);
ResultWorkflowService::reprocessOcr((int)$sub['id'],$teacher,'Integration check of actual OCR rerun');
$stmt->execute([$scanExam]);$rerun=$stmt->fetch();
checkHttp(trim($rerun['ocr_text'])!=='' && $rerun['ocr_confidence']===null && $rerun['review_status']==='pending_review','Real rerun saves fresh OCR provenance and remains pending');
checkHttp($rerun['qualification_status']==='qualified','OCR rerun recomputes qualifying outcome before publication');
$fixture['ocr_submission']=(int)$sub['id'];$fixture['ocr_exam']=$scanExam;
file_put_contents(sys_get_temp_dir().'/questbank-browser-fixture.json',json_encode($fixture));
unlink($studentCookie);unlink($teacherCookie);
echo "Completed $checks HTTP checks.\n";
