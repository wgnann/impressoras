<?php

namespace App\Helpers;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PrintingHelper
{
    public static function pdfinfo($file) {
        $info = [];

        $pdfinfo = "/usr/bin/pdfinfo";
        if (!File::exists($pdfinfo)) {
            throw new \Exception("Instalar pdfinfo: apt install poppler-utils.");
        }

        $process = new Process([$pdfinfo, $file]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $matches) != 1)
            throw new \Exception("Problema no PDF: número de páginas não encontrado.");
        $info['pages'] = $matches[1];

        if (preg_match('/^Page size:\s+(\d+\.?\d+)\sx\s(\d+\.?\d+)/m', $process->getOutput(), $matches) != 1)
            throw new \Exception("Problema no PDF: tamanho indefinido.");

        $info['width'] = $matches[1];
        $info['height'] = $matches[2];

        return $info;
    }

    public static function pdfjam($file, $shrink = false, $pages_per_sheet = 1, $start_page = 1, $end_page = null) {
        $pdfinfo = PrintingHelper::pdfinfo($file);

        $pdfjam = "/usr/bin/pdfjam";
        if (!File::exists($pdfjam)) {
            throw new \Exception("Instalar pdfjam: apt install texlive-extra-utils.");
        }

        $nup = "1x1";
        if ($pdfinfo['width'] > $pdfinfo['height']) {
            $mode = "--landscape";
            switch ($pages_per_sheet) {
                case 2:
                    $mode = "--no-landscape";
                    $nup = "1x2";
                    break;
                case 4:
                    $nup = "2x2";
                    break;
            }
        }
        else {
            $mode = "--no-landscape";
            switch ($pages_per_sheet) {
                case 2:
                    $mode = "--landscape";
                    $nup = "2x1";
                    break;
                case 4:
                    $nup = "2x2";
                    break;
            }
        }

        $scale = 1;
        if ($shrink) {
            // 6mm margin in A4 paper
            $scale = 0.95;
        }

        $pdf = File::dirname($file) . "/" . File::name($file) . "pdfjam.pdf";
        $command = [
            $pdfjam, $mode,
            "--a4paper",
            "--nup", $nup,
            "--scale", $scale,
            "--outfile", $pdf,
            $file, "$start_page-$end_page"
        ];

        $process = new Process($command);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (!File::exists($pdf))
            throw new \Exception("PDF não encontrado");

        return $pdf;
    }

    // precisa refatorar
    public static function pdfx($file, $color) {
        $pdftocairo = "/usr/bin/pdftocairo";

        if (!File::exists($pdftocairo)) {
            throw new \Exception("Instalar pdftocairo: apt install poppler-utils.");
        }

        $pdf = File::dirname($file) . "/" . File::name($file) . "pdfx.pdf";

        $timeout = (int) config('impressoras.gs_timeout');
        $process = new Process([
            $pdftocairo,
            "-pdf",
            "-paper", "A4",
            $file,
            $pdf
        ]);
        $process->setTimeout($timeout);
        $process->start();
        $i = 0;
        while ($process->isRunning() && $i < $timeout/2) {
            sleep(5);
            $i++;
        }

        if (!$process->isSuccessful()) return '';
        return $pdf;
    }

    //ou remove ou adiciona opção para usar
    public static function pdfy($file, $color) {
        $ghostscript = "/usr/bin/gs";

        if (!File::exists($ghostscript)) {
            throw new \Exception("Instalar ghostscript: apt install ghostscript.");
        }

        $pdf = File::dirname($file) . "/" . File::name($file) . "pdfx.pdf";

        $strategy = 'Gray';
        if ($color) {
            $strategy = 'CMYK';
        }

        $parallel_pdfx = base_path()."/resources/parallel_pdfx.sh";
        $base = base_path()."/resources";
        $timeout = (int) config('impressoras.gs_timeout');
        $process = new Process([
            $parallel_pdfx,
            $base,
            $file,
            $strategy,
            $pdf
        ]);
        $process->setTimeout($timeout);

        /**
         * Vamos esperar a metade do tempo do timeout
         * Contornando assincronamente a mensagem de exception do timeout, baseado em:
         * https://symfony.com/doc/current/components/process.html#process-timeout
         **/
        $process->start();
        $i = 0;
        while ($process->isRunning() && $i < $timeout/2) {
            sleep(1);
            $i++;
        }

        if (!$process->isSuccessful()) return '';
        return $pdf;
    }
}
