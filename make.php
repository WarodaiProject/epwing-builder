<?php
//Версия скрипта
$scriptVersion = '1.0';

//Название скрипта
$scriptName = 'WARODAI_EBCONV';

//Корпус
if(!isset($argv[1])){
    throw new Exception('Corpus is not defined.');
}
$edition = $argv[1];

//Входные файлы
$inputDir = 'SRC/'.$edition;
//Файл с заранее сгенерированными битмапами гайдзи
$gaijiPregenFile = "{$inputDir}/Gaiji_pregen.xml";
//Файл-шаблон словаря (содержит все, кроме корпусов всех видов и копирайта)
$dictTmplFile = "{$inputDir}/dict.tmpl.html";
//Файл копирайта
$copyrightFile = "{$inputDir}/copyright.html";
//Файл словаря в txt　формате (содержит все корпуса)
$warodaiTXTFileName = strtolower($edition).'.txt';
$warodaiTXTSrcFile = "{$inputDir}/{$warodaiTXTFileName}";

//Выходные файлы
$EBStudioSourceDir = "HTML/{$edition}";
$EBWinDir = "EPWING/{$edition}";
//Содержит весь исходник в формате HTML
$DAIDictHTMLFile = "{$EBStudioSourceDir}/dict.html";
//Выходной файл копирайта
$copyrightHTMLFile = "{$EBStudioSourceDir}/copyright.html";
//Файл GaijiMap.xml - соответствие юникод-кодов ebcode-кодам
$GaijiMapFile = "{$EBStudioSourceDir}/GaijiMap.xml";
//Файл Gaiji.xml - содержит набор битмапов гайдзи с правильным юникод- и ebcode-кодами
$GaijiFile = "{$EBStudioSourceDir}/Gaiji.xml";
//Файл .map - файл обратного соответствия ebcode-кодов юникод-кодам
//Используется EBWin для правильной подстановки символов при копирование
//Не является исходником для EbStudio, должен кластся туда же, куда EbStudio складывает
//словарь в формате EPWING и должен называться так же, как папка, в которой он лежит
//и как указано название словаря в настроечном файле EbStudio с расширением .ebs (опция Book)
$WARODAIMapFile = "{$EBWinDir}/{$edition}.map";

//-------------------------Дальше лучше не редактировать, если не знаешь, что делаешь-----------------------------//
date_default_timezone_set("UTC");

//Хранит заранее сгенерированные битмапы гайдзи
//проиндексирован по коду юникода
$gaijiPregenBitmaps = [];

//Регулярное выражение подстановочных маркеров в шаблоне
$insertMarkersReg = '/\{\{ *([a-z0-9_-]+)\.([a-z0-9_-]+) *\}\}/i';

//Информация для подстановки в файл копирайте
$versionInfo = [
    'VERSION.TEXT_SOURCE_DATE'=>gmdate('\U\T\C Y-m-d H:i:s',filemtime($warodaiTXTSrcFile)),
    'VERSION.COMPILE_DATE'=>gmdate('\U\T\C Y-m-d H:i:s'),
    'VERSION.SCRIPT_NAME'=>$scriptName,
    'VERSION.SCRIPT_VERSION'=>$scriptVersion
];

try {
    logMsg("Converting WARODAI->EPWING source for Edition {$edition}");

    //Считываем заранее сгенерированные битмапы гайдзи
    logMsg("Loading pregenerated Gaiji bitmaps from {$gaijiPregenFile}");
    if(!empty($gaijiPregenFile)){
        $gaijiPregenBitmaps = loadPregenGaiji($gaijiPregenFile);
    }

    //Считываем файл-шаблон
    logMsg("Loading dictionary HTML template from {$dictTmplFile}");
    $dictTmpl = file_get_contents_utf($dictTmplFile);

    //Считываем файл-копирайта
    logMsg("Loading copyright notes HTML from {$copyrightFile}");
    $copyrightInfo = file_get_contents_utf($copyrightFile);

    //Разбираем подстановочные маркеры в шаблоне словаря
    logMsg("Parsing include instructions in {$dictTmplFile}");
    $insertMarkers = [];
    if(preg_match_all($insertMarkersReg,$dictTmpl,$insertMarkersMatches,PREG_SET_ORDER)){
        foreach($insertMarkersMatches as $m){
            if(!isset($insertMarkers[$m[1]])){
                $insertMarkers[$m[1]] = [];
            }
            $insertMarkers[$m[1]][] = [
                'marker'=>$m[0],
                'prefix'=>$m[1],
                'term'  =>$m[2]
            ];
            logMsg("\t$m[0] - found");
        }
    }

    //Загружаем исходник словаря
    logMsg("Loading dictionary source from {$warodaiTXTSrcFile}");
    $corpuses = loadWarodaiTXTSrc($warodaiTXTSrcFile);

    //Формируем HTML корпусов
    logMsg("Generating HTML corpuses.");
    $articlesHTML = [];
    foreach($corpuses as $corpus=>$articles){
        $articlesHTML[$corpus] = "<dl>\n\n";
        foreach($articles as $article){
            $articlesHTML[$corpus] .= "\n\n".articleToHTML($article,$edition);
        }
        $articlesHTML[$corpus] .= "\n\n</dl>";
        logMsg("\t{$corpus} - done");
    }

    //Производим вставку корпусов
    logMsg("Inserting corpuses into dictionary HTML.");
    foreach($insertMarkers['CORP'] as $corp){
        if(isset($articlesHTML[$corp['term']])){
            $dictTmpl = str_replace($corp['marker'], $articlesHTML[$corp['term']], $dictTmpl);
            logMsg("\t".$corp['marker']);
        }
    }

    //Производим подстановку информации в файл копирайта
    logMsg("Inserting info into copyright HTML.");
    if(preg_match_all('/\{\{ *([a-z0-9_.-]+) *\}\}/i',$copyrightInfo,$insertMarkersMatches,PREG_SET_ORDER)){
        foreach($insertMarkersMatches as $m){
            $copyrightInfo = str_replace($m[0],$versionInfo[$m[1]],$copyrightInfo);
            logMsg("\t$m[0]");
        }
    }

    //Конвертируем сразу два файла:
    //словарь и
    //копирайт
    logMsg("Converting dictionary and copyright notes");
    $convertedDict = encodeGaiji(
        [
            'dict'=>$dictTmpl,
            'copyright'=>$copyrightInfo
        ],
        $gaijiPregenBitmaps
    );

    //Выводим результат
    logMsg("Dumping result to files");
    file_put_contents($DAIDictHTMLFile,$convertedDict['outputs']['dict']);
    logMsg("\t$DAIDictHTMLFile");
    file_put_contents($copyrightHTMLFile,$convertedDict['outputs']['copyright']);
    logMsg("\t$copyrightHTMLFile");
    file_put_contents($GaijiMapFile,$convertedDict['gaijiMap']);
    logMsg("\t$GaijiMapFile");
    file_put_contents($GaijiFile,$convertedDict['gaiji']);
    logMsg("\t$GaijiFile");
    file_put_contents($WARODAIMapFile,$convertedDict['mapFile']);
    logMsg("\t$WARODAIMapFile");

    //Делаем отчет о конвертации
    logMsg("Generating gaiji encoding report - {$EBStudioSourceDir}/encoding_report.html");
    generateEncodingReport(
        $convertedDict['unicodeToEbcodeIdx'],
        $convertedDict['gaijiIdx'],
        $convertedDict['generatedGaijiIdx'],
        "{$EBStudioSourceDir}/encoding_report.html"
    );
    logMsg("Done!");
}
catch (Exception $e){
    logMsg($e->getMessage());
}

//------------------Functions-------------------//
function loadPregenGaiji($filePath){
    $gaiji = [
        'hankaku'=>[],
        'zenkaku'=>[]
    ];
    $fileContents = @file_get_contents($filePath);
    if($fileContents === FALSE){
        throw new Exception('Cannot load pregenerated gaiji from '.$filePath);
    }

    $gaijiReg = '/<fontData[^>]+unicode="([0-9A-Fa-f]+)"[^>]*>([ #\n]+)<\/fontData>/m';
    $matches=[];
    if(preg_match_all($gaijiReg,$fileContents,$matches,PREG_SET_ORDER)){
        foreach($matches as $match){
            //раскладываем символы отдельно ханкаку и дзэнкаку
            $gaiji[detectCharBitmapWidth($match[2])][strtoupper($match[1])] = $match[2];
        }
    }
    return $gaiji;
}

function loadWarodaiTXTSrc($filePath){
    $articles = [];

    //Загружаем с учетом того, что файл в одной из кодировок UTF16
    $fileContents = @file_get_contents_utf($filePath);
    if($fileContents === FALSE){
        throw new Exception('Cannot load WARODAI txt source file from '.$filePath);
    }

    //Разрезаем весь файл на статьи (по двойному переносу строки)
    $rawArticles = explode("\n\n",$fileContents);
    //память нам еще понадобится
    unset($fileContents);

    //Проходим по каждой статье, парсим ее и складываем в массив.
    foreach($rawArticles as $article){
        if(empty(trim($article)) || $article[0] == '*'){
            //Если статья пустая, или первый символ * (это скорее всего лицензия), то пропускаем такую статью
            continue;
        }
        try{
            $parseArticle = parseWarodaiArticle($article);
            if(!isset($articles[$parseArticle['header']['corpus']['code']])){
                $articles[$parseArticle['header']['corpus']['code']] = [];
            }
            $articles[$parseArticle['header']['corpus']['code']][] = $parseArticle;
        }
        catch(Exception $e){
            //Эту статью распарсить не удалось.
            logMsg("The article was not parsed:\n".$article."\n\t".$e->getMessage());
        }
    }
    return $articles;
}

function parseWarodaiArticle($rawArticle){
    //структура данных статьи
    $article = [
        'header'=>[],           //Заголовок - содержит все, кроме тела статьи
        'body'=>[],             //Тело статьи (массив строк)
        'comments'=>[],         //Массив строк комментариев
        'xcomments'=>[],        //Массив строк рабочих комментариев
        'headerAddition'=>[]    //Массив дополнительных слов, взятых из комментариев:
                                //каждый элемент массива имеет поле comment - комментарий
                                //и words - структура аналогична header['words'])
    ];

    //Разбиваем статью на строки
    $articleStrings = explode("\n",$rawArticle);
    //Если в статье меньше 2 строк - это точно не статья.
    if(count($articleStrings) < 2){
        throw new Exception('Article is too small:'.$rawArticle);
    }
    //Первая строка статьи - заголовок (сразу убираем его из тела статьи)
    $header = array_shift($articleStrings);

    $article['header'] = parseWarodaiHeader($header);

    $xcommentsFlag = false;
    foreach($articleStrings as $articleString){
        if($xcommentsFlag){
            $article['xcomments'][] = $articleString;
        }
        elseif(preg_match('/^※/u',$articleString)){
            $xcommentsFlag = true;
            continue;
        }
        elseif(preg_match('/^•/u',$articleString)){
            $article['comments'][] = $articleString;
            //Парсинг дополнительных написаний
            if(preg_match('/^• ?(Также|Др\. чтение|Редуц\.|Вариант слова)(.+)$/u',$articleString,$match)){
                $article['headerAddition'][] = [
                    'comment'=>$articleString,
                    'words'=>parseHeaderAddition($match[2],$article['header'])
                ];
            }
        }
        else{
            $article['body'][] = $articleString;
        }
    }

    return $article;
}

function parseWarodaiHeader($headerString){
    //Структура заголовка статьи
    $header = [
        'title'=>'',             //"чистый" заголовок - без локатора и корпуса
        'rawKana'=>[],           //неразобранный массив написаний каной
        'rawHyouki'=>[],         //неразобранный массив написаний хё:ки
        'rawKiriji'=>[],         //неразобранный массив написаний киридзи
        'corpus'=>[              //корпус (словарь 1970, новая лексика, географич. названия и т.п.)
            'code'=>'',          //код корпуса (напр., MEASURES_WEIGHT)
            'name'=>''           //имя корпуса (напр., мера веса)
        ]           ,
        'locator'=>'',           //локатор типа 〔2-406-1-06〕, он же - идентификатор
        'words'=>[]              //в случае гнездования, здесь будет несколько слов
                                 //(см. структуру слова в функции collateWords $word)
    ];

    //Разбираем заголовок с помощью вот такого регулярного выражения
    $headerReg = '/^ *(([\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31f0}-\x{31ff}…A-Z.,･！ ]+)(【([^】]+)】)? ?\(([а-яА-ЯЁёйў*,…:\[\] \x{0306}-]+)\)) *(\[([^]]+)\])?(〔([^〕]+)〕)?/u';

    //Заполняем структуру данных заголовка статьи
    if(preg_match($headerReg,$headerString,$match)){
        $header['rawKana'] = normalizeKana(explode(",", $match[2]));
        $header['rawHyouki'] = (empty($match[4])) ? [] : normalizeHyouki(explode(",", $match[4]));
        $header['rawKiriji'] = normalizeKiriji(explode(",",$match[5]));

        //"чистый" заголовок
        $header['title'] = $match[1];
        //корпус
        $header['corpus']['name'] = $match[7];
        $header['corpus']['code'] = getCorpusCode($match[7]);
        //локатор
        $header['locator'] = trim($match[9]);

        //Теперь разбор слов
        $header['words'] = collateWords($header['rawKana'],$header['rawHyouki'],$header['rawKiriji']);

    }
    else{
        //Заголовок не подошел под регулярное выражение - плохо.
        throw new Exception('Article has malformed header');
    }

    return $header;
}

function parseHeaderAddition($addition, $header){
    //Парсим комментарий по дополнительному написанию
    $headerAdditionReg = '/([\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31f0}-\x{31ff}…A-Z.,･！ ]+)?(【([^】]+)】)?/u';
    //В этот массив поступит результат разбора слов
    $words = [];
    if(preg_match($headerAdditionReg,$addition,$match)){
        if(count($match)>1){
            if(!empty(trim($match[1]))){
                //Если есть кана
                $header['rawKana'] = normalizeKana(explode(",",$match[1]));
                //Придется сделать киридзи пустым,
                //поскольку мы его не можем в этом случае взять из заголовка
                //Пустых элементов киридзи должно быть столько же, сколько каны
                $header['rawKiriji'] = array_fill(0,count($header['rawKana']),null);
            }
            if(!empty($match[3])){
                $header['rawHyouki'] = normalizeHyouki(explode(",",$match[3]));
            }
            $words = collateWords($header['rawKana'],$header['rawHyouki'],$header['rawKiriji']);
        }
    }
    return $words;
}

function collateWords($rawKana, $rawHyouki, $rawKiriji){
    $word = [                    //каждый элемент (слово) включает в себя следующие поля
        'kana'=> [],             //массив вариантов записи каной
        'hyouki'=> [],           //массив вариантов иероглифического написания слова (表記), пустой массив, если нет вариантов
        'kiriji'=>'',            //транскрипция по Поливанову
    ];

    $words = [];

    //Сначала разбираемся с каной и киридзи
    if(count($rawKana) != count($rawKiriji)){
        //Если число вхождений каны и киридзи не совпадает - выравниваем
        if(count($rawKana)==1 && count($rawKiriji)>0){
            //Одно вхождение каны, более одного вхождения киридзи - для каждой киридзи повторяем кану
            for($i = 1; $i < count($rawKiriji); $i++){
                $rawKana[] = $rawKana[0];
            }
        }
        elseif(count($rawKiriji)==1 && count($rawKana)>0){
            //Одно вхождение киридзи, более одного вхождения каны - для каждой каны повторяем киридзи
            for($i = 1; $i < count($rawKana); $i++){
                $rawKiriji[] = $rawKiriji[0];
            }
        }
        else{
            //Все к чертям расходится - точно что-то не так
            throw new Exception('The number of kana doesn\'t correspond to kiriji');
        }
    }

    //Теперь все раскладываем (кана у нас всегда равна киридзи здесь) и добавляем хё:ки если есть
    if(empty($rawHyouki)){
        //Хё:ки вообще нет
        for($i = 0; $i < count($rawKana); $i++){
            $wordEntry = $word;

            $wordEntry['kana'] = explode('･',$rawKana[$i]);
            $wordEntry['hyouki'] = [];
            $wordEntry['kiriji'] = $rawKiriji[$i];

            $words[] = $wordEntry;
        }
    }
    else{
        //Хё:ки есть
        if(count($rawKana) == count($rawHyouki)){
            //Число вхождений каны и хё:ки совпадает - просто раскладываем их в соответствующие слоты
            for($i = 0; $i < count($rawKana); $i++){
                $wordEntry = $word;

                $wordEntry['kana'] = explode('･',$rawKana[$i]);
                $wordEntry['hyouki'] = explode('･',$rawHyouki[$i]);
                $wordEntry['kiriji'] = $rawKiriji[$i];

                $words[] = $wordEntry;
            }
        }
        elseif((count($rawHyouki) > count($rawKana)) && count($rawKana) == 1){
            //Вариантов хё:ки больше каны, но кана при этом одна.
            $wordEntry = $word;

            $wordEntry['kana'] = explode('･',$rawKana[0]);
            //Все варианты хё:ки представляются как варианты разного написания одной каны
            //(запятая в этом случае как бы равна ･)
            $wordEntry['hyouki'] = explode('･',join('･',$rawHyouki));
            $wordEntry['kiriji'] = $rawKiriji[0];

            $words[] = $wordEntry;
        }
        elseif((count($rawHyouki) < count($rawKana)) && count($rawHyouki) == 1){
            //Вариантов хё:ки меньше каны, но хё:ки при этом одно
            for($i = 0; $i < count($rawKana); $i++){
                $wordEntry = $word;

                $wordEntry['kana'] = explode('･',$rawKana[$i]);
                //На все варианты каны дается один и тот же единственный хё:ки
                $wordEntry['hyouki'] = (empty($rawHyouki[0])) ? [] : explode('･',$rawHyouki[0]);
                $wordEntry['kiriji'] = $rawKiriji[$i];

                $words[] = $wordEntry;
            }
        }
        else{
            //Все к чертям расходится - точно что-то не так
            throw new Exception('The number of kana doesn\'t correspond to hyouki');
        }
    }

    return $words;
}

function normalizeKana($kana){
    if(is_string($kana)){
        $kana = [$kana];
    }
    for($i=0;$i<count($kana);$i++){
        $kana[$i] = trim($kana[$i]);
        $kana[$i] = preg_replace('/([^A-Za-z])[IV]+$/','$1',$kana[$i]);
        $kana[$i] = str_replace(['…','!','.'],'',$kana[$i]);
    }
    return $kana;
}

function normalizeHyouki($hyouki){
    return normalizeKana($hyouki);
}

function normalizeKiriji($kiriji){
    if(is_string($kiriji)){
        $kiriji = [$kiriji];
    }
    for($i=0;$i<count($kiriji);$i++){
        $kiriji[$i] = trim($kiriji[$i]);
        $kiriji[$i] = str_replace(['-','…'], '',$kiriji[$i]);
        $kiriji[$i] = str_replace('ў','у',$kiriji[$i]);
        $kiriji[$i] = str_replace('й','и',$kiriji[$i]);
    }
    return $kiriji;
}

function articleToHTML($article,$edition){
    //Готовый HTML
    $corpusHTML = '';

    //Формируем метку корпуса
    if(!empty($article['header']['corpus']['name'])){
        $corpusHTML = "<sup>〔{$article['header']['corpus']['name']}〕</sup>";
    }

    //Массив всех ключ. слов каны
    $kanaKeywords = [];
    //Массив всех ключ. слов хё:ки
    $hyoukiKeywords = [];

    //Формируем массивы - кажды вариант каны и хё:ки встречается в них только один раз
    //Сначала по заголовочным
    foreach($article['header']['words'] as $word){
        foreach($word['kana'] as $wordKana){
            $kanaKeywords[$wordKana] = $wordKana;
        }
        foreach($word['hyouki'] as $wordHyouki){
            $hyoukiKeywords[$wordHyouki] = $wordHyouki;
        }
    }
    //Теперь из комментариев
    foreach($article['headerAddition'] as $addition){
        foreach($addition['words'] as $word){
            foreach($word['kana'] as $wordKana){
                $kanaKeywords[$wordKana] = $wordKana;
            }
            foreach($word['hyouki'] as $wordHyouki){
                $hyoukiKeywords[$wordHyouki] = $wordHyouki;
            }
        }
    }

    //Формируем HTML　с ключ. словами
    $keywordsHTML = implode("\n",array_merge(
        array_map(function($a){return "<key type=\"かな\">$a</key>";},$kanaKeywords),
        array_map(function($a){return "<key type=\"表記\">$a</key>";},$hyoukiKeywords)
    ));

    //Формирует HTML с телом статьи
    $indentFlag = false;
    foreach($article['body'] as &$articleString){
        if(preg_match('/^ *\d+\)/',$articleString)){
            $indentFlag = true;
        }
        else{
            $articleString = ($indentFlag) ? "&nbsp;&nbsp;".$articleString : $articleString;
        }
    }
    $bodyHTML = implode("<br>\n",$article['body']);

    //Формируем HTML с комментариями
    $commentsHTML = "";
    if(sizeof($article['comments']) > 0) {
        $commentsHTML = "<br>\n".implode("<br>\n",array_map(function($a){return '<sub>'.$a.'</sub>';}, $article['comments']));
    }

    //Формируем HTML c x-комментариями
    $xcommentsHTML = "";
    if(sizeof($article['xcomments']) > 0){
        $xcommentsHTML = implode("<br>\n",$article['xcomments']);

        if(!empty($xcommentsHTML)){
            $xcommentsHTML = "<br>\n※<br>\n".$xcommentsHTML;
        }
    }

    //Возвращаем все в собранном виде
    return <<<EOD
<dt id="{$article['header']['locator']}">{$article['header']['title']}{$corpusHTML}</dt>
<sup>〔{$article['header']['locator']}〕</sup>
{$keywordsHTML}
<dd>{$bodyHTML}{$commentsHTML}{$xcommentsHTML}</dd>
EOD;

}

function nextEbcode($charWidth){
    //хранит последний использованный код ebcode
    if(!isset($ebcodeIncrementor)){
        static $ebcodeIncrementor = [
            'hankaku'=>0xA121,          //для полуширинных символов (半角)
            'zenkaku'=>0xB021           //для полношириринных символов (全角)
        ];
    }

    //Коды ebcode можно назначать только массивами по 94 кода из каждого блока по 256 кодов.
    //Вот что по этому поводу сказано в документации EbStudio:
    //外字の範囲はA121～FE7F。※94点を1区として割り当てる(A17Eの次はA221となる)
    //Таким образом, нам приходится следить, что код
    //сначала укладывается в диапазон (дзэнкаку) 0xB021 - 0xB07E (0xB07E = (0xB021 + 94)-1),
    //затем в диапазон 0xB121 - 0xB17E (0xB121 = 0xB021 + 256) и т.д.
    //Эта переменная инкрементирует только начальные коды очередного блока
    if(!isset($ebcodeBlockIncrementor)){
        static $ebcodeBlockIncrementor = [
            'hankaku'=>0xA121,
            'zenkaku'=>0xB021
        ];
    }

    $nextCode = $ebcodeIncrementor[$charWidth];

    //Тут мы делаем "перепрыгивание" между массивами по 94 символов
    if($ebcodeIncrementor[$charWidth] + 1 == $ebcodeBlockIncrementor[$charWidth]+94){
        //Следующий код выходит за пределы массива в 94 кода, прыгаем в следующий блок 256.
        $ebcodeBlockIncrementor[$charWidth] += 256;
        $ebcodeIncrementor[$charWidth] =  $ebcodeBlockIncrementor[$charWidth];
    }
    else{
        //Код попадает в массив 94 кода
        $ebcodeIncrementor[$charWidth]++;
    }
    return $nextCode;
}

function encodeGaiji($inputs, $gaijiIdx){

    //Символы, которые нужно насильно считать гайдзи
    //несмотря на то, что они успешно конвертируются в Shift_JS
    //Сюда относятся, например, все HALFWIDTH KATAKANA
    //Ее нет в JIS X 0208 (1990), но есть в Shift_JS
    //Если ее не сделать гайдзи, то в EbWin она будет FULL WIDTH
    $forceGaijiChars = 'ｰｦｧｨｩｪｫｬｭｮｯｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉﾊﾋﾌﾍﾎﾏﾐﾑﾒﾓﾔﾕﾖﾗﾘﾙﾚﾛﾜﾝ';
    $forceGaijiChars = get_chr_array($forceGaijiChars);

    //Массив-кэш соответствия юникод-символов символам Shift-JS
    //Ускоряет конвертацию.
    $JISx208Symbols = [];

    //Выходные файлы с уже перекодированными гайдзи
    //В них будут те же файлы, которые были входными
    $outputs = array_fill_keys(array_keys($inputs),'');

    //индекс Unicode->Ebcode: соответствие юникод-кода EB-коду (для Ext(BCD) - здесь будет код из Unicode PUA).
    //(ведется раздельно для полу- и полноширинных символов)
    //Сначала заполняется шестнадцатиричными кодами юникода,
    //Затем сортируется и заполняется уже eb-code. Это сделано для того
    //чтобы порядок обоих списков кодов возрастал одинково - так удобнее.
    $unicodeToEbcodeIdx = [
        'hankaku'=>[],
        'zenkaku'=>[]
    ];

    //Зона субприватных символов, для которых нет символа Unicode
    //и которые нам приходится "рисовать" в виде битмапов руками
    //им назначается коды из этой зоны
    $subPrvZone = [
        'start'=>0xE000,
        'end'  =>0xE020
    ];

    //хранит последний использованный код из приватной зоны Unicode U+E000-U+E0020
    //с учетом субприватной зоны
    $prvUCodeIncrementor = $subPrvZone['end']+1;

    //Перечень кодов, для которых битмапы были сгенерированы
    $generatedGaijiIdx = [];

    foreach($inputs as $fileName => $string){
        //Идем по каждому байту
        logMsg("Processing characters in $fileName");
        $stringLen = strlen($string);
        //Очередной рубеж в процентах пройденных байт, при котором следует отбразить прогресс
        $percentMilestone = 0;
        //Шаг показа процентов (между этими шагами проценты не показываются)
        $percentStep = .1;

        for($i=0; $i<$stringLen; $i++){
            $percentDone = round(($i*100)/$stringLen,2);
            if($percentDone > $percentMilestone){
                print("\r\t".$percentDone.'%');
                $percentMilestone += $percentStep;
            }

            //Код символа
            $currentCharOrd = null;
            //-----Вычисляем код символа-----
            //при вычислении мы можем сдвинуть счетчик байтов вперед на 2 или 3 позиции
            $h = ord($string{$i});
            if ($h <= 0x7F) {
                $currentCharOrd = $h;
            } else if ($h < 0xC2) {
                //такого быть не должно.
            } else if ($h <= 0xDF) {
                $currentCharOrd = ($h & 0x1F) << 6 | (ord($string{$i+1}) & 0x3F);
                $i=$i+1;
            } else if ($h <= 0xEF) {
                $currentCharOrd = ($h & 0x0F) << 12 | (ord($string{$i+1}) & 0x3F) << 6
                    | (ord($string{$i+2}) & 0x3F);
                $i=$i+2;
            } else if ($h <= 0xF4) {
                $currentCharOrd = ($h & 0x0F) << 18 | (ord($string{$i+1}) & 0x3F) << 12
                    | (ord($string{$i+2}) & 0x3F) << 6
                    | (ord($string{$i+3}) & 0x3F);
                $i=$i+3;
            } else {
                //такого быть не должно.
            }

            if(empty($currentCharOrd)){
                //какой-то левый байт
                //пропускаем его
                logMsg('Unknown byte in string: '.sprintf('%X',$h));
                continue;
            }

            //Сам символ
            $currentChar = unichr($currentCharOrd);

            //Проверяем, не начало ли это entity (напр., &#xE000;)
            //В исходники помимо субприватных кодов допустимы и обычные Юникодные коды
            //они здесь корректно обработаются
            if($currentChar=='&'){
                //Первый символ - ампресанд, значит, возможно,
                //это entity
                $match = [];
                if(preg_match('/^&#x([a-f0-9]{1,5});/i',substr($string,$i,9),$match)){
                    //Совершенно точно - entity.
                    $entityHex = $match[1];

                    //Перемещаем курсор прохода по строке на число
                    //символов, которое занимает entity
                    $i = $i+strlen("#x$entityHex;");

                    //Делаем так, что entity превращается в обычный
                    //символ с нормальным кодом и дальше обрабатывается как все остальные символы.
                    //субприватные коды тут не исключение, их особенность только в том,
                    //что на них ОБЯЗАТЕЛЬНО должен быть уже готовый битмап - это проверяется ниже.
                    $currentCharOrd = hexdec($entityHex);
                    $currentChar = unichr($currentCharOrd);
                }
            }

            //Шестнадцатиричный код символа (строковое представление с добивкой нулей до минимум 4 цифр)
            $currentCharHex = sprintf('%04X',$currentCharOrd);

            //Шестнадцатиричный код символа, который будет вставлен в выходной строке.
            //По умолчанию равен просто коду символа
            $outputCharHex = $currentCharHex;

            //Пробуем конвертировать символ в кодировку Shift-JS
            $currentShiftJSChar = FALSE;

            if($currentCharOrd >= $subPrvZone['start'] && $currentCharOrd <= $subPrvZone['end']){
                //Если это субприватный код, нельзя конвертировать
                //iconv под Windows почему-то успешно его конвертирует
                $currentShiftJSChar = FALSE;
            }
            elseif(isset($JISx208Symbols[$currentChar])){
                $currentShiftJSChar = $JISx208Symbols[$currentChar];
            }
            else{
                if(isset($forceGaijiChars[$currentChar])){
                    //Этот символ - форсированный гайдзи
                    $currentShiftJSChar = FALSE;
                }
                else{
                    $currentShiftJSChar = @iconv('UTF-8', 'SHIFT-JIS', $currentChar);
                    $JISx208Symbols[$currentChar] = $currentShiftJSChar;
                }
            }

            if($currentShiftJSChar === FALSE){
                //Символ не входит в набор JISx208 - он гайдзи (外字)

                //Нужно добавить код в индекс Unicode->Ebcode, если его там нет
                if(!isset($unicodeToEbcodeIdx['hankaku'][$outputCharHex]) &&
                    !isset($unicodeToEbcodeIdx['zenkaku'][$outputCharHex])){
                    //Символа нет в индексе - добавляем

                    //Пытаемся найти символ среди предгенерированных битмапов
                    if (isset($gaijiIdx['hankaku'][$currentCharHex])) {
                        //нашли среди полуширинных
                        //значит добавлять нужно к полуширинным
                        //Здесь и ниже мы столбим место для символа в индексе - записывая в него реальный юникод-код.
                        //Именно по этим реальным юникод-кодам ниже производится сортировка фун-ей asort.
                        //Назначение ebcode　происходит ниже, после сортировки
                        $unicodeToEbcodeIdx['hankaku'][$outputCharHex] = $currentCharHex;
                    }
                    elseif (isset($gaijiIdx['zenkaku'][$currentCharHex])) {
                        //нашли среди полноширинных
                        //значит добавлять нужно к полноширинным
                        $unicodeToEbcodeIdx['zenkaku'][$outputCharHex] = $currentCharHex;
                    }
                    elseif ($currentCharOrd >= $subPrvZone['start'] && $currentCharOrd <= $subPrvZone['end']){
                        //Это символ из нашей внутренней субприватной зоны
                        //Если его нет среди предгенерированных битмапов,
                        //это плохо - его изображение неоткуда взять, поэтому сообщаем об этом
                        logMsg("Subprivate character ".$currentCharHex." was not found in bitmap index.");
                    }
                    else {
                        //такого символа нет среди битмапов
                        //генерируем
                        $charBitmap = getCharBitmap($currentCharHex);
                        $charBitmapWidth = detectCharBitmapWidth($charBitmap);
                        $gaijiIdx[$charBitmapWidth][$currentCharHex] = $charBitmap;
                        $generatedGaijiIdx[$currentCharHex] = $currentCharHex;

                        $unicodeToEbcodeIdx[$charBitmapWidth][$outputCharHex] = $currentCharHex;
                    }
                }

                //Добавляем код к выходной строке
                //Внимание: в выходном файле словаря гайдзи должны
                //кодироваться юникодными кодами (включая PUA), а не eb-кодами
                //eb-коды нужны только для связывания Gaiji.xml, GaijiMap.xml и WARODAI.map
                $outputs[$fileName] .= '&#x'.$outputCharHex.';';
            }
            else{
                //Символ входит в набор JISx208, просто добавляем его к выходной строке.
                $outputs[$fileName] .= $currentShiftJSChar;
            }
        }
    }

    //Теперь генерируем
    $gaijiMap = "<?xml version=\"1.0\" encoding=\"Shift_JIS\"?>\n<gaijiSet>\n";
    $gaiji = "<?xml version=\"1.0\" encoding=\"Shift_JIS\"?>\n<gaijiData xml:space=\"preserve\">";
    $mapFile = '';

    foreach(['hankaku','zenkaku'] as $kaku){
        $gaiji .= "<fontSet size=\"".(($kaku{0} == 'h') ? '8X16' : '16X16')."\" start=\"".(($kaku{0} == 'h') ? 'A121' : 'B021')."\">\n";

        //Сортируем индекс Unicode->EbCode
        uasort(
            $unicodeToEbcodeIdx[$kaku],
            function ($a, $b) {
                $a = hexdec($a); $b = hexdec($b);
                if ($a == $b) {
                    return 0;
                }
                return ($a < $b) ? -1 : 1;
            }
        );

        foreach($unicodeToEbcodeIdx[$kaku] as $unicodeCode=>$devnull){
            //Назначаем EbCode
            $ebCode = sprintf('%04X',nextEbcode($kaku));
            $unicodeToEbcodeIdx[$kaku][$unicodeCode] = $ebCode;

            $gaijiMap .= "\t<gaijiMap unicode=\"#x$unicodeCode\" ebcode=\"$ebCode\"/>\n";

            $gaiji .= "<fontData ebcode=\"$ebCode\" unicode=\"$unicodeCode\">".$gaijiIdx[$kaku][$unicodeCode]."</fontData>\n";

            $mapFile .= $kaku{0}.$ebCode."\tu$unicodeCode\t\t#	".unichr(hexdec($unicodeCode))."\n";
        }
        $gaiji .= "</fontSet>\n";
    }

    $gaijiMap .= "</gaijiSet>";
    $gaiji .= "</gaijiData>";

    return [
        'outputs'=>$outputs,
        'gaijiMap'=>$gaijiMap,
        'gaiji'=>$gaiji,
        'mapFile'=>$mapFile,
        'unicodeToEbcodeIdx' => $unicodeToEbcodeIdx,
        'gaijiIdx' => $gaijiIdx,
        'generatedGaijiIdx' => $generatedGaijiIdx
    ];
}

function getCharBitmap($charHex){
$fontDumpPath = 'fontdump\FontDumpW.exe';
//Этот битмап мы будем возвращать, если FontDump не сработал
$missingBitmap = <<< EOD

 ###############
 #             #
 # #         # #
 #  #       #  #
 #   #     #   #
 #    #   #    #
 #     # #     #
 #      #      #
 #     # #     #
 #    #   #    #
 #   #     #   #
 #  #       #  #
 # #         # #
 ##           ##
 #             #
 ###############

EOD;

    //Настройка шрифтов
    //Разные диапазоны символов могут быть не реализованы в определенных шрифтах
    //или реализованы плохо, поэтому здесь можно задать определенные шрифты
    //для генерации определенных диапазонов.
    //Нужно сказать, что FontDump воспринимает указание шрифта ка РЕКОМЕНДАЦИЮ
    //Если такого шрифта нет в системе, FontDump　об этом не сообщаяет, а просто генерирует
    //битмап из какого-то шрифта, который он посчитал подходящим (скорее всего из MS Mincho).
    //Рекомендации по выбору шрифтов Исиды:
    //・TTFフォントからラスタライズする場合、明朝体を字母に選ぶとドット数が小さい場合に
    //  かなり汚くなります。ゴシック体が選択可能なら、ゴシックをご使用下さい。また
    //  プロポーショナルフォントはうまくフォントの外形のなかに収まらないことがあるので、
    //  固定幅フォントをお勧めします。
    //・製品のフォントから抽出したフォントを配布することは知的所有権の侵害になります。
    //  Windowsのフォントから抽出したフォントの利用はあくまでも私的利用の範囲内で行うよ
    //  うお願いします。

    //Этот шрифт будет использоваться по умолчанию
    $defaultFont = [
        'Font'=>'Tahoma',
        'b'=>2
    ];
    //А эти шрифты будут использоваться для определенных диапазонов
    $fonts = [
        [
            'Font'=>'Sun-ExtA',  //Название шрифта (ком. Исиды: Windowsにインストールされたフォント名称。※TTFファイル名ではないので注意。)
            'Start'=>0x3400,     //Начальный код диапазона символов
            'End'=>0x4DBF,       //Последний код диапазона символов
            'b'=>2               //Насколько строк поднять базовую линию символа (для некоторых шрифтов,
                                 //если не сделать это поднятия, глиф будет обрезан!)
                                 //комм. Исиды: 指定したドット数だけ文字を上げる(ベースラインを下げる)(省略時：0)
        ],
        [
            'Font'=>'Sun-ExtA',
            'Start'=>0x4E00,
            'End'=>0x9FFF,
            'b'=>2
        ],
        [
            'Font'=>'Sun-ExtA',
            'Start'=>0xF900,
            'End'=>0xFAFF,
            'b'=>2
        ],
        [
            'Font'=>'MingLiu-ExtB',
            'Start'=>0x20000,
            'End'=>0x2A6DF,
            'b'=>0
        ],
    ];

    //Определяем, какой шрифт для этого символа подойдет
    $useFont = $defaultFont;
    foreach($fonts as $font){
        if(hexdec($charHex)>=$font['Start'] && hexdec($charHex)<=$font['End']){
            $useFont = $font;
            break;
        }
    }

    //Осуществляем вызов утилиты дампа битмапов
    //Выходные данные - массив строк
    $output = [];
    $command = "{$fontDumpPath} \"{$useFont['Font']}\" $charHex -b={$useFont['b']}";
    exec($command,$output);
    $output = implode("\n",$output);

    //Находим битмап в дампе (первый и последний перенос строк нам пока не нужен - он будет только мешать)
    if(!preg_match('/<fontData[^>]+>\n([ #\n]+)\n<\/fontData>/m',$output,$match)){
        logMsg("FontDump didn't generate the bitmap for char=0x$charHex (".unichr(hexdec($charHex)).")");
        return $missingBitmap;
    }

    //Определяем ширину символа
    //У утилиты странная особенность - она не выдает пробелы до полных 8 или 16 строк
    //после последнего знака #. Приходится восстанавливать общую ширину, вычисляя
    //максимальное число символов в строке
    //Вот так это может выглядеть в дампе, например для первых 3 cтрок полноширинного символа А
    //.....#[а тут ничего нет!]
    //....#
    //..##
    $output = explode("\n",$match[1]);
    $maxBitmapWidth = 0;
    foreach($output as $string){
        if(strlen($string) > $maxBitmapWidth){
            $maxBitmapWidth = strlen($string);
        }
    }

    //Если в битмапе максимально в строке более 8 символов, то это полноширинный глиф, иначе - полуширинный
    $bitmapWidth = ($maxBitmapWidth > 8) ? 16 : 8;

    //А теперь добиваем строки недостоющими пробелами
    for ($i=0; $i < count($output);$i++){
        $output[$i] .= str_repeat(' ',$bitmapWidth - strlen($output[$i]));
    }

    //Склеиваем и добавляем обратно отрезанные в самом начале переводы строк (у нас все битмапы с ними -
    //чтобы удобнее вставлять в <fontData></fontData>)
    $output = "\n".implode("\n",$output)."\n";

    return $output;
}

function detectCharBitmapWidth($charBitmap){
    //Определяем полу-/полноширинность по числу символов во второй строке битмапа
    //Вторая строка, поскольку нам могут передать с ведущим переносом строки
    //- то есть тем, который остался, например, от <fontData>\n
    return (strlen(explode("\n",$charBitmap)[1]) > 8 ? 'zenkaku' : 'hankaku');
}

function getCorpusCode($corpusName){
    $corpusCodesIdx = [
        'мера длины'=>'MEASURES_LENGTH',
        'мера поверхности'=>'MEASURES_SURFACE',
        'мера ёмкости'=>'MEASURES_VOLUME',
        'мера веса'=>'MEASURES_WEIGHT',
        'геогр.'=>'GEO_NAMES',
        'г. Токио'=>'GEO_TOKYO',
        'г. Осака'=>'GEO_OSAKA',
        'нов.'=>'GLOSSARY_NEW',
        ''=>'GLOSSARY_ORIG',
        'название годов правления'=>'ERA_NAMES',
        'название годов правления; Северная династия'=>'ERA_NAMES',
        'название годов правления; посмертное имя'=>'ERA_NAMES'
    ];

    return (isset($corpusCodesIdx[trim($corpusName)]) ? $corpusCodesIdx[trim($corpusName)] : null);
}

function generateEncodingReport($unicodeToEbcodeIdx, $gaijiIdx, $generatedGaijiIdx, $outputFile="encoding_report.html"){
    $pageHTML = <<<EOD
<!DOCTYPE html>
    <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <title>WARODAI->EPWING GAIJI ENCODING REPORT</title>
            <style>
                body{
                    background-color: #a5a5a5;
                }
                table.chars{
                    border-collapse:collapse;
                }
                table.chars td,  table.chars th{
                    padding: 5px;
                    border: 1px solid #cccccc;
                }
                table.big-bitmap{
                    border-collapse:collapse;
                }
                table.big-bitmap td{
                    padding: 0px;
                    width: 4px;
                    height: 4px;
                    background-color:#ffffff;
                    border: 1px solid #cccccc;
                }
                tr.g table.big-bitmap td{
                    background-color:#dea2ca;
                }
                table.big-bitmap td.h, tr.g table.big-bitmap td.h{
                    background-color:#000000;
                }
                table.bitmap{
                    border-collapse:collapse;
                    border: none;
                }
                table.bitmap td{
                    padding: 0px;
                    width: 1px;
                    height: 1px;
                    background-color:#ffffff;
                    border: none;
                }
                table.bitmap td.h{
                    background-color:#000000;
                }
            </style>
        </head>
        <body>
            <table class="chars">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Ebcode</th>
                        <th>Unicode</th>
                        <th>Big bitmap</th>
                        <th>16x16 Bitmap</th>
                        <th>Char</th>
                    </tr>
                </thead>
                <tbody>
                    {{charTable}}
                </tbody>
            </table>
        </body>
    </html>
EOD;

   $charTable = "";
   $charCounter = 1;
   foreach(['hankaku','zenkaku'] as $kaku){
        foreach($unicodeToEbcodeIdx[$kaku] as $unicodeCode=>$ebCode){
            $bitmapTable = gaijiBitmapToTable($gaijiIdx[$kaku][$unicodeCode],"bitmap");
            $bigBitmapTable = gaijiBitmapToTable($gaijiIdx[$kaku][$unicodeCode],"big-bitmap");
            $char = unichr(hexdec($unicodeCode));
            $charCounterF = sprintf('%04u',$charCounter);

            $trClass = (isset($generatedGaijiIdx[$unicodeCode])) ? 'g' : '';

            $charTR = <<<EOD
                    <tr class="{$trClass}">
                        <td>{$charCounterF}</td>
                        <td>{$kaku{0}}{$ebCode}</td>
                        <td>&amp;#x{$unicodeCode}</td>
                        <td>{$bigBitmapTable}</td>
                        <td>{$bitmapTable}</td>
                        <td>{$char}</td>
                    </tr>
EOD;
            $charTable .= $charTR;
            $charCounter++;
        }
    }

    $pageHTML = str_replace('{{charTable}}',$charTable,$pageHTML);

    file_put_contents($outputFile,$pageHTML);
}

//---------------Утилиты------------//

function gaijiBitmapToTable($gaijiBitmap,$tableClass=""){
    $table = "<table class=\"$tableClass\"><tr>";
    for($i=1; $i<strlen($gaijiBitmap)-1; $i++){
        $c = $gaijiBitmap{$i};
        switch ($c) {
            case " ":
                $table .="<td></td>";
                break;
            case "#":
                $table .="<td class=\"h\"></td>";
                break;
            case "\n":
                $table .= "</tr><tr>";
        }
    }
    $table .= "</tr></table>";
    return $table;
}

function unichr($c) {
    if ($c <= 0x7F) {
        return chr($c);
    } else if ($c <= 0x7FF) {
        return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
    } else if ($c <= 0xFFFF) {
        return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
        . chr(0x80 | $c & 0x3F);
    } else if ($c <= 0x10FFFF) {
        return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
        . chr(0x80 | $c >> 6 & 0x3F)
        . chr(0x80 | $c & 0x3F);
    } else {
        return false;
    }
}

function uniord($c) {
    $h = ord($c{0});
    if ($h <= 0x7F) {
        return $h;
    } else if ($h < 0xC2) {
        return false;
    } else if ($h <= 0xDF) {
        return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
    } else if ($h <= 0xEF) {
        return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6
        | (ord($c{2}) & 0x3F);
    } else if ($h <= 0xF4) {
        return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12
        | (ord($c{2}) & 0x3F) << 6
        | (ord($c{3}) & 0x3F);
    } else {
        return false;
    }
}

function get_chr_array($c){
    $r = strlen($c);
    for($i=0;$i<$r;$i++){
        $h = ord($c{$i});
        if ($h <= 0x7F) {
            $f = $h;
        } else if ($h < 0xC2) {

        } else if ($h <= 0xDF) {
            $f = ($h & 0x1F) << 6 | (ord($c{$i+1}) & 0x3F);
            $i=$i+1;
        } else if ($h <= 0xEF) {
            $f = ($h & 0x0F) << 12 | (ord($c{$i+1}) & 0x3F) << 6
                | (ord($c{$i+2}) & 0x3F);
            $i=$i+2;
        } else if ($h <= 0xF4) {
            $f = ($h & 0x0F) << 18 | (ord($c{$i+1}) & 0x3F) << 12
                | (ord($c{$i+2}) & 0x3F) << 6
                | (ord($c{$i+3}) & 0x3F);
            $i=$i+3;
        } else {

        }
        $chars[unichr($f)]=unichr($f);
    }
    return $chars;
}

function file_get_contents_utf($filename){
    $buf = file_get_contents($filename);

    if      (substr($buf, 0, 3) == "\xEF\xBB\xBF")          return substr($buf,3);
    else if (substr($buf, 0, 4) == "\xFF\xFE\x00\x00")      return iconv('UTF-32LE', 'UTF-8',substr($buf, 4));
    else if (substr($buf, 0, 4) == "\x00\x00\xFE\xFF")      return iconv('UTF-32BE', 'UTF-8',substr($buf, 4));
    else if (substr($buf, 0, 2) == "\xFE\xFF")              return iconv('UTF-16BE', 'UTF-8',substr($buf, 2));
    else if (substr($buf, 0, 2) == "\xFF\xFE")              return iconv('UTF-16LE', 'UTF-8',substr($buf, 2));
    else                                                    return $buf;
}

function logMsg($msg){
    print("\r".$msg."\n");
}
