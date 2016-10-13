<?php
require_once("engine/Spider.class.php");

class Usuario{
	
	var $ver_anuncios_en;
	var $total_balance;
	var $data;
	
	function Usuario($username,$userdata)
	{
		$cookies = isset($userdata['cookies']) ? $userdata['cookies'] : null;
		$this->spider = new Spider($cookies);

		$this->data = $userdata;
		$this->user = $username;
		$this->pass = $userdata['pass'];
	}

	function login()
	{
		echo "login {$this->user}            \n";
		$this->spider->post("https://www.fortadpays.com/themes/common/login.php",array(
			'user_name' => $this->user,
			'password' => rawurlencode($this->pass),
			'submit'=>'LOGIN'
		));

		if(preg_match('/jAlert\(\'(.*?)\'/',$this->spider->html,$match))
		{
			debug($match[1]);
			return false;
		}
		
		$this->memberoverview();

		return true;
	}

	function memberoverview()
	{
		for($retry=0;$retry<5;$retry++)
		{
			$root = $this->get("https://www.fortadpays.com/member/memberoverview.php");
			if($root->select('.click-today'))
				break;

			"Error cargando memberoverview $retry\n";
		}

		
		$count_down = 0;
		if(preg_match('/countdown_start.*?\((\d+)\);/',$this->spider,$match))
			$count_down = $match[1];

		$this->ver_anuncios_en = strtotime("+{$count_down} seconds");

		if($root->select('#basic-modal-content'))
		{
//			echo "      .. esperando banner   \r";
			consoleWait(8, "esperando banner inicial");
			$this->spider->post("http://www.fortadpays.com/member/function.ajax.php","func=loginviewed");
			echo "\n";
		}

		if(strtotime('+30 minute') > $this->ver_anuncios_en || $count_down==0)
		{
			echo "Mirando los anuncios diarios...        \n";
			$this->verAnuncios();
			echo sprintf("%10s",$this->user)." - anuncios vistos   \n";
		}


		$this->ganarDinero();

		$gan = normalize($root->find('Ganancias de Packs')->parent()->parent()->getNext()->contents());
		$this->ganancias = sprintf("%.02f",str_replace('$','',$gan));


//		$gan = normalize($root->find('Packs activos / completados / caducados / totales')->parent()->parent()->getNext()->contents());
		$tabla_planes = $root->find('Plan Name')->parent()->parent()->parent();

		$last_row = $tabla_planes->select('tr(4)');
		if(!$last_row)
			$last_row = $tabla_planes->select('tr(2)');
		if(!$last_row)
			echo $root;
			

		$gan_str = $last_row->contents();
		array_shift($gan_str);
		
		$gan = implode(' / ',$gan_str);


		$this->shares_data = $gan;

//		$this->withdraw();
	}

	function withdraw()
	{
		$pregunta_map = array(
			'pschelot' => 'Junti',
			'lestefania' => 'Dixie',
		);
		if(!isset($pregunta_map[$this->user]))
			return false;

		$root = $this->get('https://www.fortadpays.com/member/withdraw.php');

		$max = normalize($root->find('Maximum Withdrawal Amount')->getNext()->contents());
		$max = str_replace('$','',$max);
		if(!$max)
		{
			echo date('H:i:s')." Aún no..                                             \r";
			return false;
		}

		$cash = floatval(str_replace('$','',$root->find('Your Current Balance')->parent()->getNext()->contents()));
		if($cash < $max)
		{
			echo date('H:i:s')." falta cash                                           \r";
			return true;
		}

/*		
		global $email_sended;
		if(!isset($email_sended))
			Usuario::sendmail("Withdraw en FAP","Withdraws con Payza abiertos \n".date('d/m/Y H:i:s'));
		$email_sended = true;
*/


		
		$form = $root->select('form(0)');
		$root = $form->submit(array(
			'amount' => $max
		));
		
		$form = $root->select('form(0)');
		$root = $form->submit(array(
			'security_answer' => $pregunta_map[$this->user],
		),"https://www.fortadpays.com/member/withdraw.php?show=sec");

		if(preg_match('/jAlert\(\'(.*?)\'/',$this->spider,$match))
		{
			echo "Withdraw {$this->user}: {$match[1]}\n";
			return false;
		}

		echo "Withdraw {$this->user}: $max!\n";
		return true;
	}

	function verAnuncios()
	{
		$root = $this->get('https://www.fortadpays.com/member/viewad.php');
		$banners = $root->select('.bannerlink');

		$spider2 = new Spider($this->spider);
		foreach($banners as $i => $banner)
		{
			$href = $banner->getAttr('href');
			for($retry=0;$retry<20;$retry++)
			{
				$root2 = $spider2->get($href);
				if(!$spider2->html)
				{
					consoleWait(5,"Error cargando ".$href);
					continue;
				}
				break;
			}

			if( preg_match('/"(class\/.*?.php.*?)"/',$spider2->html,$match) )
			{
				consoleWait(10, "$i/".$banners->length()." esperando ad");
				$spider2->get("http://www.fortadpays.com/".$match[1]);
			}
		}
		
		$this->memberoverview();
	}

	function ganarDinero(){

		$root = $this->spider->get('https://www.fortadpays.com/member/viewptcad.php');
		$banners = $root->select('.earn_button a');
		if(!$banners)
			return;

		$hrefs = array();
		foreach($banners as $banner)
			if($href = trim($banner->getAttr('href')))
				$hrefs[] = $href;

		if(count($hrefs))
			echo "Anuncios: ".count($hrefs)."                \n";

		foreach($hrefs as $i => $href)
		{
			$root = $this->spider->get($href);
			if($root)
			{
				if(preg_match('/"(class\/.*?.php.*?)"/',$this->spider,$match))
				{
					consoleWait(30, "$i/".count($hrefs)." esperando ad");// - http://www.fortadpays.com/".$match[1]);
					$this->spider->get("http://www.fortadpays.com/".$match[1]);
				}
			}
		}
	}

	function purchase()
	{
		$root = $this->get("https://www.fortadpays.com/member/shares.php");
/*
		$this->cash = floatval(str_replace('$','',$root->find('Tu balance en cash')->parent()->getNext()->contents()));
		$this->repurchase = floatval(str_replace('$','',$root->find('Tu balance para recompra')->parent()->getNext()->contents()));
		$this->total_balance = floatval(str_replace('$','',$root->find('Balance total')->parent()->getNext()->contents()));
*/
		$this->cash = floatval(str_replace('$','',$root->find('Balance Wallet 1')->parent()->getNext()->contents()));
		$this->total_balance = $this->cash;
		$this->repurchase = 0;

		if(!isset($this->data['guardar']))
			$this->data['guardar'] = 0;

		$coste_pack = floatval(str_replace('$','',$root->find('Coste de Ad Pack')->parent()->getNext()->contents()));

/*
		$amount = floor($this->total_balance);
		if($this->data['guardar'] && $this->data['guardar'] > 0)
		{
*/			if($this->data['guardar'] > $this->cash + $coste_pack)
				$amount = floor($this->repurchase);
			else
				$amount = floor($this->repurchase + ($this->cash - $this->data['guardar']));
//		}


		echo sprintf("%10s",$this->user)." - total_blance: {$this->total_balance} (rep: {$this->repurchase})";
		if($coste_pack && $amount >= $coste_pack)
		{
			$cant = floor($amount/$coste_pack);

			$form = $root->select('form');
			if($form)
			{
				$root = $form->submit(array(
					'position' => $cant,
					'repurchase' => 1,
					'tos' => 1,
				),'https://www.fortadpays.com/member/shares.php');

				if($root->find('You Do Not Have Sufficient Balance To Purchase This Share')->getText())
				{
					$this->get("https://www.fortadpays.com/member/memberoverview.php");
					echo "\nNo hay dinero suficiente para comprar el pack\n";
					return;
				}

				$form = $root->select('form');
			}

			if(!$form)
			{
				consoleWait(60,"\n       error en submit de purchase, esperando a reintentar");
				echo "\n";
				return $this->login();
				
			}
			$root = $form->submit(array(),'https://www.fortadpays.com/member/shares.php');

			echo " - purchase $cant ".'$'.($cant*$coste_pack)." !";
		}

		$root = $this->get("https://www.fortadpays.com/member/shares.php");
/*
		$this->cash = floatval(str_replace('$','',$root->find('Tu balance en cash')->parent()->getNext()->contents()));
		$this->repurchase = floatval(str_replace('$','',$root->find('Tu balance para recompra')->parent()->getNext()->contents()));
		$this->total_balance = floatval(str_replace('$','',$root->find('Balance total')->parent()->getNext()->contents()));
*/
		$this->cash = floatval(str_replace('$','',$root->find('Balance Wallet 1')->parent()->getNext()->contents()));
		$this->total_balance = $this->cash;
		echo "\n";

//		if($this->total_balance >= 1)
		$this->get("https://www.fortadpays.com/member/memberoverview.php");
	}

	function get($url)
	{
		for($retry=0;$retry<20;$retry++)
		{
			$root = $this->spider->get($url);

			if(!$this->spider)
			{
				consoleWait(5,"Error cargando $url");
				continue;
			}
			break;
		}

/*		if($item = $root->find('Vuelve mas tarde'))
		{
			echo "      .. ".normalize($item->getChar())."\r";
			consoleWait(60*15);
		}
*/	
		if(strstr($this->spider->url,'login.php')!==false)
		{
			$this->login();
			$root = $this->spider->get($url);
		}

		return $root;
	}


	static function sendmail_old($titulo, $mensaje){

		include('config_sendmail.php');

		$cabeceras = 'From: '.$nombre_from."\r\n" .
			'Reply-To: '.$nombre_to."\r\n" .
			'X-Mailer: PHP/' . phpversion();

		$headers  = 'From: '.$nombre_from. "\r\n" ;
		$headers .= 'Reply-To: '. $nombre_to . "\r\n" ;
		$headers .= "Return-Path: $from\r\n";
		$headers .= 'X-Mailer: PHP/' . phpversion();
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "X-Priority: 3\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";   

		mail($to, $titulo, $mensaje, $headers);
	}

	static function sendmail($titulo, $mensaje)
	{
		set_include_path('/home/ubuntu/pear/share/pear');
		if(!@require_once("Mail.php"))
			return Mercadona::sendmail_old($titulo,$mensaje);

		include('config_sendmail.php');

		$recipients = $from;

		$headers["From"]    = $nombre_from;
		$headers["To"]      = $nombre_to;
		$headers["Subject"] = $titulo;

		$mail_object = Mail::factory("smtp", $params);
		$mail_object->send($recipients, $headers, $mensaje);
	}
}

function consoleWait($segs,$message=null)
{
//	echo " $segs    \r";sleep($segs);return;
	if($message)
		echo "      .. $message                            \r";

	for($i=$segs;$i>=0;$i--)
	{
		echo " $i  \r";
		sleep(1);
	}
}
