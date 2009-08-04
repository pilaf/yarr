<?php

/*
 * Define the base public interface for ActiveRecord here to clean up the ActiveRecord main body
 * 
 * This is the fist class in the class definition chain, so extend DatabaseConnector to get pretty
 * access to the current connection through self::$connection;
 */
abstract class ActiveRecordBasePublicInstanceMethods extends DatabaseConnector
{
}