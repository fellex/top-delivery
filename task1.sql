/*
    Делаем левый джоин заказов с логом. В джоине дополнительно указываем нужный статус.
    Если по джоину отсутствуют данные, значит такого статуса для этого заказа нет.
    Остается сгруппировать по месяцам в году и подсчитать количество.
*/
SELECT CONCAT(YEAR(`p`.`date_create`), '-', MONTH(`p`.`date_create`)) AS `date`, COUNT(`p`.`parcel_id`) AS `cnt`
FROM `parcels` AS `p`
LEFT JOIN `parcel_log` AS `l` ON
    `l`.`parcel_id` = `p`.`parcel_id`
    AND `l`.`order_log_event_type_id` = 2
    AND `l`.`order_log_event_type_title` = 'Изменение статуса движения'
    AND `l`.`new_value` = 3
    AND `l`.`new_value_title` = 'Получен в ТД'
WHERE `l`.`id` IS NULL
GROUP BY YEAR(`p`.`date_create`), MONTH(`p`.`date_create`);