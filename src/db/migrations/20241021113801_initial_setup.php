<?php

declare(strict_types=1);

namespace Migrations;

use Phoenix\Migration\AbstractMigration;

final class InitialSetup extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
            CREATE TABLE `picu_album` (
                `id` varchar(36) NOT NULL PRIMARY KEY,
                `fid` varchar(100) NOT NULL,
                `provider` varchar(10) NOT NULL,
                `title` varchar(100),
                `description` text,
                `cover` varchar(100),
                `owner` varchar(50),
                `public` int(1) DEFAULT 0,
                `sort` int(5),
                `changed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_estonian_ci
        ");
        $this->execute("ALTER TABLE `picu_album` ADD UNIQUE INDEX `picu_album_uidx` (`provider`, `fid`)");

        $this->execute("
            CREATE TABLE `picu_item` (
                `id` varchar(36) NOT NULL PRIMARY KEY,
                `fid` varchar(100) NOT NULL,
                `album` varchar(36) NOT NULL,
                `title` varchar(100),
                `description` text,
                `type` varchar(10) NOT NULL DEFAULT 'image',
                `datetaken` datetime,
                `url` varchar(250),
                `width` int,
                `height` int,
                `metadata` text,
                `sort` int(5),
                `changed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_estonian_ci
        ");
        $this->execute("ALTER TABLE `picu_item` ADD UNIQUE INDEX `picu_item_uidx` (`album`, `fid`)");
        $this->execute("ALTER TABLE `picu_item` ADD CONSTRAINT `picu_item_fidx` FOREIGN KEY (`album`) REFERENCES `picu_album` (`id`)");
    }

    protected function down(): void
    {
        $this->execute("ALTER TABLE `picu_item` DROP CONSTRAINT picu_item_fidx");
        $this->table('picu_item')->drop();
        $this->table('picu_album')->drop();
    }
}
