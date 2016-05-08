<?php

	function replace_url_path($base,$rel)
	{
		/* return if already absolute URL */
		if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;

		/* queries and anchors */
		if ($rel[0]=='#' || $rel[0]=='?')
		{
			if(($intpos = strpos($base,'?'))!==false)
				$base = substr($base,0,$intpos);

			return $base.$rel;
		}

		$qry = "";
		if( preg_match('/(.*?)\?(.*)/', $rel, $match) )
		{
			$rel = $match[1];
			$qry = "?".$match[2];
		}
		
		/* parse base URL and convert to local variables:
		   $scheme, $host, $path */
		extract(parse_url($base));

		if(!isset($path))
			$path = "";

		/* remove non-directory element from path */
		$path = preg_replace('#/[^/]*$#', '', $path);

		/* destroy path if relative url points to root */
		if ($rel[0] == '/') $path = '';

		/* dirty absolute URL */
		$abs = "$host$path/$rel";

		/* replace '//' or '/./' or '/foo/../' with '/' */
		$re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
		for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}

		/* absolute URL is ready! */
		return $scheme.'://'.$abs.$qry;
	}

	function ParseText($str)
	{
		$str = trim($str);
		$str = str_replace('\"',"\\\"",$str);
		$str = str_replace('\'',"",$str);
		$str = preg_replace('/ +/'," ",$str);

		return $str;
	}

	function  ParseDate($dat)
	{
        $Mtom=array(
          "Jan"=>"01",
          "Feb"=>"02",
          "Mar"=>"03",
          "Apr"=>"04",
          "May"=>"05",
          "Jun"=>"06",
          "Jul"=>"07",
          "Aug"=>"08",
          "Sep"=>"09",
          "Oct"=>"10",
          "Nov"=>"11",
          "Dec"=>"12",
        );

        if( preg_match('/\w+, (\d+) (\w+) (\d+) (\d+):(\d+):(\d+)/', $dat, $matches) )
        	return $matches[3]."-".$Mtom[$matches[2]]."-".$matches[1] ." ".$matches[4] .":".$matches[5].":".$matches[6];
	}



	global $specialchars;


	$specialchars = get_html_translation_table (HTML_ENTITIES);
	$specialchars2 = array('&apos;'=>'&#39;', '&minus;'=>'&#45;', '&circ;'=>'&#94;', '&tilde;'=>'&#126;', '&Scaron;'=>'&#138;', '&lsaquo;'=>'&#139;', '&OElig;'=>'&#140;', '&lsquo;'=>'&#145;', '&rsquo;'=>'&#146;', '&ldquo;'=>'&#147;', '&rdquo;'=>'&#148;', '&bull;'=>'&#149;', '&ndash;'=>'&#150;', '&mdash;'=>'&#151;', '&tilde;'=>'&#152;', '&trade;'=>'&#153;', '&scaron;'=>'&#154;', '&rsaquo;'=>'&#155;', '&oelig;'=>'&#156;', '&Yuml;'=>'&#159;', '&yuml;'=>'&#255;', '&OElig;'=>'&#338;', '&oelig;'=>'&#339;', '&Scaron;'=>'&#352;', '&scaron;'=>'&#353;', '&Yuml;'=>'&#376;', '&fnof;'=>'&#402;', '&circ;'=>'&#710;', '&tilde;'=>'&#732;', '&Alpha;'=>'&#913;', '&Beta;'=>'&#914;', '&Gamma;'=>'&#915;', '&Delta;'=>'&#916;', '&Epsilon;'=>'&#917;', '&Zeta;'=>'&#918;', '&Eta;'=>'&#919;', '&Theta;'=>'&#920;', '&Iota;'=>'&#921;', '&Kappa;'=>'&#922;', '&Lambda;'=>'&#923;', '&Mu;'=>'&#924;', '&Nu;'=>'&#925;', '&Xi;'=>'&#926;', '&Omicron;'=>'&#927;', '&Pi;'=>'&#928;', '&Rho;'=>'&#929;', '&Sigma;'=>'&#931;', '&Tau;'=>'&#932;', '&Upsilon;'=>'&#933;', '&Phi;'=>'&#934;', '&Chi;'=>'&#935;', '&Psi;'=>'&#936;', '&Omega;'=>'&#937;', '&alpha;'=>'&#945;', '&beta;'=>'&#946;', '&gamma;'=>'&#947;', '&delta;'=>'&#948;', '&epsilon;'=>'&#949;', '&zeta;'=>'&#950;', '&eta;'=>'&#951;', '&theta;'=>'&#952;', '&iota;'=>'&#953;', '&kappa;'=>'&#954;', '&lambda;'=>'&#955;', '&mu;'=>'&#956;', '&nu;'=>'&#957;', '&xi;'=>'&#958;', '&omicron;'=>'&#959;', '&pi;'=>'&#960;', '&rho;'=>'&#961;', '&sigmaf;'=>'&#962;', '&sigma;'=>'&#963;', '&tau;'=>'&#964;', '&upsilon;'=>'&#965;', '&phi;'=>'&#966;', '&chi;'=>'&#967;', '&psi;'=>'&#968;', '&omega;'=>'&#969;', '&thetasym;'=>'&#977;', '&upsih;'=>'&#978;', '&piv;'=>'&#982;', '&ensp;'=>'&#8194;', '&emsp;'=>'&#8195;', '&thinsp;'=>'&#8201;', '&zwnj;'=>'&#8204;', '&zwj;'=>'&#8205;', '&lrm;'=>'&#8206;', '&rlm;'=>'&#8207;', '&ndash;'=>'&#8211;', '&mdash;'=>'&#8212;', '&lsquo;'=>'&#8216;', '&rsquo;'=>'&#8217;', '&sbquo;'=>'&#8218;', '&ldquo;'=>'&#8220;', '&rdquo;'=>'&#8221;', '&bdquo;'=>'&#8222;', '&dagger;'=>'&#8224;', '&Dagger;'=>'&#8225;', '&bull;'=>'&#8226;', '&hellip;'=>'&#8230;', '&permil;'=>'&#8240;', '&prime;'=>'&#8242;', '&Prime;'=>'&#8243;', '&lsaquo;'=>'&#8249;', '&rsaquo;'=>'&#8250;', '&oline;'=>'&#8254;', '&frasl;'=>'&#8260;', '&euro;'=>'&#8364;','&image;'=>'&#8465;', '&weierp;'=>'&#8472;', '&real;'=>'&#8476;', '&trade;'=>'&#8482;', '&alefsym;'=>'&#8501;', '&larr;'=>'&#8592;', '&uarr;'=>'&#8593;', '&rarr;'=>'&#8594;', '&darr;'=>'&#8595;', '&harr;'=>'&#8596;', '&crarr;'=>'&#8629;', '&lArr;'=>'&#8656;', '&uArr;'=>'&#8657;', '&rArr;'=>'&#8658;', '&dArr;'=>'&#8659;', '&hArr;'=>'&#8660;', '&forall;'=>'&#8704;', '&part;'=>'&#8706;', '&exist;'=>'&#8707;', '&empty;'=>'&#8709;', '&nabla;'=>'&#8711;', '&isin;'=>'&#8712;', '&notin;'=>'&#8713;', '&ni;'=>'&#8715;', '&prod;'=>'&#8719;', '&sum;'=>'&#8721;', '&minus;'=>'&#8722;', '&lowast;'=>'&#8727;', '&radic;'=>'&#8730;', '&prop;'=>'&#8733;', '&infin;'=>'&#8734;', '&ang;'=>'&#8736;', '&and;'=>'&#8743;', '&or;'=>'&#8744;', '&cap;'=>'&#8745;', '&cup;'=>'&#8746;', '&int;'=>'&#8747;', '&there4;'=>'&#8756;', '&sim;'=>'&#8764;', '&cong;'=>'&#8773;', '&asymp;'=>'&#8776;', '&ne;'=>'&#8800;', '&equiv;'=>'&#8801;', '&le;'=>'&#8804;', '&ge;'=>'&#8805;', '&sub;'=>'&#8834;', '&sup;'=>'&#8835;', '&nsub;'=>'&#8836;', '&sube;'=>'&#8838;', '&supe;'=>'&#8839;', '&oplus;'=>'&#8853;', '&otimes;'=>'&#8855;', '&perp;'=>'&#8869;', '&sdot;'=>'&#8901;', '&lceil;'=>'&#8968;', '&rceil;'=>'&#8969;', '&lfloor;'=>'&#8970;', '&rfloor;'=>'&#8971;', '&lang;'=>'&#9001;', '&rang;'=>'&#9002;', '&loz;'=>'&#9674;', '&spades;'=>'&#9824;', '&clubs;'=>'&#9827;', '&hearts;'=>'&#9829;', '&diams;'=>'&#9830;');
	$specialchars3 = array(

		'&nbsp;'	=> '&#160;',
		'&amp;'		=> '&#38;',
		'&hellip;'	=> '&#133;',
		'&euro;'	=> '&#128;',
		'&quot;'	=> '&#34;',

		'&lt;'		=> '&#60;',
		'&gt;'		=> '&#62;',
	);

	$specialchars = array_flip($specialchars);
	$specialchars = array_merge( $specialchars, $specialchars2 );
	$specialchars = array_merge( $specialchars, $specialchars3 );

	function replacecallbak($matches)
	{
		global $specialchars;
		@$trad = $specialchars[$matches[0]];
	#	$trad = chr($matches[1]);

		return $trad ? $trad : $matches[0];
	}

	function translate($str)
	{
		return preg_replace_callback('/&[0-9a-z]+;/mi', 'replacecallbak' , $str );
	}

	function utf8_to_html ($data)
	{
		return preg_replace("/([\\xC0-\\xF7]{1,1}[\\x80-\\xBF]+)/e", '_utf8_to_html("\\1")', $data);
	}

	function _utf8_to_html ($data)
	{
		$ret = 0;
		foreach((str_split(strrev(chr((ord($data{0}) % 252 % 248 % 240 % 224 % 192) + 128) . substr($data, 1)))) as $k => $v)
			$ret += (ord($v) % 128) * pow(64, $k);
		return "&#$ret;";
	}

	function arreglar($html)
	{
		global $delete_cdata;
		if($delete_cdata)
			$html = preg_replace('/\/\/<!\[CDATA\[.*?\]>/s', "", $html);
/*
		$html = str_replace('&nbsp;', 	'&#160;', $html);
		$html = str_replace('&amp;', 	'&#38;', $html);
		$html = str_replace('&hellip;', '&#133;', $html);
		$html = str_replace('&euro;',	'&#128;', $html);
		$html = str_replace('&quot;',	'&#34;', $html);


		$html = str_replace('&lt;',	'&#60;', $html);
		$html = str_replace('&gt;',	'&#62;', $html);
*/

#		$html = str_replace(array_keys($specialchars3), array_values($specialchars3), $html);

		$html = translate($html);
		$html = utf8_encode($html);
		$html = utf8_to_html($html);

		return $html;
	}


    function html_to_utf8($str)
    {
		return preg_replace_callback('/&#([0-9]+);/mi', '_html_to_utf8' , $str );
    }

    function _html_to_utf8($data)
    {
    	return chr($data[1]);
	}


	function ver($html){
		return str_replace("\n","<br>",str_replace("<","&lt;",str_replace(">","&gt;",$html)));
	}

	function destroy(&$obj)
	{
		$obj->destruct();
		unset($obj);
	}

	function add_log($txt,$spider_name="robots")
	{
		file_put_contents( "{$spider_name}.log", "$txt\n", FILE_APPEND );
		echo "$txt\n";
		return;
	}

	function normalize($txt)
	{
		if(!is_string($txt))
			throw new Exception(print_r($txt,1)."no es un string");

		return trim(preg_replace('/\s+/',' ',$txt));
	}

	function normalizar_precio($precio)
	{
		preg_match('/[\d,.]+/',$precio,$match);
		return str_replace(',','.', str_replace('.','', $match[0]));
	}

	function parseFields($arr)
	{
		$ret_arr = array();
		foreach($arr as $key => $val)
			array_push($ret_arr,urlencode($key)."=".urlencode($val));

		return join("&",$ret_arr);
	}

	function parseData($arr)
	{
		$ret_arr = array();
		foreach($arr as $key => $val)
			array_push($ret_arr,"$key=$val");

		return join("\n",$ret_arr);
	}

	function getFormFields($inputs)
	{
		$fields = array();
		foreach($inputs as $input)
		{
			$inp_nam = $input->getAttr('name');
			if( $inp_nam != "" )
			{
				if(strcasecmp($input->getName(),"input")==0)
				{
					// para los radiobuttons: (que se salte los values no seleccionados)
					if(strcasecmp($input->getAttr('type'),"radio")==0)
						if( !$input->getAttr('checked') )
							continue;

					$fields[$inp_nam] = $input->getAttr('value');
				}

				if(strcasecmp($input->getName(),"select")==0)
				{
					$opts = $input->select('option');

					// por si no hay ninguno seleccionado
					if($opts->length())
						$fields[$inp_nam] = $opts->first()->getAttr('value');

					foreach($opts as $opt)
						if($opt->getAttr('selected'))
							$fields[$inp_nam] = $opt->getAttr('value');
				}
			}
		}

		return $fields;
	}

	function replacecode($str)
	{
		return utf8_encode(chr(base_convert(substr($str[0],3), 16, 10)));
	}

	function unidecode($str)
	{
		$str = str_replace('\u00','u00',$str);
		return preg_replace_callback('/u00[0-9A-Z]{2}/mi', 'replacecode' , $str );
	}

	function array_in_array($arr1, $arr2)
	{
		foreach($arr1 as $item)
			if(!in_array($item,$arr2))
				return false;
		return true;
	}

	function debug($var)
	{
		ob_start();
		print "<p align=left><pre align=left class=item>";
		print_r ($var);
		print "</pre></p>";
	}

	function decode_params($link)
	{
		$params_list = substr(strstr($link,'?'),1);
		$params_arr = explode('&',$params_list);

		foreach($params_arr as $param)
		{
			$data = explode("=",$param);
			$ret[$data[0]] = $data[1];
		}
		return $ret;
	}

	function sha_encode($codigo, $categoria = "")
	{
		return substr(sha1($codigo.$categoria),0,8);
	}

	function test_create_dir($dir)
	{
		$dirs = explode('/',str_replace("\\","/",$dir));

		for($it = 2; $it < count($dirs); $it++ )
		{
			$fulldir = implode("/",array_slice($dirs,0,$it));
			if( !file_exists($fulldir) ) {
				@umask( 0 );
				@mkdir($fulldir, 0777);
				@umask();
			}
		}
	}
?>
