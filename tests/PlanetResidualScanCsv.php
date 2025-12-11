<?php
require __DIR__ . '/bootstrap.php';
use Swisseph\Constants;

/*
Generate CSV summary of max residuals per planet vs swetest over a JD grid.
*/

$swetest = __DIR__ . '/swetest64.exe';
$ephe = realpath(__DIR__ . '/../../eph/ephe');
if (!is_file($swetest) || $ephe === false) { fwrite(STDERR, "swetest64.exe or ephe path missing\n"); exit(2);}

$planets = [
    Constants::SE_MERCURY => 'mercury',
    Constants::SE_VENUS => 'venus',
    Constants::SE_MARS => 'mars',
    Constants::SE_JUPITER => 'jupiter',
    Constants::SE_SATURN => 'saturn',
    Constants::SE_URANUS => 'uranus',
    Constants::SE_NEPTUNE => 'neptune',
    Constants::SE_PLUTO => 'pluto',
];

$start = 2430000.5; $end = 2470000.5; $step = 3652.5;

function jdtt_to_utc_parts(float $jd_tt): array { $jd_ut=$jd_tt; for($k=0;$k<2;$k++){ $dt=swe_deltat($jd_ut); $jd_ut=$jd_tt-$dt;} [$y,$m,$d,$hour,$min,$sec]=swe_jd_to_utc($jd_ut,1); return [sprintf('%d.%d.%d',$d,$m,$y), sprintf('%02d:%02d:%02d',$hour,$min,(int)round($sec))]; }
function parse_equ(string $out): array { $norm=preg_replace('/\s+/',' ',trim($out)); if(!preg_match('/(\d+)h\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+(\d+\.\d+)/',$norm,$m)) throw new RuntimeException('parse equ failed'); $rah=(int)$m[1];$ram=(int)$m[2];$ras=(float)$m[3]; $sign=strpos($m[4],'-')===0?-1:1; $deg=abs((int)$m[4]); $decm=(int)$m[5]; $decs=(float)$m[6]; $r=(float)$m[7]; $ra_deg=($rah+$ram/60+$ras/3600)*15.0; $dec_deg=$sign*($deg+$decm/60+$decs/3600); return ['ra'=>$ra_deg,'dec'=>$dec_deg,'r'=>$r]; }
function parse_ecl(string $out): array { $norm=preg_replace('/\s+/',' ',trim($out)); if(!preg_match('/([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+([\-+]?\d+)°\s*(\d+)' . "'" . '\s*(\d+(?:\.\d+)?)\s+(\d+\.\d+)/',$norm,$m)) throw new RuntimeException('parse ecl failed'); $lon=((int)$m[1])+((int)$m[2])/60+((float)$m[3])/3600; if($lon<0)$lon=-$lon; $sign=strpos($m[4],'-')===0?-1:1; $latdeg=abs((int)$m[4]); $latm=(int)$m[5]; $lats=(float)$m[6]; $r=(float)$m[7]; $lat=$sign*($latdeg+$latm/60+$lats/3600); return ['lon'=>$lon,'lat'=>$lat,'r'=>$r]; }

$rows = [];
foreach ($planets as $ipl => $name) {
    $max = ['ecl'=>['lon'=>0,'lat'=>0,'r'=>0],'equ'=>['ra'=>0,'dec'=>0,'r'=>0]];
    for ($jd=$start; $jd <= $end; $jd += $step) {
        [$date,$time]=jdtt_to_utc_parts($jd);
        $cmdEqu=sprintf('cmd /c ""%s" -b%s -ut%s -p%d -fPTADR -head -eswe -edir%s"',$swetest,$date,$time,$ipl,$ephe);
        $cmdEcl=sprintf('cmd /c ""%s" -b%s -ut%s -p%d -fLBR -head -eswe -edir%s"',$swetest,$date,$time,$ipl,$ephe);
        $outEqu=shell_exec($cmdEqu); $outEcl=shell_exec($cmdEcl);
        if(!$outEqu||!$outEcl) continue;
        $refEqu=parse_equ($outEqu); $refEcl=parse_ecl($outEcl);
        $xx=[]; $serr=null; if(swe_calc($jd,$ipl,0|Constants::SEFLG_SPEED,$xx,$serr)>=0){
            $max['ecl']['lon']=max($max['ecl']['lon'],abs($xx[0]-$refEcl['lon']));
            $max['ecl']['lat']=max($max['ecl']['lat'],abs($xx[1]-$refEcl['lat']));
            $max['ecl']['r']=max($max['ecl']['r'],abs($xx[2]-$refEcl['r']));
        }
        $xx=[]; $serr=null; if(swe_calc($jd,$ipl,Constants::SEFLG_EQUATORIAL|Constants::SEFLG_SPEED,$xx,$serr)>=0){
            $max['equ']['ra']=max($max['equ']['ra'],abs($xx[0]-$refEqu['ra']));
            $max['equ']['dec']=max($max['equ']['dec'],abs($xx[1]-$refEqu['dec']));
            $max['equ']['r']=max($max['equ']['r'],abs($xx[2]-$refEqu['r']));
        }
    }
    $rows[] = $name . ',' .
        sprintf('%.5f,%.5f,%.8f,%.5f,%.5f,%.8f',
            $max['ecl']['lon'],$max['ecl']['lat'],$max['ecl']['r'],
            $max['equ']['ra'],$max['equ']['dec'],$max['equ']['r']);
}

$outFile = __DIR__ . '/planet_residuals.csv';
file_put_contents($outFile, "planet,ecl_lon,ecl_lat,ecl_r,equ_ra,equ_dec,equ_r\n" . implode("\n", $rows) . "\n");

echo "Written CSV: $outFile\n";
