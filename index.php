<?php

$input_file_path = __DIR__ . DIRECTORY_SEPARATOR . 'testPeriodIntake.csv'; // входной файл
$output_file_path = __DIR__ . DIRECTORY_SEPARATOR . 'Result.csv'; // выходной файл
$first_data_line = 2; // первая строка, с которой начинаются данные во входном файле
$indexes = ['groupId', 'periodBegin', 'periodEnd', 'sumIntake']; // заголовки в CSV-файле

/**
 * Построковый читатель файла в виде генератора
 * @param string $file_path - путь к файлу
 * @return string/null - строка данных, либо пустота
 *
 */
function fileReader($file_path) {
    if(!file_exists($file_path)) {
        return;
    }
    $fh = fopen($file_path, "r");

    while(!feof($fh)) {
        yield trim(fgets($fh));
    }

    fclose($fh);
}

/**
 * Объединение пересекающихся периодов
 * @param array $group_data - входной массив периодов
 * @return array $group_data_new - массив объединенных периодов
 *
 */
function mergePeriods(array $group_data)
{
    $group_data_new = [];

    if(empty($group_data) || !is_array($group_data)) {
        return $group_data_new;
    }
    $begin_periods = array_column($group_data, 'periodBegin');
    // т.к. у нас нет уверенности, что интервалы получены в хронологическом порядке,
    // то отсортируем их по дате начала периода
    array_multisort($group_data, $begin_periods);

    $prev_preiod = null;
    foreach($group_data as $key => $period) {
        if(is_null($prev_preiod)) {
            $prev_preiod = $period;
            continue;
        }
        if( // если начало очередного периода находится между началом и концом предыдущего периода, то периоды пересекаются
            strtotime($prev_preiod['periodBegin']) <= strtotime($period['periodBegin'])
            && strtotime($prev_preiod['periodEnd']) >= strtotime($period['periodBegin'])
        ) { // тогда объединим периоды
            $prev_preiod['periodEnd'] = $period['periodEnd'];
            $prev_preiod['sumIntake'] += $period['sumIntake'];
        } else {
            $group_data_new[] = $prev_preiod;
            $prev_preiod = $period;
        }
        if(count($group_data) == $key + 1) {
            $group_data_new[] = $prev_preiod;
        }
    }

    return $group_data_new;
}

/**
 * Поиск самого длительного периода в массиве периодов
 * @param array $group_data - входной массив периодов
 * @return array $longest_period - самый длинный период
 *
 */
function findLongestPeriod(array $group_data)
{
    $longest_period = [];

    if(empty($group_data) || !is_array($group_data)) {
        return $longest_period;
    }

    foreach($group_data  as $key => $period) {
        if(empty($longest_period)) {
            $longest_period = $period;
            continue;
        }
        $longest_period_time = strtotime($longest_period['periodEnd']) - strtotime($longest_period['periodBegin']);
        $period_time = strtotime($period['periodEnd']) - strtotime($period['periodBegin']);
        if($longest_period_time < $period_time) {
            $longest_period = $period;
        }
    }

    return $longest_period;
}

/**
 * Отдача файла на выгрузку
 * @params string $file - путь к файлу
 *
 */
function fileUpload($file)
{
    if (file_exists($file)) {
        // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
        // если этого не сделать файл будет читаться в память полностью!
        if (ob_get_level()) {
            ob_end_clean();
        }
        // заставляем браузер показать окно сохранения файла
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        // читаем файл и отправляем его пользователю
        readfile($file);
        exit;
    }
    echo 'Файл на выгрузку не найден';
}

/**
 * Основная функция
 *
 */
function main()
{
    global $input_file_path, $output_file_path, $first_data_line, $indexes;

    if(!file_exists($input_file_path)) {
        die("Файл $input_file_path не найден!");
    }

    $filereader_iterator = fileReader($input_file_path); // итератор чтения файла

    $groups_data = []; // соберем сгруппированный по группам интервалы

    foreach ($filereader_iterator as $iteration) {
        if(--$first_data_line > 0) { // пропускаем строки, пока не доберемся до первой строки данных
            continue;
        }
        if(empty($iteration)) { // если строка пуста
            continue;
        }
        $row_data = explode(';', $iteration);
        $row_data = array_combine($indexes, $row_data);
        $groups_data[$row_data['groupId']][] = $row_data;
    }

    /*
        На данном этапе в массиве $groups_data по groupId собраны все периоды и суммы по ним
    */

    ksort($groups_data); // отсортируем массив по группе
    $maxSumPeriod = []; // запишем период с максимальной суммой
    foreach($groups_data as $group_id => &$group_data) {
        $group_data = mergePeriods($group_data); // объединяем периоды
        $group_data = findLongestPeriod($group_data); // находим самый продолжительный период в группе
        if(empty($maxSumPeriod)) {
            $maxSumPeriod = $group_data;
        } elseif($maxSumPeriod['sumIntake'] < $group_data['sumIntake']) {
            $maxSumPeriod = $group_data;
        }
    }
    $groups_data[] = $maxSumPeriod; // последняя строка с максимальной суммой

    createOutputFile($groups_data); // создаем файл на выгрузку
    fileUpload($output_file_path); // отдаем созданный файл на загрузку
}

/**
 * Создает файл на выгрузку
 * @params array $groups_data - массив входных данных
 *
 */
function createOutputFile(array $groups_data): void
{
    global $output_file_path, $indexes;
    $fp = fopen($output_file_path, 'w');
    fputcsv($fp, $indexes, ';'); // добавим заголовки
    foreach ($groups_data as $fields) {
        fputcsv($fp, $fields, ';');
    }
    fclose($fp);
}

main(); // запускаем процесс обработки
