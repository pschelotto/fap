<html>
	<head>
		<meta http-equiv="refresh" content="60">
		<meta charset="utf-8" />
		<link href="panel.css" rel="stylesheet" type="text/css" />
		<script src='http://code.jquery.com/jquery-1.4.1.min.js'></script>
		<script>
			function fmt(num){
				return String("00" + String(num)).substr(-2);
			}
			$(document).ready(function(){
				setInterval(function(){
					d = new Date();
					$('.countdown').each(function(){
						var segs = $(this).attr('time') - d.getTime()/1000;
						var seg = fmt(Math.floor(segs % 60));
						var min = fmt(Math.floor((segs / 60)%60));
						var hor = fmt(Math.floor(segs / (60*60)));
						$(this).text( hor+':'+min+':'+seg );
					})
				},1000);
				
				$('.guardar').change(function(){
					$.getJSON('controller.php?user='+$(this).attr('user')+'&guardar='+$(this).val());
				})
			});
		</script>
		<style>
			.datagrid{
				margin:5px;
			}
			td{
				text-align:right;
				padding:0px 15px;
			}
			.gan_toggle{
				cursor:pointer;
			}
			.gan_toggle .hist{
				display:none;
				position:absolute;
				background:white;
				border:1px solid grey;
				padding:5px;
			}
			.gan_toggle:hover .hist{
				display:block;
			}
			input{
				text-align:right;
			}
			
			.estado{
				left:0;
				top:0;
				margin:1px;
				position:absolute;
				width:10px;
				height:10px;
				background-color:red;
				border-radius:5px;
			}
			
			.estado.conectado{
				background-color:green;
			}
		</style>
	</head>
	<body>
		<?php

			$users = array();
			if(file_exists('user_data.json'))
			{
				$fecha_user_data = filemtime('user_data.json');
				$users = json_decode(file_get_contents('user_data.json'),true);
			}

			$hist = array();
			if(file_exists('history.json'))
			{
				$hist = json_decode(file_get_contents('history.json'),true);

				for($dias=0;$dias>=-20;$dias--)
				{
					$histhoy = getUserHist($hist, strtotime(''.$dias.' day'));
					if($histhoy)
					{
						$ultima_hora_hoy = array_key_last($histhoy);

						$ultimo_de_hoy = $histhoy[$ultima_hora_hoy];
						foreach($ultimo_de_hoy as $username => $user)
							$users[$username]['ultimas_ganancias_dia'][$dias] = $user['ganancias'];
					}
				}
			}
			$conectado = (strtotime('now') - $fecha_user_data)/60 < 20;
		?>
		
		<div class='estado <?php echo $conectado?'conectado':'' ?>'></div>
		<div class='datagrid'>
			<table>
			<thead>
				<tr>
					<th>Usuario</th>
					<th>Ganancias</th>
					<th title='activas / completadas / caducadas / total'>Shares</th>
					<th>Cash + Rpur</th>
					<th>Balance</th>
					<th>Gan./dia</th>
					<th>Ver ads</th>
					<th>Guardar</th>
				</tr>
			</thead>
			<?php 
				foreach($users as $username => $user)
				{
					$adsTime = $user['ver_anuncios_en'];
					$segs = $adsTime - strtotime('now');

					$seg = floor($segs % 60);
					$min = floor(($segs / 60)%60);
					$hor = floor($segs / (60*60));
					$adsQuedan =  '';//sprintf("%02d:%02d:%02d",$hor,$min,$seg);

					echo "<tr>";
					echo "<td>{$username}</td>";
					echo "<td>{$user['ganancias']}</td>";
					echo "<td style='text-align:center'>{$user['shares_data']}</td>";
					echo "<td>{$user['cash']} + {$user['repurchase']}</td>";
					echo "<td>{$user['balance']}</td>";
					echo "<td>" . getGananciasDia($user) . "</td>";
					echo "<td class='countdown' time='{$adsTime}' width='60'>{$adsQuedan}</td>";
					echo "<td><input name='guardar' user='{$username}' class='guardar' value='{$user['guardar']}' size='4'></td>";
					echo "</tr>";
				}
			?>
			</table>
		</div>
	</body>
</html>

<?php 

function getUserHist($hist, $ref)
{
	$ano = date('Y',$ref);
	$mes = date('m',$ref); 
	$dia = date('d',$ref);

	return $hist[$ano][$mes][$dia];
}

function array_key_last($arr)
{
	$keys = array_keys($arr);
	return $keys[count($keys)-1];
}

function getGananciasDia($user)
{
	foreach($user['ultimas_ganancias_dia'] as $dias => $ganancias)
	{
		if(!isset($user['ultimas_ganancias_dia'][$dias-1]))
			continue;
		$arr[] = sprintf("%.02f",$ganancias - $user['ultimas_ganancias_dia'][$dias-1]);
	}

	return "<div class='gan_toggle'>".$arr[0]."<div class='hist'>".implode('<br>',$arr)."</div></div>";
}
?>