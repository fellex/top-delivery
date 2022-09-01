/*
    Делаем левый джоин заказов выполненных в городе с полностью выполненными заказами.
    В результате считаем количество заказов в городе, полностью выполненных, % полностью выполненных от выполненных в городе
*/
SELECT COUNT(`l`.`parcel_id`) AS `total`, COUNT(`d`.`parcel_id`) AS `done`, COUNT(`d`.`parcel_id`) * 100 / COUNT(`l`.`parcel_id`) AS `percent`
FROM `parcel_log` AS `l`
LEFT JOIN ( # выбираем все заказы выполненные полностью
    SELECT `parcel_id` FROM `parcel_log`
    WHERE `order_log_event_type_id` = 10
    AND `order_log_event_type_title` = 'Изменение статуса выполнения'
    AND `new_value` = 3
    AND `new_value_title` = 'Полностью выполнен'
) AS `d` ON `d`.`parcel_id` = `l`.`parcel_id`
WHERE `l`.`order_log_event_type_id` = 2
    AND `l`.`order_log_event_type_title` = 'Изменение статуса движения'
    AND `l`.`new_value` = 7
    AND `l`.`new_value_title` = 'Получен в городе'
ORDER BY `l`.`parcel_id`;