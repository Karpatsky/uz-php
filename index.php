<?php
require_once('smsclient.class.php');
require_once('uz_fn.php');
$uz=new uz_class();
$tr=new train_set();

// Установка параметров и настроек
$tr->station_id_from = '2218217';
$tr->station_id_till = '2200001';
$tr->station_from = urlencode('Ворохта');
$tr->station_till = urlencode('Киев');
$tr->date_dep = '10.12.2015';
$tr->time_dep = urlencode('00:00');
$tr->time_dep_till = '';
$tr->another_ec = '0';
$tr->search = '';
// Настройки поиска нужного поезда по готовым результатам поиска
// А так же Какой тип мест нужен?
$tr->train = urlencode('358Л'); // Номер поезда. Например 148К
$tr->coach_type = urlencode('П'); // Тип места П - Плацкарт Л - Люкс К - Купе (Все потом в урлы кодируется)
$tr->model = '0';
$tr->date_dep_java = '';
$tr->round_trip = '0';
$uz->html = curl_get_uz('http://booking.uz.gov.ua/ru/',"cookie.txt") ; 
// Парсинг Куки сессии
preg_match('~Set-Cookie:..gv.sessid=(.*?);.path=~' , $uz->html, $str );
$uz->cookie_gv_sessid = $str[1];
// Парсинг номера сервера
preg_match('~Set-Cookie: HTTPSERVERID=(.*?);.path=~' , $uz->html, $str );
$uz->cookie_gv_server_n = $str[1];
// Парсинг GV-токена
preg_match('~gaq.push....trackPageview...;(.*?).function .. .var~' , $uz->html , $gvstr );
$jj=new JJDecode();
$str = $jj->Decode($gvstr[1]);
preg_match('~,."(.*?)..;~' , $str , $token );
$uz->token = $token[1];
// Формируем запрос на получения поездов
$url = 'http://booking.uz.gov.ua/ru/purchase/search/';
$postdata = "station_id_from=$tr->station_id_from&station_id_till=$tr->station_id_till&station_from=$tr->station_from&station_till=$tr->station_till&date_dep=$tr->date_dep&time_dep=$tr->time_dep&time_dep_till=$tr->time_dep_till&another_ec=$tr->another_ec&search=$tr->search";
// Отправка запроса
$result = uz_post($url,$postdata,$uz->token,$uz->cookie_gv_sessid,$uz->cookie_gv_server_n);
// Обработка результатов
$inp = $result;
$s = preg_replace('/\\\u0([0-9a-fA-F]{3})/','&#x\1;',$inp);
$s = html_entity_decode($s, ENT_NOQUOTES, 'UTF-8');//документ должен быть в кодировке utf-8  
$s = json_decode($s);
$train = $s->value[0]->types[2]->places; // 12345
$tr->date_dep_java = $s->value[0]->from->date;   
if ($s->error == 'true') {
    echo $s->value;
    exit;
}
// Цикл по массиву из всех полученных поездов.
// Если цикл находит нужный номер поезда делает запрос по наличию мест
foreach ($s->value as $item) {
    
    // Если номер поезда как нам нужно, получаем его инфу и начинаем работу
    if ($item->num == urldecode($tr->train)) {
        
        // Формирование запроса для получения вагонов.
        $postdata = "station_id_from=$tr->station_id_from&station_id_till=$tr->station_id_till&train=$tr->train&coach_type=$tr->coach_type&model=$tr->model&date_dep=$tr->date_dep_java&round_trip=$tr->round_trip&another_ec=$tr->another_ec";
    
        $url = 'http://booking.uz.gov.ua/ru/purchase/coaches/';
        // Отправка запроса
        $result = uz_post($url,$postdata,$uz->token,$uz->cookie_gv_sessid,$uz->cookie_gv_server_n);
        // Обработка результатов
        $inp = $result;
        $s = preg_replace('/\\\u0([0-9a-fA-F]{3})/','&#x\1;',$inp);
        $s = html_entity_decode($s, ENT_NOQUOTES, 'UTF-8');//документ должен быть в кодировке utf-8  
        $s = json_decode($s);
        $coaches = $s->value->coaches;   

        $i = 0;

            // Получили Вагоны, теперь ищем в массиве все вагоны
            // И по вагонам делаем запросы про наличие мест
            foreach ($coaches as $coach_item) {
                // Устанавливаем параметры для вагона
                $coach_num = urlencode($coach_item->num);
                $coach_class = urlencode($coach_item->coach_class);
                $coach_type_id = urlencode($coach_item->coach_type_id);
                $coach_date_dep = $tr->date_dep_java;
                // Формируем запрос на вагон
                $postdata = "station_id_from=$tr->station_id_from&station_id_till=$tr->station_id_till&train=$tr->train&coach_num=$coach_num&coach_class=$coach_class&coach_type_id=$coach_type_id&date_dep=$coach_date_dep&change_scheme=0";
                $url = 'http://booking.uz.gov.ua/ru/purchase/coach/';
                // Отправка запроса
                $result = uz_post($url,$postdata,$uz->token,$uz->cookie_gv_sessid,$uz->cookie_gv_server_n);
                // Обработка результатов
                $inp = $result;
                $s = preg_replace('/\\\u0([0-9a-fA-F]{3})/','&#x\1;',$inp);
                $s = html_entity_decode($s, ENT_NOQUOTES, 'UTF-8');//документ должен быть в кодировке utf-8 
                // Узнаем буквенный индекс вагона
                preg_match('~places".."(.*?)":~',$s,$place_index);
                $place_index = $place_index[1]; 
                $s = json_decode($s);
                // Присваиваем массив со списком свободных мест    
                $places = get_place_index($place_index,$s);
                // Циклом ищем нижние места
                foreach ($places as $place_item) {
                    // Если место нижнее инкрементируем переменную i
                    if($place_item%2) {
                    $i++;
                    }
                }
            } 
// Загрузка прошлого количества мест. Знаю, что очень тупо
$lastplaces = file_get_contents('places.txt');
// Если наличие мест сейчас больше 0 то...
if ($i>0) {
    //Если прошлый раз мест было меньше, чем сейчас
    if ($lastplaces < $i) {
        // Пишем в файл, сколько мест сейчас
        file_put_contents('places.txt', $i);   
        // Отправляем Имейл
        $clien_order_message = "$i нижних мест уже свободно.";
        $clien_order_mail = (mail('Почта получателя', "Есть места - $i!", $clien_order_message));
        // Отправляем смс
        $sms = new SMSclient('логин', 'пароль');
        $sms->sendSMS('Bilet','+Телефон получателя', "Осталось $i нижних плацкартных мест");  
        echo("Осталось <b>$i</b> нижних плацкартных мест<br><br>");
    }
}
        print_r ("Наличие поезда на $tr->date_dep - <b>Да</b><br><br>");
        print_r ("Мест Люкс - <b>".$item->types[0]->places."</b> <br><br>");
        print_r ("Мест Купе - <b>".$item->types[1]->places."</b> <br><br>");
        print_r ("Мест Плацкарт - <b>".$item->types[2]->places."</b> <br><br>");
        print_r ("Мест Плацкарт нижних - <b>".$i."</b> <br><br>");
      }
}

?>
