<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;
class Objects{

    private $path = ""; //ссылка на файл
    private $arResult = [];
    private $arFileds = [];
    private $arUsers = [];
    private $Hl; //id HL куда добавляем
    private $idUser; //ID пользователя если не найден
    public $arrItemsErorr = []; //массив записей с ошибками

    function __construct ( $path, $idUser, $fields, $code ) {
        $this->path = $path;
        $this->idUser = $idUser;

        $this->getFileds( $fields );
        $this->getUsers();
        $this->getIdHl( $code );
        $this->parseCsv();
    }


    //пользовательские поля по ID
    private function getFileds( array $id ){

        $arrStatus = CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $id]);
        while($arEnum = $arrStatus->Fetch()){
            $this->arFileds[$arEnum["VALUE"]] = $arEnum["ID"];
        }

    }


    //список фамилий пользователей
    private function getUsers(){
        $arManagers = \Bitrix\Main\UserTable::getList(["select" => ["ID", "LAST_NAME"]]);
        while ($arManager = $arManagers->fetch()) {
            $this->arUsers[$arManager['LAST_NAME']] = $arManager['ID'];
        }
    }


    //поиск ID Hl по коду
    public function getIdHl( $code ){

        if(!Loader::IncludeModule('highloadblock')){
            die("Не установлен модуль highloadblock");
        }

        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(
            ["filter" => ['TABLE_NAME' => $code]]
        )->fetch();

        if($hlblock['ID']){
            $this->Hl = $hlblock['ID'];
        }
        die("Не найден HL");
        
    }


    //иниациализация HL
    private function getDataClass(){

        $hlblock = HL\HighloadBlockTable::getById( $this->Hl )->fetch(); 
        $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 

        if($entity_data_class){
          return $entity_data_class;
        }
        return false;

    }


    //добавление в HL
    public function addHl(){

        $entity_data_class = $this->getDataClass( $this->Hl );
        foreach($this->arResult as $item){

            $result = $entity_data_class::add($item);
          
            if ($result->isSuccess()) {
                $result[] = $result->getId();
            }else{
                $result[] = $result->getErrorMessages();
                $this->arrItemsErorr[] = $item;
            }
        }

        $this->pre( $result );
          
    }


    public function pre( $array ){

        echo "<pre>";
        print_r( $array );
        echo "</pre>";

    }


    //парсим и создаём массив даных
    private function parseCsv(){

        if (!file_exists($this->path)) {
            die("Файл не найден");
        }

        if(($handle_f = fopen($this->path, "r" )) !== FALSE){

            fseek($handle_f, 4096);
            while(!feof($handle_f)){
                
                $line = fgets($handle_f);
                
                // меняем кодировку в файле на UTF-8
                $buffer = iconv("WINDOWS-1251", "UTF-8", $line);
            
                //разбиваем строку на значения и помещаем в массив
                $data = explode(';', $buffer);
            
            
                if($data[4] && $data[6] && $data[8]){
            
                    if($data[3]){
                        $this->arUsers[ substr($data[3], 0, strpos($data[3], ' ' )) ] ? $user = $this->arUsers[ substr($data[3], 0, strpos($data[3], ' ' )) ] : $user = $this->idUser;
                    }else{
                        $user = $this->idUser;
                    }
            
                    $this->arResult[] = [
                        'UF_NAME' => $data[4],
                        'UF_USER' => $user,
                        'UF_YEAR' => $data[5],
                        'UF_CATEGORY' => $this->arFileds[$data[6]], 
                        'UF_COUNTRY' => $data[8],
                        'UF_OKRUG' => $data[9],
                        'UF_OBLAST' => $data[10],
                        'UF_CITY' => $data[11],
                        'UF_ADRES' => $data[8].', '.$data[9].', '.$data[10].', '.$data[11],
                        'UF_DATE' => $data[2] ? $data[2] : date("d.m.Y"),
                    ];
                }
            
            }
            
            fclose($handle_f);
        }

    }


}

$reserv = new Reserv( "/upload/csv/objects.csv", 1, [149], "objects" );
$reserv->addHl();
$reserv->pre( $reserv->arrItemsErorr );

//вместо die можно использовать исключения, но класс накидан на коленке из процедурного метода. Так же можно добавить проверку на дубликаты перед добавлением в Hl.
//При формировании массива можно добавить преобразование значения полей и использовать htmlspecialchars
?>
