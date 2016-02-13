<?php

// Объявление классов
// Настройки поезда и вагона. Какой поезд? Откуда и куда? Что ищем, какие места?
class train_set {
    public  // Настройки поиска поездов по датам отправления
            $station_id_from,
            $station_id_till,
            $station_from,
            $station_till,
            $date_dep,
            $time_dep,
            $time_dep_till,
            $another_ec,
            $search,
            // Настройки поиска нужного поезда по готовым результатам поиска
            // А так же Какой тип мест нужен?
            $train, // Номер поезда. Например 148К
            $coach_type, // Тип места П - Плацкарт Л - Люкс К - Купе (Все потом в урлы кодируется)
            $model,
            $date_dep_java,
            $round_trip;
}



// Класс для Параметров UZ 
// Тут настройки самого сайта. Его параметры сессии, код html загруженый и т д
class uz_class {
      public  $html, 
              $cookie_gv_sessid, 
              $cookie_gv_server_n, 
              $token;  
}




// Общая функция Get 
// Получаем в ответ заголовки с куками и html страницу 
function curl_get_uz($url, $cookiefilename) {
// Курлом получаем заголовки сервера с куками и html с главной страницы
  if( $curl = curl_init() ) {
    curl_setopt($curl,CURLOPT_URL,$url);
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    //curl_setopt($curl,CURLOPT_COOKIEFILE, $cookiefilename);
    //curl_setopt($curl,CURLOPT_COOKIEJAR,$cookiefilename);
    curl_setopt($curl,CURLOPT_NOBODY,false);
    curl_setopt($curl,CURLOPT_HEADER,true);
    return curl_exec($curl);
    curl_close($curl);
  }
  
}

// Общая функция ПОСТ
// Она использует уже полученый ранне токен и ид сессии
function uz_post($url,$postdata,$token, $session_id, $server_n) {
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
$headers = array
(
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*;q=0.8',
    'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4,bg;q=0.2',
    'Accept-Encoding: gzip, deflate',
    'GV-Token: '.$token,
    'GV-Unique-Host: 1',
    'Content-Type: application/x-www-form-urlencoded',
    'GV-Ajax: 1',
    'Proxy-Connection: keep-alive',
    'Host: booking.uz.gov.ua',
    'Cookie: _gv_sessid='.$session_id.'; _gv_lang=ru; HTTPSERVERID='.$server_n.'; __utmt=1; __utma=31515437.2137620103.1434828433.1434828433.1434828433.1; __utmb=31515437.2.10.1434828433; __utmc=31515437; __utmz=31515437.1434828433.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)',
    'Referer: http://booking.uz.gov.ua/ru/',
    'GV-Screen: 1920x1080',
    'GV-Referer: http://booking.uz.gov.ua/ru/',
    'Accept: */*'
); 

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
$rs = curl_exec($ch);
curl_close($ch);
return $rs;
}



// Класс для декодирования токена
class JJDecode{
	protected $glob_var=null;

	function Decode($str){
		while(preg_match('#=\~\[\];\s*(.+?)\s*\=\{#is', $str, $mth)){
			$this->glob_var=$mth[1];

			$str=$this->ParseJs($str);
		}
		
		return $str;
	}

	/** Парсинг всех участков зашифрованных JJEncode
	 *
	 * @param string $str
	 * @return string
	 */
	protected function ParseJs($str){
		$preg='#'.preg_quote($this->glob_var, '#').'=\~\[\];\s*'.
			''.preg_quote($this->glob_var, '#').'={\s*([\s|\S]+?)\s*};\s*'.
			'[\s|\S]+?'.
			'\s*'.preg_quote($this->glob_var).'.\$\('.preg_quote($this->glob_var, '#').'.\$\(([\s|\S]+?)\)\(\)\)\(\);#is';

		$newstr=preg_replace_callback($preg, array($this, 'ParseStr'), $str);

		return $newstr;
	}

	/** Функция колбека для ParseJs($str)
	 *
	 * @param array $mathes
	 * @return string
	 */
	private function ParseStr($mathes){

		$obufstr=$mathes[2];
		$alpha=$mathes[1];

		//Выделяем начальный алфавит
		$alphabet=$this->ParseAlphabet($alpha);

		if(!is_array($alphabet))return '';

		$alphabet=array_merge($alphabet, $this->ParseAlphabetAdd());

		//Деобусфицируем строку
		$newstr=$this->ParseObufStr($obufstr, $alphabet);

		//Приводим строку к нормальному виду (без escape последовательностей)
		$newstr=$this->ParseDecodeStr($newstr);

		return $newstr;
	}

	/** Очищаем строку от escape-последовательностей
	 *
	 * @param string $str деобфусцированная строка
	 * @return string
	 */
	protected function ParseDecodeStr($str){
		$str=preg_replace_callback('#\\\\([0-7]{1,3})#i', array($this, 'ParseDecodeStrCallback'), $str);

		return $str;
	}

	/** Колбэк для ParseDecodeStr. Преобразование каждой escape - последовательности с учетом восьмиричной системы
	 *
	 * @param array $mathes
	 * @return string
	 */
	private function ParseDecodeStrCallback($mathes){
		$int=$mathes[1];
		$add='';

		while(($dec=octdec($int))>255){
			$add=substr($int, -1);
			$int=substr($int, 0, -1);

			if(strlen($int)<1)break;
		}

		return chr(octdec($int)).$add;
	}

	/** Приведение обфусцированной строки к упрощенному виду для облегчения разделения на "слагаемые"
	 *
	 * @param string $str обфусцированная строка
	 * @return string упрощенный вид
	 */
	private function ParseObufStrRaw($str){
		$nstr='';
		$incnt=0;
		$quote=false;
		for($i=0, $x=strlen($str); $i<$x; $i++){
			$char=$str{$i};

			if($char!='+'){
				if($char=='"'){
					$quote=!$quote;
					$nstr.='x';
					continue;
				}

				if($quote){
					if($char=='\\'){
						$i++;
						$nstr.='xx';
						continue;
					}else{
						$nstr.='x';
						continue;
					}
				}

				if(in_array($char, array('{', '[', '('))){
					$incnt++;
				}elseif(in_array($char, array('}', ']', ')'))){
					$incnt--;
				}

				$nstr.='x';
			}else{
				if($quote){
					$nstr.='x';
				}else{
					if($incnt==0){
						$nstr.='+';
					}else{
						$nstr.='x';
					}
				}
			}
		}

		$arr=array();
		$words=explode('+', $nstr);
		$pos=0;
		foreach($words as $word){
			$len=strlen($word);
			$arr[]=substr($str, $pos, $len);

			$pos+=($len+1);
		}

		return $arr;
	}

	/** Деобфусцирование строк
	 *
	 * @param string $str обфусцированная строка
	 * @param array $alphabet алфавит
	 * @return string
	 */
	protected function ParseObufStr($str, $alphabet){
		$array=$this->ParseObufStrRaw($str);

		$nstr='';
		$unk=array();
		foreach($array as $val){
			$val=trim($val);
			if(empty($val))continue;

			if(preg_match('#^'.preg_quote($this->glob_var).'\.([\_\$]{1,4})$#i', $val, $mth)){

				if(array_key_exists($mth[1], $alphabet)){
					$nstr.=$alphabet[$mth[1]];
				}else{
					$unk[]=$val;
					$nstr.='?';
				}
			}elseif(preg_match('#^"(.*)"$#i', $val, $mth)){
				$nstr.=str_replace('\"', '"', stripslashes($mth[1]));
			}elseif(preg_match('#\((.+)\)\['.preg_quote($this->glob_var).'\.([\_\$]{1,4})\]#i', $val, $mth)){
				if(array_key_exists($mth[2], $alphabet)){
					if(strpos($mth[1], '![]+""')!==false){
						$tmp='false';
						$nstr.=$tmp{$alphabet[$mth[2]]};
					}else{
						$unk[]=$val;
						$nstr.='?';
					}
				}else{
					$unk[]=$val;
					$nstr.='?';
				}
			}else{
				$unk[]=$val;
				$nstr.='?';
			}
		}

		if(count($unk)>0){

		}

		if(preg_match('#return\s*"(.+)"#i', $nstr, $mth)){
			$nstr=$mth[1];
			return $nstr;
		}else{
			return false;
		}
	}

	protected function ParseAlphabetAdd(){
		return array(
			'$_'=>'constructor',
			'$$'=>'return',
			'$'=>'function Function() { [native code] }',
			'__'=>'t',
			'_$'=>'o',
			'_'=>'u',
		);
	}

	/** Парсинг участка с алфавитом
	 *
	 * @param string $str участок строки с алфавитом
	 * @return array
	 */
	protected function ParseAlphabet($str){

		if(!preg_match_all('#([\_|\$]{2,4})\:(.+?),#i', $str.',', $mth)){

			return false;
		}

		$newarr=array();
		$val_o=0;

		for($i=0, $x=count($mth[0]); $i<$x; $i++){
			$key=$mth[1][$i];
			$val=$mth[2][$i];

			if($val=='++'.$this->glob_var.''){
				$newarr[$key]=$val_o;
				$val_o++;
			}elseif(strpos($val, '(![]+"")')!==false){
				$tmp='false';
				$newarr[$key]=$tmp{($val_o-1)};
			}elseif(strpos($val, '({}+"")')!==false){
				$tmp='[object Object]';
				$newarr[$key]=$tmp{($val_o-1)};
			}elseif(strpos($val, '('.$this->glob_var.'['.$this->glob_var.']+"")')!==false){
				$tmp='undefined';
				$newarr[$key]=$tmp{($val_o-1)};
			}elseif(strpos($val, '(!""+"")')!==false){
				$tmp='true';
				$newarr[$key]=$tmp{($val_o-1)};
			}
		}

		if(count($newarr)!==16){
			return false;
		}

		return $newarr;
	}
}

//=============================================================================
// ОСТОРОЖНО!!!
// БЫДЛОКОД
function get_place_index($place_index, $s) {

                switch ($place_index) {
                     case "А":
                       $places = $s->value->places->А;
                       break;
                     case "Б": 
                       $places = $s->value->places->Б;
                       break;
                     case "В": 
                       $places = $s->value->places->В;
                       break;
                     case "Г": 
                       $places = $s->value->places->Г;
                       break;
                     case "Д": 
                       $places = $s->value->places->Д;
                       break;
                     case "Е": 
                       $places = $s->value->places->Е;
                       break;
                     case "Ж": 
                       $places = $s->value->places->Ж;
                       break;
                     case "З": 
                       $places = $s->value->places->З;
                       break;
                     case "И": 
                       $places = $s->value->places->И;
                       break;
                     case "Й": 
                       $places = $s->value->places->Й;
                       break;
                     case "К": 
                       $places = $s->value->places->К;
                       break;
                     case "Л": 
                       $places = $s->value->places->Л;
                       break;
                     case "М": 
                       $places = $s->value->places->М;
                       break;
                     case "Н": 
                       $places = $s->value->places->Н;
                       break;
                     case "О": 
                       $places = $s->value->places->О;
                       break;
                     case "П": 
                       $places = $s->value->places->П;
                       break;
                     case "Р": 
                       $places = $s->value->places->Р;
                       break;
                     case "С": 
                       $places = $s->value->places->С;
                       break;
                     case "Т": 
                       $places = $s->value->places->Т;
                       break;
                     case "У": 
                       $places = $s->value->places->У;
                       break;
                     case "Ф": 
                       $places = $s->value->places->Ф;
                       break;
                     case "Х": 
                       $places = $s->value->places->Х;
                       break;
                     case "Ц": 
                       $places = $s->value->places->Ц;
                       break;
                     case "Ч": 
                       $places = $s->value->places->Ч;
                       break;
                     case "Ш": 
                       $places = $s->value->places->Ш;
                       break;
                     case "Щ": 
                       $places = $s->value->places->Щ;
                       break;
                     case "Ъ": 
                       $places = $s->value->places->Ъ;
                       break;
                     case "Ы": 
                       $places = $s->value->places->Ы;
                       break;
                     case "Ь": 
                       $places = $s->value->places->Ь;
                       break;
                     case "Э": 
                       $places = $s->value->places->Э;
                       break;
                     case "Ю": 
                       $places = $s->value->places->Ю;
                       break;
                     case "Я": 
                       $places = $s->value->places->Я;
                       break;
                }
    return $places;
}

?>
