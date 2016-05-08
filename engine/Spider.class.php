<?php
global $_debug;
//$_debug = 1;

require_once('Item.class.php');
ini_set("max_execution_time", "20000");
ini_set("memory_limit",'512M');
//date_default_timezone_set('UTC');

class Spider
{
	public $cookies = array();
	public $headers = array();
	public $url = null;
	public $user;
	public $tratar_xml = false; // henry schein viene con trozos de xml que hay que tratar con tidy
	public $agent;

	protected $proxys = array();

	public function Spider($spider = null, $ssl_version = 4)
	{
		$this->ssl_version = $ssl_version;
//		$this->build_headers();


		if($spider)
		{
			if( gettype($spider) == "object" )
			{
				$this->cookies = $spider->cookies;
				$this->url = $spider->url;
			}
			else
				$this->cookies = $spider;
		}

//		$proxys = explode("\n",trim(file_get_contents(sfConfig::get('sf_root_dir').'/config/proxy_list.txt')));
	}

	public function build_headers(){
		$this->headers = array();
		$this->headers[] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$this->headers[] = "Accept-Language: es-ES,es;q=0.8";
		$this->headers[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3";
//		$this->headers[] = "Keep-Alive: 300";
		$this->headers[] = "Connection: keep-alive";
//		$this->headers[] = "Content-Type: application/x-www-form-urlencoded; charset=UTF-8";
	}

	public function get($url, $data = null, $options_get = array())
	{
		if(gettype($data)=='array')
		{
			array_walk($data,function(&$item,$key){$item="$key=$item";});
			$data = implode('&',$data);
		}

		if($data)
			$url .= '?' . $data;

		$options_get += array(
			CURLOPT_POST 			=> false,
		);

		return $this->pageParser($url, $options_get);
	}
	
	public function post($url, $data = '', $options_post = array())
	{
		if(gettype($data)=='array')
		{
			array_walk($data,function(&$item,$key){$item="$key=$item";});
			$data = implode('&',$data);
		}

		$options_post += array(
			CURLOPT_POST 		=> true,
			CURLOPT_POSTFIELDS	=> $data,
		);
		
		return $this->pageParser($url, $options_post);
	}

	public function rawget($url)
	{
		return $this->request($url);
	}

	public function pageParser($url, array $options = array())
	{
		$html = $this->request($url, $options);

		$this->html = $html;


		if( $this->tratar_xml )
		{
			$html = str_replace('<![CDATA[','',$html);
			$html = str_replace(']]>','',$html);
		}
		
		global $noscript;if($noscript)
		{
			preg_match_all('/\<script.*?<\/script>/is', $html,$ms);
			foreach($ms as $m)
				$html = str_replace($m,"",$html);

			preg_match_all('/\<script.*?<\/script>/is', $html,$ms);
			foreach($ms as $m)
				$html = str_replace($m,"",$html);

			$html = preg_replace('/\<!--.*?-->/s', '', $html);
			$html = preg_replace('/\<link.*?\/>/i', '', $html);
		}
		
		if( $this->tratar_xml || strstr($this->content_type,'text/xml') === false )
			$html = $this->tidy($html);

		return $this->processHtml($html);
	}

	public function request($url, array $options = array())
	{
		if($this->agent)
			$agent = $this->agent;
		else
			$agent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/534.13 (KHTML, like Gecko) Chrome/9.0.597.84 Safari/534.13";

		for($reintentos=0;$reintentos<10;$reintentos++)
		{
			$ch = curl_init(); 

			if($this->url)
				$url = replace_url_path($this->url, $url);

			$options += array( 
				CURLOPT_URL 			=> $url,
				CURLOPT_HEADER 			=> true,
				CURLOPT_RETURNTRANSFER 	=> true,
	//			CURLOPT_FOLLOWLOCATION	=> true,
				CURLOPT_TIMEOUT 		=> 120,

				CURLOPT_CONNECTTIMEOUT 	=> 120,
				CURLINFO_HEADER_OUT		=> true,
				CURLOPT_SSLVERSION		=> $this->ssl_version,
				CURLOPT_ENCODING		=> "gzip,deflate,sdch",
	//			CURLOPT_USERAGENT		=> "Mozilla/5.0 (Windows; U; Windows NT 5.1; es-ES; rv:1.8.1.20) Gecko/20081217 Firefox/2.0.0.20 (.NET CLR 3.5.30729)",
				CURLOPT_USERAGENT		=> $agent,

		#		CURLOPT_AUTOREFERER			=> true,
				CURLOPT_HTTPHEADER		=> $this->headers,
				
//				CURLOPT_PROXYTYPE 		=> CURLPROXY_SOCKS5,
//				CURLOPT_PROXY			=> "localhost:9050",
			);

			$intentos = 1;
			do{
	/*
				if( sfConfig::get('app_use_proxys', false) )
				{
					$this->headers['host'] = "Host: ".parse_url($url, PHP_URL_HOST);
					curl_setopt($ch, CURLOPT_URL, 'http://'.$this->proxys[ rand() % count($this->proxys) ]);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
				}
	*/
	//			if( $this->url )
	//				$options[CURLOPT_REFERER] = $this->url;

				if( preg_match('/^https:/i',$url) )
				{
					$options[CURLOPT_SSL_VERIFYPEER] = false;
					$options[CURLOPT_SSL_VERIFYHOST] = 2;
				}

				if(count($this->cookies))
				{
					$cookies = $this->cookies;
					array_walk($cookies,function(&$item,$key){$item="$key=$item";});
					$options[CURLOPT_COOKIE] = implode('; ',$cookies);
				}

				curl_setopt_array($ch, $options); 

				global $_debug;
if($_debug)
$r=microtime(true);

				if( !$result = curl_exec($ch)) 
				{
					echo "\nSin respuesta cargando: $url ... reintentando $intentos/100\n";
					//trigger_error(curl_error($ch)); 
					sleep(1);
					$redirect = true;
					
					$intentos++;
					if($intentos >= 100)
					{
						//exec("php solicitar.php 8168");
						break;
					}
					continue;
				} 
if($_debug)
echo sprintf("%.2f",microtime(true)-$r);

				$this->status_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
				$this->url = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
				$this->content_type = curl_getinfo($ch,CURLINFO_CONTENT_TYPE);


				$header_size = curl_getinfo($ch,CURLINFO_HEADER_SIZE);
				$head = substr($result,0,$header_size);
				$html = substr($result,$header_size);

				$this->cookies = array_merge($this->cookies,$this->getCookies($head));

				if($_debug)
					$this->debugInfo($ch,$url,$head,$html,$options);


				$redirect = $this->status_code >= 300 && $this->status_code < 400;
				if( $redirect )
				{
					$url = $this->GetHTTPLocation($head);
					$url = replace_url_path($this->url, $url);

					$options[CURLOPT_URL] = $url;
					$options[CURLOPT_POST] = false;
					unset($options[CURLOPT_POSTFIELDS]);
				}

			} while($redirect);
		
			curl_close($ch); 

			break;
		}

		return $html;
	}

	function GetHTTPLocation($head)
	{
		if( preg_match('/Location: (.*)/i', $head, $matches) )
			return trim($matches[1]);
	}

	function getCookies($head)
	{
		$cookies = array();
		if( preg_match_all('/^Set-Cookie: (.*)/im', $head, $matches) )
		{
			foreach( $matches[1] as $match )
				if( preg_match('/^(.*?)=([^;]*);?/', $match, $m))
					$cookies[$m[1]] = str_replace("\r",'',$m[2]);
		}

		return $cookies;
	}

	function tidy($html)
	{
		$html = $this->utf8_to_html($html);

//		$html = preg_replace('/\/\/<!\[CDATA\[.*?\]>/s', "", $html);
		$html = preg_replace('/<!\[CDATA\[.*?(?:\]>|<\[\[)/s', "", $html);

		if( strpos($html,"<rss ") === FALSE )
		{
			$config = array(
			   'indent'         => true,
			   'output-xhtml'   => true,
			   'wrap'           => 200,
			   'hide-comments'	=> true,
			   'literal-attributes' => true, // sinó henry schein no captura bien los atributos
			);
			$html = tidy_repair_string( $html, $config );
		}

		$html = $this->arreglar($html);
		return $html;
	}

	function utf8_to_html ($data)
	{
//		return preg_replace("/([\\xC0-\\xF7]{1,1}[\\x80-\\xBF]+)/e", '$this->_utf8_to_html("\\1")', $data);

		return preg_replace_callback('/([\\xC0-\\xF7]{1,1}[\\x80-\\xBF]+)/', function($matches){
			return $this->_utf8_to_html($matches[1]);
		}, $data);
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
		$html = $this->translate($html);
		$html = utf8_encode($html);
		$html = $this->utf8_to_html($html);

		return $html;
	}

	function replacecallbak($matches)
	{
		static $specialchars;
		if( !$specialchars )
		{
			$specialchars = get_html_translation_table( HTML_ENTITIES );
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

			$specialchars = array_flip( $specialchars );
			$specialchars = array_merge( $specialchars, $specialchars2 );
			$specialchars = array_merge( $specialchars, $specialchars3 );
		}

		if(isset($specialchars[$matches[0]]))
			$trad = $specialchars[$matches[0]];

		return $trad ? $trad : $matches[0];
	}

	function translate($html)
	{
		return preg_replace_callback('/&[0-9a-z]+;/mi', array('Spider','replacecallbak') , $html );
	}

	function processHtml($html)
	{
		global $seq_id;
		$seq_id = 0;

		$this->actual = null;

		$this->map = new ItemMap($this);
		$this->safe_parse( $html );

		return $this->actual;
	}

	function safe_parse( $html )
	{
        $parser = null;
        $htmli = "";
		$counter = 0;
		do
		{
			$counter++;
			if( $counter > 10000 ) {
				print_r( $html );
				throw new Exception("Tight loop in safe_parse" );
			}
			if( strlen( $html ) > 10000000 ) {
				file_put_contents( '/tmp/htmllargo.html', $html );
				throw new Exception( "Html too long: ".strlen( $html ) );
			}
			if( $parser )
			{
				$i1 = xml_get_current_byte_index($parser);
				$tmp = substr($html,0,$i1);
				$i0 = strrpos($tmp,'<') - 1;

				$html1 = substr($html,0,$i0).substr($html,$i1);
				$html = $html1;

				if( $html == $htmli )
					die (sprint("%s <br>XML Error: %s at line %d col %d byte %d",
						$url,
						xml_error_string(xml_get_error_code($parser)),
							xml_get_current_line_number($parser),
							xml_get_current_column_number($parser),
							xml_get_current_byte_index($parser)));

				$htmli = $html;

				//Free the XML parser
				xml_parser_free($parser);
			}

			//Initialize the XML parser
			$parser = xml_parser_create();

#			xml_parser_set_option($parser,XML_OPTION_TARGET_ENCODING, "ISO-8859-1");
			xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING, false);


			//Specify element handler
			xml_set_element_handler($parser,array("Spider","start"),array("Spider","stop"));

			//Specify data handler
			xml_set_character_data_handler($parser,array("Spider","char"));

			$err = 0;

			$debug = 0;
			if(!$debug)
				xml_parse($parser,$html) or $err = 1;
			else
			{
				xml_parse($parser,$html) or
				die (sprint("%s <br>XML Error: %s at line %d col %d byte %d",
					$url,
					xml_error_string(xml_get_error_code($parser)),
						xml_get_current_line_number($parser),
						xml_get_current_column_number($parser),
						xml_get_current_byte_index($parser)));
			}

		} while( $err );

		//Free the XML parser
		xml_parser_free($parser);
	}

	//Function to use at the start of an element
	function start($parser, $element_name, $element_attrs)
	{
		$this->actual = $this->createItemInstance($this->map, $element_name ,$this->actual, $element_attrs);
	}
	
	function createItemInstance(&$map, $tagname ,$parent, $attrs)
	{
		$ObjectMap = array(
			'form' 		=> 'FormItem',
			'a' 		=> 'AnchorItem',
			'select' 	=> 'SelectItem',
			'iframe' 	=> 'IFrameItem',
		);
		$class_name = isset($ObjectMap[$tagname]) ? $ObjectMap[$tagname] : 'HtmlItem';
		return new $class_name($map, $tagname ,$parent, $attrs);
	}

	//Function to use at the end of an element
	function stop($parser,$element_name)
	{
		if( $this->actual->id )
			$this->actual = $this->actual->getParent();
	}

	//Function to use when finding character data
	function char($parser, $data)
	{
		if( strlen( trim($data) ) )
		{
			// si el último hijo del padre también era un char,
			$hijos = $this->actual->getChilds();
			$lasthijo = count($hijos) ? $hijos[count($hijos)-1] : null;

			// lo concatenamos en lugar de crear un nuevo nodo
			if($lasthijo && $lasthijo->getChar())
				$lasthijo->setChar($lasthijo->getChar().$data);
			else
			{
				$node = new HtmlItem($this->map, "", $this->actual);
				$node->setChar($data);
			}
		}
	}

	function __toString(){
		if(!isset($this->html))
			return "";
		return $this->html;
	}

	function debugInfo($ch,$url,$head,$html,$options)
	{
		$sc = $this->status_code == 200 || $this->status_code == 100 ? '#b0b0b0' : ($this->status_code >= 300 && $this->status_code < 400 ? '#40a040' : 'red');
		$sc = "<font style='color:white;background-color:$sc'>{$this->status_code}</font>";

		$is_post = isset($options[CURLOPT_POST]) ? $options[CURLOPT_POST] : false;
		if(!$is_post)
		{
			if(isset($_SERVER['HTTP_HOST']))
				echo "<div class='get'><p>$sc GET:&nbsp;".$url."</p>\n";
			else
				echo "{$this->status_code} GET: $url\n";
		}
		else
		{
			$params = $options[CURLOPT_POSTFIELDS];
			if(isset($_SERVER['HTTP_HOST']))
				echo "<div class='get'><p>$sc POST:&nbsp;$url<br>\n<blockquote>".str_replace('&','&amp;',$params)."</blockquote></p>\n";
			else
				echo "{$this->status_code} POST: $url\n\t$params\n";
		}

		if(!isset($_SERVER['HTTP_HOST']))
			return;


//		$content_type = curl_getinfo($ch,CURLINFO_CONTENT_TYPE);

		$this->setStylesForDebug();

		$html1 = $html;
		if( preg_match('/html/', $this->content_type))
			$html1 = $this->tidy($html1);

		$rep = array("script","style","link","meta","iframe","object","a");
		foreach($rep as $it)
		{
			$html1 = str_ireplace("<{$it}","<{$it}_x",$html1);
			$html1 = str_ireplace("</{$it}","</{$it}_x",$html1);
		}
		$html1 = str_ireplace(" onload="," onload_x=",$html1);
		$html1 = str_ireplace(" onError="," onError_x=",$html1);
		$html1 = str_ireplace(" style="," style_x=",$html1);
		$html1 = trim($html1);


		if( preg_match('/javascript/', $this->content_type))
			$html1 = ver($html1);

		if( preg_match('/image/', $this->content_type))
		{
			$html1 = "<img src='$url'>";
//			$html1 = substr($html1,0,100)+"...(truncado)";
		}


		global $delete_cdata;
		if($delete_cdata==1)
			$html1 = preg_replace('/<!\[CDATA\[.*?(?:\]>|<\[\[)/s', "", $html1);
		
		$head_sended = trim(curl_getinfo($ch, CURLINFO_HEADER_OUT));
		$head_sended = $this->toogle_cookie($head_sended);

		$head = trim(preg_replace('/Set-Cookie:/i',"<inline class='cookie'>Set-Cookie:</inline>",$head));

		$out  = "<div class='datos'>";
		$out .= "<div class='head'><pre>$head_sended</pre></div>";
		$out .= "<div class='response'><pre>$head</pre></div>";
global $_debug;if($_debug==1)
		$out .= "<div class='body'>$html1</div>";
		$out .= "</div>\n";

		
		echo $out;
		echo "</strong></div></div></div></div></div></div></div></div></div></div>"; #/div extra por los htmls mal formados que hay por ahi
	}

	function toogle_cookie($head)
	{
		if(preg_match('/Cookie\:\s*(.*)/',$head, $match))
		{
			$cooks = explode(';',$match[1]);
			foreach($cooks as &$cook)
				$cook = preg_replace('/^\s*(.*?)=/','   <b>$1</b> = ',$cook);

			$modif = implode('<br>',$cooks);
			$modif = "<div class='cookie'>Cookie:</div>".$modif;
			$head = str_replace('Cookie: '.$match[1],$modif,$head);
		}

		return $head;
	}

	function setStylesForDebug()
	{
		global $stylesSetted;
		if($stylesSetted)
			return;
		$stylesSetted=1;

		echo "<script src='http://code.jquery.com/jquery-1.4.1.min.js'></script>\n";
		echo "<script>
			$(document).ready(function(){
				$('div.get p, div.head, div.response').click(function () {
				  $(this).parents('div.get').find('div.datos').toggle();
				});
			});
		</script>";

		echo "<style>

			div.get{
				font-family:Courier New;
				font-size:12px;
				cursor:pointer;cursor:hand;
			}

			div.get>div{
				display:none;
			}
			p{margin:0px;}
			blockquote{margin:0 35px;}

			script_x{display:none}

			div.datos{
				 border:1px solid red;
				 padding:2px;
				 margin:5px;
			}

			div.datos div.head{
				border:1px solid green;
				margin:1px;
			}

			div.datos div.response{
				border:1px solid blue;
				margin:1px;
			}

			div.datos div.body{
				border:1px solid grey;
				margin:1px;
				padding:5px;
			}

			.cookie {
				color:red;
			}
		</style>";

	}

	public function getRoot(){
		return $this->map->getRoot();
	}

	function getjson()
	{
		$x = explode("\n", $this->html);
		if(!preg_match('/(.*)\d+\|\d+/', $x[1], $match))
			preg_match('/\d+ (.*)/', $x[1], $match);

		return json_decode( $match[1], true );
	}
	
	function reinit(){}
	function destroy(){}
}


?>
