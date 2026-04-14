-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.4
-- Час створення: Квт 14 2026 р., 08:50
-- Версія сервера: 8.4.8
-- Версія PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `information_schema`
--

--
-- VIEW `TRIGGERS`
-- Дані: Жодного
--


-- --------------------------------------------------------

--
-- Структура для представлення `TRIGGERS`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`mysql.infoschema`@`localhost` SQL SECURITY DEFINER VIEW `TRIGGERS`  AS SELECT (`cat`.`name` collate utf8mb3_tolower_ci) AS `TRIGGER_CATALOG`, (`sch`.`name` collate utf8mb3_tolower_ci) AS `TRIGGER_SCHEMA`, `trg`.`name` AS `TRIGGER_NAME`, `trg`.`event_type` AS `EVENT_MANIPULATION`, (`cat`.`name` collate utf8mb3_tolower_ci) AS `EVENT_OBJECT_CATALOG`, (`sch`.`name` collate utf8mb3_tolower_ci) AS `EVENT_OBJECT_SCHEMA`, (`tbl`.`name` collate utf8mb3_tolower_ci) AS `EVENT_OBJECT_TABLE`, `trg`.`action_order` AS `ACTION_ORDER`, NULL AS `ACTION_CONDITION`, `trg`.`action_statement_utf8` AS `ACTION_STATEMENT`, 'ROW' AS `ACTION_ORIENTATION`, `trg`.`action_timing` AS `ACTION_TIMING`, NULL AS `ACTION_REFERENCE_OLD_TABLE`, NULL AS `ACTION_REFERENCE_NEW_TABLE`, 'OLD' AS `ACTION_REFERENCE_OLD_ROW`, 'NEW' AS `ACTION_REFERENCE_NEW_ROW`, `trg`.`created` AS `CREATED`, `trg`.`sql_mode` AS `SQL_MODE`, `trg`.`definer` AS `DEFINER`, `cs_client`.`name` AS `CHARACTER_SET_CLIENT`, `coll_conn`.`name` AS `COLLATION_CONNECTION`, `coll_db`.`name` AS `DATABASE_COLLATION` FROM (((((((`mysql`.`triggers` `trg` join `mysql`.`tables` `tbl` on((`tbl`.`id` = `trg`.`table_id`))) join `mysql`.`schemata` `sch` on((`tbl`.`schema_id` = `sch`.`id`))) join `mysql`.`catalogs` `cat` on((`cat`.`id` = `sch`.`catalog_id`))) join `mysql`.`collations` `coll_client` on((`coll_client`.`id` = `trg`.`client_collation_id`))) join `mysql`.`character_sets` `cs_client` on((`cs_client`.`id` = `coll_client`.`character_set_id`))) join `mysql`.`collations` `coll_conn` on((`coll_conn`.`id` = `trg`.`connection_collation_id`))) join `mysql`.`collations` `coll_db` on((`coll_db`.`id` = `trg`.`schema_collation_id`))) WHERE ((`tbl`.`type` <> 'VIEW') AND (0 <> can_access_trigger(`sch`.`name`,`tbl`.`name`)) AND (0 <> is_visible_dd_object(`tbl`.`hidden`))) ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
