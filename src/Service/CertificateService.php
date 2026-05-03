<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class CertificateService
{
    /**
     * @param array{student_name:string,class_title:string,instructor_name:string,issued_on:string} $data
     */
    public function generate(array $data): string
    {
        $student = $this->escape($data['student_name']);
        $classTitle = $this->escape($data['class_title']);
        $instructor = $this->escape($data['instructor_name']);
        $issuedOn = $this->escape($data['issued_on']);

        $html = $this->buildHtml($student, $classTitle, $instructor, $issuedOn);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildHtml(string $student, string $classTitle, string $instructor, string $issuedOn): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }
        .page {
            width: 100%;
            height: 100%;
            padding: 42px;
            box-sizing: border-box;
            border: 9px solid #0f766e;
            position: relative;
            background: #ffffff;
        }
        .top {
            text-align: center;
            margin-bottom: 24px;
            color: #0f766e;
        }
        .brand {
            font-size: 40px;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0;
        }
        .subtitle {
            margin: 4px 0 0;
            color: #0369a1;
            font-size: 18px;
        }
        .title {
            text-align: center;
            margin-top: 6px;
            font-size: 34px;
            font-weight: 700;
            color: #0f172a;
        }
        .line {
            width: 300px;
            margin: 16px auto;
            border-top: 2px solid #f59e0b;
        }
        .content {
            text-align: center;
            margin-top: 18px;
            font-size: 19px;
            line-height: 1.7;
        }
        .student {
            font-size: 38px;
            color: #b45309;
            font-weight: 700;
            margin: 8px 0;
        }
        .class {
            font-size: 29px;
            color: #0f766e;
            font-weight: 700;
            margin: 8px 0;
        }
        .meta {
            margin-top: 30px;
            text-align: center;
            color: #334155;
            font-size: 15px;
        }
        .signature-wrap {
            margin-top: 30px;
            text-align: center;
        }
        .signature-line {
            width: 240px;
            border-top: 1px solid #0f172a;
            margin: 0 auto 6px;
        }
        .issued {
            position: absolute;
            bottom: 16px;
            left: 0;
            right: 0;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="top">
        <p class="brand">GHRAMI</p>
        <p class="subtitle">Learning Platform</p>
    </div>

    <div class="title">Certificate of Completion</div>
    <div class="line"></div>

    <div class="content">
        This certificate is proudly presented to
        <div class="student">{$student}</div>
        for successfully completing the class
        <div class="class">"{$classTitle}"</div>
    </div>

    <div class="meta">Instructed by {$instructor} • Issued on {$issuedOn}</div>

    <div class="signature-wrap">
        <div class="signature-line"></div>
        <div>{$instructor}</div>
        <div style="font-size:12px; color:#64748b;">Instructor</div>
    </div>

    <div class="issued">Ghrami Certification Document</div>
</div>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
