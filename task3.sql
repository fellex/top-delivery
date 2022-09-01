/*
    Делаем левый джоин выполненных заказов с отмененными заказами.
    Группируем по региону и дате.
    В результате считаем количество заказов (всего/отказ) в регионе по месяцам
*/
SELECT `r`.`title` AS `region`, CONCAT(YEAR(`l`.`date_create`), '-', MONTH(`l`.`date_create`)) AS `date`, COUNT(`l`.`parcel_id`) AS `done`, COUNT(`d`.`parcel_id`) AS `canceled`
FROM `parcel_log` AS `l`
INNER JOIN `parcels` AS `p` ON `p`.`parcel_id` = `l`.`parcel_id`
INNER JOIN `dir_regions` AS `r` ON `r`.`id` = `p`.`region_delivery_id`
LEFT JOIN ( # выбираем все отмененные заказы
    SELECT `parcel_id` FROM `parcel_log`
    WHERE `order_log_event_type_id` = 10
    AND `order_log_event_type_title` = 'Изменение статуса выполнения'
    AND `new_value` = 5
    AND `new_value_title` = 'Отказ'
) AS `d` ON `d`.`parcel_id` = `l`.`parcel_id`
WHERE `l`.`order_log_event_type_id` = 10
    AND `l`.`order_log_event_type_title` = 'Изменение статуса выполнения'
    AND `l`.`new_value` = 3
GROUP BY `r`.`id`, YEAR(`l`.`date_create`), MONTH(`l`.`date_create`)
ORDER BY `r`.`title`, YEAR(`l`.`date_create`), MONTH(`l`.`date_create`);