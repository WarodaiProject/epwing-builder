Пакет утилит для генерации словаря WARODAI в формат EPWING (JIS-X4081)
======================================================================
**Внимание!** Этот репозиторий предназначен только для хранения инструментов. В нем недолжны храниться исходники и результаты генерации EPWING-версия (файл .gitignore настроен так, чтобы исключать эти данные из репозитория). Для хранения исходников и результатов генерации предназначен отдельный репозиторий https://github.com/warodai/epwing-version.

## 1. Порядок действий при генерации
1. Убедитесь, что в директории RAW_SOURCE находятся текстовые файлы словаря и другие исходные файлы:
    * **ewarodai.txt** - текстовый файл словаря публичной версии (без ※-комментариев), кодировка UTF-16LE. Его можно скачать с официального сайта проекта (https://warodai.ru/download/warodai_txt.zip).
    * **xwarodai.txt** - текстовый файл словаря редакторской версии (с ※-комментариями), кодировка UTF-16LE; не является обязательным и должен быть только в том случае, если вы собираетесь генерировать редакторскую версию. Исходный файл для этой версии доступен только для старших редакторов словаря.
    * **DAI\_dict\_u.tmpl.html** - шаблонный файл HTML словаря для EBStudio. Содержит весь текст словаря, кроме самих корпусов (словников). В тех местах, куда должны будут быть вставлены корпуса, в файле стоят инструкции вида {{CORP.КОД_КОРПУСА}}. Кодировка файл - UTF-16LE.
    * **DAI\_copyright.html** - файл с информацией о защите авторских прав, кодировка UTF-16LE.
    * **Gaiji\_pregen.xml** - файл с заранее сгенерированным битмапами гайдзи.

2. Убедитесь, что все внешние инструменты, используемые пакетом, на месте:
    * В папке *AppPatch* находится приложение Microsoft AppLoc (AppLoc.exe) и все его файлы. Оно необходимо для запуска EBStudio, которое не является юникодным приложением и требует старой японской локали для корректной работы. Для корректной работы AppLoc.exe необходимо его установить. Для этого скопируйте файл *apploc.msi* из папки *_install* в корень диска C. Нажмите кнопку "Пуск" Windows и наберите в поисковой строке *cmd*. Появятся результаты поиска, в которой будет утилита cmd. Щелкните на ней ПРАВОЙ кнопкой мыши и выберите пункт "Запуск от имени Админитратора". В появившейся консоли наберите команду C:\apploc.msi. Запустится установщик AppLoc. Пройдите установку до конца.
    * В папке *EBStudio* находятся файлы EBStudio.exe и FontDumpW.exe.
    * В папке *php* находится скомпилированная верси PHP5 для Windows (версия PHP >5.2, <7). В настроечном файле php.ini опция memory\_limit должна быть выставлена в 1G (memory\_limit = 1G). В некоторых случаях php не хватает библиотек. Для устранения этой проблемы установите приложение vcredist_x86.exe из папки _install.

3. Для генерации публичной версии WARODAI запустите скрипт **warodai\_ebconv.bat**. После окончания его работы в папке WARODAI_SOURCE окажутся все файлы, необходимые для генерации словаря с помощью EBStudio. Файл WARODAI.map окажется в папке WARODAI. В файле WARODAI_encoding_report.html будет записан отчет о конвертации гайдзи.

4. Запустите скрипт **EBStudio.bat**. Этот скрипт производит корректный запуск EBStudio\EBStudio.exe через AppLoc.exe.

5. В открывшемся окне EBStudio выберите пункт меню ファイル（F） -> 開く(O). Выберите файл warodai.ebs. Все поля EBStudio автоматически заполнятся нужными значениями.

6. В меню EBStudio выберите пункт ファイル（F） -> 実行(G) или нажмите кнопку с красным восклицательным знаком. После окончания работы процедуры конвертации в папке WARODAI окажется полностью скомпилированный словарь WARODAI и файл WARODAI.map.

> Для редакторской версии словаря процедура генерации аналогична. На шаге **3** следует запускать **xwarodai\_ebconv.bat**, файлы для генерации с помощью EBStudio поступят в папку XWARODAI_SOURCE. На шаге **5** следует выбирать файл **xwarodai.ebs**, результат поступит в папку XWARODAI.

## 2. Корпуса словаря WARODAI
В состав словаря WARODAI входит несколько словарных корпусов (словников). В словарной статье корпус указан в заголовке статьи в квадратных скобках []. Процедура конвертации распознает эти указания и разбивает весь словарь на корпуса. Каждый корпус вставляется в HTML-файл для EBStudio на место инструкции вида {{CORP.КОД_КОРПУСА}}. Ниже приведен список всех корпусов с соответствующими метками в исходном файле словаря и обозначением инструкции для вставки:

| Метка                                         |Инструкция вставки        |
|-----------------------------------------------|--------------------------|
|[мера длины]                                   |{{CORP.MEASURES_LENGTH}}  |
|[мера поверхности]                             |{{CORP.MEASURES_SURFACE}} |
|[мера ёмкости]                                 |{{CORP.MEASURES_VOLUME}}  |
|[мера веса]                                    |{{CORP.MEASURES_WEIGHT}}  |
|[геогр.]                                       |{{CORP.GEO_NAMES}}        |
|[г. Токио]                                     |{{CORP.GEO_TOKYO}}        |
|[г. Осака]                                     |{{CORP.GEO_OSAKA}}        |
|[нов.]                                         |{{CORP.GLOSSARY_NEW}}     |
|[]                                             |{{CORP.GLOSSARY_ORIG}}    |
|[название годов правления]                     |{{CORP.ERA_NAMES}}        |
|[название годов правления; Северная династия]  |{{CORP.ERA_NAMES}}        |
|[название годов правления; посмертное имя]     |{{CORP.ERA_NAMES}}        |

## 3. Гайдзи (外字)
Гайдзи - это символы, не входящие в кодовую таблицу JIS X 0208, использующуюся в стандарте JIS X 4081. При конвертации из исходных файлов в файлы HTML для EBStudio такие символы должны быть особо обработаны. Общий алгоритм этой обработки выглядит следующим образом:

1. Производится сборка всех файлов HTML для EBStudio (DAI_dict.html и DAI_copyright.html) в кодировке UTF-16LE.
2. Производится посимвольный проход по всем файлам. В том числе распознаются символы, которые в исходных файлах записаны в виде Unicode Character Entity, т.е., в форме **\&#xFFFF;**. Такие символы сразу переводятся в свою нормальную юникодную форму и далее обрабатываются так, как будто они были в исходном тексте обычным символом, а не Unicode Character Entity.
3. Для каждого символа производится попытка конвертировать его из UTF-16LE в Shift_JS с помощью библиотеки iconv. Shift_JS строго говоря не является кодировкой JIS X 0208 (1990), но EBStudio принимает на вход именно Shift_JS, производя конвертацию Shift_JS->JIS X 0208 в процессе генерации EPWING. ShiftJS объединяет в себе две кодировки: JIS X 0201 (1976) и JIS X 0208 (1990). Кодировка JIS X 0201 помимо символов ASCII содержит только символы катаканы и несколько знаков японской пунктуации. Исторически катакана из этой кодировки является полуширинной. В кодировке JIS X 0208 полуширинной катаканы просто нет, а в Shift_JS　есть и полуширинная кана, и полноширинная (ведь это объединение JIS X 0201 (1976) и JIS X 0208 (1990)). Видимо, поэтому EbStudio　производит молчаливую замену полуширнной каны на полноширинную. Для того, чтобы полуширинная катакана не потерялась, при конвертации UTF-16LE в Shift_JS мы принудительно (форсировано) считаем, что полуширинные символы катаканы - это гайдзи.
4. Если символ успешно конвертировался, он просто присоединяется к выходной строке.
5. Если символ не прошел успешную конвертацию, то он считается гайдзи.
6. Для очередного символа гайдзи осуществляется проверка числа шестнадцатеричных цифр, составляющих его Unicode-код. Если шестнадцатеричных цифр в коде символа больше 4 (например, U+29E8A), то его приходится заменить на псевдо-юникод символ из приватной зоны (Private Use Area, PUA) U+E021–U+F8FF. EbStudio, по признанию Исиды, не поддерживает пятизначные коды и поэтому их приходится менять на коды из PUA. 現在のEBStudioでは、EXt-BのUnicode表記に対応していません。0xE000～の外字領域を使うしかありません. Внутри приватной зоны PUA для целей WARODAI выделена отдельная подприватная зона U+E000-U+E020. Она используется для тех символов WARODAI, которых нет в Unicode. Такие символы не встречаются внутри корпусов словаря, но могут быть в сопровождающих текстах файлов DAI\_dict.html или DAI\_copyright.html. В тексте такие символы, по понятным причинам, могут встретить только в виде записи Unicode Character Entity (\&#xE001). Они превращаются в "символы" на шаге 2 и обрабатываются так, как будто это обычный символ Unicode. Именно наличие подприватной зоны определяет то, что коды для подмены пятизначных кодов выделяются начиная с U+E0021, а не с U+E000.
7. Гайдзи присоединяется к выходной строке в виде Unicode Character Entity (\&#xFFFF) - реального, если в нем не более 4 символов, и псевдо (\&#xE021 - \&#xF8FF),　если в нем более 5 цифр. Обращаем внимание, что в результате несуществующие символы Юникод попадают в выходную строку в неизменном виде Unicode Character Entity (&#xE000-&#xE020).
8. Для гайдзи производится поиск его битмапа (16 строк по 16 символов, в которых пробел обозначает белый пиксель, а знак # - черный). Битмап используется EBWin для вывода изображения гайдзи в тексте. Поиск битмапов осуществляется в файле **Gaiji_pregen.xml**. Для поиска используется всегда **реальный** (а не псевдо) код Unicode. Поэтому в файле Gaiji_pregen.xml в атрибуте unicode каждого битмапа должен стоять реальный код символа. Естественно, для символов, отсутствующих в Unicode, "реальным" кодом является код, выделенный из субприватной зоны WARODAI (U+E000-U+E020). Если в файле Gaiji_pregen.xml отсутствует искомый символ, для генерации битмапа вызывается утилита FontDumpW.exe. Если утилита не найдена или она вернула некорректный битмап, то символ получит битмап в виде перечеркнутого квадрата (☒).
9. По окончании посимвольной обработки все гайдзи разбиваются на 2 группы: полуширинные символы (ханкаку, 半角) и полноширинные символы (дзэнкаку, 全角). Разница между этими группами состоит в том, что изображение половинного символа в EBWin и других просмотрщиках EPWING занимает по ширине в два раза меньше места. EBStudio требует учитывать эти гайдзи раздельно.
10. Для каждого гайдзи производится назначение внутреннего кода EPWING. Для символов ханкаку назначение производится, начиная с кода **A121**, для символов дзэнкаку - с кода **B021**. Коды ebcode можно назначать только массивами по 94 кода из каждого блока по 256 кодов.　Вот что по этому поводу сказано в документации EbStudio:　外字の範囲はA121～FE7F。※94点を1区として割り当てる(A17Eの次はA221となる). Таким образом, например, при инкрементации ebcode-код дзэнкаку сначала укладывается в диапазон 0xB021 - 0xB07E (0xB07E = (0xB021 + 94)-1), затем в диапазон 0xB121 - 0xB17E (0xB121 = 0xB021 + 256) и т.д.
11. Производится генерация 3 файлов, описывающих гайдзи:
    * **GaijiMap.xml** - содержит соответствия юникод-кодов eb-кодам. В качестве юникод-кода здесь выступает ровно тот код, которым символ записан в HTML-файле, т.е. для пятизначных кодов здесь будет псевдо-код из зоны U+E021 - U+F8FF.
    * **Gaiji.xml** - содержит битмапы для всех гайдзи. Полноширинные и полуширинные символы идут раздельно. Для каждого битмапа указан его ebcode-код. Для справки также указаны юникод-код (как и в случае GaijiMap.xml, для пятизначных кодов здесь будет псевдо-код из зоны U+E021 - U+F8FF).
    * **WARODAI.map** - содержит соответствие ebcode-кодов *реальным* юникод-кодам. В ebcode-коде префиксом h/z указана ширина символа.
12. По окончании генерируется файл отчета обработки гайдзи **WARODAI_encoding_report.html**, который содержит перечень всех гайдзи в виде таблицы. В таблице указаны:
    * Порядковый номер гайдзи
    * Ebcode-код, присвоенный гайдзи, включая префикс h/z, обозначающий ширину
    * Реальный юникод-код символа (для символов из субприватной зоны WARODAI здесь будет указан код \&#xE000-\&#xE020)
    * Юникод-код символа так, как он встречается в файлах DAI_dict.html и DAI_copyright.html (то есть для пятизначных кодов здесь будет код из PUA U+E021–U+F8FF).
    * Побитовое изображение гайдзи. Те гайдзи, которые были сгенерированы утилитой FontDumpW.exe, а не взяты из Gaiji_pregen.xml, подкрашены в розоватый цвет.
    * Изображение битмапа 8x8 (ханкаку) или 16x16 (дзэнкаку) пикселей. Это то, как символ будет выглядеть в EBWin.
    * Сам символ. Для символов из субприватной зоны WARODAI (\&#xE000-\&#xE020) символ будет выведен в браузере. В некоторых браузерах такой несуществующий символ будет отображаться как квадратик, а в некоторых - как ромбик или стрелочках (это изображения этих символов из наиболее распространенных PUA).

## 4. Полезные ссылки

* [EBStudio](http://ebstudio.info/home/EBStudio.html)
* [Документация EBStudio](http://ebstudio.info/manual/EBStudio/)
* [Таблица соответствия символов JIS X 0208 (1990) -> Unicode ](http://www.unicode.org/Public/MAPPINGS/OBSOLETE/EASTASIA/JIS/JIS0208.TXT)
* [Таблица соответствия символов JIS X 0201 (1976) -> Unicode](http://www.unicode.org/Public/MAPPINGS/OBSOLETE/EASTASIA/JIS/JIS0201.TXT)
* [Unicode 9.0 Private Use Area, ch.23, §23.5](http://www.unicode.org/versions/Unicode9.0.0/ch23.pdf)
