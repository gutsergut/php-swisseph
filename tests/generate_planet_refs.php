<?php
require __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

/*
 * Generic reference generator using swetest64.exe for any planet code (SE_* external index).
 * Usage (PowerShell): php tests/generate_planet_refs.php 5   # Jupiter (SE_JUPITER)
 * Writes tests/<name>_refs.json where <name> is lowercase planet name.
 */

$map = [
    Constants::SE_SUN => 'sun',
    Constants::SE_MOON => 'moon',
    Constants::SE_MERCURY => 'mercury',
    Constants::SE_VENUS => 'venus',
    Constants::SE_MARS => 'mars',
    Constants::SE_JUPITER => 'jupiter',
    Constants::SE_SATURN => 'saturn',
    Constants::SE_URANUS => 'uranus',
    Constants::SE_NEPTUNE => 'neptune',
    Constants::SE_PLUTO => 'pluto',
];

if ($argc < 2) {
    fwrite(STDERR, "Planet code required (e.g. 5 for Jupiter).\n");
    exit(1);
}
$ipl = (int)$argv[1];
if (!isset($map[$ipl])) {
    fwrite(STDERR, "Unsupported planet code: $ipl\n");
    exit(2);
}
$name = $map[$ipl];

$swetest = realpath(__DIR__ . '/swetest64.exe');
if ($swetest === false || !is_file($swetest)) {
    fwrite(STDERR, "swetest64.exe not found in tests folder\n");
    exit(3);
}
$ephe = realpath(__DIR__ . '/../../eph/ephe');
if ($ephe === false || !is_dir($ephe)) {
    fwrite(STDERR, "Ephemeris path not found\n");
    exit(4);
}

$cases = [2451545.0, 2453000.5, 2448000.5, 2460000.5];

function jdtt_to_utc_parts(float $jd_tt): array {
    $jd_ut = $jd_tt;
    for ($k=0;$k<2;$k++){ $dt = swe_deltat($jd_ut); $jd_ut = $jd_tt - $dt; }
    [$y,$m,$d,$hour,$min,$sec] = swe_jd_to_utc($jd_ut,1);
    $date = sprintf('%d.%d.%d',$d,$m,$y); $time = sprintf('%02d:%02d:%02d',$hour,$min,(int)round($sec));
    return [$date,$time];
}

function parse_equ(string $out): array {
    $norm = preg_replace('/\s+/', ' ', trim($out));
    if (!preg_match('/(\d+)h\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+(\d+\.\d+)/',$norm,$m)) {
        throw new RuntimeException('parse equ failed: '.$out);
    }
    $rah=(int)$m[1];$ram=(int)$m[2];$ras=(float)$m[3];
    $sign=strpos($m[4],'-')===0?-1:1; $deg=abs((int)$m[4]); $decm=(int)$m[5]; $decs=(float)$m[6]; $r=(float)$m[7];
    $ra_deg=($rah+$ram/60+$ras/3600)*15.0; $dec_deg=$sign*($deg+$decm/60+$decs/3600);
    return ['ra'=>$ra_deg,'dec'=>$dec_deg,'r'=>$r];
}
function parse_ecl(string $out): array {
    $norm=preg_replace('/\s+/', ' ', trim($out));
    if(!preg_match('/([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+(\d+\.\d+)/',$norm,$m)){
        throw new RuntimeException('parse ecl failed: '.$out);
    }
    $lon=((int)$m[1])+((int)$m[2])/60+((float)$m[3])/3600; if($lon<0)$lon=-$lon;
    $sign=strpos($m[4],'-')===0?-1:1; $latdeg=abs((int)$m[4]); $latm=(int)$m[5]; $lats=(float)$m[6]; $r=(float)$m[7];
    $lat=$sign*($latdeg+$latm/60+$lats/3600);
    return ['lon'=>$lon,'lat'=>$lat,'r'=>$r];
}

$refs=[];
foreach($cases as $jd){
    [$date,$time]=jdtt_to_utc_parts($jd);
    $cmdEqu=sprintf('cmd /c ""%s" -b%s -ut%s -p%d -fPTADR -head -eswe -edir%s"',$swetest,$date,$time,$ipl,$ephe);
    $cmdEcl=sprintf('cmd /c ""%s" -b%s -ut%s -p%d -fLBR -head -eswe -edir%s"',$swetest,$date,$time,$ipl,$ephe);
    $outEqu=shell_exec($cmdEqu); $outEcl=shell_exec($cmdEcl);
    if(!$outEqu||!$outEcl) throw new RuntimeException('swetest failure');
    $refs[]=['jd'=>$jd,'equ'=>parse_equ($outEqu),'ecl'=>parse_ecl($outEcl)];
}

$outFile=__DIR__ . '/' . $name . '_refs.json';
file_put_contents($outFile,json_encode(['refs'=>$refs],JSON_PRETTY_PRINT));
echo "Written: $outFile\n";
