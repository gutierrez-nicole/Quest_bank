<?php
/** Run against the isolated audit database and a loopback PHP server (3M file / 8M request limits). */
require_once __DIR__.'/../app/bootstrap.php';
$db=getDBConnection();
if (!str_starts_with($db->query('SELECT DATABASE()')->fetchColumn(),'questbank_audit_')) throw new RuntimeException('Isolated audit database required');
$base=getenv('QUESTBANK_TEST_URL') ?: 'http://127.0.0.1:8098';
if (!preg_match('#^http://127\.0\.0\.1:\d+$#',$base)) throw new RuntimeException('Loopback only');
$f=json_decode(file_get_contents(sys_get_temp_dir().'/questbank-browser-fixture.json'),true);
$n=0;
function ok($v,$label){global $n;if(!$v)throw new RuntimeException('FAIL: '.$label);++$n;echo "PASS: $label\n";}
function req($cookie,$path,$data=null){global $base;$c=curl_init($base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>180]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,$data);$b=curl_exec($c);if($b===false)throw new RuntimeException(curl_error($c));$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return [$status,$b];}
function csrf($html){preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$html,$m);return $m[1]??'';}
function signin($login){$cookie=tempnam(sys_get_temp_dir(),'qb_approval_');[, $h]=req($cookie,'/index.php');[$code]=req($cookie,'/index.php',['csrf_token'=>csrf($h),'action_login'=>1,'email'=>$login,'password'=>'Audit-Only-2026!']);ok($code===302,'Existing active login');return $cookie;}
$teacher=signin($f['teacher_login']);
$other=signin(preg_replace('/0@example/','3@example',$f['teacher_login']));
$prefix='approval_'.bin2hex(random_bytes(4));
$db->prepare("INSERT INTO sections (teacher_id, section_name, course_name, academic_year) VALUES (?,?,'Audit','2026-2027')")->execute([$f['teacher'],$prefix]);$section=$db->lastInsertId();
$guest=tempnam(sys_get_temp_dir(),'qb_pending_');[, $h]=req($guest,'/index.php');
[$code,$h]=req($guest,'/index.php',['csrf_token'=>csrf($h),'action_register'=>1,'fullname'=>'Approval Test','username'=>$prefix,'email'=>$prefix.'@example.test','password'=>'Audit-Only-2026!','confirm_password'=>'Audit-Only-2026!','student_number'=>$prefix,'course'=>'Audit','year_level'=>1,'section'=>$prefix,'teacher_id'=>$f['teacher']]);
ok(str_contains($h,'Registration submitted'),'Public registration awaits approval');
$uid=(int)$db->query("SELECT id FROM users WHERE username='$prefix'")->fetchColumn();
ok($db->query("SELECT status FROM users WHERE id=$uid")->fetchColumn()==='pending','Account stored pending');
[$code,$h]=req($guest,'/index.php',['csrf_token'=>csrf($h),'action_login'=>1,'email'=>$prefix,'password'=>'Audit-Only-2026!']);ok($code===200 && str_contains($h,'pending teacher approval'),'Pending login blocked');
$request=(int)$db->query("SELECT id FROM student_requests WHERE student_id=$uid")->fetchColumn();
[, $h]=req($other,'/teacher/manage_students.php');req($other,'/teacher/manage_students.php',['csrf_token'=>csrf($h),'handle_request'=>1,'request_id'=>$request,'action_type'=>'accept','approval_section_id'=>$section]);
ok($db->query("SELECT status FROM users WHERE id=$uid")->fetchColumn()==='pending','Other teacher cannot approve request');
[, $h]=req($teacher,'/teacher/manage_students.php');ok(str_contains($h,$prefix),'Pending request visible in roster');
req($teacher,'/teacher/manage_students.php',['csrf_token'=>csrf($h),'handle_request'=>1,'request_id'=>$request,'action_type'=>'accept','approval_section_id'=>$section]);
ok($db->query("SELECT status FROM users WHERE id=$uid")->fetchColumn()==='active','Owner acceptance activates student');
$approved=signin($prefix);[$code]=req($approved,'/student/dashboard.php');ok($code===200,'Approved student accesses dashboard');
// Session guard also rejects accounts changed back to pending in this isolated fixture.
$db->exec("UPDATE users SET status='pending' WHERE id=$uid");[$code]=req($approved,'/student/dashboard.php');ok($code===302,'Existing session cannot bypass pending status');$db->exec("UPDATE users SET status='active' WHERE id=$uid");
[, $h]=req($teacher,'/teacher/manage_students.php');
req($teacher,'/teacher/manage_students.php',['csrf_token'=>csrf($h),'add_student'=>1,'student_number'=>$prefix.'t','fullname'=>'Teacher Created Test','email'=>$prefix.'t@example.test','student_password'=>'Audit-Only-2026!','section_id'=>$section]);
$created=(int)$db->query("SELECT id FROM users WHERE username='{$prefix}t'")->fetchColumn();
ok($created>0 && $db->query("SELECT status FROM users WHERE id=$created")->fetchColumn()==='pending','Teacher-created account awaits acceptance');
ok((int)$db->query("SELECT COUNT(*) FROM student_requests WHERE student_id=$created AND status='pending'")->fetchColumn()===1,'Teacher-created request is queued');
$exam=ExamService::createExam($f['teacher'],'Multi-page scan verification','Audit','Structural Engineering',30,[['question_text'=>'Enter value','question_type'=>'identification','correct_answer'=>'4.5','points'=>1]]);$examId=$exam['exam_id'];
$im=imagecreatetruecolor(1000,400);$white=imagecolorallocate($im,255,255,255);$black=imagecolorallocate($im,0,0,0);imagefill($im,0,0,$white);imagestring($im,5,20,40,'1. 4.5',$black);$path=sys_get_temp_dir().'/qb-multi-test.png';imagepng($im,$path);file_put_contents($path,str_repeat(' ',2550000-filesize($path)),FILE_APPEND);
[, $h]=req($teacher,'/teacher/upload_check.php');$token=csrf($h);
ok(str_contains($h,'name="process_ocr_grading" value="1"') && str_contains($h,'id="ocrResult"'),'Stable action field and always-visible result panel');
$data=['csrf_token'=>$token,'process_ocr_grading'=>1,'exam_id'=>$examId,'student_id'=>$f['student']];
for($i=0;$i<3;$i++)$data['exam_files['.$i.']']=new CURLFile($path,'image/png','page'.($i+1).'.png');
[$code,$h]=req($teacher,'/teacher/upload_check.php',$data);
ok($code===200 && str_contains($h,'saved as Pending Review'),'Three 2.55 MB pages processed and saved');
preg_match('/Submission #(\d+) saved/',$h,$m);$sid=(int)($m[1]??0);
$row=$db->query("SELECT * FROM exam_submissions WHERE id=$sid")->fetch();
ok($row && $row['upload_type']==='scanned' && $row['review_status']==='pending_review','Scanned submission retains pending status');
ok(str_contains($h,'Initial Scan Result') && str_contains($h,'Multi-page scan verification') && str_contains($h,'Submission ID: #'.$sid),'Result panel identifies saved submission');
file_put_contents(sys_get_temp_dir().'/qb-scan-result.html',$h);
[, $report]=req($teacher,'/teacher/reports.php');ok(str_contains($report,'Multi-page scan verification'),'Saved scan appears in teacher Reports');
ok(!AuthorizationService::canViewSubmission($f['student'],$sid),'Pending scanned result remains private');
$large=sys_get_temp_dir().'/qb-too-large.png';file_put_contents($large,str_repeat('x',9*1024*1024));
[$code,$h]=req($teacher,'/teacher/upload_check.php',array_merge(array_slice($data,0,4),['exam_file'=>new CURLFile($large,'image/png','large.png')]));
ok($code===413 && str_contains($h,'Upload exceeds the server request limit'),'Oversized request shows actionable error');
file_put_contents($large,str_repeat('x',4*1024*1024));
[$code,$h]=req($teacher,'/teacher/upload_check.php',array_merge(array_slice($data,0,4),['exam_files[0]'=>new CURLFile($path,'image/png','good.png'),'exam_files[1]'=>new CURLFile($large,'image/png','large.png')]));
ok(str_contains($h,'page exceeds the server file limit') && !str_contains($h,'saved as Pending Review'),'One oversized page rejects whole batch');
$after=(int)$db->query("SELECT COUNT(*) FROM exam_submissions WHERE exam_id=$examId")->fetchColumn();ok($after===1,'Failed uploads do not save partial or duplicate results');
echo "Completed $n scan and approval checks.\n";
