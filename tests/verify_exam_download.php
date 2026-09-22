<?php
/** DB_NAME=questbank_audit_... QUESTBANK_TEST_URL=http://127.0.0.1:8098 php tests/verify_exam_download.php */
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/services/ExamPaperPdf.php';
if (!str_starts_with(getDBConnection()->query('SELECT DATABASE()')->fetchColumn(),'questbank_audit_')) throw new RuntimeException('Use an isolated audit database.');
$base=getenv('QUESTBANK_TEST_URL') ?: 'http://127.0.0.1:8098';
if (!preg_match('#^http://127\.0\.0\.1:\d+$#',$base)) throw new RuntimeException('Use a loopback server.');
$checks=0;
function verifyDownload($ok,$label) { global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$label); ++$checks; echo "PASS: $label\n"; }
function pdfStreams($pdf) {
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s',$pdf,$matches);
    return implode('',array_map(fn($s)=>@gzuncompress($s) ?: $s,$matches[1]));
}
$exam=['title'=>'Engineering Examination Download','subject'=>'Civil Engineering','term'=>'Finals','time_limit'=>60];
$questions=[];
foreach (['multiple_choice','true_false','identification','fill_blank','matching','problem_solving','math_formula'] as $type) {
    $questions[]=['question_type'=>$type,'question_text'=>'Verification item for '.$type.'. Use the information in the question to answer clearly.',
        'points'=>2,'correct_answer'=>'PRIVATE_KEY_SENTINEL','explanation'=>'PRIVATE_SOLUTION_SENTINEL','formula_latex'=>'PRIVATE_FORMULA_SENTINEL',
        'option_a'=>'Steel','option_b'=>'Concrete','option_c'=>'Timber','option_d'=>'Glass','matching_pairs'=>'{"Load":"Zebra","Material":"Apple"}'];
}
$questions[]=['question_type'=>'problem_solving','question_text'=>'For area A = 2 m² and stress σ = 5 MPa, compute the load.','points'=>3,'correct_answer'=>'10 MN'];
$student=ExamPaperPdf::build($exam,$questions)->Output('S');
$teacher=ExamPaperPdf::build($exam,$questions,true)->Output('S');
$studentText=pdfStreams($student);$teacherText=pdfStreams($teacher);
verifyDownload(str_starts_with($student,'%PDF-'),'Student PDF generated');
verifyDownload(!str_contains($studentText,'PRIVATE_'),'Student copy excludes key, explanation and solution formula');
verifyDownload(str_contains($teacherText,'PRIVATE_KEY_SENTINEL') && str_contains($teacherText,'PRIVATE_SOLUTION_SENTINEL') && str_contains($teacherText,'PRIVATE_FORMULA_SENTINEL'),'Teacher copy includes complete key and reference material');
verifyDownload(str_contains($teacherText,'NOT FOR STUDENT DISTRIBUTION'),'Teacher copy clearly labeled');
verifyDownload(str_contains($studentText,'Apple') && str_contains($studentText,'Zebra') && strpos($studentText,'Apple')<strpos($studentText,'Zebra'),'Matching choices independently ordered');
verifyDownload(str_contains($studentText,'m^2') && str_contains($studentText,'sigma'),'Engineering notation retains readable meaning');
verifyDownload(str_contains($studentText,'Steel') && str_contains($studentText,'True / False') && str_contains($studentText,'Answer and working'),'Choice and writing formats included');
verifyDownload(preg_match_all('#/Type /Page\b#',$student)>=2,'Long exam paginates');
file_put_contents(sys_get_temp_dir().'/questbank-download-student.pdf',$student);
file_put_contents(sys_get_temp_dir().'/questbank-download-teacher.pdf',$teacher);

$fixture=json_decode(file_get_contents(sys_get_temp_dir().'/questbank-browser-fixture.json'),true);
function fetchDownload($cookie,$path,$data=null) {
    global $base;
    $ch=curl_init($base.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie]);
    if ($data!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    $response=curl_exec($ch);$size=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    return [$code,substr($response,$size),substr($response,0,$size)];
}
foreach (['teacher','student','other_teacher'] as $role) {
    $cookie=tempnam(sys_get_temp_dir(),'qb_download_');
    [, $html]=fetchDownload($cookie,'/index.php');
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$html,$m);
    $login=$role==='other_teacher' ? preg_replace('/0@example/','3@example',$fixture['teacher_login']) : $fixture[$role.'_login'];
    [$code]=fetchDownload($cookie,'/index.php',['csrf_token'=>$m[1],'action_login'=>1,'email'=>$login,'password'=>'Audit-Only-2026!']);
    verifyDownload($code===302,$role.' login');
    [$code,$body,$headers]=fetchDownload($cookie,'/teacher/print_exam.php?id='.$fixture['exam'].'&download=1');
    if ($role==='teacher') {
        verifyDownload($code===200 && str_starts_with($body,'%PDF-') && stripos($headers,'attachment;')!==false,'Owner receives an actual PDF attachment');
        [, $keyBody]=fetchDownload($cookie,'/teacher/print_exam.php?id='.$fixture['exam'].'&download=1&with_answers=1');
        verifyDownload(str_contains(pdfStreams($keyBody),'TEACHER ANSWER KEY'),'Owner can explicitly download teacher copy');
        [, $printHtml]=fetchDownload($cookie,'/teacher/print_exam.php?id='.$fixture['exam']);
        verifyDownload(str_contains($printHtml,'Download PDF') && str_contains($printHtml,'Print Now'),'Print and download controls coexist');
    } else {
        verifyDownload($code===($role==='student'?302:403) && !str_starts_with($body,'%PDF-'),$role.' cannot download another teacher exam');
    }
    unlink($cookie);
}
echo "Completed $checks download checks.\n";
