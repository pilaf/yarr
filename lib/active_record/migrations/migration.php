<?php

interface Migration {
	public static function up($db);
	public static function down($db);
}