CREATE TABLE `xarb_article` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `title` varchar(255) NOT NULL,
 `sub_title` varchar(255) NOT NULL,
 `content` text NOT NULL,
 `type_name` varchar(60) NOT NULL,
 `create_date` date NOT NULL,
 `rel_article_id` int(11) NOT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `rel_article_id` (`rel_article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
