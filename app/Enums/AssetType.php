<?php

namespace App\Enums;

enum AssetType: string
{
    case ReportPdf = 'report_pdf';
    case Dataset = 'dataset';
    case Teaser = 'teaser';
}
