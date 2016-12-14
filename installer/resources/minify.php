<?php


error_reporting(0);

include_once('../api/Simpla.php');
$simpla = new Simpla();

define('CACHE_PATH', $simpla->config->root_dir.'cache/minify/');


$sURL = $_SERVER['REQUEST_URI'];
$purl = parse_url($simpla->config->root_url);

if (isset($purl['path']) && $purl['path']!=='/' )  
	$sURL = str_replace($purl['path'], '', $sURL); 

$sourceFile = $simpla->config->root_dir . $sURL;

if (!file_exists($sourceFile))  exit(); // �� ������ �������� ��� �����������.

#VQMOD#
require_once($simpla->config->root_dir.'/vqmod/vqmod.php');
VQMod::bootup();
$sourceFile = VQMod::modCheck($sourceFile);
#VQMOD_END#


$bGzip = false;
$sCachedName = str_replace('/', '%', $sURL);   // ����� ��� � ����

$cacheFile 	= CACHE_PATH . date('YmdHis', filemtime($sourceFile)) . '_' . $sCachedName;


header('Content-type: ' . (false!==stripos($sURL, '.css') ? 'text/css' : 'text/javascript'));
header('Vary: Accept-Encoding');
header('Cache-Control: max-age=0');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $simpla->config->static_expire_time) . ' GMT');



// ���� �������, ��� ������� ��������� ���-�� �������������
if(isset($_SERVER['HTTP_ACCEPT_ENCODING'])){
	if (stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false){
		if(function_exists('ob_gzhandler') && !ini_get('zlib.output_compression')){
			$bGzip = true;
			header('Content-Encoding: gzip');
		}
	}
}


if ($bGzip){
	if (!file_exists($cacheFile)){
		removeOldCache($sCachedName);
		
		if(!is_dir(CACHE_PATH)) 
			mkdir(CACHE_PATH, 0755, true);
		
		$cacheData = gzencode(getFileContents($sourceFile), $simpla->config->static_gzip_level, FORCE_GZIP);
		file_put_contents($cacheFile, $cacheData);
		die($cacheData);
	} else {
		readfile($cacheFile);
	}
	exit;
}
	

die(getFileContents($sourceFile));




function removeOldCache($sFileName){
	
	foreach(glob(CACHE_PATH.'*_'.$sFileName) as $old_cache_file)
		unlink($old_cache_file);
	
}


function optimcss($s){
	#�������� ������������� ����������� /* ... */
	if (strpos($s, '/*') !== false) $s = preg_replace('~/\*.*?\*/~sSX','', $s);
	if (preg_match('/[\x03-\x20]/sSX', $s)){ #�������� ������ �������
	
		/* IE7 ����� ����� ����������� ������� ������ ������ ����� ������� � �������, ���� ��� ���, �� CSS ���������� ��������, ��������:
		  background:url(/img/cat.png)0 0 no-repeat;*/
		$s = preg_replace('/\)[\x03-\x20]++(?=[-a-zA-Z\d])/sSX', ")\x01", $s); #fix for IE7
		$a = preg_split('/([{}():;,%!=]++)/sSX', $s, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		$s = implode('', array_map('trim', $a));
		$s = str_replace(")\x01", ') ', $s); #fix for IE7
		$s = preg_replace('/[\x03-\x20]++/sSX', ' ', $s);
		/*
		  ������������� ������� (������������� �������� ������� ������������ ������-���� ��������� �������� �������)
			em: 'font-size' ���������������� ������; 
			ex: 'x-height' ���������������� ������; 
			px: �������, ������������ ���������� ���������.
		  ���������� ������� ��������� (������������ ������ �����, ����� �������� ���������� �������� ��������� ����������)
			in: inches/����� -- 1 ���� ����� 2.54 ����������.
			cm: ����������
			mm: ����������
			pt: points/������ - �����, ������������ �  CSS2, ����� 1/72 �����. 
			pc: picas/���� -- 1 ���� ����� 12 �������.
		*/
		#converts '0px' to '0'
		$s = preg_replace('/ (?<![\d\.])
							 0(?:em|ex|px|in|cm|mm|pt|pc|%)
							 (?![a-zA-Z%])
						   /sxSX', '0', $s);
		#converts '#rrggbb' to '#rgb' or '#rrggbbaa' to '#rgba';
		#IE6 incorrect parse #rgb in entry, like 'filter: progid:DXImageTransform.Microsoft.Gradient(startColorStr=#ffffff, endColorStr=#c9d1d7, gradientType=0);'
		$s = preg_replace('/ :\# ([\da-fA-F])\1  #rr
								 ([\da-fA-F])\2  #gg
								 ([\da-fA-F])\3  #bb
								 (?:([\da-fA-F])\4)?+  #aa
							 (?![\da-fA-F])/sxSX', ':#$1$2$3$4', $s);
	}
	return $s;
}

function getFileContents($sFile){
	global $simpla;
	
	$sContent = file_get_contents($sFile);
	$ext = strtolower(pathinfo($sFile, PATHINFO_EXTENSION));
	switch($ext){
		case 'css':
			if($simpla->config->minify_css && strtolower(substr($sFile, -7, 3))!=='min')
				$sContent = optimcss($sContent);
			break;
		case 'js':
			if($simpla->config->minify_js && strtolower(substr($sFile, -6, 3))!=='min'){
				require 'jsmin.php';
				$sContent = JSMin::minify($sContent);	
			}
			break;		
	}

	return $sContent;
}



