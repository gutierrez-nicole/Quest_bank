<?php
require_once __DIR__ . '/../fpdf.php';

/** Downloadable papers using the existing PDF engine and saved exam records. */
class ExamPaperPdf extends FPDF {
    private bool $answerKey = false;

    private static function textValue($value): string {
        $text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Core fonts use Windows-1252; retain common engineering symbols as readable notation.
        $text = strtr($text, ['²'=>'^2','³'=>'^3','₀'=>'_0','₁'=>'_1','₂'=>'_2','₃'=>'_3',
            '≤'=>'<=','≥'=>'>=','≠'=>'!=','−'=>'-','√'=>'sqrt','∞'=>'infinity',
            'α'=>'alpha','β'=>'beta','γ'=>'gamma','δ'=>'delta','Δ'=>'Delta','θ'=>'theta',
            'λ'=>'lambda','μ'=>'mu','π'=>'pi','ρ'=>'rho','σ'=>'sigma','τ'=>'tau','φ'=>'phi','Σ'=>'Sum','∑'=>'Sum']);
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        return $converted === false ? '' : $converted;
    }

    public function Header() {
        $this->SetFont('Arial','B',11);
        $this->Cell(0,6,'HOLY CROSS COLLEGE',0,1,'C');
        $this->SetFont('Arial','',9);
        $this->Cell(0,5,'Department of Civil Engineering',0,1,'C');
        $this->SetFont('Arial','B',9);
        $this->Cell(0,5,$this->answerKey ? 'TEACHER ANSWER KEY - NOT FOR STUDENT DISTRIBUTION' : 'EXAMINATION PAPER',0,1,'C');
        $this->Ln(4);
    }

    public function Footer() {
        $this->SetY(-13);
        $this->SetFont('Arial','',8);
        $this->Cell(0,5,'QuestBank | '.($this->answerKey ? 'Teacher answer key' : 'Student examination').' | Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    private function paragraph(string $text, bool $bold = false): void {
        $this->SetFont('Arial',$bold ? 'B' : '',10);
        $this->MultiCell(0,5,self::textValue($text));
    }

    private function answerSpace(float $height): void {
        if ($this->GetY() + $height > 277) $this->AddPage();
        $this->SetDrawColor(100,100,100);
        $this->Rect(16,$this->GetY(),178,$height);
        $this->Ln($height + 4);
    }

    public static function build(array $exam, array $questions, bool $withAnswers = false): self {
        $pdf = new self('P','mm','A4');
        $pdf->answerKey = $withAnswers;
        $pdf->SetMargins(16,12,16);
        $pdf->SetAutoPageBreak(true,20);
        $pdf->AliasNbPages();
        $pdf->SetTitle(self::textValue($exam['title']));
        $pdf->AddPage();
        $pdf->paragraph($exam['title'],true);
        $pdf->paragraph('Subject: '.($exam['subject'] ?? '').' | Term: '.($exam['term'] ?? ''));
        $points = array_sum(array_map(fn($q)=>(float)($q['points'] ?? 1),$questions));
        $pdf->paragraph('Items: '.count($questions).' | Total points: '.$points.' | Time: '.(int)($exam['time_limit'] ?? 60).' minutes');
        $pdf->Ln(3);
        $pdf->paragraph('Name: __________________________________________');
        $pdf->paragraph('Section / Year: ____________________   Score: ______________');
        $pdf->Ln(3);
        $pdf->paragraph('Read each question carefully. Write your answers clearly. Show your working for problem-solving questions.');
        $pdf->Ln(5);
        foreach ($questions as $i=>$q) {
            if ($pdf->GetY() > 240) $pdf->AddPage();
            $type = $q['question_type'] ?? 'identification';
            $pdf->paragraph(($i+1).'. '.$q['question_text'].'  ['.($q['points'] ?? 1).' pt]',true);
            if ($type === 'multiple_choice') {
                foreach (['a','b','c','d'] as $letter) {
                    if (isset($q['option_'.$letter]) && $q['option_'.$letter] !== '') $pdf->paragraph('    '.strtoupper($letter).'. '.$q['option_'.$letter]);
                }
            } elseif ($type === 'true_false') {
                $pdf->paragraph('    True / False');
            } elseif ($type === 'matching') {
                $pairs = json_decode($q['matching_pairs'] ?? '{}',true) ?: [];
                $choices = array_values(array_unique(array_values($pairs)));
                sort($choices,SORT_STRING);
                $pdf->paragraph('Prompts:');
                foreach (array_keys($pairs) as $j=>$prompt) $pdf->paragraph('    '.($j+1).'. '.$prompt);
                $pdf->paragraph('Choices:');
                foreach ($choices as $j=>$choice) $pdf->paragraph('    '.($j+1).'. '.$choice);
            }
            $pdf->Ln(2);
            if ($withAnswers) {
                $pdf->paragraph('Correct answer: '.($q['correct_answer'] ?? ''),true);
                if (!empty($q['formula_latex'])) $pdf->paragraph('Formula: '.$q['formula_latex']);
                if (!empty($q['explanation'])) $pdf->paragraph('Explanation / Solution: '.$q['explanation']);
            } else {
                $pdf->paragraph(in_array($type,['problem_solving','math_formula'],true) ? 'Answer and working:' : 'Answer:');
                $pdf->answerSpace(in_array($type,['problem_solving','math_formula'],true) ? 42 : 14);
            }
            $pdf->Ln(5);
        }
        return $pdf;
    }
}
