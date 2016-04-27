<?php
global $m;$m=0;

require_once('common.php');

class ItemMap{

	protected $nodes;

	public function ItemMap(&$spider)
	{
		$this->maps = array();
		$this->nodes = array();
		$this->spider = $spider;
	}

	protected function add( $map, $key, $node )
	{
		if( !$key )
			return;

		$this->maps[$map][strtolower($key)][] = $node->id;
	}
	
	public function addNode($node)
	{
		// añadimos a los mapas el tagname, atributos y clases
		if( $node->name )
			$this->add( "tagname", $node->name, $node );

		if(isset($node->attrs))
			foreach($node->attrs as $key => $value)
			{
				if($key == 'class')
				{
					foreach( explode(' ',$value) as $clas )
						$this->add( $key, $clas, $node );
				}
				else	
					$this->add( $key, $value, $node );
			}
		$this->nodes[$node->id] = $node;
	}

	public function getNode($id){
		return isset($this->nodes[$id]) ? $this->nodes[$id] : null;
	}
	
	public function getRoot(){
		return $this->nodes[0];
	}
}

class Item implements Iterator 
{
	var $id, $map;
	var $childs = array();

	public function Item( &$map, $name = null, $parent = null, $attrs = null )
	{
		global $seq_id;
		$this->id = $seq_id++;

		$this->attrs = $attrs;
		$this->char = "";

		$this->name = $name;

		if( $parent )
		{
			$this->parent = $parent->id;
			$this->depth = $parent->depth + 1;

			array_push($parent->childs, $this);
		}
		else
		{
			$this->parent = null;
			$this->depth = 1;
		}

		$this->map = $map;
		$map->addNode($this);
	}

	// iteration
	public function current()	{ return current( $this->childs ); 	}
	public function key()		{ return key( $this->childs );		}
	public function rewind()	{ reset( $this->childs );			}
	public function next()		{ return next( $this->childs );		}
	public function valid()		{ return $this->current() !== false;}

	function getRoot(){
		return $this->map->getRoot(); 
	}

	public function getItem($key, $tag = null)
	{
		if($tag){
	    	$elems = isset($this->map->maps[$key][$tag]) ? $this->map->maps[$key][$tag] : array();
		}else
		{
			$elems = array();
			if( isset($this->map->maps[$key]) )
				foreach($this->map->maps[$key] as $items)
					$elems = array_merge($elems,$items);
		}

		//deducimos el ámbito de la búsqueda dentro del nodo especificado
		$iddesde = $this->id;
		$idhasta = "";
		$nextObj = $this->getNext();
		if( $nextObj )
			$idhasta = $nextObj->id;

		// si es un nodo "colección", adquiere un id virtual (sin referenciar en el mapa) anterior al primer elemento
		if($this->id == "")
			$iddesde = $this->childs[0]->id;

		$coll = new Selector($this->map);
		foreach( $elems as $elem )
			if( $elem > $iddesde && ($elem < $idhasta || $idhasta == null) )
				$coll->childs[] = $this->map->getNode($elem);

		return $coll;
	}

	public function getElementByName($val) {
		return $this->getItem('name',strtolower($val));
	}
	public function getElementByTagName($val) {
		return $this->getItem('tagname',strtolower($val));
	}
	public function getId($val) {
		return $this->getItem('id',strtolower($val));
	}
	public function getClass($val) {
		return $this->getItem('class',strtolower($val));
	}

	protected function merge_nodup($org, $data)
	{
		//evitamos elementos duplicados
		$minimap = array();
		foreach( $org as $it )
			$minimap[$it->id] = $it;
	
		if( gettype($data) == "array" )
		{
			foreach( $data as $it )
				$minimap[$it->id] = $it;
		}
		else
			$minimap[$data->id] = $data;
	
		return array_values($minimap);
	}

	protected function interpreta_sel($sel)
	{
		$sel = trim($sel);

		$proof = explode(" ",$sel);

		$sel_info = array();

		for( $y = 0; $y < count($proof); $y++ )
		{
			$sel1 = $proof[$y];

			$tag = "";
			$id = "";
			$clas = "";

			// Si se solicita un elemento único

			$eq = -1;
			if( preg_match('/(.*?)\((.*?)\)(.*)/',$sel1,$match) )
			{
				$sel1 = $match[1].$match[3];
				$eq = $match[2];
			}

			// Si se especifica modificador (:last)

			$modif = "";
			if(preg_match('/(.*?):(.*)/',$sel1,$match))
			{
				$sel1 = $match[1];
				$modif = explode(":",$match[2]);
				$modif = str_replace(array("next","prev"),array("_next","_prev"), $modif);
			}

			// Acceso al elemento inmediato <
			
			$inmediatos = array();
			if(preg_match('/(.*?)>(.*)/',$sel1,$match))
			{
				$sel1 = $match[1];
				$inmediatos = explode(">",$match[2]);
			}


			// Extraemos tag, class e id

			$ncl = stripos($sel1,".");
			$nid = stripos($sel1,"#");
			if( $ncl === FALSE )
			{
				if( $nid === FALSE )
					$tag = $sel1;
				else
				{
					$tag = substr($sel1,0,$nid);
					$id = substr($sel1,$nid+1);
				}
			}
			else
			{
				if( $nid === FALSE )
				{
					$tag = substr($sel1,0,$ncl);
					$clas = substr($sel1,$ncl+1);
				}
				else
				{
					$pm = min($ncl,$nid); // primera marca
					$tag = substr($sel1,0,$pm);

					if($pm == $nid)	$id = substr($sel1,$nid+1,$ncl-($nid+1));
					else			$id = substr($sel1,$nid+1);

					if($pm == $ncl)	$clas = substr($sel1,$ncl+1,$nid-($ncl+1));
					else			$clas = substr($sel1,$ncl+1);
				}
			}
			
			$sel_info[] = array(
				'tag' 	=> $tag,
				'id' 	=> $id, 
				'clas' 	=> explode('.',$clas),
				'eq' 	=> $eq, 
				'modif' => $modif,
				'inmediatos' => $inmediatos,
			);
		}
		
		return $sel_info;
	}
	
	protected function single_select($sel)
	{
		$sel_info = $this->interpreta_sel($sel);

		$item = $this;
		$coll = array($item);

		foreach( $sel_info as $info )
		{
			if(gettype($coll) != 'array')
				$coll = array($coll);

			extract($info);
	
			$refs = array();
			$coll3 = array();

			for( $x = 0; $x < count($coll); $x++ )
			{
 				$item = $coll[$x];

				// omisión del error
				if( gettype($item) != "object" )
					return;

				// Buscamos primero por lo más estricto id > class > tagname
				if( $id != "" )
					$coll2 = $item->getId($id);
				else if( $clas[0] != "" )
					$coll2 = $item->getClass($clas[0]);
				else if( $tag != "" )
					$coll2 = $item->getElementByTagName($tag);
				else
					$coll2 = $item;

				// Omitimos los resultados nulos o inválidos
				if( !$coll2 )
					continue;

				$coll2 = $coll2->childs; 

				// Descartamos los que no cumplen todos los criterios
				for( $i = count($coll2) - 1; $i >= 0  ; $i--)
				{
					$col = $coll2[$i];

					// para evitar resultados de búsqueda duplicados
					if(isset($refs[$col->id]))
						unset($coll2[$i]);

					if( $col->testItem($info) )
						$refs[$col->id] = 1;
					else
						unset($coll2[$i]);
				}

				// juntamos los resultados para continuar la selección a partir de estos
				$coll3 = array_merge($coll3,$coll2);
			}

			// si no hay resultados, retornamos objeto vacío
			if( count($coll3) == 0 )
				return null;

			// seleccionamos elemento único si se especifica el 'eq' en la cadena (paréntesis)
			if( $eq != -1 )
				$coll = $eq < count($coll3) ? $coll3[$eq] : array();
			else
				$coll = $coll3;

			// aplicamos el modificador (ej. :last)
			if( $modif && $coll )
			{
				$tmp = new Selector($this->map,$coll);
				$coll = $tmp->applyModif($modif)->single();
			}


			// buscamos inmediatos
			if($inmediatos)
			{
				// forzamos que sea otro select si es una selección compuesta para que se llame al de Select o a la de Item
				if(substr($sel,0,1) != '>')
				{
					$item = new Selector($this->map, $coll);
					$coll = $item->select(strchr($sel,'>')); // irá a parar al else de aquí abajo
				}
				else
				{
					foreach($inmediatos as $inmediato)
					{
						$sel_info_inmediato = $this->interpreta_sel($inmediato);
						$tmp = array();
						foreach($coll as $item1)
						{
							if( $item1->testItem($sel_info_inmediato[0]))
								$tmp[] = $item1;
						}
						$coll = new Selector($this->map, $tmp);
					}
				}
			}
		}

		return $coll;
	}

	protected function testItem($info)
	{
		extract($info);

		$attr = $this->getAttrs();
		$clases = isset($attr["class"]) ? explode(" ",$attr["class"]) : array();

		return !( $tag 	 != "" && strcasecmp($this->getName(),$tag) != 0  ||
				$clas[0] != "" && !array_in_array($clas,$clases) || 
				$id 	 != "" && strcasecmp($attr["id"],$id) )?1:0;
	}

	protected function single()
	{
		return $this;
	}

	public function eq($n)
	{
		return $this->childs[$n];
	}
	
	public function length()
	{
		return count($this->childs);
	}
	
	public function parent()
	{
		return $this->getParent();
	}

	public function first()
	{
		$nc = count($this->childs);
		return $nc > 0 ? $this->childs[0] : null;
	}
	
	public function last()
	{
		$nc = count($this->childs);
		return $nc > 0 ? $this->childs[$nc-1] : null;
	}
	
	public function _prev()
	{
		return $this->getPrev();
	}
	
	public function _next()
	{
		return $this->getNext();
	}

	public function parents($sel)
	{
		if( !$this->length() )
			return null;

		$sel_info = $this->interpreta_sel($sel);
		$sel_info = array_reverse($sel_info);

		$ret = array();
		$item = $this->parent();

		$first = NULL;
		$eq = -1;

		while($item && $item->id > 0 )
		{
			foreach( $sel_info as $sel_index => $info )
			{
				extract($info);

				$attr = $item->getAttrs();
				$clases = isset($attr["class"]) ? explode(" ",$attr["class"]) : array();

				if( $tag 	 != "" && strcasecmp($item->getName(),$tag) != 0  ||
					$clas[0] != "" && !array_in_array($clas,$clases) || 
					$id 	 != "" && strcasecmp($attr["id"],$id) )
				{
					$first = NULL;
					break;
				}
				else
				{
					if(!$first)
						$first = $item;

					if( $sel_index == count($sel_info)-1 )
					{
						array_push($ret, $first);
						$first = NULL;
						break;
					}
				}

				$item = $item->parent();
			}

			$item = $item->parent();
		}

		// seleccionamos elemento único si se especifica el 'eq' en la cadena (paréntesis)
		if($eq != -1 )
			$ret = $eq < count($ret) ? $ret[$eq] : array();

		// aplicamos el modificador (ej. :last)
		if( $modif && $ret )
		{
			$tmp = new Selector($this->map,$ret);
			$ret = $tmp->applyModif($modif);
		}

		$item = new Selector($this->map, $ret);

		// para que funcionen los foreachs de selects cuyo resultado solo genera un elemento
		if( count($item->childs) == 1 )
			$item = new Selector($this->map, $item);

		return $item;
	}

	protected function applyModif($modif)
	{			
		$tmp = $this;
		foreach( $modif as $mod )
		{
			if(!$tmp)
				throw new Exception('No es un objeto');

			$tmp2 = $tmp->$mod();
			// si el modificador no se aplica correctamente en el item inmediato es que estamos en una collection
			if($tmp2)
				$tmp = $tmp2;
			else // lo aplicamos al primer item de la collection
				$tmp = $tmp->first()->$mod();
		}

		return $tmp;
	}
	
	public function applyEq($eq)
	{
		if($eq < $this->length())
			return $this[$eq];
		else
			return array();
	}

	public function getSpider(){
		return $this->map->spider;
	}

	public function each($fn,$param)
	{
		for($i = 0; $i < count($this->childs); $i++)
			$fn($this->childs[$i], $i, $param);
	}

	public function html($indent=true, $depth = 0)
	{
		$tabs = "";
		$EOL = "";
		if($indent)
		{
			$tabs = str_repeat("   ",$depth);
			$EOL = "\n";
		}
		
		$char = trim($this->getText());
		if( $char )
			return "$tabs$char$EOL";

		$ret = "";
		$name = $this->getName();
		if( $name )
		{
			$ret = "$tabs<$name";

			$attrs = $this->getAttrs();
			foreach( $attrs as $key => $val )
				$ret.=" $key='$val'";
		}

		$childs = $this->getChilds();
		if(count($childs) == 0)
			return "$ret/>$EOL";

		if( $name )
			$ret .= ">$EOL";
		
		foreach( $childs as $item )
			$ret .= $item->html($indent, $depth+1);
		
		if( $name )
			$ret .= "$tabs</".$this->getName().">$EOL";

		return $ret;
	}

	public function __toString()
	{
		ob_start();

		if(isset($_SERVER['HTTP_HOST']))
		{
			print("<style>*{font-family:courier new;font-size:12px} .sig{cursor:pointer;cursor:hand;float:left;width:20px;position:absolute;} .mas .cabe{display:block;} .mas .tree{display:none} .men .cabe{display:none} .men .tree{display:block;background:#f0f0f0;}</style>");
			print("<style> .all .mas .cabe{display:none} .all .mas .tree{display:block} .all .sig{display:none} .all .men .tree{background:white} .sig2{cursor:pointer;cursor:hand;}</style>");
			print("<style> ul{list-style:none; padding-left:20px;margin:0 1px;} </style>");
		}

		$this->displaybase();

		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	}

	public function toRef($recursivo = false)
	{
		$str = $this->id;
		if($recursivo)
		{
			$arr = array();
			foreach($this->childs as $item)
				$arr[] = $item->toRef();
			if(count($arr))
				$str.='('.implode(',',$arr).')';
		}
		return $str;
	}

	public function toJson()
	{
		$name = get_class($this)=='Selector' ? '' :  $this->getName();
		$clas = @$attrs['class'];
		$char = $this->getText();
		$id = @$attrs['id'];

		$ret = "";
		if(count($this->childs))
			$ret .= "{";

		$ret .= "";
		$ret .= ($char?$char:$name);
		$ret .= $id?"#$id":"";
		$ret .= $clas?".".str_replace(' ','.',$clas):"";
		$ret .= "";

		if(count($this->childs))
		{
			foreach($this->childs as $item)
				$arr[] = $item->toJson();
			
			if(count($arr)>1)
				$ret .=':['.implode(',',$arr).']';
			else
				$ret .=':'.implode(',',$arr);
			
			$ret .= "}";
		}
		
		return $ret;
	}

	protected function displaybase($depth=0)
	{
		if(isset($_SERVER['HTTP_HOST']))
		{
			echo "<li>";
			$this->drawitem();
			echo "</li>";

			echo "<ul>";
			foreach($this->childs as $item)
				$item->displaybase();
			echo "</ul>";
		}
		else
		{
			$this->drawitem($depth);
			$depth++;
			foreach($this->childs as $item)
				$item->displaybase($depth);
			$depth--;
		}
	}

	protected function drawitem($depth=0)
	{
		$c = count($this->getChilds());
		$ref = $this->id;
		$name = get_class($this)=='Selector' ? '' :  $this->getName();
		$attrs = $this->getAttrs();
		$id = @$attrs['id'];
		$name_attr = @$attrs['name'];
		$clas = @$attrs['class'];
		$onclick = @$attrs['onclick'];
		$href = @$attrs['href'];
		$char = $this->getText();
		$parent = $this->getParent();

		if(isset($_SERVER['HTTP_HOST']))
		{
			if( $parent )
			{
				if( $parent->getName() == "a" )
					print("<a href='".$parent->getAttr('href')."'>");

				if( $parent->getName() == "link" || $parent->getName() == "FEEDBURNER:ORIGLINK" )
					print("<a href='".$char."'>");
			}

			if( $char||$char=='0')
				print("<font color=black><b>$char</b></font> ($ref)<br>");
			else
			{
				print("&lt;$name");

				if($name_attr)
					print(" name='$name_attr'");
				if($id)
					print(" id='$id'");
				if($clas)
					print(" class='$clas'");
				if($onclick)
					print(" onclick='$onclick'");
				if($href)
					print(" href='$href'");

				print("&gt;");
				print(" ($ref) - $c<br>");
			}

			if( $parent )
				if( $parent->getName() == "a" || $parent->getName() == "link" || $parent->getName() == "FEEDBURNER:ORIGLINK" )
					print("</a>");
		}
		else
		{
			echo str_repeat(' ',$depth);
			echo $char?$char:$name;
			echo $id?"#$id":"";
			echo $clas?".".str_replace(' ','.',$clas):"";
			echo "\n";
		}
	}
}

class HtmlItem extends Item 
{
	var $name, $attrs, $char, $parent;

	function getChilds() 	{ return $this->childs; }
	function getChar() 		{ return $this->char; 	}
	function setChar($char) { $this->char = $char;	}

	public function getAttrs() 		{ return $this->attrs; 							}
	public function getText() 		{ return normalize($this->char); 	}

	public function getAttr($attr)	{ return isset($this->attrs[$attr])?$this->attrs[$attr]:''; }
	public function attr($attr)		{ return $this->getAttr($attr); 							}
	public function getName()		{ return $this->name; 										}
	public function getValue()		{ return $this->getAttr('value'); 							}
	public function value()			{ return $this->getValue(); 								}

	function getInnerText() {
		$char = "";
		if( count( $this->childs ) ) {
			foreach( $this->childs as $ch ) {
				$char.=" ".$ch->getInnerText();
			}
		} else {
			$char = $this->char;
		}
		return trim( $char );
	}


	public function contents()
	{
		$ret = array();
		if( $this->getParent() )
		{
			$text = $this->getText();
			if($text!==null)
				$ret[] = $text;

			foreach( $this->childs as $item )
			{
				$text = $item->contents();
				if($text!==null)
					$ret[] = $text;
			}
			$ret = implode(' ',$ret);
		}
		else
		{
			if(!$this->childs)
				return "";

			foreach( $this->childs as $item )
				array_push($ret,$item->contents());
		}
		return $ret;
	}

	function getParent()
	{
		return $this->map->getNode($this->parent);
	}

	public function getPrev(){
		$par = $this->getParent();
		if( !$par )
			return;

		for($i=count($par->childs)-1;$i>=0;$i--)
			if(	$this->id > $par->childs[$i]->id )
				return $par->childs[$i];

		return $par;
	}

	public function getNext(){
		$par = $this->getParent();
		if( !$par )
			return;

		for( $i = 0; $i < count($par->childs) -1; $i++ )
			if( $par->childs[$i]->id == $this->id )
				return $par->childs[$i + 1];

		return $par->getNext();
	}

	public function select($sel)
	{
		$querys = explode(',', $sel);

		$item = new Selector($this->map);
		foreach($querys as $single_sel)
		{
			// evitamos elementos duplicados
			$ret = $this->single_select($single_sel);
			if( $ret )
				$item->childs = $this->merge_nodup($item->childs, $ret);
		}

		// para que funcionen los foreachs de selects cuyo resultado solo genera un elemento
		$single = $item->single();
		if( $single && count($single->childs) == 1 )
			$single = new Selector($this->map, $single);

		return $single;
	}

	// busca contenidos
	public function find($str)
	{
		$arr = array();
		if( !is_array($str) )
			$str = array($str);

		foreach( $str as $item )
			$this->findNext($arr,$item);

		$item = new Selector($this->map, array_values($arr));
		return $item;
	}

	public function findNext(&$arr, $str)
	{
		if( stripos( $this->getText(), $str ) !== false )
			$arr[$this->id] = $this;

		foreach($this->childs as $item)
			$item->findNext($arr, $str);
	}
}

class FormItem extends HtmlItem
{
	function submit($fld_val = null, $url = null, $image_name_click = null, $spider_dest = null)
	{
		$spider = $this->getSpider();
		if( !$url )
		{
			$url = $this->getAttr('action');
			$referer_url = $spider->url;
			$url = replace_url_path($referer_url, $url);
		}

		$fields = array();
		$inputs = $this->select('input, select');
		if($inputs)
			$fields = getFormFields($inputs);

		// rellenamos las coordenadas donde se supone que se pulsa la imagen para hacer el submit
		// si no se pasa parámetro se busca la primera imagen con nombre, y si se pasa, se utiliza la pasada en $image_name_click
		if( !$image_name_click )
		{
			if($inputs)
			foreach( $inputs as $key => $input )
			{
				if( $input->getAttr('type') == "image")
				{
					$image_name_click = $input->getAttr('name');
					if( $image_name_click )
						break;
				}
			}
		}

		if( $image_name_click )
		{
			if($image_name_click != "")
				$image_name_click = $image_name_click.".";

			$fields["{$image_name_click}x"] = rand(1,20);
			$fields["{$image_name_click}y"] = rand(1,10);
			unset($fields[$image_name_click]);
		}

		if($fld_val)
			$fields = array_merge($fields, $fld_val);

		foreach( $fields as $key => &$fld )
		{
			if($fld == "##unset##" )
				unset($fields[$key]);
			else
				$fld = urlencode($fld);
		}

		if($spider_dest)
			$spider = $spider_dest;

		$method = $this->getAttr('method');
		if( strtolower($method) == 'post')
			$root = $spider->post($url, $fields);
		else
		{
			array_walk($fields,function(&$item,$key){$item="$key=$item";});
			$data = implode('&',$fields);
			$root = $spider->get("$url?$data");
		}

 		return $root;
	}
}

class AnchorItem extends HtmlItem
{
	public function click()
	{
		return $this->map->spider->get( $this->attr('href') );
	}
}

class SelectItem extends HtmlItem
{
	public function value()
	{
		foreach( $this->select('option') as $opt)
			if(in_array('selected',$opt->getAttrs()))
				return $opt->value();

		return null;	
//		$sel = $this->getItem('selected');
//		return $sel && is_string($sel) ? $sel->value() : null;
	}
}

class IFrameItem extends HtmlItem
{
	public function get($spider_dest = null)
	{
		$spider = $this->map->spider;
		if($spider_dest)
			$spider = $spider_dest;

		return $spider->get( $this->attr('src') );
	}
}

class Selector extends Item
{
	public function Selector(&$map, $items = array())
	{
		$this->map = &$map;
		$this->childs = is_array($items)?$items:array($items);
	}

    public function __call($method, $args)
    {
    	if(method_exists($this, $method))
        {
			return call_user_func_array(
				array($this, $method),
				$args
			);	
		}
		else
		{
			if(in_array($method,array('getParent','getPrev','getNext')))
				return $this->runCommonFn($method,$args);

			if(in_array($method,array('select','find')))
				return $this->runCommonMergedFn($method,$args);
		}

		$ret = array();
		foreach($this->childs as $item)
		{
			$r = call_user_func_array(
				array($item, $method),
				$args
			);
			$ret[] = $r;
		}

		// Devuelve el primer item si solo hay un resultado 
		return count($ret)==1?$ret[0]:$ret;
	}
	
	protected function single()
	{
		if( count($this->childs)==0 )
			return null;
			
//return $this;

		return count($this->childs)==1?$this->childs[0]:$this;
	}

	public function runCommonMergedFn($method,$args)
	{
		# mergeamos y sacamos factor común para hacer las búsquedas con select y find

		$childs = array();
		foreach($this->childs as $child)
			$childs = $this->merge_nodup($childs, $child);

		$arr = array();
		foreach($childs as $item)
		{
			$ret = call_user_func_array(
				array($item, $method),
				$args
			);
			if($ret && $ret->length())
				foreach( $ret->childs as $par )
					$arr[$par->id] = $par;
		}

		$item =  new Selector( $this->map, array_values($arr) );
		return $item->single();
	}

	public function parents($sel)
	{
		# hacemos factor común de los padres comunes

		$arr = array();
		foreach($this->childs as $item)
		{
			$ret = $item->parents($sel);
			if($ret)
				foreach( $ret->childs as $par )
					$arr[$par->id] = $par;
		}

		$ret = new Selector( $this->map, array_values($arr) );
		return $ret->single();
	}

	public function runCommonFn($method,$args)
	{
		# hacemos factor común de los padres comunes de getParent, getPrev y getNext
		$arr = array();
		foreach($this->childs as $item)
			$arr[$item->id] = call_user_func_array(
				array($item, $method),
				$args
			);	

		return new Selector( $this->map, array_values($arr) );
	}

	public function __toString()
	{
		if($this->length() == 1)
		{
			$item = $this->first();
			if(!$item)
				return "";

			return $item->__toString();
		}

		ob_start();
		if(isset($_SERVER['HTTP_HOST']))
		{
			echo <<<EOF
<style>*{font-family:courier new;font-size:12px} .sig{cursor:pointer;cursor:hand;float:left;width:20px;position:absolute;} .mas .cabe{display:block;} .mas .tree{display:none} .men .cabe{display:none} .men .tree{display:block;background:#f0f0f0;}</style>
<style> .all .mas .cabe{display:none} .all .mas .tree{display:block} .all .sig{display:none} .all .men .tree{background:white} .sig2{cursor:pointer;cursor:hand;}</style>
<style> ul{list-style:none; padding-left:20px;margin:0 1px;} </style>
<script>
	$(document).ready(function(){
		$('.sig').click(function(event){
			obj = $(this).parent().parent();
			obj.attr('class',obj.hasClass('mas')?'men':'mas');
		});
	});
</script>
EOF;

			print("<div class='les'><div class='sig2' onclick=\"parentNode.className=(parentNode.className=='men'?'mas':'men');\">&lt;&gt;</div>");

			foreach($this->childs as $node)
			{
				print("<div class='mas'><div class='cabe'><div class='sig'>+ </div>&nbsp;&nbsp; ");
				$node->drawitem();
				print("</div><div class='tree'><div class='sig'>-</div>");
				echo "<ul>";
				$node->displaybase();
				echo "</ul>";
				print("</div></div>");
			}
			print("</div>");
		}
		else
		{
			foreach($this->childs as $node)
			{
				$node->drawitem()."\n";
				$node->displaybase();
			}
		}	
		
		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	}
	
	public function toJson()
	{
		$ret = "[";
		foreach($this->childs as $node)
			$ret .= $node->toJson();
		$ret .= "]";
		return $ret;
	}

	public function toRef($recursivo = false)
	{
		$arr = array();
		foreach($this->childs as $node)
			$arr[] = $node->toRef($recursivo);

		$refs = implode(',',$arr);
		return count($arr)<=1 ? $refs : "($refs)";
	}

	// iteration
	private $position = 0;
	function rewind() 	{ $this->position = 0; 												}
	function current() 	{
		$current = $this->eq($this->position);
		return $current;
	}
	function key() 		{ return $this->position; 											}
	function next() 	{ ++$this->position; 												}
	function valid() 	{ return isset($this->childs[$this->position]);						}
}
