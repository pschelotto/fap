<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('usuario.php');

//global $_debug;$_debug=1;


while(true)
{
	$users = array();
	if(file_exists('user_data.json'))
		$users = json_decode(file_get_contents('user_data.json'),true);

	$hist = array();
	if(file_exists('history.json'))
		$hist = json_decode(file_get_contents('history.json'),true);


	foreach($users as $username => $userdata)
	{//unset($userdata['cookies']);
		$usuarios[$username] = new Usuario($username,$userdata);
	//	$usuarios[$username]->login();
	}

	echo date('d/m/Y H:i:s')."\n";
	foreach($usuarios as $username => $usuario)
	{
		$usuario->memberoverview();
		$balance = $usuario->purchase();

		$users[$username]['cookies'] = $usuario->spider->cookies;
		$users[$username]['ganancias'] = $usuario->ganancias;
		$users[$username]['shares_data'] = $usuario->shares_data;

		$users[$username]['cash'] = sprintf("%.02f",$usuario->cash);
		$users[$username]['repurchase'] = sprintf("%.02f",$usuario->repurchase);
		$users[$username]['balance'] = sprintf("%.02f",$usuario->total_balance);
		$users[$username]['ver_anuncios_en'] = $usuario->ver_anuncios_en;
	}
	file_put_contents('user_data.json',json_encode($users));

	// Guardamos el histórico (por hora)
	$ano = date('Y');$mes = date('m'); $dia = date('d'); $hora = date('H');
	if(!isset($hist[$ano][$mes][$dia][$hora]))
	{
		foreach($usuarios as $username => $usuario)
		{
			$hist[$ano][$mes][$dia][$hora][$username] = array(
				'ganancias' 	=> $users[$username]['ganancias'],
				'shares_data' 	=> $users[$username]['shares_data'],
				'cash' 			=> $users[$username]['cash'],
			);
		}
		file_put_contents('history.json',json_encode($hist));
	}

	
	$hora = date('Y-m-d',strtotime('+1 day')).' 00:00:00';

	if(strtotime('now')>=strtotime($hora.' -5 min') && strtotime('now')<strtotime($hora.' +5 min'))
	{
		echo date('H:i:s')." Entramos en modo withdraw, sleep principal en pausa\n";

		while(strtotime('now')>=strtotime($hora.' -5 min') && strtotime('now')<strtotime($hora.' +5 min'))
		{
			echo date('H:i:s')."                                 \r";
			if(strtotime('now')>=strtotime($hora.'-10 sec'))
			{
				echo date('H:i:s')." Dentro ..                  \r";
				foreach($usuarios as $username => $usuario)
					if( !( isset($ret_map[$usuario->user]) && $ret_map[$usuario->user]) )
						$ret_map[$usuario->user] = $usuario->withdraw();
			}
			sleep(1);
		}
		echo "\n".date('H:i:s')." Saliendo ..              \n";
	}
	else
	{
		$segs = 15*60;

		for($i=$segs;$i>=0;$i--)
		{
			if(strtotime('now')>=strtotime($hora.' -5 min') && strtotime('now')<strtotime($hora.' +5 min'))
				break;

			echo " $i  \r";
			sleep(1);
		}	
	}
}

