-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.4
-- Час створення: Квт 14 2026 р., 08:52
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
-- VIEW `VIEWS`
-- Дані: Жодного
--


-- --------------------------------------------------------

--
-- Структура для представлення `VIEWS`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`mysql.infoschema`@`localhost` SQL SECURITY DEFINER VIEW `VIEWS`  AS SELECT (`cat`.`name` collate utf8mb3_tolower_ci) AS `TABLE_CATALOG`, (`sch`.`name` collate utf8mb3_tolower_ci) AS `TABLE_SCHEMA`, (`vw`.`name` collate utf8mb3_tolower_ci) AS `TABLE_NAME`, if((can_access_view(`sch`.`name`,`vw`.`name`,`vw`.`view_definer`,`vw`.`options`) = true),`vw`.`view_definition_utf8`,'') AS `VIEW_DEFINITION`, `vw`.`view_check_option` AS `CHECK_OPTION`, `vw`.`view_is_updatable` AS `IS_UPDATABLE`, `vw`.`view_definer` AS `DEFINER`, if((`vw`.`view_security_type` = 'DEFAULT'),'DEFINER',`vw`.`view_security_type`) AS `SECURITY_TYPE`, `cs`.`name` AS `CHARACTER_SET_CLIENT`, `conn_coll`.`name` AS `COLLATION_CONNECTION` FROM (((((`mysql`.`tables` `vw` join `mysql`.`schemata` `sch` on((`vw`.`schema_id` = `sch`.`id`))) join `mysql`.`catalogs` `cat` on((`cat`.`id` = `sch`.`catalog_id`))) join `mysql`.`collations` `conn_coll` on((`conn_coll`.`id` = `vw`.`view_connection_collation_id`))) join `mysql`.`collations` `client_coll` on((`client_coll`.`id` = `vw`.`view_client_collation_id`))) join `mysql`.`character_sets` `cs` on((`cs`.`id` = `client_coll`.`character_set_id`))) WHERE ((0 <> can_access_table(`sch`.`name`,`vw`.`name`)) AND (`vw`.`type` = 'VIEW')) ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
