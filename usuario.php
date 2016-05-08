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
		$root = $this->get("https://www.fortadpays.com/member/memberoverview.php");

		$count_down = 0;
		if(preg_match('/countdown_start.*?\((\d+)\);/',$this->spider,$match))
			$count_down = $match[1];

		$this->ver_anuncios_en = strtotime("+{$count_down} seconds");

		if($root->select('#basic-modal-content'))
		{
//			echo "      .. esperando banner   \r";
			consoleWait(8, "esperando banner");
			$this->spider->post("http://www.fortadpays.com/member/function.ajax.php","func=loginviewed");
		}

		if(strtotime('+30 minute') > $this->ver_anuncios_en || $count_down==0)
		{
			$this->verAnuncios();
			echo sprintf("%10s",$this->user)." - anuncios vistos   \n";
		}

		$this->ganarDinero();

		$gan = normalize($root->find('Ganancias de Packs')->parent()->parent()->getNext()->contents());
		$this->ganancias = sprintf("%.02f",str_replace('$','',$gan));

		$gan = normalize($root->find('Packs activos / completados / caducados / totales')->parent()->parent()->getNext()->contents());
		$this->shares_data = $gan;
	}

	function verAnuncios()
	{
		$root = $this->get('https://www.fortadpays.com/member/viewad.php');
		$banners = $root->select('.bannerlink');

		$this->spider2 = new Spider($this->spider);
		foreach($banners as $i => $banner)
		{
			for($retry=0;$retry<20;$retry++)
			{
				$root2 = $banner->click();
				if(!$root2->getSpider())
				{
					consoleWait(5,"Error cargando ".$banner->getAttr('href'));
					continue;
				}
				break;
			}
			
			preg_match('/"(class\/.*?.php.*?)"/',$root2->getSpider(),$match);

			consoleWait(10, "$i/".$banners->length()." esperando ad");

			$this->spider2->get("http://www.fortadpays.com/".$match[1]);
		}
		
		$this->memberoverview();
	}

	function ganarDinero(){

		$root = $this->spider->get('https://www.fortadpays.com/member/viewptcad.php');
		$banners = $root->select('.earn_button a');

		if($banners)
			echo "Anuncios: ".$banners->length()."                \n";

		$this->spider2 = new Spider($this->spider);
		if($banners)
		foreach($banners as $i => $banner)
		{
			$root2 = $banner->click();
			preg_match('/"(class\/.*?.php.*?)"/',$root2->getSpider(),$match);

			consoleWait(30, "$i/".$banners->length()." esperando ad");

			$this->spider2->get("http://www.fortadpays.com/".$match[1]);
		}
	}

	function purchase()
	{
		$root = $this->get("https://www.fortadpays.com/member/shares.php");

		$this->cash = floatval(str_replace('$','',$root->find('Tu balance en cash')->parent()->getNext()->contents()));
		$this->repurchase = floatval(str_replace('$','',$root->find('Tu balance para recompra')->parent()->getNext()->contents()));
		$this->total_balance = floatval(str_replace('$','',$root->find('Balance total')->parent()->getNext()->contents()));

		if(!isset($this->data['guardar']))
			$this->data['guardar'] = 0;
/*
		$amount = floor($this->total_balance);
		if($this->data['guardar'] && $this->data['guardar'] > 0)
		{
*/			if($this->data['guardar'] > $this->cash + 1)
				$amount = floor($this->repurchase);
			else
				$amount = floor($this->repurchase + ($this->cash - $this->data['guardar']));
//		}

		echo sprintf("%10s",$this->user)." - total_blance: {$this->total_balance} (rep: {$this->repurchase})";
		if($amount >= 1)
		{
			$form = $root->select('form');
			if($form)
			{
				$root = $form->submit(array(
					'position' => $amount,
					'repurchase' => 1,
					'tos' => 1,
				),'https://www.fortadpays.com/member/shares.php');

				$form = $root->select('form');
			}
			if(!$form)
			{
				consoleWait(60,"\nerror en submit de purchase, esperando a reintentar");
				return $this->login();
				
			}
			$root = $form->submit(array(),'https://www.fortadpays.com/member/shares.php');

			echo " - purchase $amount !";
		}

		$this->cash = floatval(str_replace('$','',$root->find('Tu balance en cash')->parent()->getNext()->contents()));
		$this->repurchase = floatval(str_replace('$','',$root->find('Tu balance para recompra')->parent()->getNext()->contents()));
		$this->total_balance = floatval(str_replace('$','',$root->find('Balance total')->parent()->getNext()->contents()));

		echo "\n";

//		if($this->total_balance >= 1)
			$this->memberoverview();
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
}

function consoleWait($segs,$message=null)
{
//	echo " $segs    \r";sleep($segs);return;
	if($message)
		echo "      .. $message                          \r";

	for($i=$segs;$i>=0;$i--)
	{
		echo " $i   \r";
		sleep(1);
	}
}
